<div class="admin-body notifications-page">
    <?php if (!empty($notifData['notifFlash'])): ?>
        <div class="notif-flash" role="status">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <span><?php echo htmlspecialchars($notifData['notifFlash'], ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
    <?php endif; ?>

    <!-- Summary Widgets -->
    <div class="summary-banner">
        <button type="button" class="summary-card summary-card--interactive" id="summaryUnreadCard" title="Filter feed to unread notifications">
            <div class="summary-icon" aria-hidden="true">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            </div>
            <div class="summary-body">
                <div class="summary-value" id="summaryUnreadValue"><?php echo (int)$notifData['unreadCount']; ?></div>
                <div class="summary-label">Unread Alerts</div>
                <div class="summary-sub"><?php echo (int)($notifData['urgentUnreadCount'] ?? 0); ?> urgent &middot; <?php echo (int)($notifData['totalCount'] ?? count($notifData['notifications'])); ?> total</div>
            </div>
        </button>

        <a href="admin_checkin" class="summary-card summary-card--link">
            <div class="summary-icon" aria-hidden="true">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
            </div>
            <div class="summary-body">
                <div class="summary-value"><?php echo (int)$notifData['pendingCount']; ?></div>
                <div class="summary-label">Pending Check-ins</div>
                <div class="summary-sub">Open Arrivals &rarr;</div>
            </div>
        </a>

        <a href="accommodations" class="summary-card summary-card--link">
            <div class="summary-icon" aria-hidden="true">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
            </div>
            <div class="summary-body">
                <div class="summary-value"><?php echo (int)$notifData['maintCount']; ?></div>
                <div class="summary-label">Rooms in Maintenance</div>
                <div class="summary-sub">View Accommodations &rarr;</div>
            </div>
        </a>
    </div>

    <section class="dashboard-grid">
        <?php
        $notifActionRoute = 'admin_notifications';
        include __DIR__ . '/_notifications_feed.php';
        ?>

        <!-- Create New Broadcast Notification -->
        <div class="card broadcast-card">
            <div class="card-header broadcast-header">
                <div class="broadcast-title-row">
                    <span class="broadcast-header-icon" aria-hidden="true">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg>
                    </span>
                    <div>
                        <h2>Send Alert Broadcast</h2>
                        <p class="broadcast-sub">Front desk announcement</p>
                    </div>
                </div>
            </div>

            <p class="broadcast-intro">Share an operational update with the front desk team. Pick an urgency level so staff can spot critical notes right away.</p>
            <form method="POST" action="admin_notifications" class="broadcast-form">
                <?php echo $notifData['csrfField']; ?>
                <input type="hidden" name="action" value="create_notif">

                <div>
                    <div class="form-label-row">
                        <label class="form-label" for="broadcastTitle">Title</label>
                        <span class="form-counter" id="broadcastTitleCount">0 / 120</span>
                    </div>
                    <input class="form-control" id="broadcastTitle" type="text" name="title" required maxlength="120" placeholder="e.g. VIP transfer arriving at 4:00 PM">
                </div>

                <div>
                    <span class="form-label">Alert level</span>
                    <div class="broadcast-type-grid" role="radiogroup" aria-label="Alert level">
                        <label class="broadcast-type-option type-info">
                            <input type="radio" name="type" value="info" checked>
                            <span class="broadcast-type-chip"><span class="type-dot"></span>Info</span>
                        </label>
                        <label class="broadcast-type-option type-success">
                            <input type="radio" name="type" value="success">
                            <span class="broadcast-type-chip"><span class="type-dot"></span>Success</span>
                        </label>
                        <label class="broadcast-type-option type-warning">
                            <input type="radio" name="type" value="warning">
                            <span class="broadcast-type-chip"><span class="type-dot"></span>Warning</span>
                        </label>
                        <label class="broadcast-type-option type-alert">
                            <input type="radio" name="type" value="alert">
                            <span class="broadcast-type-chip"><span class="type-dot"></span>Alert</span>
                        </label>
                    </div>
                </div>

                <div>
                    <div class="form-label-row">
                        <label class="form-label" for="broadcastMessage">Message</label>
                        <span class="form-counter" id="broadcastMsgCount">0 / 1000</span>
                    </div>
                    <textarea class="form-control" id="broadcastMessage" name="message" required rows="4" maxlength="1000" placeholder="Enter the broadcast details..."></textarea>
                </div>

                <div class="broadcast-preview type-info" id="broadcastPreview">
                    <span class="notif-badge badge-info" id="broadcastPreviewBadge">INFO</span>
                    <div class="broadcast-preview-title" id="broadcastPreviewTitle">Your alert title preview</div>
                    <div class="broadcast-preview-msg" id="broadcastPreviewMsg">Broadcast message details will appear in the team feed.</div>
                </div>

                <button type="submit" class="broadcast-submit">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
                    Post Notification
                </button>
            </form>
        </div>
    </section>
</div>
