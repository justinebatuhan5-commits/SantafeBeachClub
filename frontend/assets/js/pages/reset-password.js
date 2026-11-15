(function () {
    const statusContainer = document.getElementById('statusContainer');

    function showError(msg) {
        if (!statusContainer) return;
        statusContainer.innerHTML = `
            <div class="alert-box alert-error">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <span>${Security.escapeHTML(msg)}</span>
            </div>
        `;
    }

    document.querySelectorAll('[data-toggle-password]').forEach(btn => {
        btn.addEventListener('click', function () {
            const input = document.getElementById(this.dataset.togglePassword);
            if (!input) return;
            const isText = input.type === 'text';
            input.type = isText ? 'password' : 'text';
            this.classList.toggle('is-visible', !isText);
        });
    });

    const pwdInput = document.getElementById('password');
    if (pwdInput) {
        pwdInput.addEventListener('input', function () {
            const val = this.value;
            document.getElementById('rule-len')?.classList.toggle('valid', val.length >= 8);
            document.getElementById('rule-upper')?.classList.toggle('valid', /[A-Z]/.test(val));
            document.getElementById('rule-lower')?.classList.toggle('valid', /[a-z]/.test(val));
            document.getElementById('rule-num')?.classList.toggle('valid', /[0-9]/.test(val) && /[\W_]/.test(val));
        });
    }

    const form = document.getElementById('resetForm');
    const submitBtn = document.getElementById('submitBtn');

    if (form && submitBtn) {
        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            const pwd = document.getElementById('password')?.value || '';
            const confirmPwd = document.getElementById('confirm_password')?.value || '';

            if (!pwd || !confirmPwd) {
                showError('Please fill in both password fields.');
                return;
            }

            if (pwd !== confirmPwd) {
                showError('Passwords do not match.');
                return;
            }

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span>Updating password...</span>';

            const formData = new FormData(form);

            try {
                const res = await fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                const data = await res.json();
                const feedbackMsg = data.message || data.error || 'Password update failed.';
                if (data.success) {
                    form.style.display = 'none';
                    const portalUrl = (data.role === 'admin') ? 'admin_login' : 'staff_login';
                    if (statusContainer) {
                        statusContainer.innerHTML = `
                            <div class="alert-box alert-success alert-box--centered">
                                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                <span class="alert-success-msg--lg">${Security.escapeHTML(feedbackMsg)}</span>
                                <a href="${portalUrl}" class="login-btn">
                                    Sign In Now &rarr;
                                </a>
                            </div>
                        `;
                    }
                } else {
                    showError(feedbackMsg);
                }
            } catch (err) {
                showError('An unexpected network error occurred. Please try again.');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<span>Save New Password</span><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>';
            }
        });
    }
})();
