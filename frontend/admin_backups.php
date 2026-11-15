<?php
require_once __DIR__ . '/../backend/helpers/admin_auth_check.php';
require_once __DIR__ . '/../backend/helpers/pc_backup_helper.php';
require_once __DIR__ . '/../backend/controllers/admin_backups_controller.php';

$backupData = sfbc_handle_admin_backups_page();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-circle-32.png?v=3">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/favicon-circle-180.png?v=3">
    <title>PC Database Backups — Santa Fe Beach Club</title>
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="assets/css/backups.css?v=<?= filemtime(__DIR__ . '/assets/css/backups.css') ?>">
    <?php require __DIR__ . '/partials/_responsive.php'; ?>
</head>
<body>
    <?php $active_page = 'backups'; include __DIR__ . '/partials/_sidebar.php'; ?>

    <main class="admin-main">
        <?php
        $page_title = 'PC Complete Backups';
        $page_subtitle = 'Download your hosted database and private uploads to your computer every six hours.';
        include __DIR__ . '/partials/_page_header.php';
        include __DIR__ . '/views/admin_backups/_backup_dashboard.php';
        ?>
    </main>
</body>
</html>
