<?php
require_once __DIR__ . '/../backend/helpers/auth_check.php';
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/helpers/pii_masker.php';
require_once __DIR__ . '/../backend/controllers/reservations_controller.php';

$reservationsData = sfbc_handle_reservations_page($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-circle-32.png?v=3">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/favicon-circle-180.png?v=3">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservations Directory — Santa Fe Beach Club</title>
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?php echo filemtime(__DIR__ . '/assets/css/dashboard.css'); ?>">
    <link rel="stylesheet" href="assets/css/pages/admin-reservations.css?v=<?php echo filemtime(__DIR__ . '/assets/css/pages/admin-reservations.css'); ?>">
    <?php require __DIR__ . '/partials/_responsive.php'; ?>
</head>
<body>
    <?php $active_page = 'reservations'; include __DIR__ . '/partials/_sidebar.php'; ?>

    <main class="main-content">
        <?php
        $page_title = 'Guest Reservations';
        $page_subtitle = 'Front desk registry and booking logs';
        $header_extra_html = '
            <div class="search-wrapper">
                <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" placeholder="Search guests..." class="search-input" id="reservationSearch">
            </div>
            <a href="dashboard" class="btn-primary res-header-new-btn">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                New Booking
            </a>
        ';
        include __DIR__ . '/partials/_page_header.php';
        ?>

        <div class="admin-body">
            <?php include __DIR__ . '/views/reservations/_reservations_list.php'; ?>
        </div>
    </main>

    <script src="assets/js/pages/reservations-filter.js?v=<?php echo filemtime(__DIR__ . '/assets/js/pages/reservations-filter.js'); ?>"></script>
</body>
</html>
