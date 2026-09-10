<?php
/**
 * guest_lookup_api.php
 * AJAX endpoint: verify a guest by email + booking reference.
 * Returns booking + payment data as JSON on success.
 *
 * POST params:
 *   email        (string) — guest email address
 *   booking_ref  (string) — e.g. REF-042
 *   csrf_token   (string) — CSRF token
 */

require_once __DIR__ . '/../../backend/config/db.php';
require_once __DIR__ . '/../../backend/helpers/security_headers.php';
require_once __DIR__ . '/../../backend/helpers/csrf_helper.php';
require_once __DIR__ . '/../../backend/helpers/rate_limiter.php';
require_once __DIR__ . '/../../backend/helpers/guest_auth_helper.php';
require_once __DIR__ . '/../../backend/helpers/recaptcha_helper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

// CSRF check
if (!verify_csrf_token()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Security validation failed. Please refresh and try again.']);
    exit;
}

// ── reCAPTCHA v3 Verification ────────────────────────────
$recaptchaToken  = $_POST['g-recaptcha-response'] ?? '';
$recaptchaResult = recaptcha_verify($recaptchaToken, 'guest_lookup');
if (!$recaptchaResult['success']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => $recaptchaResult['error'] ?: 'Security check failed. Please try again.']);
    exit;
}

// Rate limiting — max 10 lookup attempts per 15 min per IP
$rateStatus = RateLimiter::check($conn, 'guest_lookup', 10, 900);
if (!$rateStatus['allowed']) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'error'   => "Too many attempts. Please wait {$rateStatus['retry_after']} seconds before trying again."
    ]);
    exit;
}

// Sanitise inputs
$raw_email = trim($_POST['email'] ?? '');
$raw_ref   = strtoupper(trim($_POST['booking_ref'] ?? ''));

if (empty($raw_email) || empty($raw_ref)) {
    echo json_encode(['success' => false, 'error' => 'Please enter both your email and booking reference.']);
    exit;
}

if (!filter_var($raw_email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Please enter a valid email address.']);
    exit;
}

// Parse booking ID from REF-XXXX format
if (!preg_match('/^REF-(\d+)$/i', $raw_ref, $m)) {
    RateLimiter::hit($conn, 'guest_lookup');
    echo json_encode(['success' => false, 'error' => 'Booking not found. Please check your reference and email.']);
    exit;
}
$booking_id = (int)$m[1];

// Lookup booking — email must match too (prevents enumeration by ref only)
$stmt = $conn->prepare("
    SELECT
        b.id,
        b.guest_name,
        b.guest_email,
        b.guest_type,
        b.accommodation_name,
        b.room_id,
        b.check_in,
        b.check_out,
        b.guests_count,
        b.eta,
        b.status,
        b.cancellation_token,
        b.cancellation_reason,
        b.checkin_token,
        b.payment_method,
        b.guest_phone,
        b.guest_country,
        b.guest_special_requests,
        b.created_at,
        DATEDIFF(b.check_out, b.check_in) AS nights
    FROM bookings b
    WHERE b.id = ? AND LOWER(b.guest_email) = LOWER(?)
    LIMIT 1
");
$stmt->bind_param("is", $booking_id, $raw_email);
$stmt->execute();
$result  = $stmt->get_result();
$booking = $result->fetch_assoc();
$stmt->close();

if (!$booking) {
    RateLimiter::hit($conn, 'guest_lookup');
    echo json_encode(['success' => false, 'error' => 'Booking not found. Please check your reference and email.']);
    exit;
}

// Fetch payment record
$pay_stmt = $conn->prepare("
    SELECT
        p.id AS pay_id,
        p.amount,
        p.payment_method,
        p.transaction_id,
        p.status AS payment_status,
        p.paid_at
    FROM payments p
    WHERE p.booking_id = ?
    ORDER BY p.id DESC
    LIMIT 1
");
$pay_stmt->bind_param("i", $booking_id);
$pay_stmt->execute();
$payment = $pay_stmt->get_result()->fetch_assoc();
$pay_stmt->close();

// Build the booking reference string
$booking['booking_ref'] = 'REF-' . str_pad($booking['id'], 3, '0', STR_PAD_LEFT);

// Build base URL for cancel / checkin links
$base_url    = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
             . '://' . $_SERVER['HTTP_HOST']
             . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
// The API is 2 levels deep (backend/api/), so go up to frontend root
$frontend_url = rtrim(dirname(dirname($base_url)), '/');

$booking['cancel_url']  = $frontend_url . '/cancel_booking?token=' . urlencode($booking['cancellation_token']);
$booking['checkin_url'] = $frontend_url . '/checkin?ref=' . urlencode($booking['booking_ref']) . '&token=' . urlencode($booking['checkin_token'] ?? '');

// MFA: Generate OTP for Guest and require 2FA verification
require_once __DIR__ . '/../../backend/helpers/otp_helper.php';
require_once __DIR__ . '/../../backend/services/mailer.php';

$rawOtp = otp_generate();
otp_store_for_guest((int)$booking['id'], $rawOtp, $conn);
$sendResult = otp_send_email($raw_email, $rawOtp, $booking['guest_name']);

// Store temporary pending guest state in session (not full session)
$_SESSION['mfa_pending_guest'] = [
    'booking'      => $booking,
    'payment'      => $payment,
    'booking_id'   => (int)$booking['id'],
    'guest_email'  => $raw_email,
    'email_hint'   => substr($raw_email, 0, 3) . '***' . strstr($raw_email, '@'),
    'otp_sent_at'  => time(),
];

echo json_encode([
    'success' => true,
    'mfa'     => true,
    'message' => 'Verification code sent to your email.'
]);
exit;
