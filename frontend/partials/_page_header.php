<?php
/**
 * _page_header.php — Shared luxury page header component.
 * Include this on every page (reception and admin).
 */
$_ph_title      = $page_title ?? '';
$_ph_subtitle   = $page_subtitle ?? '';
$_ph_username   = $_SESSION['admin_username'] ?? '';
$_ph_role       = $_SESSION['admin_role'] ?? 'receptionist';
$_ph_is_admin   = ($_ph_role === 'admin');
$_ph_notifications_href = $_ph_is_admin ? 'admin_notifications' : 'notifications';
$_ph_role_label = $_ph_is_admin ? 'Administrator' : 'Receptionist';
$_ph_photo      = $_SESSION['admin_profile_photo'] ?? null;

if (empty($_ph_photo) && isset($conn) && !empty($_ph_username)) {
    if ($st = $conn->prepare("SELECT profile_photo FROM admins WHERE username = ? LIMIT 1")) {
        $st->bind_param("s", $_ph_username);
        $st->execute();
        $rowP = $st->get_result()->fetch_assoc();
        $st->close();
        if (!empty($rowP['profile_photo'])) {
            $_ph_photo = $rowP['profile_photo'];
            $_SESSION['admin_profile_photo'] = $_ph_photo;
        }
    }
}

$_ph_unread = 0;
if (isset($conn)) {
    $_ph_notif_row = $conn->query("SELECT COUNT(*) as unread FROM notifications WHERE is_read = 0")->fetch_assoc();
    $_ph_unread = $_ph_notif_row ? (int)$_ph_notif_row['unread'] : 0;
}

$_ph_csrf_token = function_exists('get_csrf_token') ? get_csrf_token() : ($_SESSION['csrf_token'] ?? '');
?>
<?php
$_ph_recent_notifs = [];
if (isset($conn)) {
    $_ph_notifs_res = $conn->query("SELECT id, title, message, type, is_read, booking_id, created_at FROM notifications ORDER BY id DESC LIMIT 6");
    if ($_ph_notifs_res) {
        while ($nr = $_ph_notifs_res->fetch_assoc()) {
            $_ph_recent_notifs[] = $nr;
        }
    }
}
?>
<style>
/* Notification Popover Dropdown Styles */
.header-notif-menu {
    position: relative;
}
.header-notif-dropdown {
    display: none;
    position: absolute;
    top: calc(100% + 12px);
    right: 0;
    width: 380px;
    max-width: 90vw;
    background: var(--bg-surface-elev, #ffffff);
    border: 1px solid var(--border, #E2E8F0);
    border-radius: var(--radius-lg, 16px);
    box-shadow: 0 20px 45px -10px rgba(15, 23, 42, 0.16);
    z-index: 250;
    animation: notifDropdownFade 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    overflow: hidden;
}
@keyframes notifDropdownFade {
    from { opacity: 0; transform: translateY(-8px) scale(0.98); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}
.header-notif-menu.open .header-notif-dropdown {
    display: block;
}
.notif-dropdown-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 18px;
    border-bottom: 1px solid var(--border-light, #F1F5F9);
    background: var(--bg-surface, #ffffff);
}
.notif-dropdown-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    font-weight: 700;
    color: var(--text-main, #0F172A);
}
.notif-badge-pill {
    font-size: 11px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 999px;
    background: rgba(132, 86, 60, 0.12);
    color: var(--primary, #84563C);
}
.notif-mark-all-btn {
    background: none;
    border: none;
    font-size: 12px;
    font-weight: 600;
    color: var(--primary, #84563C);
    cursor: pointer;
    padding: 4px 8px;
    border-radius: 6px;
    transition: all 0.15s;
}
.notif-mark-all-btn:hover {
    background: var(--sidebar-hover, #F8FAFC);
}
.notif-dropdown-list {
    max-height: 340px;
    overflow-y: auto;
}
.notif-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 12px 18px;
    border-bottom: 1px solid var(--border-light, #F8FAFC);
    text-decoration: none;
    color: inherit;
    transition: background 0.15s;
    cursor: pointer;
}
.notif-item:last-child {
    border-bottom: none;
}
.notif-item:hover {
    background: var(--sidebar-hover, #F8FAFC);
}
.notif-item.unread {
    background: rgba(132, 86, 60, 0.05);
}
[data-theme="dark"] .notif-item.unread {
    background: rgba(132, 86, 60, 0.14);
}
.notif-icon-circle {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 14px;
}
.notif-icon-info { background: #E0F2FE; color: #0284C7; }
.notif-icon-warning { background: #FEF3C7; color: #D97706; }
.notif-icon-success { background: #DCFCE7; color: #16A34A; }
.notif-icon-danger { background: #FEE2E2; color: #DC2626; }
.notif-content {
    flex: 1;
    min-width: 0;
}
.notif-title-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 6px;
    margin-bottom: 2px;
}
.notif-item-title {
    font-size: 13px;
    font-weight: 600;
    color: var(--text-main, #0F172A);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.notif-item-time {
    font-size: 11px;
    color: var(--text-subtle, #94A3B8);
    flex-shrink: 0;
}
.notif-item-msg {
    font-size: 12px;
    color: var(--text-muted, #64748B);
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.notif-dot-unread {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: var(--primary, #84563C);
    flex-shrink: 0;
    margin-top: 5px;
}
.notif-empty-state {
    padding: 36px 20px;
    text-align: center;
    color: var(--text-muted, #64748B);
    font-size: 13px;
}
.notif-dropdown-footer {
    padding: 12px 18px;
    text-align: center;
    border-top: 1px solid var(--border-light, #F1F5F9);
    background: var(--bg-surface, #ffffff);
}
.notif-dropdown-footer a {
    font-size: 12.5px;
    font-weight: 700;
    color: var(--primary, #84563C);
    text-decoration: none;
}
.notif-dropdown-footer a:hover {
    text-decoration: underline;
}

/* Luxury Header Base */
.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    background: var(--bg-surface, #ffffff);
    border-bottom: 1px solid var(--border, #E2E8F0);
    padding: 18px 36px;
    position: sticky;
    top: 0;
    z-index: 100;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.02);
}
[data-theme="dark"] .page-header {
    background: var(--bg-surface, #131826);
    border-bottom-color: var(--border, #1E293B);
}

.page-header-left {
    display: flex;
    align-items: center;
    gap: 16px;
    min-width: 0;
    flex: 1;
}

.sidebar-toggle-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border: 1px solid var(--border, #E2E8F0);
    background: var(--bg-surface-elev, #ffffff);
    color: var(--text-main, #0F172A);
    cursor: pointer;
    border-radius: var(--radius-md, 12px);
    flex-shrink: 0;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: var(--shadow-xs);
}
.sidebar-toggle-btn:hover {
    background: var(--sidebar-hover, #F8FAFC);
    border-color: var(--primary, #84563C);
    color: var(--primary, #84563C);
    transform: translateY(-1px);
}

.page-header-titles {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.page-header-title {
    font-family: var(--font-heading, 'Outfit', sans-serif);
    font-size: 20px;
    font-weight: 800;
    color: var(--text-main, #0F172A);
    letter-spacing: -0.3px;
    line-height: 1.25;
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.page-header-subtitle {
    font-size: 12.5px;
    font-weight: 500;
    color: var(--text-muted, #64748B);
    margin: 2px 0 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.page-header-actions {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-shrink: 0;
}

/* Redesigned Resort Clock */
.header-resort-clock {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    background: var(--sidebar-hover, #F8FAFC);
    border: 1px solid var(--border, #E2E8F0);
    padding: 7px 14px;
    border-radius: 999px;
    font-size: 12.5px;
    color: var(--text-main, #0F172A);
    font-weight: 600;
    user-select: none;
    box-shadow: var(--shadow-xs);
}
[data-theme="dark"] .header-resort-clock {
    background: var(--bg-surface-elev, #1A2234);
    border-color: var(--border, #1E293B);
}
.resort-clock-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary, #84563C);
    position: relative;
}
.resort-clock-dot {
    width: 6px;
    height: 6px;
    background: #10B981;
    border-radius: 50%;
    position: absolute;
    top: -1px;
    right: -2px;
    box-shadow: 0 0 6px #10B981;
    animation: pulseDot 2s infinite ease-in-out;
}
@keyframes pulseDot {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.4; transform: scale(0.85); }
}
.resort-clock-details {
    display: flex;
    align-items: baseline;
    gap: 6px;
}
.resort-clock-time {
    font-weight: 700;
    font-size: 13px;
    letter-spacing: 0.2px;
    font-variant-numeric: tabular-nums;
}
.resort-clock-date {
    font-size: 11.5px;
    font-weight: 500;
    color: var(--text-muted, #64748B);
}
.resort-clock-badge {
    font-size: 9px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 2px 5px;
    background: rgba(132, 86, 60, 0.1);
    color: var(--primary, #84563C);
    border-radius: 4px;
}

/* Icon Buttons (Notifications, etc) */
.header-icon-btn {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--bg-surface-elev, #ffffff);
    border: 1px solid var(--border, #E2E8F0);
    border-radius: var(--radius-md, 12px);
    color: var(--text-muted, #64748B);
    cursor: pointer;
    position: relative;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: var(--shadow-xs);
}
.header-icon-btn:hover {
    background: var(--sidebar-hover, #F8FAFC);
    color: var(--primary, #84563C);
    border-color: var(--primary, #84563C);
    transform: translateY(-1px);
}
.notification-badge {
    position: absolute;
    top: -4px;
    right: -4px;
    min-width: 18px;
    height: 18px;
    padding: 0 4px;
    background: #EF4444;
    color: #FFFFFF;
    border-radius: 999px;
    border: 2px solid var(--bg-surface, #ffffff);
    font-size: 10.5px;
    font-weight: 800;
    line-height: 14px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 5px rgba(239, 68, 68, 0.4);
    pointer-events: none;
    box-sizing: border-box;
}

/* User Pill & Dropdown */
.header-user-menu {
    position: relative;
}
.header-user-trigger {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 5px 12px 5px 6px;
    background: var(--sidebar-hover, #F8FAFC);
    border: 1px solid var(--border, #E2E8F0);
    border-radius: 999px;
    cursor: pointer;
    font-family: 'Outfit', sans-serif;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: var(--shadow-xs);
}
.header-user-trigger:hover {
    background: var(--primary-light, #F5ECE5);
    border-color: var(--primary, #84563C);
}
.header-user-avatar {
    width: 30px;
    height: 30px;
    background: linear-gradient(135deg, #84563C 0%, #A37152 100%);
    color: #ffffff;
    font-weight: 700;
    font-size: 13px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}
.header-user-name {
    font-size: 13px;
    font-weight: 600;
    color: var(--text-main, #0F172A);
    max-width: 140px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.header-user-dropdown {
    display: none;
    position: absolute;
    top: calc(100% + 12px);
    right: 0;
    width: 220px;
    background: var(--bg-surface-elev, #ffffff);
    border: 1px solid var(--border, #E2E8F0);
    border-radius: var(--radius-lg, 16px);
    box-shadow: 0 20px 45px -10px rgba(15, 23, 42, 0.16);
    padding: 8px;
    z-index: 250;
    animation: notifDropdownFade 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.header-user-menu.open .header-user-dropdown {
    display: block;
}
.header-user-dropdown a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    font-size: 13px;
    font-weight: 600;
    color: var(--text-main, #0F172A);
    text-decoration: none;
    border-radius: var(--radius-sm, 8px);
    transition: background 0.15s;
}
.header-user-dropdown a:hover {
    background: var(--sidebar-hover, #F8FAFC);
    color: var(--primary, #84563C);
}
.header-user-dropdown a.danger {
    color: #DC2626;
}
.header-user-dropdown a.danger:hover {
    background: #FEF2F2;
}

@media (max-width: 1024px) {
    .page-header {
        padding: 14px 20px;
    }
    .header-resort-clock .resort-clock-date {
        display: none;
    }
}
@media (max-width: 768px) {
    .page-header-subtitle {
        display: none;
    }
    .header-resort-clock {
        display: none;
    }
}
</style>

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

        <div class="page-header-titles">
            <h1 class="page-header-title"><?php echo htmlspecialchars($_ph_title); ?></h1>
            <?php if ($_ph_subtitle !== ''): ?>
            <p class="page-header-subtitle"><?php echo htmlspecialchars($_ph_subtitle); ?></p>
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
                            elseif ($time_diff < 3600) { $time_str = floor($time_diff/60) . 'm ago'; }
                            elseif ($time_diff < 86400) { $time_str = floor($time_diff/3600) . 'h ago'; }
                            else { $time_str = date('M j', strtotime($n['created_at'])); }
                        ?>
                        <div class="notif-item <?php echo $is_unread ? 'unread' : ''; ?>" data-id="<?php echo $n['id']; ?>" onclick="showNotificationDetailModal({id: <?php echo $n['id']; ?>, title: <?php echo json_encode($n['title']); ?>, message: <?php echo json_encode($n['message']); ?>, type: <?php echo json_encode($type); ?>, booking_id: <?php echo (int)($n['booking_id'] ?? 0); ?>, time: <?php echo json_encode($time_str); ?>})">
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
            <button class="header-user-trigger" onclick="toggleUserDropdown(event)">
                <?php if (!empty($_ph_photo) && file_exists(__DIR__ . '/../' . $_ph_photo)): ?>
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
                <a href="logout" class="danger">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    Log Out
                </a>
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
            <button type="button" id="modalNotifActionBtn" style="display:none;padding:9px 18px;border:none;border-radius:8px;background:var(--primary, #84563C);color:#fff;font-size:13px;font-weight:600;cursor:pointer;">View Booking</button>
        </div>
    </div>
</div>

<script>
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
    if (data.booking_id && parseInt(data.booking_id) > 0) {
        bookingWrap.style.display = 'block';
        document.getElementById('modalNotifBookingText').textContent = 'Booking #' + data.booking_id;
        actionBtn.style.display = 'inline-block';
        actionBtn.onclick = function() {
            window.location.href = '<?php echo $_ph_is_admin ? "admin_reservations" : "reservations"; ?>?search=' + data.booking_id;
        };
    } else {
        bookingWrap.style.display = 'none';
        actionBtn.style.display = 'none';
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
        } catch(e) {
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
        if (isMobile) {
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
</script>

<!-- Inactivity Auto-Logout Timer (15-min timeout with 60-sec warning modal) -->
<script src="assets/js/inactivity-timer.js" defer></script>
