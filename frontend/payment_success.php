<?php
/**
 * payment_success.php
 * Guest landing page when returning from PayMongo hosted checkout.
 */

require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/services/mailer.php';
require_once __DIR__ . '/../backend/services/paymongo.php';

$bookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
$sessionId = is_string($_GET['session_id'] ?? null) ? trim($_GET['session_id']) : '';

$isVerified = false;
$bookingData = null;
$paymentMethodUsed = 'PayMongo Online';

require_once __DIR__ . '/../backend/services/paymongo_confirmation.php';
header('Cache-Control: no-store');
if ($bookingId > 0 && preg_match('/^cs_[A-Za-z0-9]+$/D', $sessionId)) {
    try {
        pm_checkout_schema($conn);
        $stored = pm_query($conn, 'SELECT session_id FROM paymongo_checkouts WHERE session_id = ? AND booking_id = ?',
            [$sessionId, $bookingId])->get_result()->fetch_assoc();
        if (!$stored) throw new RuntimeException('Unknown checkout.');
        $sessionInfo = PayMongoService::retrieveCheckoutSession($sessionId);
        if (empty($sessionInfo['success'])) throw new RuntimeException('Provider verification unavailable.');
        $confirmation = pm_confirm_checkout($conn, $bookingId, $sessionId, $sessionInfo['data']);
        $isVerified = true;
        $paymentMethodUsed = $confirmation['method'];
    } catch (Throwable $e) {
        error_log('[PayMongo Return] Checkout verification failed for booking ' . $bookingId);
    }
}

if ($isVerified) {
    // 2. Fetch booking details for display
    $stmt = $conn->prepare("SELECT * FROM bookings WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $bookingId);
    $stmt->execute();
    $bookingData = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($bookingData) {
        // Trigger confirmation email if not already sent
        if ($isVerified && !empty($bookingData['guest_email']) && empty($bookingData['confirmation_email_sent_at'])) {
            { // The mailer serializes sends and records successful delivery.
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
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-circle-32.png?v=3">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/favicon-circle-180.png?v=3">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Status — Santa Fe Beach Club</title>
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
<?php require __DIR__ . '/partials/_responsive.php'; ?>
</head>
<body>

<div class="success-card">
    <?php if ($isVerified): ?>
    <div class="check-icon-circle">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="20 6 9 17 4 12"></polyline>
        </svg>
    </div>

    <h1 class="success-title">Payment Successful!</h1>
    <p class="success-desc">
        Thank you! Your payment has been received and automatically verified. Your booking confirmation will be sent by email.
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

    <?php else: ?>
    <h1 class="success-title">Payment not verified yet</h1>
    <p class="success-desc">We could not verify this payment for your booking. Check My Booking or contact the front desk before making another payment.</p>
    <?php endif; ?>
    <div class="btn-group">
        <a href="my_booking" class="btn-primary">View My Booking</a>
        <a href="index" class="btn-secondary">Return to Home</a>
    </div>
</div>

</body>
</html>
