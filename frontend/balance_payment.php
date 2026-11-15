<?php
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/helpers/csrf_helper.php';
require_once __DIR__ . '/../backend/services/paymongo.php';
require_once __DIR__ . '/../backend/services/paymongo_confirmation.php';
require_once __DIR__ . '/../backend/helpers/balance_payment_helper.php';
require_once __DIR__ . '/../backend/controllers/balance_payment_controller.php';

extract(sfbc_handle_balance_payment_page($conn), EXTR_SKIP);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Booking Balance — Santa Fe Beach Club</title>
    <link rel="stylesheet" href="assets/css/pages/balance-payment.css?v=<?= filemtime(__DIR__ . '/assets/css/pages/balance-payment.css') ?>">
    <?php require __DIR__ . '/partials/_responsive.php'; ?>
</head>
<body>
<?php require __DIR__ . '/views/balance_payment/_card.php'; ?>
</body>
</html>
