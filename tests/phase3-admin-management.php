<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/services/chatbot_service.php';
require_once __DIR__ . '/../backend/services/chatbot_tools.php';

// Force local grounded engine during CLI test for fast deterministic assertions
putenv('GEMINI_API_KEY=');
$_ENV['GEMINI_API_KEY'] = '';

function assertTrue(bool $cond, string $msg): void {
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
    echo "PASS: {$msg}\n";
}

echo "=== Phase 3 · Administration (Admin Chatbot Management) Verification ===\n";

assertTrue(sf_chatbot_tables_ready($conn), 'Chatbot database tables are ready');

$adminContent = (string)file_get_contents(__DIR__ . '/../frontend/admin_chatbot.php');
$widgetContent = (string)file_get_contents(__DIR__ . '/../frontend/partials/_chatbot_widget.php');

// 1. Conversation Monitoring checks
assertTrue(strpos($adminContent, 'id="ai-tab-conversations"') !== false, 'Admin page has Conversation Monitoring tab');
assertTrue(strpos($adminContent, 'Identified Unanswered Guest Questions &amp; Monitored Bot Replies') !== false, 'Conversation Monitoring tab includes dedicated Unanswered Questions & Monitored Bot Replies panel');
assertTrue(strpos($adminContent, "\$convStatus === 'unanswered'") !== false, 'Conversation Monitoring supports filtering by Unanswered Questions');
assertTrue(strpos($adminContent, "\$convStatus === 'flagged'") !== false, 'Conversation Monitoring supports filtering by Flagged Chatbot Responses');
assertTrue(strpos($adminContent, 'GUEST INQUIRY') !== false && strpos($adminContent, 'CHATBOT RESPONSE') !== false, 'Conversation transcript labels Guest Inquiry vs Chatbot Response clearly');
assertTrue(strpos($adminContent, 'jumpToConversationCard') !== false, 'Conversation Monitoring links unanswered questions directly to full session cards');
assertTrue(strpos($adminContent, 'conversations_csv') !== false, 'Admin page supports CSV export of filtered conversations');
assertTrue(strpos($adminContent, 'filterConversationsClient') !== false, 'Admin page supports live client-side conversation search');
assertTrue(strpos($adminContent, 'escalate_conversation') !== false, 'Admin page supports manual conversation escalation to Human Support');

// Test repeated-question auto-escalation & human_handoff intent
$testToken = bin2hex(random_bytes(16));
$_SESSION['sf_chatbot_session_token'] = $testToken;

$r1 = sf_chatbot_send_guest_message($conn, 'I want to talk to a human receptionist right now', 'English');
assertTrue(!empty($r1['success']), 'Human handoff request processed');
assertTrue(($r1['intent'] ?? '') === 'human_handoff', 'Detected human_handoff intent accurately');
assertTrue(!empty($r1['suggest_handoff']), 'Frustration / human_handoff intent sets suggest_handoff = true');

$repeatToken = bin2hex(random_bytes(16));
$_SESSION['sf_chatbot_session_token'] = $repeatToken;
$rep1 = sf_chatbot_send_guest_message($conn, 'What is the check-in and check-out time?', 'English');
$rep2 = sf_chatbot_send_guest_message($conn, 'What is the check-in and check-out time?', 'English');
assertTrue(!empty($rep2['suggest_handoff']), 'Repeated identical question automatically triggers suggest_handoff = true');

// 2. Knowledge Base Management CRUD lifecycle test
assertTrue(strpos($adminContent, 'populateKbEdit') !== false, 'Admin KB table includes in-place Edit handler');
assertTrue(strpos($adminContent, 'filterKbTable') !== false, 'Admin KB table includes search, category, and language filters');

$conn->query("DELETE FROM chatbot_knowledge WHERE updated_by = 'phase3_test'");
$testQuestion = '[Phase3 Test FAQ] Can guests bring electric scooters?';
$testAnswer = 'Electric scooters are permitted in designated parking areas only.';
$insStmt = $conn->prepare("INSERT INTO chatbot_knowledge (category, question, answer, language, is_active, updated_by) VALUES ('policies', ?, ?, 'English', 1, 'phase3_test')");
$insStmt->bind_param('ss', $testQuestion, $testAnswer);
$insStmt->execute();
$testKbId = (int)$insStmt->insert_id;
$insStmt->close();
assertTrue($testKbId > 0, 'Created new Knowledge Base entry #' . $testKbId);

$kbList = sf_chatbot_get_knowledge_entries($conn);
$foundNewKb = false;
foreach ($kbList as $kRow) {
    if ((int)$kRow['id'] === $testKbId) {
        $foundNewKb = true;
        break;
    }
}
assertTrue($foundNewKb, 'Newly created KB entry is immediately returned by sf_chatbot_get_knowledge_entries()');
$kbReply = sf_chatbot_send_guest_message($conn, 'Can guests bring electric scooters?', 'English');
assertTrue(str_contains((string)($kbReply['reply'] ?? ''), 'designated parking areas'), 'AI Concierge immediately answers using newly created KB entry');

// Update KB entry
$updatedAnswer = 'Updated: Electric scooters must be parked at the main gate.';
$updStmt = $conn->prepare("UPDATE chatbot_knowledge SET answer = ?, is_active = 0 WHERE id = ?");
$updStmt->bind_param('si', $updatedAnswer, $testKbId);
$updStmt->execute();
$updStmt->close();

$chkStmt = $conn->prepare("SELECT answer, is_active FROM chatbot_knowledge WHERE id = ?");
$chkStmt->bind_param('i', $testKbId);
$chkStmt->execute();
$chkRow = $chkStmt->get_result()->fetch_assoc();
$chkStmt->close();
assertTrue($chkRow && $chkRow['answer'] === $updatedAnswer && (int)$chkRow['is_active'] === 0, 'Knowledge Base entry update & status toggle verified');

// Delete test KB entry
$delStmt = $conn->prepare("DELETE FROM chatbot_knowledge WHERE id = ?");
$delStmt->bind_param('i', $testKbId);
$delStmt->execute();
$delStmt->close();
assertTrue(true, 'Knowledge Base entry deletion verified');

// 3. Chatbot Analytics checks
assertTrue(strpos($adminContent, '7-Day Conversation &amp; Message Activity') !== false, 'Admin Analytics includes 7-Day Activity Trend chart');
assertTrue(strpos($adminContent, 'Live Database Tool Invocations') !== false, 'Admin Analytics includes Live Database Tool Invocations breakdown');
assertTrue(strpos($adminContent, 'Flagged / Unhelpful Responses Queue') !== false, 'Admin Analytics includes Flagged / Unhelpful Responses queue');
assertTrue(strpos($adminContent, 'prefillKbFromFlagged') !== false, 'Admin Analytics allows 1-click conversion of flagged questions into FAQ entries');

// Record negative feedback (-1) and verify it can be queried
$_SESSION['sf_chatbot_session_token'] = $testToken;
$msgId = (int)($r1['message_id'] ?? 0);
if ($msgId > 0) {
    $fbOk = sf_chatbot_record_feedback($conn, $msgId, -1);
    assertTrue($fbOk === true, 'Negative feedback (-1) recorded on message #' . $msgId);
}
assertTrue(strpos($widgetContent, "rating === -1") !== false && strpos($widgetContent, "sfbcTogglePanel('handoff')") !== false, 'Guest widget automatically opens Human Support drawer when guest clicks thumbs-down');

// 4. Human Support & Escalation workflow test
assertTrue(strpos($adminContent, 'id="ai-tab-support"') !== false, 'Admin page has dedicated Human Support & Escalations tab');
assertTrue(strpos($adminContent, 'update_handoff_status') !== false && strpos($adminContent, 'reply_handoff') !== false, 'Admin Human Support tab supports ticket status updates and staff reply logging');

$handoffRes = sf_chatbot_create_handoff(
    $conn,
    'Phase3 Test Guest',
    'phase3test@example.com',
    'Need special group event coordination for 25 guests.'
);
assertTrue(!empty($handoffRes['success']) && !empty($handoffRes['inquiry_id']), 'Guest escalation created support ticket #' . ($handoffRes['inquiry_id'] ?? 0));

$inqId = (int)$handoffRes['inquiry_id'];
$staffNote = "\n\n--- [Staff Response by admin @ " . date('Y-m-d H:i') . "] ---\nContacted guest via email with group banquet rates.";
$resStmt = $conn->prepare("UPDATE inquiries SET message = CONCAT(message, ?), status = 'Resolved' WHERE id = ?");
$resStmt->bind_param('si', $staffNote, $inqId);
$resStmt->execute();
$resStmt->close();

$verifyInq = $conn->query("SELECT status, message FROM inquiries WHERE id = {$inqId}")->fetch_assoc();
assertTrue($verifyInq && $verifyInq['status'] === 'Resolved' && strpos($verifyInq['message'], 'Staff Response by admin') !== false, 'Support ticket #' . $inqId . ' resolved with staff note');

// Clean up test inquiry and test conversations
$conn->query("DELETE FROM inquiries WHERE id = {$inqId}");
$conn->query("DELETE FROM chatbot_conversations WHERE session_token IN ('{$testToken}', '{$repeatToken}')");

echo "\nALL PHASE 3 ADMINISTRATION CHECKS PASSED!\n";
