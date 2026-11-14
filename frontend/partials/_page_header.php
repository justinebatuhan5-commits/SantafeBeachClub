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
    $tbl = ($_ph_role === 'admin') ? 'administrators' : 'receptionists';
    if ($st = $conn->prepare("SELECT profile_photo FROM `{$tbl}` WHERE username = ? LIMIT 1")) {
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
if (!function_exists('sf_header_context')) {
    /**
     * Maps the active admin/reception route or title to a clean section label and crisp SVG icon.
     */
    function sf_header_context($activePage, $title, $isAdmin) {
        $key = strtolower(trim((string)$activePage));
        $t   = strtolower(trim((string)$title));
        $icons = [
            'grid'     => '<svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/></svg>',
            'calendar' => '<svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="m9 16 2 2 4-4"/></svg>',
            'checkin'  => '<svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 3l4 4-4 4"/><path d="M20 7H4"/><path d="M8 21l-4-4 4-4"/><path d="M4 17h16"/></svg>',
            'users'    => '<svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
            'home'     => '<svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 10.5L12 3l9 7.5"/><path d="M5 10v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V10"/><path d="M9 21v-6h6v6"/></svg>',
            'tag'      => '<svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>',
            'gallery'  => '<svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>',
            'wallet'   => '<svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>',
            'chart'    => '<svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>',
            'bell'     => '<svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>',
            'chat'     => '<svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
            'shield'   => '<svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
            'settings' => '<svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
            'clock'    => '<svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
            'backup'   => '<svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>',
        ];

        $map = [
            'dashboard'         => ['section' => 'Overview',             'icon' => $icons['grid']],
            'reservations'      => ['section' => 'Front Desk',           'icon' => $icons['calendar']],
            'checkin'           => ['section' => 'Front Desk',           'icon' => $icons['checkin']],
            'checkout'          => ['section' => 'Front Desk',           'icon' => $icons['checkin']],
            'guests'            => ['section' => 'Property & Guests',    'icon' => $icons['users']],
            'accommodations'    => ['section' => 'Property & Guests',    'icon' => $icons['home']],
            'calendar'          => ['section' => 'Property & Guests',    'icon' => $icons['calendar']],
            'promotions'        => ['section' => 'Property & Guests',    'icon' => $icons['tag']],
            'gallery'           => ['section' => 'Property & Guests',    'icon' => $icons['gallery']],
            'room_photos'       => ['section' => 'Property & Guests',    'icon' => $icons['home']],
            'chatbot'           => ['section' => 'AI Concierge',         'icon' => $icons['chat']],
            'discount_requests' => ['section' => 'Finance',              'icon' => $icons['tag']],
            'payments'          => ['section' => 'Finance',              'icon' => $icons['wallet']],
            'admin_refunds'     => ['section' => 'Finance',              'icon' => $icons['wallet']],
            'reports'           => ['section' => 'Insights & Updates',   'icon' => $icons['chart']],
            'notifications'     => ['section' => 'Insights & Updates',   'icon' => $icons['bell']],
            'inquiries'         => ['section' => 'Guest Feedback',       'icon' => $icons['chat']],
            'live_support'      => ['section' => 'Guest Feedback',       'icon' => $icons['chat']],
            'reviews'           => ['section' => 'Guest Feedback',       'icon' => $icons['chat']],
            'staff'             => ['section' => 'System & Access',      'icon' => $icons['users']],
            'logs'              => ['section' => 'System & Access',      'icon' => $icons['shield']],
            'backups'           => ['section' => 'System & Access',      'icon' => $icons['backup']],
            'automation'        => ['section' => 'System & Access',      'icon' => $icons['clock']],
            'admin_sessions'    => ['section' => 'System & Access',      'icon' => $icons['shield']],
            'sessions'          => ['section' => 'System & Access',      'icon' => $icons['shield']],
            'settings'          => ['section' => 'System & Access',      'icon' => $icons['settings']],
        ];

        if (isset($map[$key])) {
            return $map[$key];
        }
        if (str_contains($t, 'discount') || str_contains($t, 'payment') || str_contains($t, 'refund')) {
            return ['section' => 'Finance', 'icon' => $icons['wallet']];
        }
        if (str_contains($t, 'review') || str_contains($t, 'inquir')) {
            return ['section' => 'Guest Feedback', 'icon' => $icons['chat']];
        }
        if (str_contains($t, 'reservation') || str_contains($t, 'check-in') || str_contains($t, 'check-out')) {
            return ['section' => 'Front Desk', 'icon' => $icons['calendar']];
        }
        if (str_contains($t, 'setting')) {
            return ['section' => 'System & Access', 'icon' => $icons['settings']];
        }
        if (str_contains($t, 'session') || str_contains($t, 'log')) {
            return ['section' => 'System & Access', 'icon' => $icons['shield']];
        }

        return [
            'section' => $isAdmin ? 'Resort Operations' : 'Front Desk',
            'icon'    => $icons['grid'],
        ];
    }
}
$_ph_active_key = $active_page ?? basename($_SERVER['PHP_SELF'] ?? '', '.php');
$_ph_context = sf_header_context($_ph_active_key, $_ph_title, $_ph_is_admin);
$_ph_portal_badge = $_ph_is_admin ? 'Admin' : 'Front Desk';
?>
<link rel="stylesheet" href="<?php echo $base_url ?? ''; ?>assets/css/tailwind.css?v=<?php echo (int) @filemtime(__DIR__ . '/../assets/css/tailwind.css'); ?>">
<link rel="stylesheet" href="<?php echo $base_url ?? ''; ?>assets/css/page-transitions.css?v=<?php echo (int) @filemtime(__DIR__ . '/../assets/css/page-transitions.css'); ?>">
<script src="<?php echo $base_url ?? ''; ?>assets/js/page-transitions.js?v=<?php echo (int) @filemtime(__DIR__ . '/../assets/js/page-transitions.js'); ?>" defer></script>
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

/* Luxury Coastal Workspace Header (All Admin & Reception Pages) */
header.page-header[data-role] {
    position: sticky;
    top: 0;
    z-index: 95;
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    row-gap: 12px;
    flex-wrap: wrap;
    padding: 14px 32px;
    background: transparent !important;
    backdrop-filter: none !important;
    -webkit-backdrop-filter: none !important;
    border-bottom: 1px solid var(--sb-border, #E5E9F0) !important;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03), 0 12px 28px -18px rgba(132, 86, 60, 0.22) !important;
}
header.page-header[data-role]::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 2.5px;
    z-index: 2;
    background: linear-gradient(90deg, #84563C 0%, #C49574 44%, rgba(212, 163, 115, 0.14) 100%);
    pointer-events: none;
}
header.page-header[data-role]::after {
    content: '';
    position: absolute;
    inset: 0;
    z-index: -1;
    background: linear-gradient(108deg, rgba(255, 255, 255, 0.96) 0%, rgba(255, 255, 255, 0.94) 58%, rgba(248, 241, 235, 0.93) 100%);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    pointer-events: none;
}
[data-theme="dark"] header.page-header[data-role] {
    border-bottom-color: var(--sb-border, #1E293B) !important;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.34), 0 14px 28px -18px rgba(0, 0, 0, 0.7) !important;
}
[data-theme="dark"] header.page-header[data-role]::after {
    background: linear-gradient(108deg, rgba(15, 20, 34, 0.95) 0%, rgba(18, 25, 42, 0.94) 60%, rgba(36, 27, 29, 0.92) 100%);
}
main > header.page-header[data-role] + :not(.admin-body):not(.stay-operations-tabs-wrap):not(script):not(style) {
    margin-top: 22px;
}

header.page-header[data-role] .page-header-left {
    display: flex;
    align-items: center;
    gap: 14px;
    min-width: 0;
    flex: 1 1 180px;
}

.sidebar-toggle-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    border: 1px solid var(--border, #E2E8F0);
    background: var(--bg-surface-elev, #ffffff);
    color: var(--text-main, #0F172A);
    cursor: pointer;
    border-radius: 11px;
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

.page-header-icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    color: #84563C;
    background: linear-gradient(145deg, #FBF6F1 0%, #F1E2D5 100%);
    border: 1px solid rgba(132, 86, 60, 0.2);
    box-shadow: 0 3px 10px -4px rgba(132, 86, 60, 0.26), inset 0 1px 0 rgba(255, 255, 255, 0.85);
}
[data-theme="dark"] .page-header-icon {
    color: #E2B892;
    background: linear-gradient(145deg, rgba(132, 86, 60, 0.24) 0%, rgba(132, 86, 60, 0.1) 100%);
    border-color: rgba(212, 163, 115, 0.24);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08);
}

.page-header-titles {
    display: flex;
    flex-direction: column;
    min-width: 0;
    flex: 1;
}

.page-header-eyebrow {
    display: flex;
    align-items: center;
    gap: 6px;
    font-family: var(--font-heading, 'Outfit', sans-serif);
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--text-muted, #64748B);
    line-height: 1.2;
    margin-bottom: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.page-header-portal-chip {
    display: inline-flex;
    align-items: center;
    padding: 1.5px 7px;
    border-radius: 999px;
    background: rgba(132, 86, 60, 0.1);
    color: var(--primary, #84563C);
    font-weight: 800;
    flex-shrink: 0;
}
[data-theme="dark"] .page-header-portal-chip {
    background: rgba(212, 163, 115, 0.16);
    color: #E2B892;
}
.page-header-eyebrow-sep {
    opacity: 0.45;
}

header.page-header[data-role] .page-header-title {
    font-family: var(--font-heading, 'Outfit', sans-serif);
    font-size: clamp(17.5px, 1.35vw, 20.5px);
    font-weight: 800;
    color: var(--text-main, #0F172A);
    letter-spacing: -0.025em;
    line-height: 1.2;
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

header.page-header[data-role] .page-header-subtitle {
    font-size: 12.5px;
    font-weight: 500;
    color: var(--text-muted, #64748B);
    margin: 2px 0 0;
    line-height: 1.35;
    white-space: normal;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

header.page-header[data-role] .page-header-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
    flex-wrap: nowrap;
    margin-left: auto;
}

header.page-header[data-role] .admin-search-wrapper,
header.page-header[data-role] .search-wrapper {
    position: relative;
    display: flex;
    align-items: center;
    order: 1;
}

header.page-header[data-role] .admin-search-input,
header.page-header[data-role] .search-input {
    background-color: var(--bg-surface-elev, #ffffff);
    border: 1px solid var(--border, #E2E8F0);
    border-radius: 999px;
    padding: 8px 16px 8px 36px;
    font-family: inherit;
    font-size: 13px;
    width: 210px;
    height: 38px;
    outline: none;
    color: var(--text-main, #0F172A);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    transition: width 0.25s ease, border-color 0.2s ease, box-shadow 0.2s ease;
}

header.page-header[data-role] .admin-search-input:focus,
header.page-header[data-role] .search-input:focus {
    width: 250px;
    border-color: var(--primary, #84563C);
    box-shadow: 0 0 0 3px rgba(132, 86, 60, 0.14);
}

header.page-header[data-role] .header-resort-clock {
    order: 2;
}

header.page-header[data-role] .btn-primary,
header.page-header[data-role] .btn-new-res-top {
    order: 3;
    height: 38px;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    border: 0;
    border-radius: 10px;
    padding: 0 15px;
    font-size: 13px;
    font-weight: 600;
    white-space: nowrap;
    text-decoration: none;
    background: linear-gradient(135deg, #84563C 0%, #9E6C50 100%);
    color: #FFFFFF;
    box-shadow: 0 4px 12px -3px rgba(132, 86, 60, 0.36), inset 0 1px 0 rgba(255, 255, 255, 0.18);
    transition: transform 0.15s ease, box-shadow 0.2s ease;
}
header.page-header[data-role] .btn-primary:hover,
header.page-header[data-role] .btn-new-res-top:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 16px -3px rgba(132, 86, 60, 0.44);
}

header.page-header[data-role] .header-notif-menu {
    order: 4;
    margin-left: 6px;
}

header.page-header[data-role] .header-notif-menu::before {
    content: '';
    position: absolute;
    left: -8px;
    top: 50%;
    transform: translateY(-50%);
    width: 1px;
    height: 22px;
    background: var(--border, #E2E8F0);
    pointer-events: none;
}

header.page-header[data-role] .header-user-menu {
    order: 5;
}

/* Redesigned Resort Clock */
.header-resort-clock {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    height: 38px;
    background: rgba(248, 250, 252, 0.92);
    border: 1px solid var(--border, #E2E8F0);
    padding: 0 13px;
    border-radius: 999px;
    font-size: 12.5px;
    color: var(--text-main, #0F172A);
    font-weight: 600;
    user-select: none;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    white-space: nowrap;
}
[data-theme="dark"] .header-resort-clock {
    background: var(--bg-surface-elev, #161E31);
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
    font-size: 12.5px;
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
[data-theme="dark"] .resort-clock-badge {
    background: rgba(212, 163, 115, 0.16);
    color: #E2B892;
}

/* Icon Buttons (Notifications, etc) */
.header-icon-btn {
    width: 38px;
    height: 38px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--bg-surface-elev, #ffffff);
    border: 1px solid var(--border, #E2E8F0);
    border-radius: 11px;
    color: var(--text-muted, #64748B);
    cursor: pointer;
    position: relative;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}
.header-icon-btn:hover {
    background: rgba(132, 86, 60, 0.06);
    color: var(--primary, #84563C);
    border-color: rgba(132, 86, 60, 0.35);
    transform: translateY(-1px);
}
[data-theme="dark"] .header-icon-btn {
    background: #161E31;
    border-color: #1E293B;
    color: #94A3B8;
}
[data-theme="dark"] .header-icon-btn:hover {
    background: rgba(255, 255, 255, 0.06);
    color: #E2B892;
    border-color: rgba(212, 163, 115, 0.35);
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
    font-size: 10px;
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
    height: 38px;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 4px 11px 4px 5px;
    background: rgba(248, 250, 252, 0.92);
    border: 1px solid var(--border, #E2E8F0);
    border-radius: 999px;
    cursor: pointer;
    font-family: 'Outfit', sans-serif;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}
.header-user-trigger:hover {
    background: #FBF6F1;
    border-color: rgba(132, 86, 60, 0.35);
}
[data-theme="dark"] .header-user-trigger {
    background: #161E31;
    border-color: #1E293B;
}
[data-theme="dark"] .header-user-trigger:hover {
    background: rgba(132, 86, 60, 0.18);
    border-color: rgba(212, 163, 115, 0.35);
}
.header-user-avatar {
    width: 28px;
    height: 28px;
    background: linear-gradient(135deg, #84563C 0%, #A37152 100%);
    color: #ffffff;
    font-weight: 700;
    font-size: 12px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.header-user-name {
    font-size: 12.5px;
    font-weight: 600;
    color: var(--text-main, #0F172A);
    max-width: 132px;
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
[data-theme="dark"] .header-user-dropdown a.danger:hover {
    background: rgba(220, 38, 38, 0.14);
}

@media (max-width: 1380px) {
    header.page-header[data-role] {
        padding: 13px 24px;
        gap: 12px;
    }
    header.page-header[data-role] .page-header-actions {
        gap: 8px;
    }
    header.page-header[data-role] .admin-search-input,
    header.page-header[data-role] .search-input {
        width: 168px;
    }
    header.page-header[data-role] .admin-search-input:focus,
    header.page-header[data-role] .search-input:focus {
        width: 205px;
    }
    header.page-header[data-role] .header-resort-clock {
        padding: 0 11px;
        gap: 7px;
    }
    header.page-header[data-role] .resort-clock-date {
        display: none;
    }
    header.page-header[data-role] .header-user-name {
        max-width: 96px;
    }
}
@media (max-width: 1024px) {
    header.page-header[data-role] {
        padding: 12px 18px !important;
        z-index: 95;
    }
    .page-header-icon {
        width: 38px;
        height: 38px;
    }
    .header-resort-clock .resort-clock-date {
        display: none;
    }
}
@media (max-width: 768px) {
    header.page-header[data-role] .page-header-subtitle {
        display: none;
    }
    .header-resort-clock {
        display: none;
    }
    .header-user-name {
        display: none;
    }
    header.page-header[data-role] .header-user-trigger {
        padding: 4px 8px 4px 5px;
        gap: 4px;
    }
    header.page-header[data-role] .header-notif-menu {
        margin-left: 0;
    }
    header.page-header[data-role] .header-notif-menu::before {
        display: none;
    }
}
@media (max-width: 640px) {
    .page-header-eyebrow {
        display: none;
    }
    header.page-header[data-role] .page-header-title {
        white-space: normal;
        font-size: 17.5px;
    }
}
@media print {
    header.page-header[data-role] {
        position: static;
        box-shadow: none !important;
    }
    header.page-header[data-role]::after {
        display: none;
    }
    header.page-header[data-role] .page-header-actions {
        display: none !important;
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
                            elseif ($time_diff < 3600) { $time_str = floor($time_diff/60) . 'm ago'; }
                            elseif ($time_diff < 86400) { $time_str = floor($time_diff/3600) . 'h ago'; }
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
    var title = String(data.title || '').toLowerCase();
    var message = String(data.message || '').toLowerCase();
    var type = String(data.type || '').toLowerCase();
    var context = title + ' ' + message + ' ' + type;
    var bookingId = parseInt(data.booking_id, 10) || 0;
    var bookingRef = bookingId > 0 ? 'REF-' + String(bookingId).padStart(3, '0') : '';
    var query = bookingId > 0 ? encodeURIComponent(bookingRef) : '';
    var adminReservations = <?php echo json_encode($_ph_is_admin ? 'admin_reservations' : 'reservations'); ?>;
    var checkinPage = 'admin_checkin';
    var checkoutPage = <?php echo json_encode($_ph_is_admin ? 'admin_checkout' : 'checkout'); ?>;

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
    if (data.booking_id && parseInt(data.booking_id) > 0) {
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
</script>

<!-- Inactivity Auto-Logout Timer (15-min timeout with 60-sec warning modal) -->
<script src="assets/js/inactivity-timer.js" defer></script>
