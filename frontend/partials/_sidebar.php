<?php
/**
 * _sidebar.php — Role-aware luxury sidebar component for Admin & Reception.
 */
if (!isset($conn)) {
    require_once __DIR__ . '/../../backend/config/db.php';
}
require_once __DIR__ . '/../../backend/helpers/sidebar_badge_helper.php';

$_page          = $active_page ?? '';
$_sidebarState  = sfbc_resolve_sidebar_state($conn ?? null);
$_role          = $_sidebarState['role'];
$_user          = $_sidebarState['user'];
$_sidebar_photo = $_sidebarState['sidebar_photo'];
$_is_admin      = $_sidebarState['is_admin'];
$_unread_count      = $_sidebarState['unread_count'];
$_unread_inquiries  = $_sidebarState['unread_inquiries'];
$_unread_live_chats = $_sidebarState['unread_live_chats'];
$_pending_bookings  = $_sidebarState['pending_bookings'];
$_pending_payments  = $_sidebarState['pending_payments'];
$_pending_refunds   = $_sidebarState['pending_refunds'];
?>
<script>
(function() {
    document.documentElement.classList.add('sbc-sidebar-dropdown', 'sbc-sidebar-docked');
})();
</script>

<?php
if ($_is_admin) {
    include __DIR__ . '/sidebar/_admin_nav.php';
} else {
    include __DIR__ . '/sidebar/_reception_nav.php';
}
?>

<script src="assets/js/dark-mode-toggle.js?v=4"></script>
<link rel="stylesheet" href="assets/css/sidebar-workspace.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/sidebar-workspace.css'); ?>">
<link rel="stylesheet" href="assets/css/components/sidebar.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/components/sidebar.css'); ?>">
<script src="assets/js/sidebar-workspace.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/sidebar-workspace.js'); ?>"></script>
<script src="assets/js/sidebar-toggle.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/sidebar-toggle.js'); ?>"></script>
<script src="assets/js/security.js?v=4"></script>

<?php include __DIR__ . '/sidebar/_modals.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
<script>
window.SFBC_SIDEBAR_CONFIG = <?php echo json_encode(['isAdmin' => $_is_admin], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>
<script src="assets/js/components/sidebar.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/components/sidebar.js'); ?>"></script>

<?php include __DIR__ . '/_staff_assistant_widget.php'; ?>
