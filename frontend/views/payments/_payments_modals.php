<?php
$csrf = htmlspecialchars($pageData['csrfToken'] ?? '', ENT_QUOTES, 'UTF-8');
$sendBookingId = (int)($pageData['sendBookingId'] ?? 0);
$sendBooking = $pageData['sendBooking'] ?? null;
?>

<?php if ($sendBookingId > 0 && $sendBooking): ?>
    <div class="gcash-overlay active payments-send-overlay" id="sendConfirmationOverlay">
        <div class="payments-send-card">
            <a href="payments" class="payments-send-close" aria-label="Close">&times;</a>

            <div style="width: 64px; height: 64px; border-radius: 20px; background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%); border: 1.5px solid #BFDBFE; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; box-shadow: 0 10px 25px -5px rgba(59, 130, 246, 0.25);">
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
                </svg>
            </div>

            <h3 style="margin: 0 0 8px; font-size: 20px; font-weight: 800; color: #0F172A; letter-spacing: -0.01em;">Resend Confirmation Email?</h3>
            <p style="margin: 0 0 20px; font-size: 13.5px; color: #64748B; line-height: 1.55;">Send another copy of the booking confirmation and check-in QR pass to the guest.</p>

            <div style="background: #F8FAFC; border: 1.5px solid #E2E8F0; border-radius: 14px; padding: 14px 16px; margin-bottom: 24px; text-align: left; display: flex; align-items: center; gap: 12px;">
                <div style="width: 38px; height: 38px; border-radius: 10px; background: #0F172A; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; flex-shrink: 0;">
                    <?php echo htmlspecialchars(strtoupper(substr((string)($sendBooking['guest_name'] ?: 'G'), 0, 1)), ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <div style="min-width: 0; flex: 1;">
                    <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 2px;">
                        <strong style="font-size: 13.5px; color: #0F172A; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars((string)($sendBooking['guest_name'] ?: 'Guest'), ENT_QUOTES, 'UTF-8'); ?></strong>
                        <span style="background: #DCFCE7; color: #15803D; font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 4px; text-transform: uppercase; letter-spacing: 0.5px;">Verified</span>
                    </div>
                    <div style="font-size: 12.5px; color: #475569; word-break: break-all; font-weight: 500;">
                        <?php echo htmlspecialchars((string)($sendBooking['guest_email'] ?: 'No email address on file'), ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                </div>
            </div>

            <div style="display: flex; gap: 12px; justify-content: center;">
                <a href="payments" class="payments-send-btn-cancel">Not Now</a>
                <?php if (!empty($sendBooking['guest_email'])): ?>
                    <form method="POST" action="payments" style="flex: 1.3; margin: 0;">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                        <input type="hidden" name="action" value="send_confirmation_email">
                        <input type="hidden" name="booking_id" value="<?php echo $sendBookingId; ?>">
                        <button type="submit" class="payments-send-btn-confirm">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
                            Resend Email
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Proof of Payment Image Lightbox Modal -->
<div class="gcash-overlay" id="proofOverlay">
    <div class="gcash-modal" style="max-width:500px; padding:20px; text-align:center;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
            <h3 style="font-size:16px; font-weight:700; margin:0;">Guest Payment Proof</h3>
            <button type="button" data-close-modal="proofOverlay" style="background:none; border:none; font-size:22px; color:#888; cursor:pointer;" aria-label="Close">&times;</button>
        </div>
        <div id="proofRefBadge" style="display:none; background:#F0F7FF; border:1.5px solid #BAE6FD; border-radius:10px; padding:10px 14px; margin-bottom:12px; text-align:center;">
            <div style="font-size:10px; color:#0369A1; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:2px;">Guest-Entered Reference Number</div>
            <div id="proofRefNumber" style="font-size:16px; font-weight:800; color:#005CE6; letter-spacing:1px; font-family:'Courier New',monospace;"></div>
            <div style="font-size:10px; color:#64748B; margin-top:4px;">Compare this with the reference on the receipt screenshot below</div>
        </div>
        <div style="max-height:70vh; overflow-y:auto; border-radius:8px; background:#f5f5f5; padding:8px;">
            <img id="proofModalImg" src="" alt="Proof of Payment" style="max-width:100%; border-radius:6px; display:block; margin:0 auto;">
        </div>
        <button type="button" class="btn-receipt" data-close-modal="proofOverlay" style="margin-top:14px; width:100%; padding:9px;">Close</button>
    </div>
</div>

<!-- Refund Confirmation Modal -->
<div class="gcash-overlay" id="refundOverlay">
    <div class="gcash-modal payments-modal-sheet">
        <div class="payments-modal-bar payments-modal-bar--refund"></div>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px;">
            <div style="display:flex; align-items:center; gap:12px;">
                <img src="assets/logo.png" data-fallback-src="assets/logo.jpg" alt="Santa Fe Beach Club" style="height:38px; width:auto; border-radius:8px; object-fit:contain;">
                <div>
                    <h3 style="font-size:17px; font-weight:800; margin:0; color:#1E293B; letter-spacing:-0.3px; display:flex; align-items:center; gap:6px;">
                        <span style="display:inline-flex; align-items:center; justify-content:center; width:22px; height:22px; border-radius:50%; background:#F3E8FF; color:#7C3AED; font-size:12px;">↩</span>
                        Issue Refund
                    </h3>
                    <div style="font-size:12px; color:#64748B; margin-top:2px;">Santa Fe Beach Club • Billing Management</div>
                </div>
            </div>
            <button type="button" class="payments-modal-circle-close" data-close-modal="refundOverlay" aria-label="Close">&times;</button>
        </div>

        <div id="refundModalInfo" style="background:linear-gradient(135deg, #FAF5FF, #F3E8FF); border:1px solid #E9D5FF; border-radius:12px; padding:14px 16px; margin-bottom:18px; font-size:13.5px; color:#581C87; line-height:1.6;"></div>

        <form method="POST" action="payments" id="refundForm">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <input type="hidden" name="action" value="refund_payment">
            <input type="hidden" name="payment_id" id="refundPaymentId">
            <div style="margin-bottom:16px;">
                <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:7px; text-transform:uppercase; letter-spacing:0.6px;">Reason for Refund <span style="color:#DC2626;">*</span></label>
                <select name="refund_reason" id="refundReasonSelect" class="payments-modal-select">
                    <option value="">— Select a reason —</option>
                    <option value="Guest requested cancellation">Guest requested cancellation</option>
                    <option value="Booking error / duplicate">Booking error / duplicate</option>
                    <option value="No show — policy refund">No show — policy refund</option>
                    <option value="Overcharge correction">Overcharge correction</option>
                    <option value="Other">Other (specify below)</option>
                </select>
            </div>
            <div id="customReasonWrap" style="display:none; margin-bottom:16px;">
                <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:7px; text-transform:uppercase; letter-spacing:0.6px;">Custom Reason</label>
                <input type="text" id="customReasonInput" placeholder="Describe the reason..." style="width:100%; padding:11px 14px; border:1.5px solid #E2E8F0; border-radius:10px; font-size:14px; font-family:inherit; box-sizing:border-box; background:#fff;">
            </div>
            <div style="background:#FFF7ED; border:1px solid #FED7AA; border-radius:12px; padding:12px 14px; margin-bottom:20px; font-size:12.5px; color:#9A3412; display:flex; gap:8px; align-items:flex-start;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#D97706" stroke-width="2.2" style="flex-shrink:0; margin-top:1px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                <div>This will mark the payment as <strong>Refunded</strong> and cancel the associated booking. This action cannot be undone.</div>
            </div>
            <div style="display:flex; gap:12px;">
                <button type="button" class="payments-send-btn-cancel" data-close-modal="refundOverlay">Cancel</button>
                <button type="button" class="payments-btn-refund-confirm" data-action="submit-refund">Confirm Refund</button>
            </div>
        </form>
    </div>
</div>

<!-- Reject Payment Modal -->
<div class="gcash-overlay" id="rejectOverlay">
    <div class="gcash-modal payments-modal-sheet">
        <div class="payments-modal-bar payments-modal-bar--reject"></div>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px;">
            <div style="display:flex; align-items:center; gap:12px;">
                <img src="assets/logo.png" data-fallback-src="assets/logo.jpg" alt="Santa Fe Beach Club" style="height:38px; width:auto; border-radius:8px; object-fit:contain;">
                <div>
                    <h3 style="font-size:17px; font-weight:800; margin:0; color:#1E293B; letter-spacing:-0.3px; display:flex; align-items:center; gap:6px;">
                        <span style="display:inline-flex; align-items:center; justify-content:center; width:22px; height:22px; border-radius:50%; background:#FEE2E2; color:#DC2626; font-size:12px;">&times;</span>
                        Reject Payment
                    </h3>
                    <div style="font-size:12px; color:#64748B; margin-top:2px;">Santa Fe Beach Club • Payment Desk</div>
                </div>
            </div>
            <button type="button" class="payments-modal-circle-close" data-close-modal="rejectOverlay" aria-label="Close">&times;</button>
        </div>

        <div id="rejectModalInfo" style="background:linear-gradient(135deg, #FEF2F2, #FFF5F5); border:1px solid #FECACA; border-radius:12px; padding:14px 16px; margin-bottom:18px; font-size:13.5px; color:#991B1B; line-height:1.6;"></div>

        <form method="POST" action="payments" id="rejectForm">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <input type="hidden" name="action" value="reject_payment">
            <input type="hidden" name="payment_id" id="rejectPaymentId">
            <div style="margin-bottom:16px;">
                <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:7px; text-transform:uppercase; letter-spacing:0.6px;">Reason for Rejection <span style="color:#DC2626;">*</span></label>
                <div style="position:relative;">
                    <select name="reject_reason" id="rejectReasonSelect" class="payments-modal-select payments-modal-select--reject">
                        <option value="">— Select a reason —</option>
                        <option value="Invalid or unclear proof of payment">Invalid or unclear proof of payment</option>
                        <option value="Payment amount does not match the booking total">Payment amount does not match the booking total</option>
                        <option value="Duplicate payment submission">Duplicate payment submission</option>
                        <option value="Expired payment / payment deadline passed">Expired payment / payment deadline passed</option>
                        <option value="Fraudulent or suspicious transaction">Fraudulent or suspicious transaction</option>
                        <option value="Wrong payment account / reference number">Wrong payment account / reference number</option>
                        <option value="Booking no longer available">Booking no longer available</option>
                        <option value="Other">Other (specify below)</option>
                    </select>
                </div>
            </div>
            <div id="rejectCustomReasonWrap" style="display:none; margin-bottom:16px;">
                <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:7px; text-transform:uppercase; letter-spacing:0.6px;">Custom Reason</label>
                <input type="text" id="rejectCustomReasonInput" placeholder="Describe the reason..." style="width:100%; padding:11px 14px; border:1.5px solid #E2E8F0; border-radius:10px; font-size:14px; font-family:inherit; box-sizing:border-box; background:#fff;">
            </div>
            <div style="background:#FFFBEB; border:1px solid #FDE68A; border-radius:12px; padding:12px 14px; margin-bottom:20px; font-size:12.5px; color:#92400E; display:flex; gap:8px; align-items:flex-start;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#D97706" stroke-width="2.2" style="flex-shrink:0; margin-top:1px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                <div>The guest will receive an email notification with the rejection reason. This will <strong>cancel the booking</strong> and cannot be undone.</div>
            </div>
            <div style="display:flex; gap:12px;">
                <button type="button" class="payments-send-btn-cancel" data-close-modal="rejectOverlay">Cancel</button>
                <button type="button" class="payments-btn-reject-confirm" data-action="submit-reject">Confirm Reject &amp; Notify Guest</button>
            </div>
        </form>
    </div>
</div>

<!-- Receipt Preview Modal -->
<div class="gcash-overlay" id="receiptOverlay">
    <div class="payments-receipt-card">
        <div style="display:flex; justify-content:space-between; align-items:center; padding:16px 20px; border-bottom:1px solid #f0f0f0; background:#FAFAFA;">
            <span style="font-weight:700; font-size:15px; color:#1F2937;">Official Receipt Preview</span>
            <button type="button" data-close-modal="receiptOverlay" style="background:none; border:none; font-size:24px; color:#9CA3AF; cursor:pointer; line-height:1;" aria-label="Close">&times;</button>
        </div>
        <div id="receiptPreview" style="background:#fff; color:#000; font-family:'Courier New',Courier,monospace; padding:20px 24px; font-size:13px; line-height:1.6; text-align:center; overflow-y:auto; flex:1;"></div>
        <div style="padding:14px 20px; display:flex; gap:12px; justify-content:flex-end; background:#FAFAFA; border-top:1px solid #f0f0f0;">
            <button type="button" data-close-modal="receiptOverlay" style="background:#fff; border:1px solid #D1D5DB; color:#374151; padding:9px 18px; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer;">Close</button>
            <button type="button" data-action="print-receipt" style="background:#7C533C; border:none; color:#fff; padding:9px 24px; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; box-shadow:0 2px 8px rgba(124,83,60,0.3); display:inline-flex; align-items:center; gap:6px;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Print Receipt
            </button>
        </div>
    </div>
</div>
