<!-- RIGHT SIDEBAR (SUMMARY) -->
<aside class="bk-sidebar">
    <!-- Dark branded header -->
    <div class="bk-sidebar-header">
        <span class="bk-sb-kicker">Reservation Summary</span>
        <h3 class="bk-sb-title">Booking Summary</h3>
        <?php if ($full_name): ?>
        <div style="color:rgba(255,255,255,0.72); font-size:13px; margin-top:4px;"><?php echo htmlspecialchars($full_name); ?></div>
        <?php endif; ?>
    </div>

    <div class="bk-sb-inner">
        <div class="bk-sb-dates">
            <div class="bk-sb-dcol">
                <div class="bk-sb-dmain"><?php echo $checkin_fmt; ?></div>
                <div class="bk-sb-dsub">Check-in &middot; 13:30</div>
            </div>
            <div class="bk-sb-darrow">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </div>
            <div class="bk-sb-dcol">
                <div class="bk-sb-dmain"><?php echo $checkout_fmt; ?></div>
                <div class="bk-sb-dsub">Check-out &middot; 11:00</div>
            </div>
        </div>

        <div class="bk-sb-meta">
            <span class="stay-facts" aria-label="Stay details">
                <span><?php echo $nights; ?> night<?php echo $nights > 1 ? 's' : ''; ?></span>
                <span>1 room</span>
                <span><?php echo $guests_total; ?> adult<?php echo $guests_total > 1 ? 's' : ''; ?></span>
            </span>
        </div>

        <div class="bk-sb-room-row">
            <span>Base Rate (<?php echo $nights; ?> night<?php echo $nights > 1 ? 's' : ''; ?>)</span>
            <span>₱ <?php echo number_format($pricing['base_price_per_night'] * $nights, 2); ?></span>
        </div>

        <?php if (($pricing['extra_person_total'] ?? 0) > 0): ?>
        <div class="bk-sb-room-row" style="color:#7C533C; font-size:12.5px;">
            <span>Extra Adult Fee (<?php echo $pricing['extra_adults']; ?> &times; ₱<?php echo number_format($pricing['extra_rate_per_adult'], 0); ?>)</span>
            <span>+₱ <?php echo number_format($pricing['extra_person_total'], 2); ?></span>
        </div>
        <?php endif; ?>

        <?php if ($pricing['weekend_surcharge_total'] > 0): ?>
        <div class="bk-sb-room-row" style="color:#B45309; font-size:12.5px;">
            <span>Weekend Rate Adjustment</span>
            <span>+₱ <?php echo number_format($pricing['weekend_surcharge_total'], 2); ?></span>
        </div>
        <?php endif; ?>

        <?php if ($pricing['seasonal_adjustment_total'] > 0): ?>
        <div class="bk-sb-room-row" style="color:#B45309; font-size:12.5px;">
            <span>Seasonal Rate Adjustment</span>
            <span>+₱ <?php echo number_format($pricing['seasonal_adjustment_total'], 2); ?></span>
        </div>
        <?php endif; ?>

        <?php if ($discount_amount > 0): ?>
        <div class="bk-sb-room-row" style="color:#15803D; font-weight:700; font-size:12.5px;">
            <span>Promo (<?php echo htmlspecialchars($applied_promo_code); ?>)</span>
            <span>-₱ <?php echo number_format($discount_amount, 2); ?></span>
        </div>
        <?php endif; ?>

        <div class="bk-sb-room-detail">
            <strong>1 &times; <?php echo htmlspecialchars($accommodation_name); ?></strong>
            <?php echo $guests_total; ?> adult<?php echo $guests_total > 1 ? 's' : ''; ?>
            <?php if ($has_breakfast): ?><br><span style="color:var(--green); font-size:12px; font-weight:600;">Breakfast included</span><?php endif; ?>
        </div>

        <div class="bk-sb-total-row" id="regularSummaryTotal">
            <span>Total Stay Cost</span>
            <span>₱ <?php echo number_format($total_amount, 2); ?></span>
        </div>

        <div class="bk-sb-deposit-pill" id="regularSummaryDeposit">
            <span>50% Deposit Due</span>
            <span>₱ <?php echo number_format($deposit_amount, 2); ?></span>
        </div>

        <div class="bk-sb-tax-note">Price includes all taxes and applicable fees</div>
        <div id="discountSummary" hidden><p><strong>Estimated total after 20% discount: ₱ <?php echo number_format(round($pricing['subtotal'] * .8, 2), 2); ?></strong></p><p>No payment due while awaiting ID verification. Any promo code shown above will not apply.</p></div>
    </div>

    <div class="bk-trust-badges">
        <div class="bk-trust-badge">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <span>Encrypted</span>
        </div>
        <div class="bk-trust-badge">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            <span>Direct Rate</span>
        </div>
        <div class="bk-trust-badge">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <span>24/7 Desk</span>
        </div>
    </div>
</aside>
