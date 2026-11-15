<?php
/**
 * _inquiry_reply_assets.php — Shared Staff-to-Guest Two-Way Messaging UI Partial
 *
 * Used by:
 *  - frontend/admin_live_support.php ("Need a human?" Split-Pane Live Chat Desk)
 *  - frontend/admin_inquiries.php (Contact Us Web Inquiries)
 *  - frontend/admin_chatbot.php (AI Concierge Human Support tab)
 */

if (!function_exists('sf_reply_ui_has_real_email')) {
    function sf_reply_ui_has_real_email(?string $email): bool
    {
        if (function_exists('sf_mailer_is_deliverable_email')) {
            return sf_mailer_is_deliverable_email($email);
        }
        $clean = strtolower(trim((string)$email));
        if ($clean === '' || !filter_var($clean, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $placeholders = [
            'pending-contact@santafebeachclub.com',
            '.example',
            '@example.com',
            '@example.org',
            '@example.net',
            'not-provided',
            'guest-widget',
            'no-reply@',
            'noreply@',
        ];
        foreach ($placeholders as $token) {
            if (strpos($clean, $token) !== false) {
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('sf_reply_ui_format_time')) {
    function sf_reply_ui_format_time(?string $ts, bool $short = false): string
    {
        $raw = trim((string)$ts);
        if ($raw === '') {
            return '';
        }
        $parsed = strtotime($raw);
        if ($parsed === false) {
            return $raw;
        }
        if ($short) {
            return (date('Y-m-d', $parsed) === date('Y-m-d'))
                ? date('g:i A', $parsed)
                : date('M j, g:i A', $parsed);
        }
        return date('M j, Y g:i A', $parsed);
    }
}

if (!function_exists('sf_reply_ui_format_sender')) {
    function sf_reply_ui_format_sender(?string $senderName, string $senderType): array
    {
        $raw = trim((string)$senderName);
        if ($senderType === 'guest') {
            $clean = preg_replace('/\s*\(Live Follow-Up\)\s*$/i', '', $raw);
            $clean = trim((string)$clean);
            return [
                'label' => $clean !== '' ? $clean : 'Guest',
                'tooltip' => $raw !== '' ? $raw : 'Guest',
            ];
        }

        if ($raw === '') {
            return ['label' => 'Reception Staff', 'tooltip' => 'Reception Staff'];
        }

        $role = '';
        $identity = $raw;
        if (preg_match('/^(.+?)\s*\(([^)]+)\)\s*$/', $raw, $m)) {
            $identity = trim($m[1]);
            $role = trim($m[2]);
        }

        if (strpos($identity, '@') !== false) {
            $local = strstr($identity, '@', true);
            $local = trim(str_replace(['.', '_', '-'], ' ', (string)$local));
            $prettyName = $local !== '' ? ucwords(strtolower($local)) : $identity;
        } else {
            $prettyName = $identity;
        }

        if ($role !== '' && strcasecmp($prettyName, $role) === 0) {
            $label = $prettyName;
        } elseif ($role !== '') {
            $label = $prettyName . ' (' . $role . ')';
        } else {
            $label = $prettyName;
        }

        return [
            'label' => $label,
            'tooltip' => $raw,
        ];
    }
}

if (!function_exists('sf_reply_ui_initial')) {
    function sf_reply_ui_initial(?string $name): string
    {
        $clean = trim((string)$name);
        if ($clean === '') {
            return 'G';
        }
        if (function_exists('mb_substr') && function_exists('mb_strtoupper')) {
            return mb_strtoupper(mb_substr($clean, 0, 1, 'UTF-8'), 'UTF-8');
        }
        return strtoupper(substr($clean, 0, 1));
    }
}

if (!function_exists('sf_reply_ui_parse_handoff_context')) {
    function sf_reply_ui_parse_handoff_context(string $rawMessage): array
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", trim($rawMessage));
        $note = '';
        $transcriptRaw = $normalized;

        if (strpos($normalized, '--- Chat Transcript ---') !== false) {
            $parts = explode('--- Chat Transcript ---', $normalized, 2);
            $head = trim($parts[0]);
            $transcriptRaw = trim($parts[1] ?? '');
            if (stripos($head, 'Guest Note:') === 0) {
                $note = trim(substr($head, strlen('Guest Note:')));
            } elseif ($head !== '') {
                $note = $head;
            }
        }

        $note = trim((string)preg_replace('/^\[Staff Handoff Request\]\s*/i', '', $note));

        $turns = [];
        foreach (explode("\n", $transcriptRaw) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $lineClean = trim((string)preg_replace('/\[Staff Handoff Request\]\s*/i', '', $line));
            if ($lineClean === '') {
                continue;
            }
            if (preg_match('/^(Guest|AI|Assistant|Staff|User)\s*:\s*(.+)$/i', $lineClean, $m)) {
                $speakerRaw = ucfirst(strtolower($m[1]));
                $text = trim($m[2]);
                if ($speakerRaw === 'User') {
                    $speakerRaw = 'Guest';
                } elseif ($speakerRaw === 'Assistant') {
                    $speakerRaw = 'AI';
                }
                if ($text !== '') {
                    $turns[] = ['speaker' => $speakerRaw, 'text' => $text];
                }
            } else {
                $turns[] = ['speaker' => 'Context', 'text' => $lineClean];
            }
        }

        $preview = $note;
        if ($preview === '' && !empty($turns)) {
            $preview = (string)$turns[count($turns) - 1]['text'];
        }
        if ($preview === '') {
            $preview = preg_replace('/\s+/', ' ', $normalized);
        }

        return [
            'note' => $note,
            'turns' => $turns,
            'preview' => trim((string)$preview),
        ];
    }
}

if (!function_exists('sf_reply_ui_truncate')) {
    function sf_reply_ui_truncate(string $text, int $limit = 64): string
    {
        $single = trim((string)preg_replace('/\s+/', ' ', $text));
        if ($single === '') {
            return '';
        }
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($single, 'UTF-8') > $limit
                ? mb_substr($single, 0, $limit - 1, 'UTF-8') . '...'
                : $single;
        }
        return strlen($single) > $limit ? substr($single, 0, $limit - 3) . '...' : $single;
    }
}

if (!function_exists('sf_reply_ui_render_styles')) {
    function sf_reply_ui_render_styles(): void
    {
        $cssPath = __DIR__ . '/../assets/css/components/inquiry-reply.css';
        $cssVer = (int) @filemtime($cssPath);
        ?>
        <link rel="stylesheet" href="assets/css/components/inquiry-reply.css?v=<?php echo $cssVer; ?>">
        <?php
    }
}

if (!function_exists('sf_reply_ui_render_panel')) {
    function sf_reply_ui_render_panel(int $inquiryId, ?string $guestEmail, string $status, array $thread, array $options = []): void
    {
        $convId = (int)($thread['conversation_id'] ?? 0);
        $hasChat = $convId > 0;
        $liveActive = !empty($thread['live_chat_active']);
        $replies = is_array($thread['replies'] ?? null) ? $thread['replies'] : [];
        $hasRealEmail = sf_reply_ui_has_real_email($guestEmail);
        $compactMode = !empty($options['compact']);
        $hideStatusBar = !empty($options['hide_status_bar']);
        ?>
        <div class="sf-reply-box <?php echo $compactMode ? 'is-compact' : ''; ?>"
             data-reply-box
             data-inquiry-id="<?php echo $inquiryId; ?>"
             data-conversation-id="<?php echo $convId; ?>"
             data-live-active="<?php echo $liveActive ? '1' : '0'; ?>"
             data-has-chat="<?php echo $hasChat ? '1' : '0'; ?>"
             data-has-email="<?php echo $hasRealEmail ? '1' : '0'; ?>">

            <?php if (!$hideStatusBar): ?>
                <div class="sf-reply-status-bar">
                    <?php if ($hasChat): ?>
                        <span class="sf-live-pill <?php echo $liveActive ? 'is-active' : 'is-idle'; ?>" data-live-status-pill>
                            <?php if ($liveActive): ?>
                                <span class="sf-live-dot"></span>
                                Active Two-Way Chat &middot; Session #<?php echo $convId; ?>
                            <?php else: ?>
                                Chat Session #<?php echo $convId; ?> &middot; <?php echo ($status === 'Resolved') ? 'Resolved' : 'AI Mode'; ?>
                            <?php endif; ?>
                        </span>
                    <?php else: ?>
                        <span class="sf-live-pill is-email-only" data-live-status-pill>
                            Direct Email Reply Channel
                        </span>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <span class="sf-live-pill" data-live-status-pill hidden></span>
            <?php endif; ?>

            <div class="sf-thread-wrap" data-thread-list>
                <?php if (empty($replies)): ?>
                    <div class="sf-thread-empty" data-thread-empty>
                        <?php echo $hasChat
                            ? 'No replies sent yet. Write a response below to message this guest directly in Live Chat.'
                            : 'No replies sent yet. Use the composer below to send a direct email reply.'; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($replies as $rep):
                        $senderType = (($rep['sender_type'] ?? 'staff') === 'staff') ? 'staff' : 'guest';
                        $isStaff = ($senderType === 'staff');
                        $repId = (int)($rep['id'] ?? 0);
                        $fmtSender = sf_reply_ui_format_sender((string)($rep['sender_name'] ?? ''), $senderType);
                        $deliveryParts = [];
                        if ($isStaff && !empty($rep['delivered_live'])) {
                            $deliveryParts[] = 'Live Chat';
                        }
                        if ($isStaff && !empty($rep['delivered_email'])) {
                            $deliveryParts[] = 'Email Sent';
                        }
                        $emailError = trim((string)($rep['email_error'] ?? ''));
                        $timeStr = sf_reply_ui_format_time((string)($rep['created_at'] ?? ''), true);
                    ?>
                        <div class="sf-thread-item <?php echo $isStaff ? 'is-staff' : 'is-guest'; ?>" data-reply-id="<?php echo $repId; ?>">
                            <div class="sf-bubble"><?php echo htmlspecialchars((string)($rep['message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="sf-bubble-meta">
                                <span class="sf-bubble-sender" title="<?php echo htmlspecialchars($fmtSender['tooltip'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars($fmtSender['label'], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                                <?php if (!empty($deliveryParts)): ?>
                                    <span>&middot;</span>
                                    <span class="sf-delivery-note">
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                        <?php echo htmlspecialchars(implode(' & ', $deliveryParts), ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($isStaff && $emailError !== ''): ?>
                                    <span class="sf-delivery-note is-warn" title="<?php echo htmlspecialchars($emailError, ENT_QUOTES, 'UTF-8'); ?>">&middot; Email notice</span>
                                <?php endif; ?>
                                <?php if ($timeStr !== ''): ?>
                                    <span>&middot; <?php echo htmlspecialchars($timeStr, ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="sf-reply-composer">
                <textarea class="sf-reply-textarea"
                          data-reply-input
                          rows="2"
                          maxlength="2000"
                          placeholder="<?php echo $hasChat
                              ? 'Reply to guest (Enter to send, Shift+Enter for new line)...'
                              : 'Write your reply to send directly via SMTP email...'; ?>"></textarea>

                <div class="sf-reply-controls">
                    <div class="sf-reply-checks">
                        <?php if ($hasChat): ?>
                            <label class="sf-reply-check">
                                <input type="checkbox" data-send-live checked>
                                <span>Live Chat</span>
                            </label>
                        <?php endif; ?>

                        <?php if ($hasRealEmail): ?>
                            <label class="sf-reply-check">
                                <input type="checkbox" data-send-email checked>
                                <span>Email Copy</span>
                            </label>
                        <?php elseif ($hasChat): ?>
                            <span class="sf-reply-muted-note">No email on file (Live Chat only)</span>
                        <?php else: ?>
                            <span class="sf-reply-muted-note">No deliverable email address on file</span>
                        <?php endif; ?>
                    </div>

                    <div class="sf-reply-buttons">
                        <button type="button" class="sf-btn-send" data-send-reply data-resolve="0">Send Reply</button>
                        <button type="button" class="sf-btn-send-resolve" data-send-reply data-resolve="1">Send &amp; Resolve</button>
                    </div>
                </div>
                <div class="sf-reply-feedback" data-reply-feedback hidden></div>
            </div>
        </div>
        <?php
    }
}

if (!function_exists('sf_reply_ui_render_ticket_cards')) {
    function sf_reply_ui_render_ticket_cards(array $tickets, array $threads, array $options = []): void
    {
        $channel = (string)($options['channel'] ?? 'contact');
        $isLiveChatChannel = ($channel === 'live_chat');
        $emptyText = (string)($options['empty_text'] ?? ($isLiveChatChannel
            ? 'No live support chats requested via "Need a human?" yet.'
            : 'No Contact Us page inquiries found.'));

        $openCount = 0;
        $resolvedCount = 0;
        foreach ($tickets as $t) {
            if (($t['status'] ?? '') === 'Resolved') {
                $resolvedCount++;
            } else {
                $openCount++;
            }
        }

        if ($isLiveChatChannel):
            ?>
            <div class="sf-comm-page">
                <?php if (empty($tickets)): ?>
                    <div style="text-align: center; padding: 64px 20px; color: #64748B; background: var(--card-bg, #FFFFFF); border: 1px solid #E5E0D8; border-radius: 16px;">
                        <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 10px; opacity: 0.5;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                        <p style="margin:0; font-size: 14px;"><?php echo htmlspecialchars($emptyText, ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                <?php else: ?>
                    <?php
                    // Default active chat: first open/unread chat, or first chat overall
                    $defaultSelectedId = (int)$tickets[0]['id'];
                    foreach ($tickets as $cand) {
                        if (($cand['status'] ?? '') !== 'Resolved') {
                            $defaultSelectedId = (int)$cand['id'];
                            break;
                        }
                    }
                    ?>
                    <div class="sf-desk-shell" data-chat-desk>
                        <!-- Left Conversation Queue -->
                        <aside class="sf-desk-sidebar">
                            <div class="sf-desk-sidebar-top">
                                <div class="sf-desk-search-wrap">
                                    <svg class="sf-desk-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                                    <input type="search" class="sf-desk-search" placeholder="Search guest or message..." data-chat-search>
                                </div>
                                <div class="sf-desk-queue-pills sf-filter-bar" style="margin-bottom: 0;">
                                    <button type="button" class="sf-filter-btn is-active" data-ticket-filter="all">All (<?php echo count($tickets); ?>)</button>
                                    <button type="button" class="sf-filter-btn" data-ticket-filter="open">Active (<?php echo $openCount; ?>)</button>
                                    <button type="button" class="sf-filter-btn" data-ticket-filter="resolved">Done (<?php echo $resolvedCount; ?>)</button>
                                </div>
                            </div>

                            <div class="sf-desk-list" data-chat-queue>
                                <?php foreach ($tickets as $inq):
                                    $inqId = (int)$inq['id'];
                                    $statusStr = (string)($inq['status'] ?? 'Unread');
                                    $lowerStatus = strtolower($statusStr);
                                    $thread = $threads[$inqId] ?? [];
                                    $liveActive = !empty($thread['live_chat_active']);
                                    $replies = is_array($thread['replies'] ?? null) ? $thread['replies'] : [];
                                    $ctx = sf_reply_ui_parse_handoff_context((string)($inq['message'] ?? ''));

                                    if (!empty($replies)) {
                                        $lastRep = $replies[count($replies) - 1];
                                        $lastPrefix = (($lastRep['sender_type'] ?? 'staff') === 'staff') ? 'Staff: ' : '';
                                        $snippet = sf_reply_ui_truncate($lastPrefix . (string)($lastRep['message'] ?? ''), 56);
                                        $lastTime = sf_reply_ui_format_time((string)($lastRep['created_at'] ?? ''), true);
                                    } else {
                                        $snippet = sf_reply_ui_truncate($ctx['preview'], 56);
                                        $lastTime = sf_reply_ui_format_time((string)($inq['created_at'] ?? ''), true);
                                    }

                                    $isSelected = ($inqId === $defaultSelectedId);
                                    $rowStateLabel = $statusStr === 'Read' ? 'Open' : $statusStr;
                                ?>
                                    <button type="button"
                                            class="sf-chat-row is-<?php echo htmlspecialchars($lowerStatus, ENT_QUOTES, 'UTF-8'); ?> <?php echo $isSelected ? 'is-selected' : ''; ?>"
                                            data-chat-row
                                            data-select-chat="<?php echo $inqId; ?>"
                                            data-search-haystack="<?php echo htmlspecialchars(strtolower((string)$inq['guest_name'] . ' ' . (string)($inq['guest_email'] ?? '') . ' ' . $snippet), ENT_QUOTES, 'UTF-8'); ?>">
                                        <span class="sf-chat-avatar">
                                            <?php echo htmlspecialchars(sf_reply_ui_initial((string)$inq['guest_name']), ENT_QUOTES, 'UTF-8'); ?>
                                            <?php if ($liveActive && $statusStr !== 'Resolved'): ?>
                                                <span class="sf-chat-avatar-dot is-live" data-row-dot></span>
                                            <?php elseif ($statusStr === 'Unread'): ?>
                                                <span class="sf-chat-avatar-dot is-unread" data-row-dot></span>
                                            <?php else: ?>
                                                <span class="sf-chat-avatar-dot" data-row-dot hidden></span>
                                            <?php endif; ?>
                                        </span>
                                        <span class="sf-chat-row-body">
                                            <span class="sf-chat-row-top">
                                                <span class="sf-chat-row-name"><?php echo htmlspecialchars((string)$inq['guest_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                <span class="sf-chat-row-time" data-row-time><?php echo htmlspecialchars($lastTime, ENT_QUOTES, 'UTF-8'); ?></span>
                                            </span>
                                            <span class="sf-chat-row-snippet" data-row-snippet><?php echo htmlspecialchars($snippet !== '' ? $snippet : 'Live support request', ENT_QUOTES, 'UTF-8'); ?></span>
                                            <span class="sf-chat-row-meta">
                                                <span class="sf-row-state-tag <?php echo htmlspecialchars($lowerStatus, ENT_QUOTES, 'UTF-8'); ?>" data-row-status><?php echo htmlspecialchars($rowStateLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                            </span>
                                        </span>
                                    </button>
                                <?php endforeach; ?>
                                <div class="sf-desk-list-empty" data-queue-empty hidden>No conversations match this filter.</div>
                            </div>
                        </aside>

                        <!-- Right Active Conversation Pane -->
                        <section class="sf-desk-main">
                            <?php foreach ($tickets as $inq):
                                $inqId = (int)$inq['id'];
                                $statusStr = (string)($inq['status'] ?? 'Unread');
                                $cardClass = strtolower($statusStr);
                                if (!in_array($cardClass, ['unread', 'read', 'resolved'], true)) {
                                    $cardClass = 'read';
                                }
                                $badgeClass = 'status-' . $cardClass;
                                $hasRealEmail = sf_reply_ui_has_real_email($inq['guest_email'] ?? '');
                                $thread = $threads[$inqId] ?? [];
                                $convId = (int)($thread['conversation_id'] ?? ($inq['conversation_id'] ?? 0));
                                $liveActive = !empty($thread['live_chat_active']);
                                $langLabel = trim((string)($thread['language'] ?? ($inq['language'] ?? '')));
                                $replies = is_array($thread['replies'] ?? null) ? $thread['replies'] : [];
                                $ctx = sf_reply_ui_parse_handoff_context((string)($inq['message'] ?? ''));
                                $isSelected = ($inqId === $defaultSelectedId);
                                $expandContext = empty($replies);
                            ?>
                                <article id="inquiry-<?php echo $inqId; ?>"
                                         class="sf-conv-pane <?php echo htmlspecialchars($cardClass, ENT_QUOTES, 'UTF-8'); ?> <?php echo $isSelected ? 'is-selected' : ''; ?>"
                                         data-inquiry-card
                                         data-inquiry-id="<?php echo $inqId; ?>">

                                    <header class="sf-conv-head">
                                        <div class="sf-conv-guest">
                                            <button type="button" class="sf-mobile-back" data-mobile-back>
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
                                                Chats
                                            </button>
                                            <span class="sf-chat-avatar" style="width:38px;height:38px;">
                                                <?php echo htmlspecialchars(sf_reply_ui_initial((string)$inq['guest_name']), ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                            <div class="sf-conv-title-wrap">
                                                <div class="sf-conv-name-row">
                                                    <h3 class="sf-conv-name"><?php echo htmlspecialchars((string)$inq['guest_name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                                    <span class="status-badge <?php echo htmlspecialchars($badgeClass, ENT_QUOTES, 'UTF-8'); ?>" data-status-badge><?php echo htmlspecialchars($statusStr, ENT_QUOTES, 'UTF-8'); ?></span>
                                                    <?php if ($langLabel !== ''): ?>
                                                        <span class="sf-lang-pill"><?php echo htmlspecialchars($langLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="sf-conv-sub">
                                                    <?php if ($hasRealEmail): ?>
                                                        <a href="mailto:<?php echo htmlspecialchars((string)$inq['guest_email'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string)$inq['guest_email'], ENT_QUOTES, 'UTF-8'); ?></a>
                                                    <?php else: ?>
                                                        <span>Widget Session</span>
                                                    <?php endif; ?>
                                                    <?php if ($convId > 0): ?>
                                                        <span>&middot;</span>
                                                        <span>Session #<?php echo $convId; ?></span>
                                                    <?php endif; ?>
                                                    <?php if ($liveActive && $statusStr !== 'Resolved'): ?>
                                                        <span>&middot;</span>
                                                        <span style="color:#047857;font-weight:600;">Active Live Chat</span>
                                                    <?php endif; ?>
                                                    <span>&middot; <?php echo htmlspecialchars(sf_reply_ui_format_time((string)$inq['created_at']), ENT_QUOTES, 'UTF-8'); ?></span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="sf-conv-actions">
                                            <form method="POST">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="inquiry_id" value="<?php echo $inqId; ?>">
                                                <?php if ($statusStr === 'Unread'): ?>
                                                    <button type="submit" name="action" value="mark_read" class="sf-ghost-btn is-read">Mark Read</button>
                                                <?php endif; ?>
                                                <?php if ($statusStr !== 'Resolved'): ?>
                                                    <button type="submit" name="action" value="mark_resolved" class="sf-ghost-btn is-resolve">Resolve</button>
                                                <?php else: ?>
                                                    <button type="submit" name="action" value="reopen" class="sf-ghost-btn">Re-open</button>
                                                <?php endif; ?>
                                                <?php if ($hasRealEmail): ?>
                                                    <a href="mailto:<?php echo htmlspecialchars((string)$inq['guest_email'], ENT_QUOTES, 'UTF-8'); ?>?subject=Re: <?php echo urlencode((string)$inq['subject']); ?>" class="sf-ghost-btn">Mail</a>
                                                <?php endif; ?>
                                                <button type="button" class="sf-ghost-btn is-danger" onclick="showConfirm({ title: 'Delete Conversation', message: 'Are you sure you want to delete this live support record? This cannot be undone.', icon: 'trash', iconBg: '#FEE2E2', confirmText: 'Delete', onConfirm: () => this.form.requestSubmit(this.form.querySelector('[value=delete]')) })">Delete</button>
                                                <button type="submit" name="action" value="delete" hidden>Confirm Delete</button>
                                            </form>
                                        </div>
                                    </header>

                                    <?php if ($ctx['note'] !== '' || !empty($ctx['turns'])): ?>
                                        <details class="sf-context-accordion" <?php echo $expandContext ? 'open' : ''; ?>>
                                            <summary class="sf-context-summary">
                                                <span class="sf-context-summary-left">
                                                    <span class="sf-context-kicker">Handoff Context</span>
                                                    <span class="sf-context-preview"><?php echo htmlspecialchars(sf_reply_ui_truncate($ctx['preview'], 82), ENT_QUOTES, 'UTF-8'); ?></span>
                                                </span>
                                                <svg class="sf-context-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                                            </summary>
                                            <div class="sf-context-body">
                                                <?php if ($ctx['note'] !== ''): ?>
                                                    <div class="sf-context-note">
                                                        <strong>Guest Request:</strong> <?php echo htmlspecialchars($ctx['note'], ENT_QUOTES, 'UTF-8'); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php foreach ($ctx['turns'] as $turn): ?>
                                                    <div class="sf-context-turn">
                                                        <span class="sf-context-speaker"><?php echo htmlspecialchars($turn['speaker'], ENT_QUOTES, 'UTF-8'); ?>:</span>
                                                        <span><?php echo htmlspecialchars($turn['text'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </details>
                                    <?php endif; ?>

                                    <?php sf_reply_ui_render_panel($inqId, (string)($inq['guest_email'] ?? ''), $statusStr, $thread, ['hide_status_bar' => true]); ?>
                                </article>
                            <?php endforeach; ?>
                        </section>
                    </div>
                <?php endif; ?>
            </div>
            <?php
            return;
        endif;

        // Clean Contact Us Inquiries View (admin_inquiries.php)
        ?>
        <div class="inquiries-container">
            <?php if (!empty($tickets)): ?>
                <div class="sf-filter-bar">
                    <div class="sf-filter-pills">
                        <button type="button" class="sf-filter-btn is-active" data-ticket-filter="all">All (<?php echo count($tickets); ?>)</button>
                        <button type="button" class="sf-filter-btn" data-ticket-filter="open">Open (<?php echo $openCount; ?>)</button>
                        <button type="button" class="sf-filter-btn" data-ticket-filter="resolved">Resolved (<?php echo $resolvedCount; ?>)</button>
                    </div>
                </div>

                <?php foreach ($tickets as $inq):
                    $inqId = (int)$inq['id'];
                    $statusStr = (string)($inq['status'] ?? 'Unread');
                    $cardClass = strtolower($statusStr);
                    if (!in_array($cardClass, ['unread', 'read', 'resolved'], true)) {
                        $cardClass = 'read';
                    }
                    $badgeClass = 'status-' . $cardClass;
                    $hasRealEmail = sf_reply_ui_has_real_email($inq['guest_email'] ?? '');
                    $thread = $threads[$inqId] ?? [];
                ?>
                    <div id="inquiry-<?php echo $inqId; ?>" class="inquiry-card <?php echo htmlspecialchars($cardClass, ENT_QUOTES, 'UTF-8'); ?>" data-inquiry-card data-inquiry-id="<?php echo $inqId; ?>">
                        <div class="inquiry-header">
                            <div class="inquiry-author">
                                <span class="sf-chat-avatar" style="width:36px;height:36px;">
                                    <?php echo htmlspecialchars(sf_reply_ui_initial((string)$inq['guest_name']), ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                                <div class="inquiry-meta">
                                    <strong><?php echo htmlspecialchars((string)$inq['guest_name'], ENT_QUOTES, 'UTF-8'); ?></strong><br>
                                    <?php if ($hasRealEmail): ?>
                                        <a href="mailto:<?php echo htmlspecialchars((string)$inq['guest_email'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string)$inq['guest_email'], ENT_QUOTES, 'UTF-8'); ?></a> &middot;
                                    <?php else: ?>
                                        <span>No email provided &middot; </span>
                                    <?php endif; ?>
                                    <span><?php echo htmlspecialchars(sf_reply_ui_format_time((string)$inq['created_at']), ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                            </div>

                            <div class="inquiry-head-right">
                                <span class="status-badge <?php echo htmlspecialchars($badgeClass, ENT_QUOTES, 'UTF-8'); ?>" data-status-badge><?php echo htmlspecialchars($statusStr, ENT_QUOTES, 'UTF-8'); ?></span>
                                <form method="POST">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="inquiry_id" value="<?php echo $inqId; ?>">
                                    <?php if ($statusStr === 'Unread'): ?>
                                        <button type="submit" name="action" value="mark_read" class="sf-ghost-btn is-read">Mark Read</button>
                                    <?php endif; ?>
                                    <?php if ($statusStr !== 'Resolved'): ?>
                                        <button type="submit" name="action" value="mark_resolved" class="sf-ghost-btn is-resolve">Resolve</button>
                                    <?php endif; ?>
                                    <?php if ($hasRealEmail): ?>
                                        <a href="mailto:<?php echo htmlspecialchars((string)$inq['guest_email'], ENT_QUOTES, 'UTF-8'); ?>?subject=Re: <?php echo urlencode((string)$inq['subject']); ?>" class="sf-ghost-btn">Mail App</a>
                                    <?php endif; ?>
                                    <button type="button" class="sf-ghost-btn is-danger" onclick="showConfirm({ title: 'Delete Message', message: 'Are you sure you want to delete this inquiry? This cannot be undone.', icon: 'trash', iconBg: '#FEE2E2', confirmText: 'Delete', onConfirm: () => this.form.requestSubmit(this.form.querySelector('[value=delete]')) })">Delete</button>
                                    <button type="submit" name="action" value="delete" hidden>Confirm Delete</button>
                                </form>
                            </div>
                        </div>

                        <div class="inquiry-subject"><?php echo htmlspecialchars((string)$inq['subject'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="inquiry-message"><?php echo htmlspecialchars((string)$inq['message'], ENT_QUOTES, 'UTF-8'); ?></div>

                        <?php sf_reply_ui_render_panel($inqId, (string)($inq['guest_email'] ?? ''), $statusStr, $thread, ['compact' => true, 'hide_status_bar' => true]); ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 54px 20px; color: #64748B; background: var(--card-bg, #FFFFFF); border: 1px solid #E5E0D8; border-radius: 14px;">
                    <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 10px; opacity: 0.5;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                    <p style="margin:0; font-size: 14px;"><?php echo htmlspecialchars($emptyText, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}

if (!function_exists('sf_reply_ui_render_script')) {
    function sf_reply_ui_render_script(string $csrfToken, array $options = []): void
    {
        $supportTabSelector = (string)($options['support_tab_selector'] ?? '');
        $badgeStyleMode = (string)($options['badge_style'] ?? 'inquiries'); // 'inquiries' or 'chatbot'
        $cfg = [
            'csrfToken' => $csrfToken,
            'supportTabSelector' => $supportTabSelector,
            'badgeStyleMode' => $badgeStyleMode,
        ];
        $jsPath = __DIR__ . '/../assets/js/components/inquiry-reply.js';
        $jsVer = (int) @filemtime($jsPath);
        ?>
        <script>window.SF_REPLY_UI_CONFIG = <?php echo json_encode($cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
        <script src="assets/js/components/inquiry-reply.js?v=<?php echo $jsVer; ?>"></script>
        <?php
    }
}
