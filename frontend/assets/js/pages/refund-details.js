(function () {
    const card = document.getElementById('refundDetailsCard');
    const message = document.getElementById('message');
    if (!card || !message) return;

    const csrf = card.dataset.csrf || '';
    const token = card.dataset.token || '';
    let refundOtpVerificationPending = false;

    function showMessage(t, o = false) {
        message.className = 'msg ' + (o ? 'ok' : 'err');
        message.textContent = t;
    }

    document.getElementById('sendOtpBtn')?.addEventListener('click', async e => {
        const b = e.currentTarget;
        b.disabled = true;
        const f = new FormData();
        f.append('csrf_token', csrf);
        f.append('token', token);
        try {
            const r = await fetch('../backend/api/manual_refund_api.php?action=send_otp', { method: 'POST', body: f });
            const d = await r.json();
            if (!d.success) throw new Error(d.message);
            showMessage(d.message, true);
            setTimeout(() => { b.disabled = false; }, 60000);
        } catch (x) {
            showMessage(x.message || 'Could not send the code.');
            b.disabled = false;
        }
    });

    async function verifyRefundOtp() {
        const input = document.getElementById('otp');
        const b = document.getElementById('verifyOtpBtn');
        const otp = input?.value.trim() || '';
        if (!/^\d{6}$/.test(otp)) {
            showMessage('Enter the six-digit code.');
            return;
        }
        if (refundOtpVerificationPending) return;
        refundOtpVerificationPending = true;
        b.disabled = true;
        const f = new FormData();
        f.append('csrf_token', csrf);
        f.append('token', token);
        f.append('otp', otp);
        try {
            const r = await fetch('../backend/api/manual_refund_api.php?action=verify_otp', { method: 'POST', body: f });
            const d = await r.json();
            if (!d.success) throw new Error(d.message);
            showMessage(d.message, true);
            setTimeout(() => location.reload(), 700);
        } catch (x) {
            refundOtpVerificationPending = false;
            showMessage(x.message || 'Verification failed.');
            b.disabled = false;
            input?.focus();
        }
    }

    document.getElementById('verifyOtpBtn')?.addEventListener('click', verifyRefundOtp);
    document.getElementById('otp')?.addEventListener('input', e => {
        e.target.value = e.target.value.replace(/[^0-9]/g, '').slice(0, 6);
        if(e.target.value.length===6)verifyRefundOtp();
    });

    document.getElementById('detailsForm')?.addEventListener('submit', async e => {
        e.preventDefault();
        const b = document.getElementById('submitBtn');
        b.disabled = true;
        b.textContent = 'Submitting...';
        try {
            const r = await fetch('../backend/api/manual_refund_api.php?action=guest_submit', {
                method: 'POST',
                body: new FormData(e.target)
            });
            const d = await r.json();
            if (!d.success) throw new Error(d.message || 'Submission failed.');
            showMessage(d.message, true);
            e.target.remove();
        } catch (x) {
            showMessage(x.message || 'Please try again.');
            b.disabled = false;
            b.textContent = 'Submit refund details';
        }
    });
})();
