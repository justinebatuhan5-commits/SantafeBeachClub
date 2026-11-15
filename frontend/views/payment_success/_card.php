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
    <p class="success-desc">We could not verify this payment for your booking. Check Manage Booking or contact the front desk before making another payment.</p>
    <?php endif; ?>
    <div class="btn-group">
        <a href="my_booking" class="btn-primary">Manage Booking</a>
        <a href="index" class="btn-secondary">Return to Home</a>
    </div>
</div>
