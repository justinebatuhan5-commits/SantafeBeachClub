<?php
require_once __DIR__ . '/../backend/helpers/auth_check.php';
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/services/chatbot_service.php';
require_once __DIR__ . '/partials/_inquiry_reply_assets.php';
$active_page = 'live_support';

// Handle Live Support Chat status / reopen / delete actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    require_csrf_token();
    $inquiry_id = (int)($_POST['inquiry_id'] ?? 0);
    $action = (string)$_POST['action'];
    sf_inquiry_handle_ticket_post($conn, $action, $inquiry_id);

    header("Location: admin_live_support");
    exit;
}

// Fetch only escalated "Need a human?" Live Support Chats
$liveChatList = sf_inquiry_fetch_channel_tickets($conn, 'live_chat');
$liveChatIds = array_map(static fn(array $r): int => (int)$r['id'], $liveChatList);
$liveChatThreads = sf_inquiry_get_threads($conn, $liveChatIds);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-circle-32.png?v=3">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/favicon-circle-180.png?v=3">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Support Chats - Santa Fe Beach Club</title>
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/assets/css/admin.css'); ?>">
<?php sf_reply_ui_render_styles(); ?>
<?php require __DIR__ . '/partials/_responsive.php'; ?>
</head>
<body>
    <?php
    $active_page = 'live_support';
    require_once 'partials/_sidebar.php';
    ?>
    <div class="admin-main">
        <?php
        $is_admin_communications = ($_SESSION['admin_role'] ?? '') === 'admin';
        $page_title = 'Live Support Chats';
        $page_subtitle = 'Chat live with guests who requested Reception Staff through "Need a human?" in the AI Concierge.';
        $header_extra_html = '
            <div style="font-size: 13px; font-weight: 600; color: var(--text-muted); display: flex; align-items: center; gap: 6px; padding: 6px 12px; background: var(--card-bg); border: 1px solid var(--border); border-radius: var(--radius-sm);">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                <span>Real-time Two-Way Queue</span>
            </div>
        ';
        include __DIR__ . '/partials/_page_header.php';
        ?>

        <div class="admin-body">
            <?php if ($is_admin_communications): ?>
                <?php $guest_communications_active = 'live_support'; include __DIR__ . '/partials/_guest_communications_tabs.php'; ?>
            <?php endif; ?>

            <?php sf_reply_ui_render_ticket_cards($liveChatList, $liveChatThreads, [
                'channel' => 'live_chat',
                'show_filters' => true,
                'empty_text' => 'No live support chats requested via "Need a human?" right now.',
            ]); ?>
        </div><!-- end admin-body -->
    </div><!-- end admin-main -->

<?php sf_reply_ui_render_script(get_csrf_token(), ['badge_style' => 'inquiries']); ?>
</body>
</html>
