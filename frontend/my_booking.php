<?php
/**
 * my_booking.php — Guest Portal
 * Guests enter email + booking reference to view their booking details,
 * download their QR code, cancel, or rebook.
 */

require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/helpers/security_headers.php';
require_once __DIR__ . '/../backend/helpers/csrf_helper.php';
require_once __DIR__ . '/../backend/helpers/guest_auth_helper.php';
require_once __DIR__ . '/../backend/helpers/recaptcha_helper.php';
require_once __DIR__ . '/../backend/libs/phpqrcode/phpqrcode.php';
require_once __DIR__ . '/../backend/controllers/my_booking_controller.php';

extract(sfbc_handle_my_booking_page($conn), EXTR_SKIP);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-circle-32.png?v=3">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/favicon-circle-180.png?v=3">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Booking &mdash; Santa Fe Beach Club</title>
    <link rel="stylesheet" href="assets/css/tailwind.css?v=<?php echo (int) filemtime(__DIR__ . '/assets/css/tailwind.css'); ?>">
    <link rel="stylesheet" href="assets/css/styles.css?v=<?php echo (int)@filemtime(__DIR__ . '/assets/css/styles.css'); ?>">
    <link rel="stylesheet" href="assets/css/navbar.css?v=<?php echo (int)@filemtime(__DIR__ . '/assets/css/navbar.css'); ?>">
    <script src="https://www.google.com/recaptcha/api.js?render=<?php echo rawurlencode(RECAPTCHA_SITE_KEY); ?>" async defer></script>
    <link rel="stylesheet" href="assets/css/my-booking.css?v=<?php echo (int) filemtime(__DIR__ . '/assets/css/my-booking.css'); ?>">
    <link rel="stylesheet" href="assets/css/pages/my-booking.css?v=<?php echo (int) filemtime(__DIR__ . '/assets/css/pages/my-booking.css'); ?>">
    <script src="assets/js/site-dialog.js?v=1"></script>
<?php require __DIR__ . '/partials/_responsive.php'; ?>
</head>
<body class="booking-page<?php echo !$booking ? ' booking-lookup-page' : ' booking-account-page'; ?>">

<div class="portal-wrapper">
    <div class="booking-slideshow" aria-hidden="true">
        <div class="booking-slide"></div>
        <div class="booking-slide"></div>
        <div class="booking-slide"></div>
        <div class="booking-slide"></div>
    </div>

<?php if (!$booking): ?>
    <?php require __DIR__ . '/views/my_booking/_lookup_form.php'; ?>
<?php else: ?>
    <?php require __DIR__ . '/views/my_booking/_portal_dashboard.php'; ?>
<?php endif; ?>

</div><!-- /.portal-wrapper -->

<footer style="background:#1E293B; color:#94A3B8; text-align:center; padding:20px 16px; font-size:12px;">
    &copy; <?php echo date('Y'); ?> Santa Fe Beach Club. All rights reserved. &bull; Developed by <strong style="color:#CBD5E1;">Justine Batuhan</strong>
</footer>

<?php require __DIR__ . '/views/my_booking/_confirm_modal.php'; ?>

<script src="assets/js/pages/my-booking.js?v=<?php echo (int) filemtime(__DIR__ . '/assets/js/pages/my-booking.js'); ?>"></script>
</body>
</html>
