(function () {
    const digits = document.querySelectorAll('.otp-digit');
    const fullOtp = document.getElementById('fullOtp');
    const otpForm = document.getElementById('otpForm');
    const msgContainer = document.getElementById('msgContainer');
    const authLoader = document.getElementById('authLoader');
    const verifyBtn = document.getElementById('verifyBtn');
    const resendBtn = document.getElementById('resendBtn');
    const countdownText = document.getElementById('countdownText');
    const timerSpan = document.getElementById('timer');
    const expiryBanner = document.getElementById('otpExpiryBanner');
    const expiryLabel = document.getElementById('otpExpiryLabel');
    const expiryTimer = document.getElementById('otpExpiryTimer');
    const expiryProgress = document.getElementById('otpExpiryProgress');

    let otpVerificationPending = false;
    let resendPending = false;
    let isOtpExpired = false;
    let otpExpiresAtMs = 0;
    let otpInterval = null;
    let cooldown = 0;
    let resendReadyAtMs = 0;
    let resendInterval = null;

    if (!otpForm || !fullOtp || digits.length === 0) return;

    const otpTotalSeconds = Math.max(1, parseInt(expiryBanner?.dataset.total || '600', 10) || 600);
    const resendTotalSeconds = Math.max(1, parseInt(expiryBanner?.dataset.resendTotal || '60', 10) || 60);
    const initialOtpRemaining = Math.max(0, parseInt(expiryBanner?.dataset.remaining || String(otpTotalSeconds), 10) || 0);
    const initialResendRemaining = Math.max(0, parseInt(expiryBanner?.dataset.resendRemaining || '0', 10) || 0);

    function updateFullOtp() {
        let combined = '';
        digits.forEach(d => combined += d.value);
        fullOtp.value = combined;
    }

    function maybeAutoSubmitOtp() {
        if (!isOtpExpired && fullOtp.value.length === 6 && !otpVerificationPending) {
            otpForm.requestSubmit();
        }
    }

    function showMessage(msg, isSuccess = false) {
        if (!msgContainer) return;
        msgContainer.innerHTML = `<div class="${isSuccess ? 'success-box' : 'error'}">${Security.escapeHTML(msg)}</div>`;
    }

    function formatMmSs(totalSec) {
        const sec = Math.max(0, Math.floor(totalSec));
        const mins = Math.floor(sec / 60);
        const rem = sec % 60;
        return `${String(mins).padStart(2, '0')}:${String(rem).padStart(2, '0')}`;
    }

    function clearFieldValidationErrors() {
        digits.forEach((input) => {
            input.classList.remove('is-invalid', 'is-valid');
        });
        const inputsWrap = otpForm.querySelector('.otp-inputs');
        if (inputsWrap) {
            inputsWrap.querySelectorAll('.security-field-error').forEach((el) => {
                el.style.display = 'none';
            });
        }
    }

    function setExpiredUi(expired, showExpiredNotice = true) {
        isOtpExpired = expired;
        digits.forEach((input) => {
            input.disabled = expired;
            if (expired) {
                input.value = '';
            }
        });
        if (expired) {
            fullOtp.value = '';
            clearFieldValidationErrors();
        }
        if (verifyBtn) {
            verifyBtn.disabled = expired || otpVerificationPending;
        }
        if (expiryBanner) {
            expiryBanner.classList.toggle('is-expired', expired);
            if (expired) {
                expiryBanner.classList.remove('is-warning');
            }
        }
        if (expiryLabel) {
            expiryLabel.textContent = expired ? 'Code expired' : 'Code expires in';
        }
        if (expired && expiryTimer) {
            expiryTimer.textContent = '00:00';
        }
        if (expired && expiryProgress) {
            expiryProgress.style.width = '0%';
        }
        if (expired && showExpiredNotice) {
            showMessage('Verification code has expired. Please click "Resend Code" to receive a new one.');
        }
    }

    function updateOtpExpiryDisplay() {
        if (!expiryBanner || !expiryTimer) return false;
        const remaining = Math.max(0, Math.ceil((otpExpiresAtMs - Date.now()) / 1000));
        expiryTimer.textContent = formatMmSs(remaining);
        if (expiryProgress) {
            const pct = Math.min(100, Math.max(0, Math.round((remaining / otpTotalSeconds) * 100)));
            expiryProgress.style.width = `${pct}%`;
        }
        expiryBanner.classList.toggle('is-warning', remaining > 0 && remaining <= 60);

        if (remaining <= 0) {
            if (otpInterval) {
                clearInterval(otpInterval);
                otpInterval = null;
            }
            isOtpExpired = true;
            if (!otpVerificationPending) {
                setExpiredUi(true, true);
            } else {
                expiryBanner.classList.add('is-expired');
                expiryBanner.classList.remove('is-warning');
                if (expiryLabel) expiryLabel.textContent = 'Code expired';
            }
            return false;
        }
        return true;
    }

    function startOtpExpiryTimer(seconds, preserveMessage = false) {
        if (otpInterval) {
            clearInterval(otpInterval);
            otpInterval = null;
        }
        const safeSec = Math.max(0, Math.floor(seconds));
        if (safeSec <= 0) {
            otpExpiresAtMs = Date.now();
            setExpiredUi(true, !preserveMessage);
            return;
        }

        otpExpiresAtMs = Date.now() + safeSec * 1000;
        setExpiredUi(false, false);
        updateOtpExpiryDisplay();
        otpInterval = setInterval(updateOtpExpiryDisplay, 500);
    }

    // Auto tab across inputs and handle paste
    digits.forEach((input, index) => {
        input.addEventListener('input', (e) => {
            if (isOtpExpired) {
                e.target.value = '';
                return;
            }
            e.target.value = e.target.value.replace(/[^0-9]/g, '').slice(-1);
            const val = e.target.value;
            if (val.length === 1 && index < digits.length - 1) {
                digits[index + 1].focus();
            }
            updateFullOtp();
            maybeAutoSubmitOtp();
        });

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && !input.value && index > 0) {
                digits[index - 1].focus();
            }
        });

        input.addEventListener('paste', (e) => {
            e.preventDefault();
            if (isOtpExpired) return;
            const pasteData = e.clipboardData.getData('text').trim();
            if (/^[0-9]{6}$/.test(pasteData)) {
                pasteData.split('').forEach((char, idx) => {
                    if (digits[idx]) digits[idx].value = char;
                });
                updateFullOtp();
                digits[digits.length - 1].focus();
                maybeAutoSubmitOtp();
            }
        });
    });

    // Handle OTP verification submit
    otpForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (isOtpExpired) {
            showMessage('Verification code has expired. Please click "Resend Code" to receive a new one.');
            return;
        }

        updateFullOtp();

        if (fullOtp.value.length !== 6) {
            showMessage('Please enter all 6 digits.');
            return;
        }

        if (otpVerificationPending) return;
        otpVerificationPending = true;

        if (authLoader) authLoader.classList.add('active');
        if (verifyBtn) verifyBtn.disabled = true;

        try {
            const formData = new FormData(otpForm);
            const response = await fetch('verify_otp', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            const data = await response.json();

            if (data.success) {
                window.location.href = data.redirect;
            } else {
                otpVerificationPending = false;
                if (authLoader) authLoader.classList.remove('active');
                if (data.expired || isOtpExpired) {
                    if (otpInterval) {
                        clearInterval(otpInterval);
                        otpInterval = null;
                    }
                    setExpiredUi(true, false);
                    showMessage(data.message || 'Verification code expired or not found. Please click "Resend Code".');
                } else {
                    if (verifyBtn) verifyBtn.disabled = false;
                    showMessage(data.message || 'Verification failed.');
                    if (data.redirect) {
                        setTimeout(() => window.location.href = data.redirect, 2000);
                    }
                }
            }
        } catch (err) {
            otpVerificationPending = false;
            if (authLoader) authLoader.classList.remove('active');
            if (verifyBtn) verifyBtn.disabled = isOtpExpired;
            showMessage('Network error occurred. Please try again.');
        }
    });

    // Handle Resend
    function updateCooldownDisplay() {
        cooldown = Math.max(0, Math.ceil((resendReadyAtMs - Date.now()) / 1000));
        if (cooldown > 0) {
            if (resendBtn) resendBtn.disabled = true;
            if (countdownText) countdownText.style.display = 'inline';
            if (timerSpan) timerSpan.textContent = cooldown;
            return true;
        }

        if (resendInterval) {
            clearInterval(resendInterval);
            resendInterval = null;
        }
        if (resendBtn && !resendPending) resendBtn.disabled = false;
        if (countdownText) countdownText.style.display = 'none';
        return false;
    }

    function startCooldown(sec) {
        if (resendInterval) {
            clearInterval(resendInterval);
            resendInterval = null;
        }
        const safeSec = Math.max(0, Math.floor(sec));
        if (safeSec <= 0) {
            resendReadyAtMs = 0;
            updateCooldownDisplay();
            return;
        }

        resendReadyAtMs = Date.now() + safeSec * 1000;
        updateCooldownDisplay();
        resendInterval = setInterval(updateCooldownDisplay, 500);
    }

    if (resendBtn) {
        resendBtn.addEventListener('click', async () => {
            if (cooldown > 0 || resendPending) return;

            resendPending = true;
            resendBtn.disabled = true;

            const formData = new FormData();
            formData.append('action', 'resend');
            formData.append('ajax', '1');
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            formData.append('csrf_token', token);

            try {
                const response = await fetch('verify_otp', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await response.json();
                resendPending = false;
                if (data.success) {
                    digits.forEach((d) => { d.value = ''; });
                    fullOtp.value = '';
                    clearFieldValidationErrors();
                    startOtpExpiryTimer(Number(data.expires_in) || otpTotalSeconds);
                    showMessage(data.message, true);
                    startCooldown(Number(data.resend_cooldown) || resendTotalSeconds);
                    digits[0].focus();
                } else {
                    if (Number(data.resend_cooldown) > 0) {
                        startCooldown(Number(data.resend_cooldown));
                    } else {
                        resendBtn.disabled = false;
                    }
                    showMessage(data.message || 'Failed to request new code. Try again.');
                }
            } catch (e) {
                resendPending = false;
                if (cooldown <= 0) resendBtn.disabled = false;
                showMessage('Failed to request new code. Try again.');
            }
        });
    }

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            if (otpExpiresAtMs > 0 && !isOtpExpired) {
                updateOtpExpiryDisplay();
            }
            if (resendReadyAtMs > 0) {
                updateCooldownDisplay();
            }
        }
    });

    const hasInitialMessage = Boolean(msgContainer && msgContainer.textContent.trim() !== '');
    startOtpExpiryTimer(initialOtpRemaining, hasInitialMessage);
    if (initialResendRemaining > 0) {
        startCooldown(initialResendRemaining);
    }
})();
