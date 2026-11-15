<?php
/**
 * page_header_helper.php — Shared header context, profile avatar lookup, and recent notifications state.
 */

if (!function_exists('sf_header_context')) {
    /**
     * Maps the active admin/reception route or title to a clean section label and crisp SVG icon.
     */
    function sf_header_context($activePage, $title, $isAdmin)
    {
        $key = strtolower(trim((string) $activePage));
        $t   = strtolower(trim((string) $title));
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
            'dashboard'         => ['section' => 'Overview',           'icon' => $icons['grid']],
            'reservations'      => ['section' => 'Front Desk',         'icon' => $icons['calendar']],
            'checkin'           => ['section' => 'Front Desk',         'icon' => $icons['checkin']],
            'checkout'          => ['section' => 'Front Desk',         'icon' => $icons['checkin']],
            'guests'            => ['section' => 'Property & Guests',  'icon' => $icons['users']],
            'accommodations'    => ['section' => 'Property & Guests',  'icon' => $icons['home']],
            'calendar'          => ['section' => 'Property & Guests',  'icon' => $icons['calendar']],
            'promotions'        => ['section' => 'Property & Guests',  'icon' => $icons['tag']],
            'gallery'           => ['section' => 'Property & Guests',  'icon' => $icons['gallery']],
            'room_photos'       => ['section' => 'Property & Guests',  'icon' => $icons['home']],
            'chatbot'           => ['section' => 'AI Concierge',       'icon' => $icons['chat']],
            'discount_requests' => ['section' => 'Finance',            'icon' => $icons['tag']],
            'payments'          => ['section' => 'Finance',            'icon' => $icons['wallet']],
            'admin_refunds'     => ['section' => 'Finance',            'icon' => $icons['wallet']],
            'reports'           => ['section' => 'Insights & Updates', 'icon' => $icons['chart']],
            'notifications'     => ['section' => 'Insights & Updates', 'icon' => $icons['bell']],
            'inquiries'         => ['section' => 'Guest Feedback',     'icon' => $icons['chat']],
            'live_support'      => ['section' => 'Live Support',       'icon' => $icons['chat']],
            'reviews'           => ['section' => 'Guest Feedback',     'icon' => $icons['chat']],
            'staff'             => ['section' => 'System & Access',    'icon' => $icons['users']],
            'logs'              => ['section' => 'System & Access',    'icon' => $icons['shield']],
            'backups'           => ['section' => 'System & Access',    'icon' => $icons['backup']],
            'automation'        => ['section' => 'System & Access',    'icon' => $icons['clock']],
            'admin_sessions'    => ['section' => 'System & Access',    'icon' => $icons['shield']],
            'sessions'          => ['section' => 'System & Access',    'icon' => $icons['shield']],
            'settings'          => ['section' => 'System & Access',    'icon' => $icons['settings']],
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

if (!function_exists('sfbc_resolve_page_header_state')) {
    function sfbc_resolve_page_header_state(?mysqli $conn, string $title, string $subtitle, ?string $activePage): array
    {
        $username          = $_SESSION['admin_username'] ?? '';
        $role              = $_SESSION['admin_role'] ?? 'receptionist';
        $isAdmin           = ($role === 'admin');
        $notificationsHref = $isAdmin ? 'admin_notifications' : 'notifications';
        $roleLabel         = $isAdmin ? 'Administrator' : 'Receptionist';
        $photo             = $_SESSION['admin_profile_photo'] ?? null;

        if (empty($photo) && $conn !== null && !empty($username)) {
            $tbl = ($role === 'admin') ? 'administrators' : 'receptionists';
            if ($st = $conn->prepare("SELECT profile_photo FROM `{$tbl}` WHERE username = ? LIMIT 1")) {
                $st->bind_param("s", $username);
                $st->execute();
                $rowP = $st->get_result()->fetch_assoc();
                $st->close();
                if (!empty($rowP['profile_photo'])) {
                    $photo = $rowP['profile_photo'];
                    $_SESSION['admin_profile_photo'] = $photo;
                }
            }
        }

        $unread = 0;
        $recentNotifs = [];
        if ($conn !== null) {
            if ($notifRowRes = $conn->query("SELECT COUNT(*) as unread FROM notifications WHERE is_read = 0")) {
                $notifRow = $notifRowRes->fetch_assoc();
                $unread = $notifRow ? (int) $notifRow['unread'] : 0;
            }
            if ($notifsRes = $conn->query("SELECT id, title, message, type, is_read, booking_id, created_at FROM notifications ORDER BY id DESC LIMIT 6")) {
                while ($nr = $notifsRes->fetch_assoc()) {
                    $recentNotifs[] = $nr;
                }
            }
        }

        $csrfToken   = function_exists('get_csrf_token') ? get_csrf_token() : ($_SESSION['csrf_token'] ?? '');
        $activeKey   = $activePage ?? basename($_SERVER['PHP_SELF'] ?? '', '.php');
        $context     = sf_header_context($activeKey, $title, $isAdmin);
        $portalBadge = $isAdmin ? 'Admin' : 'Front Desk';

        return [
            'title'              => $title,
            'subtitle'           => $subtitle,
            'username'           => $username,
            'role'               => $role,
            'is_admin'           => $isAdmin,
            'notifications_href' => $notificationsHref,
            'role_label'         => $roleLabel,
            'photo'              => $photo,
            'unread'             => $unread,
            'csrf_token'         => $csrfToken,
            'recent_notifs'      => $recentNotifs,
            'context'            => $context,
            'portal_badge'       => $portalBadge,
        ];
    }
}
