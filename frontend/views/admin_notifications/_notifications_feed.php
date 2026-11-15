<?php
/**
 * Shared notification feed card + filters + modal used by admin_notifications and notifications.
 *
 * Expected in scope:
 * - array  $notifData
 * - string $notifActionRoute ('admin_notifications' or 'notifications')
 */
$notifActionRoute = $notifActionRoute ?? 'admin_notifications';
$allNotifs = $notifData['notifications'] ?? [];
$totalCount = (int)($notifData['totalCount'] ?? count($allNotifs));
$unreadCount = (int)($notifData['unreadCount'] ?? 0);
$urgentCount = (int)($notifData['urgentCount'] ?? 0);
$infoSuccessCount = max(0, $totalCount - $urgentCount);

$renderNotifIcon = static function (string $type): string {
    switch ($type) {
        case 'success':
            return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
        case 'warning':
            return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';
        case 'alert':
            return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
        default:
            return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>';
    }
};
?>
<div class="card notif-feed-card">
    <div class="card-header notif-feed-header">
        <div class="notif-feed-title-wrap">
            <h2>Recent Notifications</h2>
            <p class="notif-feed-subtitle">Click any alert to inspect details or jump directly to the related record.</p>
        </div>
        <div class="notif-header-badges">
            <span class="section-count" id="notifUnreadPill"><?php echo $unreadCount; ?> unread</span>
            <span class="section-count section-count--muted" id="notifVisibleCount"><?php echo $totalCount; ?> total</span>
        </div>
    </div>

    <?php if (!empty($allNotifs)): ?>
    <div class="notif-toolbar">
        <div class="notif-filter-tabs" role="tablist" aria-label="Filter notifications">
            <button type="button" class="notif-filter-pill active" data-notif-filter="all">
                <span>All</span>
                <span class="notif-pill-count"><?php echo $totalCount; ?></span>
            </button>
            <button type="button" class="notif-filter-pill" data-notif-filter="unread">
                <span>Unread</span>
                <span class="notif-pill-count" id="notifTabUnreadCount"><?php echo $unreadCount; ?></span>
            </button>
            <button type="button" class="notif-filter-pill" data-notif-filter="urgent">
                <span>Alerts &amp; Warnings</span>
                <span class="notif-pill-count"><?php echo $urgentCount; ?></span>
            </button>
            <button type="button" class="notif-filter-pill" data-notif-filter="updates">
                <span>Info &amp; Success</span>
                <span class="notif-pill-count"><?php echo $infoSuccessCount; ?></span>
            </button>
        </div>

        <div class="notif-search-box">
            <svg class="notif-search-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="search" id="notifSearchInput" class="notif-search-input" placeholder="Search title, guest, REF-..." aria-label="Search notifications">
            <button type="button" id="notifSearchClear" class="notif-search-clear" title="Clear search" aria-label="Clear search" hidden>&times;</button>
        </div>
    </div>
    <?php endif; ?>

    <div class="notif-card-list" id="notifCardList">
        <?php if (!empty($allNotifs)): ?>
            <?php foreach ($allNotifs as $n):
                $bookingId = (int)($n['booking_id'] ?? 0);
                $refLabel = $bookingId > 0 ? 'REF-' . str_pad((string)$bookingId, 3, '0', STR_PAD_LEFT) : '';
                $searchHaystack = strtolower(trim($n['title'] . ' ' . $n['message'] . ' ' . $n['type'] . ' ' . $refLabel));
            ?>
                <div class="notif-item type-<?php echo htmlspecialchars($n['type'], ENT_QUOTES, 'UTF-8'); ?> <?php echo $n['is_read'] ? 'is-read' : 'unread'; ?>"
                     data-id="<?php echo (int)$n['id']; ?>"
                     data-type="<?php echo htmlspecialchars($n['type'], ENT_QUOTES, 'UTF-8'); ?>"
                     data-unread="<?php echo $n['is_read'] ? '0' : '1'; ?>"
                     data-search="<?php echo htmlspecialchars($searchHaystack, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="notif-leading-icon" aria-hidden="true">
                        <?php echo $renderNotifIcon($n['type']); ?>
                    </div>

                    <div class="notif-content" data-action="show-notif-detail" data-notif="<?php echo htmlspecialchars($n['detailJson'], ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="notif-meta-row">
                            <span class="notif-badge <?php echo htmlspecialchars($n['badgeClass'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(strtoupper($n['type']), ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php if (!$n['is_read']): ?>
                                <span class="notif-unread-pill"><span class="notif-unread-dot" aria-hidden="true"></span>Unread</span>
                            <?php endif; ?>
                            <?php if ($refLabel !== ''): ?>
                                <span class="notif-ref-chip"><?php echo htmlspecialchars($refLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="notif-title"><?php echo htmlspecialchars($n['title'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="notif-msg"><?php echo htmlspecialchars($n['message'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="notif-footer-meta">
                            <span class="notif-time">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                <?php echo htmlspecialchars($n['time'], ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                            <span class="notif-open-cue">View details &rarr;</span>
                        </div>
                    </div>

                    <div class="notif-actions">
                        <?php if (!$n['is_read']): ?>
                            <form method="POST" action="<?php echo htmlspecialchars($notifActionRoute, ENT_QUOTES, 'UTF-8'); ?>" style="margin:0;">
                                <?php echo $notifData['csrfField']; ?>
                                <input type="hidden" name="action" value="mark_read">
                                <input type="hidden" name="notif_id" value="<?php echo (int)$n['id']; ?>">
                                <button type="submit" class="btn-action-icon btn-mark-read" title="Mark as read">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                                    <span>Mark Read</span>
                                </button>
                            </form>
                        <?php endif; ?>
                        <form method="POST" action="<?php echo htmlspecialchars($notifActionRoute, ENT_QUOTES, 'UTF-8'); ?>" style="margin:0;">
                            <?php echo $notifData['csrfField']; ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="notif_id" value="<?php echo (int)$n['id']; ?>">
                            <button type="submit" class="btn-action-icon btn-delete-notif" title="Delete notification" aria-label="Delete notification">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
            <div class="notif-empty notif-filter-empty" id="notifFilterEmpty" hidden>
                <div class="notif-empty-icon" aria-hidden="true">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                </div>
                <strong>No matching notifications</strong>
                <p>Try another keyword or switch filters to see all alerts.</p>
                <button type="button" class="notif-reset-btn" id="notifResetFilter">Show all notifications</button>
            </div>
        <?php else: ?>
            <div class="notif-empty">
                <div class="notif-empty-icon" aria-hidden="true">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                </div>
                <strong>No notifications right now</strong>
                <p>Booking updates, payment verifications, and front desk broadcasts will appear here.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Custom Accept Confirmation Modal -->
<div id="acceptModal" class="notif-accept-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(15,23,42,.55);backdrop-filter:blur(4px);align-items:center;justify-content:center;">
    <div style="background:var(--card-bg, #fff);border:1px solid var(--border, #E2E8F0);border-radius:18px;box-shadow:0 24px 60px rgba(15,23,42,.22);padding:36px 32px 28px;max-width:420px;width:calc(100% - 40px);text-align:center;animation:modalPop .18s cubic-bezier(.34,1.56,.64,1);">
        <div style="width:54px;height:54px;border-radius:50%;background:var(--blue-bg, #EFF6FF);display:flex;align-items:center;justify-content:center;margin:0 auto 18px;">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#1D4ED8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
        </div>
        <h3 style="margin:0 0 8px;font-size:18px;font-weight:700;color:var(--text-main, #0f172a);">Review booking notification?</h3>
        <p style="margin:0 0 26px;font-size:14px;color:var(--text-muted, #64748b);line-height:1.6;">Mark this booking notification as reviewed. The guest receives a confirmation automatically after payment verification.</p>
        <div style="display:flex;gap:10px;justify-content:center;">
            <button type="button" data-action="close-accept-modal" style="flex:1;padding:11px 0;border:1.5px solid var(--border, #E2E8F0);border-radius:10px;background:var(--bg-surface, #fff);font-size:14px;font-weight:600;color:var(--text-muted, #475569);cursor:pointer;transition:background .15s;">Cancel</button>
            <button type="button" id="acceptConfirmBtn" style="flex:1;padding:11px 0;border:none;border-radius:10px;background:linear-gradient(135deg,#16a34a,#22c55e);font-size:14px;font-weight:700;color:#fff;cursor:pointer;box-shadow:0 4px 12px rgba(34,197,94,.35);transition:opacity .15s;">Mark as reviewed</button>
        </div>
    </div>
</div>
