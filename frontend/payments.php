<?php
require_once __DIR__ . '/../backend/helpers/auth_check.php';
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/services/mailer.php';
require_once __DIR__ . '/../backend/helpers/private_storage_helper.php';
require_once __DIR__ . '/../backend/controllers/payments_controller.php';

$pageData = sfbc_handle_payments_page($conn);
$flash = $pageData['flash'];
$summary = $pageData['summary'] ?? [
    'total_count' => 0,
    'verified_count' => 0,
    'verified_total' => 0.0,
    'pending_count' => 0,
    'pending_total' => 0.0,
    'refunded_count' => 0,
    'refunded_total' => 0.0,
    'rejected_count' => 0,
    'rejected_total' => 0.0,
];
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
        $page_subtitle = 'Verify guest settlements, manage billing receipts, and audit payment trails';
        $payments_toolbar_html = '
            <div class="payments-toolbar-main">
                <div class="search-wrapper payments-search-box">
                    <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input type="text" placeholder="Search invoice, REF, guest, room, or reference..." class="search-input" id="paymentSearch">
                </div>
                <div class="payments-status-pills" role="tablist" aria-label="Filter bills by status">
                    <button type="button" class="pay-status-pill active" data-status-pill="">
                        All <span class="pill-count">' . (int)$summary['total_count'] . '</span>
                    </button>
                    <button type="button" class="pay-status-pill pill-pending" data-status-pill="pending">
                        Pending <span class="pill-count">' . (int)$summary['pending_count'] . '</span>
                    </button>
                    <button type="button" class="pay-status-pill pill-verified" data-status-pill="verified">
                        Verified <span class="pill-count">' . (int)$summary['verified_count'] . '</span>
                    </button>
                    <button type="button" class="pay-status-pill pill-refunded" data-status-pill="refunded">
                        Refunded <span class="pill-count">' . (int)$summary['refunded_count'] . '</span>
                    </button>
                    <button type="button" class="pay-status-pill pill-rejected" data-status-pill="rejected">
                        Rejected <span class="pill-count">' . (int)$summary['rejected_count'] . '</span>
                    </button>
                </div>
            </div>
            <div class="payments-toolbar-controls">
                <div class="filter-wrapper">
                    <select id="paymentMethodFilter" class="payments-status-select" aria-label="Filter by payment method">
                        <option value="">All Channels</option>
                        <option value="gcash">GCash QR</option>
                        <option value="paymongo">PayMongo</option>
                        <option value="cash">Front Desk Cash</option>
                        <option value="card">Card / POS</option>
                        <option value="bank">Bank Deposit</option>
                    </select>
                </div>
                <div class="filter-wrapper">
                    <select id="paymentStatusFilter" class="payments-status-select" aria-label="Filter by payment status">
                        <option value="">All Statuses</option>
                        <option value="verified">Verified</option>
                        <option value="pending">Pending</option>
                        <option value="rejected">Rejected</option>
                        <option value="refunded">Refunded</option>
                    </select>
                </div>
                <span class="payments-results-counter" id="paymentsResultsCounter">Showing ' . (int)$summary['total_count'] . ' bills</span>
            </div>
        ';
        $header_extra_html = '
            <a href="../backend/api/availability.php?action=export_report_csv&type=payments" class="header-icon-btn" style="width:auto; padding:0 14px; height:38px; font-size:12.5px; font-weight:700; text-decoration:none; gap:6px; color:#059669; border-color:#A7F3D0; background:#ECFDF5;" title="Export Payments CSV">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                <span>Export CSV</span>
            </a>
        ';
        include __DIR__ . '/partials/_page_header.php';
        ?>

        <div class="admin-body">
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
        </div>
    </main>

    <?php include __DIR__ . '/views/payments/_payments_modals.php'; ?>

    <script id="paymentsPageData" type="application/json"><?php echo xss_json([
        'breakdownMap' => $pageData['breakdownMap'],
        'receptionistName' => $pageData['receptionistName'],
    ]); ?></script>
    <script src="assets/js/pages/payments.js?v=<?php echo $pageData['jsVersion']; ?>"></script>
</body>
</html>
