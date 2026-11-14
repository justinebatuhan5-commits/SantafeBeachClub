<?php
require_once __DIR__ . '/../backend/helpers/admin_auth_check.php';
require_once __DIR__ . '/../backend/helpers/security_logger.php';

$active_page = 'admin_sessions';
$message = '';
$currentHash = admin_session_hash();
$userId = (int)$_SESSION['admin_account_id'];
$userType = (string)$_SESSION['admin_user_type'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
    if (!admin_password_recent()) {
        header('Location: admin_reauth.php?return=admin_sessions'); exit;
    }
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'revoke') {
        $id = (int)($_POST['session_id'] ?? 0);
        $stmt = $conn->prepare('UPDATE admin_sessions SET revoked_at=NOW() WHERE id=? AND user_id=? AND user_type=? AND session_hash<>? AND revoked_at IS NULL');
        $stmt->bind_param('iiss', $id, $userId, $userType, $currentHash); $stmt->execute(); $stmt->close();
        $message = 'The selected session has been signed out.';
    } elseif ($action === 'revoke_others') {
        $stmt = $conn->prepare('UPDATE admin_sessions SET revoked_at=NOW() WHERE user_id=? AND user_type=? AND session_hash<>? AND revoked_at IS NULL');
        $stmt->bind_param('iss', $userId, $userType, $currentHash); $stmt->execute(); $stmt->close();
        $message = 'All other sessions have been signed out.';
    }
    SecurityLogger::log($conn, 'ADMIN_SESSIONS_REVOKED', $message, SecurityLogger::LEVEL_WARNING, (string)$_SESSION['admin_username']);
}
$stmt = $conn->prepare('SELECT id,session_hash,ip_address,user_agent,created_at,last_seen_at FROM admin_sessions WHERE user_id=? AND user_type=? AND revoked_at IS NULL AND created_at >= DATE_SUB(NOW(), INTERVAL 8 HOUR) AND last_seen_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE) ORDER BY last_seen_at DESC');
$stmt->bind_param('is', $userId, $userType); $stmt->execute(); $sessions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
function device_label(string $ua): string { if (stripos($ua,'Edg/')!==false)return 'Microsoft Edge'; if(stripos($ua,'Chrome/')!==false)return 'Google Chrome'; if(stripos($ua,'Firefox/')!==false)return 'Mozilla Firefox'; if(stripos($ua,'Safari/')!==false)return 'Safari'; return 'Unknown browser'; }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Active Sessions — Santa Fe Beach Club</title><link rel="stylesheet" href="assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/assets/css/admin.css'); ?>"><style>body{margin:0;background:var(--bg-body,#f7f8fa);font-family:var(--font-body,'Plus Jakarta Sans',sans-serif);color:var(--text-main,#172033)}.main-content{min-height:100vh}.wrap{max-width:1000px;margin:0 auto}.btn{border:0;border-radius:10px;padding:10px 16px;background:var(--primary,#84563c);color:#fff;font:600 13px 'Plus Jakarta Sans',sans-serif;cursor:pointer;display:inline-flex;align-items:center;gap:6px;white-space:nowrap}.notice{background:var(--green-bg,#dcfce7);color:var(--green,#166534);padding:12px 15px;border-radius:10px;margin-bottom:16px}.card{background:var(--card-bg,#fff);border:1px solid var(--border,#e2e8f0);border-radius:14px;margin-bottom:12px;padding:18px;display:flex;justify-content:space-between;gap:16px;align-items:center}.meta{color:var(--text-muted,#64748b);font-size:13px;margin-top:7px}.current{display:inline-block;background:var(--green-bg,#dcfce7);color:var(--green,#166534);border-radius:999px;padding:3px 8px;font-size:12px;font-weight:700;margin-left:8px}.outline{background:var(--bg-surface-elev,#fff);color:#b91c1c;border:1px solid #fecaca}</style><?php require __DIR__.'/partials/_responsive.php'; ?></head><body>
<?php include __DIR__.'/partials/_sidebar.php'; ?><main class="main-content"><?php $page_title = 'Active Sessions'; $page_subtitle = 'Review browsers signed in to your administrator account and remotely sign them out.'; $header_extra_html = '<form method="post" style="margin:0">' . csrf_field() . '<input type="hidden" name="action" value="revoke_others"><button class="btn-primary" type="submit">Sign Out All Other Sessions</button></form>'; include __DIR__.'/partials/_page_header.php'; ?><div class="admin-body"><div class="wrap">
<?php if($message):?><div class="notice"><?=htmlspecialchars($message)?></div><?php endif;?>
<?php foreach($sessions as $s): $current=hash_equals($currentHash,$s['session_hash']); ?><section class="card"><div><strong><?=htmlspecialchars(device_label((string)$s['user_agent']))?></strong><?php if($current):?><span class="current">This device</span><?php endif;?><div class="meta">IP <?=htmlspecialchars($s['ip_address']?:'Unknown')?> · Last active <?=htmlspecialchars($s['last_seen_at'])?> · Signed in <?=htmlspecialchars($s['created_at'])?></div></div><?php if(!$current):?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="revoke"><input type="hidden" name="session_id" value="<?=(int)$s['id']?>"><button class="btn outline" type="submit">Sign Out</button></form><?php endif;?></section><?php endforeach;?>
</div></div></main></body></html>
