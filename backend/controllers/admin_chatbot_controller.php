<?php
/**
 * Controller for frontend/admin_chatbot.php.
 */

function sfbc_handle_admin_chatbot_page(mysqli $conn, string $admin): array
{
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

    $handoffThreads = !empty($handoffQueue)
        ? sf_inquiry_get_threads($conn, array_map(static fn($h) => (int)$h['inquiry_id'], $handoffQueue))
        : [];

    return compact(
        'flashSuccess',
        'flashError',
        'activeTab',
        'tablesReady',
        'convSearch',
        'convLang',
        'convStatus',
        'convDays',
        'allowedCategories',
        'supportedLanguages',
        'chatbotEnabled',
        'chatbotDailyLimit',
        'chatbotWelcome',
        'geminiKeyConfigured',
        'geminiModelName',
        'metrics',
        'intentStats',
        'langStats',
        'toolStats',
        'dailyTrend',
        'flaggedMessages',
        'unansweredMessages',
        'recentConversations',
        'kbRows',
        'handoffQueue',
        'handoffThreads'
    );
}
