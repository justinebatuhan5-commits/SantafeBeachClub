<?php
/**
 * guest_verify_otp_api.php
 * Endpoint to verify OTP for guest booking portal login or resend OTP.
 */

require_once __DIR__ . '/../../backend/config/db.php';
require_once __DIR__ . '/../../backend/helpers/security_headers.php';
require_once __DIR__ . '/../../backend/helpers/csrf_helper.php';
require_once __DIR__ . '/../../backend/helpers/guest_auth_helper.php';
require_once __DIR__ . '/../../backend/helpers/otp_helper.php';
require_once __DIR__ . '/../../backend/helpers/recaptcha_helper.php';
require_once __DIR__ . '/../../backend/helpers/security_logger.php';
require_once __DIR__ . '/../../backend/services/mailer.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

if (!verify_csrf_token()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Security validation failed. Please refresh.']);
    exit;
}

if (!isset($_SESSION['mfa_pending_guest'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No verification in progress. Please search again.']);
    exit;
}

$pending = $_SESSION['mfa_pending_guest'];
$bookingId = (int)$pending['booking_id'];
$guestEmail = $pending['guest_email'];
$guestName  = $pending['booking']['guest_name'] ?? 'Guest';

$action = $_POST['action'] ?? 'verify';
$recaptchaAction = ($action === 'resend') ? 'guest_otp_resend' : 'guest_otp_verify';
$recaptchaToken  = is_string($_POST['g-recaptcha-response'] ?? null) ? $_POST['g-recaptcha-response'] : '';
$recaptchaResult = recaptcha_verify($recaptchaToken, $recaptchaAction, true);
if (!$recaptchaResult['success']) {
    SecurityLogger::log(
        $conn,
        'RECAPTCHA_FAIL',
        'reCAPTCHA failed on ' . $recaptchaAction . ' (score: ' . $recaptchaResult['score'] . ')',
        SecurityLogger::LEVEL_WARNING,
        $guestEmail
    );
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => $recaptchaResult['error'] ?: 'Security check failed. Please try again.']);
    exit;
}

if ($action === 'resend') {
    $lastSent = $pending['otp_sent_at'] ?? 0;
    $elapsed = time() - $lastSent;
    if ($elapsed < OTP_RESEND_COOLDOWN) {
        $wait = OTP_RESEND_COOLDOWN - $elapsed;
        echo json_encode(['success' => false, 'error' => "Please wait {$wait}s before requesting a new code."]);
        exit;
    }

    $rawOtp = otp_generate();
    otp_store_for_guest($bookingId, $rawOtp, $conn);
    $sendRes = otp_send_email($guestEmail, $rawOtp, $guestName);

    $_SESSION['mfa_pending_guest']['otp_sent_at'] = time();

    if ($sendRes['success']) {
        echo json_encode(['success' => true, 'message' => 'A new code has been sent to your email.']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to send email. Please try again later.']);
    }
    exit;
}

// Default: Verify OTP
$otp = trim($_POST['otp'] ?? '');
if (empty($otp) || !preg_match('/^[0-9]{6}$/', $otp)) {
    echo json_encode(['success' => false, 'error' => 'Please enter the 6-digit numeric verification code.']);
    exit;
}

$verify = otp_verify_guest($bookingId, $otp, $conn);

if ($verify['success']) {
    // Complete guest portal login
    set_guest_booking([
        'booking' => $pending['booking'],
        'payment' => $pending['payment']
    ]);

    unset($_SESSION['mfa_pending_guest']);
    echo json_encode(['success' => true, 'message' => 'Verified successfully!']);
    exit;
}

if ($verify['reason'] === 'locked_out') {
    unset($_SESSION['mfa_pending_guest']);
    echo json_encode(['success' => false, 'error' => 'Maximum verification attempts reached. Please start over.', 'reset' => true]);
    exit;
}

if ($verify['reason'] === 'expired_or_not_found') {
    echo json_encode(['success' => false, 'error' => 'Code has expired. Please click resend code.']);
    exit;
}

$rem = $verify['remaining'] ?? 0;
echo json_encode(['success' => false, 'error' => "Invalid code. {$rem} attempt(s) remaining."]);
exit;
