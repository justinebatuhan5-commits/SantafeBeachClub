<?php
require_once __DIR__ . '/../backend/helpers/session_init.php';
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/helpers/security_headers.php';
require_once __DIR__ . '/../backend/helpers/csrf_helper.php';
require_once __DIR__ . '/../backend/helpers/rate_limiter.php';
require_once __DIR__ . '/../backend/helpers/security_logger.php';
require_once __DIR__ . '/../backend/helpers/otp_helper.php';
require_once __DIR__ . '/../backend/helpers/admin_session_helper.php';
require_once __DIR__ . '/../backend/helpers/device_trust_helper.php';
require_once __DIR__ . '/../backend/controllers/auth_controller.php';

/** @var mysqli $conn */
extract(sfbc_handle_verify_otp_page($conn), EXTR_SKIP);
$portalBodyClass = (($loginSource ?? 'staff') === 'admin') ? 'portal-admin' : 'portal-staff';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-circle-32.png?v=3">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/favicon-circle-180.png?v=3">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Authentication - Santa Fe Beach Club</title>
    <meta name="csrf-token" content="<?php echo htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/pages/verify-otp.css?v=<?php echo filemtime(__DIR__ . '/assets/css/pages/verify-otp.css'); ?>">
    <script src="assets/js/security.js" defer></script>
<?php require __DIR__ . '/partials/_responsive.php'; ?>
</head>
<body class="<?php echo $portalBodyClass; ?>">
<?php require __DIR__ . '/views/auth/_verify_otp.php'; ?>
<script src="assets/js/pages/verify-otp.js?v=<?php echo filemtime(__DIR__ . '/assets/js/pages/verify-otp.js'); ?>" defer></script>
</body>
</html>
