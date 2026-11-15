<?php
require_once __DIR__ . '/../backend/helpers/admin_auth_check.php';
require_once __DIR__ . '/../backend/config/db.php';

$page_title = 'Automation & Scheduler';
$page_subtitle = 'Monitor background jobs, scheduled tasks, and automated operations';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Santa Fe Beach Club</title>
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="assets/css/tailwind.css?v=<?php echo (int) filemtime(__DIR__ . '/assets/css/tailwind.css'); ?>">
    <link rel="stylesheet" href="assets/css/styles.css?v=<?php echo filemtime(__DIR__ . '/assets/css/styles.css'); ?>">
    <link rel="stylesheet" href="assets/css/pages/admin-automation.css?v=<?php echo filemtime(__DIR__ . '/assets/css/pages/admin-automation.css'); ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-circle-32.png">
</head>
<body>
    <?php $active_page = 'automation'; include __DIR__ . '/partials/_sidebar.php'; ?>

    <main class="main-content">
        <?php include __DIR__ . '/partials/_page_header.php'; ?>

        <div class="admin-body">
            <?php include __DIR__ . '/views/admin_automation/_scheduler_content.php'; ?>
        </div>
    </main>

    <script src="assets/js/pages/admin-automation.js?v=<?php echo filemtime(__DIR__ . '/assets/js/pages/admin-automation.js'); ?>"></script>
</body>
</html>
