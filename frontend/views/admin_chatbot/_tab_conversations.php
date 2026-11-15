        <!-- TAB 2: CONVERSATION MONITORING -->
        <section id="ai-tab-conversations" class="ai-panel <?php echo $activeTab === 'conversations' ? 'active' : ''; ?>">
            <!-- Conversation Monitoring Overview & Quick Filter KPI Strip -->
            <div class="admin-card" style="margin-bottom:16px; border-left:4px solid #84563C;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px;">
                    <div>
                        <h3 style="font-size:16px; font-weight:800; margin:0; color:var(--text-main, #0F172A);">Conversation Monitoring Center</h3>
                        <p style="font-size:12.5px; color:var(--text-muted, #64748B); margin:4px 0 0;">
                            <strong>1. View Guest Conversations:</strong> Inspect full multi-turn guest transcripts across all languages &middot;
                            <strong>2. Identify Unanswered Questions:</strong> Detect knowledge gaps &amp; teach the AI in 1 click &middot;
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

            <!-- Unanswered Questions & Monitored Bot Responses Inspector -->
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
                        <tr><td colspan="6" style="color:var(--text-muted);">No unanswered guest questions detected &mdash; all recent guest inquiries were answered with verified resort data!</td></tr>
                    <?php else: foreach ($unansweredMessages as $um): ?>
                        <tr>
                            <td>
                                <a href="#conv-card-<?php echo (int)$um['conversation_id']; ?>" class="js-jump-conv" data-conv-id="<?php echo (int)$um['conversation_id']; ?>" style="font-weight:700; color:#84563C; text-decoration:underline;">
                                    #<?php echo (int)$um['conversation_id']; ?> &darr;
                                </a>
                            </td>
                            <td><span class="badge badge-confirmed"><?php echo htmlspecialchars((string)$um['language']); ?></span></td>
                            <td><strong style="color:#92400E;"><?php echo htmlspecialchars((string)$um['user_question']); ?></strong></td>
                            <td style="font-size:12px; color:var(--text-muted);"><?php echo htmlspecialchars(mb_strimwidth((string)($um['bot_reply'] ?? 'Flagged for human follow-up'), 0, 130, '...')); ?></td>
                            <td style="font-size:12px; white-space:nowrap;"><?php echo htmlspecialchars((string)$um['created_at']); ?></td>
                            <td style="white-space:nowrap;">
                                <button type="button" class="btn-primary js-prefill-kb" style="padding:5px 10px; font-size:11.5px;"
                                    data-question="<?php echo htmlspecialchars((string)$um['user_question'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-lang="<?php echo htmlspecialchars((string)$um['language'], ENT_QUOTES, 'UTF-8'); ?>">
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
                    <input type="text" id="convSearchInput" name="conv_q" value="<?php echo htmlspecialchars($convSearch); ?>" placeholder="Search guest questions, chatbot responses, or session ID...">
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
                                        <?php if (!empty($m['provider'])): ?><span>Provider: <strong><?php echo htmlspecialchars((string)$m['provider']); ?></strong></span> &middot; <?php endif; ?>
                                        <?php if (!empty($m['latency_ms'])): ?><span><?php echo (int)$m['latency_ms']; ?>ms</span> &middot; <?php endif; ?>
                                        <span><?php echo htmlspecialchars((string)$m['created_at']); ?></span>
                                        <?php if ((int)$m['feedback'] !== 0): ?>
                                            <strong style="margin-left:4px; color:<?php echo (int)$m['feedback'] > 0 ? '#16A34A' : '#DC2626'; ?>;">
                                                <?php echo (int)$m['feedback'] > 0 ? 'Helpful' : 'Unhelpful'; ?>
                                            </strong>
                                        <?php endif; ?>
                                        <?php if ($isUnansTurn): ?>
                                            <button type="button" class="btn-primary js-prefill-kb" style="padding:2px 8px; font-size:10.5px;"
                                                data-question="<?php echo htmlspecialchars((string)$m['content'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-lang="<?php echo htmlspecialchars((string)$conv['language'], ENT_QUOTES, 'UTF-8'); ?>">
                                                Teach AI this Question
                                            </button>
                                        <?php elseif ($isNegTurn && $lastGuestQuestionInConv !== ''): ?>
                                            <button type="button" class="btn-secondary js-prefill-kb" style="padding:2px 8px; font-size:10.5px;"
                                                data-question="<?php echo htmlspecialchars($lastGuestQuestionInConv, ENT_QUOTES, 'UTF-8'); ?>"
                                                data-lang="<?php echo htmlspecialchars((string)$conv['language'], ENT_QUOTES, 'UTF-8'); ?>">
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
