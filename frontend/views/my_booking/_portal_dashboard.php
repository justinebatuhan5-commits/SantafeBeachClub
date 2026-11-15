<?php
$etaRaw = trim((string)($booking['eta'] ?? '14:00'));
$etaTs = $etaRaw !== '' ? strtotime($etaRaw) : false;
$etaDisplay = $eta_display ?? ($etaTs !== false ? date('g:i A', $etaTs) : ($etaRaw !== '' ? $etaRaw : '2:00 PM'));
$holdWindow = max(1800, (int)($hold_window_seconds ?? 1800));
$nightsCount = max(1, (int)($nights ?? 1));
$guestsCount = max(1, (int)($booking['guests_count'] ?? 1));
$showQrPass = !empty($qr_data_uri) && $bk_status !== 'Cancelled';
$bookingRef = (string)($booking['booking_ref'] ?? '');

$initialTimerSeconds = max(0, (int)($seconds_remaining ?? 0));
if ($initialTimerSeconds >= 3600) {
    $timerInitialText = sprintf(
        '%02d:%02d:%02d',
        intdiv($initialTimerSeconds, 3600),
        intdiv($initialTimerSeconds % 3600, 60),
        $initialTimerSeconds % 60
    );
} else {
    $timerInitialText = sprintf('%02d:%02d', intdiv($initialTimerSeconds, 60), $initialTimerSeconds % 60);
}
?>
<!-- PORTAL DASHBOARD - Guest authenticated -->
<div class="portal-section">

    <!-- Coastal Espresso Stay Hero -->
    <div class="portal-top-bar">
        <div class="portal-identity">
            <p class="portal-greeting">Santa Fe Beach Club &middot; Your stay, at a glance</p>
            <h1 class="portal-welcome"><?php echo htmlspecialchars((string)$booking['guest_name'], ENT_QUOTES, 'UTF-8'); ?></h1>
            <div class="portal-hero-meta">
                <div class="portal-reference">
                    <span>Reference</span>
                    <strong><?php echo htmlspecialchars($bookingRef, ENT_QUOTES, 'UTF-8'); ?></strong>
                </div>
                <span class="portal-hero-stay">
                    <?php echo htmlspecialchars((string)$booking['accommodation_name'], ENT_QUOTES, 'UTF-8'); ?>
                    &bull;
                    <?php echo htmlspecialchars($checkin_fmt . ' — ' . $checkout_fmt, ENT_QUOTES, 'UTF-8'); ?>
                </span>
                <?php if (!empty($booking['guest_type'])): ?>
                <span class="portal-hero-badge"><?php echo htmlspecialchars((string)$booking['guest_type'], ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="portal-top-actions">
            <a href="index" class="btn-portal-home">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                Resort Home
            </a>
            <a href="my_booking?logout=1" class="btn-portal-logout">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                Look up another booking
            </a>
        </div>
    </div>

    <!-- Payment Countdown Timer Banner (if pending) -->
    <?php if ($is_pending_payment): ?>
    <div class="portal-card payment-timer-card <?php echo $is_payment_expired ? 'timer-expired' : ''; ?>"
         id="paymentTimerCard"
         data-remaining="<?php echo $initialTimerSeconds; ?>"
         data-window="<?php echo $holdWindow; ?>">
        <div class="timer-flex">
            <div class="timer-icon-wrap">
                <span class="timer-pulse-ring"></span>
                <span class="timer-icon" id="timerIconSlot">
                    <?php if ($is_payment_expired): ?>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#DC2626" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <?php else: ?>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#D97706" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <?php endif; ?>
                </span>
            </div>
            <div class="timer-text">
                <div class="timer-title" id="timerTitle">
                    <?php echo $is_payment_expired ? 'Payment Window Expired' : 'Complete Your Payment Verification'; ?>
                </div>
                <div class="timer-desc" id="timerDesc">
                    <?php echo $is_payment_expired
                        ? 'Your provisional reservation hold has expired. Please contact front desk if you have already sent payment.'
                        : 'Your provisional room hold is reserved while we verify your deposit submission.'; ?>
                </div>
            </div>
            <div class="timer-clock-badge" id="timerClockBadge">
                <span class="timer-clock-digits" id="timerDigits"><?php echo $is_payment_expired ? '00:00' : htmlspecialchars($timerInitialText, ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="timer-clock-sub" id="timerSub"><?php echo $is_payment_expired ? 'Expired' : 'Remaining'; ?></span>
            </div>
        </div>
        <div class="timer-progress-bar">
            <div class="timer-progress-fill" id="timerProgressFill" style="width: <?php echo min(100, (int)round(($initialTimerSeconds / $holdWindow) * 100)); ?>%;"></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Two-Column Stay Workspace -->
    <div class="portal-grid">

        <!-- Main Column: Summary & Payment Ledger -->
        <div class="portal-main-col">

            <!-- Booking Summary Card -->
            <section class="portal-card" aria-labelledby="bookingSummaryHeading">
                <div class="portal-card-header">
                    <span class="portal-card-icon" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                    </span>
                    <h2 id="bookingSummaryHeading">Booking Summary</h2>
                    <span class="status-badge" style="margin-left:auto; background:<?php echo htmlspecialchars((string)$status_style['bg'], ENT_QUOTES, 'UTF-8'); ?>; color:<?php echo htmlspecialchars((string)$status_style['color'], ENT_QUOTES, 'UTF-8'); ?>;">
                        <?php echo $status_style['icon']; ?> <?php echo htmlspecialchars((string)$bk_status, ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                </div>
                <div class="portal-card-body">
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Accommodation</label>
                            <span><?php echo htmlspecialchars((string)$booking['accommodation_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Check-in</label>
                            <span><?php echo htmlspecialchars((string)$checkin_fmt, ENT_QUOTES, 'UTF-8'); ?></span>
                            <small class="info-sub">From 2:00 PM</small>
                        </div>
                        <div class="info-item">
                            <label>Check-out</label>
                            <span><?php echo htmlspecialchars((string)$checkout_fmt, ENT_QUOTES, 'UTF-8'); ?></span>
                            <small class="info-sub">Until 12:00 PM</small>
                        </div>
                        <div class="info-item">
                            <label>Duration</label>
                            <span><?php echo $nightsCount; ?> night<?php echo $nightsCount > 1 ? 's' : ''; ?></span>
                        </div>
                        <div class="info-item">
                            <label>Guests</label>
                            <span><?php echo $guestsCount; ?> adult<?php echo $guestsCount > 1 ? 's' : ''; ?></span>
                        </div>
                        <div class="info-item">
                            <label>Estimated Arrival</label>
                            <span><?php echo htmlspecialchars((string)$etaDisplay, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <?php if ($discountBooking): ?>
                        <div class="info-item">
                            <label>Discount</label>
                            <span><?php echo strtoupper(htmlspecialchars((string)$discountBooking['category'], ENT_QUOTES, 'UTF-8')); ?> &mdash; 20%</span>
                        </div>
                        <div class="info-item">
                            <label>Discount Status</label>
                            <span><?php echo htmlspecialchars((string)$discountBooking['state'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($booking['guest_special_requests'])): ?>
                    <div class="special-requests-callout">
                        <span class="special-requests-label">Special Requests</span>
                        <p><?php echo htmlspecialchars((string)$booking['guest_special_requests'], ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Payment Breakdown Card -->
            <?php if ($payment || $discountBooking): ?>
            <section class="portal-card" aria-labelledby="paymentCardHeading">
                <div class="portal-card-header">
                    <span class="portal-card-icon" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                    </span>
                    <h2 id="paymentCardHeading">Payment Summary</h2>
                    <span class="status-badge" style="margin-left:auto; background:<?php echo htmlspecialchars((string)$pay_style['bg'], ENT_QUOTES, 'UTF-8'); ?>; color:<?php echo htmlspecialchars((string)$pay_style['color'], ENT_QUOTES, 'UTF-8'); ?>;">
                        <?php echo $pay_style['label']; ?>
                    </span>
                </div>
                <div class="portal-card-body portal-card-body--flush">
                    <div class="payment-ledger">
                        <div class="payment-row">
                            <label>Payment Method</label>
                            <strong><?php echo htmlspecialchars((string)($payment['payment_method'] ?? $booking['payment_method'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></strong>
                        </div>
                        <div class="payment-row">
                            <label>Reference / Transaction ID</label>
                            <strong>
                                <?php $txId = trim((string)($payment['transaction_id'] ?? '')); ?>
                                <?php if ($txId !== ''): ?>
                                    <code class="tx-code"><?php echo htmlspecialchars($txId, ENT_QUOTES, 'UTF-8'); ?></code>
                                <?php else: ?>
                                    &mdash;
                                <?php endif; ?>
                            </strong>
                        </div>
                        <?php if ($discountBooking): ?>
                        <div class="payment-row">
                            <label>Original Stay Cost</label>
                            <strong>&#8369; <?php echo number_format((float)$original_amount, 2); ?></strong>
                        </div>
                        <div class="payment-row">
                            <label>Senior Citizen / PWD Discount (20%)</label>
                            <strong class="amount-discount">&minus; &#8369; <?php echo number_format((float)$discount_amount, 2); ?></strong>
                        </div>
                        <div class="payment-row">
                            <label>Required Deposit (50%)</label>
                            <strong>&#8369; <?php echo number_format((float)$required_deposit, 2); ?></strong>
                        </div>
                        <?php endif; ?>
                        <div class="payment-row">
                            <label><?php echo $discountBooking ? 'Verified Payments' : 'Deposit Paid (50%)'; ?></label>
                            <strong class="amount-deposit">&#8369; <?php echo number_format((float)$deposit_paid, 2); ?></strong>
                        </div>
                        <div class="payment-row">
                            <label>Total Stay Cost</label>
                            <strong>&#8369; <?php echo number_format((float)$total_amount, 2); ?></strong>
                        </div>
                    </div>

                    <?php if ($remaining > 0 && !in_array($bk_status, ['Checked Out', 'Cancelled'], true)): ?>
                    <div class="payment-row balance-row">
                        <div class="balance-label-group">
                            <label>Balance Due Before Arrival</label>
                            <small>Settle online ahead of check-in or at Front Desk</small>
                        </div>
                        <strong>&#8369; <?php echo number_format((float)$remaining, 2); ?></strong>
                    </div>
                    <?php elseif ($remaining <= 0 && $bk_status !== 'Cancelled'): ?>
                    <div class="payment-row balance-row is-settled">
                        <div class="balance-label-group">
                            <label>Balance Status</label>
                            <small>No remaining room balance due at arrival</small>
                        </div>
                        <strong>Paid in Full</strong>
                    </div>
                    <?php endif; ?>
                </div>
            </section>
            <?php endif; ?>

        </div><!-- /.portal-main-col -->

        <!-- Side Rail: Check-In QR Pass & Quick Actions -->
        <aside class="portal-side-col" aria-label="Check-in Pass and Quick Actions">

            <?php if ($showQrPass): ?>
            <section class="portal-card qr-pass-card" aria-labelledby="qrPassHeading">
                <div class="qr-pass-top">
                    <div>
                        <span class="qr-pass-kicker">Santa Fe Beach Club</span>
                        <h2 id="qrPassHeading">Check-In Pass</h2>
                    </div>
                    <span class="qr-pass-ref"><?php echo htmlspecialchars($bookingRef, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="qr-pass-body">
                    <div class="qr-pass-frame">
                        <img src="<?php echo htmlspecialchars((string)$qr_data_uri, ENT_QUOTES, 'UTF-8'); ?>"
                             alt="Check-in QR Pass for <?php echo htmlspecialchars($bookingRef, ENT_QUOTES, 'UTF-8'); ?>"
                             width="148"
                             height="148">
                    </div>
                    <p class="qr-pass-caption">Present this code at the Front Desk for fast arrival verification.</p>
                    <div class="qr-pass-buttons">
                        <a href="<?php echo htmlspecialchars((string)$qr_data_uri, ENT_QUOTES, 'UTF-8'); ?>"
                           download="SantaFe_Pass_<?php echo htmlspecialchars($bookingRef, ENT_QUOTES, 'UTF-8'); ?>.png"
                           class="btn-action btn-pass-primary">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            Save QR Pass (PNG)
                        </a>
                        <a href="invoice.php?ref=<?php echo urlencode($bookingRef); ?>&token=<?php echo urlencode((string)($booking['checkin_token'] ?? '')); ?>"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="btn-action btn-pass-outline">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                            Print / PDF Invoice
                        </a>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <section class="portal-card" aria-labelledby="quickActionsHeading">
                <div class="portal-card-header">
                    <span class="portal-card-icon" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                    </span>
                    <h2 id="quickActionsHeading">Quick Actions</h2>
                </div>
                <div class="portal-card-body">
                    <div class="portal-actions-stack">
                        <?php if (!$showQrPass): ?>
                        <a href="invoice.php?ref=<?php echo urlencode($bookingRef); ?>&token=<?php echo urlencode((string)($booking['checkin_token'] ?? '')); ?>"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="btn-action btn-pass-outline">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                            Print / PDF Invoice
                        </a>
                        <?php endif; ?>

                        <?php if ($remaining > 0 && in_array($bk_status, ['Pending', 'Confirmed'], true) && !empty($booking['cancellation_token'])): ?>
                        <a href="balance_payment.php?token=<?php echo urlencode((string)$booking['cancellation_token']); ?>"
                           class="btn-action btn-action-pay">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                            Pay Remaining Balance
                        </a>
                        <?php endif; ?>

                        <?php if ($discountBooking): ?>
                        <a href="discount_booking.php?booking=<?php echo (int)$booking['id']; ?>"
                           class="btn-action btn-action-teal">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            Manage Discount &amp; Payment
                        </a>
                        <?php endif; ?>

                        <?php if ($bk_status === 'Pending Payment' && !empty($payment['receipt_url']) && strpos($payment['receipt_url'], 'http') === 0): ?>
                        <a href="<?php echo htmlspecialchars((string)$payment['receipt_url'], ENT_QUOTES, 'UTF-8'); ?>"
                           class="btn-action btn-action-teal">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2" ry="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            Complete Payment
                        </a>
                        <?php endif; ?>

                        <a href="book?rebook=1&email=<?php echo urlencode((string)$booking['guest_email']); ?>&first_name=<?php echo urlencode((string)strtok((string)$booking['guest_name'], ' ')); ?>&last_name=<?php echo urlencode((string)trim((string)strstr((string)$booking['guest_name'], ' '))); ?>&phone=<?php echo urlencode((string)($booking['guest_phone'] ?? '')); ?>&country=<?php echo urlencode((string)($booking['guest_country'] ?? '')); ?>"
                           class="btn-action btn-action-primary">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><polyline points="3 3 3 8 8 8"/></svg>
                            Book Again
                        </a>

                        <?php
                        $has_payment = !empty($payment);
                        $refund_eligible_status = in_array($bk_status, ['Pending Payment', 'Pending', 'Confirmed', 'Checked In'], true);
                        $refund_token = $booking['cancellation_token'] ?? '';
                        $existing_refund_status = $booking['refund_status'] ?? null;
                        if ($has_payment && $refund_eligible_status && !empty($refund_token)):
                        ?>
                            <?php if ($existing_refund_status && in_array($existing_refund_status, ['requested', 'approved', 'processing'], true)): ?>
                                <span class="btn-action btn-action-refund is-status">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                    Refund <?php echo htmlspecialchars(ucfirst((string)$existing_refund_status), ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            <?php elseif ($existing_refund_status === 'completed'): ?>
                                <span class="btn-action btn-action-refund is-completed">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                                    Refund Completed
                                </span>
                            <?php else: ?>
                                <a href="refund_request_form?token=<?php echo urlencode((string)$refund_token); ?>"
                                   class="btn-action btn-action-refund">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
                                    Request Refund
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if ($can_cancel): ?>
                        <button type="button"
                                class="btn-action btn-action-danger js-cancel-booking-btn"
                                data-cancel-url="<?php echo htmlspecialchars((string)$cancel_url, ENT_QUOTES, 'UTF-8'); ?>">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                            Cancel Booking
                        </button>
                        <?php elseif ($is_cancellation_expired && in_array($bk_status, ['Pending Payment', 'Pending', 'Confirmed'], true)): ?>
                        <button type="button"
                                class="btn-action btn-action-disabled js-cancel-locked-btn"
                                title="Cancellation locked (<?php echo htmlspecialchars((string)$cancel_window_label, ENT_QUOTES, 'UTF-8'); ?> deadline passed on <?php echo htmlspecialchars((string)$deadline_formatted, ENT_QUOTES, 'UTF-8'); ?>)"
                                data-deadline="<?php echo htmlspecialchars((string)$deadline_formatted, ENT_QUOTES, 'UTF-8'); ?>"
                                data-window="<?php echo htmlspecialchars((string)$cancel_window_label, ENT_QUOTES, 'UTF-8'); ?>"
                                data-nights="<?php echo (int)($cancel_policy['nights'] ?? $nightsCount); ?>">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            Cancel Booking (Locked)
                        </button>
                        <?php endif; ?>
                    </div>

                    <!-- Cancellation Policy Notice -->
                    <?php if ($bk_status === 'Cancelled'): ?>
                        <div class="portal-policy-box is-danger">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                            <div>
                                <strong>Reservation Cancelled</strong>
                                <p>
                                    <?php if (!empty($booking['cancellation_reason'])): ?>
                                        Reason: <?php echo htmlspecialchars((string)$booking['cancellation_reason'], ENT_QUOTES, 'UTF-8'); ?>
                                    <?php else: ?>
                                        This booking has been cancelled and room inventory was released.
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    <?php elseif ($can_cancel): ?>
                        <div class="portal-policy-box is-info">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            <div>
                                <strong>Free Cancellation Window (<?php echo htmlspecialchars((string)$cancel_window_label, ENT_QUOTES, 'UTF-8'); ?> policy)</strong>
                                <p>Open until <strong><?php echo htmlspecialchars((string)$deadline_formatted, ENT_QUOTES, 'UTF-8'); ?></strong> (<?php echo $hours_left_to_cancel >= 24 ? (int)$days_left_to_cancel . ' days left' : ($hours_left_to_cancel > 0 ? (int)$hours_left_to_cancel . ' hrs left' : 'closing soon'); ?>).</p>
                            </div>
                        </div>
                    <?php elseif ($bk_status !== 'Checked Out'): ?>
                        <div class="portal-policy-box is-warning">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            <div>
                                <strong>Cancellation Deadline Passed</strong>
                                <p>Online cancellations closed on <strong><?php echo htmlspecialchars((string)$deadline_formatted, ENT_QUOTES, 'UTF-8'); ?></strong> (<?php echo htmlspecialchars((string)$cancel_window_label, ENT_QUOTES, 'UTF-8'); ?> prior to arrival for a <?php echo (int)($cancel_policy['nights'] ?? $nightsCount); ?>-night stay). Contact Front Desk if you need assistance.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <div class="portal-help-card">
                <div>
                    <strong>Need help with your reservation?</strong>
                    <p>Our Front Desk team is happy to assist with requests or schedule adjustments.</p>
                </div>
                <a href="contact" class="portal-help-link">Contact Front Desk &rarr;</a>
            </div>

        </aside><!-- /.portal-side-col -->

    </div><!-- /.portal-grid -->

</div><!-- /.portal-section -->
