<?php
require_once __DIR__ . '/../backend/helpers/auth_check.php';
require_once __DIR__ . '/../backend/config/db.php';
if (!in_array($_SESSION['admin_role'] ?? '', ['admin', 'receptionist'], true)) {
    http_response_code(403);
    exit('Staff access required.');
}
header('Referrer-Policy: no-referrer');
require_once __DIR__ . '/../backend/controllers/discount_requests_controller.php';

$discountData = sfbc_handle_discount_requests_page($conn);
$v = $discountData['versions'];
$id = $discountData['id'];
$active_page = 'discount_requests';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-circle-32.png?v=3">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/favicon-circle-180.png?v=3">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discount Requests — Santa Fe Beach Club</title>
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo $v['adminCss']; ?>">
    <link rel="stylesheet" href="assets/css/discounts.css?v=<?php echo $v['discountsCss']; ?>">
    <?php require __DIR__ . '/partials/_responsive.php'; ?>
</head>
<body>

<?php include __DIR__ . '/partials/_sidebar.php'; ?>

<main class="admin-main">
    <?php
    $page_title    = 'Discount Requests';
    $page_subtitle = $id
        ? ('Reviewing Request REF-' . str_pad((string)$id, 3, '0', STR_PAD_LEFT))
        : 'Verify guest eligibility, review submitted IDs and receipts, and confirm bookings';
    include __DIR__ . '/partials/_page_header.php';
    ?>

    <div class="admin-body">
        <div class="discount-shell" style="max-width: 100%; margin: 0; padding: 0;">
            <?php if ($id): ?>
            <div class="discount-nav-bar" style="margin-bottom: 20px;">
                <div class="discount-breadcrumb">
                    <a href="discount_requests.php">&larr; Back to All Discount Requests</a>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($discountData['message'] !== ''): ?>
                <div class="discount-note" role="status"><span><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg></span> <div><?php echo htmlspecialchars($discountData['message'], ENT_QUOTES, 'UTF-8'); ?></div></div>
            <?php endif; ?>
            <?php if ($discountData['error'] !== ''): ?>
                <div class="discount-note discount-error" role="alert"><span><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></span> <div><?php echo htmlspecialchars($discountData['error'], ENT_QUOTES, 'UTF-8'); ?></div></div>
            <?php endif; ?>

            <?php if ($discountData['detail']): ?>
                <?php include __DIR__ . '/views/discount_requests/_detail_view.php'; ?>
            <?php else: ?>
                <?php include __DIR__ . '/views/discount_requests/_list_view.php'; ?>
            <?php endif; ?>
        </div>
    </div>
</main>

<div id="imageModal" class="image-modal-overlay" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="image-modal-content">
        <div class="image-modal-header">
            <h3 id="imageModalTitle">Document Preview</h3>
            <button type="button" class="image-modal-close" id="closeImageModal" aria-label="Close">&times;</button>
        </div>
        <div class="image-modal-body">
            <div id="imageModalLoading" class="document-modal-message" role="status">Loading document&hellip;</div>
            <img id="imageModalImage" alt="Document preview" hidden>
            <iframe id="imageModalPdf" title="PDF document preview" hidden></iframe>
            <div id="imageModalError" class="document-modal-message" role="alert" hidden>
                <div class="document-modal-icon" aria-hidden="true"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></div>
                <strong>Document unavailable</strong>
                <p id="imageModalErrorDetail">The file could not be loaded.</p>
            </div>
        </div>
    </div>
</div>

<script>
    window.SFBC_DISCOUNT_REQUESTS_CONFIG = <?php echo json_encode([
        'extraAuditCount' => max(0, $discountData['historyCount'] - 5),
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
</script>
<script src="assets/js/pages/discount-requests.js?v=<?php echo $v['pageJs']; ?>"></script>
</body>
</html>
