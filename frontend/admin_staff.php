<?php
require_once __DIR__ . '/../backend/helpers/admin_auth_check.php';
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/controllers/admin_staff_controller.php';

$staffData = sfbc_handle_admin_staff_page($conn);
$v = $staffData['versions'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-circle-32.png?v=3">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/favicon-circle-180.png?v=3">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Management — Santa Fe Beach Club</title>
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo $v['adminCss']; ?>">
    <link rel="stylesheet" href="assets/css/pages/admin-staff.css?v=<?php echo $v['pageCss']; ?>">
    <?php require __DIR__ . '/partials/_responsive.php'; ?>
</head>
<body>
    <?php $active_page = 'staff'; include __DIR__ . '/partials/_sidebar.php'; ?>

    <main class="admin-main">
        <?php
        $page_title = 'Staff Management';
        $page_subtitle = 'Manage receptionist and admin accounts, profile photos, roles, and MFA verification emails.';
        include __DIR__ . '/partials/_page_header.php';
        ?>

        <?php include __DIR__ . '/views/admin_staff/_directory_and_security.php'; ?>
    </main>

    <?php include __DIR__ . '/views/admin_staff/_modals.php'; ?>

    <script src="assets/js/pages/admin-staff.js?v=<?php echo $v['pageJs']; ?>"></script>
</body>
</html>
