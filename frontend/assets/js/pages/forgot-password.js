(function () {
    const form = document.getElementById('forgotForm');
    const submitBtn = document.getElementById('submitBtn');
    const statusContainer = document.getElementById('statusContainer');
    if (!form || !submitBtn || !statusContainer) return;

    const recaptchaSiteKey = form.dataset.recaptchaSiteKey || '6LfE7bEtAAAAAKWR7cu0DZaBeVem3ZIuHOyJ7zWT';

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        const emailInput = document.getElementById('email');
        const email = emailInput ? emailInput.value.trim() : '';

        if (!email) {
            statusContainer.innerHTML = `
                <div class="alert-box alert-error">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <span>Please enter your account email address.</span>
                </div>
            `;
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span>Sending instructions...</span>';

        if (typeof grecaptcha !== 'undefined') {
            try {
                const token = await grecaptcha.execute(recaptchaSiteKey, { action: 'forgot_password' });
                const recaptchaInput = document.getElementById('g-recaptcha-response');
                if (recaptchaInput) recaptchaInput.value = token;
            } catch (err) {
                console.warn('reCAPTCHA token error:', err);
            }
        }

        const formData = new FormData(form);

        try {
            const res = await fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            const data = await res.json();
            const feedbackMsg = data.message || data.error || 'Request completed.';
            if (data.success) {
                statusContainer.innerHTML = `
                    <div class="alert-box alert-success">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        <span>${Security.escapeHTML(feedbackMsg)}</span>
                    </div>
                `;
                form.reset();
            } else {
                statusContainer.innerHTML = `
                    <div class="alert-box alert-error">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <span>${Security.escapeHTML(feedbackMsg)}</span>
                    </div>
                `;
            }
        } catch (err) {
            statusContainer.innerHTML = `
                <div class="alert-box alert-error">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <span>An unexpected error occurred. Please try again later.</span>
                </div>
            `;
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<span>Send Reset Link</span><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>';
        }
    });
})();
