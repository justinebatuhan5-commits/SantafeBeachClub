<?php
declare(strict_types=1);

/**
 * inquiry_reply_api.php — Two-Way Staff-to-Guest Messaging & Live Thread Sync API
 *
 * Allows authenticated Administrators and Receptionists to:
 *  - Send direct replies to guests (pushed live into their open AI Concierge chat + via Gmail SMTP)
 *  - Poll active inquiry & escalated chatbot threads for real-time guest follow-ups
 *  - Update ticket status (synchronizing chatbot live_chat_active cleanly)
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../helpers/auth_check.php';
require_once __DIR__ . '/../helpers/rbac_helper.php';
require_once __DIR__ . '/../helpers/rate_limiter.php';
require_once __DIR__ . '/../services/chatbot_service.php';

RBAC::requireRole(['admin', 'receptionist']);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
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

$action = trim((string)($input['action'] ?? ($_GET['action'] ?? '')));
$role = RBAC::getCurrentRole();
$username = trim((string)($_SESSION['admin_username'] ?? $role)) ?: $role;
$roleLabel = $role === 'admin' ? 'Admin' : 'Reception';
$displayName = ucfirst($username) . " ({$roleLabel})";

try {
    if ($action === 'poll_threads') {
        $rawIds = $input['inquiry_ids'] ?? ($_GET['inquiry_ids'] ?? '');
        $idArr = is_array($rawIds) ? $rawIds : explode(',', (string)$rawIds);
        $ids = array_slice(
            array_values(array_unique(array_filter(array_map('intval', $idArr), static fn(int $v): bool => $v > 0))),
            0,
            50
        );

        $statuses = [];
        if (!empty($ids)) {
            $idList = implode(',', $ids);
            $sRes = $conn->query("SELECT id, status FROM inquiries WHERE id IN ({$idList})");
            if ($sRes) {
                while ($sRow = $sRes->fetch_assoc()) {
                    $statuses[(int)$sRow['id']] = (string)$sRow['status'];
                }
            }
        }

        $threads = sf_inquiry_get_threads($conn, $ids);
        foreach ($threads as $iid => &$t) {
            if (isset($statuses[$iid])) {
                $t['status'] = $statuses[$iid];
            }
        }
        unset($t);

        echo json_encode([
            'success' => true,
            'threads' => $threads,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
        exit;
    }

    $csrfToken = isset($input['csrf_token']) ? (string)$input['csrf_token'] : ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
    if (!verify_csrf_token($csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid or expired security token. Please reload the page.']);
        exit;
    }

    if ($action === 'send_reply') {
        RateLimiter::enforce($conn, 'staff_inquiry_reply', 30, 60);
        $inquiryId = (int)($input['inquiry_id'] ?? 0);
        $message = (string)($input['message'] ?? '');
        $sendLive = array_key_exists('send_live', $input) ? (bool)$input['send_live'] : true;
        $sendEmail = array_key_exists('send_email', $input) ? (bool)$input['send_email'] : true;
        $markResolved = !empty($input['mark_resolved']);

        $res = sf_inquiry_send_staff_reply($conn, $inquiryId, $message, $username, $displayName, [
            'send_live' => $sendLive,
            'send_email' => $sendEmail,
            'mark_resolved' => $markResolved,
        ]);

        if (empty($res['success'])) {
            http_response_code(400);
        }
        echo json_encode($res, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'update_status') {
        RateLimiter::enforce($conn, 'staff_inquiry_reply', 40, 60);
        $inquiryId = (int)($input['inquiry_id'] ?? 0);
        $newStatus = trim((string)($input['status'] ?? ''));
        if ($inquiryId <= 0 || !in_array($newStatus, ['Unread', 'Read', 'Resolved'], true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid ticket or status value.']);
            exit;
        }

        $stmt = $conn->prepare("UPDATE inquiries SET status = ? WHERE id = ?");
        $stmt->bind_param('si', $newStatus, $inquiryId);
        $stmt->execute();
        $stmt->close();

        if ($newStatus === 'Resolved' && sf_chatbot_has_live_chat_support($conn)) {
            $uConv = $conn->prepare("UPDATE chatbot_conversations SET live_chat_active = 0 WHERE inquiry_id = ?");
            if ($uConv) {
                $uConv->bind_param('i', $inquiryId);
                $uConv->execute();
                $uConv->close();
            }
        }

        try {
            log_activity($conn, $username, 'Inquiry Status Updated', "Ticket #{$inquiryId} marked as {$newStatus}");
        } catch (Throwable $e) {
            // Ignore audit error
        }

        echo json_encode([
            'success' => true,
            'inquiry_id' => $inquiryId,
            'status' => $newStatus,
            'live_chat_active' => ($newStatus !== 'Resolved'),
        ]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Unsupported action.']);
} catch (Throwable $e) {
    error_log('Inquiry Reply API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not process inquiry action right now.']);
}
