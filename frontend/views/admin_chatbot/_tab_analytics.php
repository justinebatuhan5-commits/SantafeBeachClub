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
                        <tr><td colspan="5" style="color:var(--text-muted);">No unanswered guest questions detected &mdash; all recent inquiries matched verified resort data or FAQs!</td></tr>
                    <?php else: foreach ($unansweredMessages as $um): ?>
                        <tr>
                            <td><strong>#<?php echo (int)$um['conversation_id']; ?></strong></td>
                            <td><span class="badge badge-confirmed"><?php echo htmlspecialchars((string)$um['language']); ?></span></td>
                            <td><strong><?php echo htmlspecialchars((string)$um['user_question']); ?></strong></td>
                            <td style="font-size:12px; white-space:nowrap;"><?php echo htmlspecialchars((string)$um['created_at']); ?></td>
                            <td style="white-space:nowrap;">
                                <button type="button" class="btn-primary js-prefill-kb" style="padding:5px 10px; font-size:11.5px;"
                                    data-question="<?php echo htmlspecialchars((string)$um['user_question'], ENT_QUOTES, 'UTF-8'); ?>">
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
                                <button type="button" class="btn-secondary js-prefill-kb" style="padding:5px 10px; font-size:11.5px;"
                                    data-question="<?php echo htmlspecialchars((string)($fm['user_question'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                    Add to FAQ
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
