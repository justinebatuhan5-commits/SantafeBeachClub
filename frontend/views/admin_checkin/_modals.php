    <!-- Walk-in / Express Check-in Modal -->
    <div class="gcash-overlay" id="walkinModalOverlay">
        <div class="gcash-modal" style="max-width: 580px;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; border-bottom: 1px solid #eee; padding-bottom: 14px;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 42px; height: 42px; background: #F2EBE5; border-radius: 10px; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                        <img src="assets/logo.jpg" alt="Santa Fe Beach Club logo" style="width: 100%; height: 100%; object-fit: cover;">
                    </div>
                    <div>
                        <h2 style="font-size: 19px; font-weight: 800; color: #1f2937; margin: 0;">Express Walk-in Check-in</h2>
                        <p style="color: #6b7280; font-size: 13px; margin: 2px 0 0;">Register, collect payment, &amp; check in arriving guest</p>
                    </div>
                </div>
                <button type="button" data-action="close-walkin" style="background:none; border:none; font-size:22px; color:#9ca3af; cursor:pointer; line-height:1;">&times;</button>
            </div>

            <form method="POST" id="walkinForm" action="admin_checkin">
                <input type="hidden" name="action" value="walkin_checkin">

                <!-- Guest Information -->
                <div class="mobile-form-grid" style="display:grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                    <div style="grid-column: span 2;">
                        <label style="display:block; font-size:11px; font-weight:700; color:#374151; margin-bottom:5px; text-transform:uppercase; letter-spacing:0.5px;">Guest Full Name *</label>
                        <input type="text" name="guest_name" id="walkinGuestName" required placeholder="e.g. Maria Santos" style="width:100%; padding:10px 12px; border:1.5px solid #D1D5DB; border-radius:8px; font-size:14px; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:11px; font-weight:700; color:#374151; margin-bottom:5px; text-transform:uppercase; letter-spacing:0.5px;">Contact Phone</label>
                        <input type="tel" name="guest_phone" id="walkinGuestPhone" placeholder="0917 123 4567" style="width:100%; padding:10px 12px; border:1.5px solid #D1D5DB; border-radius:8px; font-size:14px; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:11px; font-weight:700; color:#374151; margin-bottom:5px; text-transform:uppercase; letter-spacing:0.5px;">Email (Optional)</label>
                        <input type="email" name="guest_email" id="walkinGuestEmail" placeholder="guest@example.com" style="width:100%; padding:10px 12px; border:1.5px solid #D1D5DB; border-radius:8px; font-size:14px; box-sizing:border-box;">
                    </div>
                </div>

                <!-- Stay Details -->
                <div style="background:#F9FAFB; border:1px solid #E5E7EB; border-radius:10px; padding:14px; margin-bottom:16px;">
                    <div style="margin-bottom:12px;">
                        <label style="display:block; font-size:11px; font-weight:700; color:#374151; margin-bottom:5px; text-transform:uppercase; letter-spacing:0.5px;">Select Available Room *</label>
                        <select name="room_id" id="walkinRoomSelect" required style="width:100%; padding:10px 12px; border:1.5px solid #D1D5DB; border-radius:8px; font-size:14px; font-weight:600; background:#fff;">
                            <?php if (empty($available_rooms)): ?>
                                <option value="" disabled selected>No ready rooms available right now</option>
                            <?php else: ?>
                                <option value="" disabled selected>-- Choose an available room --</option>
                                <?php foreach ($available_rooms as $r): ?>
                                    <option value="<?php echo $r['id']; ?>"
                                            data-price="<?php echo $r['price_per_night']; ?>"
                                            data-capacity="<?php echo $r['capacity']; ?>"
                                            data-name="<?php echo htmlspecialchars($r['name']); ?>">
                                        <?php echo htmlspecialchars($r['name']) . ' (₱' . number_format($r['price_per_night'], 2) . '/nt - Max ' . $r['capacity'] . ' Guests)'; ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="mobile-form-grid" style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:10px;">
                        <div>
                            <label style="display:block; font-size:11px; font-weight:700; color:#374151; margin-bottom:5px; text-transform:uppercase;">Check-in</label>
                            <input type="date" name="check_in" id="walkinCheckIn" value="<?php echo date('Y-m-d'); ?>" min="<?php echo date('Y-m-d'); ?>" style="width:100%; padding:8px 10px; border:1px solid #D1D5DB; border-radius:6px; font-size:13px; box-sizing:border-box;">
                        </div>
                        <div>
                            <label style="display:block; font-size:11px; font-weight:700; color:#374151; margin-bottom:5px; text-transform:uppercase;">Check-out</label>
                            <input type="date" name="check_out" id="walkinCheckOut" value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" style="width:100%; padding:8px 10px; border:1px solid #D1D5DB; border-radius:6px; font-size:13px; box-sizing:border-box;">
                        </div>
                        <div>
                            <label style="display:block; font-size:11px; font-weight:700; color:#374151; margin-bottom:5px; text-transform:uppercase;">Guests</label>
                            <input type="number" name="guests_count" id="walkinGuestsCount" value="2" min="1" max="10" style="width:100%; padding:8px 10px; border:1px solid #D1D5DB; border-radius:6px; font-size:13px; box-sizing:border-box;">
                        </div>
                    </div>

                    <!-- Live Cost Banner -->
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:12px; padding-top:10px; border-top:1px dashed #D1D5DB;">
                        <div style="font-size:13px; color:#4B5563;">
                            <span id="walkinNightsDisplay">1 night</span> &bull; <span id="walkinRateDisplay">&#8369;0.00/night</span>
                        </div>
                        <div style="text-align:right;">
                            <span style="font-size:11px; color:#6B7280; text-transform:uppercase; font-weight:700; display:block;">Total Amount</span>
                            <strong style="font-size:19px; color:#7C533C; font-weight:800;" id="walkinTotalCostDisplay">&#8369;0.00</strong>
                        </div>
                    </div>
                </div>

                <!-- Payment Method Section -->
                <div style="margin-bottom: 16px;">
                    <label style="display:block; font-size:11px; font-weight:700; color:#374151; margin-bottom:8px; text-transform:uppercase; letter-spacing:0.5px;">Payment Method</label>
                    <div class="mobile-form-grid" style="display:grid; grid-template-columns: repeat(3, 1fr); gap: 10px;">
                        <label class="pay-method-card">
                            <input type="radio" name="payment_method" value="Front Desk Cash" checked style="display:none;">
                            <div class="pay-card-inner">
                                <span class="icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/></svg></span>
                                <span class="label">Cash</span>
                            </div>
                        </label>
                        <label class="pay-method-card">
                            <input type="radio" name="payment_method" value="GCash QR" style="display:none;">
                            <div class="pay-card-inner">
                                <span class="icon"><img src="assets/images/gcash_logo.png?v=<?= htmlspecialchars($gcashLogoVersion, ENT_QUOTES, 'UTF-8'); ?>" alt="GCash" style="width:22px; height:22px; border-radius:4px; object-fit:contain; vertical-align:middle;"></span>
                                <span class="label">GCash</span>
                            </div>
                        </label>
                        <label class="pay-method-card">
                            <input type="radio" name="payment_method" value="Front Desk Card" style="display:none;">
                            <div class="pay-card-inner">
                                <span class="icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></span>
                                <span class="label">Card</span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Cash Tendered -->
                <div id="walkinCashSection" style="margin-bottom:16px;">
                    <label style="display:block; font-size:11px; font-weight:700; color:#555; margin-bottom:6px; text-transform:uppercase;">Amount Tendered</label>
                    <input type="number" step="0.01" id="walkinAmountTendered" name="amount_tendered" placeholder="0.00" style="width:100%; padding:10px 12px; border:1.5px solid #E5E7EB; border-radius:8px; font-size:15px; font-weight:600; box-sizing:border-box;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:8px; background:#FDF8F5; border:1px solid #EAD8CE; border-radius:8px; padding:8px 12px;">
                        <span style="font-size:13px; color:#7C533C; font-weight:600;">Change</span>
                        <div style="display:flex; align-items:center;">
                            <span style="font-size:17px; font-weight:800; color:#7C533C;">&#8369;</span>
                            <input type="text" id="walkinChangeAmount" name="change_amount" value="0.00" readonly style="border:none; background:transparent; font-size:17px; font-weight:800; color:#7C533C; width:90px; text-align:right;">
                        </div>
                    </div>
                </div>

                <!-- GCash Reference -->
                <div id="walkinGcashSection" style="display:none; margin-bottom:16px; text-align:center;">
                    <p style="font-size:12px; color:#555; margin-bottom:8px;">Present QR to guest or send payment to GCash:</p>
                    <div style="display:inline-block; background:#f0f4ff; border:2px dashed #007AFF; border-radius:10px; padding:10px; margin-bottom:8px;">
                        <img src="assets/gcash_qr.png?v=<?= htmlspecialchars($gcashQrVersion, ENT_QUOTES, 'UTF-8'); ?>" alt="GCash QR Code" width="130" height="130" style="display:block;">
                    </div>
                    <div style="background:#f0f4ff; border-radius:8px; padding:6px 14px; margin:0 auto 10px; max-width:240px; font-weight:700; color:#007AFF; font-size:15px;">
                        09505223146
                    </div>
                    <div style="text-align:left;">
                        <label style="display:block; font-size:11px; font-weight:700; color:#555; margin-bottom:4px; text-transform:uppercase;">GCash Reference Number</label>
                        <input type="text" name="walkin_reference_number_gcash" placeholder="e.g. 1234 5678 9012" style="width:100%; padding:9px 12px; border:1.5px solid #E5E7EB; border-radius:8px; font-size:13px; box-sizing:border-box;">
                    </div>
                </div>

                <!-- Card Reference -->
                <div id="walkinCardSection" style="display:none; margin-bottom:16px;">
                    <label style="display:block; font-size:11px; font-weight:700; color:#555; margin-bottom:6px; text-transform:uppercase;">Card Reference / Approval Code</label>
                    <input type="text" name="walkin_reference_number_card" placeholder="e.g. AUTH-123456" style="width:100%; padding:9px 12px; border:1.5px solid #E5E7EB; border-radius:8px; font-size:13px; box-sizing:border-box;">
                </div>

                <input type="hidden" name="reference_number" id="walkinFinalReferenceNumber" value="">

                <button type="submit" id="btnSubmitWalkin" class="btn-walkin-submit" <?php echo empty($available_rooms) ? 'disabled' : ''; ?>>
                    Complete Walk-in Check-in
                </button>
                <button type="button" data-action="close-walkin" style="width:100%; background:transparent; border:1px solid #E5E7EB; color:#666; padding:9px; border-radius:10px; font-size:13px; font-weight:600; cursor:pointer;">
                    Cancel
                </button>
            </form>
        </div>
    </div>

    <!-- Mark No-Show Confirmation Modal -->
    <div class="gcash-overlay" id="noshowModalOverlay">
        <div class="gcash-modal" style="max-width: 480px;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; border-bottom: 1px solid #eee; padding-bottom: 12px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 40px; height: 40px; background: #FEE2E2; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #DC2626; flex-shrink: 0;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                    </div>
                    <div>
                        <h2 style="font-size: 18px; font-weight: 800; color: #1f2937; margin: 0;">Mark Guest as No-Show</h2>
                        <p style="color: #6b7280; font-size: 13px; margin: 2px 0 0;">Cancel overdue booking and release room</p>
                    </div>
                </div>
                <button type="button" data-action="close-noshow" style="background:none; border:none; font-size:22px; color:#9ca3af; cursor:pointer; line-height:1;">&times;</button>
            </div>

            <div style="background: #FFF5F5; border: 1px solid #FED7D7; border-radius: 10px; padding: 14px; margin-bottom: 16px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 13.5px;">
                    <span style="color: #666;">Guest:</span>
                    <strong id="noshowGuestName" style="color: #1f2937;">&mdash;</strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 13.5px;">
                    <span style="color: #666;">Accommodation:</span>
                    <strong id="noshowAccName" style="color: #1f2937;">&mdash;</strong>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 13.5px;">
                    <span style="color: #666;">Scheduled Check-in:</span>
                    <strong id="noshowCheckinDate" style="color: #DC2626;">&mdash;</strong>
                </div>
            </div>

            <p style="font-size: 13px; color: #4B5563; line-height: 1.5; margin-bottom: 14px;">
                This action will mark this reservation as <strong>Cancelled</strong> and immediately make the room available again for other guests and walk-ins.
            </p>

            <form method="POST" id="noshowForm" action="admin_checkin">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="cancel_noshow">
                <input type="hidden" name="booking_id" id="noshowBookingId" value="">

                <div style="margin-bottom: 16px;">
                    <label style="display:block; font-size:11px; font-weight:700; color:#374151; margin-bottom:6px; text-transform:uppercase; letter-spacing:0.5px;">Reason / Note</label>
                    <select name="cancel_reason" id="noshowReasonSelect" style="width:100%; padding:9px 12px; border:1.5px solid #D1D5DB; border-radius:8px; font-size:13px; background:#fff; margin-bottom:8px;">
                        <option value="Guest did not arrive (No-Show)">Guest did not arrive (No-Show)</option>
                        <option value="Contacted guest - unable to arrive / cancelled travel">Contacted guest - unable to arrive / cancelled travel</option>
                        <option value="Check-in date overdue by >24 hours with no response">Check-in date overdue by &gt;24 hours with no response</option>
                        <option value="Balance unpaid after payment deadline and grace period">Balance unpaid after payment deadline and grace period</option>
                        <option value="Other / Staff cancellation">Other / Staff cancellation</option>
                    </select>
                </div>

                <div style="display:flex; gap:10px;">
                    <button type="submit" class="btn-noshow-confirm">
                        Yes, Mark No-Show
                    </button>
                    <button type="button" data-action="close-noshow" style="flex:1; background:transparent; border:1.5px solid #D1D5DB; color:#4B5563; padding:11px; border-radius:8px; font-size:14px; font-weight:600; cursor:pointer;">
                        Keep Booking
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Balance Collection Modal -->
    <div class="gcash-overlay" id="balanceModalOverlay">
        <div class="gcash-modal">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                <div style="width: 40px; height: 40px; background: #E8F5E9; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #2E7D32; flex-shrink: 0;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="1" x2="12" y2="23"></line>
                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                    </svg>
                </div>
                <div>
                    <h2 style="font-size: 20px; font-weight: 800; color: #333; margin: 0;">Collect Balance Payment</h2>
                    <p style="color: #666; font-size: 13px; margin: 2px 0 0;">Verify payment details before guest check-in</p>
                </div>
            </div>

            <div style="background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 12px; padding: 16px; margin-bottom: 20px; color: #333;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px;">
                    <span style="color: #666;">Guest Name:</span>
                    <strong id="modalGuestName">John Doe</strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px;">
                    <span style="color: #666;">Accommodation:</span>
                    <strong id="modalAccName">Standard Room</strong>
                </div>
                <div id="modalDiscountRow" style="display:none; justify-content:space-between; align-items:center; margin-bottom:8px; font-size:14px;">
                    <span style="color:#666;">Discount:</span>
                    <strong id="modalDiscountBadge" style="background:#F3E8FF; color:#7E22CE; padding:4px 9px; border-radius:999px; font-size:11px;"></strong>
                </div>
                <div style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:14px;">
                    <span style="color:#666;">Scheduled Check-in:</span>
                    <strong id="modalScheduledCheckin">&mdash;</strong>
                </div>
                <div id="modalDateWarning" style="display:none;margin:0 0 12px;padding:9px 11px;border-radius:8px;font-size:12px;font-weight:700;"></div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 14px; border-bottom: 1px dashed #E5E7EB; padding-bottom: 12px;">
                    <span style="color: #666;">Total Cost:</span>
                    <strong id="modalTotalCost">&#8369;0.00</strong>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="color: #D32F2F; font-weight: 700; font-size: 15px;">Balance Due:</span>
                    <strong style="color: #D32F2F; font-size: 22px; font-weight: 800;" id="modalBalanceDue">&#8369;0.00</strong>
                </div>
            </div>

            <form method="POST" id="balanceForm" action="admin_checkin">
                <input type="hidden" name="action" value="checkin">
                <input type="hidden" name="booking_id" id="modalBookingId" value="">
                <input type="hidden" name="collect_payment" value="1">
                <input type="hidden" name="balance_amount" id="modalBalanceAmountInput" value="">

                <div style="margin-bottom: 24px;">
                    <label style="display: block; font-size: 11px; font-weight: 700; color: #555; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px;">Select Payment Method</label>
                    <div class="mobile-form-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;">
                        <label class="pay-method-card">
                            <input type="radio" name="payment_method" value="Front Desk Cash" checked style="display:none;">
                            <div class="pay-card-inner">
                                <span class="icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/></svg></span>
                                <span class="label">Cash</span>
                            </div>
                        </label>
                        <label class="pay-method-card">
                            <input type="radio" name="payment_method" value="GCash QR" style="display:none;">
                            <div class="pay-card-inner">
                                <span class="icon"><img src="assets/images/gcash_logo.png?v=<?= htmlspecialchars($gcashLogoVersion, ENT_QUOTES, 'UTF-8'); ?>" alt="GCash" style="width:22px; height:22px; border-radius:4px; object-fit:contain; vertical-align:middle;"></span>
                                <span class="label">GCash</span>
                            </div>
                        </label>
                        <label class="pay-method-card">
                            <input type="radio" name="payment_method" value="Front Desk Card" style="display:none;">
                            <div class="pay-card-inner">
                                <span class="icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></span>
                                <span class="label">Card</span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Cash Section -->
                <div id="cashSection" style="margin-bottom:18px;">
                    <label style="display:block; font-size:11px; font-weight:700; color:#555; margin-bottom:6px; text-transform:uppercase; letter-spacing:0.5px;">Amount Tendered</label>
                    <input type="number" step="0.01" id="amountTendered" name="amount_tendered" placeholder="0.00" style="width:100%; padding:10px 12px; border:1.5px solid #E5E7EB; border-radius:8px; font-size:15px; font-weight:600; box-sizing:border-box;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:10px; background:#F0FFF4; border:1px solid #A7F3D0; border-radius:8px; padding:10px 14px;">
                        <span style="font-size:13px; color:#065F46; font-weight:600;">Change</span>
                        <div style="display:flex; align-items:center;">
                            <span style="font-size:18px; font-weight:800; color:#065F46;">&#8369;</span>
                            <input type="text" id="changeAmount" name="change_amount" value="0.00" readonly style="border:none; background:transparent; font-size:18px; font-weight:800; color:#065F46; width:80px; text-align:right;">
                        </div>
                    </div>
                </div>

                <!-- GCash Section -->
                <div id="gcashSection" style="display:none; margin-bottom:18px; text-align:center;">
                    <p style="font-size:13px; color:#555; margin-bottom:12px;">Ask guest to scan the QR code or send to the number below, then enter the reference number.</p>
                    <div style="display:inline-block; background:#f0f4ff; border:2px dashed #007AFF; border-radius:12px; padding:14px; margin-bottom:12px;">
                        <img src="assets/gcash_qr.png?v=<?= htmlspecialchars($gcashQrVersion, ENT_QUOTES, 'UTF-8'); ?>" alt="GCash QR Code" width="160" height="160" style="display:block;">
                    </div>
                    <div style="background:#f0f4ff; border-radius:10px; padding:10px 20px; margin:0 auto 12px; max-width:260px;">
                        <div style="font-size:11px; color:#888; text-transform:uppercase; letter-spacing:0.5px;">GCash Number</div>
                        <div style="font-size:22px; font-weight:800; color:#007AFF; letter-spacing:2px;">09505223146</div>
                        <div style="font-size:13px; color:#333; font-weight:600; margin-top:2px;">Santa Fe Beach Club</div>
                    </div>
                    <div style="text-align:left; margin-bottom:6px;">
                        <label style="display:block; font-size:11px; font-weight:700; color:#555; margin-bottom:6px; text-transform:uppercase; letter-spacing:0.5px;">GCash Reference Number</label>
                        <input type="text" name="reference_number_gcash" placeholder="e.g. 1234 5678 9012" style="width:100%; padding:10px 12px; border:1.5px solid #E5E7EB; border-radius:8px; font-size:14px; box-sizing:border-box;">
                    </div>
                </div>

                <!-- Card Section -->
                <div id="cardSection" style="display:none; margin-bottom:18px;">
                    <label style="display:block; font-size:11px; font-weight:700; color:#555; margin-bottom:6px; text-transform:uppercase; letter-spacing:0.5px;">Card Reference / Approval Code</label>
                    <input type="text" name="reference_number_card" placeholder="e.g. AUTH-123456" style="width:100%; padding:10px 12px; border:1.5px solid #E5E7EB; border-radius:8px; font-size:14px; box-sizing:border-box;">
                </div>

                <input type="hidden" name="reference_number" id="finalReferenceNumber" value="">

                <button type="submit" style="width:100%; background:#2E7D32; color:#fff; border:none; padding:14px; border-radius:10px; font-size:15px; font-weight:700; cursor:pointer; margin-bottom:10px;">
                    Confirm Check-in &amp; Record Payment
                </button>
                <button type="button" data-action="close-balance" style="width:100%; background:transparent; border:1px solid #E5E7EB; color:#666; padding:10px; border-radius:10px; font-size:14px; font-weight:600; cursor:pointer;">
                    Cancel
                </button>
            </form>
        </div>
    </div>
