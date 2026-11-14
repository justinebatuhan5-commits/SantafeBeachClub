<?php
declare(strict_types=1);

require_once __DIR__ . '/../backend/helpers/admin_auth_check.php';
require_once __DIR__ . '/../backend/helpers/rbac_helper.php';
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/services/chatbot_service.php';
require_once __DIR__ . '/partials/_inquiry_reply_assets.php';

RBAC::requireRole('admin');

$admin = (string)($_SESSION['admin_username'] ?? 'admin');
$active_page = 'chatbot';
$flashSuccess = '';
$flashError = '';
$activeTab = isset($_GET['tab']) ? strtolower(trim((string)$_GET['tab'])) : 'analytics';
if (!in_array($activeTab, ['analytics', 'conversations', 'knowledge', 'support', 'settings'], true)) {
    $activeTab = 'analytics';
}

$tablesReady = sf_chatbot_tables_ready($conn);

// Conversation Monitoring Filters (GET)
$convSearch = trim((string)($_GET['conv_q'] ?? ''));
$convLang = trim((string)($_GET['conv_lang'] ?? 'all'));
$convStatus = trim((string)($_GET['conv_status'] ?? 'all'));
$convDays = trim((string)($_GET['conv_days'] ?? 'all'));
if (isset($_GET['conv_q']) || isset($_GET['conv_lang']) || isset($_GET['conv_status']) || isset($_GET['conv_days'])) {
    if (!isset($_GET['tab'])) {
        $activeTab = 'conversations';
    }
}

// Build WHERE clause for Conversation Monitoring & CSV Export
$convWhere = ['1=1'];
$convParams = [];
$convTypes = '';

if ($convLang !== '' && $convLang !== 'all') {
    $convWhere[] = 'c.language = ?';
    $convParams[] = $convLang;
    $convTypes .= 's';
}
if ($convStatus === 'resolved') {
    $convWhere[] = 'c.handed_off = 0';
} elseif ($convStatus === 'handed_off') {
    $convWhere[] = 'c.handed_off = 1';
} elseif ($convStatus === 'otp_verified') {
    $convWhere[] = 'c.booking_id IS NOT NULL';
} elseif ($convStatus === 'booking_clicked') {
    $convWhere[] = 'c.booking_link_clicks > 0';
} elseif ($convStatus === 'unanswered') {
    $convWhere[] = "EXISTS (SELECT 1 FROM chatbot_messages m_u WHERE m_u.conversation_id = c.id AND m_u.intent = 'unanswered')";
} elseif ($convStatus === 'flagged') {
    $convWhere[] = "EXISTS (SELECT 1 FROM chatbot_messages m_f WHERE m_f.conversation_id = c.id AND m_f.feedback = -1)";
}
if (in_array($convDays, ['1', '7', '30'], true)) {
    $daysInt = (int)$convDays;
    $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$daysInt} days"));
    $convWhere[] = 'c.last_message_at >= ?';
    $convParams[] = $cutoffDate;
    $convTypes .= 's';
}
if ($convSearch !== '') {
    $convWhere[] = '(CAST(c.id AS CHAR) LIKE ? OR EXISTS (SELECT 1 FROM chatbot_messages m_s WHERE m_s.conversation_id = c.id AND m_s.content LIKE ?))';
    $likeQ = '%' . $convSearch . '%';
    $convParams[] = $likeQ;
    $convParams[] = $likeQ;
    $convTypes .= 'ss';
}
$convWhereSql = implode(' AND ', $convWhere);

// CSV Export for Conversation Monitoring
if ($tablesReady && isset($_GET['export']) && $_GET['export'] === 'conversations_csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="sfbc_chatbot_conversations_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    if ($out !== false) {
        fputcsv($out, [
            'Conversation ID',
            'Language',
            'Status',
            'OTP Verified Booking ID',
            'Booking Link Clicks',
            'Total Messages',
            'Started At',
            'Last Active At',
            'Sample Guest Prompt',
            'Sample Assistant Reply',
        ]);
        $exportSql = "
            SELECT c.id, c.language, c.booking_id, c.handed_off, c.booking_link_clicks, c.started_at, c.last_message_at,
                   (SELECT COUNT(*) FROM chatbot_messages m WHERE m.conversation_id = c.id) AS msg_count,
                   (SELECT content FROM chatbot_messages m1 WHERE m1.conversation_id = c.id AND m1.role = 'user' ORDER BY m1.id ASC LIMIT 1) AS first_user_msg,
                   (SELECT content FROM chatbot_messages m2 WHERE m2.conversation_id = c.id AND m2.role = 'model' ORDER BY m2.id ASC LIMIT 1) AS first_bot_msg
            FROM chatbot_conversations c
            WHERE {$convWhereSql}
            ORDER BY c.last_message_at DESC
            LIMIT 500
        ";
        $expStmt = $conn->prepare($exportSql);
        if ($convTypes !== '') {
            $expStmt->bind_param($convTypes, ...$convParams);
        }
        $expStmt->execute();
        $expRes = $expStmt->get_result();
        while ($row = $expRes->fetch_assoc()) {
            fputcsv($out, [
                '#' . (int)$row['id'],
                (string)$row['language'],
                (int)$row['handed_off'] === 1 ? 'Escalated to Human Support' : 'Automated / Resolved',
                !empty($row['booking_id']) ? 'REF-' . str_pad((string)$row['booking_id'], 3, '0', STR_PAD_LEFT) : 'No',
                (int)$row['booking_link_clicks'],
                (int)$row['msg_count'],
                (string)$row['started_at'],
                (string)$row['last_message_at'],
                (string)($row['first_user_msg'] ?? ''),
                (string)($row['first_bot_msg'] ?? ''),
            ]);
        }
        $expStmt->close();
        fclose($out);
    }
    exit;
}

$allowedCategories = [
    'accommodations' => 'Accommodations & Room Rates',
    'amenities' => 'Amenities & Facilities',
    'directions' => 'Directions & Ferry Schedule',
    'payment' => 'Payment & Deposit',
    'cancellation' => 'Cancellation & Rebooking',
    'discounts' => 'Promotions & Discounts',
    'policies' => 'Resort Policies',
    'house_rules' => 'Check-in & House Rules',
    'general' => 'General Inquiry',
];
$supportedLanguages = sf_chatbot_supported_languages();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    require_csrf_token();
    $postAction = (string)$_POST['action'];

    try {
        if ($postAction === 'save_settings') {
            $activeTab = 'settings';
            $enabled = isset($_POST['chatbot_enabled']) && $_POST['chatbot_enabled'] === '1' ? '1' : '0';
            $dailyLimit = (string)max(50, min(10000, (int)($_POST['chatbot_daily_limit'] ?? 500)));
            $welcome = trim(strip_tags((string)($_POST['chatbot_welcome_message'] ?? '')));
            if ($welcome === '') {
                $welcome = 'Hello! Welcome to Santa Fe Beach Club. How may I assist you today?';
            }

            $stmt = $conn->prepare("
                INSERT INTO settings (setting_key, setting_value)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");
            foreach ([
                'chatbot_enabled' => $enabled,
                'chatbot_daily_limit' => $dailyLimit,
                'chatbot_welcome_message' => $welcome,
            ] as $k => $v) {
                $stmt->bind_param('ss', $k, $v);
                $stmt->execute();
            }
            $stmt->close();

            log_activity($conn, $admin, 'AI Concierge Settings Updated', "Enabled={$enabled}, DailyLimit={$dailyLimit}");
            $flashSuccess = 'AI Concierge settings saved successfully.';
        } elseif ($postAction === 'save_kb' && $tablesReady) {
            $activeTab = 'knowledge';
            $kbId = (int)($_POST['kb_id'] ?? 0);
            $category = strtolower(trim(strip_tags((string)($_POST['category'] ?? 'general'))));
            if (!array_key_exists($category, $allowedCategories)) {
                $category = 'general';
            }
            $language = trim((string)($_POST['language'] ?? 'English'));
            if (!in_array($language, $supportedLanguages, true)) {
                $language = 'English';
            }
            $question = trim(strip_tags((string)($_POST['question'] ?? '')));
            $answer = trim(strip_tags((string)($_POST['answer'] ?? '')));

            if ($question === '' || $answer === '') {
                $flashError = 'Both question and answer are required.';
            } elseif ($kbId > 0) {
                $stmt = $conn->prepare("UPDATE chatbot_knowledge SET category = ?, language = ?, question = ?, answer = ?, updated_by = ? WHERE id = ?");
                $stmt->bind_param('sssssi', $category, $language, $question, $answer, $admin, $kbId);
                $stmt->execute();
                $stmt->close();
                log_activity($conn, $admin, 'AI Knowledge Base Updated', "Updated FAQ #{$kbId}: {$question}");
                $flashSuccess = "Knowledge base entry #{$kbId} updated successfully.";
            } else {
                $stmt = $conn->prepare("INSERT INTO chatbot_knowledge (category, question, answer, language, is_active, updated_by) VALUES (?, ?, ?, ?, 1, ?)");
                $stmt->bind_param('sssss', $category, $question, $answer, $language, $admin);
                $stmt->execute();
                $stmt->close();
                log_activity($conn, $admin, 'AI Knowledge Base Added', "Added FAQ: {$question}");
                $flashSuccess = 'New knowledge base entry added.';
            }
        } elseif ($postAction === 'toggle_kb' && $tablesReady) {
            $activeTab = 'knowledge';
            $kbId = (int)($_POST['kb_id'] ?? 0);
            if ($kbId > 0) {
                $stmt = $conn->prepare("UPDATE chatbot_knowledge SET is_active = 1 - is_active, updated_by = ? WHERE id = ?");
                $stmt->bind_param('si', $admin, $kbId);
                $stmt->execute();
                $stmt->close();
                $flashSuccess = 'Knowledge base entry status toggled.';
            }
        } elseif ($postAction === 'delete_kb' && $tablesReady) {
            $activeTab = 'knowledge';
            $kbId = (int)($_POST['kb_id'] ?? 0);
            if ($kbId > 0) {
                $stmt = $conn->prepare("DELETE FROM chatbot_knowledge WHERE id = ?");
                $stmt->bind_param('i', $kbId);
                $stmt->execute();
                $stmt->close();
                log_activity($conn, $admin, 'AI Knowledge Base Deleted', "Deleted FAQ #{$kbId}");
                $flashSuccess = 'Knowledge base entry deleted.';
            }
        } elseif ($postAction === 'escalate_conversation' && $tablesReady) {
            $activeTab = 'support';
            $convId = (int)($_POST['conversation_id'] ?? 0);
            $adminNote = trim(strip_tags((string)($_POST['escalation_note'] ?? 'Flagged by administrator for human follow-up.')));
            if ($convId > 0) {
                $cStmt = $conn->prepare("SELECT id, session_token, handed_off, inquiry_id FROM chatbot_conversations WHERE id = ? LIMIT 1");
                $cStmt->bind_param('i', $convId);
                $cStmt->execute();
                $cRow = $cStmt->get_result()->fetch_assoc();
                $cStmt->close();

                if ($cRow) {
                    $mStmt = $conn->prepare("SELECT role, content, created_at FROM chatbot_messages WHERE conversation_id = ? ORDER BY id DESC LIMIT 6");
                    $mStmt->bind_param('i', $convId);
                    $mStmt->execute();
                    $mRows = array_reverse($mStmt->get_result()->fetch_all(MYSQLI_ASSOC));
                    $mStmt->close();

                    $transcriptLines = [];
                    foreach ($mRows as $mr) {
                        $roleLabel = $mr['role'] === 'user' ? 'Guest' : ($mr['role'] === 'model' ? 'AI Concierge' : 'Tool');
                        $transcriptLines[] = "[{$roleLabel}]: " . $mr['content'];
                    }
                    $subject = "[AI Concierge Handoff] Session #{$convId} (Admin Escalated)";
                    $body = "Escalated by Admin: {$admin}\nNote: {$adminNote}\n\nRecent Chat Transcript (Session #{$convId}):\n" . implode("\n", $transcriptLines);
                    $guestName = "Chat Session #{$convId}";
                    $guestEmail = "pending-contact@santafebeachclub.com";

                    $inqStmt = $conn->prepare("INSERT INTO inquiries (guest_name, guest_email, subject, message, status) VALUES (?, ?, ?, ?, 'Unread')");
                    $inqStmt->bind_param('ssss', $guestName, $guestEmail, $subject, $body);
                    $inqStmt->execute();
                    $inqId = (int)$inqStmt->insert_id;
                    $inqStmt->close();

                    $uStmt = $conn->prepare("UPDATE chatbot_conversations SET handed_off = 1, inquiry_id = ? WHERE id = ?");
                    $uStmt->bind_param('ii', $inqId, $convId);
                    $uStmt->execute();
                    $uStmt->close();

                    log_activity($conn, $admin, 'AI Conversation Escalated', "Escalated Chat Session #{$convId} to Inquiry #{$inqId}");
                    $flashSuccess = "Conversation #{$convId} escalated to Human Support Queue (Ticket #{$inqId}).";
                }
            }
        } elseif ($postAction === 'update_handoff_status' && $tablesReady) {
            $activeTab = 'support';
            $inqId = (int)($_POST['inquiry_id'] ?? 0);
            $newStatus = trim((string)($_POST['new_status'] ?? 'Read'));
            if ($inqId > 0 && in_array($newStatus, ['Unread', 'Read', 'Resolved'], true)) {
                $stmt = $conn->prepare("UPDATE inquiries SET status = ? WHERE id = ?");
                $stmt->bind_param('si', $newStatus, $inqId);
                $stmt->execute();
                $stmt->close();
                if ($newStatus === 'Resolved' && sf_chatbot_has_live_chat_support($conn)) {
                    $uConv = $conn->prepare("UPDATE chatbot_conversations SET live_chat_active = 0 WHERE inquiry_id = ?");
                    if ($uConv) {
                        $uConv->bind_param('i', $inqId);
                        $uConv->execute();
                        $uConv->close();
                    }
                }
                log_activity($conn, $admin, 'AI Support Ticket Status Updated', "Ticket #{$inqId} marked as {$newStatus}");
                $flashSuccess = "Support ticket #{$inqId} status updated to {$newStatus}.";
            }
        } elseif ($postAction === 'reply_handoff' && $tablesReady) {
            $activeTab = 'support';
            $inqId = (int)($_POST['inquiry_id'] ?? 0);
            $staffReply = trim(strip_tags((string)($_POST['staff_reply'] ?? '')));
            if ($inqId > 0 && $staffReply !== '') {
                $repRes = sf_inquiry_send_staff_reply($conn, $inqId, $staffReply, $admin, ucfirst($admin) . ' (Admin)', [
                    'send_live' => true,
                    'send_email' => true,
                    'mark_resolved' => true,
                ]);
                if (!empty($repRes['success'])) {
                    $flashSuccess = "Reply sent for Support Ticket #{$inqId} and marked Resolved.";
                    if (!empty($repRes['email_error'])) {
                        $flashSuccess .= ' Email notice: ' . $repRes['email_error'];
                    }
                } else {
                    $flashError = (string)($repRes['error'] ?? 'Could not send staff response.');
                }
            } else {
                $flashError = 'Please enter a staff response or resolution note.';
            }
        } elseif ($postAction === 'purge_old_logs' && $tablesReady) {
            $activeTab = 'settings';
            $cutoff = date('Y-m-d H:i:s', strtotime('-90 days'));
            $mStmt = $conn->prepare("DELETE FROM chatbot_messages WHERE created_at < ?");
            $mStmt->bind_param('s', $cutoff);
            $mStmt->execute();
            $delMsg = (int)$mStmt->affected_rows;
            $mStmt->close();

            $cStmt = $conn->prepare("DELETE FROM chatbot_conversations WHERE last_message_at < ?");
            $cStmt->bind_param('s', $cutoff);
            $cStmt->execute();
            $delConv = (int)$cStmt->affected_rows;
            $cStmt->close();

            log_activity($conn, $admin, 'AI Concierge Logs Purged', "Deleted {$delConv} conversations and {$delMsg} messages older than 90 days");
            $flashSuccess = "Purged {$delConv} conversation(s) and {$delMsg} message(s) older than 90 days.";
        }
    } catch (Throwable $e) {
        $flashError = 'Operation failed: ' . $e->getMessage();
    }
}

// Fetch current settings
$chatbotEnabled = sf_chatbot_get_setting($conn, 'chatbot_enabled', '1');
$chatbotDailyLimit = sf_chatbot_get_setting($conn, 'chatbot_daily_limit', '500');
$chatbotWelcome = sf_chatbot_get_setting($conn, 'chatbot_welcome_message', 'Hello! Welcome to Santa Fe Beach Club. How may I assist you today?');
$geminiKeyConfigured = trim((string)(getenv('GEMINI_API_KEY') ?: ($_ENV['GEMINI_API_KEY'] ?? ''))) !== '';
$geminiModelName = trim((string)(getenv('GEMINI_MODEL') ?: ($_ENV['GEMINI_MODEL'] ?? 'gemini-2.5-flash')));

// Analytics & Metrics
$metrics = [
    'total_conversations' => 0,
    'total_messages' => 0,
    'handoff_count' => 0,
    'open_handoffs' => 0,
    'resolution_rate' => 100.0,
    'booking_clicks' => 0,
    'positive_feedback' => 0,
    'negative_feedback' => 0,
    'satisfaction_pct' => 100.0,
    'avg_latency_ms' => 0,
];
$intentStats = [];
$langStats = [];
$toolStats = [];
$dailyTrend = [];
$flaggedMessages = [];
$unansweredMessages = [];
$recentConversations = [];
$kbRows = [];
$handoffQueue = [];

if ($tablesReady) {
    $cRow = $conn->query("
        SELECT COUNT(*) AS total_conv,
               COALESCE(SUM(handed_off), 0) AS handoffs,
               COALESCE(SUM(booking_link_clicks), 0) AS clicks
        FROM chatbot_conversations
    ")->fetch_assoc();
    $metrics['total_conversations'] = (int)($cRow['total_conv'] ?? 0);
    $metrics['handoff_count'] = (int)($cRow['handoffs'] ?? 0);
    $metrics['booking_clicks'] = (int)($cRow['clicks'] ?? 0);
    if ($metrics['total_conversations'] > 0) {
        $resolved = max(0, $metrics['total_conversations'] - $metrics['handoff_count']);
        $metrics['resolution_rate'] = round(($resolved / $metrics['total_conversations']) * 100, 1);
    }

    $mRow = $conn->query("
        SELECT COUNT(*) AS total_msg,
               COALESCE(SUM(CASE WHEN feedback = 1 THEN 1 ELSE 0 END), 0) AS pos,
               COALESCE(SUM(CASE WHEN feedback = -1 THEN 1 ELSE 0 END), 0) AS neg,
               COALESCE(ROUND(AVG(CASE WHEN role = 'model' AND latency_ms > 0 THEN latency_ms ELSE NULL END)), 0) AS avg_lat
        FROM chatbot_messages
    ")->fetch_assoc();
    $metrics['total_messages'] = (int)($mRow['total_msg'] ?? 0);
    $metrics['positive_feedback'] = (int)($mRow['pos'] ?? 0);
    $metrics['negative_feedback'] = (int)($mRow['neg'] ?? 0);
    $metrics['avg_latency_ms'] = (int)($mRow['avg_lat'] ?? 0);
    $totalFb = $metrics['positive_feedback'] + $metrics['negative_feedback'];
    if ($totalFb > 0) {
        $metrics['satisfaction_pct'] = round(($metrics['positive_feedback'] / $totalFb) * 100, 1);
    }

    // 7-day trend
    $trendMap = [];
    for ($i = 6; $i >= 0; $i--) {
        $dKey = date('Y-m-d', strtotime("-{$i} days"));
        $trendMap[$dKey] = [
            'date' => $dKey,
            'label' => date('M j', strtotime($dKey)),
            'conversations' => 0,
            'messages' => 0,
        ];
    }
    $tConvRes = $conn->query("
        SELECT DATE(started_at) AS d, COUNT(*) AS cnt
        FROM chatbot_conversations
        WHERE started_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(started_at)
    ");
    if ($tConvRes) {
        while ($r = $tConvRes->fetch_assoc()) {
            $dk = (string)$r['d'];
            if (isset($trendMap[$dk])) {
                $trendMap[$dk]['conversations'] = (int)$r['cnt'];
            }
        }
    }
    $tMsgRes = $conn->query("
        SELECT DATE(created_at) AS d, COUNT(*) AS cnt
        FROM chatbot_messages
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(created_at)
    ");
    if ($tMsgRes) {
        while ($r = $tMsgRes->fetch_assoc()) {
            $dk = (string)$r['d'];
            if (isset($trendMap[$dk])) {
                $trendMap[$dk]['messages'] = (int)$r['cnt'];
            }
        }
    }
    $dailyTrend = array_values($trendMap);

    // Intents
    $iRes = $conn->query("
        SELECT COALESCE(NULLIF(intent, ''), 'general') AS intent, COUNT(*) AS cnt
        FROM chatbot_messages
        WHERE role = 'user'
        GROUP BY COALESCE(NULLIF(intent, ''), 'general')
        ORDER BY cnt DESC
    ");
    if ($iRes) {
        while ($r = $iRes->fetch_assoc()) {
            $intentStats[] = $r;
        }
    }

    // Languages
    $lRes = $conn->query("
        SELECT language, COUNT(*) AS cnt
        FROM chatbot_conversations
        GROUP BY language
        ORDER BY cnt DESC
    ");
    if ($lRes) {
        while ($r = $lRes->fetch_assoc()) {
            $langStats[] = $r;
        }
    }

    // Live Database Tool Invocations
    $toolRes = $conn->query("
        SELECT tool_name, COUNT(*) AS cnt
        FROM chatbot_messages
        WHERE tool_name IS NOT NULL AND tool_name <> ''
        GROUP BY tool_name
        ORDER BY cnt DESC
    ");
    if ($toolRes) {
        while ($r = $toolRes->fetch_assoc()) {
            $toolStats[] = $r;
        }
    }

    // Flagged / Unhelpful Responses (feedback = -1)
    $flagRes = $conn->query("
        SELECT m.id, m.conversation_id, m.content AS bot_reply, m.created_at,
               (SELECT u.content FROM chatbot_messages u WHERE u.conversation_id = m.conversation_id AND u.role = 'user' AND u.id < m.id ORDER BY u.id DESC LIMIT 1) AS user_question
        FROM chatbot_messages m
        WHERE m.feedback = -1
        ORDER BY m.created_at DESC
        LIMIT 10
    ");
    if ($flagRes) {
        while ($r = $flagRes->fetch_assoc()) {
            $flaggedMessages[] = $r;
        }
    }

    // Unanswered Guest Questions (intent = 'unanswered') with monitored bot reply
    $unansRes = $conn->query("
        SELECT m.id, m.conversation_id, m.content AS user_question, m.created_at,
               COALESCE(c.language, 'English') AS language,
               (SELECT b.content FROM chatbot_messages b WHERE b.conversation_id = m.conversation_id AND b.role = 'model' AND b.id > m.id ORDER BY b.id ASC LIMIT 1) AS bot_reply
        FROM chatbot_messages m
        LEFT JOIN chatbot_conversations c ON c.id = m.conversation_id
        WHERE m.role = 'user' AND m.intent = 'unanswered'
        ORDER BY m.created_at DESC
        LIMIT 15
    ");
    if ($unansRes) {
        while ($r = $unansRes->fetch_assoc()) {
            $unansweredMessages[] = $r;
        }
    }

    // Filtered Conversations for Conversation Monitoring
    $convSql = "
        SELECT c.id, c.language, c.booking_id, c.handed_off, c.inquiry_id, c.booking_link_clicks, c.started_at, c.last_message_at,
               (SELECT COUNT(*) FROM chatbot_messages m WHERE m.conversation_id = c.id) AS msg_count
        FROM chatbot_conversations c
        WHERE {$convWhereSql}
        ORDER BY c.last_message_at DESC
        LIMIT 50
    ";
    $convStmt = $conn->prepare($convSql);
    if ($convTypes !== '') {
        $convStmt->bind_param($convTypes, ...$convParams);
    }
    $convStmt->execute();
    $convRes = $convStmt->get_result();
    if ($convRes) {
        while ($c = $convRes->fetch_assoc()) {
            $cid = (int)$c['id'];
            $mStmt = $conn->prepare("SELECT id, role, content, intent, tool_name, provider, latency_ms, feedback, created_at FROM chatbot_messages WHERE conversation_id = ? ORDER BY id ASC LIMIT 40");
            $mStmt->bind_param('i', $cid);
            $mStmt->execute();
            $mList = $mStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $mStmt->close();
            $c['messages'] = $mList;
            $recentConversations[] = $c;
        }
    }
    $convStmt->close();

    // Knowledge Base rows
    $kbRes = $conn->query("SELECT * FROM chatbot_knowledge ORDER BY category ASC, language ASC, id ASC");
    if ($kbRes) {
        $kbRows = $kbRes->fetch_all(MYSQLI_ASSOC);
    }

    // Human Support & Escalations Queue
    $hqRes = $conn->query("
        SELECT i.id AS inquiry_id, i.guest_name, i.guest_email, i.subject, i.message, i.status, i.created_at,
               c.id AS conversation_id, c.language, c.booking_id, c.last_message_at
        FROM inquiries i
        LEFT JOIN chatbot_conversations c ON c.inquiry_id = i.id
        WHERE i.subject LIKE '[AI Concierge Handoff]%' OR c.handed_off = 1
        ORDER BY CASE WHEN i.status = 'Unread' THEN 0 WHEN i.status = 'Read' THEN 1 ELSE 2 END ASC, i.created_at DESC
        LIMIT 50
    ");
    if ($hqRes) {
        while ($hq = $hqRes->fetch_assoc()) {
            if (($hq['status'] ?? '') !== 'Resolved') {
                $metrics['open_handoffs']++;
            }
            $handoffQueue[] = $hq;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Concierge Management &amp; Analytics — Santa Fe Beach Club</title>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-circle-32.png?v=3">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo (int)filemtime(__DIR__ . '/assets/css/admin.css'); ?>">
    <?php require __DIR__ . '/partials/_responsive.php'; ?>
    <style>
        .ai-tabs { display:flex; gap:8px; margin-bottom:20px; flex-wrap:wrap; }
        .ai-tab-btn { padding:9px 16px; border-radius:8px; border:1px solid var(--border, #E2E8F0); background:var(--card-bg, #fff); color:var(--text-main, #1E293B); font-weight:600; font-size:13px; cursor:pointer; display:inline-flex; align-items:center; gap:6px; transition:all 0.15s ease; }
        .ai-tab-btn:hover { border-color:#84563C; }
        .ai-tab-btn.active { background:#84563C; color:#fff; border-color:#84563C; }
        .ai-tab-badge { display:inline-block; padding:1px 7px; border-radius:999px; font-size:11px; font-weight:700; background:rgba(15,23,42,0.08); color:inherit; }
        .ai-tab-btn.active .ai-tab-badge { background:rgba(255,255,255,0.22); color:#fff; }
        .ai-tab-badge.alert-badge { background:#EF4444; color:#fff; }
        .ai-panel { display:none; }
        .ai-panel.active { display:block; }
        .kpi-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:16px; margin-bottom:22px; }
        .kpi-card { background:var(--card-bg, #fff); border:1px solid var(--border, #E2E8F0); border-radius:12px; padding:18px; }
        .kpi-label { font-size:11px; font-weight:700; text-transform:uppercase; color:var(--text-muted, #64748B); letter-spacing:0.5px; }
        .kpi-value { font-size:26px; font-weight:800; color:var(--text-main, #0F172A); margin:6px 0 2px; }
        .kpi-sub { font-size:12px; color:var(--text-muted, #64748B); }
        .conv-item { background:var(--card-bg, #fff); border:1px solid var(--border, #E2E8F0); border-radius:12px; padding:16px; margin-bottom:14px; }
        .conv-turn { padding:9px 12px; border-radius:8px; margin-top:6px; font-size:12.5px; line-height:1.45; }
        .conv-turn.user { background:#F1F5F9; color:#0F172A; }
        .conv-turn.model { background:#FAF7F2; border:1px solid #E2D5C8; color:#2D1F17; }
        .conv-turn.tool { background:#EFF6FF; color:#1E40AF; font-family:monospace; font-size:11.5px; }
        .filter-bar { display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end; background:var(--card-bg, #fff); border:1px solid var(--border, #E2E8F0); border-radius:12px; padding:14px 16px; margin-bottom:16px; }
        .filter-group { display:flex; flex-direction:column; gap:4px; flex:1; min-width:150px; }
        .filter-group label { font-size:11px; font-weight:700; text-transform:uppercase; color:var(--text-muted, #64748B); }
        .filter-group input, .filter-group select { padding:8px 10px; border:1px solid var(--border, #CBD5E1); border-radius:8px; font-size:13px; background:var(--card-bg, #fff); color:var(--text-main, #0F172A); }
        .stat-bar-track { width:100%; height:7px; background:#E2E8F0; border-radius:999px; overflow:hidden; margin-top:4px; }
        .stat-bar-fill { height:100%; background:#84563C; border-radius:999px; }
        .trend-chart { display:grid; grid-template-columns:repeat(7, 1fr); gap:10px; align-items:end; height:140px; padding:14px 8px 6px; border-bottom:1px solid var(--border, #E2E8F0); }
        .trend-col { display:flex; flex-direction:column; align-items:center; justify-content:flex-end; height:100%; gap:4px; }
        .trend-bars { display:flex; align-items:flex-end; gap:4px; height:96px; width:100%; justify-content:center; }
        .trend-bar-conv { width:14px; background:#84563C; border-radius:4px 4px 0 0; min-height:4px; }
        .trend-bar-msg { width:14px; background:#D4A373; border-radius:4px 4px 0 0; min-height:4px; }
        .trend-label { font-size:11px; color:var(--text-muted, #64748B); font-weight:600; }
        .support-card { background:var(--card-bg, #fff); border:1px solid var(--border, #E2E8F0); border-radius:12px; padding:18px; margin-bottom:14px; }
        .support-card.unread { border-left:4px solid #EF4444; }
        .support-card.read { border-left:4px solid #F59E0B; }
        .support-card.resolved { border-left:4px solid #10B981; opacity:0.92; }
        .conv-turn.staff { background:#F0FDFA; border:1px solid #99F6E4; border-left:3px solid #0F766E; }
    </style>
<?php sf_reply_ui_render_styles(); ?>
</head>
<body>
<?php require_once __DIR__ . '/partials/_sidebar.php'; ?>

<main class="main-content">
    <?php
    $page_title = 'AI Concierge & Virtual Guest Assistant';
    $page_subtitle = 'Phase 3 Administration: Monitor guest conversations, manage the multilingual knowledge base, review analytics, and resolve human support escalations.';
    include __DIR__ . '/partials/_page_header.php';
    ?>

    <div class="admin-body">
        <?php
        $guest_communications_active = 'chatbot';
        include __DIR__ . '/partials/_guest_communications_tabs.php';
        ?>

        <?php if (!$tablesReady): ?>
            <div class="alert alert-error" style="margin-bottom:18px;">
                <strong>Database migration pending:</strong> Run <code>php backend/scripts/migrate.php</code> from the command line to create the <code>chatbot_conversations</code>, <code>chatbot_messages</code>, and <code>chatbot_knowledge</code> tables.
            </div>
        <?php endif; ?>

        <?php if ($flashSuccess !== ''): ?>
            <div class="alert alert-success" style="margin-bottom:16px;"><?php echo htmlspecialchars($flashSuccess); ?></div>
        <?php endif; ?>
        <?php if ($flashError !== ''): ?>
            <div class="alert alert-error" style="margin-bottom:16px;"><?php echo htmlspecialchars($flashError); ?></div>
        <?php endif; ?>

        <div class="ai-tabs" role="tablist">
            <button type="button" id="ai-tab-btn-analytics" class="ai-tab-btn <?php echo $activeTab === 'analytics' ? 'active' : ''; ?>" onclick="switchAiTab('analytics', this)">
                Chatbot Analytics
            </button>
            <button type="button" id="ai-tab-btn-conversations" class="ai-tab-btn <?php echo $activeTab === 'conversations' ? 'active' : ''; ?>" onclick="switchAiTab('conversations', this)">
                Conversation Monitoring <span class="ai-tab-badge"><?php echo count($recentConversations); ?></span>
            </button>
            <button type="button" id="ai-tab-btn-knowledge" class="ai-tab-btn <?php echo $activeTab === 'knowledge' ? 'active' : ''; ?>" onclick="switchAiTab('knowledge', this)">
                Knowledge Base Management <span class="ai-tab-badge"><?php echo count($kbRows); ?></span>
            </button>
            <button type="button" id="ai-tab-btn-support" class="ai-tab-btn <?php echo $activeTab === 'support' ? 'active' : ''; ?>" onclick="switchAiTab('support', this)">
                Human Support &amp; Escalations
                <span class="ai-tab-badge <?php echo $metrics['open_handoffs'] > 0 ? 'alert-badge' : ''; ?>"><?php echo (int)$metrics['open_handoffs']; ?> open</span>
            </button>
            <button type="button" id="ai-tab-btn-settings" class="ai-tab-btn <?php echo $activeTab === 'settings' ? 'active' : ''; ?>" onclick="switchAiTab('settings', this)">
                Settings &amp; Privacy
            </button>
        </div>

        <!-- TAB 1: ANALYTICS -->
        <section id="ai-tab-analytics" class="ai-panel <?php echo $activeTab === 'analytics' ? 'active' : ''; ?>">
            <div class="kpi-grid">
                <div class="kpi-card">
                    <div class="kpi-label">Total Conversations</div>
                    <div class="kpi-value"><?php echo number_format($metrics['total_conversations']); ?></div>
                    <div class="kpi-sub"><?php echo number_format($metrics['total_messages']); ?> total messages exchanged</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Automated Resolution Rate</div>
                    <div class="kpi-value"><?php echo $metrics['resolution_rate']; ?>%</div>
                    <div class="kpi-sub"><?php echo $metrics['handoff_count']; ?> escalated to human staff</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Guest Helpfulness Score</div>
                    <div class="kpi-value"><?php echo $metrics['satisfaction_pct']; ?>%</div>
                    <div class="kpi-sub">Helpful: <?php echo $metrics['positive_feedback']; ?> &middot; Unhelpful: <?php echo $metrics['negative_feedback']; ?></div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Booking Link Conversions</div>
                    <div class="kpi-value"><?php echo number_format($metrics['booking_clicks']); ?></div>
                    <div class="kpi-sub">Clicks on pre-filled booking links</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Avg Response Time</div>
                    <div class="kpi-value"><?php echo number_format($metrics['avg_latency_ms']); ?> ms</div>
                    <div class="kpi-sub">Provider: <?php echo $geminiKeyConfigured ? 'Gemini (' . htmlspecialchars($geminiModelName) . ')' : 'Local Grounded Engine'; ?></div>
                </div>
            </div>

            <!-- 7-Day Trend + Tool Usage -->
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(340px, 1fr)); gap:20px; margin-bottom:20px;">
                <div class="admin-card">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                        <h3 style="font-size:15px; font-weight:700; margin:0;">7-Day Conversation &amp; Message Activity</h3>
                        <span style="font-size:11.5px; color:var(--text-muted);">
                            <span style="display:inline-block;width:10px;height:10px;background:#84563C;border-radius:2px;margin-right:4px;"></span>Sessions
                            <span style="display:inline-block;width:10px;height:10px;background:#D4A373;border-radius:2px;margin:0 4px 0 10px;"></span>Messages
                        </span>
                    </div>
                    <?php
                    $maxTrendVal = 1;
                    foreach ($dailyTrend as $dt) {
                        if ($dt['conversations'] > $maxTrendVal) $maxTrendVal = $dt['conversations'];
                        if ($dt['messages'] > $maxTrendVal) $maxTrendVal = $dt['messages'];
                    }
                    ?>
                    <div class="trend-chart">
                        <?php foreach ($dailyTrend as $dt):
                            $cH = max(4, (int)round(($dt['conversations'] / $maxTrendVal) * 88));
                            $mH = max(4, (int)round(($dt['messages'] / $maxTrendVal) * 88));
                        ?>
                            <div class="trend-col" title="<?php echo htmlspecialchars($dt['date'] . ': ' . $dt['conversations'] . ' sessions, ' . $dt['messages'] . ' messages'); ?>">
                                <div style="font-size:10px; color:var(--text-muted);"><?php echo (int)$dt['conversations']; ?>/<?php echo (int)$dt['messages']; ?></div>
                                <div class="trend-bars">
                                    <div class="trend-bar-conv" style="height:<?php echo $cH; ?>px;"></div>
                                    <div class="trend-bar-msg" style="height:<?php echo $mH; ?>px;"></div>
                                </div>
                                <div class="trend-label"><?php echo htmlspecialchars($dt['label']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="admin-card">
                    <h3 style="font-size:15px; font-weight:700; margin-bottom:12px;">Live Database Tool Invocations</h3>
                    <table class="admin-table" style="width:100%;">
                        <thead><tr><th>Function / Tool Name</th><th>Invocations</th></tr></thead>
                        <tbody>
                        <?php if (empty($toolStats)): ?>
                            <tr><td colspan="2" style="color:var(--text-muted);">No database tool calls recorded yet.</td></tr>
                        <?php else:
                            $totalTools = array_sum(array_map(static fn($r) => (int)$r['cnt'], $toolStats));
                            foreach ($toolStats as $ts):
                                $pct = $totalTools > 0 ? round(((int)$ts['cnt'] / $totalTools) * 100, 1) : 0;
                        ?>
                            <tr>
                                <td>
                                    <code><?php echo htmlspecialchars((string)$ts['tool_name']); ?></code>
                                    <div class="stat-bar-track"><div class="stat-bar-fill" style="width:<?php echo $pct; ?>%;"></div></div>
                                </td>
                                <td style="font-weight:700;"><?php echo (int)$ts['cnt']; ?> <span style="font-size:11px; color:var(--text-muted); font-weight:400;">(<?php echo $pct; ?>%)</span></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Intents & Languages -->
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:20px; margin-bottom:20px;">
                <div class="admin-card">
                    <h3 style="font-size:15px; font-weight:700; margin-bottom:12px;">Top Guest Question Intents</h3>
                    <table class="admin-table" style="width:100%;">
                        <thead><tr><th>Intent Category</th><th>Questions</th></tr></thead>
                        <tbody>
                        <?php if (empty($intentStats)): ?>
                            <tr><td colspan="2" style="color:var(--text-muted);">No guest messages recorded yet.</td></tr>
                        <?php else:
                            $totalIntentMsgs = array_sum(array_map(static fn($r) => (int)$r['cnt'], $intentStats));
                            foreach ($intentStats as $is):
                                $iPct = $totalIntentMsgs > 0 ? round(((int)$is['cnt'] / $totalIntentMsgs) * 100, 1) : 0;
                        ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars((string)$is['intent']); ?></strong>
                                    <div class="stat-bar-track"><div class="stat-bar-fill" style="width:<?php echo $iPct; ?>%;"></div></div>
                                </td>
                                <td><strong><?php echo (int)$is['cnt']; ?></strong> <span style="font-size:11px; color:var(--text-muted);">(<?php echo $iPct; ?>%)</span></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="admin-card">
                    <h3 style="font-size:15px; font-weight:700; margin-bottom:12px;">Language Distribution</h3>
                    <table class="admin-table" style="width:100%;">
                        <thead><tr><th>Language</th><th>Conversations</th></tr></thead>
                        <tbody>
                        <?php if (empty($langStats)): ?>
                            <tr><td colspan="2" style="color:var(--text-muted);">No conversations recorded yet.</td></tr>
                        <?php else:
                            $totalLangConvs = array_sum(array_map(static fn($r) => (int)$r['cnt'], $langStats));
                            foreach ($langStats as $ls):
                                $lPct = $totalLangConvs > 0 ? round(((int)$ls['cnt'] / $totalLangConvs) * 100, 1) : 0;
                        ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars((string)$ls['language']); ?></strong>
                                    <div class="stat-bar-track"><div class="stat-bar-fill" style="width:<?php echo $lPct; ?>%;"></div></div>
                                </td>
                                <td><strong><?php echo (int)$ls['cnt']; ?></strong> <span style="font-size:11px; color:var(--text-muted);">(<?php echo $lPct; ?>%)</span></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Unanswered Guest Questions (Knowledge Gap Detector) -->
            <div class="admin-card" style="margin-bottom:20px;">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; margin-bottom:12px;">
                    <div>
                        <h3 style="font-size:15px; font-weight:700; margin:0;">Unanswered Guest Questions (Knowledge Gap Detector)</h3>
                        <p style="font-size:12px; color:var(--text-muted); margin:2px 0 0;">Questions automatically flagged when no verified database rule or FAQ matched. Click <strong>Teach AI</strong> to add an official answer to the Knowledge Base.</p>
                    </div>
                    <span class="badge badge-pending"><?php echo count($unansweredMessages); ?> detected</span>
                </div>
                <table class="admin-table" style="width:100%;">
                    <thead>
                        <tr>
                            <th>Session</th>
                            <th>Language</th>
                            <th>Unanswered Guest Question</th>
                            <th>Timestamp</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($unansweredMessages)): ?>
                        <tr><td colspan="5" style="color:var(--text-muted);">No unanswered guest questions detected — all recent inquiries matched verified resort data or FAQs!</td></tr>
                    <?php else: foreach ($unansweredMessages as $um): ?>
                        <tr>
                            <td><strong>#<?php echo (int)$um['conversation_id']; ?></strong></td>
                            <td><span class="badge badge-confirmed"><?php echo htmlspecialchars((string)$um['language']); ?></span></td>
                            <td><strong><?php echo htmlspecialchars((string)$um['user_question']); ?></strong></td>
                            <td style="font-size:12px; white-space:nowrap;"><?php echo htmlspecialchars((string)$um['created_at']); ?></td>
                            <td style="white-space:nowrap;">
                                <button type="button" class="btn-primary" style="padding:5px 10px; font-size:11.5px;"
                                    onclick="prefillKbFromFlagged(<?php echo htmlspecialchars(json_encode((string)$um['user_question']), ENT_QUOTES, 'UTF-8'); ?>)">
                                    Teach AI (Add to FAQ)
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Flagged / Unhelpful Responses Queue -->
            <div class="admin-card">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; margin-bottom:12px;">
                    <div>
                        <h3 style="font-size:15px; font-weight:700; margin:0;">Flagged / Unhelpful Responses Queue</h3>
                        <p style="font-size:12px; color:var(--text-muted); margin:2px 0 0;">Review bot answers rated unhelpful by guests and convert gaps directly into Knowledge Base entries.</p>
                    </div>
                </div>
                <table class="admin-table" style="width:100%;">
                    <thead>
                        <tr>
                            <th>Session</th>
                            <th>Guest Question</th>
                            <th>Flagged Bot Response</th>
                            <th>Timestamp</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($flaggedMessages)): ?>
                        <tr><td colspan="5" style="color:var(--text-muted);">No unhelpful responses flagged by guests.</td></tr>
                    <?php else: foreach ($flaggedMessages as $fm): ?>
                        <tr>
                            <td><strong>#<?php echo (int)$fm['conversation_id']; ?></strong></td>
                            <td><?php echo htmlspecialchars((string)($fm['user_question'] ?? 'N/A')); ?></td>
                            <td style="font-size:12px; color:var(--text-muted);"><?php echo htmlspecialchars(mb_strimwidth((string)$fm['bot_reply'], 0, 160, '...')); ?></td>
                            <td style="font-size:12px; white-space:nowrap;"><?php echo htmlspecialchars((string)$fm['created_at']); ?></td>
                            <td style="white-space:nowrap;">
                                <button type="button" class="btn-secondary" style="padding:5px 10px; font-size:11.5px;"
                                    onclick="prefillKbFromFlagged(<?php echo htmlspecialchars(json_encode((string)($fm['user_question'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>)">
                                    Add to FAQ
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- TAB 2: CONVERSATION MONITORING -->
        <section id="ai-tab-conversations" class="ai-panel <?php echo $activeTab === 'conversations' ? 'active' : ''; ?>">
            <!-- Conversation Monitoring Overview & Quick Filter KPI Strip -->
            <div class="admin-card" style="margin-bottom:16px; border-left:4px solid #84563C;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px;">
                    <div>
                        <h3 style="font-size:16px; font-weight:800; margin:0; color:var(--text-main, #0F172A);">Conversation Monitoring Center</h3>
                        <p style="font-size:12.5px; color:var(--text-muted, #64748B); margin:4px 0 0;">
                            <strong>1. View Guest Conversations:</strong> Inspect full multi-turn guest transcripts across all languages ·
                            <strong>2. Identify Unanswered Questions:</strong> Detect knowledge gaps &amp; teach the AI in 1 click ·
                            <strong>3. Monitor Chatbot Responses:</strong> Audit database tool calls, latency, and guest helpful/unhelpful ratings.
                        </p>
                    </div>
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        <a href="admin_chatbot?tab=conversations&amp;conv_status=unanswered" class="btn-secondary" style="padding:6px 12px; font-size:12px; text-decoration:none; <?php echo $convStatus === 'unanswered' ? 'border-color:#D97706; background:#FFFBEB; color:#92400E; font-weight:700;' : ''; ?>">
                            Unanswered Questions (<?php echo count($unansweredMessages); ?>)
                        </a>
                        <a href="admin_chatbot?tab=conversations&amp;conv_status=flagged" class="btn-secondary" style="padding:6px 12px; font-size:12px; text-decoration:none; <?php echo $convStatus === 'flagged' ? 'border-color:#EF4444; background:#FEF2F2; color:#991B1B; font-weight:700;' : ''; ?>">
                            Flagged Responses (<?php echo count($flaggedMessages); ?>)
                        </a>
                        <a href="admin_chatbot?tab=conversations" class="btn-secondary" style="padding:6px 12px; font-size:12px; text-decoration:none;">
                            All Sessions (<?php echo (int)$metrics['total_conversations']; ?>)
                        </a>
                    </div>
                </div>
            </div>

            <!-- Unanswered Questions & Monitored Bot Responses Inspector (Directly in Conversation Monitoring) -->
            <div class="admin-card" style="margin-bottom:18px;">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; margin-bottom:10px;">
                    <div>
                        <h3 style="font-size:14.5px; font-weight:700; margin:0;">Identified Unanswered Guest Questions &amp; Monitored Bot Replies</h3>
                        <p style="font-size:12px; color:var(--text-muted); margin:2px 0 0;">Questions where the guest asked something not yet covered in the Knowledge Base, alongside the chatbot's monitored response.</p>
                    </div>
                    <span class="badge <?php echo count($unansweredMessages) > 0 ? 'badge-pending' : 'badge-checkedin'; ?>">
                        <?php echo count($unansweredMessages); ?> unanswered question(s)
                    </span>
                </div>
                <table class="admin-table" style="width:100%;">
                    <thead>
                        <tr>
                            <th>Session</th>
                            <th>Language</th>
                            <th>Guest Question (Unanswered)</th>
                            <th>Monitored Chatbot Response</th>
                            <th>Timestamp</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($unansweredMessages)): ?>
                        <tr><td colspan="6" style="color:var(--text-muted);">No unanswered guest questions detected — all recent guest inquiries were answered with verified resort data!</td></tr>
                    <?php else: foreach ($unansweredMessages as $um): ?>
                        <tr>
                            <td>
                                <a href="#conv-card-<?php echo (int)$um['conversation_id']; ?>" onclick="jumpToConversationCard(<?php echo (int)$um['conversation_id']; ?>)" style="font-weight:700; color:#84563C; text-decoration:underline;">
                                    #<?php echo (int)$um['conversation_id']; ?> ↓
                                </a>
                            </td>
                            <td><span class="badge badge-confirmed"><?php echo htmlspecialchars((string)$um['language']); ?></span></td>
                            <td><strong style="color:#92400E;"><?php echo htmlspecialchars((string)$um['user_question']); ?></strong></td>
                            <td style="font-size:12px; color:var(--text-muted);"><?php echo htmlspecialchars(mb_strimwidth((string)($um['bot_reply'] ?? 'Flagged for human follow-up'), 0, 130, '...')); ?></td>
                            <td style="font-size:12px; white-space:nowrap;"><?php echo htmlspecialchars((string)$um['created_at']); ?></td>
                            <td style="white-space:nowrap;">
                                <button type="button" class="btn-primary" style="padding:5px 10px; font-size:11.5px;"
                                    onclick="prefillKbFromFlagged(<?php echo htmlspecialchars(json_encode((string)$um['user_question']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode((string)$um['language']), ENT_QUOTES, 'UTF-8'); ?>)">
                                    Teach AI (Add Answer)
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <form method="GET" action="admin_chatbot" class="filter-bar">
                <input type="hidden" name="tab" value="conversations">
                <div class="filter-group" style="flex:2; min-width:220px;">
                    <label for="convSearchInput">Keyword or Session #</label>
                    <input type="text" id="convSearchInput" name="conv_q" value="<?php echo htmlspecialchars($convSearch); ?>" placeholder="Search guest questions, chatbot responses, or session ID..." oninput="filterConversationsClient(this.value)">
                </div>
                <div class="filter-group">
                    <label for="convLangSelect">Language</label>
                    <select id="convLangSelect" name="conv_lang">
                        <option value="all" <?php echo $convLang === 'all' ? 'selected' : ''; ?>>All Languages</option>
                        <?php foreach ($supportedLanguages as $sLang): ?>
                            <option value="<?php echo htmlspecialchars($sLang); ?>" <?php echo $convLang === $sLang ? 'selected' : ''; ?>><?php echo htmlspecialchars($sLang); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="convStatusSelect">Monitoring Filter</label>
                    <select id="convStatusSelect" name="conv_status">
                        <option value="all" <?php echo $convStatus === 'all' ? 'selected' : ''; ?>>All Conversations</option>
                        <option value="unanswered" <?php echo $convStatus === 'unanswered' ? 'selected' : ''; ?>>Has Unanswered Question</option>
                        <option value="flagged" <?php echo $convStatus === 'flagged' ? 'selected' : ''; ?>>Has Unhelpful / Flagged Reply</option>
                        <option value="resolved" <?php echo $convStatus === 'resolved' ? 'selected' : ''; ?>>Automated / Resolved</option>
                        <option value="handed_off" <?php echo $convStatus === 'handed_off' ? 'selected' : ''; ?>>Escalated to Staff</option>
                        <option value="otp_verified" <?php echo $convStatus === 'otp_verified' ? 'selected' : ''; ?>>OTP Verified Guest</option>
                        <option value="booking_clicked" <?php echo $convStatus === 'booking_clicked' ? 'selected' : ''; ?>>Clicked Booking Link</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="convDaysSelect">Time Range</label>
                    <select id="convDaysSelect" name="conv_days">
                        <option value="all" <?php echo $convDays === 'all' ? 'selected' : ''; ?>>All Time</option>
                        <option value="1" <?php echo $convDays === '1' ? 'selected' : ''; ?>>Last 24 Hours</option>
                        <option value="7" <?php echo $convDays === '7' ? 'selected' : ''; ?>>Last 7 Days</option>
                        <option value="30" <?php echo $convDays === '30' ? 'selected' : ''; ?>>Last 30 Days</option>
                    </select>
                </div>
                <div style="display:flex; gap:8px;">
                    <button type="submit" class="btn-primary" style="padding:8px 14px; font-size:13px;">Filter</button>
                    <a href="admin_chatbot?tab=conversations" class="btn-secondary" style="padding:8px 12px; font-size:13px; text-decoration:none;">Reset</a>
                    <a href="admin_chatbot?export=conversations_csv&amp;conv_q=<?php echo urlencode($convSearch); ?>&amp;conv_lang=<?php echo urlencode($convLang); ?>&amp;conv_status=<?php echo urlencode($convStatus); ?>&amp;conv_days=<?php echo urlencode($convDays); ?>" class="btn-secondary" style="padding:8px 12px; font-size:13px; text-decoration:none;">Export CSV</a>
                </div>
            </form>

            <?php if (empty($recentConversations)): ?>
                <div class="admin-card"><p style="color:var(--text-muted);">No guest conversations match the selected filters.</p></div>
            <?php else: foreach ($recentConversations as $conv):
                $hasUnansweredInConv = false;
                $hasFlaggedInConv = false;
                foreach ($conv['messages'] as $chkM) {
                    if (($chkM['intent'] ?? '') === 'unanswered') {
                        $hasUnansweredInConv = true;
                    }
                    if ((int)($chkM['feedback'] ?? 0) === -1) {
                        $hasFlaggedInConv = true;
                    }
                }
            ?>
                <div class="conv-item" id="conv-card-<?php echo (int)$conv['id']; ?>" style="<?php echo $hasUnansweredInConv ? 'border-left:4px solid #F59E0B;' : ($hasFlaggedInConv ? 'border-left:4px solid #EF4444;' : ''); ?>" data-conv-search="<?php echo htmlspecialchars(strtolower('#' . $conv['id'] . ' ' . $conv['language'] . ' ' . implode(' ', array_column($conv['messages'], 'content')))); ?>">
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                        <div style="display:flex; align-items:center; flex-wrap:wrap; gap:6px;">
                            <strong>Session #<?php echo (int)$conv['id']; ?></strong>
                            <span class="badge badge-confirmed"><?php echo htmlspecialchars((string)$conv['language']); ?></span>
                            <span class="badge badge-checkedout"><?php echo (int)$conv['msg_count']; ?> msgs</span>
                            <?php if ($hasUnansweredInConv): ?>
                                <span class="badge badge-pending" style="background:#FEF3C7; color:#92400E; border:1px solid #FCD34D;">Unanswered Question</span>
                            <?php endif; ?>
                            <?php if ($hasFlaggedInConv): ?>
                                <span class="badge badge-pending" style="background:#FEE2E2; color:#991B1B; border:1px solid #FCA5A5;">Unhelpful Flagged</span>
                            <?php endif; ?>
                            <?php if (!empty($conv['booking_id'])): ?>
                                <span class="badge badge-checkedin">OTP Verified (REF-<?php echo str_pad((string)$conv['booking_id'], 3, '0', STR_PAD_LEFT); ?>)</span>
                            <?php endif; ?>
                            <?php if (!empty($conv['handed_off'])): ?>
                                <span class="badge badge-pending">Escalated to Staff<?php echo !empty($conv['inquiry_id']) ? ' (Ticket #' . (int)$conv['inquiry_id'] . ')' : ''; ?></span>
                            <?php else: ?>
                                <span class="badge badge-checkedin">Automated</span>
                            <?php endif; ?>
                            <?php if ((int)$conv['booking_link_clicks'] > 0): ?>
                                <span class="badge badge-checkedin"><?php echo (int)$conv['booking_link_clicks']; ?> Booking Click(s)</span>
                            <?php endif; ?>
                        </div>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <span style="font-size:12px; color:var(--text-muted);">Last active: <?php echo htmlspecialchars((string)$conv['last_message_at']); ?></span>
                            <?php if (empty($conv['handed_off'])): ?>
                                <form method="POST" style="display:inline;">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="escalate_conversation">
                                    <input type="hidden" name="conversation_id" value="<?php echo (int)$conv['id']; ?>">
                                    <input type="hidden" name="escalation_note" value="Manually escalated by administrator from Conversation Monitoring.">
                                    <button type="submit" class="btn-secondary" style="padding:4px 10px; font-size:11.5px;">Escalate to Human Support</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div style="margin-top:10px;">
                        <?php
                        $lastGuestQuestionInConv = '';
                        foreach ($conv['messages'] as $m):
                            $isUserTurn = ($m['role'] === 'user');
                            if ($isUserTurn) {
                                $lastGuestQuestionInConv = (string)$m['content'];
                            }
                            $isUnansTurn = ($isUserTurn && ($m['intent'] ?? '') === 'unanswered');
                            $isNegTurn = (!$isUserTurn && (int)($m['feedback'] ?? 0) === -1);
                            $turnExtraStyle = $isUnansTurn
                                ? 'background:#FFFBEB; border:1px solid #FCD34D;'
                                : ($isNegTurn ? 'background:#FEF2F2; border:1px solid #FCA5A5;' : '');
                        ?>
                            <div class="conv-turn <?php echo htmlspecialchars((string)$m['role']); ?>" style="<?php echo $turnExtraStyle; ?>">
                                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; margin-bottom:3px;">
                                    <div style="display:flex; align-items:center; flex-wrap:wrap; gap:6px;">
                                        <strong>
                                            <?php if ($m['role'] === 'user'): ?>
                                                GUEST INQUIRY
                                            <?php elseif ($m['role'] === 'model'): ?>
                                                CHATBOT RESPONSE
                                            <?php elseif ($m['role'] === 'staff'): ?>
                                                RECEPTION STAFF
                                            <?php else: ?>
                                                <?php echo strtoupper(htmlspecialchars((string)$m['role'])); ?>
                                            <?php endif; ?>
                                        </strong>
                                        <?php if (!empty($m['intent'])): ?>
                                            <span style="font-size:11px; font-weight:600; color:#64748B;">[Intent: <?php echo htmlspecialchars((string)$m['intent']); ?>]</span>
                                        <?php endif; ?>
                                        <?php if ($isUnansTurn): ?>
                                            <span style="background:#F59E0B; color:#fff; font-size:10px; font-weight:700; padding:1px 6px; border-radius:4px;">UNANSWERED QUESTION</span>
                                        <?php endif; ?>
                                        <?php if (!empty($m['tool_name'])): ?>
                                            <span style="font-size:11px; font-weight:600; color:#1D4ED8;">[Tool: <?php echo htmlspecialchars((string)$m['tool_name']); ?>]</span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="display:flex; align-items:center; gap:8px; font-size:11px; color:var(--text-muted);">
                                        <?php if (!empty($m['provider'])): ?><span>Provider: <strong><?php echo htmlspecialchars((string)$m['provider']); ?></strong></span> · <?php endif; ?>
                                        <?php if (!empty($m['latency_ms'])): ?><span><?php echo (int)$m['latency_ms']; ?>ms</span> · <?php endif; ?>
                                        <span><?php echo htmlspecialchars((string)$m['created_at']); ?></span>
                                        <?php if ((int)$m['feedback'] !== 0): ?>
                                            <strong style="margin-left:4px; color:<?php echo (int)$m['feedback'] > 0 ? '#16A34A' : '#DC2626'; ?>;">
                                                <?php echo (int)$m['feedback'] > 0 ? 'Helpful' : 'Unhelpful'; ?>
                                            </strong>
                                        <?php endif; ?>
                                        <?php if ($isUnansTurn): ?>
                                            <button type="button" class="btn-primary" style="padding:2px 8px; font-size:10.5px;"
                                                onclick="prefillKbFromFlagged(<?php echo htmlspecialchars(json_encode((string)$m['content']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode((string)$conv['language']), ENT_QUOTES, 'UTF-8'); ?>)">
                                                Teach AI this Question
                                            </button>
                                        <?php elseif ($isNegTurn && $lastGuestQuestionInConv !== ''): ?>
                                            <button type="button" class="btn-secondary" style="padding:2px 8px; font-size:10.5px;"
                                                onclick="prefillKbFromFlagged(<?php echo htmlspecialchars(json_encode($lastGuestQuestionInConv), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode((string)$conv['language']), ENT_QUOTES, 'UTF-8'); ?>)">
                                                Improve Answer in FAQ
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php echo nl2br(htmlspecialchars((string)$m['content'])); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </section>

        <!-- TAB 3: KNOWLEDGE BASE MANAGEMENT -->
        <section id="ai-tab-knowledge" class="ai-panel <?php echo $activeTab === 'knowledge' ? 'active' : ''; ?>">
            <div class="admin-card" id="kbEditorCard" style="margin-bottom:20px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                    <h3 id="kbFormHeading" style="font-size:15px; font-weight:700; margin:0;">Add New Multilingual FAQ Entry</h3>
                    <button type="button" id="kbCancelEditBtn" class="btn-secondary" style="display:none; padding:5px 12px; font-size:12px;" onclick="resetKbForm()">Cancel Edit</button>
                </div>
                <form method="POST" id="kbEditorForm">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="save_kb">
                    <input type="hidden" name="kb_id" id="kbFormId" value="0">
                    <div class="admin-form-row" style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
                        <div class="admin-form-group">
                            <label for="kbFormCategory">Category</label>
                            <select name="category" id="kbFormCategory">
                                <?php foreach ($allowedCategories as $catKey => $catLabel): ?>
                                    <option value="<?php echo htmlspecialchars($catKey); ?>"><?php echo htmlspecialchars($catLabel); ?> (<?php echo htmlspecialchars($catKey); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="admin-form-group">
                            <label for="kbFormLanguage">Language</label>
                            <select name="language" id="kbFormLanguage">
                                <?php foreach ($supportedLanguages as $sLang): ?>
                                    <option value="<?php echo htmlspecialchars($sLang); ?>"><?php echo htmlspecialchars($sLang); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="admin-form-group" style="margin-bottom:12px;">
                        <label for="kbFormQuestion">Guest Question</label>
                        <input type="text" name="question" id="kbFormQuestion" required maxlength="300" placeholder="e.g. What time is check-in and check-out?">
                    </div>
                    <div class="admin-form-group" style="margin-bottom:12px;">
                        <label for="kbFormAnswer">Grounded Official Answer</label>
                        <textarea name="answer" id="kbFormAnswer" rows="3" required style="width:100%; padding:10px; border:1px solid var(--border, #CBD5E1); border-radius:8px;" placeholder="Enter the official resort answer..."></textarea>
                    </div>
                    <button type="submit" id="kbSubmitBtn" class="btn-primary">Save FAQ Entry</button>
                </form>
            </div>

            <div class="admin-card">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:14px;">
                    <h3 style="font-size:15px; font-weight:700; margin:0;">Existing Knowledge Base Entries (<?php echo count($kbRows); ?>)</h3>
                    <div style="display:flex; flex-wrap:wrap; gap:8px;">
                        <input type="text" id="kbSearchFilter" placeholder="Filter FAQs by keyword..." oninput="filterKbTable()" style="padding:7px 10px; border:1px solid var(--border, #CBD5E1); border-radius:8px; font-size:12.5px; min-width:200px;">
                        <select id="kbCategoryFilter" onchange="filterKbTable()" style="padding:7px 10px; border:1px solid var(--border, #CBD5E1); border-radius:8px; font-size:12.5px;">
                            <option value="all">All Categories</option>
                            <?php foreach ($allowedCategories as $catKey => $catLabel): ?>
                                <option value="<?php echo htmlspecialchars($catKey); ?>"><?php echo htmlspecialchars($catLabel); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select id="kbLanguageFilter" onchange="filterKbTable()" style="padding:7px 10px; border:1px solid var(--border, #CBD5E1); border-radius:8px; font-size:12.5px;">
                            <option value="all">All Languages</option>
                            <?php foreach ($supportedLanguages as $sLang): ?>
                                <option value="<?php echo htmlspecialchars($sLang); ?>"><?php echo htmlspecialchars($sLang); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <table class="admin-table" id="kbEntriesTable" style="width:100%;">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Category</th>
                            <th>Language</th>
                            <th>Question &amp; Answer</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($kbRows as $kb): ?>
                        <tr class="kb-row"
                            data-kb-category="<?php echo htmlspecialchars((string)$kb['category']); ?>"
                            data-kb-language="<?php echo htmlspecialchars((string)$kb['language']); ?>"
                            data-kb-search="<?php echo htmlspecialchars(strtolower($kb['category'] . ' ' . $kb['language'] . ' ' . $kb['question'] . ' ' . $kb['answer'])); ?>">
                            <td>#<?php echo (int)$kb['id']; ?></td>
                            <td><span class="badge badge-confirmed"><?php echo htmlspecialchars((string)$kb['category']); ?></span></td>
                            <td><?php echo htmlspecialchars((string)$kb['language']); ?></td>
                            <td>
                                <strong>Q: <?php echo htmlspecialchars((string)$kb['question']); ?></strong><br>
                                <span style="font-size:12px; color:var(--text-muted);">A: <?php echo htmlspecialchars((string)$kb['answer']); ?></span>
                            </td>
                            <td><?php echo (int)$kb['is_active'] === 1 ? '<span class="badge badge-checkedin">Active</span>' : '<span class="badge badge-checkedout">Disabled</span>'; ?></td>
                            <td style="white-space:nowrap;">
                                <button type="button" class="btn-secondary" style="padding:4px 8px; font-size:11px;"
                                    onclick='populateKbEdit(<?php echo json_encode([
                                        "id" => (int)$kb["id"],
                                        "category" => (string)$kb["category"],
                                        "language" => (string)$kb["language"],
                                        "question" => (string)$kb["question"],
                                        "answer" => (string)$kb["answer"],
                                    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'>
                                    Edit
                                </button>
                                <form method="POST" style="display:inline;">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="toggle_kb">
                                    <input type="hidden" name="kb_id" value="<?php echo (int)$kb['id']; ?>">
                                    <button type="submit" class="btn-secondary" style="padding:4px 8px; font-size:11px;"><?php echo (int)$kb['is_active'] === 1 ? 'Disable' : 'Enable'; ?></button>
                                </form>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete FAQ entry #<?php echo (int)$kb['id']; ?>?');">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete_kb">
                                    <input type="hidden" name="kb_id" value="<?php echo (int)$kb['id']; ?>">
                                    <button type="submit" class="btn-danger" style="padding:4px 8px; font-size:11px;">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- TAB 4: HUMAN SUPPORT & ESCALATIONS -->
        <section id="ai-tab-support" class="ai-panel <?php echo $activeTab === 'support' ? 'active' : ''; ?>">
            <div class="admin-card" style="margin-bottom:18px;">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                    <div>
                        <h3 style="font-size:15px; font-weight:700; margin:0;">Human Support &amp; Live Escalation Queue</h3>
                        <p style="font-size:12.5px; color:var(--text-muted); margin:4px 0 0;">
                            Conversations escalated by guests (via the <em>Talk to Human Staff</em> drawer, repeated unanswered prompts, or negative feedback) or flagged by administrators appear here for staff follow-up.
                        </p>
                    </div>
                    <a href="admin_live_support" class="btn-secondary" style="padding:7px 14px; font-size:12.5px; text-decoration:none;">Open Live Chats Inbox &rarr;</a>
                </div>
            </div>

            <?php
            $handoffThreads = !empty($handoffQueue)
                ? sf_inquiry_get_threads($conn, array_map(static fn($h) => (int)$h['inquiry_id'], $handoffQueue))
                : [];
            if (empty($handoffQueue)): ?>
                <div class="admin-card">
                    <p style="color:var(--text-muted); margin:0;">No human support escalations in queue. All guest conversations are currently resolved by the AI Concierge.</p>
                </div>
            <?php else: foreach ($handoffQueue as $hq):
                $statusClass = strtolower((string)($hq['status'] ?? 'unread'));
                $inqId = (int)$hq['inquiry_id'];
                $hasRealEmail = sf_reply_ui_has_real_email($hq['guest_email'] ?? '');
                $thread = $handoffThreads[$inqId] ?? [];
            ?>
                <div class="support-card <?php echo htmlspecialchars($statusClass); ?>" data-inquiry-card data-inquiry-id="<?php echo $inqId; ?>">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:10px; margin-bottom:10px;">
                        <div>
                            <span class="badge <?php echo $hq['status'] === 'Resolved' ? 'badge-checkedin' : ($hq['status'] === 'Read' ? 'badge-confirmed' : 'badge-pending'); ?>" data-status-badge>
                                <?php echo htmlspecialchars((string)$hq['status']); ?>
                            </span>
                            <strong style="margin-left:6px; font-size:14px;">Ticket #<?php echo $inqId; ?> — <?php echo htmlspecialchars((string)$hq['subject']); ?></strong>
                            <?php if (!empty($hq['conversation_id'])): ?>
                                <span class="badge badge-confirmed" style="margin-left:6px;">Chat Session #<?php echo (int)$hq['conversation_id']; ?> (<?php echo htmlspecialchars((string)($hq['language'] ?? 'English')); ?>)</span>
                            <?php endif; ?>
                            <div style="font-size:12px; color:var(--text-muted); margin-top:4px;">
                                Guest: <strong><?php echo htmlspecialchars((string)$hq['guest_name']); ?></strong> &middot;
                                <?php if ($hasRealEmail): ?>
                                    Email: <a href="mailto:<?php echo htmlspecialchars((string)$hq['guest_email']); ?>?subject=<?php echo rawurlencode('Re: ' . (string)$hq['subject']); ?>"><?php echo htmlspecialchars((string)$hq['guest_email']); ?></a> &middot;
                                <?php else: ?>
                                    No email provided (Live Widget Session) &middot;
                                <?php endif; ?>
                                Escalated: <?php echo htmlspecialchars((string)$hq['created_at']); ?>
                            </div>
                        </div>

                        <div style="display:flex; gap:6px; flex-wrap:wrap;">
                            <?php if ($hq['status'] !== 'Read'): ?>
                                <form method="POST" style="display:inline;">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="update_handoff_status">
                                    <input type="hidden" name="inquiry_id" value="<?php echo $inqId; ?>">
                                    <input type="hidden" name="new_status" value="Read">
                                    <button type="submit" class="btn-secondary" style="padding:5px 10px; font-size:11.5px;">Mark In-Progress (Read)</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($hq['status'] !== 'Resolved'): ?>
                                <form method="POST" style="display:inline;">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="update_handoff_status">
                                    <input type="hidden" name="inquiry_id" value="<?php echo $inqId; ?>">
                                    <input type="hidden" name="new_status" value="Resolved">
                                    <button type="submit" class="btn-primary" style="padding:5px 10px; font-size:11.5px;">Mark Resolved</button>
                                </form>
                            <?php else: ?>
                                <form method="POST" style="display:inline;">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="update_handoff_status">
                                    <input type="hidden" name="inquiry_id" value="<?php echo $inqId; ?>">
                                    <input type="hidden" name="new_status" value="Unread">
                                    <button type="submit" class="btn-secondary" style="padding:5px 10px; font-size:11.5px;">Re-open Ticket</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($hasRealEmail): ?>
                                <a href="mailto:<?php echo htmlspecialchars((string)$hq['guest_email']); ?>?subject=<?php echo rawurlencode('Santa Fe Beach Club Support - ' . (string)$hq['subject']); ?>"
                                   class="btn-secondary" style="padding:5px 10px; font-size:11.5px; text-decoration:none;">Open in Mail App</a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:8px; padding:12px; font-size:12.5px; white-space:pre-wrap; line-height:1.5; color:#1E293B; margin-bottom:4px;"><?php echo htmlspecialchars((string)$hq['message']); ?></div>

                    <?php sf_reply_ui_render_panel($inqId, (string)($hq['guest_email'] ?? ''), (string)($hq['status'] ?? 'Unread'), $thread); ?>
                </div>
            <?php endforeach; endif; ?>
        </section>

        <!-- TAB 5: SETTINGS & PRIVACY -->
        <section id="ai-tab-settings" class="ai-panel <?php echo $activeTab === 'settings' ? 'active' : ''; ?>">
            <div class="admin-card" style="margin-bottom:20px;">
                <h3 style="font-size:15px; font-weight:700; margin-bottom:12px;">Assistant Configuration</h3>
                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="save_settings">
                    <div class="admin-form-group" style="margin-bottom:12px;">
                        <label>
                            <input type="checkbox" name="chatbot_enabled" value="1" <?php echo $chatbotEnabled === '1' ? 'checked' : ''; ?>>
                            Enable Guest AI Concierge Widget on Public Pages
                        </label>
                    </div>
                    <div class="admin-form-group" style="margin-bottom:12px;">
                        <label>Daily Global Message Cap</label>
                        <input type="number" name="chatbot_daily_limit" min="50" max="10000" value="<?php echo htmlspecialchars($chatbotDailyLimit); ?>">
                    </div>
                    <div class="admin-form-group" style="margin-bottom:12px;">
                        <label>Welcome Message</label>
                        <textarea name="chatbot_welcome_message" rows="2" style="width:100%; padding:10px; border:1px solid var(--border, #CBD5E1); border-radius:8px;"><?php echo htmlspecialchars($chatbotWelcome); ?></textarea>
                    </div>
                    <button type="submit" class="btn-primary">Save Settings</button>
                </form>
            </div>

            <div class="admin-card">
                <h3 style="font-size:15px; font-weight:700; margin-bottom:8px;">Data Privacy Act (RA 10173) — 90-Day Log Retention</h3>
                <p style="font-size:13px; color:var(--text-muted); margin-bottom:12px;">
                    All guest emails and mobile numbers in chat messages are automatically masked prior to storage. Conversations older than 90 days can be purged below or via <code>php backend/cron/purge_chatbot_logs.php</code>.
                </p>
                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="purge_old_logs">
                    <button type="submit" class="btn-danger">Purge Logs Older Than 90 Days</button>
                </form>
            </div>
        </section>
    </div>
</main>

<script>
function switchAiTab(tabId, btnEl) {
    document.querySelectorAll('.ai-panel').forEach(function(p) { p.classList.remove('active'); });
    document.querySelectorAll('.ai-tab-btn').forEach(function(b) { b.classList.remove('active'); });
    var target = document.getElementById('ai-tab-' + tabId);
    if (target) target.classList.add('active');
    if (btnEl) {
        btnEl.classList.add('active');
    } else {
        var autoBtn = document.getElementById('ai-tab-btn-' + tabId);
        if (autoBtn) autoBtn.classList.add('active');
    }
}

function populateKbEdit(entry) {
    if (!entry) return;
    switchAiTab('knowledge');
    document.getElementById('kbFormId').value = entry.id || 0;
    var catSelect = document.getElementById('kbFormCategory');
    if (catSelect && entry.category) {
        catSelect.value = entry.category;
    }
    var langSelect = document.getElementById('kbFormLanguage');
    if (langSelect && entry.language) {
        langSelect.value = entry.language;
    }
    document.getElementById('kbFormQuestion').value = entry.question || '';
    document.getElementById('kbFormAnswer').value = entry.answer || '';
    document.getElementById('kbFormHeading').textContent = 'Edit Multilingual FAQ Entry #' + entry.id;
    document.getElementById('kbSubmitBtn').textContent = 'Update FAQ Entry #' + entry.id;
    document.getElementById('kbCancelEditBtn').style.display = 'inline-block';
    var card = document.getElementById('kbEditorCard');
    if (card && card.scrollIntoView) {
        card.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function resetKbForm() {
    document.getElementById('kbFormId').value = '0';
    document.getElementById('kbFormQuestion').value = '';
    document.getElementById('kbFormAnswer').value = '';
    document.getElementById('kbFormHeading').textContent = 'Add New Multilingual FAQ Entry';
    document.getElementById('kbSubmitBtn').textContent = 'Save FAQ Entry';
    document.getElementById('kbCancelEditBtn').style.display = 'none';
}

function prefillKbFromFlagged(questionText, lang) {
    switchAiTab('knowledge');
    resetKbForm();
    if (lang) {
        var langSelect = document.getElementById('kbFormLanguage');
        if (langSelect) {
            for (var i = 0; i < langSelect.options.length; i++) {
                if (langSelect.options[i].value.toLowerCase() === String(lang).toLowerCase()) {
                    langSelect.selectedIndex = i;
                    break;
                }
            }
        }
    }
    var qInput = document.getElementById('kbFormQuestion');
    if (qInput) {
        qInput.value = questionText || '';
        qInput.focus();
    }
    var card = document.getElementById('kbEditorCard');
    if (card && card.scrollIntoView) {
        card.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function jumpToConversationCard(convId) {
    switchAiTab('conversations');
    var target = document.getElementById('conv-card-' + convId);
    if (target) {
        target.style.display = '';
        if (target.scrollIntoView) {
            target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        var oldOutline = target.style.outline;
        target.style.outline = '3px solid #2A9D8F';
        setTimeout(function() {
            target.style.outline = oldOutline;
        }, 2400);
    } else {
        window.location.href = 'admin_chatbot.php?tab=conversations&conv_status=all#conv-card-' + convId;
    }
}

function filterKbTable() {
    var q = (document.getElementById('kbSearchFilter').value || '').toLowerCase().trim();
    var cat = document.getElementById('kbCategoryFilter').value || 'all';
    var lang = document.getElementById('kbLanguageFilter').value || 'all';
    document.querySelectorAll('#kbEntriesTable tbody .kb-row').forEach(function(row) {
        var rCat = row.getAttribute('data-kb-category') || '';
        var rLang = row.getAttribute('data-kb-language') || '';
        var rSearch = row.getAttribute('data-kb-search') || '';
        var matchCat = (cat === 'all' || rCat === cat);
        var matchLang = (lang === 'all' || rLang === lang);
        var matchQ = (q === '' || rSearch.indexOf(q) !== -1);
        row.style.display = (matchCat && matchLang && matchQ) ? '' : 'none';
    });
}

function filterConversationsClient(query) {
    var q = (query || '').toLowerCase().trim();
    document.querySelectorAll('#ai-tab-conversations .conv-item').forEach(function(item) {
        var hay = item.getAttribute('data-conv-search') || '';
        item.style.display = (q === '' || hay.indexOf(q) !== -1) ? '' : 'none';
    });
}
</script>
<?php sf_reply_ui_render_script(get_csrf_token(), ['support_tab_selector' => '#ai-tab-support', 'badge_style' => 'chatbot']); ?>
</body>
</html>
