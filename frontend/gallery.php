<?php
require_once __DIR__ . '/../backend/helpers/security_headers.php';
require_once __DIR__ . '/../backend/helpers/csrf_helper.php';
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/helpers/cloudinary_helper.php';
require_once __DIR__ . '/../backend/controllers/gallery_controller.php';

extract(sfbc_handle_gallery_page($conn), EXTR_SKIP);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-circle-32.png?v=3">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/favicon-circle-180.png?v=3">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Explore photography of Santa Fe Beach Club — golden shoreline views, sunlit mornings, and island moments in Santa Fe, Cebu.">
    <title>Gallery - Santa Fe Beach Club</title>
    <link rel="stylesheet" href="assets/css/tailwind.css?v=<?php echo (int) filemtime(__DIR__ . '/assets/css/tailwind.css'); ?>">
    <link rel="stylesheet" href="assets/css/styles.css?v=<?php echo (int) filemtime(__DIR__ . '/assets/css/styles.css'); ?>">
    <link rel="stylesheet" href="assets/css/navbar.css?v=<?php echo (int) filemtime(__DIR__ . '/assets/css/navbar.css'); ?>">
    <link rel="stylesheet" href="assets/css/gallery.css?v=<?php echo (int) filemtime(__DIR__ . '/assets/css/gallery.css'); ?>">
<?php require __DIR__ . '/partials/_responsive.php'; ?>
</head>
<body class="gallery-page">
    <?php $active_nav = 'gallery'; require __DIR__ . '/partials/_navbar.php'; ?>

    <?php require __DIR__ . '/views/gallery/_showcase.php'; ?>

    <?php require __DIR__ . '/partials/_footer.php'; ?>

    <?php require __DIR__ . '/views/gallery/_lightbox.php'; ?>

    <script src="assets/js/pages/gallery.js?v=<?php echo (int) filemtime(__DIR__ . '/assets/js/pages/gallery.js'); ?>"></script>
</body>
</html>
