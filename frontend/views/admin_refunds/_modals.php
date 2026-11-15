<?php
$is_admin_user = $pageData['isAdminUser'];
?>
<!-- Reject Modal -->
<div id="rejectModal" class="modal-backdrop">
    <div class="modal-box">
        <div class="modal-title">Reject Refund Request</div>
        <div class="modal-field">
            <label>Guest</label>
            <div id="rejectGuestName" style="padding: 8px 12px; background: var(--bg-alt, #f8fafc); border-radius: 6px; font-size: 13.5px; font-weight: 600; color: var(--text-main);"></div>
        </div>
        <div class="modal-field">
            <label for="rejectionReason">Rejection Reason *</label>
            <textarea id="rejectionReason" placeholder="Explain why this refund is being declined. The guest will be notified with this reason..." required></textarea>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary-sm" data-action="close-modal" data-modal-id="rejectModal">Cancel</button>
            <button type="button" class="btn-reject" id="rejectBtn" data-action="submit-reject">Reject Request</button>
        </div>
    </div>
</div>

<?php if (!$is_admin_user): ?>
<!-- Receptionist Submit Refund Modal -->
<div id="receptionRefundModal" class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="receptionRefundTitle">
    <div class="modal-box">
        <div class="modal-title" id="receptionRefundTitle">Submit Guest Refund Request</div>
        <div class="modal-help">
            Confirm the guest's booking reference and reason. The payment and refundable amount are selected and calculated by the server. This does not approve or send money.
        </div>
        <div class="modal-field">
            <label for="receptionBookingRef">Booking reference</label>
            <input id="receptionBookingRef" type="text" autocomplete="off" spellcheck="false" placeholder="REF-025" maxlength="20">
            <div id="receptionBookingError" class="modal-error">Enter a valid booking reference such as REF-025.</div>
        </div>
        <div class="modal-field">
            <label for="receptionRefundReason">Guest's reason</label>
            <textarea id="receptionRefundReason" placeholder="Enter the guest's reason for requesting a refund..." maxlength="1000"></textarea>
            <div id="receptionReasonError" class="modal-error">Enter the guest's reason for the request.</div>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary-sm" type="button" data-action="close-reception-modal">Cancel</button>
            <button class="btn-process" type="button" id="submitReceptionRefundBtn" data-action="submit-reception-refund">Submit Request</button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Record PayMongo Refund Modal -->
<div id="paymongoRefundModal" class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="paymongoRefundTitle">
    <div class="modal-box">
        <div class="modal-title" id="paymongoRefundTitle">Record PayMongo Refund</div>
        <div class="modal-help">
            Create the refund in PayMongo Dashboard first. Then paste the PayMongo refund ID below. The system will verify the payment, amount, currency, and status directly with PayMongo.
        </div>
        <div class="modal-field">
            <label for="paymongoRefundId">PayMongo Refund ID</label>
            <input id="paymongoRefundId" type="text" inputmode="text" autocomplete="off" spellcheck="false" placeholder="ref_..." maxlength="100">
            <div id="paymongoRefundError" class="modal-error">Enter a valid refund ID beginning with ref_.</div>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary-sm" type="button" data-action="close-paymongo-modal">Cancel</button>
            <button class="btn-process" type="button" id="verifyPayMongoRefundBtn" data-action="submit-paymongo-refund">Verify Refund</button>
        </div>
    </div>
</div>

<!-- Email PayMongo Refund Link Modal -->
<div id="refundLinkModal" class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="refundLinkTitle">
    <div class="modal-box">
        <div class="modal-title" id="refundLinkTitle">Email PayMongo Refund Link</div>
        <div class="modal-help">
            Copy the complete link from PayMongo's “Refund Transfers Link Generated” window and paste it here. It will be emailed to the guest's saved address with instructions and the 3-day expiry notice.
        </div>
        <div class="modal-field">
            <label>Send to</label>
            <div id="refundLinkRecipient" style="padding:10px 12px;background:var(--bg-alt, #f8fafc);border-radius:7px;font-size:13px;color:var(--text-main);"></div>
        </div>
        <div class="modal-field">
            <label for="paymongoTransferLink">PayMongo Refund Transfers Link</label>
            <input id="paymongoTransferLink" type="url" inputmode="url" autocomplete="off" spellcheck="false" placeholder="https://transfer.paymongo.com/..." maxlength="2048">
            <div id="paymongoTransferLinkError" class="modal-error">Paste a valid https://transfer.paymongo.com/ link.</div>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary-sm" type="button" data-action="close-refund-link-modal">Cancel</button>
            <button class="btn-approve" type="button" id="sendRefundLinkBtn" data-action="send-refund-link">Send to Guest Email</button>
        </div>
    </div>
</div>

<!-- Complete Manual GCash Refund Modal -->
<div id="manualRefundModal" class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="manualRefundTitle">
    <div class="modal-box">
        <div class="modal-title" id="manualRefundTitle">Record Manual GCash Refund</div>
        <div class="modal-help">Transfer the exact approved amount first. Then record the unique GCash reference and upload the genuine transfer receipt. This action completes the refund.</div>
        <div class="modal-field">
            <label>Recipient</label>
            <div style="display:flex;align-items:center;gap:8px;padding:10px 12px;background:var(--bg-alt,#f8fafc);border-radius:7px">
                <div id="manualRefundRecipient" style="flex:1"></div>
                <button id="toggleManualRefundMobile" class="btn-secondary-sm" type="button" data-action="toggle-manual-mobile" aria-pressed="false">Show full number</button>
            </div>
        </div>
        <div class="modal-field"><label for="manualTransferReference">GCash reference number</label><input id="manualTransferReference" maxlength="100" autocomplete="off" placeholder="e.g. 1234 567 890123"></div>
        <div class="modal-field"><label for="manualReceipt">Transfer receipt (JPG/PNG, max 2 MB)</label><input id="manualReceipt" type="file" accept="image/jpeg,image/png"></div>
        <div class="modal-field"><label for="manualRefundNotes">Internal notes (optional)</label><textarea id="manualRefundNotes" maxlength="1000"></textarea></div>
        <div class="modal-footer"><button class="btn-secondary-sm" type="button" data-action="close-modal" data-modal-id="manualRefundModal">Cancel</button><button class="btn-process" id="completeManualRefundBtn" type="button" data-action="complete-manual-refund">Confirm Money Sent</button></div>
    </div>
</div>

<!-- Details Modal -->
<div id="detailsModal" class="modal-backdrop">
    <div class="modal-box" style="max-width: 520px;">
        <div class="modal-title">Refund Request Details</div>
        <div id="detailsContent" style="font-size: 13.5px; line-height: 1.7; color: var(--text-main);"></div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary-sm" data-action="close-modal" data-modal-id="detailsModal">Close</button>
        </div>
    </div>
</div>
