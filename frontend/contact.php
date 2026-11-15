<?php
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/helpers/security_headers.php';
require_once __DIR__ . '/../backend/helpers/csrf_helper.php';
require_once __DIR__ . '/../backend/helpers/inquiry_guard.php';
require_once __DIR__ . '/../backend/helpers/recaptcha_helper.php';
require_once __DIR__ . '/../backend/controllers/contact_controller.php';

extract(sfbc_handle_contact_page($conn), EXTR_SKIP);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-circle-32.png?v=3">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/favicon-circle-180.png?v=3">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Contact Santa Fe Beach Club in Bantayan Island, Cebu. Call 0950 522 3146, email reception, or send a direct stay inquiry.">
    <title>Contact Us - Santa Fe Beach Club</title>
    <meta name="csrf-token" content="<?php echo htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="assets/css/tailwind.css?v=<?php echo (int) filemtime(__DIR__ . '/assets/css/tailwind.css'); ?>">
    <link rel="stylesheet" href="assets/css/styles.css?v=<?php echo (int) filemtime(__DIR__ . '/assets/css/styles.css'); ?>">
    <link rel="stylesheet" href="assets/css/navbar.css?v=<?php echo (int) filemtime(__DIR__ . '/assets/css/navbar.css'); ?>">
    <link rel="stylesheet" href="assets/css/contact.css?v=<?php echo (int) filemtime(__DIR__ . '/assets/css/contact.css'); ?>">
    <script src="assets/js/security.js" defer></script>
<?php require __DIR__ . '/partials/_responsive.php'; ?>
</head>
<body class="contact-page">
    <?php $active_nav = 'contact'; require __DIR__ . '/partials/_navbar.php'; ?>

    <?php require __DIR__ . '/views/contact/_content.php'; ?>

    <?php require __DIR__ . '/partials/_footer.php'; ?>
    <script src="https://www.google.com/recaptcha/api.js?render=<?php echo rawurlencode(RECAPTCHA_SITE_KEY); ?>" defer></script>
    <script src="assets/js/inquiry.js" defer></script>
    <script src="assets/js/pages/contact.js?v=<?php echo (int) filemtime(__DIR__ . '/assets/js/pages/contact.js'); ?>" defer></script>
</body>
</html>
