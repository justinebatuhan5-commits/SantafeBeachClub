<?php
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/helpers/csrf_helper.php';
require_once __DIR__ . '/../backend/controllers/refund_details_controller.php';

extract(sfbc_handle_refund_details_page($conn), EXTR_SKIP);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>GCash Refund Details</title>
    <link rel="stylesheet" href="assets/css/pages/refund-details.css?v=<?= filemtime(__DIR__ . '/assets/css/pages/refund-details.css') ?>">
    <?php require __DIR__ . '/partials/_responsive.php'; ?>
</head>
<body>
<?php require __DIR__ . '/views/refund_details/_card.php'; ?>
<script src="assets/js/pages/refund-details.js?v=<?= filemtime(__DIR__ . '/assets/js/pages/refund-details.js') ?>"></script>
</body>
</html>
