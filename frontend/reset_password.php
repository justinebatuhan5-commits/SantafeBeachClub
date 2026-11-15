<?php
require_once __DIR__ . '/../backend/helpers/session_init.php';
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/helpers/error_handler.php';
require_once __DIR__ . '/../backend/helpers/csrf_helper.php';
require_once __DIR__ . '/../backend/helpers/password_helper.php';
require_once __DIR__ . '/../backend/helpers/password_reset_helper.php';
require_once __DIR__ . '/../backend/controllers/auth_controller.php';

/** @var mysqli $conn */
extract(sfbc_handle_reset_password_page($conn), EXTR_SKIP);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-circle-32.png?v=3">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/favicon-circle-180.png?v=3">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password - Santa Fe Beach Club</title>
    <meta name="csrf-token" content="<?php echo htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/pages/auth-recovery.css?v=<?php echo filemtime(__DIR__ . '/assets/css/pages/auth-recovery.css'); ?>">
    <script src="assets/js/security.js" defer></script>
<?php require __DIR__ . '/partials/_responsive.php'; ?>
</head>
<body>
<?php require __DIR__ . '/views/auth/_reset_password.php'; ?>
<script src="assets/js/pages/reset-password.js?v=<?php echo filemtime(__DIR__ . '/assets/js/pages/reset-password.js'); ?>" defer></script>
</body>
</html>
