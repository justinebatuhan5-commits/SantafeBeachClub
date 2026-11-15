<?php
require_once __DIR__ . '/../backend/helpers/auth_check.php';
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/services/chatbot_service.php';
require_once __DIR__ . '/partials/_inquiry_reply_assets.php';
$active_page = 'inquiries';

// Handle Contact Inquiry status / delete actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    require_csrf_token();
    $inquiry_id = (int)($_POST['inquiry_id'] ?? 0);
    $action = (string)$_POST['action'];
    sf_inquiry_handle_ticket_post($conn, $action, $inquiry_id);

    header("Location: admin_inquiries");
    exit;
}

// Fetch Contact Us page inquiries only (separated from "Need a human?" Live Support Chats)
$inquiriesList = sf_inquiry_fetch_channel_tickets($conn, 'contact');
$inquiryIds = array_map(static fn(array $r): int => (int)$r['id'], $inquiriesList);
$inquiryThreads = sf_inquiry_get_threads($conn, $inquiryIds);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-circle-32.png?v=3">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/favicon-circle-180.png?v=3">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inquiries - Admin Dashboard</title>
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/assets/css/admin.css'); ?>">
<?php sf_reply_ui_render_styles(); ?>
<?php require __DIR__ . '/partials/_responsive.php'; ?>
</head>
<body>
    <?php
    $active_page = 'inquiries';
    require_once 'partials/_sidebar.php';
    ?>
    <div class="admin-main">
        <?php
        $is_admin_communications = ($_SESSION['admin_role'] ?? '') === 'admin';
        $page_title = $is_admin_communications ? 'Reviews & Inquiries' : 'Guest Inquiries';
        $page_subtitle = $is_admin_communications
            ? 'Review Contact page messages and reply via email. Chatbot "Need a human?" chats have their own Live Support Chats page.'
            : 'Review and reply to guest messages submitted through the Contact Us page.';
        $header_extra_html = '
            <div style="font-size: 13px; font-weight: 600; color: var(--text-muted); display: flex; align-items: center; gap: 6px; padding: 6px 12px; background: var(--card-bg); border: 1px solid var(--border); border-radius: var(--radius-sm);">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                <span>' . date('l, M j, Y') . '</span>
            </div>
        ';
        include __DIR__ . '/partials/_page_header.php';
        ?>

        <div class="admin-body">
            <?php if ($is_admin_communications): ?>
                <?php $guest_communications_active = 'inquiries'; include __DIR__ . '/partials/_guest_communications_tabs.php'; ?>
            <?php endif; ?>

            <?php sf_reply_ui_render_ticket_cards($inquiriesList, $inquiryThreads, [
                'channel' => 'contact',
                'empty_text' => 'No guest inquiries submitted through the Contact Us page.',
            ]); ?>
        </div><!-- end admin-body -->
    </div><!-- end admin-main -->

<?php sf_reply_ui_render_script(get_csrf_token(), ['badge_style' => 'inquiries']); ?>
</body>
</html>
