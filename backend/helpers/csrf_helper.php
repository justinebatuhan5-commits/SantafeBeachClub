<?php
/**
 * csrf_helper.php — Robust CSRF Protection Helper
 * Handles generation, rendering, and verification of CSRF tokens.
 */

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? 80) == 443)
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

/**
 * Generate or retrieve current CSRF token from session
 * @return string
 */
function get_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Render a hidden input field with CSRF token for HTML forms
 * @return string
 */
function csrf_field(): string {
    $token = htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * Validate submitted CSRF token from POST or HTTP Header
 * @param string|null $token
 * @return bool
 */
function verify_csrf_token(?string $token = null): bool {
    if ($token === null) {
        $token = $_POST['csrf_token'] 
            ?? $_SERVER['HTTP_X_CSRF_TOKEN'] 
            ?? $_SERVER['HTTP_X_XSRF_TOKEN'] 
            ?? null;
    }

    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Require valid CSRF token on POST requests or terminate with 403
 */
function require_csrf_token(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verify_csrf_token()) {
            http_response_code(403);
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Invalid or expired CSRF token. Please refresh the page.']);
            } else {
                echo '<!DOCTYPE html><html><head><title>403 Forbidden</title><style>body{font-family:sans-serif;text-align:center;padding:50px;background:#f8f9fa;color:#333;} .box{max-width:500px;margin:0 auto;background:#fff;padding:30px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.1);} h1{color:#e53e3e;} a{color:#007bff;text-decoration:none;font-weight:bold;}</style></head><body><div class="box"><h1>403 Forbidden</h1><p>Security validation failed (Invalid or missing CSRF token). Please go back, refresh the page, and try again.</p><p><a href="javascript:history.back()">Go Back</a></p></div></body></html>';
            }
            exit;
        }
    }
}
?>
