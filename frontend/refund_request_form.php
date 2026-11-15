<?php
/**
 * Guest Refund Request Form
 * Allows guests to submit refund requests for their bookings
 */

require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/services/mailer.php';
require_once __DIR__ . '/../backend/helpers/refund_method_helper.php';
require_once __DIR__ . '/../backend/controllers/refund_request_controller.php';

extract(sfbc_handle_refund_request_page($conn), EXTR_SKIP);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-circle-32.png?v=3">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/favicon-circle-180.png?v=3">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Refund - Santa Fe Beach Club</title>
    <link rel="stylesheet" href="assets/css/pages/refund-request.css?v=<?php echo filemtime(__DIR__ . '/assets/css/pages/refund-request.css'); ?>">
    <?php require __DIR__ . '/partials/_responsive.php'; ?>
</head>
<body>
<?php require __DIR__ . '/views/refund_request/_form.php'; ?>
<script src="assets/js/pages/refund-request.js?v=<?php echo filemtime(__DIR__ . '/assets/js/pages/refund-request.js'); ?>"></script>
</body>
</html>
