<?php
declare(strict_types=1);

/**
 * staff_assistant_api.php — Read-Only AI Operations Assistant for Admin & Reception Staff
 *
 * Strictly protected by:
 *  - auth_check.php (active authenticated session + MFA + device trust + session timeout)
 *  - rbac_helper.php (requires 'admin' or 'receptionist' role; revenue restricted to 'admin')
 *  - csrf_helper.php (CSRF verification)
 *  - rate_limiter.php (rate limiting)
 */

require_once __DIR__ . '/../helpers/auth_check.php';
require_once __DIR__ . '/../helpers/rbac_helper.php';
require_once __DIR__ . '/../helpers/rate_limiter.php';
require_once __DIR__ . '/../services/chatbot_service.php';

header('Content-Type: application/json; charset=utf-8');

RBAC::requireRole(['admin', 'receptionist']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$rawBody = file_get_contents('php://input');
$input = [];
if (is_string($rawBody) && trim($rawBody) !== '') {
    $decoded = json_decode($rawBody, true);
    if (is_array($decoded)) {
        $input = $decoded;
    }
}
if (empty($input) && !empty($_POST)) {
    $input = $_POST;
}

$csrfToken = isset($input['csrf_token']) ? (string)$input['csrf_token'] : null;
if (!verify_csrf_token($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid or expired CSRF token.']);
    exit;
}

RateLimiter::enforce($conn, 'staff_assistant', 25, 60);

$question = trim((string)($input['message'] ?? ''));
$question = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $question) ?? '';

if ($question === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Please enter a question.']);
    exit;
}
if (mb_strlen($question) > 400) {
    $question = mb_substr($question, 0, 400);
}

$role = RBAC::getCurrentRole();
$username = (string)($_SESSION['admin_username'] ?? $role);

$res = sf_staff_assistant_send_message($conn, $question, $role, $username);
echo json_encode($res, JSON_UNESCAPED_UNICODE);
