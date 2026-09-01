<?php
/**
 * paymongo_webhook.php
 * Handles asynchronous PayMongo Webhook events (e.g. checkout_session.payment.paid)
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../services/mailer.php';
require_once __DIR__ . '/../services/paymongo.php';

header('Content-Type: application/json');

$rawInput = file_get_contents('php://input');
if (empty($rawInput)) {
    http_response_code(400);
    echo json_encode(['error' => 'No payload received']);
    exit;
}

// Verify PayMongo webhook signature if secret key & header exist
$webhookSecret = PayMongoService::getWebhookSecretKey();
$signatureHeader = $_SERVER['HTTP_PAYMONGO_SIGNATURE'] ?? ($_SERVER['HTTP_PAYMONGO_SIGNATURE_HEADER'] ?? '');

if (!empty($webhookSecret) && !empty($signatureHeader)) {
    // PayMongo signature header format: t=timestamp,te=test_signature,li=live_signature
    $parts = explode(',', $signatureHeader);
    $sigValues = [];
    foreach ($parts as $part) {
        $kv = explode('=', trim($part), 2);
        if (count($kv) === 2) {
            $sigValues[$kv[0]] = $kv[1];
        }
    }
    
    $timestamp = $sigValues['t'] ?? '';
    $expectedSig = $sigValues['li'] ?? ($sigValues['te'] ?? '');
    
    if ($timestamp && $expectedSig) {
        $signedPayload = $timestamp . '.' . $rawInput;
        $computedSig = hash_hmac('sha256', $signedPayload, $webhookSecret);
        if (!hash_equals($computedSig, $expectedSig)) {
            error_log('[PayMongo Webhook] Invalid signature verification - unauthorized payload rejected');
            http_response_code(401);
            echo json_encode(['error' => 'Invalid signature verification']);
            exit;
        }
    }
}

$event = json_decode($rawInput, true);
$eventType = $event['data']['attributes']['type'] ?? '';

if ($eventType === 'checkout_session.payment.paid' || $eventType === 'payment.paid') {
    $sessionData = $event['data']['attributes']['data'] ?? [];
    $metadata    = $sessionData['attributes']['metadata'] ?? [];
    $bookingId   = isset($metadata['booking_id']) ? (int)$metadata['booking_id'] : 0;
    $payments    = $sessionData['attributes']['payments'] ?? [];
    $sessionId   = $sessionData['id'] ?? '';

    // Determine payment channel and reference ID
    $paymentChannel = 'PayMongo Online';
    $transactionId  = $sessionId;

    if (!empty($payments) && is_array($payments)) {
        foreach ($payments as $p) {
            $pAttr = $p['attributes'] ?? [];
            if (($pAttr['status'] ?? '') === 'paid') {
                $transactionId = $p['id'] ?? $sessionId;
                $sourceType = $pAttr['source']['type'] ?? ($pAttr['payment_method_type'] ?? 'Online');
                $paymentChannel = 'PayMongo (' . ucfirst($sourceType) . ')';
                break;
            }
        }
    }

    if ($bookingId > 0) {
        // 1. Update Payment record to verified
        $stmt = $conn->prepare("UPDATE payments SET status = 'verified', payment_method = ?, transaction_id = ? WHERE booking_id = ?");
        $stmt->bind_param("ssi", $paymentChannel, $transactionId, $bookingId);
        $stmt->execute();
        $stmt->close();

        // 2. Update Booking record to Confirmed
        $stmt = $conn->prepare("UPDATE bookings SET status = 'Confirmed' WHERE id = ?");
        $stmt->bind_param("i", $bookingId);
        $stmt->execute();
        $stmt->close();

        // 3. Log into payment_action_history
        $payCheck = $conn->prepare("SELECT id FROM payments WHERE booking_id = ? LIMIT 1");
        $payCheck->bind_param("i", $bookingId);
        $payCheck->execute();
        $pRow = $payCheck->get_result()->fetch_assoc();
        $payCheck->close();

        if ($pRow) {
            $pId = (int)$pRow['id'];
            $hist = $conn->prepare("INSERT INTO payment_action_history (payment_id, action, performed_by, details) VALUES (?, 'verified', 'PayMongo Webhook', ?)");
            $details = 'Auto-confirmed via PayMongo Webhook (' . $paymentChannel . '). Ref: ' . $transactionId;
            $hist->bind_param("is", $pId, $details);
            $hist->execute();
            $hist->close();
        }

        // 3b. Add System Notification for Admin Dashboard & SSE Stream
        $bRef = 'REF-' . str_pad($bookingId, 3, '0', STR_PAD_LEFT);
        $notifTitle = 'Online Payment Received';
        $notifMsg = 'Payment for ' . $bRef . ' was auto-verified via PayMongo (' . $paymentChannel . '). Ref: ' . $transactionId;
        $notifType = 'payment';

        // Prevent duplicate notification if payment_success.php already inserted one recently
        $chkNotif = $conn->prepare("SELECT id FROM notifications WHERE booking_id = ? AND title LIKE '%Payment%' LIMIT 1");
        $chkNotif->bind_param("i", $bookingId);
        $chkNotif->execute();
        if ($chkNotif->get_result()->num_rows === 0) {
            $nStmt = $conn->prepare("INSERT INTO notifications (title, message, type, booking_id) VALUES (?, ?, ?, ?)");
            $nStmt->bind_param("sssi", $notifTitle, $notifMsg, $notifType, $bookingId);
            $nStmt->execute();
            $nStmt->close();
        }
        $chkNotif->close();

        // 4. Dispatch booking confirmation email with QR code (only if not already sent)
        $bStmt = $conn->prepare("SELECT guest_name, guest_email, accommodation_name, check_in, check_out, cancellation_token, checkin_token, confirmation_email_sent_at FROM bookings WHERE id = ? LIMIT 1");
        $bStmt->bind_param("i", $bookingId);
        $bStmt->execute();
        $bData = $bStmt->get_result()->fetch_assoc();
        $bStmt->close();

        if ($bData && !empty($bData['guest_email']) && empty($bData['confirmation_email_sent_at'])) {
            $mStmt = $conn->prepare("UPDATE bookings SET confirmation_email_sent_at = NOW() WHERE id = ? AND confirmation_email_sent_at IS NULL");
            $mStmt->bind_param("i", $bookingId);
            $mStmt->execute();
            $affected = $mStmt->affected_rows;
            $mStmt->close();

            if ($affected > 0) {
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
