<?php
require_once __DIR__ . '/../backend/helpers/admin_auth_check.php';

$admin = $_SESSION['admin_username'] ?? 'Admin';
$adminCssVersion = file_exists(__DIR__ . '/assets/css/admin.css') ? filemtime(__DIR__ . '/assets/css/admin.css') : 1;
$cssVersion = file_exists(__DIR__ . '/assets/css/pages/admin-reports.css') ? filemtime(__DIR__ . '/assets/css/pages/admin-reports.css') : 1;
$securityJsVersion = file_exists(__DIR__ . '/assets/js/security.js') ? filemtime(__DIR__ . '/assets/js/security.js') : 1;
$jsVersion = file_exists(__DIR__ . '/assets/js/pages/admin-reports.js') ? filemtime(__DIR__ . '/assets/js/pages/admin-reports.js') : 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-circle-32.png?v=3">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/favicon-circle-180.png?v=3">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial &amp; Occupancy Reports — Santa Fe Beach Club</title>
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo $adminCssVersion; ?>">
    <link rel="stylesheet" href="assets/css/pages/admin-reports.css?v=<?php echo $cssVersion; ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <?php require __DIR__ . '/partials/_responsive.php'; ?>
</head>
<body>
    <?php $active_page = 'reports'; include __DIR__ . '/partials/_sidebar.php'; ?>

    <main class="admin-main">
        <?php
        $page_title = 'Financial & Business Reports';
        $page_subtitle = 'Audit trails, income performance, and guest volume metrics';
        $header_extra_html = '
            <div style="display:flex; gap:8px; align-items:center;">
                <a id="exportBookingsCsvLink" href="../backend/api/availability.php?action=export_report_csv&type=bookings" class="header-icon-btn" style="width:auto; padding:0 14px; height:40px; font-size:12.5px; font-weight:700; text-decoration:none; gap:6px; color:#334155;" title="Export Bookings CSV">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    <span>Bookings CSV</span>
                </a>
                <a id="exportPaymentsCsvLink" href="../backend/api/availability.php?action=export_report_csv&type=payments" class="header-icon-btn" style="width:auto; padding:0 14px; height:40px; font-size:12.5px; font-weight:700; text-decoration:none; gap:6px; color:#059669; border-color:#A7F3D0; background:#ECFDF5;" title="Export Payments CSV">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    <span>Payments CSV</span>
                </a>
                <button type="button" class="btn-primary" data-action="print-report" style="height:40px; padding:0 16px; border-radius:12px; font-size:13px; font-weight:700;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                    Print
                </button>
            </div>
        ';
        include __DIR__ . '/partials/_page_header.php';
        ?>

        <div class="admin-body">
            <?php include __DIR__ . '/views/admin_reports/_report_sections.php'; ?>
        </div>
    </main>

    <script src="assets/js/security.js?v=<?php echo $securityJsVersion; ?>"></script>
    <script src="assets/js/pages/admin-reports.js?v=<?php echo $jsVersion; ?>"></script>
</body>
</html>
