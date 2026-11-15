/**
 * sidebar.js — Shared layout normalization, search filter, confirm dialog,
 * live SSE notification badge/toast alerts, and desktop QR check-in scanner.
 */
(function() {
    var cfg = window.SFBC_SIDEBAR_CONFIG || {};

    if (cfg.isAdmin) {
        // Switch body wrapper class so admin.css layout applies consistently on shared pages
        document.addEventListener('DOMContentLoaded', function() {
            var mc = document.querySelector('.main-content');
            if (mc && !mc.classList.contains('admin-main')) {
                mc.classList.remove('main-content');
                mc.classList.add('admin-main');

                if (!mc.querySelector('.admin-body')) {
                    var wrapper = document.createElement('div');
                    wrapper.className = 'admin-body';
                    // Keep the sticky header outside the padded content container.
                    Array.from(mc.childNodes).forEach(function(node) {
                        if (node.nodeType === 1 && node.matches('.page-header')) return;
                        wrapper.appendChild(node);
                    });
                    mc.appendChild(wrapper);
                }
            }
        });
    }

    // Restore saved scroll position after confirmation submits
    try {
        var raw = sessionStorage.getItem('sbc_scroll_restore');
        if (raw) {
            sessionStorage.removeItem('sbc_scroll_restore');
            var data = JSON.parse(raw);
            if (data && data.path === window.location.pathname) {
                window.addEventListener('DOMContentLoaded', function() {
                    setTimeout(function() {
                        window.scrollTo(data.x || 0, data.y || 0);
                    }, 60);
                });
            }
        }
    } catch (e) {}
})();

// Sidebar search filter: filters nav links in real-time
(function() {
    function initSidebarSearch(inputId) {
        var input = document.getElementById(inputId);
        if (!input) return;
        var sidebar = input.closest('.admin-sidebar, .sidebar');
        if (!sidebar) return;

        input.addEventListener('input', function() {
            if (sidebar.classList.contains('workspace-sidebar')) return;
            var query = this.value.trim().toLowerCase();
            var links = sidebar.querySelectorAll('.sidebar-link, .nav-item');
            var sections = sidebar.querySelectorAll('.sidebar-section-label');

            links.forEach(function(link) {
                var text = link.textContent.trim().toLowerCase();
                var li = link.closest('li');
                if (!li) return;
                li.style.display = (!query || text.includes(query)) ? '' : 'none';
            });

            // Hide section labels if all their items are hidden
            sections.forEach(function(label) {
                var ul = label.nextElementSibling;
                if (!ul) return;
                var visibleItems = ul.querySelectorAll('li:not([style*="display: none"])');
                label.style.display = visibleItems.length === 0 ? 'none' : '';
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        initSidebarSearch('sidebarSearchInput');
        initSidebarSearch('sidebarSearchInputReception');
    });
})();

/**
 * showConfirm(options) — Custom styled confirmation dialog.
 * Replaces native browser confirm().
 */
function showConfirm(opts) {
    var savedScrollX = window.scrollX || window.pageXOffset || 0;
    var savedScrollY = window.scrollY || window.pageYOffset || 0;
    var overlay   = document.getElementById('sfbc-confirm-overlay');
    // Ensure overlay is attached to document.body to avoid clipping by transformed ancestors
    if (overlay && overlay.parentNode !== document.body) { document.body.appendChild(overlay); }
    var icon      = document.getElementById('sfbc-confirm-icon');
    var title     = document.getElementById('sfbc-confirm-title');
    var message   = document.getElementById('sfbc-confirm-message');
    var okBtn     = document.getElementById('sfbc-confirm-ok');
    var cancelBtn = document.getElementById('sfbc-confirm-cancel');

    var defaultWarningSvg = '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#D97706" stroke-width="2.2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';
    var iconVal = opts.icon || defaultWarningSvg;
    if (iconVal === 'warning') iconVal = defaultWarningSvg;
    if (iconVal === 'user') {
        iconVal = '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#DC2626" stroke-width="2.2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>';
    } else if (iconVal === 'lock') {
        iconVal = '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#DC2626" stroke-width="2.2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>';
    } else if (iconVal === 'trash') {
        iconVal = '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#DC2626" stroke-width="2.2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>';
    } else if (iconVal === 'image') {
        iconVal = '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#D97706" stroke-width="2.2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>';
    }

    if (iconVal.indexOf('<svg') !== -1) {
        icon.innerHTML = iconVal;
    } else {
        icon.textContent = iconVal;
    }
    icon.style.background  = opts.iconBg   || '#FEF3C7';
    title.textContent      = opts.title    || 'Are you sure?';
    message.textContent    = opts.message  || 'This action cannot be undone.';
    okBtn.textContent      = opts.confirmText  || 'Confirm';
    okBtn.style.background = opts.confirmColor || '#DC2626';
    okBtn.onmouseout  = function() { this.style.background = opts.confirmColor || '#DC2626'; };
    okBtn.onmouseover = function() { this.style.background = darkenHex(opts.confirmColor || '#DC2626'); };

    overlay.style.display = 'flex';

    // Clone buttons to remove stale listeners
    var newOk = okBtn.cloneNode(true);
    okBtn.parentNode.replaceChild(newOk, okBtn);
    newOk.textContent      = opts.confirmText  || 'Confirm';
    newOk.style.background = opts.confirmColor || '#DC2626';
    newOk.onmouseover = function() { this.style.background = darkenHex(opts.confirmColor || '#DC2626'); };
    newOk.onmouseout  = function() { this.style.background = opts.confirmColor || '#DC2626'; };
    newOk.addEventListener('click', function() {
        overlay.style.display = 'none';
        try { sessionStorage.setItem('sbc_scroll_restore', JSON.stringify({ path: window.location.pathname, x: savedScrollX, y: savedScrollY })); } catch (e) {}
        if (typeof opts.onConfirm === 'function') opts.onConfirm();
    });

    var newCancel = cancelBtn.cloneNode(true);
    cancelBtn.parentNode.replaceChild(newCancel, cancelBtn);
    newCancel.addEventListener('click', function(e) {
        if (e && e.preventDefault) e.preventDefault();
        overlay.style.display = 'none';
        window.scrollTo(savedScrollX, savedScrollY);
    });

    overlay.onclick = function(e) {
        if (e.target === overlay) {
            overlay.style.display = 'none';
            window.scrollTo(savedScrollX, savedScrollY);
        }
    };
}

function darkenHex(hex) {
    // Simple darken: reduce each channel by ~20%
    hex = hex.replace('#', '');
    if (hex.length === 3) hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
    var r = Math.max(0, parseInt(hex.substr(0, 2), 16) - 30);
    var g = Math.max(0, parseInt(hex.substr(2, 2), 16) - 30);
    var b = Math.max(0, parseInt(hex.substr(4, 2), 16) - 30);
    return '#' + [r, g, b].map(function(x) { return x.toString(16).padStart(2, '0'); }).join('');
}

// Global handler: intercept submit buttons inside forms with data-confirm-* attributes
document.addEventListener('DOMContentLoaded', function() {
    document.body.addEventListener('click', function(e) {
        var btn = e.target.closest('button[type="submit"]');
        if (!btn) return;
        var form = btn.closest('form[data-confirm-title], form[data-confirm-msg]');
        if (!form) return;
        e.preventDefault();
        showConfirm({
            title:        form.dataset.confirmTitle   || 'Are you sure?',
            message:      form.dataset.confirmMsg     || 'This action cannot be undone.',
            icon:         form.dataset.confirmIcon    || '',
            iconBg:       form.dataset.confirmIconBg  || '#FEF3C7',
            confirmText:  form.dataset.confirmText    || 'Confirm',
            confirmColor: form.dataset.confirmColor   || '#DC2626',
            onConfirm: function() { form.submit(); }
        });
    });
});

// Live-refresh notification badge for both admin and reception sidebars.
(function() {
    var cfg = window.SFBC_SIDEBAR_CONFIG || {};
    var POLL_MS = 45000;
    var API_URL = '../backend/api/notifications_api.php?action=get_recent';
    var lastUnreadCount = null;
    var audioCtx = null;
    var toastTimer = null;
    var soundMuted = false;
    var desktopNotificationsEnabled = false;
    var seenNotificationIds = {};

    try {
        seenNotificationIds = JSON.parse(sessionStorage.getItem('sbc_seen_notification_ids') || '{}');
    } catch (e) {
        seenNotificationIds = {};
    }

    try {
        soundMuted = localStorage.getItem('sbc_notif_sound_muted') === '1';
    } catch (e) {
        soundMuted = false;
    }

    // Read user preference for desktop notifications (set in Settings -> Notifications)
    var desktopNotifPref = false;
    try {
        desktopNotifPref = localStorage.getItem('sbc_notif_desktop_enabled') === '1';
    } catch (e) {
        desktopNotifPref = false;
    }

    function ensureToastStyles() {
        if (document.getElementById('sbc-notif-toast-styles')) return;
        var style = document.createElement('style');
        style.id = 'sbc-notif-toast-styles';
        style.textContent = [
            '#sbc-notif-toast {',
            'position: fixed;',
            'right: 22px;',
            'bottom: 22px;',
            'z-index: 999997;',
            'max-width: min(340px, calc(100vw - 24px));',
            'display: flex;',
            'gap: 10px;',
            'align-items: flex-start;',
            'padding: 12px 14px;',
            'border-radius: 14px;',
            'background: rgba(12, 18, 29, 0.96);',
            'color: #F8FAFC;',
            'border: 1px solid rgba(148, 163, 184, 0.28);',
            'box-shadow: 0 14px 34px rgba(2, 6, 23, 0.36);',
            'backdrop-filter: blur(6px);',
            'opacity: 0;',
            'transform: translateY(10px);',
            'pointer-events: none;',
            'transition: opacity 0.22s ease, transform 0.22s ease;',
            'font-family: "Outfit", "Segoe UI", sans-serif;',
            'cursor: pointer;',
            '}',
            '#sbc-notif-toast.show {',
            'opacity: 1;',
            'transform: translateY(0);',
            'pointer-events: auto;',
            '}',
            '.sbc-notif-toast-icon { font-size: 18px; line-height: 1.2; }',
            '.sbc-notif-toast-title { font-weight: 700; font-size: 13px; margin-bottom: 2px; }',
            '.sbc-notif-toast-msg { font-size: 12px; color: rgba(226, 232, 240, 0.95); line-height: 1.35; }',
            '.sbc-notif-toast-link { margin-top: 7px; color: #93C5FD; font-size: 11px; font-weight: 700; }',
            '.sbc-notif-toast-actions { margin-left: auto; display:flex; align-items:center; }',
            '.sbc-notif-toast-mute {',
            'border: 1px solid rgba(148, 163, 184, 0.35);',
            'background: transparent;',
            'color: #E2E8F0;',
            'border-radius: 8px;',
            'font-size: 11px;',
            'font-weight: 600;',
            'padding: 4px 8px;',
            'cursor: pointer;',
            '}',
            '.sbc-notif-toast-mute:hover { background: rgba(148, 163, 184, 0.16); }',
            '.sbc-notif-toast-close { border: none; background: transparent; color: #94A3B8; font-size: 16px; line-height: 1; padding: 3px 6px; cursor: pointer; border-radius: 6px; margin-left: 6px; }',
            '.sbc-notif-toast-close:hover { color: #FFFFFF; background: rgba(148, 163, 184, 0.2); }',
            '@media (max-width: 768px) { #sbc-notif-toast { left: 12px; right: 12px; bottom: 14px; max-width: none; } }'
        ].join('');
        document.head.appendChild(style);
    }

    function ensureToastElement() {
        ensureToastStyles();
        var toast = document.getElementById('sbc-notif-toast');
        if (toast) return toast;

        toast = document.createElement('div');
        toast.id = 'sbc-notif-toast';
        toast.innerHTML = '' +
            '<div class="sbc-notif-toast-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg></div>' +
            '<div>' +
                '<div class="sbc-notif-toast-title">New notification</div>' +
                '<div class="sbc-notif-toast-msg">You have 1 new alert.</div>' +
                '<div class="sbc-notif-toast-link">Open notification</div>' +
            '</div>' +
            '<div class="sbc-notif-toast-actions">' +
                '<button type="button" class="sbc-notif-toast-mute" id="sbc-notif-toast-mute">' +
                (soundMuted ? 'Unmute' : 'Mute') +
                '</button>' +
                '<button type="button" class="sbc-notif-toast-close" id="sbc-notif-toast-close" title="Dismiss">&times;</button>' +
            '</div>';

        document.body.appendChild(toast);

        var muteBtn = document.getElementById('sbc-notif-toast-mute');
        if (muteBtn) {
            muteBtn.addEventListener('click', function() {
                soundMuted = !soundMuted;
                muteBtn.textContent = soundMuted ? 'Unmute' : 'Mute';
                try {
                    localStorage.setItem('sbc_notif_sound_muted', soundMuted ? '1' : '0');
                } catch (e) {}
            });
        }

        var closeBtn = document.getElementById('sbc-notif-toast-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                if (toastTimer) { clearTimeout(toastTimer); toastTimer = null; }
                toast.classList.remove('show');
            });
        }

        return toast;
    }

    function showNotificationToast(increaseBy, totalUnread, notification) {
        var toast = ensureToastElement();
        if (!toast) return;

        var title = toast.querySelector('.sbc-notif-toast-title');
        var msg = toast.querySelector('.sbc-notif-toast-msg');
        if (title) {
            title.textContent = notification && notification.title
                ? notification.title
                : (increaseBy > 1 ? 'New notifications' : 'New notification');
        }
        if (msg) {
            if (notification && notification.message) {
                msg.textContent = notification.message + (increaseBy > 1 ? ' +' + (increaseBy - 1) + ' more.' : '');
            } else {
                var addText = increaseBy > 1 ? increaseBy + ' new alerts arrived.' : '1 new alert arrived.';
                msg.textContent = addText + ' Unread total: ' + totalUnread + '.';
            }
        }

        toast.onclick = function(e) {
            if (e.target.closest('.sbc-notif-toast-mute') || e.target.closest('.sbc-notif-toast-close')) return;
            openNotificationDestination(notification);
        };

        if (toastTimer) {
            clearTimeout(toastTimer);
            toastTimer = null;
        }

        toast.classList.add('show');
        toastTimer = setTimeout(function() {
            toast.classList.remove('show');
        }, 4200);
    }

    function playNotificationSound() {
        if (soundMuted) return;
        try {
            var AC = window.AudioContext || window.webkitAudioContext;
            if (!AC) return;
            if (!audioCtx) {
                audioCtx = new AC();
            }
            if (audioCtx.state === 'suspended') {
                audioCtx.resume();
            }

            var now = audioCtx.currentTime;
            var osc = audioCtx.createOscillator();
            var gain = audioCtx.createGain();

            osc.type = 'sine';
            osc.frequency.setValueAtTime(880, now);
            osc.frequency.exponentialRampToValueAtTime(660, now + 0.18);

            gain.gain.setValueAtTime(0.0001, now);
            gain.gain.exponentialRampToValueAtTime(0.05, now + 0.01);
            gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.22);

            osc.connect(gain);
            gain.connect(audioCtx.destination);

            osc.start(now);
            osc.stop(now + 0.24);
        } catch (e) {
            // Silent fail on unsupported audio environments.
        }
    }

    function requestDesktopNotificationPermission() {
        if (!('Notification' in window)) return;
        if (Notification.permission === 'default') {
            Notification.requestPermission().then(function(permission) {
                desktopNotificationsEnabled = permission === 'granted';
            }).catch(function() {});
        } else {
            desktopNotificationsEnabled = Notification.permission === 'granted';
        }
    }

    function showDesktopNotification(notification) {
        if (!desktopNotificationsEnabled || !desktopNotifPref || !notification) return;

        var desktopNotification = new Notification(notification.title || 'Santa Fe Beach Club', {
            body: notification.message || 'You have a new notification.',
            tag: 'sbc-notification-' + (notification.id || 'latest')
        });

        desktopNotification.onclick = function() {
            window.focus();
            openNotificationDestination(notification);
        };
    }

    function openNotificationDestination(notification) {
        var title = String((notification && notification.title) || '').toLowerCase();
        var message = String((notification && notification.message) || '').toLowerCase();
        var bookingId = Number(notification && notification.booking_id) || 0;
        var isAdmin = !!cfg.isAdmin;
        var reservationsPage = isAdmin ? 'admin_reservations' : 'reservations';
        var checkoutPage = isAdmin ? 'admin_checkout' : 'checkout';

        if (bookingId > 0) {
            window.location.href = reservationsPage + '?search=' + encodeURIComponent(bookingId);
        } else if (title.indexOf('check-out') !== -1 || title.indexOf('checkout') !== -1 || message.indexOf('check-out') !== -1) {
            window.location.href = checkoutPage;
        } else if (title.indexOf('payment') !== -1 || message.indexOf('payment') !== -1) {
            window.location.href = 'payments';
        } else if (title.indexOf('live chat') !== -1 || title.indexOf('escalation') !== -1 || title.indexOf('human support') !== -1 || title.indexOf('concierge') !== -1 || message.indexOf('live support') !== -1) {
            window.location.href = 'admin_live_support';
        } else if (title.indexOf('inquir') !== -1 || message.indexOf('inquir') !== -1) {
            window.location.href = 'admin_inquiries';
        } else if (title.indexOf('room') !== -1 || title.indexOf('maintenance') !== -1 || message.indexOf('room') !== -1) {
            window.location.href = isAdmin ? 'accommodations' : 'admin_calendar';
        } else {
            window.location.href = isAdmin ? 'admin_notifications' : 'notifications';
        }
    }

    function rememberNotification(notification) {
        if (!notification || !notification.id) return;
        seenNotificationIds[String(notification.id)] = true;
        try {
            sessionStorage.setItem('sbc_seen_notification_ids', JSON.stringify(seenNotificationIds));
        } catch (e) {}
    }

    function formatBadgeCount(count) {
        return count > 99 ? '99+' : String(count);
    }

    function updateNotificationBadge(count) {
        var links = document.querySelectorAll('a[href="admin_notifications"], a[href="notifications"]');
        if (links.length) {
            links.forEach(function(link) {
                var existing = link.querySelector('.sidebar-badge[data-badge-type="notifications"]');

                if (count > 0) {
                    if (!existing) {
                        existing = document.createElement('span');
                        existing.className = 'sidebar-badge';
                        existing.setAttribute('data-badge-type', 'notifications');
                        link.appendChild(existing);
                    }
                    existing.textContent = formatBadgeCount(count);
                } else if (existing) {
                    existing.remove();
                }
            });
        }

        // Also sync top page header bell notification badge
        var headerBadge = document.getElementById('headerNotifBadge');
        if (headerBadge) {
            if (count > 0) {
                headerBadge.style.display = 'inline-flex';
                headerBadge.textContent = formatBadgeCount(count);
            } else {
                headerBadge.style.display = 'none';
                headerBadge.textContent = '0';
            }
        }
        var headerCount = document.getElementById('headerNotifCount');
        if (headerCount) {
            headerCount.textContent = count + ' unread';
        }
    }

    function refreshNotificationCount() {
        fetch(API_URL, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(res) { return res.ok ? res.json() : null; })
        .then(function(data) {
            if (!data || data.success !== true || typeof data.unread_count === 'undefined') return;
            var nextCount = Number(data.unread_count) || 0;
            var unreadNotifications = (data.notifications || []).filter(function(item) {
                return Number(item.is_read) === 0;
            });
            var newNotifications = unreadNotifications.filter(function(item) {
                return !seenNotificationIds[String(item.id)];
            });

            if (newNotifications.length > 0 && (lastUnreadCount === null || nextCount > lastUnreadCount)) {
                var newestNotification = newNotifications[0];
                showNotificationToast(newNotifications.length, nextCount, newestNotification);
                if (lastUnreadCount !== null) playNotificationSound();
                showDesktopNotification(newestNotification);
                newNotifications.forEach(rememberNotification);
            }
            lastUnreadCount = nextCount;
            updateNotificationBadge(nextCount);
        })
        .catch(function() {
            // Silent fail: avoid UI interruption if endpoint is temporarily unavailable.
        });
    }

    // Real-Time Server-Sent Events (SSE) Push Listener
    function initRealtimeNotificationStream() {
        if (!window.EventSource) {
            setInterval(refreshNotificationCount, POLL_MS);
            return;
        }

        try {
            var streamUrl = '../backend/api/notifications_api.php?action=stream';
            var sse = new EventSource(streamUrl, { withCredentials: true });

            sse.addEventListener('notification', function(e) {
                try {
                    var notif = JSON.parse(e.data);
                    if (!seenNotificationIds[String(notif.id)]) {
                        rememberNotification(notif);
                        showNotificationToast(1, (lastUnreadCount || 0) + 1, notif);
                        playNotificationSound();
                        showDesktopNotification(notif);
                    }
                } catch (err) {}
            });

            sse.addEventListener('badge_sync', function(e) {
                try {
                    var data = JSON.parse(e.data);
                    var count = Number(data.unread_count) || 0;
                    lastUnreadCount = count;
                    updateNotificationBadge(count);
                } catch (err) {}
            });

            sse.onerror = function() {
                sse.close();
                // Reconnect after 6 seconds
                setTimeout(initRealtimeNotificationStream, 6000);
            };
        } catch (e) {
            setInterval(refreshNotificationCount, POLL_MS);
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        if ('Notification' in window) {
            desktopNotificationsEnabled = Notification.permission === 'granted';
            var notificationButtons = document.querySelectorAll('#headerNotifBtn, a[href="admin_notifications"], a[href="notifications"]');
            notificationButtons.forEach(function(button) {
                button.addEventListener('click', requestDesktopNotificationPermission);
            });
        }
        refreshNotificationCount();
        initRealtimeNotificationStream();
    });
})();

// In-Browser QR Scanner Modal (Native getUserMedia + jsQR)
(function() {
    const CHECKIN_URL = 'admin_checkin';

    let stream       = null;
    let rafId        = null;
    let scannerOpen  = false;
    let done         = false;
    let camerasList  = [];
    let savedScrollX = 0;
    let savedScrollY = 0;

    const video     = document.getElementById('qr-video');
    const canvas    = document.getElementById('qr-canvas');
    const ctx       = canvas ? canvas.getContext('2d', { willReadFrequently: true }) : null;
    const statusEl  = document.getElementById('qr-status-text');
    const flashEl   = document.getElementById('qr-success-flash');
    const overlayEl = document.getElementById('qr-scanner-overlay');
    const camSelect = document.getElementById('qr-cam-select');

    /* ── PUBLIC: toggle open/close ── */
    window.launchDesktopScanner = function() {
        scannerOpen ? closeQrScanner() : openQrScanner();
    };

    window.openQrScanner = function() {
        if (scannerOpen || !overlayEl) return;
        scannerOpen = true;
        done = false;
        overlayEl.classList.add('open');
        if (flashEl) flashEl.classList.remove('show');
        if (statusEl) statusEl.textContent = 'Starting camera\u2026';
        if (camSelect) camSelect.style.display = 'none';

        if (typeof window.jsQR !== 'function') {
            if (statusEl) statusEl.textContent = 'QR scanner library unavailable. Check the internet connection and reload.';
            return;
        }

        if (!navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== 'function') {
            if (statusEl) statusEl.textContent = 'Camera access is unavailable. Use HTTPS or localhost and allow camera permission.';
            return;
        }

        // Populate camera list first if we have permissions
        if (navigator.mediaDevices && navigator.mediaDevices.enumerateDevices) {
            navigator.mediaDevices.enumerateDevices().then(function(devices) {
                camerasList = devices.filter(function(d) { return d.kind === 'videoinput' && d.deviceId; });
                if (camerasList.length > 0 && camSelect) {
                    camSelect.innerHTML = '';
                    camerasList.forEach(function(cam, i) {
                        var opt = document.createElement('option');
                        opt.value = cam.deviceId;
                        opt.text = cam.label || ('Camera ' + (i + 1));
                        camSelect.appendChild(opt);
                    });
                    camSelect.style.display = 'block';
                }
                startCamera();
            }).catch(function() { startCamera(); });
        } else {
            startCamera();
        }
    };

    window.closeQrScanner = function() {
        scannerOpen = false;
        if (overlayEl) overlayEl.classList.remove('open');
        stopCamera();
        window.scrollTo(savedScrollX, savedScrollY);
    };

    if (camSelect) {
        camSelect.addEventListener('change', function() {
            if (this.value) {
                this.dataset.userSelected = '1';
                stopCamera();
                if (statusEl) statusEl.textContent = 'Switching camera...';
                tryConstraints([{ video: { deviceId: { exact: this.value } } }], 0);
            }
        });
    }

    /* ── Camera ── */
    function startCamera() {
        // If we have a populated dropdown, use the selected one
        if (camSelect && camSelect.value && camSelect.dataset.userSelected === '1') {
            tryConstraints([{ video: { deviceId: { exact: camSelect.value } } }], 0);
            return;
        }

        // Fallback generic constraints
        const constraints = [
            { video: { facingMode: { ideal: 'environment' } } },
            { video: true },
            { video: { facingMode: 'user' } }
        ];

        tryConstraints(constraints, 0);
    }

    function tryConstraints(list, idx) {
        if (idx >= list.length) {
            if (!statusEl) return;
            // All constraints failed — show a retry button
            statusEl.innerHTML = 'Camera unavailable. &nbsp;<button id="qr-retry-btn" style="display:inline-flex;align-items:center;gap:6px;margin-left:6px;padding:5px 14px;border:none;border-radius:20px;' +
                'background:linear-gradient(135deg,#C8996F,#B07D52);color:#fff;font-weight:700;font-size:12px;cursor:pointer;' +
                'font-family:Outfit,sans-serif;box-shadow:0 2px 8px rgba(200,153,111,0.4);">\u21bb Retry</button>';
            var retryBtn = document.getElementById('qr-retry-btn');
            if (retryBtn) {
                retryBtn.addEventListener('click', function() {
                    statusEl.textContent = '\u21bb Retrying camera\u2026';
                    startCamera();
                });
            }
            return;
        }
        navigator.mediaDevices.getUserMedia(list[idx])
            .then(function(s) {
                if (!scannerOpen) {
                    s.getTracks().forEach(function(t) { t.stop(); });
                    return;
                }
                stream = s;
                video.srcObject = s;
                const videoTrack = s.getVideoTracks()[0];
                const facingMode = videoTrack && typeof videoTrack.getSettings === 'function'
                    ? videoTrack.getSettings().facingMode
                    : '';
                const activeDeviceId = videoTrack && typeof videoTrack.getSettings === 'function'
                    ? videoTrack.getSettings().deviceId
                    : '';
                if (activeDeviceId && camSelect && Array.from(camSelect.options).some(function(opt) { return opt.value === activeDeviceId; })) {
                    camSelect.value = activeDeviceId;
                }
                video.style.transform = facingMode === 'user' ? 'scaleX(-1)' : 'none';
                video.onloadedmetadata = function() {
                    if (!scannerOpen) return;
                    video.play().then(function() {
                        if (!scannerOpen) return;
                        if (statusEl) statusEl.textContent = 'Camera live \u2014 align QR code to frame';
                        scanLoop();
                    }).catch(function() {
                        if (statusEl) statusEl.textContent = 'Camera preview could not start. Close and retry.';
                    });
                };
            })
            .catch(function(err) {
                console.warn('Camera attempt', idx, 'failed:', err.name, err.message);
                tryConstraints(list, idx + 1);
            });
    }

    function stopCamera() {
        if (rafId) { cancelAnimationFrame(rafId); rafId = null; }
        if (stream) { stream.getTracks().forEach(function(t) { t.stop(); }); stream = null; }
        if (video) video.srcObject = null;
    }

    /* ── Decode loop ── */
    function scanLoop() {
        if (!scannerOpen || done || !video || !canvas || !ctx) return;

        if (video.readyState === video.HAVE_ENOUGH_DATA) {
            canvas.width  = video.videoWidth;
            canvas.height = video.videoHeight;
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

            const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
            const code = jsQR(imageData.data, imageData.width, imageData.height, {
                inversionAttempts: 'dontInvert'
            });

            if (code) {
                onQrFound(code.data);
                return;
            }
        }

        rafId = requestAnimationFrame(scanLoop);
    }

    /* ── On successful scan ── */
    function onQrFound(text) {
        done = true;
        if (flashEl) flashEl.classList.add('show');
        if (statusEl) statusEl.textContent = 'QR detected! Redirecting\u2026';
        stopCamera();

        setTimeout(function() {
            try {
                let ref = null, token = null;
                const scannedText = String(text || '').trim();

                // Accept a booking URL (absolute or relative) or the legacy REF|token format.
                // Never navigate directly to the scanned value.
                try {
                    const u = new URL(scannedText, window.location.href);
                    ref   = u.searchParams.get('ref');
                    token = u.searchParams.get('token');
                } catch (_) {}

                if (!ref || !token) {
                    const parts = scannedText.split('|');
                    if (parts.length === 2) { ref = parts[0]; token = parts[1]; }
                }

                ref = String(ref || '').trim().toUpperCase();
                token = String(token || '').trim().toLowerCase();

                if (/^REF-\d+$/.test(ref) && /^[a-f0-9]{32}$/.test(token)) {
                    window.location.assign(CHECKIN_URL + '?ref=' + encodeURIComponent(ref) +
                                             '&token=' + encodeURIComponent(token));
                } else {
                    if (statusEl) statusEl.textContent = 'Invalid QR \u2014 not a booking code.';
                    if (flashEl) flashEl.classList.remove('show');
                    done = false;
                    startCamera();
                }
            } catch (e) {
                if (statusEl) statusEl.textContent = 'Could not read QR data.';
                if (flashEl) flashEl.classList.remove('show');
                done = false;
                startCamera();
            }
        }, 700);
    }

    /* ── Keyboard & backdrop close ── */
    if (overlayEl) {
        overlayEl.addEventListener('click', function(e) {
            if (e.target === overlayEl) closeQrScanner();
        });
    }
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && scannerOpen) closeQrScanner();
        // Q key to toggle scanner (but not when typing in inputs)
        if (e.key === 'q' || e.key === 'Q') {
            const tag = document.activeElement && document.activeElement.tagName;
            if (tag !== 'INPUT' && tag !== 'TEXTAREA' && tag !== 'SELECT') {
                e.preventDefault();
                launchDesktopScanner();
            }
        }
    });
})();
