<?php
/**
 * invoice.php — Official Printable Invoice & Booking Voucher
 * Accessible by guests with token validation (or admin session)
 */

require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/helpers/security_headers.php';
require_once __DIR__ . '/../backend/libs/phpqrcode/phpqrcode.php';
require_once __DIR__ . '/../backend/controllers/invoice_controller.php';

extract(sfbc_handle_invoice_page($conn), EXTR_SKIP);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-circle-32.png?v=3">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/favicon-circle-180.png?v=3">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice & Voucher — <?php echo htmlspecialchars($booking_ref); ?> — Santa Fe Beach Club</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/pages/invoice.css?v=<?php echo filemtime(__DIR__ . '/assets/css/pages/invoice.css'); ?>">
    <?php require __DIR__ . '/partials/_responsive.php'; ?>
</head>
<body data-auto-print="<?php echo $autoPrint ? '1' : '0'; ?>">

<?php require __DIR__ . '/views/invoice/_voucher.php'; ?>

<script src="assets/js/pages/invoice.js?v=<?php echo filemtime(__DIR__ . '/assets/js/pages/invoice.js'); ?>"></script>
</body>
</html>
