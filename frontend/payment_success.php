<?php
/**
 * payment_success.php
 * Guest landing page when returning from PayMongo hosted checkout.
 */

require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/services/mailer.php';
require_once __DIR__ . '/../backend/services/paymongo.php';
require_once __DIR__ . '/../backend/services/paymongo_confirmation.php';
require_once __DIR__ . '/../backend/controllers/payment_success_controller.php';

extract(sfbc_handle_payment_success_page($conn), EXTR_SKIP);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-circle-32.png?v=3">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/favicon-circle-180.png?v=3">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Status — Santa Fe Beach Club</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/pages/payment-success.css?v=<?php echo filemtime(__DIR__ . '/assets/css/pages/payment-success.css'); ?>">
<?php require __DIR__ . '/partials/_responsive.php'; ?>
</head>
<body>

<?php require __DIR__ . '/views/payment_success/_card.php'; ?>

</body>
</html>
