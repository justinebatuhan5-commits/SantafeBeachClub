<?php
require_once __DIR__ . '/../backend/helpers/session_init.php';
$role = $_SESSION['admin_role'] ?? $_SESSION['mfa_pending_admin_role'] ?? null;
$source = $_SESSION['login_source'] ?? ($role === 'admin' ? 'admin' : 'reception');

if (isset($_SESSION['admin_logged_in']) && isset($_SESSION['admin_username'])) {
    require_once __DIR__ . '/../backend/config/db.php';
    log_activity($conn, $_SESSION['admin_username'], 'Logout', 'Logged out');
}

session_unset();
session_destroy();

$isTimeout = isset($_GET['timeout']) && $_GET['timeout'] == '1';
$dest = ($source === 'admin') ? 'admin_login' : 'staff_login';
if ($isTimeout) {
    $dest .= '?timeout=1';
}
header("Location: $dest");
exit;

