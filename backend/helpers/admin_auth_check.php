<?php
// Use centralized secure session initializer (Secure, HttpOnly, SameSite, use_only_cookies)
require_once __DIR__ . '/session_init.php';

require_once __DIR__ . '/security_headers.php';
require_once __DIR__ . '/csrf_helper.php';

// Enforce strict no-cache on sensitive authenticated pages
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

if (isset($_SESSION['mfa_pending_admin_id']) && (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true)) {
    header('Location: verify_otp');
    exit;
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true || ($_SESSION['admin_role'] ?? '') !== 'admin') {
    header('Location: admin_login');
    exit;
}

// ── Absolute Session Lifetime Check (8 hours = 28800 seconds) ───────────
$absolute_limit = 28800;
if (isset($_SESSION['session_start_time']) && (time() - $_SESSION['session_start_time'] > $absolute_limit)) {
    session_unset();
    session_destroy();
    header('Location: admin_login?timeout=1');
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

