<?php
require_once __DIR__ . '/../backend/helpers/admin_auth_check.php';
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/controllers/admin_reviews_controller.php';

$active_page = 'reviews';
$reviewsData = sfbc_handle_admin_reviews_page($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guest Reviews - Santa Fe</title>
    <link rel="stylesheet" href="assets/css/admin.css?v=<?= filemtime(__DIR__ . '/assets/css/admin.css') ?>">
    <link rel="stylesheet" href="assets/css/pages/admin-reviews.css?v=<?= filemtime(__DIR__ . '/assets/css/pages/admin-reviews.css') ?>">
    <script src="assets/js/site-dialog.js?v=1"></script>
    <?php require __DIR__ . '/partials/_responsive.php'; ?>
</head>
<body>
    <?php include __DIR__ . '/partials/_sidebar.php'; ?>

    <main class="admin-main">
        <?php
        $page_title = 'Reviews & Inquiries';
        $page_subtitle = 'Manage guest feedback and messages in one place.';
        include __DIR__ . '/partials/_page_header.php';
        ?>

        <div class="admin-body">
            <?php $guest_communications_active = 'reviews'; include __DIR__ . '/partials/_guest_communications_tabs.php'; ?>
            <?php include __DIR__ . '/views/admin_reviews/_reviews_board.php'; ?>
        </div>
    </main>
</body>
</html>
