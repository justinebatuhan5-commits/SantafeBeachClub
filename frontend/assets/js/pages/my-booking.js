(function() {
    const lookupForm = document.getElementById('lookupForm');
    if (lookupForm) {
        const lookupBtn = document.getElementById('lookupBtn');
        const lookupText = document.getElementById('lookupBtnText');
        const lookupSpin = document.getElementById('lookupSpinner');
        const errorBox = document.getElementById('lookupError');
        const errorMsg = document.getElementById('lookupErrorMsg');

        const otpContainer = document.getElementById('otpStepContainer');
        const guestOtpForm = document.getElementById('guestOtpForm');
        const guestFullOtp = document.getElementById('guestFullOtp');
        const guestOtpDigits = document.querySelectorAll('.otp-box-digit');
        const guestVerifyBtn = document.getElementById('guestVerifyBtn');
        const guestVerifyText = document.getElementById('guestVerifyBtnText');
        const guestVerifySpin = document.getElementById('guestVerifySpinner');
        const guestResendBtn = document.getElementById('guestResendBtn');
        const guestResendTimer = document.getElementById('guestResendTimer');
        const guestSeconds = document.getElementById('guestSeconds');
        const guestBackBtn = document.getElementById('guestBackBtn');
        let guestOtpVerificationPending = false;

        function showError(msg) {
            errorMsg.textContent = msg;
            errorBox.style.display = 'flex';
            errorBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        function hideError() {
            errorBox.style.display = 'none';
        }

        lookupForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            hideError();

            const email = document.getElementById('email').value.trim();
            const ref = document.getElementById('booking_ref').value.trim().toUpperCase();

            if (!email || !ref) {
                showError('Please enter both your email and booking reference.');
                return;
            }
            if (!/^REF-\d+$/i.test(ref)) {
                showError('Booking reference format is invalid. It should look like REF-001.');
                return;
            }

            lookupBtn.disabled = true;
            lookupText.textContent = 'Sending Code...';
            lookupSpin.style.display = 'block';

            try {
                const recaptchaSiteKey = lookupForm.dataset.recaptchaSiteKey || '6LfE7bEtAAAAAKWR7cu0DZaBeVem3ZIuHOyJ7zWT';
                const recaptchaToken = await Promise.race([
                    grecaptcha.execute(recaptchaSiteKey, { action: 'guest_lookup' }),
                    new Promise((_, reject) => setTimeout(() => reject(new Error('reCAPTCHA timeout')), 5000))
                ]);
                document.getElementById('g-recaptcha-response').value = recaptchaToken;
            } catch (err) {
                console.warn('reCAPTCHA failed to load, proceeding anyway.');
            }

            const formData = new FormData(lookupForm);
            formData.set('booking_ref', ref);

            try {
                const response = await fetch('../backend/api/guest_lookup_api.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await response.json();

                if (data.success && data.mfa) {
                    lookupForm.style.display = 'none';
                    otpContainer.style.display = 'block';
                    guestOtpDigits[0].focus();
                    startGuestCooldown(60);
                } else {
                    showError(data.error || 'Booking not found. Please check your details and try again.');
                    lookupBtn.disabled = false;
                    lookupText.textContent = 'Find Reservation';
                    lookupSpin.style.display = 'none';
                }
            } catch (err) {
                showError('A network error occurred. Please check your connection and try again.');
                lookupBtn.disabled = false;
                lookupText.textContent = 'Find Reservation';
                lookupSpin.style.display = 'none';
            }
        });

        guestOtpDigits.forEach((input, idx) => {
            input.setAttribute('aria-label', 'Verification code digit ' + (idx + 1) + ' of 6');
            input.addEventListener('animationend', () => input.classList.remove('digit-pop'));
            input.addEventListener('input', (e) => {
                input.value = input.value.replace(/[^0-9]/g, '').slice(-1);
                if (input.value) animateOtpDigit(input);
                if (e.target.value.length === 1 && idx < guestOtpDigits.length - 1) {
                    guestOtpDigits[idx + 1].focus();
                }
                updateGuestFullOtp();
                maybeAutoSubmitGuestOtp();
            });
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !input.value && idx > 0) {
                    guestOtpDigits[idx - 1].focus();
                }
            });
            input.addEventListener('paste', (e) => {
                e.preventDefault();
                const pasteData = e.clipboardData.getData('text').trim();
                if (/^[0-9]{6}$/.test(pasteData)) {
                    pasteData.split('').forEach((c, i) => {
                        if (guestOtpDigits[i]) {
                            guestOtpDigits[i].value = c;
                            animateOtpDigit(guestOtpDigits[i]);
                        }
                    });
                    updateGuestFullOtp();
                    guestOtpDigits[guestOtpDigits.length - 1].focus();
                    maybeAutoSubmitGuestOtp();
                }
            });
        });

        function animateOtpDigit(input) {
            input.classList.remove('digit-pop');
            void input.offsetWidth;
            input.classList.add('digit-pop');
        }

        function updateGuestFullOtp() {
            let combined = '';
            guestOtpDigits.forEach(d => {
                combined += d.value;
                d.classList.toggle('is-filled', d.value.length === 1);
            });
            guestFullOtp.value = combined;
        }

        function maybeAutoSubmitGuestOtp() {
            if (guestFullOtp.value.length === 6 && !guestOtpVerificationPending) {
                guestOtpForm.requestSubmit();
            }
        }

        guestOtpForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            hideError();
            updateGuestFullOtp();

            if (guestFullOtp.value.length !== 6) {
                showError('Please enter all 6 digits.');
                return;
            }

            if (guestOtpVerificationPending) return;
            guestOtpVerificationPending = true;

            guestVerifyBtn.disabled = true;
            guestVerifyText.textContent = 'Verifying...';
            guestVerifySpin.style.display = 'inline-block';

            const formData = new FormData(guestOtpForm);

            try {
                const response = await fetch('../backend/api/guest_verify_otp_api.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await response.json();

                if (data.success) {
                    window.location.reload();
                } else {
                    guestOtpVerificationPending = false;
                    showError(data.error || 'Verification failed.');
                    guestVerifyBtn.disabled = false;
                    guestVerifyText.textContent = 'Verify & View Booking';
                    guestVerifySpin.style.display = 'none';

                    if (data.reset) {
                        setTimeout(() => {
                            otpContainer.style.display = 'none';
                            lookupForm.style.display = 'block';
                            lookupBtn.disabled = false;
                            lookupText.textContent = 'Find Reservation';
                            lookupSpin.style.display = 'none';
                        }, 2000);
                    }
                }
            } catch (err) {
                guestOtpVerificationPending = false;
                showError('A network error occurred. Please try again.');
                guestVerifyBtn.disabled = false;
                guestVerifyText.textContent = 'Verify & View Booking';
                guestVerifySpin.style.display = 'none';
            }
        });

        let guestCooldown = 0;
        function startGuestCooldown(sec) {
            guestCooldown = sec;
            guestResendBtn.disabled = true;
            guestResendTimer.style.display = 'inline';
            guestSeconds.textContent = guestCooldown;

            const interval = setInterval(() => {
                guestCooldown--;
                guestSeconds.textContent = guestCooldown;
                if (guestCooldown <= 0) {
                    clearInterval(interval);
                    guestResendBtn.disabled = false;
                    guestResendTimer.style.display = 'none';
                }
            }, 1000);
        }

        guestResendBtn.addEventListener('click', async () => {
            if (guestCooldown > 0) return;
            hideError();

            const formData = new FormData();
            formData.append('action', 'resend');
            const token = document.querySelector('input[name="csrf_token"]').value;
            formData.append('csrf_token', token);

            try {
                const response = await fetch('../backend/api/guest_verify_otp_api.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await response.json();
                if (data.success) {
                    startGuestCooldown(60);
                    SiteDialog.alert(data.message || 'New code sent to your email.');
                } else {
                    showError(data.error || 'Failed to resend code.');
                }
            } catch (err) {
                showError('Network error resending code.');
            }
        });

        guestBackBtn.addEventListener('click', () => {
            otpContainer.style.display = 'none';
            lookupForm.style.display = 'block';
            lookupBtn.disabled = false;
            lookupText.textContent = 'Find Reservation';
            lookupSpin.style.display = 'none';
        });

        const bookingRefInput = document.getElementById('booking_ref');
        if (bookingRefInput) {
            bookingRefInput.addEventListener('input', function() {
                const pos = this.selectionStart;
                this.value = this.value.toUpperCase();
                this.setSelectionRange(pos, pos);
            });
        }
    }

    function showConfirm(opts) {
        var overlay = document.getElementById('sfbc-confirm-overlay');
        var iconEl = document.getElementById('sfbc-confirm-icon');
        var title = document.getElementById('sfbc-confirm-title');
        var message = document.getElementById('sfbc-confirm-message');
        var okBtn = document.getElementById('sfbc-confirm-ok');
        var cancelBtn = document.getElementById('sfbc-confirm-cancel');

        var iconKey = opts.icon || 'cancel';
        var iconSvg = '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>';

        if (iconKey.indexOf('<svg') !== -1) {
            iconSvg = iconKey;
        } else if (iconKey === 'lock') {
            iconSvg = '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#D97706" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>';
        } else if (iconKey === 'trash') {
            iconSvg = '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#DC2626" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6l-1 14H6L5 6"></path><path d="M10 11v6M14 11v6"></path></svg>';
        } else if (iconKey === 'warning') {
            iconSvg = '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#D97706" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>';
        }

        iconEl.innerHTML = iconSvg;
        iconEl.style.background = opts.iconBg || '#FEE2E2';
        title.textContent = opts.title || 'Are you sure?';
        message.textContent = opts.message || 'This action cannot be undone.';
        okBtn.textContent = opts.confirmText || 'Confirm';
        okBtn.style.background = opts.confirmColor ? opts.confirmColor : '#DC2626';

        overlay.style.display = 'flex';

        var newOk = okBtn.cloneNode(true);
        okBtn.parentNode.replaceChild(newOk, okBtn);
        newOk.addEventListener('click', function() {
            overlay.style.display = 'none';
            if (typeof opts.onConfirm === 'function') opts.onConfirm();
        });

        var newCancel = cancelBtn.cloneNode(true);
        cancelBtn.parentNode.replaceChild(newCancel, cancelBtn);
        newCancel.addEventListener('click', function() { overlay.style.display = 'none'; });
        overlay.onclick = function(e) { if (e.target === overlay) overlay.style.display = 'none'; };
    }

    window.showConfirm = showConfirm;
})();
