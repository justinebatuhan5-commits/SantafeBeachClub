<?php
/**
 * api_auth_helper.php — API Authentication & Access Guard
 * Verifies that requests to internal/admin APIs are authenticated.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/cors_helper.php';
require_once __DIR__ . '/security_logger.php';

function require_api_auth(mysqli $conn, ?string $requiredRole = null): void {
    handle_cors();

    // Check if session is authenticated
    $isAuthenticated = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
    
    // Also allow Bearer token if present (for server-to-server or API integration)
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (!$isAuthenticated && !empty($authHeader)) {
        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $token = $matches[1];
            // Validate API token against settings or admin API key
            $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'api_secret_key' LIMIT 1");
            if ($stmt) {
                $stmt->execute();
                $res = $stmt->get_result()->fetch_assoc();
                if ($res && !empty($res['setting_value']) && hash_equals($res['setting_value'], $token)) {
                    $isAuthenticated = true;
                    $_SESSION['admin_username'] = 'api_client';
                    $_SESSION['admin_role'] = 'admin';
                }
                $stmt->close();
            }
        }
    }

    if (!$isAuthenticated) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => 'Unauthorized: Authentication required to access this endpoint.'
        ]);
        SecurityLogger::log($conn, 'UNAUTHORIZED_API_ACCESS', 'Unauthenticated request to ' . ($_SERVER['REQUEST_URI'] ?? ''), SecurityLogger::LEVEL_WARNING);
        exit;
    }

    // Role check if specified
    if ($requiredRole !== null) {
        $userRole = $_SESSION['admin_role'] ?? 'guest';
        if ($userRole !== $requiredRole && $userRole !== 'admin') {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error' => "Forbidden: Insufficient privileges. Required role: {$requiredRole}."
            ]);
            SecurityLogger::log($conn, 'FORBIDDEN_API_ACCESS', "User {$userRole} denied access to {$requiredRole} endpoint", SecurityLogger::LEVEL_WARNING);
            exit;
        }
    }
}

/**
 * require_admin_access() — Guard for admin-only refund/API actions.
 * Alias of require_api_auth() used throughout refund_request_api.php.
 * Terminates with 401 JSON if admin session is not authenticated.
 */
function require_admin_access(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $isAdmin = isset($_SESSION['admin_logged_in'])
        && $_SESSION['admin_logged_in'] === true
        && ($_SESSION['admin_role'] ?? '') === 'admin';

    if (!$isAdmin) {
        http_response_code(isset($_SESSION['admin_logged_in']) ? 403 : 401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Administrator access is required for this refund action.'
        ]);
        exit;
    }
}
?>
