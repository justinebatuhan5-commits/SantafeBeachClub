<!-- SUCCESS SCREEN -->
<div class="booking-page-container">
    <div class="booking-card success-box">
        <div class="success-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <h2>Booking Confirmed!</h2>
        <p style="color: #666; margin-bottom: 30px;">Thank you, <?php echo htmlspecialchars($guest_name); ?>. Your reservation has been received!<br><br>
        <strong style="color:#d32f2f;">Important:</strong> Your booking is currently <strong>Pending Payment Verification</strong>. Once our staff verifies your payment, your booking will be Confirmed and your room will be officially reserved.</p>

        <div class="ticket-pass">
            <div class="ticket-header">
                <div class="ticket-brand">Santa Fe Beach Club</div>
                <div class="ticket-header-actions">
                    <div class="ticket-ref">REF-<?php echo str_pad((string) $booking_id, 3, '0', STR_PAD_LEFT); ?></div>
                </div>
            </div>
            <div class="ticket-grid">
                <div>
                    <div style="margin-bottom: 15px;">
                        <span style="font-size: 11px; color: #888; text-transform: uppercase;">Room</span><br>
                        <strong><?php echo htmlspecialchars($accommodation_name); ?></strong>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <span style="font-size: 11px; color: #888; text-transform: uppercase;">Check-in / Check-out</span><br>
                        <strong><?php echo $checkin_fmt; ?> - <?php echo $checkout_fmt; ?></strong><br>
                        <small style="color:#555;">Estimated Arrival: <strong><?php echo htmlspecialchars($booking_eta); ?></strong></small>
                    </div>
                    <div>
                        <span style="font-size: 11px; color: #888; text-transform: uppercase;">Guests</span><br>
                        <strong><?php echo $guests_total; ?> Adult<?php echo $guests_total > 1 ? 's' : ''; ?></strong>
                    </div>
                </div>
            </div>

            <div style="margin-top: 15px; border-top: 1px dashed #ccc; padding-top: 15px;">
                <div style="display:flex; justify-content:space-between; margin-bottom: 5px; font-size: 13px;">
                    <span>Total Stay Cost:</span>
                    <strong>₱ <?php echo number_format($total_amount, 2); ?></strong>
                </div>
                <div style="display:flex; justify-content:space-between; margin-bottom: 5px; font-size: 13px;">
                    <span>Deposit (Pending Verification):</span>
                    <strong style="color: #7C533C;">₱ <?php echo number_format($deposit_amount, 2); ?></strong>
                </div>
                <div style="display:flex; justify-content:space-between; margin-top: 10px; font-size: 15px; font-weight: 700; color: #b45309; background: #fffbeb; padding: 10px; border-radius: 6px;">
                    <span>Balance due at Front Desk:</span>
                    <span>₱ <?php echo number_format($remaining_balance, 2); ?></span>
                </div>
            </div>

            <div style="margin-top:15px; padding:12px 14px; border:1px solid #f0e3d8; border-radius:8px; background:#fffaf6; font-size:12px; color:#6b4a35;">
                Need to cancel later? Save this secure link:
                <div style="margin-top:6px; word-break: break-all;">
                    <a href="<?php echo htmlspecialchars($cancel_url); ?>" style="color:#8B5E3C; font-weight:600;"><?php echo htmlspecialchars($cancel_url); ?></a>
                </div>
            </div>
        </div>
        <div class="booking-success-actions">
            <a href="invoice.php?ref=<?php echo urlencode($booking_ref); ?>&token=<?php echo urlencode($checkin_token); ?>"
               target="_blank"
               class="btn-home" style="background:#7C533C; color:#fff; margin-right:10px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:4px; vertical-align:middle;"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Print / PDF Invoice
            </a>
            <a href="assets/qrcodes/qr_booking_<?php echo $booking_id; ?>.png"
               download="SantaFe_Pass_<?php echo htmlspecialchars($booking_ref); ?>.png"
               class="btn-home" style="background:#3A251A; color:#fff; margin-right:10px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:4px; vertical-align:middle;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Save QR Pass (PNG)
            </a>
            <a href="my_booking" class="btn-home" style="margin-right:10px;">Manage Booking</a>
        </div>
    </div>
</div>
