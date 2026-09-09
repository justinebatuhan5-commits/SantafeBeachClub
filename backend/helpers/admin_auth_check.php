<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/security_headers.php';
require_once __DIR__ . '/csrf_helper.php';

if (isset($_SESSION['mfa_pending_admin_id']) && (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true)) {
    header('Location: verify_otp');
    exit;
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true || ($_SESSION['admin_role'] ?? '') !== 'admin') {
    header('Location: admin_login');
    exit;
}

// ── Inactivity Timeout Check (15 minutes = 900 seconds) ─────────────────
$inactivity_limit = 900;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $inactivity_limit)) {
    session_unset();
    session_destroy();
    header('Location: admin_login?timeout=1');
    exit;
}
$_SESSION['last_activity'] = time();

?>
