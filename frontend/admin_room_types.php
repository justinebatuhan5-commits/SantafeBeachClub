<?php
require_once __DIR__ . '/../backend/helpers/admin_auth_check.php';
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/controllers/admin_room_types_controller.php';

$roomTypesData = sfbc_handle_admin_room_types_page($conn);
$v = $roomTypesData['versions'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-circle-32.png?v=3">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/favicon-circle-180.png?v=3">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room Types &amp; Photos — Santa Fe Beach Club</title>
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo $v['adminCss']; ?>">
    <link rel="stylesheet" href="assets/css/pages/admin-room-types.css?v=<?php echo $v['pageCss']; ?>">
    <script src="assets/js/site-dialog.js?v=<?php echo $v['dialogJs']; ?>"></script>
    <?php require __DIR__ . '/partials/_responsive.php'; ?>
</head>
<body>
<?php $active_page = 'room_photos'; include __DIR__ . '/partials/_sidebar.php'; ?>

<main class="main-content">
    <?php
    $page_title    = 'Room Types & Photos';
    $page_subtitle = 'Manage the base price and photos shown on the rooms page for each accommodation type.';
    include __DIR__ . '/partials/_page_header.php';
    ?>

    <?php include __DIR__ . '/views/admin_room_types/_cards_and_lightbox.php'; ?>
</main>

<script src="assets/js/pages/admin-room-types.js?v=<?php echo $v['pageJs']; ?>"></script>
</body>
</html>
