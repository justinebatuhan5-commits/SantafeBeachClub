<?php
declare(strict_types=1);

require_once __DIR__ . '/../backend/helpers/admin_auth_check.php';
require_once __DIR__ . '/../backend/helpers/rbac_helper.php';
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/services/chatbot_service.php';
require_once __DIR__ . '/partials/_inquiry_reply_assets.php';
require_once __DIR__ . '/../backend/controllers/admin_chatbot_controller.php';

RBAC::requireRole('admin');

/** @var mysqli $conn */
$admin = (string)($_SESSION['admin_username'] ?? 'admin');
$active_page = 'chatbot';
extract(sfbc_handle_admin_chatbot_page($conn, $admin), EXTR_SKIP);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Concierge Management &amp; Analytics &mdash; Santa Fe Beach Club</title>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-circle-32.png?v=3">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo (int)filemtime(__DIR__ . '/assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="assets/css/pages/admin-chatbot.css?v=<?php echo (int)filemtime(__DIR__ . '/assets/css/pages/admin-chatbot.css'); ?>">
    <?php require __DIR__ . '/partials/_responsive.php'; ?>
<?php sf_reply_ui_render_styles(); ?>
</head>
<body>
<?php require_once __DIR__ . '/partials/_sidebar.php'; ?>

<main class="main-content">
    <?php
    $page_title = 'AI Concierge & Virtual Guest Assistant';
    $page_subtitle = 'Phase 3 Administration: Monitor guest conversations, manage the multilingual knowledge base, review analytics, and resolve human support escalations.';
    include __DIR__ . '/partials/_page_header.php';
    ?>

    <div class="admin-body">
        <?php
        $guest_communications_active = 'chatbot';
        include __DIR__ . '/partials/_guest_communications_tabs.php';
        ?>

        <?php if (!$tablesReady): ?>
            <div class="alert alert-error" style="margin-bottom:18px;">
                <strong>Database migration pending:</strong> Run <code>php backend/scripts/migrate.php</code> from the command line to create the <code>chatbot_conversations</code>, <code>chatbot_messages</code>, and <code>chatbot_knowledge</code> tables.
            </div>
        <?php endif; ?>

        <?php if ($flashSuccess !== ''): ?>
            <div class="alert alert-success" style="margin-bottom:16px;"><?php echo htmlspecialchars($flashSuccess); ?></div>
        <?php endif; ?>
        <?php if ($flashError !== ''): ?>
            <div class="alert alert-error" style="margin-bottom:16px;"><?php echo htmlspecialchars($flashError); ?></div>
        <?php endif; ?>

        <div class="ai-tabs" role="tablist">
            <button type="button" id="ai-tab-btn-analytics" class="ai-tab-btn <?php echo $activeTab === 'analytics' ? 'active' : ''; ?>" data-ai-tab="analytics">
                Chatbot Analytics
            </button>
            <button type="button" id="ai-tab-btn-conversations" class="ai-tab-btn <?php echo $activeTab === 'conversations' ? 'active' : ''; ?>" data-ai-tab="conversations">
                Conversation Monitoring <span class="ai-tab-badge"><?php echo count($recentConversations); ?></span>
            </button>
            <button type="button" id="ai-tab-btn-knowledge" class="ai-tab-btn <?php echo $activeTab === 'knowledge' ? 'active' : ''; ?>" data-ai-tab="knowledge">
                Knowledge Base Management <span class="ai-tab-badge"><?php echo count($kbRows); ?></span>
            </button>
            <button type="button" id="ai-tab-btn-support" class="ai-tab-btn <?php echo $activeTab === 'support' ? 'active' : ''; ?>" data-ai-tab="support">
                Human Support &amp; Escalations
                <span class="ai-tab-badge <?php echo $metrics['open_handoffs'] > 0 ? 'alert-badge' : ''; ?>"><?php echo (int)$metrics['open_handoffs']; ?> open</span>
            </button>
            <button type="button" id="ai-tab-btn-settings" class="ai-tab-btn <?php echo $activeTab === 'settings' ? 'active' : ''; ?>" data-ai-tab="settings">
                Settings &amp; Privacy
            </button>
        </div>

        <?php require __DIR__ . '/views/admin_chatbot/_tab_analytics.php'; ?>
        <?php require __DIR__ . '/views/admin_chatbot/_tab_conversations.php'; ?>
        <?php require __DIR__ . '/views/admin_chatbot/_tab_knowledge.php'; ?>
        <?php require __DIR__ . '/views/admin_chatbot/_tab_support.php'; ?>
        <?php require __DIR__ . '/views/admin_chatbot/_tab_settings.php'; ?>
    </div>
</main>

<script src="assets/js/pages/admin-chatbot.js?v=<?php echo (int)filemtime(__DIR__ . '/assets/js/pages/admin-chatbot.js'); ?>" defer></script>
<?php sf_reply_ui_render_script(get_csrf_token(), ['support_tab_selector' => '#ai-tab-support', 'badge_style' => 'chatbot']); ?>
</body>
</html>
