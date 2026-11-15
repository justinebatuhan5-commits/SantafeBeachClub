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

            <?php if (empty($handoffQueue)): ?>
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
                            <strong style="margin-left:6px; font-size:14px;">Ticket #<?php echo $inqId; ?> &mdash; <?php echo htmlspecialchars((string)$hq['subject']); ?></strong>
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
