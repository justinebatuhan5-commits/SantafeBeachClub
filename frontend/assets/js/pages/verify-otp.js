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
    let otpVerificationPending = false;

    if (!otpForm || !fullOtp || digits.length === 0) return;

    function updateFullOtp() {
        let combined = '';
        digits.forEach(d => combined += d.value);
        fullOtp.value = combined;
    }

    function maybeAutoSubmitOtp() {
        if (fullOtp.value.length === 6 && !otpVerificationPending) {
            otpForm.requestSubmit();
        }
    }

    function showMessage(msg, isSuccess = false) {
        if (!msgContainer) return;
        msgContainer.innerHTML = `<div class="${isSuccess ? 'success-box' : 'error'}">${Security.escapeHTML(msg)}</div>`;
    }

    // Auto tab across inputs and handle paste
    digits.forEach((input, index) => {
        input.addEventListener('input', (e) => {
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
                if (verifyBtn) verifyBtn.disabled = false;
                showMessage(data.message || 'Verification failed.');
                if (data.redirect) {
                    setTimeout(() => window.location.href = data.redirect, 2000);
                }
            }
        } catch (err) {
            otpVerificationPending = false;
            if (authLoader) authLoader.classList.remove('active');
            if (verifyBtn) verifyBtn.disabled = false;
            showMessage('Network error occurred. Please try again.');
        }
    });

    // Handle Resend
    let cooldown = 0;
    function startCooldown(sec) {
        cooldown = sec;
        if (resendBtn) resendBtn.disabled = true;
        if (countdownText) countdownText.style.display = 'inline';
        if (timerSpan) timerSpan.textContent = cooldown;

        const interval = setInterval(() => {
            cooldown--;
            if (timerSpan) timerSpan.textContent = cooldown;
            if (cooldown <= 0) {
                clearInterval(interval);
                if (resendBtn) resendBtn.disabled = false;
                if (countdownText) countdownText.style.display = 'none';
            }
        }, 1000);
    }

    if (resendBtn) {
        resendBtn.addEventListener('click', async () => {
            if (cooldown > 0) return;

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
                if (data.success) {
                    showMessage(data.message, true);
                    startCooldown(60);
                } else {
                    showMessage(data.message);
                }
            } catch (e) {
                showMessage('Failed to request new code. Try again.');
            }
        });
    }
})();
