<?php
declare(strict_types=1);

/**
 * Automated Cron Job Script: 90-Day Chatbot Log Retention Purge (RA 10173 Compliance)
 *
 * Deletes chatbot conversations and messages older than 90 days.
 * Usage (CLI):
 *   php backend/cron/purge_chatbot_logs.php
 */

require_once __DIR__ . '/../config/db.php';

$isCli = (PHP_SAPI === 'cli');
if (!$isCli) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $secret = trim((string) (getenv('CRON_SECRET') ?: ($_ENV['CRON_SECRET'] ?? '')));
    $provided = (string) ($_GET['key'] ?? '');
    $isAdmin = !empty($_SESSION['admin_logged_in']) && ($_SESSION['admin_role'] ?? '') === 'admin';
    $keyValid = $secret !== '' && $provided !== '' && hash_equals($secret, $provided);
    if (!$isAdmin && !$keyValid) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Forbidden']);
        exit;
    }
}

$retentionDays = isset($_GET['days']) ? max(30, (int)$_GET['days']) : 90;
$cutoff = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));

$deletedMessages = 0;
$deletedConversations = 0;

$tbl = $conn->query("SHOW TABLES LIKE 'chatbot_conversations'");
if ($tbl && $tbl->num_rows > 0) {
    $mStmt = $conn->prepare("DELETE FROM chatbot_messages WHERE created_at < ?");
    $mStmt->bind_param('s', $cutoff);
    $mStmt->execute();
    $deletedMessages = (int)$mStmt->affected_rows;
    $mStmt->close();

    $cStmt = $conn->prepare("DELETE FROM chatbot_conversations WHERE last_message_at < ?");
    $cStmt->bind_param('s', $cutoff);
    $cStmt->execute();
    $deletedConversations = (int)$cStmt->affected_rows;
    $cStmt->close();
}

$result = [
    'success' => true,
    'retention_days' => $retentionDays,
    'cutoff' => $cutoff,
    'deleted_messages' => $deletedMessages,
    'deleted_conversations' => $deletedConversations,
];

if ($isCli) {
    echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
    exit(0);
}

header('Content-Type: application/json');
echo json_encode($result, JSON_PRETTY_PRINT);
