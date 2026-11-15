<?php
require_once __DIR__ . '/../backend/helpers/auth_check.php';
if (!in_array($_SESSION['admin_role'] ?? '', ['admin', 'receptionist'], true)) {
    http_response_code(403);
    exit('Access denied.');
}
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/helpers/prearrival_reminder_helper.php';
require_once __DIR__ . '/../backend/helpers/noshow_refund_helper.php';
require_once __DIR__ . '/../backend/helpers/balance_payment_helper.php';
require_once __DIR__ . '/../backend/controllers/admin_checkin_controller.php';

/** @var mysqli $conn */
extract(sfbc_handle_admin_checkin_page($conn), EXTR_SKIP);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-circle-32.png?v=3">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/favicon-circle-180.png?v=3">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check-in / Check-out - Santa Fe Beach Club</title>
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="assets/css/pages/admin-checkin.css?v=<?php echo filemtime(__DIR__ . '/assets/css/pages/admin-checkin.css'); ?>">
    <script src="assets/js/site-dialog.js?v=2"></script>
<?php require __DIR__ . '/partials/_responsive.php'; ?>
</head>
<body>
    <?php $active_page = 'checkin'; include __DIR__ . '/partials/_sidebar.php'; ?>

    <main class="admin-main" id="checkinPageRoot" data-today="<?php echo htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>">
        <?php
        $page_title = 'Check-in / Check-out';
        $page_subtitle = 'Manage guest arrivals and departures';
        $header_extra_html = '
            <div class="search-wrapper">
                <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <input type="text" placeholder="Search by name or ref..." class="search-input" id="reservationSearch">
            </div>
        ';
        include __DIR__ . '/partials/_page_header.php';
        ?>

        <div class="admin-body">
            <?php $stay_operations_active = 'checkin'; include __DIR__ . '/partials/_stay_operations_tabs.php'; ?>
            <?php require __DIR__ . '/views/admin_checkin/_arrivals_table.php'; ?>
        </div>
    </main>

    <?php require __DIR__ . '/views/admin_checkin/_modals.php'; ?>

    <?php if (!empty($printReceipt)): ?>
    <script type="application/json" id="checkinReceiptPayload"><?php echo json_encode($printReceipt, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
    <?php endif; ?>

    <script src="assets/js/pages/admin-checkin.js?v=<?php echo filemtime(__DIR__ . '/assets/js/pages/admin-checkin.js'); ?>" defer></script>
</body>
</html>
