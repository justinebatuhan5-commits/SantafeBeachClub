<?php
require_once __DIR__ . '/../backend/helpers/auth_check.php';
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/controllers/guests_controller.php';

$guestsData = sfbc_handle_guests_page($conn);
$v = $guestsData['versions'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-circle-32.png?v=3">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/favicon-circle-180.png?v=3">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guest Management — Santa Fe Beach Club</title>
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo $v['adminCss']; ?>">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?php echo $v['dashboardCss']; ?>">
    <link rel="stylesheet" href="assets/css/pages/guests.css?v=<?php echo $v['pageCss']; ?>">
    <script src="assets/js/site-dialog.js?v=1"></script>
    <?php require __DIR__ . '/partials/_responsive.php'; ?>
</head>
<body>

<?php $active_page = 'guests'; include __DIR__ . '/partials/_sidebar.php'; ?>

<main class="main-content">
<?php
$page_title    = 'Guest Management';
$page_subtitle = 'Guest directory, profiles and booking history';
$header_extra_html = '
    <div class="search-wrapper" style="position:relative;">
        <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" placeholder="Search by name or email..." class="search-input" id="guestSearch">
    </div>
';
include __DIR__ . '/partials/_page_header.php';
?>

<?php include __DIR__ . '/views/guests/_directory.php'; ?>
</main>

<?php include __DIR__ . '/views/guests/_profile_modal.php'; ?>

<script src="assets/js/pages/guests.js?v=<?php echo $v['pageJs']; ?>"></script>
</body>
</html>
