<?php
$bookingIdInt = (int) ($booking_id ?? 0);
$bookingRefFormatted = !empty($booking_ref)
    ? (string) $booking_ref
    : ('REF-' . str_pad((string) $bookingIdInt, 3, '0', STR_PAD_LEFT));
$nightsCount = max(1, (int) ($nights ?? 1));
$guestsCount = max(1, (int) ($guests_total ?? ($guests ?? 1)));
$etaRaw = trim((string) ($booking_eta ?? '14:00'));
$etaTimestamp = strtotime($etaRaw);
$etaDisplay = $etaTimestamp !== false ? date('g:i A', $etaTimestamp) : $etaRaw;
$paymentMethodLabel = trim((string) ($payment_method ?? ''));
$qrRelPath = 'assets/qrcodes/qr_booking_' . $bookingIdInt . '.png';
$qrDiskPath = __DIR__ . '/../../' . $qrRelPath;
$hasQrFile = $bookingIdInt > 0 && is_file($qrDiskPath);
$qrImageSrc = $qrRelPath . ($hasQrFile ? ('?v=' . (int) filemtime($qrDiskPath)) : '');
?>
<!-- SUCCESS SCREEN -->
<div class="booking-page-container">
    <div class="booking-card success-box">
        <div class="success-icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.75" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        </div>

        <div class="bk-status-pill">
            <span class="bk-status-pill-dot" aria-hidden="true"></span>
            Pending Payment Verification
        </div>

        <h2>Booking Received!</h2>
        <p class="bk-success-subtitle">
            Thank you, <strong><?php echo htmlspecialchars($guest_name); ?></strong>. Your reservation request has been recorded<?php if (!empty($guest_email)): ?> and emailed to <strong><?php echo htmlspecialchars($guest_email); ?></strong><?php endif; ?>.
        </p>

        <div class="bk-status-notice" role="status">
            <svg class="bk-status-notice-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <div>
                <strong>Important:</strong> Your room is held while our staff verifies your
                <strong><?php echo htmlspecialchars($paymentMethodLabel !== '' ? $paymentMethodLabel : 'deposit'); ?></strong>
                submission. Once verified, your booking status updates to <strong>Confirmed</strong>.
            </div>
        </div>

        <div class="ticket-pass">
            <div class="ticket-header">
                <div class="ticket-brand-stack">
                    <div class="ticket-brand">Santa Fe Beach Club</div>
                    <span class="ticket-sub">Official Reservation Pass</span>
                </div>
                <div class="ticket-header-actions">
                    <div class="ticket-ref"><?php echo htmlspecialchars($bookingRefFormatted); ?></div>
                    <?php if ($paymentMethodLabel !== ''): ?>
                        <span class="ticket-method-chip"><?php echo htmlspecialchars($paymentMethodLabel); ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="ticket-grid">
                <div class="ticket-details-grid">
                    <div class="ticket-cell">
                        <span class="ticket-label">Room</span>
                        <strong class="ticket-value"><?php echo htmlspecialchars($accommodation_name); ?></strong>
                    </div>
                    <div class="ticket-cell">
                        <span class="ticket-label">Guests</span>
                        <strong class="ticket-value"><?php echo $guestsCount; ?> Adult<?php echo $guestsCount > 1 ? 's' : ''; ?></strong>
                    </div>
                    <div class="ticket-cell ticket-cell--wide">
                        <span class="ticket-label">Check-in / Check-out</span>
                        <strong class="ticket-value"><?php echo htmlspecialchars($checkin_fmt . ' — ' . $checkout_fmt); ?></strong>
                        <span class="ticket-meta"><?php echo $nightsCount; ?> Night<?php echo $nightsCount > 1 ? 's' : ''; ?></span>
                    </div>
                    <div class="ticket-cell ticket-cell--wide">
                        <span class="ticket-label">Estimated Arrival</span>
                        <strong class="ticket-value"><?php echo htmlspecialchars($etaDisplay); ?></strong>
                        <span class="ticket-meta">Standard check-in opens at 2:00 PM</span>
                    </div>
                </div>

                <div class="ticket-qr-panel">
                    <div class="ticket-qr-frame">
                        <img src="<?php echo htmlspecialchars($qrImageSrc); ?>"
                             alt="Check-in QR Pass for <?php echo htmlspecialchars($bookingRefFormatted); ?>"
                             width="136"
                             height="136"
                             class="bk-qr-img">
                        <span class="ticket-qr-fallback">QR Pass Ready for Download</span>
                    </div>
                    <span class="ticket-qr-title">Check-In Pass</span>
                    <span class="ticket-qr-caption">Present at Front Desk</span>
                </div>
            </div>

            <div class="ticket-payment-summary">
                <div class="ticket-fee-row">
                    <span>Total Stay Cost</span>
                    <strong>&#8369; <?php echo number_format((float) $total_amount, 2); ?></strong>
                </div>
                <div class="ticket-fee-row">
                    <span>Deposit (Pending Verification)</span>
                    <strong class="ticket-deposit-amount">&#8369; <?php echo number_format((float) $deposit_amount, 2); ?></strong>
                </div>
                <div class="ticket-balance-row">
                    <span>Balance Due at Front Desk</span>
                    <span>&#8369; <?php echo number_format((float) $remaining_balance, 2); ?></span>
                </div>
            </div>

            <?php if (!empty($cancel_url)): ?>
            <div class="ticket-cancel-box pdf-hide">
                <div class="ticket-cancel-copy">
                    <strong>Need to cancel later?</strong>
                    <span>Save or open your secure self-service cancellation link.</span>
                </div>
                <div class="ticket-cancel-actions">
                    <button type="button"
                            class="bk-copy-cancel-btn"
                            data-copy-url="<?php echo htmlspecialchars($cancel_url); ?>">
                        Copy Link
                    </button>
                    <a href="<?php echo htmlspecialchars($cancel_url); ?>" class="bk-cancel-link-btn">
                        Cancel Page
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="booking-success-actions">
            <a href="invoice.php?ref=<?php echo urlencode($bookingRefFormatted); ?>&amp;token=<?php echo urlencode($checkin_token); ?>"
               target="_blank"
               rel="noopener"
               class="btn-home btn-success-primary">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Print / PDF Invoice
            </a>
            <a href="<?php echo htmlspecialchars($qrRelPath); ?>"
               download="SantaFe_Pass_<?php echo htmlspecialchars($bookingRefFormatted); ?>.png"
               class="btn-home btn-success-dark">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Save QR Pass (PNG)
            </a>
            <a href="my_booking" class="btn-home btn-success-outline">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Manage Booking
            </a>
        </div>
    </div>
</div>
