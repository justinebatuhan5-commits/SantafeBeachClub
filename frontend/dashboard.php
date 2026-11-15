<?php
require_once __DIR__ . '/../backend/helpers/auth_check.php';
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/controllers/dashboard_controller.php';

$dashboardData = sfbc_handle_dashboard_page($conn);
$v = $dashboardData['versions'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-circle-32.png?v=3">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/favicon-circle-180.png?v=3">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reception Cockpit — Santa Fe Beach Club</title>
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo $v['adminCss']; ?>">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?php echo $v['dashboardCss']; ?>">
    <link rel="stylesheet" href="assets/css/pages/dashboard.css?v=<?php echo $v['pageCss']; ?>">
    <script src="assets/js/site-dialog.js?v=1"></script>
    <?php require __DIR__ . '/partials/_responsive.php'; ?>
</head>
<body>

    <?php $active_page = 'dashboard'; include __DIR__ . '/partials/_sidebar.php'; ?>

    <main class="main-content">
        <?php
        $page_title = 'Front Desk Dashboard';
        $page_subtitle = 'Guest arrivals, departures, and room operations • ' . date('l, F j, Y');
        $header_extra_html = '
            <div class="search-wrapper" style="position:relative;">
                <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" placeholder="Search by name, room, phone, or Ref ID..." class="search-input" id="dashboardSearch">
            </div>
            <button type="button" class="btn-new-res-top" data-action="open-reservation-modal">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                New Reservation
            </button>
        ';
        include __DIR__ . '/partials/_page_header.php';
        ?>

        <?php include __DIR__ . '/views/dashboard/_cockpit_content.php'; ?>
    </main>

    <?php include __DIR__ . '/views/dashboard/_modals.php'; ?>

    <script>
        window.SFBC_DASHBOARD_CONFIG = <?php echo json_encode([
            'csrfToken' => $dashboardData['csrfToken'],
            'overdueGuestNames' => $dashboardData['overdueGuestNames'],
        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
    </script>
    <script src="assets/js/pages/dashboard.js?v=<?php echo $v['pageJs']; ?>"></script>

    <?php include __DIR__ . '/partials/_new_booking_alert.php'; ?>
</body>
</html>
