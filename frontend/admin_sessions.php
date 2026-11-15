<?php
require_once __DIR__ . '/../backend/helpers/admin_auth_check.php';
require_once __DIR__ . '/../backend/helpers/security_logger.php';
require_once __DIR__ . '/../backend/controllers/admin_sessions_controller.php';

$active_page = 'admin_sessions';
$sessionsData = sfbc_handle_admin_sessions_page($conn);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Active Sessions — Santa Fe Beach Club</title>
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="assets/css/pages/admin-sessions.css?v=<?php echo filemtime(__DIR__ . '/assets/css/pages/admin-sessions.css'); ?>">
    <?php require __DIR__ . '/partials/_responsive.php'; ?>
</head>
<body>
    <?php include __DIR__ . '/partials/_sidebar.php'; ?>

    <main class="main-content">
        <?php
        $page_title = 'Active Sessions';
        $page_subtitle = 'Review browsers signed in to your administrator account and remotely sign them out.';
        $header_extra_html = '<form method="post" class="sessions-header-form">' . $sessionsData['csrfField'] . '<input type="hidden" name="action" value="revoke_others"><button class="btn-primary" type="submit">Sign Out All Other Sessions</button></form>';
        include __DIR__ . '/partials/_page_header.php';
        include __DIR__ . '/views/admin_sessions/_sessions_list.php';
        ?>
    </main>
</body>
</html>
