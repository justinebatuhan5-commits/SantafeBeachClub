<?php
/**
 * _page_header.php — Shared luxury page header component.
 * Include this on every page (reception and admin).
 */
require_once __DIR__ . '/../../backend/helpers/page_header_helper.php';

$_phState              = sfbc_resolve_page_header_state($conn ?? null, $page_title ?? '', $page_subtitle ?? '', $active_page ?? null);
$_ph_title             = $_phState['title'];
$_ph_subtitle          = $_phState['subtitle'];
$_ph_username          = $_phState['username'];
$_ph_role              = $_phState['role'];
$_ph_is_admin          = $_phState['is_admin'];
$_ph_notifications_href = $_phState['notifications_href'];
$_ph_role_label        = $_phState['role_label'];
$_ph_photo             = $_phState['photo'];
$_ph_unread            = $_phState['unread'];
$_ph_csrf_token        = $_phState['csrf_token'];
$_ph_recent_notifs     = $_phState['recent_notifs'];
$_ph_context           = $_phState['context'];
$_ph_portal_badge      = $_phState['portal_badge'];
?>
<link rel="stylesheet" href="<?php echo $base_url ?? ''; ?>assets/css/tailwind.css?v=<?php echo (int) @filemtime(__DIR__ . '/../assets/css/tailwind.css'); ?>">
<link rel="stylesheet" href="<?php echo $base_url ?? ''; ?>assets/css/page-transitions.css?v=<?php echo (int) @filemtime(__DIR__ . '/../assets/css/page-transitions.css'); ?>">
<link rel="stylesheet" href="<?php echo $base_url ?? ''; ?>assets/css/components/page-header.css?v=<?php echo (int) @filemtime(__DIR__ . '/../assets/css/components/page-header.css'); ?>">
<script src="<?php echo $base_url ?? ''; ?>assets/js/page-transitions.js?v=<?php echo (int) @filemtime(__DIR__ . '/../assets/js/page-transitions.js'); ?>" defer></script>

<header class="page-header" data-role="<?php echo htmlspecialchars($_ph_role, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="page-header-left">
        <button class="sidebar-toggle-btn" id="sidebarToggleBtn" aria-label="Toggle Navigation" title="Toggle Sidebar">
            <!-- Arrow icon (shown when sidebar is open/expanded) -->
            <svg class="toggle-icon-arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M15 18l-6-6 6-6"/>
            </svg>
            <!-- Burger icon (shown when sidebar is collapsed/closed) -->
            <svg class="toggle-icon-burger" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
                <line x1="3" y1="6" x2="21" y2="6"/>
                <line x1="3" y1="12" x2="21" y2="12"/>
                <line x1="3" y1="18" x2="21" y2="18"/>
            </svg>
        </button>

        <div class="page-header-icon" aria-hidden="true">
            <?php echo $_ph_context['icon']; ?>
        </div>

        <div class="page-header-titles">
            <div class="page-header-eyebrow">
                <span class="page-header-portal-chip"><?php echo htmlspecialchars($_ph_portal_badge); ?></span>
                <span class="page-header-eyebrow-sep" aria-hidden="true">/</span>
                <span><?php echo htmlspecialchars($_ph_context['section']); ?></span>
            </div>
            <h1 class="page-header-title"><?php echo htmlspecialchars($_ph_title); ?></h1>
            <?php if ($_ph_subtitle !== ''): ?>
            <p class="page-header-subtitle" title="<?php echo htmlspecialchars($_ph_subtitle, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($_ph_subtitle); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="page-header-actions">
        <!-- Live Resort Clock -->
        <div class="header-resort-clock" id="headerResortClock" title="Resort Operational Time (PHT - Asia/Manila)">
            <div class="resort-clock-icon">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <span class="resort-clock-dot"></span>
            </div>
            <div class="resort-clock-details">
                <span class="resort-clock-time" id="resortClockTime">--:--:-- --</span>
                <span class="resort-clock-date" id="resortClockDate">Loading...</span>
            </div>
            <span class="resort-clock-badge">PST</span>
        </div>

        <?php if (!empty($header_extra_html)) { echo $header_extra_html; } ?>

        <!-- Notification Popover Menu -->
        <div class="header-notif-menu" id="headerNotifMenu">
            <button type="button" class="header-icon-btn" id="headerNotifBtn" title="Notifications" aria-label="Notifications" onclick="toggleNotifDropdown(event)">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                <span class="notification-badge" id="headerNotifBadge" style="<?php echo $_ph_unread > 0 ? '' : 'display:none;'; ?>"><?php echo $_ph_unread > 99 ? '99+' : $_ph_unread; ?></span>
            </button>

            <div class="header-notif-dropdown" id="headerNotifDropdown">
                <div class="notif-dropdown-header">
                    <div class="notif-dropdown-title">
                        <span>Notifications</span>
                        <span class="notif-badge-pill" id="headerNotifCount"><?php echo $_ph_unread; ?> unread</span>
                    </div>
                    <button type="button" class="notif-mark-all-btn" onclick="markAllNotifsRead(event)">Mark all read</button>
                </div>

                <div class="notif-dropdown-list" id="headerNotifList">
                    <?php if (!empty($_ph_recent_notifs)): ?>
                        <?php foreach ($_ph_recent_notifs as $n):
                            $is_unread = ($n['is_read'] == 0);
                            $type = strtolower($n['type'] ?? 'info');
                            $icon_class = 'notif-icon-info';
                            $icon_svg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>';
                            if ($type === 'warning') {
                                $icon_class = 'notif-icon-warning';
                                $icon_svg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';
                            } elseif ($type === 'success') {
                                $icon_class = 'notif-icon-success';
                                $icon_svg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
                            } elseif ($type === 'danger') {
                                $icon_class = 'notif-icon-danger';
                                $icon_svg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';
                            }

                            $time_diff = time() - strtotime($n['created_at']);
                            if ($time_diff < 60) { $time_str = 'Just now'; }
                            elseif ($time_diff < 3600) { $time_str = floor($time_diff / 60) . 'm ago'; }
                            elseif ($time_diff < 86400) { $time_str = floor($time_diff / 3600) . 'h ago'; }
                            else { $time_str = date('M j', strtotime($n['created_at'])); }
                        ?>
                        <div class="notif-item <?php echo $is_unread ? 'unread' : ''; ?>" data-id="<?php echo $n['id']; ?>" onclick="showNotificationDetailModal({id: <?php echo $n['id']; ?>, title: <?php echo xss_attr_json($n['title']); ?>, message: <?php echo xss_attr_json($n['message']); ?>, type: <?php echo xss_attr_json($type); ?>, booking_id: <?php echo (int)($n['booking_id'] ?? 0); ?>, time: <?php echo xss_attr_json($time_str); ?>})">
                            <div class="notif-icon-circle <?php echo $icon_class; ?>">
                                <?php echo $icon_svg; ?>
                            </div>
                            <div class="notif-content">
                                <div class="notif-title-row">
                                    <span class="notif-item-title"><?php echo htmlspecialchars($n['title']); ?></span>
                                    <span class="notif-item-time"><?php echo $time_str; ?></span>
                                </div>
                                <div class="notif-item-msg"><?php echo htmlspecialchars($n['message']); ?></div>
                            </div>
                            <?php if ($is_unread): ?>
                                <span class="notif-dot-unread"></span>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="notif-empty-state">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom:8px; opacity:0.5;"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                            <div>No notifications right now</div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="notif-dropdown-footer">
                    <a href="<?php echo $_ph_notifications_href; ?>">View all notifications &rarr;</a>
                </div>
            </div>
        </div>

        <div class="header-user-menu" id="pageHeaderUserMenu">
            <button type="button" class="header-user-trigger" onclick="toggleUserDropdown(event)" title="<?php echo htmlspecialchars(($_ph_username !== '' ? $_ph_username : $_ph_role_label) . ' (' . $_ph_role_label . ')', ENT_QUOTES, 'UTF-8'); ?>">
                <?php if (!empty($_ph_photo) && (str_starts_with($_ph_photo, 'http') || file_exists(__DIR__ . '/../' . $_ph_photo))): ?>
                    <img src="<?php echo htmlspecialchars($_ph_photo); ?>" alt="Avatar" class="header-user-avatar" style="object-fit:cover; border:1px solid var(--border);">
                <?php else: ?>
                    <span class="header-user-avatar"><?php echo strtoupper(substr($_ph_username !== '' ? $_ph_username : 'U', 0, 1)); ?></span>
                <?php endif; ?>
                <span class="header-user-name"><?php echo htmlspecialchars($_ph_username !== '' ? $_ph_username : $_ph_role_label); ?></span>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
            </button>
            <div class="header-user-dropdown">
                <?php if ($_ph_is_admin): ?>
                <a href="dashboard">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    Switch to Reception Desk
                </a>
                <?php else: ?>
                <a href="admin_dashboard">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>
                    Switch to Admin Panel
                </a>
                <?php endif; ?>
                <a href="settings">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                    Settings
                </a>
                <?php if ($_ph_is_admin): ?>
                <a href="forgot_password?portal=admin">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    Reset My Password
                </a>
                <?php endif; ?>
                <a href="logout" class="danger">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    Log Out
                </a>
                <div style="padding:8px 14px 4px; font-size:11px; color:#94A3B8; border-top:1px solid var(--border,#E2E8F0); text-align:center;">
                    Dev: <strong style="color:var(--text-secondary,#475569);">Justine Batuhan</strong>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- ── Global Notification Detail Modal ── -->
<div id="globalNotifDetailModal" style="display:none;position:fixed;inset:0;z-index:99999;background:rgba(15,23,42,.6);backdrop-filter:blur(4px);align-items:center;justify-content:center;padding:16px;">
    <div style="background:var(--bg-surface-elev, #ffffff);border:1px solid var(--border, #E5E7EB);border-radius:18px;box-shadow:0 24px 60px rgba(15,23,42,.25);max-width:480px;width:100%;animation:notifDropdownFade .18s cubic-bezier(.34,1.56,.64,1);overflow:hidden;">
        <div style="padding:20px 24px 16px;display:flex;align-items:flex-start;justify-content:space-between;border-bottom:1px solid var(--border, #F3F4F6);gap:12px;">
            <div style="display:flex;align-items:center;gap:12px;">
                <div id="modalNotifIconCircle" class="notif-icon-circle notif-icon-info" style="width:38px;height:38px;font-size:16px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                </div>
                <div>
                    <span id="modalNotifBadge" class="notif-badge badge-info" style="margin-bottom:4px;">INFO</span>
                    <h3 id="modalNotifTitle" style="margin:0;font-size:16px;font-weight:700;color:var(--text-main, #0f172a);"></h3>
                </div>
            </div>
            <button type="button" onclick="closeGlobalNotifModal()" style="background:none;border:none;font-size:24px;color:var(--text-muted, #94a3b8);cursor:pointer;line-height:1;padding:2px 6px;">&times;</button>
        </div>
        <div style="padding:20px 24px;max-height:60vh;overflow-y:auto;">
            <div style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--text-muted, #64748b);margin-bottom:14px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <span id="modalNotifTime"></span>
            </div>
            <div id="modalNotifMessage" style="font-size:14px;color:var(--text-main, #334155);line-height:1.6;background:var(--bg-surface, #F8FAFC);padding:14px 16px;border-radius:10px;border:1px solid var(--border-light, #E2E8F0);white-space:pre-wrap;"></div>
            <div id="modalNotifBookingWrap" style="display:none;margin-top:14px;padding:12px 14px;background:rgba(59,130,246,0.08);border:1px solid rgba(59,130,246,0.25);border-radius:10px;">
                <span style="font-size:11.5px;font-weight:700;color:#1D4ED8;display:block;margin-bottom:4px;text-transform:uppercase;letter-spacing:0.5px;">Related Booking</span>
                <span id="modalNotifBookingText" style="font-size:13px;font-weight:600;color:#1E40AF;"></span>
            </div>
        </div>
        <div style="padding:14px 24px;background:var(--bg-surface, #F8FAFC);border-top:1px solid var(--border, #F3F4F6);display:flex;justify-content:flex-end;gap:10px;">
            <button type="button" onclick="closeGlobalNotifModal()" style="padding:9px 18px;border:1.5px solid var(--border, #E2E8F0);border-radius:8px;background:var(--bg-surface-elev, #fff);font-size:13px;font-weight:600;color:var(--text-main, #475569);cursor:pointer;">Close</button>
            <button type="button" id="modalNotifActionBtn" style="display:none;padding:9px 18px;border:none;border-radius:8px;background:var(--primary, #84563C);color:#fff;font-size:13px;font-weight:600;cursor:pointer;">Open</button>
        </div>
    </div>
</div>

<script>
window.SFBC_HEADER_CONFIG = <?php echo json_encode([
    'adminReservations' => $_ph_is_admin ? 'admin_reservations' : 'reservations',
    'checkoutPage'      => $_ph_is_admin ? 'admin_checkout' : 'checkout',
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>
<script src="<?php echo $base_url ?? ''; ?>assets/js/components/page-header.js?v=<?php echo (int) @filemtime(__DIR__ . '/../assets/js/components/page-header.js'); ?>"></script>

<!-- Inactivity Auto-Logout Timer (15-min timeout with 60-sec warning modal) -->
<script src="assets/js/inactivity-timer.js" defer></script>
