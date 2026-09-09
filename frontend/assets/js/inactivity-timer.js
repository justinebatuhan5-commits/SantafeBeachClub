/**
 * inactivity-timer.js — Santa Fe Beach Club
 * Handles user inactivity monitoring, countdown warning modal, and automatic secure logout.
 * 
 * Configured for 15 minutes timeout with a 60-second warning countdown.
 * Automatically synchronizes across multiple tabs using localStorage.
 */

(function (window, document) {
    'use strict';

    const TIMEOUT_SECONDS = 15 * 60; // 15 minutes (900 seconds)
    const WARNING_SECONDS = 60;      // Warning popup appears when 60 seconds remain
    const STORAGE_KEY = 'sbc_last_active_timestamp';
    const LOGOUT_FLAG_KEY = 'sbc_force_logout_event';

    let warningModal = null;
    let countdownDisplay = null;
    let countdownProgress = null;
    let warningTimerId = null;
    let tickerIntervalId = null;
    let isWarningActive = false;

    // Determine user role and corresponding login destination
    function getRole() {
        const headerEl = document.querySelector('.page-header');
        if (headerEl && headerEl.getAttribute('data-role')) {
            return headerEl.getAttribute('data-role');
        }
        if (window.location.pathname.includes('admin_') || window.location.pathname.includes('/admin')) {
            return 'admin';
        }
        return 'receptionist';
    }

    function getLogoutUrl() {
        return 'logout';
    }

    function getRedirectUrl() {
        const role = getRole();
        return (role === 'admin' ? 'admin_login' : 'staff_login') + '?timeout=1';
    }

    // Update last activity timestamp locally and across tabs
    function recordActivity() {
        const now = Date.now();
        localStorage.setItem(STORAGE_KEY, now.toString());

        if (isWarningActive) {
            dismissWarning(false);
        }
    }

    function getLastActivity() {
        const stored = localStorage.getItem(STORAGE_KEY);
        if (!stored) {
            const now = Date.now();
            localStorage.setItem(STORAGE_KEY, now.toString());
            return now;
        }
        return parseInt(stored, 10);
    }

    // Build the luxury warning modal
    function injectWarningModal() {
        if (document.getElementById('sbcInactivityModal')) return;

        const modal = document.createElement('div');
        modal.id = 'sbcInactivityModal';
        modal.className = 'sbc-inactivity-modal-backdrop';
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('aria-labelledby', 'sbcInactivityTitle');

        modal.innerHTML = `
            <div class="sbc-inactivity-card">
                <div class="sbc-inactivity-icon-wrap">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                </div>
                
                <h3 id="sbcInactivityTitle" class="sbc-inactivity-title">Session Inactivity Warning</h3>
                
                <p class="sbc-inactivity-desc">
                    You have been inactive for a while. To protect guest data and system security, your session will automatically log out in:
                </p>

                <div class="sbc-inactivity-countdown-box">
                    <span id="sbcInactivityCountdown" class="sbc-inactivity-countdown">60</span>
                    <span class="sbc-inactivity-countdown-label">seconds</span>
                </div>

                <div class="sbc-inactivity-progress-bar">
                    <div id="sbcInactivityProgress" class="sbc-inactivity-progress-fill"></div>
                </div>

                <div class="sbc-inactivity-actions">
                    <button type="button" id="sbcStayLoggedInBtn" class="sbc-btn-stay">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 6L9 17l-5-5"></path>
                        </svg>
                        Stay Signed In
                    </button>
                    <button type="button" id="sbcLogoutNowBtn" class="sbc-btn-logout">
                        Log Out Now
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        // Inject modal CSS
        const style = document.createElement('style');
        style.id = 'sbc-inactivity-styles';
        style.textContent = `
            .sbc-inactivity-modal-backdrop {
                position: fixed;
                inset: 0;
                z-index: 999999;
                background: rgba(15, 23, 42, 0.72);
                backdrop-filter: blur(8px);
                -webkit-backdrop-filter: blur(8px);
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
                opacity: 0;
                visibility: hidden;
                transition: opacity 0.3s cubic-bezier(0.16, 1, 0.3, 1), visibility 0.3s ease;
            }
            .sbc-inactivity-modal-backdrop.active {
                opacity: 1;
                visibility: visible;
            }
            .sbc-inactivity-card {
                background: var(--bg-surface-elev, #ffffff);
                border: 1px solid var(--border, #E2E8F0);
                border-radius: 20px;
                box-shadow: 0 25px 60px -12px rgba(15, 23, 42, 0.35);
                max-width: 440px;
                width: 100%;
                padding: 32px 28px 26px;
                text-align: center;
                transform: scale(0.92) translateY(12px);
                transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
                position: relative;
                color: var(--text-main, #0F172A);
                font-family: 'Outfit', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            }
            [data-theme="dark"] .sbc-inactivity-card {
                background: #1A2234;
                border-color: #2A364F;
                color: #F8FAFC;
                box-shadow: 0 25px 60px -12px rgba(0, 0, 0, 0.6);
            }
            .sbc-inactivity-modal-backdrop.active .sbc-inactivity-card {
                transform: scale(1) translateY(0);
            }
            .sbc-inactivity-icon-wrap {
                width: 58px;
                height: 58px;
                border-radius: 50%;
                background: rgba(245, 158, 11, 0.12);
                border: 2px solid rgba(245, 158, 11, 0.3);
                color: #D97706;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 18px;
                animation: sbcPulseIcon 2s infinite ease-in-out;
            }
            [data-theme="dark"] .sbc-inactivity-icon-wrap {
                background: rgba(245, 158, 11, 0.2);
                color: #FBBF24;
            }
            @keyframes sbcPulseIcon {
                0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.3); }
                50% { transform: scale(1.05); box-shadow: 0 0 0 8px rgba(245, 158, 11, 0); }
            }
            .sbc-inactivity-title {
                font-size: 20px;
                font-weight: 700;
                margin: 0 0 10px;
                letter-spacing: -0.3px;
            }
            .sbc-inactivity-desc {
                font-size: 13.5px;
                color: var(--text-muted, #64748B);
                line-height: 1.55;
                margin: 0 0 20px;
            }
            [data-theme="dark"] .sbc-inactivity-desc {
                color: #94A3B8;
            }
            .sbc-inactivity-countdown-box {
                display: flex;
                align-items: baseline;
                justify-content: center;
                gap: 6px;
                margin-bottom: 16px;
            }
            .sbc-inactivity-countdown {
                font-size: 42px;
                font-weight: 800;
                font-variant-numeric: tabular-nums;
                color: #D97706;
                line-height: 1;
            }
            [data-theme="dark"] .sbc-inactivity-countdown {
                color: #FBBF24;
            }
            .sbc-inactivity-countdown-label {
                font-size: 14px;
                font-weight: 600;
                color: var(--text-muted, #64748B);
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            .sbc-inactivity-progress-bar {
                width: 100%;
                height: 6px;
                background: rgba(226, 232, 240, 0.8);
                border-radius: 999px;
                overflow: hidden;
                margin-bottom: 24px;
            }
            [data-theme="dark"] .sbc-inactivity-progress-bar {
                background: #2D3748;
            }
            .sbc-inactivity-progress-fill {
                height: 100%;
                width: 100%;
                background: linear-gradient(90deg, #F59E0B, #EF4444);
                border-radius: 999px;
                transition: width 1s linear;
            }
            .sbc-inactivity-actions {
                display: flex;
                flex-direction: column;
                gap: 10px;
            }
            .sbc-btn-stay {
                width: 100%;
                padding: 13px 20px;
                background: var(--primary, #84563C);
                color: #ffffff;
                border: none;
                border-radius: 12px;
                font-size: 14.5px;
                font-weight: 600;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                transition: all 0.2s ease;
                box-shadow: 0 4px 12px rgba(132, 86, 60, 0.25);
            }
            .sbc-btn-stay:hover {
                filter: brightness(1.08);
                transform: translateY(-1px);
                box-shadow: 0 6px 16px rgba(132, 86, 60, 0.35);
            }
            .sbc-btn-stay:active {
                transform: translateY(0);
            }
            .sbc-btn-logout {
                width: 100%;
                padding: 11px 20px;
                background: transparent;
                border: 1px solid var(--border, #CBD5E1);
                color: var(--text-muted, #64748B);
                border-radius: 12px;
                font-size: 13.5px;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.2s ease;
            }
            .sbc-btn-logout:hover {
                background: rgba(239, 68, 68, 0.08);
                border-color: rgba(239, 68, 68, 0.3);
                color: #EF4444;
            }
            [data-theme="dark"] .sbc-btn-logout {
                border-color: #334155;
                color: #94A3B8;
            }
        `;
        document.head.appendChild(style);

        warningModal = modal;
        countdownDisplay = document.getElementById('sbcInactivityCountdown');
        countdownProgress = document.getElementById('sbcInactivityProgress');

        // Button handlers
        document.getElementById('sbcStayLoggedInBtn').addEventListener('click', function () {
            keepSessionAlive();
        });

        document.getElementById('sbcLogoutNowBtn').addEventListener('click', function () {
            performLogout();
        });
    }

    // Sends keepalive request to backend and resets inactivity timer
    async function keepSessionAlive() {
        recordActivity();
        dismissWarning(true);

        try {
            await fetch('../backend/api/api_heartbeat.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
        } catch (e) {
            // Heartbeat best-effort
        }
    }

    function dismissWarning(notify) {
        if (!isWarningActive) return;
        isWarningActive = false;

        if (tickerIntervalId) {
            clearInterval(tickerIntervalId);
            tickerIntervalId = null;
        }

        if (warningModal) {
            warningModal.classList.remove('active');
        }
    }

    function showWarning(secondsRemaining) {
        if (!warningModal) injectWarningModal();

        isWarningActive = true;
        warningModal.classList.add('active');

        let remaining = Math.max(1, secondsRemaining);
        updateCountdownUI(remaining);

        if (tickerIntervalId) clearInterval(tickerIntervalId);

        tickerIntervalId = setInterval(function () {
            const elapsed = Math.floor((Date.now() - getLastActivity()) / 1000);
            const left = Math.max(0, TIMEOUT_SECONDS - elapsed);

            if (left <= 0) {
                clearInterval(tickerIntervalId);
                performLogout();
                return;
            }

            updateCountdownUI(left);
        }, 1000);
    }

    function updateCountdownUI(seconds) {
        if (countdownDisplay) {
            countdownDisplay.textContent = seconds;
        }
        if (countdownProgress) {
            const pct = Math.max(0, Math.min(100, (seconds / WARNING_SECONDS) * 100));
            countdownProgress.style.width = pct + '%';
        }
    }

    function performLogout() {
        localStorage.setItem(LOGOUT_FLAG_KEY, Date.now().toString());
        // Clean session and redirect to login page with timeout notice
        window.location.href = 'logout.php?timeout=1';
    }

    // Heartbeat check every 2 seconds
    function checkInactivity() {
        const elapsed = Math.floor((Date.now() - getLastActivity()) / 1000);
        const remaining = TIMEOUT_SECONDS - elapsed;

        if (remaining <= 0) {
            performLogout();
            return;
        }

        if (remaining <= WARNING_SECONDS) {
            if (!isWarningActive) {
                showWarning(remaining);
            }
        } else {
            if (isWarningActive) {
                dismissWarning(false);
            }
        }
    }

    // Attach listeners for user interactions
    function setupActivityListeners() {
        const events = ['mousedown', 'mousemove', 'keydown', 'scroll', 'touchstart', 'click'];
        let throttleTimer = null;

        events.forEach(function (evtName) {
            window.addEventListener(evtName, function () {
                // Throttle activity updates to once every 5 seconds to reduce localStorage writes
                if (!throttleTimer) {
                    recordActivity();
                    throttleTimer = setTimeout(function () {
                        throttleTimer = null;
                    }, 5000);
                }
            }, { passive: true });
        });

        // Listen for storage events to synchronize across open tabs
        window.addEventListener('storage', function (e) {
            if (e.key === STORAGE_KEY) {
                if (isWarningActive) {
                    dismissWarning(false);
                }
            } else if (e.key === LOGOUT_FLAG_KEY) {
                // Logged out from another tab
                window.location.href = getRedirectUrl();
            }
        });
    }

    // Initialization
    function init() {
        // Record initial interaction
        recordActivity();
        injectWarningModal();
        setupActivityListeners();

        // Run routine check every 1000ms
        setInterval(checkInactivity, 1000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})(window, document);
