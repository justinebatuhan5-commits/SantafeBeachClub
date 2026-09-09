<?php
// Enforce strict, secure, and HttpOnly session cookies for token storage
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? 80) == 443)
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '', // Default domain
    'secure' => $isHttps, // Automatically true in production over HTTPS
    'httponly' => true, // Prevent JavaScript access to session cookie (mitigates XSS)
    'samesite' => 'Lax' // Mitigate CSRF
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load Security Headers and CSRF Helper
require_once __DIR__ . '/security_headers.php';
require_once __DIR__ . '/csrf_helper.php';

// Enforce strict no-cache on sensitive authenticated pages (Checklist 10)
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

$is_api_request = (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
                   (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
                   (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/backend/api/') !== false);

if (isset($_SESSION['mfa_pending_admin_id']) && (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true)) {
    if ($is_api_request) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'MFA verification required', 'redirect' => 'verify_otp']);
        exit;
    }
    header('Location: verify_otp');
    exit;
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    if ($is_api_request) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Authentication required', 'redirect' => 'staff_login']);
        exit;
    }
    header('Location: staff_login');
    exit;
}

// ── Inactivity Timeout Check (15 minutes = 900 seconds) ─────────────────
$inactivity_limit = 900;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $inactivity_limit)) {
    // Session expired due to inactivity
    $role = $_SESSION['admin_role'] ?? 'receptionist';
    $source = $_SESSION['login_source'] ?? ($role === 'admin' ? 'admin' : 'reception');
    
    session_unset();
    session_destroy();
    
    $dest = ($source === 'admin') ? 'admin_login?timeout=1' : 'staff_login?timeout=1';
    if ($is_api_request) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Session timed out due to inactivity', 'redirect' => $dest]);
        exit;
    }
    header("Location: $dest");
    exit;
}
$_SESSION['last_activity'] = time();
?>
