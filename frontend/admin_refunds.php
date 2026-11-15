<?php
require_once __DIR__ . '/../backend/helpers/auth_check.php';
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/helpers/refund_method_helper.php';
require_once __DIR__ . '/../backend/controllers/admin_refunds_controller.php';

$pageData = sfbc_handle_admin_refunds_page($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-circle-32.png?v=3">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/favicon-circle-180.png?v=3">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Refund Requests — Admin</title>
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo $pageData['adminCssVersion']; ?>">
    <link rel="stylesheet" href="assets/css/pages/admin-refunds.css?v=<?php echo $pageData['cssVersion']; ?>">
    <script src="assets/js/site-dialog.js?v=1"></script>
    <?php require __DIR__ . '/partials/_responsive.php'; ?>
</head>
<body>
    <?php $active_page = 'admin_refunds'; include __DIR__ . '/partials/_sidebar.php'; ?>

    <main class="admin-main">
        <?php
        $page_title    = 'Refund Requests';
        $page_subtitle = 'Review and process guest refund requests with multi-step approval workflow';
        include __DIR__ . '/partials/_page_header.php';
        ?>

        <div class="admin-body">
            <?php include __DIR__ . '/views/admin_refunds/_refund_cards.php'; ?>
        </div>
    </main>

    <?php include __DIR__ . '/views/admin_refunds/_modals.php'; ?>

    <script id="adminRefundsPageData" type="application/json"><?php echo json_encode([
        'csrfToken' => $pageData['csrfToken'],
        'noshowHandoff' => $pageData['noshowHandoff'],
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
    <script src="assets/js/pages/admin-refunds.js?v=<?php echo $pageData['jsVersion']; ?>"></script>
</body>
</html>
