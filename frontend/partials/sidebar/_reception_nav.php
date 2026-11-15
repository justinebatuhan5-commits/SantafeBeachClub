<!-- ═══════════════ RECEPTION SIDEBAR ═══════════════ -->
<aside class="sidebar" id="appSidebar">
    <div class="sidebar-logo">
        <button type="button" class="sidebar-logo-toggle" aria-label="Hide sidebar" aria-controls="appSidebar" aria-expanded="true" title="Hide sidebar">
            <img src="assets/logo.jpg" alt="Logo" class="logo-circle">
            <div class="logo-text-group">
                <h2>Santa Fe.</h2>
                <p>Reception Desk</p>
            </div>
        </button>
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
            <li><a href="admin_checkin" class="nav-item <?php echo in_array($_page, ['checkin', 'checkout'], true) ? 'active' : ''; ?>" title="Check-in / Check-out">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 7h11"></path><polyline points="15 4 18 7 15 10"></polyline><path d="M17 17H6"></path><polyline points="9 14 6 17 9 20"></polyline></svg>
                Check-in / Check-out
            </a></li>
            <li><a href="javascript:void(0)" onclick="launchDesktopScanner()" class="nav-item sidebar-link-action" title="Launch QR Scanner">
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
            <li><a href="discount_requests.php" class="nav-item <?php echo _sb_active('discount_requests',$_page); ?>" title="Discount Requests"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8" cy="10" r="2"/><path d="M5 16c0-3 6-3 6 0M14 10h4M14 14h4"/></svg>Discount Requests</a></li>
            <li><a href="payments" class="nav-item <?php echo _sb_active('payments',$_page); ?>" title="Payments & Billing">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2" ry="2"></rect><line x1="12" y1="10" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg>
                Payments & Billing
                <?php echo _sb_badge($_pending_payments, 'payments'); ?>
            </a></li>
            <li><a href="admin_refunds" class="nav-item <?php echo _sb_active('admin_refunds',$_page); ?>" title="Refund Requests">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path><path d="M3 3v5h5"></path><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                Refund Requests
                <?php echo _sb_badge($_pending_refunds, 'refunds'); ?>
            </a></li>

            <li><a href="admin_live_support" class="nav-item <?php echo _sb_active('live_support',$_page); ?>" title="Live Support Chats">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path><line x1="8" y1="9" x2="16" y2="9"></line><line x1="8" y1="13" x2="13" y2="13"></line></svg>
                Live Support Chats
                <?php echo _sb_badge($_unread_live_chats, 'live_chats'); ?>
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
            <?php if (!empty($_sidebar_photo) && (str_starts_with($_sidebar_photo, 'http') || file_exists(__DIR__ . '/../../' . $_sidebar_photo))): ?>
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
