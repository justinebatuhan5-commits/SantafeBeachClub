<?php
require_once __DIR__ . '/../backend/helpers/admin_auth_check.php';
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/helpers/room_status_helper.php';
require_once __DIR__ . '/../backend/controllers/accommodations_controller.php';

$roomsData = sfbc_handle_accommodations_page($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-circle-32.png?v=3">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/favicon-circle-180.png?v=3">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accommodation Status — Santa Fe Beach Club</title>
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="assets/css/pages/accommodations.css?v=<?php echo filemtime(__DIR__ . '/assets/css/pages/accommodations.css'); ?>">
    <?php require __DIR__ . '/partials/_responsive.php'; ?>
</head>
<body>
    <?php $active_page = 'accommodations'; include __DIR__ . '/partials/_sidebar.php'; ?>

    <main class="main-content">
        <?php
        $page_title = 'Accommodation Status';
        $page_subtitle = 'Manage and track room conditions.';
        include __DIR__ . '/partials/_page_header.php';
        include __DIR__ . '/views/accommodations/_rooms_table.php';
        ?>
    </main>
</body>
</html>
