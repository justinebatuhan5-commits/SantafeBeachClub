<?php
/**
 * paymongo_webhook.php
 * Handles asynchronous PayMongo Webhook events (e.g. checkout_session.payment.paid)
 */

require_once __DIR__ . '/../config/env_loader.php';
load_env(__DIR__ . '/../../.env');
require_once __DIR__ . '/../services/paymongo.php';
require_once __DIR__ . '/../helpers/paymongo_signature.php';

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$rawInput = file_get_contents('php://input');
if (empty($rawInput)) {
    http_response_code(400);
    echo json_encode(['error' => 'No payload received']);
    exit;
}

// Fail closed: no database or mailer initialization before authentication.
$webhookSecret = PayMongoService::getWebhookSecretKey();
$signatureHeader = $_SERVER['HTTP_PAYMONGO_SIGNATURE'] ?? ($_SERVER['HTTP_PAYMONGO_SIGNATURE_HEADER'] ?? '');
$apiKey = PayMongoService::getSecretKey();
if (trim($webhookSecret) === '' || !preg_match('/^sk_(live|test)_/', $apiKey, $mode)) {
    error_log('[PayMongo Webhook] Signing secret or API mode is not configured');
    http_response_code(503);
    echo json_encode(['error' => 'Webhook verification is not configured']);
    exit;
}
if (!verify_paymongo_signature($rawInput, $signatureHeader, $webhookSecret, $mode[1] === 'live')) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid webhook signature']);
    exit;
}

$event = json_decode($rawInput, true);
if (!is_array($event) || json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload']);
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../services/mailer.php';

$eventType = $event['data']['attributes']['type'] ?? '';

require_once __DIR__ . '/../services/paymongo_confirmation.php';

// Standalone payment events lack the immutable checkout binding used here.
if ($eventType === 'checkout_session.payment.paid') {
    $sessionData = $event['data']['attributes']['data'] ?? [];
    $bookingId = (int)($sessionData['attributes']['metadata']['booking_id'] ?? 0);
    $sessionId = $sessionData['id'] ?? '';
    try {
        $confirmation = pm_confirm_checkout($conn, $bookingId, $sessionId, $sessionData);
    } catch (Throwable $e) {
        error_log('[PayMongo Webhook] Checkout confirmation rejected for booking ' . $bookingId);
        if (pm_query($conn, 'SELECT booking_id FROM booking_discounts WHERE booking_id=?', [$bookingId])->get_result()->fetch_assoc()) {
            // A late payment must never silently reclaim a room after its hold expired.
            if (!pm_query($conn, "SELECT id FROM notifications WHERE booking_id=? AND title='Discount payment needs attention' LIMIT 1", [$bookingId])->get_result()->fetch_assoc()) {
                pm_query($conn, "INSERT INTO notifications (title,message,type,booking_id) VALUES ('Discount payment needs attention',?,'warning',?)", ['A provider payment could not confirm REF-' . $bookingId . '. Review the provider transaction and room availability; arrange a refund or new booking as appropriate.', $bookingId]);
            }
        }
        http_response_code(409);
        echo json_encode(['error' => 'Checkout could not be confirmed']);
        exit;
    }
    $discountBooking = pm_query($conn, 'SELECT booking_id FROM booking_discounts WHERE booking_id=?', [$bookingId])->get_result()->fetch_assoc();
    if ($discountBooking && $confirmation['new']) discount_email($conn, $bookingId);
    if ($bookingId > 0 && !$discountBooking) {
        // 4. Dispatch booking confirmation email with QR code (only if not already sent)
        $bStmt = $conn->prepare("SELECT guest_name, guest_email, accommodation_name, check_in, check_out, cancellation_token, checkin_token, confirmation_email_sent_at FROM bookings WHERE id = ? LIMIT 1");
        $bStmt->bind_param("i", $bookingId);
        $bStmt->execute();
        $bData = $bStmt->get_result()->fetch_assoc();
        $bStmt->close();

        if ($bData && !empty($bData['guest_email']) && empty($bData['confirmation_email_sent_at'])) {
            { // The mailer serializes sends and records successful delivery.
                $bRef = 'REF-' . str_pad($bookingId, 3, '0', STR_PAD_LEFT);
                $baseUrl = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/SantaBeachClub-BookingSystem/frontend';
                $cancellationUrl = rtrim($baseUrl, '/') . '/cancel_booking?token=' . urlencode($bData['cancellation_token'] ?? '');
                $checkinUrl = rtrim($baseUrl, '/') . '/checkin?ref=' . urlencode($bRef) . '&token=' . urlencode($bData['checkin_token'] ?? '');

                $amtStmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) AS amount FROM payments WHERE booking_id = ? AND status = 'verified'");
                $amtStmt->bind_param("i", $bookingId);
                $amtStmt->execute();
                $bAmount = (float)($amtStmt->get_result()->fetch_assoc()['amount'] ?? 0);
                $amtStmt->close();

                @sendBookingConfirmationEmail(
                    $bData['guest_email'],
                    $bData['guest_name'],
                    $bRef,
                    $bData['accommodation_name'],
                    $bData['check_in'],
                    $bData['check_out'],
                    $bAmount,
                    $cancellationUrl,
                    $checkinUrl
                );
            }
        }
    }
}

http_response_code(200);
echo json_encode(['received' => true]);
