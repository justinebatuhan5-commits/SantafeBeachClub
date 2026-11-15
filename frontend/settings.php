<?php
require_once __DIR__ . '/../backend/helpers/auth_check.php';
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/helpers/business_time_helper.php';
require_once __DIR__ . '/../backend/helpers/security_logger.php';
require_once __DIR__ . '/../backend/helpers/rbac_helper.php';
require_once __DIR__ . '/../backend/helpers/password_helper.php';
require_once __DIR__ . '/../backend/helpers/cloudinary_helper.php';
require_once __DIR__ . '/../backend/controllers/settings_controller.php';

$pageData = sfbc_handle_settings_page($conn, !empty($_is_admin));
$active_tab = $pageData['activeTab'];
$isAdmin    = $pageData['isAdmin'];
$success    = $pageData['success'];
$error      = $pageData['error'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-circle-32.png?v=3">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/favicon-circle-180.png?v=3">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo $pageData['adminCssVersion']; ?>">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?php echo $pageData['dashboardCssVersion']; ?>">
    <link rel="stylesheet" href="assets/css/pages/settings.css?v=<?php echo $pageData['cssVersion']; ?>">
    <script src="assets/js/site-dialog.js?v=1"></script>
    <?php require __DIR__ . '/partials/_responsive.php'; ?>
</head>
<body>

    <?php $active_page = 'settings'; include __DIR__ . '/partials/_sidebar.php'; ?>

    <main class="main-content">
        <?php
        $page_title = 'Settings';
        $page_subtitle = 'Manage your account, team access, and property configuration.';
        include __DIR__ . '/partials/_page_header.php';
        ?>
        <div class="admin-body">
            <div class="settings-wrapper">
                <div class="settings-legacy-header" hidden>
                    <h1>Settings</h1>
                    <p>Manage your account, team access, and property configuration.</p>
                </div>

                <?php if ($success !== ''): ?>
                    <div class="alert alert-success">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        <?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <?php if ($error !== ''): ?>
                    <div class="alert alert-error">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                        <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                    <?php if ($pageData['needsReauth']): ?>
                        <p style="margin:-6px 0 18px"><a class="btn-save" style="display:inline-block;text-decoration:none" href="admin_reauth.php?return=settings">Confirm Password</a></p>
                    <?php endif; ?>
                <?php endif; ?>

                <div class="tab-nav">
                    <button type="button" class="tab-btn <?php echo $active_tab === 'profile' ? 'active' : ''; ?>" data-tab="profile">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                        My Profile
                    </button>
                    <?php if ($isAdmin): ?>
                        <button type="button" class="tab-btn <?php echo $active_tab === 'users' ? 'active' : ''; ?>" data-tab="users">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                            User Management
                        </button>
                        <button type="button" class="tab-btn <?php echo $active_tab === 'property' ? 'active' : ''; ?>" data-tab="property">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                            Property
                        </button>
                    <?php endif; ?>
                    <button type="button" class="tab-btn <?php echo $active_tab === 'notifications' ? 'active' : ''; ?>" data-tab="notifications">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                        Notifications
                    </button>
                </div>

                <?php include __DIR__ . '/views/settings/_tab_profile.php'; ?>
                <?php if ($isAdmin): ?>
                    <?php include __DIR__ . '/views/settings/_tab_users.php'; ?>
                    <?php include __DIR__ . '/views/settings/_tab_property.php'; ?>
                <?php endif; ?>
                <?php include __DIR__ . '/views/settings/_tab_notifications.php'; ?>
            </div>
        </div>
    </main>

    <script src="assets/js/pages/settings.js?v=<?php echo $pageData['jsVersion']; ?>"></script>
</body>
</html>
