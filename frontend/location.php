<?php
require_once __DIR__ . '/../backend/helpers/security_headers.php';
require_once __DIR__ . '/../backend/helpers/csrf_helper.php';
header('Cache-Control: no-store');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-circle-32.png?v=3">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/favicon-circle-180.png?v=3">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Find Santa Fe Beach Club in Pantalan, Santa Fe, Cebu and get practical arrival information for your stay.">
    <title>Location &amp; Directions - Santa Fe Beach Club</title>
    <link rel="stylesheet" href="assets/css/tailwind.css?v=<?php echo (int) filemtime(__DIR__ . '/assets/css/tailwind.css'); ?>">
    <link rel="stylesheet" href="assets/css/styles.css?v=<?php echo (int) filemtime(__DIR__ . '/assets/css/styles.css'); ?>">
    <link rel="stylesheet" href="assets/css/navbar.css?v=<?php echo (int) filemtime(__DIR__ . '/assets/css/navbar.css'); ?>">
    <link rel="stylesheet" href="assets/css/location.css?v=<?php echo (int) filemtime(__DIR__ . '/assets/css/location.css'); ?>">
    <?php require __DIR__ . '/partials/_responsive.php'; ?>
</head>
<body class="location-page">
    <?php $active_nav = 'location'; require __DIR__ . '/partials/_navbar.php'; ?>
    <?php include __DIR__ . '/views/location/_content.php'; ?>
    <?php require __DIR__ . '/partials/_footer.php'; ?>
    <script src="assets/js/location.js?v=<?php echo (int) filemtime(__DIR__ . '/assets/js/location.js'); ?>" defer></script>
</body>
</html>
