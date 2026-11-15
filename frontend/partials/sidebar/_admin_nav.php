<!-- ═══════════════ ADMIN SIDEBAR ═══════════════ -->
<aside class="admin-sidebar" id="appSidebar">
    <div class="sidebar-brand">
        <button type="button" class="sidebar-logo-toggle" aria-label="Hide sidebar" aria-controls="appSidebar" aria-expanded="true" title="Hide sidebar">
            <img src="assets/logo.jpg" alt="Logo" class="brand-circle">
            <div class="brand-text">
                <h2>Santa Fe.</h2>
                <p>Admin Command</p>
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
            <li><a href="admin_checkin" class="sidebar-link <?php echo in_array($_page, ['checkin', 'checkout'], true) ? 'active' : ''; ?>" title="Check-in / Check-out">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 7h11"/><polyline points="15 4 18 7 15 10"/><path d="M17 17H6"/><polyline points="9 14 6 17 9 20"/></svg>
                Check-in / Check-out
            </a></li>
            <li><a href="javascript:void(0)" onclick="launchDesktopScanner()" class="sidebar-link sidebar-link-action" title="Launch QR Scanner">
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
            <li><a href="discount_requests.php" class="sidebar-link <?php echo _sb_active('discount_requests',$_page); ?>" title="Discount Requests"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8" cy="10" r="2"/><path d="M5 16c0-3 6-3 6 0M14 10h4M14 14h4"/></svg>Discount Requests</a></li>
            <li><a href="payments" class="sidebar-link <?php echo _sb_active('payments',$_page); ?>" title="Payments">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><line x1="12" y1="10" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                Payments
                <?php echo _sb_badge($_pending_payments, 'payments'); ?>
            </a></li>
            <li><a href="admin_refunds" class="sidebar-link <?php echo _sb_active('admin_refunds',$_page); ?>" title="Refund Requests">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                Refund Requests
                <?php echo _sb_badge($_pending_refunds, 'refunds'); ?>
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
            <li><a href="admin_automation" class="sidebar-link <?php echo _sb_active('automation',$_page); ?>" title="Automation & Scheduler">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                Automation & Scheduler
            </a></li>
            <li><a href="admin_backups" class="sidebar-link <?php echo _sb_active('backups',$_page); ?>" title="Database Backups">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14c0 4 18 4 18 0V5M3 12c0 4 18 4 18 0"/></svg>
                Database Backups
            </a></li>
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
            <li><a href="admin_sessions" class="sidebar-link <?php echo _sb_active('admin_sessions',$_page); ?>" title="Active Sessions">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="14" rx="2"/><path d="M8 22h8M12 18v4"/><circle cx="17" cy="9" r="2"/></svg>
                Active Sessions
            </a></li>

            <li><a href="admin_reviews" class="sidebar-link <?php echo in_array($_page, ['reviews', 'inquiries'], true) ? 'active' : ''; ?>" title="Reviews &amp; Inquiries">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/><path d="m8.5 11 2.2 1.2-.4 2.5 1.7-1.8 2.3 1.1-1.1-2.3 1.7-1.8-2.5.3L11.2 8l-.5 2.5z"/></svg>
                Reviews &amp; Inquiries
                <?php echo _sb_badge($_unread_inquiries, 'inquiries'); ?>
            </a></li>
            <li><a href="admin_live_support" class="sidebar-link <?php echo _sb_active('live_support', $_page); ?>" title="Live Support Chats">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 18a2 2 0 0 0-2-2H9a2 2 0 0 0-2 2"/><rect x="3" y="4" width="18" height="12" rx="2"/><line x1="8" y1="9" x2="16" y2="9"/><line x1="8" y1="12" x2="13" y2="12"/></svg>
                Live Support Chats
                <?php echo _sb_badge($_unread_live_chats, 'live_chats'); ?>
            </a></li>
            <li><a href="admin_chatbot" class="sidebar-link <?php echo _sb_active('chatbot', $_page); ?>" title="AI Concierge &amp; Virtual Guest Assistant">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><circle cx="9" cy="10" r="1"/><circle cx="15" cy="10" r="1"/></svg>
                AI Concierge
            </a></li>
        </ul>
    </div>

    <div class="sidebar-bottom">
        <div class="user-pill" title="<?php echo htmlspecialchars($_user !== '' ? $_user : 'Admin'); ?>">
            <?php if (!empty($_sidebar_photo) && (str_starts_with($_sidebar_photo, 'http') || file_exists(__DIR__ . '/../../' . $_sidebar_photo))): ?>
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
