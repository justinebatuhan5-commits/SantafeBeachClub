<?php
require_once __DIR__ . '/../backend/helpers/admin_auth_check.php';
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/helpers/cloudinary_helper.php';
require_once __DIR__ . '/../backend/helpers/security_logger.php';
require_once __DIR__ . '/../backend/controllers/admin_gallery_controller.php';

$galleryData = sfbc_handle_admin_gallery_page($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-circle-32.png?v=3">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/favicon-circle-180.png?v=3">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gallery — Santa Fe Beach Club</title>
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="assets/css/pages/admin-gallery.css?v=<?php echo filemtime(__DIR__ . '/assets/css/pages/admin-gallery.css'); ?>">
    <script src="assets/js/site-dialog.js?v=1"></script>
    <?php require __DIR__ . '/partials/_responsive.php'; ?>
</head>
<body>
    <?php $active_page = 'gallery'; include __DIR__ . '/partials/_sidebar.php'; ?>

    <main class="main-content">
        <?php
        $page_title = 'Gallery';
        $page_subtitle = 'Manage photos showcased on the public gallery page.';
        $header_extra_html = '
            <button type="button" class="btn-primary gallery-add-btn" data-open-gallery-modal>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Add Photo
            </button>
        ';
        include __DIR__ . '/partials/_page_header.php';
        include __DIR__ . '/views/admin_gallery/_gallery_grid_and_modal.php';
        ?>
    </main>

    <script src="assets/js/pages/admin-gallery.js?v=<?php echo filemtime(__DIR__ . '/assets/js/pages/admin-gallery.js'); ?>"></script>
</body>
</html>
