document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('inquiry-form');
    if (!form) return;
    const button = form.querySelector('button[type="submit"]');
    const error = document.getElementById('inquiry-security-error');
    let busy = false;
    form.addEventListener('submit', async (event) => {
        if (event.defaultPrevented) return;
        event.preventDefault();
        if (busy || !form.reportValidity()) return;
        busy = true;
        button.disabled = true;
        error.hidden = true;
        button.textContent = 'Verifying…';
        let timeout;
        try {
            if (!window.grecaptcha) throw new Error('Unavailable');
            const token = await Promise.race([
                new Promise((resolve, reject) => {
                    grecaptcha.ready(() => {
                        try {
                            grecaptcha.execute(form.dataset.siteKey, { action: 'guest_inquiry' }).then(resolve, reject);
                        } catch (e) { reject(e); }
                    });
                }),
                new Promise((_, reject) => { timeout = setTimeout(() => reject(new Error('Timeout')), 15000); })
            ]);
            if (!token) throw new Error('Missing token');
            document.getElementById('inquiry-captcha').value = token;
            button.textContent = 'Sending…';
            HTMLFormElement.prototype.submit.call(form);
        } catch (e) {
            error.textContent = 'Security verification could not complete. Please try again or contact us by phone.';
            error.hidden = false;
            busy = false;
            button.disabled = false;
            button.textContent = 'Send Message';
        } finally {
            clearTimeout(timeout);
        }
    });
});
