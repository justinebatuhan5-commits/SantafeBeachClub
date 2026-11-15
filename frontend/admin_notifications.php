<?php
require_once __DIR__ . '/../backend/helpers/admin_auth_check.php';
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/controllers/admin_notifications_controller.php';

$notifData = sfbc_handle_notifications_page($conn, 'admin_notifications', true);
$v = $notifData['versions'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-circle-32.png?v=3">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/favicon-circle-180.png?v=3">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Notifications - Santa Fe Beach Club</title>
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo $v['adminCss']; ?>">
    <link rel="stylesheet" href="assets/css/pages/admin-notifications.css?v=<?php echo $v['pageCss']; ?>">
    <?php require __DIR__ . '/partials/_responsive.php'; ?>
</head>
<body>

    <?php $active_page = 'notifications'; include __DIR__ . '/partials/_sidebar.php'; ?>

    <main class="admin-main">
        <?php
        $page_title = 'Admin Notifications';
        $page_subtitle = 'System alerts & booking updates';
        $header_extra_html = '
            <form method="POST" action="admin_notifications" style="margin: 0;">
                ' . $notifData['csrfField'] . '
                <input type="hidden" name="action" value="mark_all_read">
                <button type="submit" class="header-icon-btn" title="Mark All as Read" style="width: auto; padding: 0 15px; gap: 8px; font-weight: 600; font-size: 13px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    Mark All Read
                </button>
            </form>
        ';
        include __DIR__ . '/partials/_page_header.php';
        ?>

        <?php include __DIR__ . '/views/admin_notifications/_notifications_content.php'; ?>
    </main>

    <script src="assets/js/pages/admin-notifications.js?v=<?php echo $v['pageJs']; ?>"></script>
</body>
</html>
