<?php
require_once __DIR__ . '/../backend/helpers/admin_auth_check.php';
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/controllers/admin_dashboard_controller.php';
/** @var mysqli $conn */

$pageData = sfbc_handle_admin_dashboard_page($conn);
$admin = $pageData['adminName'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-circle-32.png?v=3">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/favicon-circle-180.png?v=3">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — Santa Fe Beach Club</title>
    <link rel="stylesheet" href="assets/css/tailwind.css?v=1794418047058">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo $pageData['adminCssVersion']; ?>">
    <link rel="stylesheet" href="assets/css/admin_calendar.css?v=<?php echo $pageData['adminCalendarCssVersion']; ?>">
    <link rel="stylesheet" href="assets/css/pages/admin-dashboard.css?v=<?php echo $pageData['cssVersion']; ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="assets/js/occupancy_kpi.js?v=<?php echo $pageData['occupancyKpiJsVersion']; ?>"></script>
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#ff6b6b">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Beach Club">
    <meta name="description" content="Admin Dashboard for Santa Fe Beach Club Booking System">
    <?php require __DIR__ . '/partials/_responsive.php'; ?>
</head>
<body>

<?php
$active_page = 'dashboard';
include __DIR__ . '/partials/_sidebar.php';
?>

<div class="admin-main executive-dashboard">
    <?php
    $page_title = 'Admin Dashboard';
    $page_subtitle = 'Welcome back, ' . htmlspecialchars($admin, ENT_QUOTES, 'UTF-8') . ' • Resort operations and financial summary';
    $header_extra_html = '
        <div class="admin-search-wrapper">
            <svg class="admin-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="adminGlobalSearch" class="admin-search-input" placeholder="Search bookings, rooms, guests…" autocomplete="off">
            <div id="adminSearchResults" class="admin-search-dropdown"></div>
        </div>
        <a href="admin_reservations" class="btn-primary" style="height:38px;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            New Booking
        </a>
    ';
    include __DIR__ . '/partials/_page_header.php';
    ?>

    <div class="admin-body">
        <?php include __DIR__ . '/views/admin_dashboard/_kpi_and_charts.php'; ?>
    </div>
</div>

<?php include __DIR__ . '/views/admin_dashboard/_overdue_modal.php'; ?>
<?php include __DIR__ . '/views/admin_dashboard/_revenue_modal.php'; ?>

<script id="adminDashboardPageData" type="application/json"><?php echo json_encode([
    'overdueGuestNames' => $pageData['overdueGuestNames'],
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?></script>
<script src="assets/js/pages/admin-dashboard.js?v=<?php echo $pageData['jsVersion']; ?>"></script>
<?php include __DIR__ . '/partials/_new_booking_alert.php'; ?>
<?php include __DIR__ . '/assets/pwa-install-prompt.html'; ?>
<script src="assets/pwa-install.js" defer></script>

</body>
</html>
