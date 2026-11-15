<?php
require_once __DIR__ . '/../backend/helpers/admin_auth_check.php';
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/helpers/checkout_notification_helper.php';
require_once __DIR__ . '/../backend/controllers/checkout_controller.php';

$checkoutData = sfbc_handle_checkout_page($conn, 'admin_checkout', true, false);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-circle-32.png?v=3">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/favicon-circle-180.png?v=3">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check-in / Check-out — Santa Fe Beach Club</title>
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="assets/css/pages/checkout.css?v=<?php echo filemtime(__DIR__ . '/assets/css/pages/checkout.css'); ?>">
    <?php require __DIR__ . '/partials/_responsive.php'; ?>
</head>
<body>
    <?php $active_page = 'checkout'; include __DIR__ . '/partials/_sidebar.php'; ?>

    <main class="admin-main">
        <?php
        $page_title = 'Check-in / Check-out';
        $page_subtitle = 'Manage guest arrivals and departures';
        $header_extra_html = '
            <div class="search-wrapper">
                <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <input type="text" placeholder="Search guests..." class="search-input" id="checkoutSearch">
            </div>
        ';
        include __DIR__ . '/partials/_page_header.php';
        ?>

        <div class="admin-body">
            <?php $stay_operations_active = 'checkout'; include __DIR__ . '/partials/_stay_operations_tabs.php'; ?>
            <?php include __DIR__ . '/views/checkout/_checkout_content.php'; ?>
        </div>
    </main>

    <script src="assets/js/pages/checkout.js?v=<?php echo filemtime(__DIR__ . '/assets/js/pages/checkout.js'); ?>"></script>
</body>
</html>
