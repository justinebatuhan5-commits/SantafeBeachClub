<?php
require_once __DIR__ . '/../backend/helpers/admin_auth_check.php';
require_once __DIR__ . '/../backend/helpers/password_helper.php';
require_once __DIR__ . '/../backend/helpers/security_logger.php';
require_once __DIR__ . '/../backend/controllers/auth_controller.php';

/** @var mysqli $conn */
extract(sfbc_handle_admin_reauth_page($conn), EXTR_SKIP);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Confirm Administrator Password</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/pages/admin-reauth.css?v=<?php echo filemtime(__DIR__ . '/assets/css/pages/admin-reauth.css'); ?>">
</head>
<body>
<?php require __DIR__ . '/views/auth/_admin_reauth.php'; ?>
</body>
</html>
