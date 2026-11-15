<?php
require_once __DIR__ . '/../backend/helpers/admin_auth_check.php';
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/controllers/admin_logs_controller.php';

$logsData = sfbc_handle_admin_logs_page($conn);
$v = $logsData['versions'];
$tab = $logsData['tab'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-circle-32.png?v=3">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/favicon-circle-180.png?v=3">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System &amp; Security Logs — Santa Fe Beach Club</title>
    <meta name="csrf-token" content="<?php echo htmlspecialchars($logsData['csrfToken'], ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo $v['adminCss']; ?>">
    <script src="assets/js/security.js" defer></script>
    <?php require __DIR__ . '/partials/_responsive.php'; ?>
</head>
<body>
    <?php $active_page = 'logs'; include __DIR__ . '/partials/_sidebar.php'; ?>

    <main class="main-content">
        <?php
        $page_title = $tab === 'security' ? 'Security & Audit Logs' : 'Activity Logs';
        $page_subtitle = number_format($logsData['totalRows']) . ' total entries. Page ' . $logsData['pageNum'] . ' of ' . $logsData['totalPages'] . '.';
        $clearAction = $tab === 'security' ? 'clear_security_logs' : 'clear_logs';
        $header_extra_html = '
            <form method="POST" class="js-confirm-only" data-confirm-title="Clear Logs" data-confirm-msg="Clear these logs? This cannot be undone." data-confirm-icon="trash" data-confirm-icon-bg="#FEE2E2">
                ' . $logsData['csrfField'] . '
                <input type="hidden" name="action" value="' . $clearAction . '">
                <button type="submit" style="cursor:pointer;border:1px solid #FCA5A5;color:#DC2626;background:none;padding:7px 14px;border-radius:8px;font-size:13px;display:flex;align-items:center;gap:6px;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/></svg>
                    Clear ' . ($tab === 'security' ? 'Security' : 'Activity') . ' Logs
                </button>
            </form>
        ';
        include __DIR__ . '/partials/_page_header.php';
        ?>

        <?php include __DIR__ . '/views/admin_logs/_logs_table.php'; ?>
    </main>

    <script src="assets/js/pages/admin-logs.js?v=<?php echo $v['pageJs']; ?>"></script>
</body>
</html>
