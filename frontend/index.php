<?php
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/helpers/security_headers.php';
require_once __DIR__ . '/../backend/helpers/csrf_helper.php';
require_once __DIR__ . '/../backend/helpers/cloudinary_helper.php';
require_once __DIR__ . '/../backend/controllers/home_controller.php';

header('Cache-Control: no-store');

extract(sfbc_handle_home_page($conn), EXTR_SKIP);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-circle-32.png?v=3">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/favicon-circle-180.png?v=3">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Book beachfront rooms, duplex suites, and coastal villas at Santa Fe Beach Club in Pantalan, Santa Fe, Bantayan Island, Cebu.">
    <meta name="theme-color" content="#7C533C">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Santa Fe Beach Club - Escape to Paradise</title>
    <link rel="stylesheet" href="assets/css/tailwind.css?v=<?php echo (int) filemtime(__DIR__ . '/assets/css/tailwind.css'); ?>">
    <link rel="stylesheet" href="assets/css/styles.css?v=<?php echo (int) filemtime(__DIR__ . '/assets/css/styles.css'); ?>">
    <link rel="stylesheet" href="assets/css/navbar.css?v=<?php echo (int) filemtime(__DIR__ . '/assets/css/navbar.css'); ?>">
    <link rel="stylesheet" href="assets/css/home.css?v=<?php echo (int) filemtime(__DIR__ . '/assets/css/home.css'); ?>">
    <script src="assets/js/dark-mode-toggle.js" defer></script>
    <script src="assets/js/site-dialog.js?v=1"></script>
    <?php require __DIR__ . '/partials/_responsive.php'; ?>
</head>
<body class="home-page">

    <?php $active_nav = 'home'; require __DIR__ . '/partials/_navbar.php'; ?>

    <main>
        <?php require __DIR__ . '/views/index/_hero_search.php'; ?>
        <?php require __DIR__ . '/views/index/_stays_story.php'; ?>
    </main>

    <?php require __DIR__ . '/partials/_footer.php'; ?>

    <?php require __DIR__ . '/views/index/_review_modal.php'; ?>

    <script>
    window.SFBC_HOME_CONFIG = {
        today: <?php echo json_encode(date('Y-m-d')); ?>
    };
    </script>
    <script src="assets/js/pages/index.js?v=<?php echo (int) filemtime(__DIR__ . '/assets/js/pages/index.js'); ?>"></script>
</body>
</html>
