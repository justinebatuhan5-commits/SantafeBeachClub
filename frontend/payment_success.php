<?php
/**
 * payment_success.php
 * Guest landing page when returning from PayMongo hosted checkout.
 */

require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/services/mailer.php';
require_once __DIR__ . '/../backend/services/paymongo.php';

$bookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
$sessionId = isset($_GET['session_id']) ? trim($_GET['session_id']) : '';

$isVerified = false;
$bookingData = null;
$paymentMethodUsed = 'PayMongo Online';

if ($bookingId > 0) {
    // 1. If we have a sessionId, query PayMongo directly to verify payment status
    if (!empty($sessionId)) {
        $sessionInfo = PayMongoService::retrieveCheckoutSession($sessionId);
        if (!empty($sessionInfo['success']) && !empty($sessionInfo['is_paid'])) {
            $isVerified = true;
            $paymentMethodUsed = $sessionInfo['payment_method_used'] ?? 'PayMongo Online';
            $paidTxnId = $sessionInfo['paid_payment_id'] ?? $sessionId;

            // Update Payments table
            $stmt = $conn->prepare("UPDATE payments SET status = 'verified', payment_method = ?, transaction_id = ? WHERE booking_id = ?");
            $stmt->bind_param("ssi", $paymentMethodUsed, $paidTxnId, $bookingId);
            $stmt->execute();
            $stmt->close();

            // Update Bookings table
            $stmt = $conn->prepare("UPDATE bookings SET status = 'Confirmed' WHERE id = ?");
            $stmt->bind_param("i", $bookingId);
            $stmt->execute();
            $stmt->close();

            // Record action history if not already recorded
            $histCheck = $conn->prepare("SELECT id FROM payment_action_history WHERE payment_id = (SELECT id FROM payments WHERE booking_id = ? LIMIT 1) AND action = 'verified'");
            $histCheck->bind_param("i", $bookingId);
            $histCheck->execute();
            if ($histCheck->get_result()->num_rows === 0) {
                $payIdStmt = $conn->prepare("SELECT id FROM payments WHERE booking_id = ? LIMIT 1");
                $payIdStmt->bind_param("i", $bookingId);
                $payIdStmt->execute();
                $payRow = $payIdStmt->get_result()->fetch_assoc();
                $payIdStmt->close();

                if ($payRow) {
                    $pid = (int)$payRow['id'];
                    $details = 'Auto-confirmed via PayMongo Checkout (' . $paymentMethodUsed . '). Ref: ' . $paidTxnId;
                    $hStmt = $conn->prepare("INSERT INTO payment_action_history (payment_id, action, performed_by, details) VALUES (?, 'verified', 'PayMongo System', ?)");
                    $hStmt->bind_param("is", $pid, $details);
                    $hStmt->execute();
                    $hStmt->close();
                }

                // Add notification to admin dashboard
                $bRef = 'REF-' . str_pad($bookingId, 3, '0', STR_PAD_LEFT);
                $nTitle = 'Online Payment Verified';
                $nMsg = 'Payment of ₱' . number_format($sessionInfo['session']['attributes']['line_items'][0]['amount'] / 100 ?? 0, 2) . ' for ' . $bRef . ' was auto-verified via PayMongo (' . $paymentMethodUsed . ').';
                $nType = 'success';
                $nStmt = $conn->prepare("INSERT INTO notifications (title, message, type, booking_id) VALUES (?, ?, ?, ?)");
                $nStmt->bind_param("sssi", $nTitle, $nMsg, $nType, $bookingId);
                $nStmt->execute();
                $nStmt->close();
            }
            $histCheck->close();
        }
    }

    // 2. Fetch booking details for display
    $stmt = $conn->prepare("SELECT * FROM bookings WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $bookingId);
    $stmt->execute();
    $bookingData = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($bookingData) {
        if ($bookingData['status'] === 'Confirmed') {
            $isVerified = true;
        }

        // Trigger confirmation email if not already sent
        if ($isVerified && !empty($bookingData['guest_email']) && empty($bookingData['confirmation_email_sent_at'])) {
            // Immediately mark as sent in DB to prevent duplicates on refresh or concurrent requests
            $mStmt = $conn->prepare("UPDATE bookings SET confirmation_email_sent_at = NOW() WHERE id = ? AND confirmation_email_sent_at IS NULL");
            $mStmt->bind_param("i", $bookingId);
            $mStmt->execute();
            $affected = $mStmt->affected_rows;
            $mStmt->close();

            if ($affected > 0) {
                $bRef = 'REF-' . str_pad($bookingId, 3, '0', STR_PAD_LEFT);
                $baseUrl = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . dirname($_SERVER['SCRIPT_NAME']);
                $cancellationUrl = rtrim($baseUrl, '/') . '/cancel_booking?token=' . urlencode($bookingData['cancellation_token'] ?? '');
                $checkinUrl = rtrim($baseUrl, '/') . '/checkin?ref=' . urlencode($bRef) . '&token=' . urlencode($bookingData['checkin_token'] ?? '');

                $amtStmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) AS amount FROM payments WHERE booking_id = ? AND status = 'verified'");
                $amtStmt->bind_param("i", $bookingId);
                $amtStmt->execute();
                $bAmount = (float)($amtStmt->get_result()->fetch_assoc()['amount'] ?? 0);
                $amtStmt->close();

                @sendBookingConfirmationEmail(
                    $bookingData['guest_email'],
                    $bookingData['guest_name'],
                    $bRef,
                    $bookingData['accommodation_name'],
                    $bookingData['check_in'],
                    $bookingData['check_out'],
                    $bAmount,
                    $cancellationUrl,
                    $checkinUrl
                );
            }
        }
    }
}

$bookingRef = $bookingId > 0 ? ('REF-' . str_pad($bookingId, 3, '0', STR_PAD_LEFT)) : 'N/A';
$guestName  = $bookingData['guest_name'] ?? 'Guest';
$roomName   = $bookingData['accommodation_name'] ?? 'Room';
$checkInDate = !empty($bookingData['check_in']) ? date('M j, Y', strtotime($bookingData['check_in'])) : '—';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/svg+xml" sizes="any" href="assets/icons/favicon-circle.svg?v=2">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful — Santa Fe Beach Club</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #EBF8F2;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: #0F172A;
        }
        .success-card {
            background: #ffffff;
            border-radius: 24px;
            box-shadow: 0 10px 40px rgba(16, 185, 129, 0.08), 0 4px 12px rgba(0,0,0,0.03);
            max-width: 480px;
            width: 100%;
            padding: 40px 32px;
            text-align: center;
            animation: fadeIn 0.4s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .check-icon-circle {
            width: 76px;
            height: 76px;
            background-color: #D1FAE5;
            color: #059669;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }
        .check-icon-circle svg {
            width: 38px;
            height: 38px;
        }
        .success-title {
            font-size: 26px;
            font-weight: 800;
            color: #065F46;
            margin-bottom: 8px;
            letter-spacing: -0.02em;
        }
        .success-desc {
            font-size: 14.5px;
            color: #475569;
            line-height: 1.55;
            margin-bottom: 28px;
        }
        .details-box {
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 16px;
            padding: 18px 20px;
            margin-bottom: 28px;
            text-align: left;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 9px 0;
            font-size: 13.5px;
            border-bottom: 1px solid #F1F5F9;
        }
        .detail-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
        .detail-row:first-child {
            padding-top: 0;
        }
        .detail-label {
            color: #64748B;
            font-weight: 500;
        }
        .detail-value {
            color: #0F172A;
            font-weight: 700;
            text-align: right;
        }
        .btn-group {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .btn-primary {
            display: block;
            width: 100%;
            padding: 14px;
            background: #059669;
            color: #ffffff;
            font-size: 15px;
            font-weight: 700;
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.2s ease;
            box-shadow: 0 4px 14px rgba(5, 150, 105, 0.3);
        }
        .btn-primary:hover {
            background: #047857;
            transform: translateY(-1px);
        }
        .btn-secondary {
            display: block;
            width: 100%;
            padding: 13px;
            background: #F1F5F9;
            color: #475569;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.2s ease;
        }
        .btn-secondary:hover {
            background: #E2E8F0;
            color: #0F172A;
        }
    </style>
</head>
<body>

<div class="success-card">
    <div class="check-icon-circle">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="20 6 9 17 4 12"></polyline>
        </svg>
    </div>

    <h1 class="success-title">Payment Successful!</h1>
    <p class="success-desc">
        Thank you! Your payment has been received and automatically verified. A confirmation email with your check-in QR code pass has been sent.
    </p>

    <div class="details-box">
        <div class="detail-row">
            <span class="detail-label">Booking Reference:</span>
            <span class="detail-value" style="color: #059669; font-size: 14.5px;"><?php echo htmlspecialchars($bookingRef); ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Guest Name:</span>
            <span class="detail-value"><?php echo htmlspecialchars($guestName); ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Accommodation:</span>
            <span class="detail-value"><?php echo htmlspecialchars($roomName); ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Check-in Date:</span>
            <span class="detail-value"><?php echo htmlspecialchars($checkInDate); ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Payment Channel:</span>
            <span class="detail-value"><?php echo htmlspecialchars($paymentMethodUsed); ?></span>
        </div>
    </div>

    <div class="btn-group">
        <a href="my_booking" class="btn-primary">View My Booking</a>
        <a href="index" class="btn-secondary">Return to Home</a>
    </div>
</div>

</body>
</html>
