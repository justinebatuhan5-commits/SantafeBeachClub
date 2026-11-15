(function () {
    const portalMain = document.querySelector('.portal-container');
    const portalEndpoint = portalMain?.dataset.endpoint || 'staff_login';
    const recaptchaAction = portalMain?.dataset.recaptchaAction || portalEndpoint;
    const recaptchaSiteKey = portalMain?.dataset.recaptchaSiteKey || '6LfE7bEtAAAAAKWR7cu0DZaBeVem3ZIuHOyJ7zWT';
    const dynamicGreeting = portalMain?.dataset.dynamicGreeting === '1';

    // ── Auto-Sliding Background ──
    const slides = document.querySelectorAll('.bg-slide');
    const dots = document.querySelectorAll('.slide-dot');
    if (slides.length > 0 && dots.length > 0) {
        let current = 0;
        const INTERVAL = 6000;

        function goToSlide(idx) {
            slides[current].classList.remove('active');
            dots[current].classList.remove('active');
            current = (idx + slides.length) % slides.length;
            slides[current].classList.add('active');
            dots[current].classList.add('active');
        }

        let timer = setInterval(() => goToSlide(current + 1), INTERVAL);

        dots.forEach(dot => {
            dot.addEventListener('click', () => {
                clearInterval(timer);
                goToSlide(parseInt(dot.dataset.slide, 10));
                timer = setInterval(() => goToSlide(current + 1), INTERVAL);
            });
        });
    }

    // ── Realtime Bantayan Clock & Dynamic Greeting ──
    function updateClock() {
        const now = new Date();
        const timeFormatter = new Intl.DateTimeFormat('en-US', {
            timeZone: 'Asia/Manila',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: true
        });
        const dateFormatter = new Intl.DateTimeFormat('en-US', {
            timeZone: 'Asia/Manila',
            weekday: 'short',
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        });

        const clockEl = document.getElementById('liveClock');
        const dateEl = document.getElementById('liveDate');
        if (clockEl) clockEl.textContent = timeFormatter.format(now);
        if (dateEl) dateEl.textContent = dateFormatter.format(now);

        const greetingEl = document.getElementById('greetingTitle');
        if (greetingEl) {
            if (dynamicGreeting) {
                const manilaHour = parseInt(new Intl.DateTimeFormat('en-US', { timeZone: 'Asia/Manila', hour: 'numeric', hour12: false }).format(now), 10);
                if (manilaHour >= 5 && manilaHour < 12) {
                    greetingEl.innerHTML = 'Good <span>Morning</span>';
                } else if (manilaHour >= 12 && manilaHour < 18) {
                    greetingEl.innerHTML = 'Good <span>Afternoon</span>';
                } else {
                    greetingEl.innerHTML = 'Good <span>Evening</span>';
                }
            } else {
                greetingEl.innerHTML = 'Welcome, <span>Administrator</span>';
            }
        }
    }
    setInterval(updateClock, 1000);
    updateClock();

    // ── Password Visibility Toggle ──
    const passwordInput = document.getElementById('password');
    const togglePwBtn = document.getElementById('togglePwBtn');
    const eyeIcon = document.getElementById('eyeIcon');

    if (togglePwBtn && passwordInput && eyeIcon) {
        togglePwBtn.addEventListener('click', function () {
            const isPassword = passwordInput.getAttribute('type') === 'password';
            passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
            if (isPassword) {
                eyeIcon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line>';
            } else {
                eyeIcon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>';
            }
        });
    }

    // ── Caps Lock Detector ──
    const capsWarning = document.getElementById('capsWarning');
    if (passwordInput && capsWarning) {
        passwordInput.addEventListener('keyup', function (e) {
            if (e.getModifierState && e.getModifierState('CapsLock')) {
                capsWarning.classList.add('active');
            } else {
                capsWarning.classList.remove('active');
            }
        });
    }

    // ── Dismiss handlers (timeout banner & lock overlay) ──
    document.querySelectorAll('[data-dismiss-parent]').forEach(btn => {
        btn.addEventListener('click', () => {
            btn.parentElement?.remove();
        });
    });

    document.querySelectorAll('[data-dismiss-overlay]').forEach(btn => {
        btn.addEventListener('click', () => {
            const target = document.getElementById(btn.dataset.dismissOverlay);
            if (target) target.style.display = 'none';
        });
    });

    // ── Ensure reCAPTCHA badge is initialized on load ──
    if (typeof grecaptcha !== 'undefined') {
        grecaptcha.ready(function () {
            grecaptcha.execute(recaptchaSiteKey, { action: 'homepage' }).catch(function () {});
        });
    }

    // ── AJAX Login Submission ──
    const loginForm = document.getElementById('loginForm');
    const authLoader = document.getElementById('authLoader');
    const errorContainer = document.getElementById('errorContainer');
    const submitBtn = document.getElementById('submitBtn');

    function showError(msg) {
        const safeMsg = (typeof Security !== 'undefined' && Security.escapeHTML)
            ? Security.escapeHTML(msg)
            : String(msg);
        errorContainer.innerHTML = `
            <div class="error">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                <span>${safeMsg}</span>
            </div>
        `;
    }

    if (loginForm) {
        loginForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            errorContainer.innerHTML = '';

            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;

            if (!username || !password) {
                showError('Please enter both username and password.');
                return;
            }

            let recaptchaToken = '';
            try {
                recaptchaToken = await Promise.race([
                    grecaptcha.execute(recaptchaSiteKey, { action: recaptchaAction }),
                    new Promise((_, reject) => setTimeout(() => reject(new Error('reCAPTCHA timeout')), 5000))
                ]);
                document.getElementById('g-recaptcha-response').value = recaptchaToken;
            } catch (err) {
                console.warn('reCAPTCHA failed to load, proceeding anyway.');
            }

            authLoader.classList.add('active');
            authLoader.setAttribute('aria-hidden', 'false');
            submitBtn.disabled = true;

            const formData = new FormData(loginForm);
            const startTime = Date.now();
            let redirectUrl = null;

            try {
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 10000);

                const response = await fetch(portalEndpoint, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    signal: controller.signal
                });
                clearTimeout(timeoutId);

                const data = await response.json();
                const elapsed = Date.now() - startTime;
                const minDisplayTime = 1000;
                const remainingTime = Math.max(0, minDisplayTime - elapsed);

                if (data && data.success) {
                    redirectUrl = data.redirect;
                    setTimeout(() => { window.location.href = redirectUrl; }, remainingTime);
                    return;
                } else {
                    await new Promise(resolve => setTimeout(resolve, remainingTime));
                    showError(data.message || 'Invalid username or password.');
                }
            } catch (err) {
                await new Promise(resolve => setTimeout(resolve, 400));
                if (err.name === 'AbortError') {
                    showError('Request timed out. Please check your connection and try again.');
                } else {
                    showError('An unexpected network error occurred. Please try again.');
                }
            } finally {
                if (!redirectUrl) {
                    authLoader.classList.remove('active');
                    authLoader.setAttribute('aria-hidden', 'true');
                    submitBtn.disabled = false;
                }
            }
        });
    }
})();
