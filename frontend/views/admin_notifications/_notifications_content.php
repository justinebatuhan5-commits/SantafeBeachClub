<div class="admin-body notifications-page">
    <?php if ($notifData['notifFlash']): ?>
        <div class="notif-flash">
            <?php echo htmlspecialchars($notifData['notifFlash']); ?>
        </div>
    <?php endif; ?>

    <!-- Summary Widgets -->
    <div class="summary-banner">
        <div class="summary-card">
            <div class="summary-icon" style="background: #2196F3;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path></svg>
            </div>
            <div>
                <h3 class="summary-value"><?php echo (int)$notifData['unreadCount']; ?></h3>
                <p class="summary-label">Unread Alerts</p>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon" style="background: #FF9800;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path></svg>
            </div>
            <div>
                <h3 class="summary-value"><?php echo (int)$notifData['pendingCount']; ?></h3>
                <p class="summary-label">Pending Check-ins</p>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon" style="background: #F44336;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path></svg>
            </div>
            <div>
                <h3 class="summary-value"><?php echo (int)$notifData['maintCount']; ?></h3>
                <p class="summary-label">Rooms in Maintenance</p>
            </div>
        </div>
    </div>

    <section class="dashboard-grid">
        <!-- Notifications List -->
        <div class="card">
            <div class="card-header">
                <h2>Recent Notifications</h2>
                <span class="section-count"><?php echo (int)$notifData['unreadCount']; ?> unread</span>
            </div>

            <div class="notif-card-list">
                <?php if (!empty($notifData['notifications'])): ?>
                    <?php foreach ($notifData['notifications'] as $n): ?>
                        <div class="notif-item type-<?php echo htmlspecialchars($n['type']); ?> <?php echo $n['is_read'] ? '' : 'unread'; ?>">
                            <div class="notif-content" data-action="show-notif-detail" data-notif="<?php echo htmlspecialchars($n['detailJson'], ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="notif-badge <?php echo htmlspecialchars($n['badgeClass']); ?>"><?php echo htmlspecialchars(strtoupper($n['type'])); ?></span>
                                <div class="notif-title"><?php echo htmlspecialchars($n['title']); ?></div>
                                <div class="notif-msg"><?php echo htmlspecialchars($n['message']); ?></div>
                                <div class="notif-time"><?php echo htmlspecialchars($n['time']); ?></div>
                            </div>
                            <div class="notif-actions">
                                <?php if (!$n['is_read']): ?>
                                    <form method="POST" action="admin_notifications" style="margin:0;">
                                        <?php echo $notifData['csrfField']; ?>
                                        <input type="hidden" name="action" value="mark_read">
                                        <input type="hidden" name="notif_id" value="<?php echo (int)$n['id']; ?>">
                                        <button type="submit" class="btn-action-icon" title="Mark as read">Mark Read</button>
                                    </form>
                                <?php endif; ?>
                                <form method="POST" action="admin_notifications" style="margin:0;">
                                    <?php echo $notifData['csrfField']; ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="notif_id" value="<?php echo (int)$n['id']; ?>">
                                    <button type="submit" class="btn-action-icon btn-delete-notif" title="Delete notification" aria-label="Delete notification">&times;</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="notif-empty">No notifications found</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Create New Broadcast Notification -->
        <div class="card broadcast-card">
            <div class="card-header">
                <h2>Send Alert Broadcast</h2>
            </div>

            <p class="broadcast-intro">Share an operational update with the front desk team. Choose the alert level that matches its urgency.</p>
            <form method="POST" action="admin_notifications" class="broadcast-form">
                <?php echo $notifData['csrfField']; ?>
                <input type="hidden" name="action" value="create_notif">

                <div>
                    <label class="form-label" for="broadcastTitle">Title</label>
                    <input class="form-control" id="broadcastTitle" type="text" name="title" required maxlength="120" placeholder="e.g. VIP transfer request">
                </div>

                <div>
                    <label class="form-label" for="broadcastType">Alert type</label>
                    <select class="form-control" id="broadcastType" name="type">
                        <option value="info">Info (Blue)</option>
                        <option value="success">Success (Green)</option>
                        <option value="warning">Warning (Orange)</option>
                        <option value="alert">Alert (Red)</option>
                    </select>
                </div>

                <div>
                    <label class="form-label" for="broadcastMessage">Message</label>
                    <textarea class="form-control" id="broadcastMessage" name="message" required rows="4" maxlength="1000" placeholder="Enter the broadcast details..."></textarea>
                </div>

                <button type="submit" class="broadcast-submit">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
                    Post Notification
                </button>
            </form>
        </div>
    </section>
</div>

<!-- Custom Accept Confirmation Modal -->
<div id="acceptModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(15,23,42,.55);backdrop-filter:blur(4px);align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:18px;box-shadow:0 24px 60px rgba(15,23,42,.22);padding:36px 32px 28px;max-width:420px;width:calc(100% - 40px);text-align:center;animation:modalPop .18s cubic-bezier(.34,1.56,.64,1);">
        <div style="width:54px;height:54px;border-radius:50%;background:#EFF6FF;display:flex;align-items:center;justify-content:center;margin:0 auto 18px;">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#1D4ED8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
        </div>
        <h3 style="margin:0 0 8px;font-size:18px;font-weight:700;color:#0f172a;">Review booking notification?</h3>
        <p style="margin:0 0 26px;font-size:14px;color:#64748b;line-height:1.6;">Mark this booking notification as reviewed. The guest receives a confirmation automatically after payment verification.</p>
        <div style="display:flex;gap:10px;justify-content:center;">
            <button type="button" data-action="close-accept-modal" style="flex:1;padding:11px 0;border:1.5px solid #E2E8F0;border-radius:10px;background:#fff;font-size:14px;font-weight:600;color:#475569;cursor:pointer;transition:background .15s;">Cancel</button>
            <button type="button" id="acceptConfirmBtn" style="flex:1;padding:11px 0;border:none;border-radius:10px;background:linear-gradient(135deg,#16a34a,#22c55e);font-size:14px;font-weight:700;color:#fff;cursor:pointer;box-shadow:0 4px 12px rgba(34,197,94,.35);transition:opacity .15s;">Mark as reviewed</button>
        </div>
    </div>
</div>
