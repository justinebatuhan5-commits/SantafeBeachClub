<?php
require_once __DIR__ . '/../backend/helpers/auth_check.php';
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/controllers/admin_notifications_controller.php';

$notifData = sfbc_handle_notifications_page($conn, 'notifications', false);
$v = $notifData['versions'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-circle-32.png?v=3">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/favicon-circle-180.png?v=3">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications — Santa Fe Beach Club</title>
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo $v['adminCss']; ?>">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?php echo $v['dashboardCss']; ?>">
    <link rel="stylesheet" href="assets/css/pages/admin-notifications.css?v=<?php echo $v['pageCss']; ?>">
    <?php require __DIR__ . '/partials/_responsive.php'; ?>
</head>
<body>

    <?php $active_page = 'notifications'; include __DIR__ . '/partials/_sidebar.php'; ?>

    <main class="main-content">
        <?php
        $page_title = 'Notifications';
        $page_subtitle = 'Front desk alerts & updates';
        $hasUnread = ((int)($notifData['unreadCount'] ?? 0)) > 0;
        $header_extra_html = '
            <form method="POST" action="notifications" style="margin: 0;">
                ' . $notifData['csrfField'] . '
                <input type="hidden" name="action" value="mark_all_read">
                <button type="submit" class="header-icon-btn notif-mark-all-header" title="Mark All as Read"' . ($hasUnread ? '' : ' disabled') . '>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 7 17l-5-5"/><path d="m22 10-7.5 7.5L13 16"/></svg>
                    <span>' . ($hasUnread ? 'Mark All Read' : 'All Caught Up') . '</span>
                </button>
            </form>
        ';
        include __DIR__ . '/partials/_page_header.php';
        ?>

        <?php include __DIR__ . '/views/notifications/_notifications_list.php'; ?>
    </main>

    <script src="assets/js/pages/admin-notifications.js?v=<?php echo $v['pageJs']; ?>"></script>
</body>
</html>
