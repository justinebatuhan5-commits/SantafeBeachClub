(function () {
    'use strict';

    var pageData = { csrfToken: '', noshowHandoff: null };
    var dataEl = document.getElementById('adminRefundsPageData');
    if (dataEl && dataEl.textContent) {
        try {
            pageData = JSON.parse(dataEl.textContent);
        } catch (e) {
            pageData = { csrfToken: '', noshowHandoff: null };
        }
    }

    let currentRefundId = null;
    const refundCsrfToken = pageData.csrfToken || '';
    const noshowHandoff = pageData.noshowHandoff || null;
    let manualRefundRecipient = { accountName: '', mobile: '', revealed: false };

    function openReceptionRefundModal(handoff = null) {
        const modal = document.getElementById('receptionRefundModal');
        if (!modal) return;
        document.getElementById('receptionBookingRef').value = handoff ? 'REF-' + String(handoff.booking_id).padStart(3, '0') : '';
        document.getElementById('receptionRefundReason').value = handoff ? handoff.reason : '';
        document.getElementById('receptionBookingError').classList.remove('show');
        document.getElementById('receptionReasonError').classList.remove('show');
        modal.classList.add('show');
        setTimeout(() => document.getElementById('receptionBookingRef').focus(), 50);
    }

    function closeReceptionRefundModal() {
        const modal = document.getElementById('receptionRefundModal');
        if (modal) modal.classList.remove('show');
    }

    async function submitReceptionRefund() {
        const referenceInput = document.getElementById('receptionBookingRef');
        const reasonInput = document.getElementById('receptionRefundReason');
        const match = referenceInput.value.trim().match(/^(?:REF-)?0*([1-9][0-9]*)$/i);
        const reason = reasonInput.value.trim();
        document.getElementById('receptionBookingError').classList.toggle('show', !match);
        document.getElementById('receptionReasonError').classList.toggle('show', reason === '');
        if (!match || reason === '') return;

        const btn = document.getElementById('submitReceptionRefundBtn');
        const original = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Submitting...';
        const fd = new FormData();
        fd.append('booking_id', match[1]);
        fd.append('reason', reason);
        fd.append('csrf_token', refundCsrfToken);
        try {
            const response = await fetch('../backend/api/refund_request_api.php?action=submit_request', { method: 'POST', body: fd });
            const data = await response.json();
            if (!data.success) throw new Error(data.message || 'Could not submit the refund request.');
            showToast('Refund request submitted for administrator review.', 'success');
            setTimeout(() => { window.location.href = 'admin_refunds?status=requested'; }, 1000);
        } catch (error) {
            btn.disabled = false;
            btn.textContent = original;
            showToast(error.message || 'Network error. Please try again.', 'error');
        }
    }

    async function approveRefund(refundId, guestName, amount, btn) {
        if (!await SiteDialog.confirm(`Approve the refund request for ${guestName}?\n\nAmount to be refunded: ₱${amount}\n\nThis will notify the guest and move the request to "Approved".`, { title: 'Approve refund?', confirmText: 'Approve refund' })) return;
        const origHTML = btn.innerHTML;
        btn.innerHTML = '<span class="loading-dot"></span> Approving...';
        btn.disabled = true;

        const fd = new FormData();
        fd.append('request_id', refundId);
        fd.append('csrf_token', refundCsrfToken);

        fetch('../backend/api/refund_request_api.php?action=approve', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showToast('Refund approved. Payment processing remains pending.', 'success');
                    setTimeout(() => location.reload(), 1200);
                } else {
                    btn.innerHTML = origHTML;
                    btn.disabled = false;
                    showToast('Error: ' + data.message, 'error');
                }
            })
            .catch(() => {
                btn.innerHTML = origHTML;
                btn.disabled = false;
                showToast('Network error. Please try again.', 'error');
            });
    }

    function openRejectModal(refundId, guestName) {
        currentRefundId = refundId;
        document.getElementById('rejectGuestName').textContent = guestName;
        document.getElementById('rejectionReason').value = '';
        document.getElementById('rejectModal').classList.add('show');
    }

    function closeModal(id) {
        const el = document.getElementById(id);
        if (el) el.classList.remove('show');
        currentRefundId = null;
    }

    function submitReject() {
        const reason = document.getElementById('rejectionReason').value.trim();
        if (!reason) { showToast('Please enter a rejection reason.', 'warning'); return; }

        const btn = document.getElementById('rejectBtn');
        btn.innerHTML = '<span class="loading-dot"></span> Rejecting...';
        btn.disabled = true;

        const fd = new FormData();
        fd.append('request_id', currentRefundId);
        fd.append('rejection_reason', reason);
        fd.append('csrf_token', refundCsrfToken);

        fetch('../backend/api/refund_request_api.php?action=reject', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    closeModal('rejectModal');
                    showToast('Refund rejected. Guest has been notified.', 'success');
                    setTimeout(() => location.reload(), 1200);
                } else {
                    btn.innerHTML = 'Reject Request';
                    btn.disabled = false;
                    showToast('Error: ' + data.message, 'error');
                }
            })
            .catch(() => {
                btn.innerHTML = 'Reject Request';
                btn.disabled = false;
                showToast('Network error. Please try again.', 'error');
            });
    }

    async function cancelRefundApproval(refundId, btn) {
        if (!await SiteDialog.confirm('Cancel this refund approval? No payment or booking record will be changed. The request will move to Rejected.', { title: 'Cancel refund approval?', confirmText: 'Cancel approval', danger: true })) return;
        const original = btn.innerHTML;
        btn.disabled = true;
        btn.textContent = 'Cancelling...';
        const fd = new FormData();
        fd.append('request_id', refundId);
        fd.append('reason', 'Refund approval cancelled by management before payment processing.');
        fd.append('csrf_token', refundCsrfToken);
        try {
            const response = await fetch('../backend/api/refund_request_api.php?action=cancel_approval', { method: 'POST', body: fd });
            const data = await response.json();
            if (!data.success) throw new Error(data.message || 'Could not cancel approval.');
            showToast('Refund approval cancelled. No money was refunded.', 'success');
            setTimeout(() => location.reload(), 1000);
        } catch (error) {
            btn.disabled = false;
            btn.innerHTML = original;
            showToast(error.message || 'Network error. Please try again.', 'error');
        }
    }

    function processPayMongoRefund(refundId) {
        currentRefundId = refundId;
        const input = document.getElementById('paymongoRefundId');
        input.value = '';
        document.getElementById('paymongoRefundError').classList.remove('show');
        document.getElementById('paymongoRefundModal').classList.add('show');
        setTimeout(() => input.focus(), 50);
    }

    function openRefundLinkModal(refundId, guestName, guestEmail) {
        currentRefundId = refundId;
        const input = document.getElementById('paymongoTransferLink');
        document.getElementById('refundLinkRecipient').textContent = guestName + ' — ' + (guestEmail || 'No email address');
        input.value = '';
        document.getElementById('paymongoTransferLinkError').classList.remove('show');
        document.getElementById('refundLinkModal').classList.add('show');
        setTimeout(() => input.focus(), 50);
    }

    function closeRefundLinkModal() {
        document.getElementById('refundLinkModal').classList.remove('show');
        document.getElementById('paymongoTransferLinkError').classList.remove('show');
        currentRefundId = null;
    }

    async function sendRefundLinkEmail() {
        const input = document.getElementById('paymongoTransferLink');
        const link = input.value.trim();
        const error = document.getElementById('paymongoTransferLinkError');
        let valid = false;
        try {
            const parsed = new URL(link);
            valid = parsed.protocol === 'https:' && parsed.hostname.toLowerCase() === 'transfer.paymongo.com';
        } catch (_) {}
        if (!valid) {
            error.classList.add('show');
            input.focus();
            return;
        }
        error.classList.remove('show');
        const btn = document.getElementById('sendRefundLinkBtn');
        const original = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Sending...';
        const fd = new FormData();
        fd.append('request_id', currentRefundId);
        fd.append('refund_link', link);
        fd.append('csrf_token', refundCsrfToken);
        try {
            const response = await fetch('../backend/api/refund_request_api.php?action=send_refund_link', { method: 'POST', body: fd });
            const data = await response.json();
            if (!data.success) throw new Error(data.message || 'Could not email the refund link.');
            closeRefundLinkModal();
            showToast(data.message, 'success');
        } catch (sendError) {
            showToast(sendError.message || 'Network error. Please try again.', 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = original;
        }
    }

    function closePayMongoRefundModal() {
        document.getElementById('paymongoRefundModal').classList.remove('show');
        document.getElementById('paymongoRefundError').classList.remove('show');
        currentRefundId = null;
    }

    async function submitPayMongoRefund() {
        const input = document.getElementById('paymongoRefundId');
        const providerRefundId = input.value.trim();
        const error = document.getElementById('paymongoRefundError');
        if (!/^ref_[A-Za-z0-9]+$/.test(providerRefundId)) {
            error.classList.add('show');
            input.focus();
            return;
        }
        error.classList.remove('show');
        const btn = document.getElementById('verifyPayMongoRefundBtn');
        await runRefundAction('process_paymongo', currentRefundId, btn, 'Verifying...', 'PayMongo refund verified.', { paymongo_refund_id: providerRefundId });
    }

    async function syncPayMongoRefund(refundId, btn) {
        await runRefundAction('sync_paymongo', refundId, btn, 'Verifying...', 'PayMongo refund status verified.');
    }

    async function sendManualRefundLink(refundId, btn, paymongoFallback = false) {
        const warning = paymongoFallback
            ? 'This payment originated from GCash through PayMongo. Use this manual fallback only if you will NOT also create a PayMongo refund, or the guest could be refunded twice. Email the secure GCash details link?'
            : 'Email a secure, single-use GCash details link to this guest? The link expires after 48 hours.';
        if (!await SiteDialog.confirm(warning, { title: paymongoFallback ? 'Use manual GCash fallback?' : 'Send GCash details link?', confirmText: 'Send link' })) return;
        const original = btn.innerHTML;
        btn.disabled = true;
        btn.textContent = 'Sending...';
        const fd = new FormData();
        fd.append('request_id', refundId);
        fd.append('csrf_token', refundCsrfToken);
        try {
            const response = await fetch('../backend/api/manual_refund_api.php?action=generate_link', { method: 'POST', body: fd });
            const data = await response.json();
            if (!data.success) throw new Error(data.message || 'Could not send the link.');
            showToast(data.message, 'success');
            setTimeout(() => location.reload(), 1000);
        } catch (error) {
            btn.disabled = false;
            btn.innerHTML = original;
            showToast(error.message || 'Network error.', 'error');
        }
    }

    function renderManualRefundRecipient() {
        const recipient = manualRefundRecipient;
        const shownMobile = recipient.revealed
            ? recipient.mobile
            : (recipient.mobile ? recipient.mobile.slice(0, 2) + '•• •••• ' + recipient.mobile.slice(-3) : 'No mobile number');
        document.getElementById('manualRefundRecipient').textContent = recipient.accountName + ' — ' + shownMobile;
        const toggle = document.getElementById('toggleManualRefundMobile');
        toggle.hidden = !recipient.mobile;
        toggle.textContent = recipient.revealed ? 'Hide number' : 'Show full number';
        toggle.setAttribute('aria-pressed', recipient.revealed ? 'true' : 'false');
    }

    function toggleManualRefundMobile() {
        manualRefundRecipient.revealed = !manualRefundRecipient.revealed;
        renderManualRefundRecipient();
    }

    function openManualRefundModal(refundId, accountName, mobile) {
        currentRefundId = refundId;
        manualRefundRecipient = { accountName, mobile, revealed: false };
        renderManualRefundRecipient();
        document.getElementById('manualTransferReference').value = '';
        document.getElementById('manualReceipt').value = '';
        document.getElementById('manualRefundNotes').value = '';
        document.getElementById('manualRefundModal').classList.add('show');
    }

    async function completeManualRefund() {
        const reference = document.getElementById('manualTransferReference').value.trim();
        const receipt = document.getElementById('manualReceipt').files[0];
        const notes = document.getElementById('manualRefundNotes').value.trim();
        if (reference.length < 6) { showToast('Enter the GCash transfer reference.', 'warning'); return; }
        if (!receipt) { showToast('Upload the GCash transfer receipt.', 'warning'); return; }
        if (!await SiteDialog.confirm('Confirm that the money was actually sent to the displayed GCash account. This will mark the payment refunded.', { title: 'Complete manual refund?', confirmText: 'Confirm completed' })) return;
        const btn = document.getElementById('completeManualRefundBtn');
        const original = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Saving...';
        const fd = new FormData();
        fd.append('request_id', currentRefundId);
        fd.append('transfer_reference', reference);
        fd.append('notes', notes);
        fd.append('receipt', receipt);
        fd.append('csrf_token', refundCsrfToken);
        try {
            const response = await fetch('../backend/api/manual_refund_api.php?action=complete', { method: 'POST', body: fd });
            const data = await response.json();
            if (!data.success) throw new Error(data.message || 'Could not complete refund.');
            showToast(data.message, 'success');
            setTimeout(() => { location.href = 'admin_refunds?status=completed'; }, 1000);
        } catch (error) {
            btn.disabled = false;
            btn.textContent = original;
            showToast(error.message || 'Network error.', 'error');
        }
    }

    async function resetRefundVerification(refundId, btn) {
        if (!await SiteDialog.confirm('Reset this guest verification lock and OTP limits?', { title: 'Reset email verification?', confirmText: 'Reset verification' })) return;
        const original = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Resetting...';
        const fd = new FormData();
        fd.append('request_id', refundId);
        fd.append('csrf_token', refundCsrfToken);
        try {
            const response = await fetch('../backend/api/manual_refund_api.php?action=reset_verification', { method: 'POST', body: fd });
            const data = await response.json();
            if (!data.success) throw new Error(data.message || 'Could not reset verification.');
            showToast(data.message, 'success');
            setTimeout(() => location.reload(), 700);
        } catch (error) {
            btn.disabled = false;
            btn.textContent = original;
            showToast(error.message || 'Network error.', 'error');
        }
    }

    async function runRefundAction(action, refundId, btn, busyText, fallbackMessage, extraFields = {}) {
        const original = btn.innerHTML;
        btn.disabled = true;
        btn.textContent = busyText;
        const fd = new FormData();
        fd.append('request_id', refundId);
        fd.append('csrf_token', refundCsrfToken);
        Object.entries(extraFields).forEach(([key, value]) => fd.append(key, value));
        try {
            const response = await fetch('../backend/api/refund_request_api.php?action=' + encodeURIComponent(action), { method: 'POST', body: fd });
            const data = await response.json();
            if (!data.success) throw new Error(data.message || 'Refund action failed.');
            showToast(data.message || fallbackMessage, 'success');
            setTimeout(() => location.reload(), 1200);
        } catch (error) {
            btn.disabled = false;
            btn.innerHTML = original;
            showToast(error.message || 'Network error. Please try again.', 'error');
        }
    }

    function viewDetails(refundId) {
        fetch(`../backend/api/refund_request_api.php?action=get_request&request_id=${refundId}`)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const r = data.data;
                    const fmtAmt = v => v ? '₱' + parseFloat(v).toLocaleString('en-PH', { minimumFractionDigits: 2 }) : '—';
                    const refundSystem = r.approved_refund_method === 'manual_gcash'
                        ? 'Manual GCash Transfer'
                        : (r.paymongo_refund_id ? 'PayMongo Refund' : (/^PayMongo/i.test(r.payment_method || '') ? 'PayMongo / Original Source' : 'Original Payment Source'));
                    document.getElementById('detailsContent').innerHTML = `
                        <table style="width:100%; border-collapse:collapse; font-size:13px;">
                            <tr><td style="padding:7px 0; color:var(--text-muted); width:140px;">Request ID</td><td style="font-weight:600;">#${Security.escapeHTML(r.id)}</td></tr>
                            <tr><td style="padding:7px 0; color:var(--text-muted);">Booking Ref</td><td style="font-weight:600;">REF-${Security.escapeHTML(String(r.booking_id).padStart(3, '0'))}</td></tr>
                            <tr><td style="padding:7px 0; color:var(--text-muted);">Guest</td><td>${Security.escapeHTML(r.guest_name)} (${Security.escapeHTML(r.guest_email)})</td></tr>
                            <tr><td style="padding:7px 0; color:var(--text-muted);">Accommodation</td><td>${Security.escapeHTML(r.accommodation_name)}</td></tr>
                            <tr><td style="padding:7px 0; color:var(--text-muted);">Check-in</td><td>${Security.escapeHTML(r.check_in)}</td></tr>
                            <tr><td style="padding:7px 0; color:var(--text-muted);">Refund Amount</td><td style="font-weight:700; color:var(--primary);">${fmtAmt(r.net_refund)}</td></tr>
                            <tr><td style="padding:7px 0; color:var(--text-muted);">Cancellation Fee</td><td>${fmtAmt(r.refund_fee)}</td></tr>
                            <tr><td style="padding:7px 0; color:var(--text-muted);">Original Paid</td><td>${fmtAmt(r.original_amount)}</td></tr>
                            <tr><td style="padding:7px 0; color:var(--text-muted);">Original Payment</td><td>${Security.escapeHTML(r.payment_method || '—')}</td></tr>
                            <tr><td style="padding:7px 0; color:var(--text-muted);">Refund System Used</td><td><strong>${Security.escapeHTML(refundSystem)}</strong></td></tr>
                            ${r.approved_refund_method === 'manual_gcash' ? `<tr><td style="padding:7px 0; color:var(--text-muted);">GCash Reference</td><td><strong>${Security.escapeHTML(r.transfer_reference || '—')}</strong></td></tr><tr><td style="padding:7px 0; color:var(--text-muted);">Guest Confirmation</td><td><strong style="color:${r.guest_confirmed_at ? '#047857' : '#92400e'}">${r.guest_confirmed_at ? 'Confirmed received on ' + Security.escapeHTML(r.guest_confirmed_at) : 'Awaiting guest confirmation'}</strong></td></tr>` : ''}
                            <tr><td style="padding:7px 0; color:var(--text-muted);">Status</td><td><strong style="text-transform:uppercase;">${Security.escapeHTML(r.status)}</strong></td></tr>
                            <tr><td style="padding:7px 0; color:var(--text-muted);">Requested</td><td>${Security.escapeHTML(r.requested_at)}</td></tr>
                            ${r.approved_by ? `<tr><td style="padding:7px 0; color:var(--text-muted);">Reviewed By</td><td>${Security.escapeHTML(r.approved_by)} on ${Security.escapeHTML(r.approved_at)}</td></tr>` : ''}
                            ${r.rejection_reason ? `<tr><td style="padding:7px 0; color:var(--text-muted);">Reject Reason</td><td style="color:#991b1b;">${Security.escapeHTML(r.rejection_reason)}</td></tr>` : ''}
                            <tr><td style="padding:7px 0; color:var(--text-muted);" colspan="2"><strong>Guest Reason:</strong><br><span style="color:var(--text-main); line-height:1.5;">${Security.escapeHTML(r.reason || '—')}</span></td></tr>
                        </table>
                    `;
                    document.getElementById('detailsModal').classList.add('show');
                } else {
                    showToast('Could not load details: ' + data.message, 'error');
                }
            })
            .catch(() => showToast('Network error loading details.', 'error'));
    }

    function showToast(msg, type = 'info') {
        const colors = { success: '#15803d', error: '#991b1b', warning: '#92400e', info: '#1e40af' };
        const t = document.createElement('div');
        t.textContent = msg;
        Object.assign(t.style, {
            position: 'fixed', bottom: '24px', right: '24px', zIndex: '9999',
            padding: '12px 20px', borderRadius: '8px',
            background: colors[type] || colors.info, color: '#fff',
            fontSize: '13px', fontWeight: '600',
            boxShadow: '0 8px 24px rgba(0,0,0,0.2)',
            animation: 'slideUp 0.3s ease',
            maxWidth: '360px', lineHeight: '1.5'
        });
        document.body.appendChild(t);
        setTimeout(() => { t.style.opacity = '0'; t.style.transition = 'opacity 0.3s'; setTimeout(() => t.remove(), 300); }, 3500);
    }

    if (noshowHandoff) openReceptionRefundModal(noshowHandoff);

    document.querySelectorAll('.modal-backdrop').forEach(m => {
        m.addEventListener('click', e => {
            if (e.target === m) m.classList.remove('show');
        });
    });

    document.addEventListener('click', function (e) {
        const el = e.target.closest('[data-action]');
        if (!el) return;
        const action = el.dataset.action;

        if (action === 'open-reception-modal') {
            openReceptionRefundModal();
        } else if (action === 'close-reception-modal') {
            closeReceptionRefundModal();
        } else if (action === 'submit-reception-refund') {
            submitReceptionRefund();
        } else if (action === 'approve-refund') {
            approveRefund(Number(el.dataset.id), el.dataset.guest || '', el.dataset.amount || '0.00', el);
        } else if (action === 'open-reject-modal') {
            openRejectModal(Number(el.dataset.id), el.dataset.guest || '');
        } else if (action === 'submit-reject') {
            submitReject();
        } else if (action === 'open-refund-link-modal') {
            openRefundLinkModal(Number(el.dataset.id), el.dataset.guest || '', el.dataset.email || '');
        } else if (action === 'close-refund-link-modal') {
            closeRefundLinkModal();
        } else if (action === 'send-refund-link') {
            sendRefundLinkEmail();
        } else if (action === 'process-paymongo') {
            processPayMongoRefund(Number(el.dataset.id));
        } else if (action === 'close-paymongo-modal') {
            closePayMongoRefundModal();
        } else if (action === 'submit-paymongo-refund') {
            submitPayMongoRefund();
        } else if (action === 'sync-paymongo') {
            syncPayMongoRefund(Number(el.dataset.id), el);
        } else if (action === 'send-manual-link') {
            sendManualRefundLink(Number(el.dataset.id), el, el.dataset.fallback === '1');
        } else if (action === 'reset-verification') {
            resetRefundVerification(Number(el.dataset.id), el);
        } else if (action === 'open-manual-modal') {
            openManualRefundModal(Number(el.dataset.id), el.dataset.accountName || '', el.dataset.mobile || '');
        } else if (action === 'toggle-manual-mobile') {
            toggleManualRefundMobile();
        } else if (action === 'complete-manual-refund') {
            completeManualRefund();
        } else if (action === 'cancel-approval') {
            cancelRefundApproval(Number(el.dataset.id), el);
        } else if (action === 'view-details') {
            viewDetails(Number(el.dataset.id));
        } else if (action === 'close-modal') {
            closeModal(el.dataset.modalId || '');
        }
    });
})();
