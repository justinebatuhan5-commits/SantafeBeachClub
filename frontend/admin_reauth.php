<?php
require_once __DIR__ . '/../backend/helpers/admin_auth_check.php';
require_once __DIR__ . '/../backend/helpers/password_helper.php';
require_once __DIR__ . '/../backend/helpers/security_logger.php';

$allowedReturns = ['admin_refunds', 'settings', 'admin_sessions'];
$return = (string)($_GET['return'] ?? $_POST['return'] ?? 'admin_refunds');
if (!in_array($return, $allowedReturns, true)) $return = 'admin_refunds';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
    $password = (string)($_POST['password'] ?? '');
    $username = (string)($_SESSION['admin_username'] ?? '');
    $stmt = $conn->prepare('SELECT password FROM administrators WHERE username=? LIMIT 1');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row && pw_verify($password, (string)$row['password'])) {
        admin_session_revoke_current($conn);
        $_SESSION['password_confirmed_at'] = time();
        session_regenerate_id(true);
        admin_session_register($conn, (int)$_SESSION['admin_account_id'], 'admin', $username);
        SecurityLogger::log($conn, 'STEP_UP_AUTH_SUCCESS', 'Password confirmed for a sensitive administrator action.', SecurityLogger::LEVEL_INFO, $username);
        header('Location: ' . $return . (str_starts_with($return, 'settings') ? '?tab=property' : ''));
        exit;
    }
    $error = 'Password is incorrect.';
    SecurityLogger::log($conn, 'STEP_UP_AUTH_FAILED', 'Incorrect password during step-up authentication.', SecurityLogger::LEVEL_WARNING, $username);
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Confirm Administrator Password</title><link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f4f1ed;font-family:Outfit,sans-serif;color:#172033}.card{width:min(440px,calc(100% - 32px));background:#fff;border-radius:20px;padding:34px;box-shadow:0 18px 50px #53392320}.icon{width:58px;height:58px;border-radius:50%;display:grid;place-items:center;background:#f1e9e3;color:#704b36;font-size:27px;margin-bottom:18px}h1{font-size:25px;margin:0 0 8px}p{color:#64748b;line-height:1.55;margin:0 0 22px}label{display:block;font-weight:600;margin-bottom:7px}input{width:100%;padding:13px;border:1px solid #cbd5e1;border-radius:10px;font:inherit}button{width:100%;margin-top:16px;padding:13px;border:0;border-radius:10px;background:#704b36;color:#fff;font:700 15px Outfit;cursor:pointer}.error{padding:11px;border-radius:8px;background:#fee2e2;color:#991b1b;margin-bottom:16px}.back{display:block;text-align:center;margin-top:16px;color:#704b36;text-decoration:none}</style></head><body>
<main class="card"><div class="icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></div><h1>Confirm it’s you</h1><p>Enter your administrator password. Confirmation remains valid for five minutes for refund and security-setting changes.</p>
<?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="post"><?= csrf_field() ?><input type="hidden" name="return" value="<?= htmlspecialchars($return) ?>"><label for="password">Administrator password</label><input id="password" name="password" type="password" autocomplete="current-password" required autofocus><button type="submit">Confirm Password</button></form>
<a class="back" href="<?= htmlspecialchars($return) ?>">Cancel</a></main></body></html>
