<?php
require_once __DIR__ . '/../backend/helpers/auth_check.php';
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/services/mailer.php';
require_once __DIR__ . '/../backend/helpers/private_storage_helper.php';
require_once __DIR__ . '/../backend/controllers/payments_controller.php';

$pageData = sfbc_handle_payments_page($conn);
$flash = $pageData['flash'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-circle-32.png?v=3">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/favicon-circle-180.png?v=3">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($pageData['csrfToken'], ENT_QUOTES, 'UTF-8'); ?>">
    <title>Payment Processing — Santa Fe Beach Club</title>
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo $pageData['adminCssVersion']; ?>">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?php echo $pageData['dashboardCssVersion']; ?>">
    <link rel="stylesheet" href="assets/css/pages/payments.css?v=<?php echo $pageData['cssVersion']; ?>">
    <script src="assets/js/site-dialog.js?v=1"></script>
    <?php require __DIR__ . '/partials/_responsive.php'; ?>
</head>
<body class="payments-page">

    <?php $active_page = 'payments'; include __DIR__ . '/partials/_sidebar.php'; ?>

    <main class="main-content">
        <?php
        $page_title = 'Payment Processing';
        $page_subtitle = 'Manage bills and transactions';
        $payments_toolbar_html = '
            <div class="search-wrapper">
                <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <input type="text" placeholder="Search invoice..." class="search-input" id="paymentSearch">
            </div>
            <div class="filter-wrapper">
                <select id="paymentStatusFilter" class="payments-status-select">
                    <option value="">All Statuses</option>
                    <option value="verified">Verified</option>
                    <option value="pending">Pending</option>
                    <option value="rejected">Rejected</option>
                    <option value="refunded">Refunded</option>
                </select>
            </div>
        ';
        $header_extra_html = '';
        include __DIR__ . '/partials/_page_header.php';
        ?>

        <section class="dashboard-grid" style="grid-template-columns: 1fr;">
            <?php if ($flash['success']): ?>
                <div class="alert-success">
                    Payment verified successfully! Booking has been confirmed.
                </div>
            <?php endif; ?>
            <?php if ($flash['email_sent']): ?>
                <div class="alert-success">
                    Confirmation email sent to the guest successfully.
                </div>
            <?php endif; ?>
            <?php if ($flash['email_error'] !== null): ?>
                <div class="alert-success alert-success--error">
                    Confirmation email was not sent: <?php echo htmlspecialchars((string)$flash['email_error'], ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>
            <?php if ($flash['rejected']): ?>
                <div class="alert-success alert-success--error">
                    Payment rejected. Booking has been cancelled.
                </div>
            <?php endif; ?>
            <?php if ($flash['refunded']): ?>
                <div class="alert-success alert-success--refund">
                    Payment marked as refunded. Booking has been cancelled and activity logged.
                </div>
            <?php endif; ?>

            <?php include __DIR__ . '/views/payments/_payments_table.php'; ?>
        </section>
    </main>

    <?php include __DIR__ . '/views/payments/_payments_modals.php'; ?>

    <script id="paymentsPageData" type="application/json"><?php echo xss_json([
        'breakdownMap' => $pageData['breakdownMap'],
        'receptionistName' => $pageData['receptionistName'],
    ]); ?></script>
    <script src="assets/js/pages/payments.js?v=<?php echo $pageData['jsVersion']; ?>"></script>
</body>
</html>
