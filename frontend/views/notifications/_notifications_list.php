<?php if ($notifData['notifFlash']): ?>
    <div style="background:#E8F5E9; color:#2E7D32; border-radius:8px; padding:12px 18px; margin-bottom:20px; font-size:14px; font-weight:600;">
        <?php echo htmlspecialchars($notifData['notifFlash']); ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2>Notifications <?php echo $notifData['unreadCount'] > 0 ? '(' . (int)$notifData['unreadCount'] . ' unread)' : ''; ?></h2>
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
                            <form method="POST" action="notifications" style="margin:0;">
                                <?php echo $notifData['csrfField']; ?>
                                <input type="hidden" name="action" value="mark_read">
                                <input type="hidden" name="notif_id" value="<?php echo (int)$n['id']; ?>">
                                <button type="submit" class="btn-action-icon" title="Mark as read">Mark Read</button>
                            </form>
                        <?php endif; ?>
                        <form method="POST" action="notifications" style="margin:0;">
                            <?php echo $notifData['csrfField']; ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="notif_id" value="<?php echo (int)$n['id']; ?>">
                            <button type="submit" class="btn-action-icon" style="color: #c62828;" title="Delete notification">&times;</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="text-align: center; color: #888; padding: 40px;">No notifications found</div>
        <?php endif; ?>
    </div>
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
