<!-- PORTAL DASHBOARD - Guest authenticated -->
<div class="portal-section">

    <!-- Top bar -->
    <div class="portal-top-bar">
        <div class="portal-identity">
            <p class="portal-greeting">Your stay, at a glance</p>
            <p class="portal-welcome">
                <?php echo htmlspecialchars($booking['guest_name']); ?>
            </p>
            <div class="portal-reference"><span>Booking reference</span><strong><?php echo htmlspecialchars($booking['booking_ref']); ?></strong></div>
        </div>
        <a href="my_booking?logout=1" class="btn-portal-logout">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
            Look up another booking
        </a>
    </div>

    <!-- Payment Countdown Timer Banner (if pending) -->
    <?php if ($is_pending_payment): ?>
    <div class="portal-card payment-timer-card <?php echo $is_payment_expired ? 'timer-expired' : ''; ?>" id="paymentTimerCard" data-remaining="<?php echo $seconds_remaining; ?>">
        <div class="timer-flex">
            <div class="timer-icon-wrap">
                <span class="timer-pulse-ring"></span>
                <span class="timer-icon">
                    <?php if ($is_payment_expired): ?>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#DC2626" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    <?php else: ?>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#D97706" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    <?php endif; ?>
                </span>
            </div>
            <div class="timer-text">
                <div class="timer-title" id="timerTitle">
                    <?php echo $is_payment_expired ? 'Payment Window Expired' : 'Complete Your Payment Verification'; ?>
                </div>
                <div class="timer-desc" id="timerDesc">
                    <?php echo $is_payment_expired
                        ? 'Your 30-minute provisional reservation hold has expired. Please contact front desk if you have already sent payment.'
                        : 'Your provisional room hold is reserved for 30 minutes while we verify your deposit.'; ?>
                </div>
            </div>
            <div class="timer-clock-badge" id="timerClockBadge">
                <span class="timer-clock-digits" id="timerDigits">--:--</span>
                <span class="timer-clock-sub" id="timerSub"><?php echo $is_payment_expired ? 'Expired' : 'Remaining'; ?></span>
            </div>
        </div>
        <div class="timer-progress-bar">
            <div class="timer-progress-fill" id="timerProgressFill" style="width: <?php echo min(100, round(($seconds_remaining / 1800) * 100)); ?>%;"></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Booking Summary Card -->
    <div class="portal-card">
        <div class="portal-card-header">
            <span class="portal-card-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></span>
            <h2>Booking Summary</h2>
            <span class="status-badge" style="margin-left:auto; background:<?php echo $status_style['bg']; ?>; color:<?php echo $status_style['color']; ?>;">
                <?php echo $status_style['icon']; ?> <?php echo htmlspecialchars($bk_status); ?>
            </span>
        </div>
        <div class="portal-card-body">
            <div class="info-grid">
                <div class="info-item">
                    <label>Accommodation</label>
                    <span><?php echo htmlspecialchars($booking['accommodation_name']); ?></span>
                </div>
                <div class="info-item">
                    <label>Check-in</label>
                    <span><?php echo htmlspecialchars($checkin_fmt); ?></span>
                </div>
                <div class="info-item">
                    <label>Check-out</label>
                    <span><?php echo htmlspecialchars($checkout_fmt); ?></span>
                </div>
                <div class="info-item">
                    <label>Duration</label>
                    <span><?php echo $nights; ?> night<?php echo $nights > 1 ? 's' : ''; ?></span>
                </div>
                <div class="info-item">
                    <label>Guests</label>
                    <span><?php echo (int)$booking['guests_count']; ?> adult<?php echo $booking['guests_count'] > 1 ? 's' : ''; ?></span>
                </div>
                <div class="info-item">
                    <label>Estimated Arrival</label>
                    <span><?php echo htmlspecialchars($booking['eta'] ?? '14:00'); ?></span>
                </div>
                <?php if (!empty($booking['guest_type'])): ?>
                <div class="info-item">
                    <label>Guest Type</label>
                    <span><?php echo htmlspecialchars($booking['guest_type']); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($discountBooking): ?>
                <div class="info-item">
                    <label>Discount</label>
                    <span><?php echo strtoupper(htmlspecialchars($discountBooking['category'])); ?> — 20%</span>
                </div>
                <div class="info-item">
                    <label>Discount Status</label>
                    <span><?php echo htmlspecialchars($discountBooking['state']); ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($booking['guest_special_requests'])): ?>
                <div class="info-item" style="grid-column: 1 / -1;">
                    <label>Special Requests</label>
                    <span><?php echo htmlspecialchars($booking['guest_special_requests']); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Payment Card -->
    <?php if ($payment || $discountBooking): ?>
    <div class="portal-card">
        <div class="portal-card-header">
            <span class="portal-card-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></span>
            <h2>Payment</h2>
            <span class="status-badge" style="margin-left:auto; background:<?php echo $pay_style['bg']; ?>; color:<?php echo $pay_style['color']; ?>;">
                <?php echo $pay_style['label']; ?>
            </span>
        </div>
        <div class="portal-card-body" style="padding-bottom:0;">
            <div class="payment-row">
                <label>Payment Method</label>
                <strong><?php echo htmlspecialchars($payment['payment_method'] ?? $booking['payment_method'] ?? '—'); ?></strong>
            </div>
            <div class="payment-row">
                <label>Reference / Transaction ID</label>
                <strong><?php echo htmlspecialchars($payment['transaction_id'] ?? '—'); ?></strong>
            </div>
            <?php if ($discountBooking): ?>
            <div class="payment-row">
                <label>Original Stay Cost</label>
                <strong>&#8369; <?php echo number_format($original_amount, 2); ?></strong>
            </div>
            <div class="payment-row">
                <label>Senior Citizen / PWD Discount (20%)</label>
                <strong style="color:#15803d;">&minus; &#8369; <?php echo number_format($discount_amount, 2); ?></strong>
            </div>
            <div class="payment-row">
                <label>Required Deposit (50%)</label>
                <strong>&#8369; <?php echo number_format($required_deposit, 2); ?></strong>
            </div>
            <?php endif; ?>
            <div class="payment-row">
                <label><?php echo $discountBooking ? 'Verified Payments' : 'Deposit Paid (50%)'; ?></label>
                <strong style="color:#0284c7;">&#8369; <?php echo number_format($deposit_paid, 2); ?></strong>
            </div>
            <div class="payment-row">
                <label>Total Stay Cost</label>
                <strong>&#8369; <?php echo number_format($total_amount, 2); ?></strong>
            </div>
            <?php if ($remaining > 0 && !in_array($bk_status, ['Checked Out', 'Cancelled'])): ?>
            <div class="payment-row balance-row" style="margin-left:-24px; margin-right:-24px; padding-left:24px; padding-right:24px;">
                <label>Balance Due Before Arrival</label>
                <strong>&#8369; <?php echo number_format($remaining, 2); ?></strong>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Actions Card -->
    <div class="portal-card">
        <div class="portal-card-header">
            <span class="portal-card-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg></span>
            <h2>Quick Actions</h2>
        </div>
        <div class="portal-card-body">
            <div class="portal-actions">
                <!-- Print / Save Invoice & Voucher -->
                <a href="invoice.php?ref=<?php echo urlencode($booking['booking_ref']); ?>&token=<?php echo urlencode($booking['checkin_token'] ?? ''); ?>"
                   target="_blank"
                   class="btn-action" style="background:#7C533C; color:#fff;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                    Print / PDF Invoice
                </a>

                <!-- Download QR Pass -->
                <?php if (!empty($qr_data_uri)): ?>
                <a href="<?php echo htmlspecialchars($qr_data_uri); ?>"
                   download="SantaFe_Pass_<?php echo htmlspecialchars($booking['booking_ref']); ?>.png"
                   class="btn-action" style="background:#0F172A; color:#fff;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Save QR Pass (PNG)
                </a>
                <?php endif; ?>

                <!-- Resume Payment -->
                <?php if ($discountBooking): ?>
                <a href="discount_booking.php?booking=<?php echo (int)$booking['id']; ?>"
                   class="btn-action" style="background:#0e7490; color:#fff;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="4" width="18" height="16" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    Manage Discount &amp; Payment
                </a>
                <?php endif; ?>

                <?php if ($remaining > 0 && in_array($bk_status, ['Pending','Confirmed'], true) && !empty($booking['cancellation_token'])): ?>
                <a href="balance_payment.php?token=<?php echo urlencode($booking['cancellation_token']); ?>"
                   class="btn-action" style="background:#15803d;color:#fff;">
                    Pay Remaining Balance
                </a>
                <?php endif; ?>
                <?php
                if ($bk_status === 'Pending Payment' && !empty($payment['receipt_url']) && strpos($payment['receipt_url'], 'http') === 0):
                ?>
                <a href="<?php echo htmlspecialchars($payment['receipt_url']); ?>"
                   class="btn-action" style="background:#0ea5e9; color:#fff;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="4" width="18" height="16" rx="2" ry="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    Complete Payment
                </a>
                <?php endif; ?>

                <!-- Rebook: pre-fill booking form with same guest details -->
                <a href="book?rebook=1&email=<?php echo urlencode($booking['guest_email']); ?>&first_name=<?php echo urlencode(strtok($booking['guest_name'], ' ')); ?>&last_name=<?php echo urlencode(strstr($booking['guest_name'], ' ')); ?>&phone=<?php echo urlencode($booking['guest_phone'] ?? ''); ?>&country=<?php echo urlencode($booking['guest_country'] ?? ''); ?>"
                   class="btn-action btn-action-primary">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><polyline points="3 3 3 8 8 8"/></svg>
                    Book Again
                </a>

                <!-- Cancel Booking (Active or Locked state) -->
                <?php if ($can_cancel): ?>
                <a href="javascript:void(0)"
                   class="btn-action btn-action-danger"
                   data-cancel-url="<?php echo htmlspecialchars($cancel_url); ?>"
                   onclick="(function(el){ showConfirm({ title: 'Cancel Booking', message: 'Are you sure you want to cancel this booking? This cannot be undone.', icon: 'cancel', iconBg: '#FEE2E2', confirmText: 'Yes, Cancel It', onConfirm: function(){ window.location.href = el.dataset.cancelUrl; } }); })(this)">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                    Cancel Booking
                </a>
                <?php elseif ($is_cancellation_expired && in_array($bk_status, ['Pending Payment', 'Pending', 'Confirmed'])): ?>
                <a href="javascript:void(0)"
                   class="btn-action btn-action-disabled"
                   title="Cancellation locked (<?php echo htmlspecialchars($cancel_window_label); ?> deadline passed on <?php echo htmlspecialchars($deadline_formatted); ?>)"
                   onclick="showConfirm({ title: 'Cancellation Locked', message: 'Self-service cancellation closed on <?php echo addslashes($deadline_formatted); ?> (<?php echo addslashes($cancel_window_label); ?> prior to arrival for a <?php echo (int)$cancel_policy['nights']; ?>-night stay).\n\nTo cancel or reschedule your reservation, please reach out to our Front Desk directly.', icon: 'lock', iconBg: '#FEF3C7', confirmText: 'Contact Front Desk', confirmColor: '#7C533C', onConfirm: function(){ window.location.href = 'contact'; } })">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    Cancel Booking (Locked)
                </a>
                <?php endif; ?>

                <?php
                $has_payment = !empty($payment);
                $refund_eligible_status = in_array($bk_status, ['Pending Payment', 'Pending', 'Confirmed', 'Checked In']);
                $refund_token = $booking['cancellation_token'] ?? '';
                $existing_refund_status = $booking['refund_status'] ?? null;
                if ($has_payment && $refund_eligible_status && !empty($refund_token)):
                ?>
                <?php if ($existing_refund_status && in_array($existing_refund_status, ['requested','approved','processing'])): ?>
                    <span class="btn-action" style="background:#fef3c7; color:#92400e; border:1px solid #fcd34d; cursor:default; opacity:0.85;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        Refund <?php echo ucfirst($existing_refund_status); ?>
                    </span>
                <?php elseif ($existing_refund_status === 'completed'): ?>
                    <span class="btn-action" style="background:#dcfce7; color:#15803d; border:1px solid #bbf7d0; cursor:default;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                        Refund Completed
                    </span>
                <?php else: ?>
                    <a href="refund_request_form?token=<?php echo urlencode($refund_token); ?>"
                       class="btn-action"
                       style="background:#fef3c7; color:#92400e; border:1px solid #fcd34d;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
                        Request Refund
                    </a>
                <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Cancellation Deadline Notification -->
            <?php if ($bk_status === 'Cancelled'): ?>
                <div style="background:#FEF2F2; border:1px solid #FECACA; border-radius:10px; padding:12px 14px; margin-top:16px; font-size:12.5px; color:#991B1B; display:flex; align-items:flex-start; gap:10px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#DC2626" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0; margin-top:2px;"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
                    <div>
                        <strong>Reservation Cancelled</strong>
                        <div style="margin-top:2px; font-size:12px; color:#B91C1C;">
                            <?php if (!empty($booking['cancellation_reason'])): ?>
                                Reason: <?php echo htmlspecialchars($booking['cancellation_reason']); ?>
                            <?php else: ?>
                                This booking has been cancelled and room inventory was released.
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php elseif ($can_cancel): ?>
                <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:10px; padding:11px 14px; margin-top:16px; font-size:12.5px; color:#475569; display:flex; align-items:center; gap:10px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#475569" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    <div>
                        <strong style="color:#0F172A;">Free Cancellation Window (<?php echo htmlspecialchars($cancel_window_label); ?> policy):</strong>
                        <span>Online cancellation is open until <strong><?php echo htmlspecialchars($deadline_formatted); ?></strong> (<?php echo $hours_left_to_cancel >= 24 ? $days_left_to_cancel . ' days left' : ($hours_left_to_cancel > 0 ? $hours_left_to_cancel . ' hrs left' : 'closing soon'); ?>).</span>
                    </div>
                </div>
            <?php elseif ($bk_status !== 'Checked Out'): ?>
                <div style="background:#FFFBEB; border:1px solid #FDE68A; border-radius:10px; padding:12px 14px; margin-top:16px; font-size:12.5px; color:#92400E; display:flex; align-items:flex-start; gap:10px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#D97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0; margin-top:2px;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    <div>
                        <strong style="display:block; margin-bottom:2px;">Cancellation Deadline Passed</strong>
                        <span>Online cancellations closed on <strong><?php echo htmlspecialchars($deadline_formatted); ?></strong> (<?php echo htmlspecialchars($cancel_window_label); ?> prior to arrival for a <?php echo (int)$cancel_policy['nights']; ?>-night stay). Please contact the Front Desk if you need assistance.</span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div><!-- /.portal-section -->
