<?php
/**
 * Authenticated JSON endpoint for Admin Automation & Scheduler operations.
 *
 * GET  -> returns active scheduled task definitions and recent automation logs.
 * POST -> executes an allowed automation task manually with CSRF protection.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/api_auth_helper.php';
require_once __DIR__ . '/../helpers/admin_session_helper.php';
require_once __DIR__ . '/../helpers/csrf_helper.php';
require_once __DIR__ . '/../helpers/rate_limiter.php';
require_once __DIR__ . '/../services/automation_service.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

require_api_auth($conn, 'admin');

if (!empty($_SESSION['user_id']) && !admin_session_validate($conn)) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Admin session expired or invalidated.',
    ]);
    exit;
}

RateLimiter::enforce($conn, 'automation_api', 60, 60);

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

try {
    if ($method === 'GET') {
        echo json_encode(
            sfbc_automation_overview($conn),
            JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );
        exit;
    }

    if ($method === 'POST') {
        if (!verify_csrf_token()) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'Invalid or missing CSRF token.',
            ]);
            exit;
        }

        $rawBody = file_get_contents('php://input');
        $input = [];
        if (is_string($rawBody) && trim($rawBody) !== '') {
            $decoded = json_decode($rawBody, true);
            if (!is_array($decoded)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid JSON request payload.',
                ]);
                exit;
            }
            $input = $decoded;
        } elseif (!empty($_POST)) {
            $input = $_POST;
        }

        $taskId = isset($input['task_id']) && is_string($input['task_id'])
            ? trim($input['task_id'])
            : '';

        $actor = (string) ($_SESSION['username'] ?? $_SESSION['admin_username'] ?? 'admin');
        $result = sfbc_automation_trigger_task($conn, $taskId, $actor);

        http_response_code($result['status']);
        echo json_encode(
            $result['payload'],
            JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );
        exit;
    }

    header('Allow: GET, POST');
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed.',
    ]);
} catch (Throwable $e) {
    error_log('[automation_api] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to process automation request.',
    ]);
}
