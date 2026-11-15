<?php
/**
 * Controller for frontend/refund_details.php
 */

function sfbc_handle_refund_details_page(mysqli $conn): array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    header('Referrer-Policy: no-referrer');

    $queryToken = trim((string)($_GET['token'] ?? ''));
    if (preg_match('/^[a-f0-9]{64}$/', $queryToken)) {
        $_SESSION['manual_refund_token'] = $queryToken;
        header('Location: refund_details.php', true, 303);
        exit;
    }

    $token = trim((string)($_SESSION['manual_refund_token'] ?? ''));
    $valid = false;
    $refund = null;

    if (preg_match('/^[a-f0-9]{64}$/', $token)) {
        $hash = hash('sha256', $token);
        $stmt = $conn->prepare("SELECT r.net_refund,r.booking_id,b.guest_name,b.guest_email,d.token_expires_at,d.email_verified_at,d.verification_locked_at FROM manual_refund_details d JOIN refund_requests r ON r.id=d.refund_request_id JOIN bookings b ON b.id=r.booking_id WHERE d.token_hash=? AND d.token_expires_at>NOW() AND d.submitted_at IS NULL AND r.status='awaiting_guest_details' LIMIT 1");
        $stmt->bind_param('s', $hash);
        $stmt->execute();
        $refund = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $valid = (bool)$refund;
        if (!$valid) {
            unset($_SESSION['manual_refund_token']);
        }
    }

    $email = (string)($refund['guest_email'] ?? '');
    $at = strpos($email, '@');
    $maskedEmail = $at === false
        ? 'the email saved with your booking'
        : substr($email, 0, min(2, $at)) . str_repeat('•', max(3, $at - 2)) . substr($email, $at);
    $emailReady = filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    $verified = !empty($refund['email_verified_at']);

    return compact('token', 'valid', 'refund', 'maskedEmail', 'emailReady', 'verified');
}
