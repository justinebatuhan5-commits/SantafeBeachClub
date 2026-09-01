<?php
/**
 * _sidebar.php — Role-aware luxury sidebar component for Admin & Reception.
 */
$_role      = $_SESSION['admin_role'] ?? 'receptionist';
$_user      = $_SESSION['admin_username'] ?? '';
$_page      = $active_page ?? '';
$_sidebar_photo = $_SESSION['admin_profile_photo'] ?? null;

// Some pages include this sidebar without loading db.php first.
// Load the shared mysqli connection here so role and badge counts always work.
if (!isset($conn)) {
    require_once __DIR__ . '/../../backend/config/db.php';
}

if (!empty($_user) && isset($conn)) {
    if ($roleStmt = $conn->prepare("SELECT role, profile_photo FROM admins WHERE username = ?")) {
        $roleStmt->bind_param("s", $_user);
        $roleStmt->execute();
        $roleResult = $roleStmt->get_result()->fetch_assoc();
        $roleStmt->close();

        if ($roleResult && !empty($roleResult['role'])) {
            $_SESSION['admin_role'] = $roleResult['role'];
            $_role = $roleResult['role'];
        }
        if (!empty($roleResult['profile_photo'])) {
            $_sidebar_photo = $roleResult['profile_photo'];
            $_SESSION['admin_profile_photo'] = $_sidebar_photo;
        }
    }
}

$_is_admin  = ($_role === 'admin');

// Unread notification count
$_unread_count = 0;
if (isset($conn) && $countStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM notifications WHERE is_read = 0")) {
    $countStmt->execute();
    $countResult = $countStmt->get_result()->fetch_assoc();
    $_unread_count = (int)($countResult['cnt'] ?? 0);
    $countStmt->close();
}

// Unread inquiries count
$_unread_inquiries = 0;
if (isset($conn)) {
    $tableCheck = $conn->query("SHOW TABLES LIKE 'inquiries'");
    if ($tableCheck && $tableCheck->num_rows > 0) {
        if ($countInqStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM inquiries WHERE status = 'Unread'")) {
            $countInqStmt->execute();
            $countInqResult = $countInqStmt->get_result()->fetch_assoc();
            $_unread_inquiries = (int)($countInqResult['cnt'] ?? 0);
            $countInqStmt->close();
        }
    }
}

// Pending bookings count
$_pending_bookings = 0;
if (isset($conn) && $countBkgStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM bookings WHERE status = 'Pending'")) {
    $countBkgStmt->execute();
    $countBkgResult = $countBkgStmt->get_result()->fetch_assoc();
    $_pending_bookings = (int)($countBkgResult['cnt'] ?? 0);
    $countBkgStmt->close();
}

// Pending / Unverified payments count
$_pending_payments = 0;
if (isset($conn)) {
    $tableCheckPay = $conn->query("SHOW TABLES LIKE 'payments'");
    if ($tableCheckPay && $tableCheckPay->num_rows > 0) {
        if ($countPayStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM payments WHERE LOWER(status) = 'pending'")) {
            $countPayStmt->execute();
            $countPayResult = $countPayStmt->get_result()->fetch_assoc();
            $_pending_payments = (int)($countPayResult['cnt'] ?? 0);
            $countPayStmt->close();
        }
    }
}

// Helper: returns 'active' css class if page matches
function _sb_active($page, $current) {
    return $page === $current ? 'active' : '';
}

// Helper: returns badge HTML span for unread count
function _sb_badge($count, $type = '') {
    if ($count <= 0) return '';
    $display = $count > 99 ? '99+' : (string)$count;
    $typeAttr = $type !== '' ? ' data-badge-type="' . htmlspecialchars($type) . '"' : '';
    return '<span class="sidebar-badge"' . $typeAttr . '>' . htmlspecialchars($display) . '</span>';
}
?>



<script>
(function() {
    try {
        if (localStorage.getItem('sbc_sidebar_collapsed') === 'true' && window.innerWidth > 1024) {
            document.documentElement.classList.add('sbc-collapsed');
        }
    } catch(e) {}
})();
</script>

<?php if ($_is_admin): ?>
<!-- ═══════════════ ADMIN SIDEBAR ═══════════════ -->
<aside class="admin-sidebar" id="appSidebar">
    <div class="sidebar-brand">
        <a href="admin_dashboard" style="text-decoration:none; display:flex; align-items:center; gap:12px; min-width:0;" title="Dashboard">
            <img src="assets/logo.jpg" alt="Logo" class="brand-circle">
            <div class="brand-text">
                <h2>Santa Fe</h2>
                <p>Admin Command</p>
            </div>
        </a>
        <button class="dark-mode-btn" title="Toggle Theme" aria-label="Toggle Theme" style="margin-left:auto;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        </button>
    </div>

    <!-- Sidebar Search -->
    <div class="sidebar-search-wrap">
        <div class="sidebar-search-inner">
            <svg class="sidebar-search-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" class="sidebar-search-input" id="sidebarSearchInput" placeholder="Search" autocomplete="off">
        </div>
    </div>

    <div class="sidebar-scroll-body">
        <p class="sidebar-section-label">Overview</p>
        <ul class="sidebar-nav">
            <li><a href="admin_dashboard" class="sidebar-link <?php echo _sb_active('dashboard',$_page); ?>" title="Dashboard">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>
                Dashboard
            </a></li>
            <li><a href="admin_reports" class="sidebar-link <?php echo _sb_active('reports',$_page); ?>" title="Reports & Analytics">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                Reports & Analytics
            </a></li>
            <li><a href="admin_notifications" class="sidebar-link <?php echo _sb_active('notifications',$_page); ?>" title="Notifications">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                Notifications
                <?php echo _sb_badge($_unread_count, 'notifications'); ?>
            </a></li>
        </ul>

        <p class="sidebar-section-label">Reservations & Desk</p>
        <ul class="sidebar-nav">
            <li><a href="admin_reservations" class="sidebar-link <?php echo _sb_active('reservations',$_page); ?>" title="Reservations">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Reservations
            </a></li>
            <li><a href="admin_checkin" class="sidebar-link <?php echo _sb_active('checkin',$_page); ?>" title="Check-in">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                Check-in
            </a></li>
            <li><a href="admin_checkout" class="sidebar-link <?php echo _sb_active('checkout',$_page); ?>" title="Check-out">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                Check-out
            </a></li>
            <li><a href="javascript:void(0)" onclick="launchDesktopScanner()" class="sidebar-link" title="Launch QR Scanner" style="color:#0284c7; font-weight:600;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="12" cy="12" r="3"/><line x1="12" y1="9" x2="12" y2="15"/><line x1="9" y1="12" x2="15" y2="12"/></svg>
                Launch QR Scanner
            </a></li>
        </ul>

        <p class="sidebar-section-label">Operations</p>
        <ul class="sidebar-nav">
            <li><a href="guests" class="sidebar-link <?php echo _sb_active('guests',$_page); ?>" title="Customers & Guests">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Customers & Guests
            </a></li>
            <li><a href="payments" class="sidebar-link <?php echo _sb_active('payments',$_page); ?>" title="Payments">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><line x1="12" y1="10" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                Payments
                <?php echo _sb_badge($_pending_payments, 'payments'); ?>
            </a></li>
            <li><a href="accommodations" class="sidebar-link <?php echo _sb_active('accommodations',$_page); ?>" title="Accommodations">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                Accommodations
            </a></li>
            <li><a href="admin_calendar" class="sidebar-link <?php echo _sb_active('calendar',$_page); ?>" title="Availability Calendar">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><rect x="7" y="14" width="3" height="3" rx="0.5"/><rect x="14" y="14" width="3" height="3" rx="0.5"/></svg>
                Availability Calendar
            </a></li>
        </ul>

        <p class="sidebar-section-label">Administration</p>
        <ul class="sidebar-nav">
            <li><a href="admin_staff" class="sidebar-link <?php echo _sb_active('staff',$_page); ?>" title="Staff Management">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Staff Management
            </a></li>
            <li><a href="admin_promotions" class="sidebar-link <?php echo _sb_active('promotions',$_page); ?>" title="Promotions">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                Promotions & Coupons
            </a></li>
            <li><a href="admin_gallery" class="sidebar-link <?php echo _sb_active('gallery',$_page); ?>" title="Gallery">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                Gallery
            </a></li>
            <li><a href="admin_room_types" class="sidebar-link <?php echo _sb_active('room_photos',$_page); ?>" title="Room Types & Photos">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><rect x="9" y="14" width="6" height="8"/><circle cx="12" cy="7" r="1.5"/></svg>
                Room Types & Photos
            </a></li>
            <li><a href="admin_logs" class="sidebar-link <?php echo _sb_active('logs',$_page); ?>" title="Activity Logs">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                Activity Logs
            </a></li>

            <li><a href="admin_inquiries" class="sidebar-link <?php echo _sb_active('inquiries',$_page); ?>" title="Inquiries">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                Inquiries
                <?php echo _sb_badge($_unread_inquiries, 'inquiries'); ?>
            </a></li>
        </ul>
    </div>

    <div class="sidebar-bottom">
        <div class="user-pill" title="<?php echo htmlspecialchars($_user !== '' ? $_user : 'Admin'); ?>">
            <?php if (!empty($_sidebar_photo) && file_exists(__DIR__ . '/../' . $_sidebar_photo)): ?>
                <img src="<?php echo htmlspecialchars($_sidebar_photo); ?>" alt="Avatar" class="user-avatar" style="object-fit:cover; border:1px solid var(--border);">
            <?php else: ?>
                <div class="user-avatar"><?php echo strtoupper(substr($_user !== '' ? $_user : 'A', 0, 1)); ?></div>
            <?php endif; ?>
            <div style="min-width:0;">
                <div class="user-info-text"><?php echo htmlspecialchars($_user !== '' ? $_user : 'Admin'); ?></div>
                <div class="user-info-role">Administrator</div>
            </div>
        </div>
    </div>
</aside>

<?php else: ?>
<!-- ═══════════════ RECEPTION SIDEBAR ═══════════════ -->
<aside class="sidebar" id="appSidebar">
    <div class="sidebar-logo">
        <a href="dashboard" style="text-decoration:none; display:flex; align-items:center; gap:12px; min-width:0;" title="Dashboard">
            <img src="assets/logo.jpg" alt="Logo" class="logo-circle">
            <div class="logo-text-group">
                <h2>Santa Fe</h2>
                <p>Reception Desk</p>
            </div>
        </a>
        <button class="dark-mode-btn" title="Toggle Theme" aria-label="Toggle Theme" style="margin-left:auto;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        </button>
    </div>

    <!-- Sidebar Search -->
    <div class="sidebar-search-wrap">
        <div class="sidebar-search-inner">
            <svg class="sidebar-search-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" class="sidebar-search-input" id="sidebarSearchInputReception" placeholder="Search" autocomplete="off">
        </div>
    </div>

    <div class="sidebar-scroll-body">
        <p class="sidebar-section-label">Front Desk</p>
        <ul class="nav-links">
            <li><a href="dashboard" class="nav-item <?php echo _sb_active('dashboard',$_page); ?>" title="Dashboard">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"></rect><rect x="14" y="3" width="7" height="5"></rect><rect x="14" y="12" width="7" height="9"></rect><rect x="3" y="16" width="7" height="5"></rect></svg>
                Console Dashboard
            </a></li>
            <li><a href="notifications" class="nav-item <?php echo _sb_active('notifications',$_page); ?>" title="Notifications">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                Notifications
                <?php echo _sb_badge($_unread_count, 'notifications'); ?>
            </a></li>
            <li><a href="reservations" class="nav-item <?php echo _sb_active('reservations',$_page); ?>" title="Reservations">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                Reservations
            </a></li>
            <li><a href="checkin" class="nav-item <?php echo _sb_active('checkin',$_page); ?>" title="Check-in">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path><polyline points="10 17 15 12 10 7"></polyline><line x1="15" y1="12" x2="3" y2="12"></line></svg>
                Express Check-in
            </a></li>
            <li><a href="checkout" class="nav-item <?php echo _sb_active('checkout',$_page); ?>" title="Check-out">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                Check-out
            </a></li>
            <li><a href="javascript:void(0)" onclick="launchDesktopScanner()" class="nav-item" title="Launch QR Scanner" style="color:#0284c7; font-weight:600;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="12" cy="12" r="3"/><line x1="12" y1="9" x2="12" y2="15"/><line x1="9" y1="12" x2="15" y2="12"/></svg>
                Launch QR Scanner
            </a></li>
        </ul>

        <p class="sidebar-section-label">Operations</p>
        <ul class="nav-links">
            <li><a href="admin_calendar" class="nav-item <?php echo _sb_active('calendar',$_page); ?>" title="Room Calendar">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line><rect x="7" y="14" width="3" height="3" rx="0.5"></rect><rect x="14" y="14" width="3" height="3" rx="0.5"></rect></svg>
                Room Calendar
            </a></li>
            <li><a href="guests" class="nav-item <?php echo _sb_active('guests',$_page); ?>" title="Guest Directory">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                Guest Directory
            </a></li>
            <li><a href="payments" class="nav-item <?php echo _sb_active('payments',$_page); ?>" title="Payments & Billing">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2" ry="2"></rect><line x1="12" y1="10" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg>
                Payments & Billing
                <?php echo _sb_badge($_pending_payments, 'payments'); ?>
            </a></li>

            <li><a href="admin_inquiries" class="nav-item <?php echo _sb_active('inquiries',$_page); ?>" title="Guest Inquiries">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                Guest Inquiries
                <?php echo _sb_badge($_unread_inquiries, 'inquiries'); ?>
            </a></li>
        </ul>
    </div>

    <div class="sidebar-bottom">
        <div class="user-pill" title="<?php echo htmlspecialchars($_user !== '' ? $_user : 'Front Desk'); ?>">
            <?php if (!empty($_sidebar_photo) && file_exists(__DIR__ . '/../' . $_sidebar_photo)): ?>
                <img src="<?php echo htmlspecialchars($_sidebar_photo); ?>" alt="Avatar" class="user-avatar" style="object-fit:cover; border:1px solid var(--border);">
            <?php else: ?>
                <div class="user-avatar"><?php echo strtoupper(substr($_user !== '' ? $_user : 'R', 0, 1)); ?></div>
            <?php endif; ?>
            <div style="min-width:0;">
                <div class="user-info-text"><?php echo htmlspecialchars($_user !== '' ? $_user : 'Front Desk'); ?></div>
                <div class="user-info-role">Receptionist</div>
            </div>
        </div>
    </div>
</aside>
<?php endif; ?>

<?php if ($_is_admin): ?>
<script>
// Switch body wrapper class so admin.css layout applies consistently on shared pages
document.addEventListener('DOMContentLoaded', function() {
    var mc = document.querySelector('.main-content');
    if (mc && !mc.classList.contains('admin-main')) { 
        mc.classList.remove('main-content'); 
        mc.classList.add('admin-main'); 
        
        if (!mc.querySelector('.admin-body')) {
            var wrapper = document.createElement('div');
            wrapper.className = 'admin-body';
            while (mc.firstChild) {
                wrapper.appendChild(mc.firstChild);
            }
            mc.appendChild(wrapper);
        }
    }
});
</script>
<?php endif; ?>

<script src="assets/js/dark-mode-toggle.js?v=4"></script>
<script src="assets/js/sidebar-toggle.js?v=4"></script>
<script src="assets/js/security.js?v=4"></script>
<script>
// Sidebar search filter: filters nav links in real-time
(function() {
    function initSidebarSearch(inputId) {
        var input = document.getElementById(inputId);
        if (!input) return;
        var sidebar = input.closest('.admin-sidebar, .sidebar');
        if (!sidebar) return;

        input.addEventListener('input', function() {
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
</script>


<!-- ══════════════════════════════════════════════════
     GLOBAL CUSTOM CONFIRMATION DIALOG
══════════════════════════════════════════════════ -->
<div id="sfbc-confirm-overlay" style="
    display: none;
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.55);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    z-index: 99999;
    align-items: center;
    justify-content: center;
    animation: sfbcFadeIn 0.15s ease-out;
">
    <div style="
        background: #fff;
        border-radius: 20px;
        padding: 32px 28px 24px;
        max-width: 400px;
        width: calc(100% - 40px);
        box-shadow: 0 24px 60px rgba(0,0,0,0.18);
        text-align: center;
        animation: sfbcSlideUp 0.2s cubic-bezier(0.16,1,0.3,1);
        position: relative;
    ">
        <div id="sfbc-confirm-icon" style="
            width: 60px; height: 60px;
            border-radius: 50%;
            background: #FEF3C7;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 18px;
            font-size: 28px;
        ">⚠️</div>
        <h3 id="sfbc-confirm-title" style="margin: 0 0 8px; font-size: 18px; font-weight: 800; color: #111827;"></h3>
        <p id="sfbc-confirm-message" style="margin: 0 0 24px; font-size: 14px; color: #6B7280; line-height: 1.6;"></p>
        <div style="display: flex; gap: 12px;">
            <button id="sfbc-confirm-cancel" style="
                flex: 1; padding: 12px;
                background: #F3F4F6; color: #374151;
                border: none; border-radius: 10px;
                font-size: 14px; font-weight: 600;
                cursor: pointer; transition: background 0.15s;
            " onmouseover="this.style.background='#E5E7EB'" onmouseout="this.style.background='#F3F4F6'">
                Cancel
            </button>
            <button id="sfbc-confirm-ok" style="
                flex: 1; padding: 12px;
                background: #DC2626; color: #fff;
                border: none; border-radius: 10px;
                font-size: 14px; font-weight: 700;
                cursor: pointer; transition: background 0.15s;
            " onmouseover="this.style.background='#B91C1C'" onmouseout="this.style.background='#DC2626'">
                Confirm
            </button>
        </div>
    </div>
</div>
<style>
@keyframes sfbcFadeIn { from { opacity:0; } to { opacity:1; } }
@keyframes sfbcSlideUp { from { opacity:0; transform:translateY(16px); } to { opacity:1; transform:translateY(0); } }
</style>
<script>
/**
 * showConfirm(options) — Custom styled confirmation dialog.
 * Replaces native browser confirm().
 *
 * Usage:
 *   showConfirm({ title: 'Delete?', message: 'This cannot be undone.', onConfirm: () => form.submit() });
 *
 * Options:
 *   title      {string}   — Bold heading
 *   message    {string}   — Body text
 *   icon       {string}   — Emoji icon (default: ⚠️)
 *   iconBg     {string}   — Icon background color (default: #FEF3C7)
 *   confirmText{string}   — Confirm button label (default: 'Confirm')
 *   confirmColor{string}  — Confirm button color (default: #DC2626 red)
 *   onConfirm  {function} — Called when user clicks Confirm
 */
function showConfirm(opts) {
    var overlay  = document.getElementById('sfbc-confirm-overlay');
    var icon     = document.getElementById('sfbc-confirm-icon');
    var title    = document.getElementById('sfbc-confirm-title');
    var message  = document.getElementById('sfbc-confirm-message');
    var okBtn    = document.getElementById('sfbc-confirm-ok');
    var cancelBtn = document.getElementById('sfbc-confirm-cancel');

    icon.textContent        = opts.icon     || '⚠️';
    icon.style.background   = opts.iconBg   || '#FEF3C7';
    title.textContent       = opts.title    || 'Are you sure?';
    message.textContent     = opts.message  || 'This action cannot be undone.';
    okBtn.textContent       = opts.confirmText  || 'Confirm';
    okBtn.style.background  = opts.confirmColor || '#DC2626';
    okBtn.onmouseout  = function(){ this.style.background = opts.confirmColor || '#DC2626'; };
    okBtn.onmouseover = function(){ this.style.background = darkenHex(opts.confirmColor || '#DC2626'); };

    overlay.style.display = 'flex';

    // Clone buttons to remove stale listeners
    var newOk = okBtn.cloneNode(true);
    okBtn.parentNode.replaceChild(newOk, okBtn);
    newOk.textContent      = opts.confirmText  || 'Confirm';
    newOk.style.background = opts.confirmColor || '#DC2626';
    newOk.onmouseover = function(){ this.style.background = darkenHex(opts.confirmColor || '#DC2626'); };
    newOk.onmouseout  = function(){ this.style.background = opts.confirmColor || '#DC2626'; };
    newOk.addEventListener('click', function() {
        overlay.style.display = 'none';
        if (typeof opts.onConfirm === 'function') opts.onConfirm();
    });

    var newCancel = cancelBtn.cloneNode(true);
    cancelBtn.parentNode.replaceChild(newCancel, cancelBtn);
    newCancel.addEventListener('click', function() {
        overlay.style.display = 'none';
    });

    overlay.onclick = function(e) {
        if (e.target === overlay) overlay.style.display = 'none';
    };
}

function darkenHex(hex) {
    // Simple darken: reduce each channel by ~20%
    hex = hex.replace('#','');
    if (hex.length === 3) hex = hex[0]+hex[0]+hex[1]+hex[1]+hex[2]+hex[2];
    var r = Math.max(0, parseInt(hex.substr(0,2),16) - 30);
    var g = Math.max(0, parseInt(hex.substr(2,2),16) - 30);
    var b = Math.max(0, parseInt(hex.substr(4,2),16) - 30);
    return '#'+[r,g,b].map(x => x.toString(16).padStart(2,'0')).join('');
}
</script>

<script>
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
            icon:         form.dataset.confirmIcon    || '⚠️',
            iconBg:       form.dataset.confirmIconBg  || '#FEF3C7',
            confirmText:  form.dataset.confirmText    || 'Confirm',
            confirmColor: form.dataset.confirmColor   || '#DC2626',
            onConfirm: function() { form.submit(); }
        });
    });
});
</script>

<script>
// Live-refresh notification badge for both admin and reception sidebars.
(function() {
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

    // Read user preference for desktop notifications (set in Settings → Notifications)
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
            '<div class="sbc-notif-toast-icon">🔔</div>' +
            '<div>' +
                '<div class="sbc-notif-toast-title">New notification</div>' +
                '<div class="sbc-notif-toast-msg">You have 1 new alert.</div>' +
                '<div class="sbc-notif-toast-link">Open notification</div>' +
            '</div>' +
            '<div class="sbc-notif-toast-actions">' +
                '<button type="button" class="sbc-notif-toast-mute" id="sbc-notif-toast-mute">' +
                (soundMuted ? 'Unmute' : 'Mute') +
                '</button>' +
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
            if (e.target.closest('.sbc-notif-toast-mute')) return;
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
        var isAdmin = <?php echo $_is_admin ? 'true' : 'false'; ?>;
        var reservationsPage = isAdmin ? 'admin_reservations' : 'reservations';
        var checkoutPage = isAdmin ? 'admin_checkout' : 'checkout';

        if (bookingId > 0) {
            window.location.href = reservationsPage + '?search=' + encodeURIComponent(bookingId);
        } else if (title.indexOf('check-out') !== -1 || title.indexOf('checkout') !== -1 || message.indexOf('check-out') !== -1) {
            window.location.href = checkoutPage;
        } else if (title.indexOf('payment') !== -1 || message.indexOf('payment') !== -1) {
            window.location.href = 'payments';
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
                } catch(err) {}
            });

            sse.addEventListener('badge_sync', function(e) {
                try {
                    var data = JSON.parse(e.data);
                    var count = Number(data.unread_count) || 0;
                    lastUnreadCount = count;
                    updateNotificationBadge(count);
                } catch(err) {}
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
</script>

<!-- ══════════════════════════════════════════════════
     IN-BROWSER QR SCANNER MODAL (Native getUserMedia + jsQR)
══════════════════════════════════════════════════ -->
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>

<style>
#qr-scanner-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(10, 15, 29, 0.75);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    z-index: 999998;
    align-items: center;
    justify-content: center;
}
#qr-scanner-overlay.open { display: flex; }

#qr-scanner-box {
    background: linear-gradient(180deg, #1E293B 0%, #0F172A 100%);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 28px;
    padding: 28px 24px 22px;
    width: 380px;
    max-width: calc(100vw - 32px);
    box-shadow: 0 30px 80px -15px rgba(0, 0, 0, 0.8), 0 0 1px rgba(255, 255, 255, 0.15);
    position: relative;
    text-align: center;
    animation: qrSlideUp 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes qrSlideUp {
    from { opacity:0; transform:translateY(24px) scale(0.96); }
    to   { opacity:1; transform:translateY(0)    scale(1);    }
}

#qr-scanner-box h3 {
    color: #FFFFFF; font-size: 18px; font-weight: 800;
    margin: 0 0 4px; font-family: 'Outfit', sans-serif;
    display: flex; align-items: center; justify-content: center; gap: 10px;
    letter-spacing: -0.01em;
}
#qr-scanner-box .qr-logo {
    width: 34px; height: 34px; border-radius: 50%;
    object-fit: cover; border: 2px solid #C8996F;
    box-shadow: 0 4px 12px rgba(200, 153, 111, 0.35);
}
#qr-scanner-box .qr-sub {
    color: #94A3B8; font-size: 13px;
    margin: 0 0 18px; font-family: 'Outfit', sans-serif;
}

/* Video container */
#qr-video-wrap {
    position: relative;
    width: 100%;
    border-radius: 18px;
    overflow: hidden;
    background: #000;
    line-height: 0;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
}
#qr-video-wrap::before {
    content: '';
    position: absolute;
    inset: 16px;
    z-index: 2;
    border: 2px solid rgba(200, 153, 111, 0.85);
    border-radius: 16px;
    box-shadow: 0 0 0 9999px rgba(15, 23, 42, 0.35), 0 0 20px rgba(200, 153, 111, 0.25);
    pointer-events: none;
}
#qr-video {
    width: 100%;
    height: auto;
    display: block;
    border-radius: 18px;
    transform: scaleX(-1); /* mirror for front cam feel */
}
#qr-canvas { display: none; }

/* Corner brackets */
.qr-corner {
    position: absolute; width: 36px; height: 36px;
    z-index: 3; pointer-events: none; opacity: 0.9;
}
.qr-corner::before, .qr-corner::after {
    content: ''; position: absolute;
    background: #C8996F; border-radius: 2px;
    box-shadow: 0 0 8px rgba(200, 153, 111, 0.6);
}
.qr-corner::before { width: 3.5px; height: 100%; }
.qr-corner::after  { width: 100%; height: 3.5px; }
.qr-corner.tl { top:14px; left:14px; }
.qr-corner.tl::before { top:0; left:0; }
.qr-corner.tl::after  { top:0; left:0; }
.qr-corner.tr { top:14px; right:14px; transform:scaleX(-1); }
.qr-corner.tr::before { top:0; left:0; }
.qr-corner.tr::after  { top:0; left:0; }
.qr-corner.bl { bottom:14px; left:14px; transform:scaleY(-1); }
.qr-corner.bl::before { top:0; left:0; }
.qr-corner.bl::after  { top:0; left:0; }
.qr-corner.br { bottom:14px; right:14px; transform:scale(-1,-1); }
.qr-corner.br::before { top:0; left:0; }
.qr-corner.br::after  { top:0; left:0; }

/* Scan line */
.qr-scanline {
    position: absolute; left:18px; right:18px; height:2.5px;
    background: linear-gradient(90deg, transparent, #38BDF8 30%, #C8996F 70%, transparent);
    box-shadow: 0 0 12px rgba(200, 153, 111, 0.8), 0 0 6px rgba(56, 189, 248, 0.6);
    z-index: 3; pointer-events: none;
    animation: scanMove 2.2s ease-in-out infinite;
}
@keyframes scanMove {
    0%   { top:18px;           opacity:0; }
    10%  {                     opacity:1; }
    90%  {                     opacity:1; }
    100% { top:calc(100% - 18px); opacity:0; }
}

/* Success flash */
#qr-success-flash {
    display: none; position: absolute; inset: 0;
    background: rgba(22, 101, 52, 0.85); backdrop-filter: blur(4px); border-radius: 18px;
    z-index: 4; align-items: center; justify-content: center;
    flex-direction: column; gap: 10px;
}
#qr-success-flash.show { display: flex; animation: popIn 0.25s ease; }
#qr-success-flash span {
    color:#fff; font-weight:800; font-size:16px;
    font-family:'Outfit',sans-serif; letter-spacing: -0.01em;
}

/* Status bar */
#qr-status-text {
    margin: 16px 0 4px; font-size: 13px;
    color: #CBD5E1;
    font-family: 'Outfit', sans-serif; min-height: 20px;
    display: inline-flex; align-items: center; justify-content: center; gap: 8px;
    padding: 6px 14px; background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 20px;
}

/* Close button */
#qr-close-btn {
    position: absolute; top: 16px; right: 16px;
    background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.12); color: #CBD5E1;
    width: 32px; height: 32px; border-radius: 50%; font-size: 16px;
    cursor: pointer; display: flex; align-items: center;
    justify-content: center; transition: all 0.2s;
}
#qr-close-btn:hover { background: rgba(255, 255, 255, 0.2); color: #fff; transform: scale(1.05); }
</style>

<!-- Modal HTML -->
<div id="qr-scanner-overlay">
    <div id="qr-scanner-box">
        <button id="qr-close-btn" onclick="closeQrScanner()">&times;</button>
        <h3><img src="assets/logo.jpg" alt="Santa Fe Beach Club logo" class="qr-logo"> QR Code Scanner</h3>
        <p class="qr-sub">Align the guest's booking QR code within the frame</p>

        <div id="qr-video-wrap">
            <video id="qr-video" autoplay playsinline muted></video>
            <canvas id="qr-canvas"></canvas>
            <div class="qr-corner tl"></div>
            <div class="qr-corner tr"></div>
            <div class="qr-corner bl"></div>
            <div class="qr-corner br"></div>
            <div class="qr-scanline"></div>
            <div id="qr-success-flash">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none"
                     stroke="#4ADE80" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/>
                    <polyline points="9 12 11 14 15 10"/>
                </svg>
                <span>QR Verified! Loading Pass…</span>
            </div>
        </div>

        <div id="qr-status-text">Starting camera…</div>
        <select id="qr-cam-select" style="display:none; margin-top:12px; width:100%; padding:10px 14px; border-radius:12px; background:rgba(30,41,59,0.9); color:#F1F5F9; border:1px solid rgba(255,255,255,0.15); font-family:'Outfit',sans-serif; font-size:13px; outline:none; cursor:pointer;"></select>
    </div>
</div>

<script>
(function () {
    const CHECKIN_URL = '<?php echo $_is_admin ? "admin_checkin" : "checkin"; ?>';

    let stream        = null;
    let rafId         = null;
    let scannerOpen   = false;
    let done          = false;
    let camerasList   = [];

    const video       = document.getElementById('qr-video');
    const canvas      = document.getElementById('qr-canvas');
    const ctx         = canvas.getContext('2d', { willReadFrequently: true });
    const statusEl    = document.getElementById('qr-status-text');
    const flashEl     = document.getElementById('qr-success-flash');
    const overlayEl   = document.getElementById('qr-scanner-overlay');
    const camSelect   = document.getElementById('qr-cam-select');

    /* ── PUBLIC: toggle open/close ── */
    window.launchDesktopScanner = function () {
        scannerOpen ? closeQrScanner() : openQrScanner();
    };

    window.openQrScanner = function () {
        if (scannerOpen) return;
        scannerOpen = true;
        done = false;
        overlayEl.classList.add('open');
        flashEl.classList.remove('show');
        statusEl.textContent = 'Starting camera…';
        camSelect.style.display = 'none';
        
        // Populate camera list first if we have permissions
        if (navigator.mediaDevices && navigator.mediaDevices.enumerateDevices) {
            navigator.mediaDevices.enumerateDevices().then(devices => {
                camerasList = devices.filter(d => d.kind === 'videoinput' && d.deviceId);
                if (camerasList.length > 0) {
                    camSelect.innerHTML = '';
                    camerasList.forEach((cam, i) => {
                        let opt = document.createElement('option');
                        opt.value = cam.deviceId;
                        opt.text = cam.label || `Camera ${i + 1}`;
                        camSelect.appendChild(opt);
                    });
                    camSelect.style.display = 'block';
                }
                startCamera();
            }).catch(() => startCamera());
        } else {
            startCamera();
        }
    };

    window.closeQrScanner = function () {
        scannerOpen = false;
        overlayEl.classList.remove('open');
        stopCamera();
    };

    camSelect.addEventListener('change', function() {
        if (this.value) {
            stopCamera();
            statusEl.textContent = 'Switching camera...';
            tryConstraints([{ video: { deviceId: { exact: this.value } } }], 0);
        }
    });

    /* ── Camera ── */
    function startCamera() {
        // If we have a populated dropdown, use the selected one
        if (camSelect.value) {
            tryConstraints([{ video: { deviceId: { exact: camSelect.value } } }], 0);
            return;
        }

        // Fallback generic constraints
        const constraints = [
            { video: true },
            { video: { facingMode: 'environment' } },
            { video: { facingMode: 'user' } }
        ];

        tryConstraints(constraints, 0);
    }

    function tryConstraints(list, idx) {
        if (idx >= list.length) {
            // All constraints failed — show a retry button
            statusEl.innerHTML = '⚠️ Camera unavailable. &nbsp;<button id="qr-retry-btn" style="display:inline-flex;align-items:center;gap:6px;margin-left:6px;padding:5px 14px;border:none;border-radius:20px;' +
                'background:linear-gradient(135deg,#C8996F,#B07D52);color:#fff;font-weight:700;font-size:12px;cursor:pointer;' +
                'font-family:Outfit,sans-serif;box-shadow:0 2px 8px rgba(200,153,111,0.4);">↻ Retry</button>';
            document.getElementById('qr-retry-btn').addEventListener('click', function() {
                statusEl.textContent = '↻ Retrying camera…';
                startCamera();
            });
            return;
        }
        navigator.mediaDevices.getUserMedia(list[idx])
            .then(function (s) {
                stream = s;
                video.srcObject = s;
                video.onloadedmetadata = function () {
                    video.play();
                    statusEl.textContent = '✓ Camera live — align QR code to frame';
                    scanLoop();
                };
            })
            .catch(function (err) {
                console.warn('Camera attempt', idx, 'failed:', err.name, err.message);
                tryConstraints(list, idx + 1);
            });
    }

    function stopCamera() {
        if (rafId) { cancelAnimationFrame(rafId); rafId = null; }
        if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; }
        video.srcObject = null;
    }

    /* ── Decode loop ── */
    function scanLoop() {
        if (!scannerOpen || done) return;

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
        flashEl.classList.add('show');
        statusEl.textContent = '✅ QR detected! Redirecting…';
        stopCamera();

        setTimeout(function () {
            try {
                let ref = null, token = null;

                // Try as full URL
                try {
                    const u = new URL(text);
                    ref   = u.searchParams.get('ref');
                    token = u.searchParams.get('token');
                } catch (_) {
                    // Try pipe-separated fallback
                    const parts = text.split('|');
                    if (parts.length === 2) { ref = parts[0]; token = parts[1]; }
                }

                if (ref && token) {
                    window.location.href = CHECKIN_URL + '?ref=' + encodeURIComponent(ref) +
                                           '&token=' + encodeURIComponent(token);
                } else if (text.includes('ref=') && text.includes('token=')) {
                    window.location.href = text;
                } else {
                    statusEl.textContent = '⚠️ Invalid QR — not a booking code.';
                    flashEl.classList.remove('show');
                    done = false;
                    startCamera();
                    scanLoop();
                }
            } catch (e) {
                statusEl.textContent = '⚠️ Could not read QR data.';
                done = false;
            }
        }, 700);
    }

    /* ── Keyboard & backdrop close ── */
    overlayEl.addEventListener('click', function (e) {
        if (e.target === overlayEl) closeQrScanner();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && scannerOpen) closeQrScanner();
        // Q key to toggle scanner (but not when typing in inputs)
        if (e.key === 'q' || e.key === 'Q') {
            const tag = document.activeElement.tagName;
            if (tag !== 'INPUT' && tag !== 'TEXTAREA' && tag !== 'SELECT') {
                e.preventDefault();
                launchDesktopScanner();
            }
        }
    });
})();
</script>

