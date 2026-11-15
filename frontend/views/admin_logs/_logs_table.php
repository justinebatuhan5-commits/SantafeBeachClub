<?php
$tab          = $logsData['tab'];
$filterUser   = $logsData['filterUser'];
$filterAction = $logsData['filterAction'];
$filterDate   = $logsData['filterDate'];
$pageNum      = $logsData['pageNum'];
$totalPages   = $logsData['totalPages'];
?>
<!-- Tabs Navigation -->
<div style="display:flex; gap:10px; margin-bottom:20px; border-bottom:1px solid #E2E8F0; padding-bottom:12px;">
    <a href="admin_logs?tab=activity" style="padding:8px 16px; border-radius:8px; font-weight:600; font-size:14px; text-decoration:none; display:flex; align-items:center; gap:8px; <?php echo $tab === 'activity' ? 'background:#7C533C; color:#fff;' : 'background:#F1F5F9; color:#475569;'; ?>">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        Activity Logs
    </a>
    <a href="admin_logs?tab=security" style="padding:8px 16px; border-radius:8px; font-weight:600; font-size:14px; text-decoration:none; display:flex; align-items:center; gap:8px; <?php echo $tab === 'security' ? 'background:#7C533C; color:#fff;' : 'background:#F1F5F9; color:#475569;'; ?>">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        Security &amp; Threat Monitoring
    </a>
</div>

<!-- Filters -->
<form method="GET" class="filter-bar" style="display:flex; flex-wrap:wrap; gap:10px; align-items:center;">
    <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">
    <input type="text" name="user" placeholder="Filter by username/IP" value="<?php echo htmlspecialchars($filterUser); ?>">
    <input type="text" name="action_type" placeholder="Filter by event/action" value="<?php echo htmlspecialchars($filterAction); ?>">
    <input type="date" name="date" value="<?php echo htmlspecialchars($filterDate); ?>">
    <button type="submit" class="btn-primary">Filter</button>
    <a href="admin_logs?tab=<?php echo urlencode($tab); ?>" class="btn-secondary">Clear</a>
    <a href="admin_logs?tab=<?php echo urlencode($tab); ?>&amp;export=csv&amp;user=<?php echo urlencode($filterUser); ?>&amp;action_type=<?php echo urlencode($filterAction); ?>&amp;date=<?php echo urlencode($filterDate); ?>"
       class="btn-secondary"
       style="margin-left:auto; display:inline-flex; align-items:center; gap:6px; background:#F8FAFC; border:1px solid #CBD5E1; color:#334155; font-weight:600; text-decoration:none; padding:8px 14px; border-radius:8px;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Export CSV
    </a>
</form>

<div class="admin-card">
    <?php if ($tab === 'security'): ?>
    <!-- SECURITY LOGS TABLE -->
    <table class="admin-table">
        <thead>
            <tr>
                <th>Timestamp</th>
                <th>Level</th>
                <th>Event Type</th>
                <th>User</th>
                <th>Details</th>
                <th>IP Address</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($logsData['logRows'])): ?>
        <tr><td colspan="6"><div class="empty-state"><svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg><p>No security events recorded.</p></div></td></tr>
        <?php else: ?>
        <?php foreach ($logsData['logRows'] as $log):
            $lvl = strtoupper((string)($log['event_level'] ?? 'INFO'));
            $badgeStyle = match ($lvl) {
                'CRITICAL' => 'background:#FEE2E2;color:#B91C1C;font-weight:700;',
                'WARNING'  => 'background:#FEF3C7;color:#B45309;font-weight:600;',
                default    => 'background:#E0F2FE;color:#0369A1;'
            };
        ?>
        <tr>
            <td style="color:var(--text-muted);font-size:12.5px;white-space:nowrap;"><?php echo date('M j, Y g:i a', strtotime((string)$log['created_at'])); ?></td>
            <td><span style="display:inline-block;padding:3px 8px;border-radius:6px;font-size:11px;<?php echo $badgeStyle; ?>"><?php echo htmlspecialchars($lvl); ?></span></td>
            <td style="font-weight:600;color:#0F172A;"><?php echo htmlspecialchars((string)$log['event_type']); ?></td>
            <td><?php echo htmlspecialchars((string)($log['username'] ?: 'anonymous')); ?></td>
            <td style="font-size:13px;max-width:320px;word-break:break-word;"><?php echo htmlspecialchars((string)($log['description'] ?? '—')); ?></td>
            <td style="color:var(--text-muted);font-size:12.5px;"><?php echo htmlspecialchars((string)($log['ip_address'] ?? '—')); ?></td>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>

    <?php else: ?>
    <!-- ACTIVITY LOGS TABLE -->
    <table class="admin-table">
        <thead>
            <tr>
                <th>Timestamp</th>
                <th>User</th>
                <th>Action</th>
                <th>Details</th>
                <th>IP Address</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($logsData['logRows'])): ?>
        <tr><td colspan="5"><div class="empty-state"><svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg><p>No activity logs found.</p></div></td></tr>
        <?php else: ?>
        <?php foreach ($logsData['logRows'] as $log):
            $dot = 'default';
            if (stripos((string)$log['action'], 'login') !== false) {
                $dot = 'login';
            }
            if (stripos((string)$log['action'], 'booking') !== false) {
                $dot = 'booking';
            }
            if (stripos((string)$log['action'], 'payment') !== false) {
                $dot = 'payment';
            }
            $dotColors = ['login' => '#10B981', 'booking' => '#3B82F6', 'payment' => '#F59E0B', 'default' => '#94A3B8'];
        ?>
        <tr>
            <td style="color:var(--text-muted);font-size:12.5px;white-space:nowrap;"><?php echo date('M j, Y g:i a', strtotime((string)$log['created_at'])); ?></td>
            <td>
                <div style="display:flex;align-items:center;gap:8px;">
                    <div style="width:7px;height:7px;border-radius:50%;background:<?php echo $dotColors[$dot]; ?>;flex-shrink:0;"></div>
                    <span style="font-weight:600;"><?php echo htmlspecialchars((string)$log['admin_username']); ?></span>
                </div>
            </td>
            <td style="font-weight:500;"><?php echo htmlspecialchars((string)$log['action']); ?></td>
            <td style="color:var(--text-muted);font-size:13px;max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo htmlspecialchars((string)($log['details'] ?? '')); ?>"><?php echo htmlspecialchars((string)($log['details'] ?? '—')); ?></td>
            <td style="color:var(--text-muted);font-size:12.5px;"><?php echo htmlspecialchars((string)($log['ip_address'] ?? '—')); ?></td>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php if ($pageNum > 1): ?><a href="?tab=<?php echo urlencode($tab); ?>&amp;p=<?php echo $pageNum - 1; ?>&amp;user=<?php echo urlencode($filterUser); ?>&amp;action_type=<?php echo urlencode($filterAction); ?>&amp;date=<?php echo urlencode($filterDate); ?>">&larr; Prev</a><?php endif; ?>
        <?php for ($i = max(1, $pageNum - 2); $i <= min($totalPages, $pageNum + 2); $i++): ?>
            <?php if ($i === $pageNum): ?><span class="current"><?php echo $i; ?></span>
            <?php else: ?><a href="?tab=<?php echo urlencode($tab); ?>&amp;p=<?php echo $i; ?>&amp;user=<?php echo urlencode($filterUser); ?>&amp;action_type=<?php echo urlencode($filterAction); ?>&amp;date=<?php echo urlencode($filterDate); ?>"><?php echo $i; ?></a><?php endif; ?>
        <?php endfor; ?>
        <?php if ($pageNum < $totalPages): ?><a href="?tab=<?php echo urlencode($tab); ?>&amp;p=<?php echo $pageNum + 1; ?>&amp;user=<?php echo urlencode($filterUser); ?>&amp;action_type=<?php echo urlencode($filterAction); ?>&amp;date=<?php echo urlencode($filterDate); ?>">Next &rarr;</a><?php endif; ?>
    </div>
    <?php endif; ?>
</div>
