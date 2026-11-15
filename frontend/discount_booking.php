<?php
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/helpers/csrf_helper.php';
require_once __DIR__ . '/../backend/helpers/security_headers.php';
require_once __DIR__ . '/../backend/controllers/discount_booking_controller.php';

$pageData = sfbc_handle_discount_booking_page($conn);
if (!empty($pageData['needsAccessBootstrap'])) {
    require __DIR__ . '/views/discount_booking/_access_redirect.php';
    exit;
}
extract($pageData, EXTR_SKIP);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Your discount request · Santa Fe Beach Club</title>
    <link rel="stylesheet" href="assets/css/discounts.css?v=<?= filemtime(__DIR__ . '/assets/css/discounts.css') ?>">
    <?php require __DIR__ . '/partials/_responsive.php'; ?>
</head>
<body class="discount-page">
<?php require __DIR__ . '/views/discount_booking/_portal.php'; ?>
</body>
</html>
