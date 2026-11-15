/**
 * page-header.js — Header notification dropdown, detail modal routing,
 * live resort clock (Asia/Manila), and sidebar toggle icon synchronization.
 */
function toggleNotifDropdown(e) {
    e.stopPropagation();
    var notifMenu = document.getElementById('headerNotifMenu');
    var userMenu = document.getElementById('pageHeaderUserMenu');
    if (userMenu) userMenu.classList.remove('open');
    if (notifMenu) notifMenu.classList.toggle('open');
}

function toggleUserDropdown(e) {
    e.stopPropagation();
    var notifMenu = document.getElementById('headerNotifMenu');
    var userMenu = document.getElementById('pageHeaderUserMenu');
    if (notifMenu) notifMenu.classList.remove('open');
    if (userMenu) userMenu.classList.toggle('open');
}

function resolveNotificationAction(data) {
    var cfg = window.SFBC_HEADER_CONFIG || {};
    var title = String(data.title || '').toLowerCase();
    var message = String(data.message || '').toLowerCase();
    var type = String(data.type || '').toLowerCase();
    var context = title + ' ' + message + ' ' + type;
    var bookingId = parseInt(data.booking_id, 10) || 0;
    var bookingRef = bookingId > 0 ? 'REF-' + String(bookingId).padStart(3, '0') : '';
    var query = bookingId > 0 ? encodeURIComponent(bookingRef) : '';
    var adminReservations = cfg.adminReservations || 'reservations';
    var checkinPage = 'admin_checkin';
    var checkoutPage = cfg.checkoutPage || 'checkout';

    if (context.includes('discount')) {
        return { label: 'Review Discount', url: 'discount_requests.php' + (bookingId ? '?booking=' + bookingId : '') };
    }
    if (context.includes('refund')) {
        return { label: 'View Refund', url: 'admin_refunds' + (bookingId ? '?booking=' + bookingId : '') };
    }
    if (context.includes('payment') || context.includes('gcash') || context.includes('balance collected')) {
        return { label: 'Review Payment', url: 'payments' + (query ? '?search=' + query : '') };
    }
    if (context.includes('check-out') || context.includes('checkout') || context.includes('departure')) {
        return { label: 'Open Check-out', url: checkoutPage + (query ? '?search=' + query : '') };
    }
    if (context.includes('check-in') || context.includes('checkin') || context.includes('walk-in') || context.includes('arrival')) {
        return { label: 'Open Check-in', url: checkinPage + (query ? '?search=' + query : '') };
    }
    if (context.includes('maintenance') || context.includes('room unavailable')) {
        return { label: 'View Rooms', url: 'accommodations' };
    }
    if (context.includes('concierge') || context.includes('escalation') || context.includes('human support') || context.includes('live chat') || context.includes('live support')) {
        return { label: 'Open Live Chats', url: 'admin_live_support' };
    }
    if (context.includes('inquiry')) {
        return { label: 'Open Inquiries', url: 'admin_inquiries' };
    }
    if (bookingId > 0) {
        return { label: 'View Reservation', url: adminReservations + '?search=' + query };
    }
    return null;
}

function showNotificationDetailModal(data) {
    var notifMenu = document.getElementById('headerNotifMenu');
    if (notifMenu) notifMenu.classList.remove('open');

    var modal = document.getElementById('globalNotifDetailModal');
    if (!modal) return;

    document.getElementById('modalNotifTitle').textContent = data.title || 'Notification';
    document.getElementById('modalNotifMessage').textContent = data.message || '';
    document.getElementById('modalNotifTime').textContent = data.time || 'Recent';

    var type = (data.type || 'info').toLowerCase();
    var badge = document.getElementById('modalNotifBadge');
    badge.textContent = type.toUpperCase();
    badge.className = 'notif-badge badge-' + type;

    var iconCircle = document.getElementById('modalNotifIconCircle');
    iconCircle.className = 'notif-icon-circle notif-icon-' + type;

    var bookingWrap = document.getElementById('modalNotifBookingWrap');
    var actionBtn = document.getElementById('modalNotifActionBtn');
    var action = resolveNotificationAction(data);
    if (data.booking_id && parseInt(data.booking_id, 10) > 0) {
        bookingWrap.style.display = 'block';
        document.getElementById('modalNotifBookingText').textContent = 'Booking #' + data.booking_id;
    } else {
        bookingWrap.style.display = 'none';
    }

    if (action) {
        actionBtn.textContent = action.label;
        actionBtn.style.display = 'inline-block';
        actionBtn.onclick = function() { window.location.href = action.url; };
    } else {
        actionBtn.style.display = 'none';
        actionBtn.onclick = null;
    }

    modal.style.display = 'flex';

    // Mark as read asynchronously
    if (data.id) {
        var formData = new FormData();
        formData.append('action', 'mark_read');
        formData.append('id', data.id);

        fetch('../backend/api/notifications_api.php', {
            method: 'POST',
            body: formData
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res && res.success) {
                var badgeEl = document.getElementById('headerNotifBadge');
                if (badgeEl) {
                    if (res.unread_count <= 0) {
                        badgeEl.style.display = 'none';
                        badgeEl.textContent = '0';
                    } else {
                        badgeEl.style.display = 'inline-flex';
                        badgeEl.textContent = res.unread_count > 99 ? '99+' : res.unread_count;
                    }
                }
                var countEl = document.getElementById('headerNotifCount');
                if (countEl) countEl.textContent = res.unread_count + ' unread';

                var itemEl = document.querySelector('.notif-item[data-id="' + data.id + '"]');
                if (itemEl) {
                    itemEl.classList.remove('unread');
                    var dot = itemEl.querySelector('.notif-dot-unread');
                    if (dot) dot.remove();
                }
            }
        })
        .catch(function() {});
    }
}

function closeGlobalNotifModal() {
    var modal = document.getElementById('globalNotifDetailModal');
    if (modal) modal.style.display = 'none';
}

function markAllNotifsRead(e) {
    e.stopPropagation();
    var formData = new FormData();
    formData.append('action', 'mark_all_read');

    fetch('../backend/api/notifications_api.php', {
        method: 'POST',
        body: formData
    })
    .then(function(res) { return res.json(); })
    .then(function(data) {
        if (data.success) {
            var badge = document.getElementById('headerNotifBadge');
            if (badge) {
                badge.style.display = 'none';
                badge.textContent = '0';
            }
            var count = document.getElementById('headerNotifCount');
            if (count) count.textContent = '0 unread';

            var items = document.querySelectorAll('.notif-item.unread');
            items.forEach(function(el) {
                el.classList.remove('unread');
                var dot = el.querySelector('.notif-dot-unread');
                if (dot) dot.remove();
            });
        }
    });
}

document.addEventListener('click', function(e) {
    var notifMenu = document.getElementById('headerNotifMenu');
    var userMenu = document.getElementById('pageHeaderUserMenu');
    var detailModal = document.getElementById('globalNotifDetailModal');

    if (notifMenu && !notifMenu.contains(e.target)) {
        notifMenu.classList.remove('open');
    }
    if (userMenu && !userMenu.contains(e.target)) {
        userMenu.classList.remove('open');
    }
    if (detailModal && e.target === detailModal) {
        closeGlobalNotifModal();
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeGlobalNotifModal();
    }
});

// Live Resort Clock Ticker (Asia/Manila PST)
(function initResortClock() {
    var timeEl = document.getElementById('resortClockTime');
    var dateEl = document.getElementById('resortClockDate');
    if (!timeEl || !dateEl) return;

    var timeFormatter = new Intl.DateTimeFormat('en-US', {
        timeZone: 'Asia/Manila',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: true
    });

    var dateFormatter = new Intl.DateTimeFormat('en-US', {
        timeZone: 'Asia/Manila',
        weekday: 'short',
        month: 'short',
        day: 'numeric'
    });

    function tick() {
        var now = new Date();
        try {
            timeEl.textContent = timeFormatter.format(now);
            dateEl.textContent = dateFormatter.format(now);
        } catch (e) {
            timeEl.textContent = now.toLocaleTimeString();
            dateEl.textContent = now.toLocaleDateString();
        }
    }

    tick();
    setInterval(tick, 1000);
})();

// Dynamic toggle button icon: arrow when open, burger when collapsed
(function() {
    function updateToggleIcon() {
        var btn = document.getElementById('sidebarToggleBtn');
        if (!btn) return;
        var arrowIcon  = btn.querySelector('.toggle-icon-arrow');
        var burgerIcon = btn.querySelector('.toggle-icon-burger');
        if (!arrowIcon || !burgerIcon) return;
        var isCollapsed = document.documentElement.classList.contains('sbc-collapsed') ||
                          (document.querySelector('.admin-sidebar, .sidebar') &&
                           document.querySelector('.admin-sidebar, .sidebar').classList.contains('collapsed'));
        var isMobile = window.innerWidth <= 1024;
        var isOpenMobile = document.querySelector('.admin-sidebar, .sidebar') &&
                           document.querySelector('.admin-sidebar, .sidebar').classList.contains('open');
        if (isMobile || document.documentElement.classList.contains('sbc-sidebar-dropdown')) {
            // On mobile: burger when closed, arrow when open overlay
            arrowIcon.style.display  = isOpenMobile ? '' : 'none';
            burgerIcon.style.display = isOpenMobile ? 'none' : '';
        } else {
            // On desktop: arrow when expanded, burger when collapsed
            arrowIcon.style.display  = isCollapsed ? 'none' : '';
            burgerIcon.style.display = isCollapsed ? '' : 'none';
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        updateToggleIcon();
        // Re-check on every click of the toggle button
        var btn = document.getElementById('sidebarToggleBtn');
        if (btn) {
            btn.addEventListener('click', function() {
                setTimeout(updateToggleIcon, 40);
            });
        }
        // Also watch for sidebar open/close on mobile
        var sidebar = document.querySelector('.admin-sidebar, .sidebar');
        if (sidebar) {
            var obs = new MutationObserver(updateToggleIcon);
            obs.observe(sidebar, { attributes: true, attributeFilter: ['class'] });
        }
        var htmlEl = document.documentElement;
        var htmlObs = new MutationObserver(updateToggleIcon);
        htmlObs.observe(htmlEl, { attributes: true, attributeFilter: ['class'] });
    });

    window.addEventListener('resize', function() {
        setTimeout(updateToggleIcon, 50);
    });
})();
