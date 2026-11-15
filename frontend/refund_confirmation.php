<?php
declare(strict_types=1);
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/helpers/csrf_helper.php';
require_once __DIR__ . '/../backend/controllers/refund_confirmation_controller.php';

extract(sfbc_handle_refund_confirmation_page($conn), EXTR_SKIP);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Confirm GCash Refund</title>
    <link rel="stylesheet" href="assets/css/pages/refund-confirmation.css?v=<?= filemtime(__DIR__ . '/assets/css/pages/refund-confirmation.css') ?>">
    <?php require __DIR__ . '/partials/_responsive.php'; ?>
</head>
<body>
<?php require __DIR__ . '/views/refund_confirmation/_card.php'; ?>
</body>
</html>
