    <div class="no-print-bar">
        <a href="my_booking" class="btn-back">&larr; Back to Guest Portal</a>
        <div style="display:flex; gap:10px;">
            <a href="<?php echo htmlspecialchars($qr_src); ?>" download="SantaFe_Pass_<?php echo htmlspecialchars($booking_ref); ?>.png" class="btn-print" style="background:#0F172A;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Download QR Pass
            </a>
            <button type="button" data-action="print-invoice" class="btn-print">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Print / Save as PDF
            </button>
        </div>
    </div>

    <div class="invoice-wrapper">

        <!-- Header -->
        <div class="invoice-header">
            <div class="resort-brand">
                <img src="assets/logo.jpg" alt="Santa Fe Beach Club" class="resort-logo">
                <div>
                    <h1 class="resort-name">Santa Fe Beach Club</h1>
                    <div class="resort-sub">Bantayan Island, Cebu, Philippines • official voucher</div>
                </div>
            </div>
            <div class="invoice-meta">
                <span class="invoice-badge">BOOKING VOUCHER & INVOICE</span>
                <div class="invoice-ref"><?php echo htmlspecialchars($booking_ref); ?></div>
                <div class="invoice-date">Issued on <?php echo $created_fmt; ?></div>
            </div>
        </div>

        <!-- Body -->
        <div class="invoice-body">

            <!-- Guest & Stay Information Grid -->
            <div class="section-grid">
                <div class="info-card">
                    <div class="info-card-title">Guest Information</div>
                    <div class="info-row">
                        <span class="info-label">Guest Name:</span>
                        <span class="info-value"><?php echo htmlspecialchars($booking['guest_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Email:</span>
                        <span class="info-value"><?php echo htmlspecialchars($booking['guest_email']); ?></span>
                    </div>
                    <?php if (!empty($booking['guest_phone'])): ?>
                    <div class="info-row">
                        <span class="info-label">Contact Phone:</span>
                        <span class="info-value"><?php echo htmlspecialchars($booking['guest_phone']); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="info-row">
                        <span class="info-label">Status:</span>
                        <span class="info-value" style="color:#059669; font-weight:700;"><?php echo htmlspecialchars($booking['status']); ?></span>
                    </div>
                </div>

                <div class="info-card">
                    <div class="info-card-title">Stay Itinerary</div>
                    <div class="info-row">
                        <span class="info-label">Accommodation:</span>
                        <span class="info-value"><?php echo htmlspecialchars($booking['accommodation_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Check-in:</span>
                        <span class="info-value"><?php echo $checkin_fmt; ?> (from 13:30)</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Check-out:</span>
                        <span class="info-value"><?php echo $checkout_fmt; ?> (until 11:00)</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Duration & Guests:</span>
                        <span class="info-value stay-facts" aria-label="Stay details">
                            <span><?php echo $nights; ?> night<?php echo $nights > 1 ? 's' : ''; ?></span>
                            <span>1 room</span>
                            <span><?php echo (int)$booking['guests_count']; ?> guest<?php echo (int)$booking['guests_count'] > 1 ? 's' : ''; ?></span>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Itemized Financial Breakdown Table -->
            <table class="invoice-table">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th style="text-align:center;">Qty / Nights</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($booking['accommodation_name']); ?></strong><br>
                            <small style="color:#64748B;">Room accommodation for <?php echo $nights; ?> night<?php echo $nights > 1 ? 's' : ''; ?></small>
                        </td>
                        <td style="text-align:center;"><?php echo $nights; ?> night<?php echo $nights > 1 ? 's' : ''; ?></td>
                        <td>₱ <?php echo number_format($total_amount, 2); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Included guests</strong><br><small style="color:#64748B;">Included in the room rate</small></td>
                        <td style="text-align:center;"><?php echo $included_guests; ?> person<?php echo $included_guests === 1 ? '' : 's'; ?></td>
                        <td>Included</td>
                    </tr>
                    <?php if ($extra_adults > 0): ?>
                    <tr>
                        <td><strong>Extra adult<?php echo $extra_adults === 1 ? '' : 's'; ?></strong></td>
                        <td style="text-align:center;"><?php echo $extra_adults; ?></td>
                        <td>Added</td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($extra_children > 0): ?>
                    <tr>
                        <td><strong>Child<?php echo $extra_children === 1 ? '' : 'ren'; ?></strong></td>
                        <td style="text-align:center;"><?php echo $extra_children; ?></td>
                        <td>Added</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Totals & Balances -->
            <div class="invoice-totals">
                <div class="tot-row">
                    <span style="color:#64748B;">Total Stay Cost:</span>
                    <span>₱ <?php echo number_format($total_amount + $discount_amt, 2); ?></span>
                </div>
                <?php if ($discount_amt > 0): ?>
                <div class="tot-row">
                    <span style="color:#16A34A; font-weight:600;"><?php echo htmlspecialchars($discount_label); ?> <?php echo !empty($booking['promo_code']) ? '(' . htmlspecialchars($booking['promo_code']) . ')' : ''; ?>:</span>
                    <span style="color:#16A34A; font-weight:700;">-₱ <?php echo number_format($discount_amt, 2); ?></span>
                </div>
                <div class="tot-row" style="border-top:1px dashed #E2E8F0; padding-top:6px;">
                    <span style="color:#64748B;">After Discount:</span>
                    <span style="font-weight:600;">₱ <?php echo number_format($total_amount, 2); ?></span>
                </div>
                <?php endif; ?>
                <div class="tot-row">
                    <span style="color:#0284C7; font-weight:600;">Verified Payments:</span>
                    <span style="color:#0284C7; font-weight:700;">-₱ <?php echo number_format($deposit_paid, 2); ?></span>
                </div>
                <div class="tot-row grand">
                    <span>Total Paid:</span>
                    <span>₱ <?php echo number_format($deposit_paid, 2); ?></span>
                </div>
                <?php if ($remaining_balance > 0): ?>
                <div class="tot-row balance">
                    <span>Balance Due at Front Desk:</span>
                    <span>₱ <?php echo number_format($remaining_balance, 2); ?></span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Check-in QR Pass -->
            <div class="checkin-pass-banner">
                <img src="<?php echo htmlspecialchars($qr_src); ?>" alt="Check-in QR Pass" class="pass-qr-img">
                <div class="pass-text">
                    <h4>Express Check-in QR Pass</h4>
                    <p>Present this QR code or your reference <strong><?php echo htmlspecialchars($booking_ref); ?></strong> upon arrival at Santa Fe Beach Club front desk for instant contactless check-in.</p>
                    <p style="margin-top:6px; font-size:11.5px; color:#84563C;">Need help or late arrival? Contact front desk in advance.</p>
                </div>
            </div>

        </div>

        <!-- Footer -->
        <div class="invoice-footer">
            <p>Santa Fe Beach Club • Bantayan Island, Northern Cebu • Thank you for choosing to stay with us!</p>
        </div>

    </div>
