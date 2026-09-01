<?php
require_once __DIR__ . '/../backend/helpers/admin_auth_check.php';
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/helpers/rbac_helper.php';
require_once __DIR__ . '/../backend/helpers/security_logger.php';

$admin = $_SESSION['admin_username'];
$tab = ($_GET['tab'] ?? 'activity') === 'security' ? 'security' : 'activity';

// Filters
$filter_user   = trim($_GET['user'] ?? '');
$filter_action = trim($_GET['action_type'] ?? '');
$filter_date   = trim($_GET['date'] ?? '');
$do_export     = ($_GET['export'] ?? '') === 'csv';

// Handle CSV Export
if ($do_export) {
    $filename = ($tab === 'security' ? 'security_logs_' : 'activity_logs_') . date('Y-m-d_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    // UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    $whereParts = [];
    $types = '';
    $params = [];

    if ($tab === 'security') {
        fputcsv($output, ['ID', 'Timestamp', 'Level', 'Event Type', 'Username', 'IP Address', 'User Agent', 'Request URI', 'Description']);
        if ($filter_user !== '') {
            $whereParts[] = 'username LIKE ?';
            $types .= 's';
            $params[] = '%' . $filter_user . '%';
        }
        if ($filter_action !== '') {
            $whereParts[] = 'event_type LIKE ?';
            $types .= 's';
            $params[] = '%' . $filter_action . '%';
        }
        if ($filter_date !== '') {
            $whereParts[] = 'DATE(created_at) = ?';
            $types .= 's';
            $params[] = $filter_date;
        }
        $whereClause = !empty($whereParts) ? 'WHERE ' . implode(' AND ', $whereParts) : '';
        $sql = "SELECT id, created_at, event_level, event_type, username, ip_address, user_agent, request_uri, description FROM security_logs $whereClause ORDER BY id DESC";
        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            fputcsv($output, [
                $row['id'],
                $row['created_at'],
                $row['event_level'],
                $row['event_type'],
                $row['username'],
                $row['ip_address'],
                $row['user_agent'],
                $row['request_uri'],
                $row['description'],
            ]);
        }
        $stmt->close();
    } else {
        fputcsv($output, ['ID', 'Timestamp', 'Admin Username', 'Action', 'Details', 'IP Address']);
        if ($filter_user !== '') {
            $whereParts[] = 'admin_username LIKE ?';
            $types .= 's';
            $params[] = '%' . $filter_user . '%';
        }
        if ($filter_action !== '') {
            $whereParts[] = 'action LIKE ?';
            $types .= 's';
            $params[] = '%' . $filter_action . '%';
        }
        if ($filter_date !== '') {
            $whereParts[] = 'DATE(created_at) = ?';
            $types .= 's';
            $params[] = $filter_date;
        }
        $whereClause = !empty($whereParts) ? 'WHERE ' . implode(' AND ', $whereParts) : '';
        $sql = "SELECT id, created_at, admin_username, action, details, ip_address FROM activity_logs $whereClause ORDER BY id DESC";
        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            fputcsv($output, [
                $row['id'],
                $row['created_at'],
                $row['admin_username'],
                $row['action'],
                $row['details'],
                $row['ip_address'],
            ]);
        }
        $stmt->close();
    }

    fclose($output);
    exit;
}

// Handle clear logs
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
    RBAC::requireRole('admin');
    $action = $_POST['action'] ?? '';
    
    if ($action === 'clear_logs') {
        $conn->query("DELETE FROM activity_logs");
        log_activity($conn, $admin, 'Logs Cleared', 'Activity log table was cleared');
        SecurityLogger::log($conn, 'LOGS_CLEARED', 'Activity log table was cleared by admin', SecurityLogger::LEVEL_WARNING, $admin);
        header('Location: admin_logs?tab=' . $tab);
        exit;
    }

    if ($action === 'clear_security_logs') {
        $conn->query("DELETE FROM security_logs");
        log_activity($conn, $admin, 'Security Logs Cleared', 'Security logs were purged');
        SecurityLogger::log($conn, 'SECURITY_LOGS_CLEARED', 'Security audit logs were cleared by admin', SecurityLogger::LEVEL_CRITICAL, $admin);
        header('Location: admin_logs?tab=security');
        exit;
    }
}

// Pagination
$per_page = 30;
$page_num = max(1, (int)($_GET['p'] ?? 1));
$offset   = ($page_num - 1) * $per_page;

if ($tab === 'security') {
    // ── Security Logs Query ───────────────────────────────────────────────
    $whereParts = [];
    $types = '';
    $params = [];

    if ($filter_user !== '') {
        $whereParts[] = 'username LIKE ?';
        $types .= 's';
        $params[] = '%' . $filter_user . '%';
    }
    if ($filter_action !== '') {
        $whereParts[] = 'event_type LIKE ?';
        $types .= 's';
        $params[] = '%' . $filter_action . '%';
    }
    if ($filter_date !== '') {
        $whereParts[] = 'DATE(created_at) = ?';
        $types .= 's';
        $params[] = $filter_date;
    }

    $whereClause = !empty($whereParts) ? 'WHERE ' . implode(' AND ', $whereParts) : '';

    // Count
    $countSql = "SELECT COUNT(*) AS c FROM security_logs $whereClause";
    $countStmt = $conn->prepare($countSql);
    if (!empty($params)) {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $total_rows = (int)$countStmt->get_result()->fetch_assoc()['c'];
    $countStmt->close();

    $total_pages = max(1, (int)ceil($total_rows / $per_page));

    // Data
    $dataSql = "SELECT id, event_type, event_level, username, ip_address, user_agent, description, created_at FROM security_logs $whereClause ORDER BY id DESC LIMIT ? OFFSET ?";
    $dataStmt = $conn->prepare($dataSql);
    $dataTypes = $types . 'ii';
    $dataParams = array_merge($params, [$per_page, $offset]);
    $dataStmt->bind_param($dataTypes, ...$dataParams);
    $dataStmt->execute();
    $logs = $dataStmt->get_result();
    $dataStmt->close();

} else {
    // ── Activity Logs Query ───────────────────────────────────────────────
    $whereParts = [];
    $types = '';
    $params = [];

    if ($filter_user !== '') {
        $whereParts[] = 'admin_username LIKE ?';
        $types .= 's';
        $params[] = '%' . $filter_user . '%';
    }
    if ($filter_action !== '') {
        $whereParts[] = 'action LIKE ?';
        $types .= 's';
        $params[] = '%' . $filter_action . '%';
    }
    if ($filter_date !== '') {
        $whereParts[] = 'DATE(created_at) = ?';
        $types .= 's';
        $params[] = $filter_date;
    }

    $whereClause = !empty($whereParts) ? 'WHERE ' . implode(' AND ', $whereParts) : '';

    // Count
    $countSql = "SELECT COUNT(*) AS c FROM activity_logs $whereClause";
    $countStmt = $conn->prepare($countSql);
    if (!empty($params)) {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $total_rows = (int)$countStmt->get_result()->fetch_assoc()['c'];
    $countStmt->close();

    $total_pages = max(1, (int)ceil($total_rows / $per_page));

    // Data
    $dataSql = "SELECT admin_username, action, details, ip_address, created_at FROM activity_logs $whereClause ORDER BY id DESC LIMIT ? OFFSET ?";
    $dataStmt = $conn->prepare($dataSql);
    $dataTypes = $types . 'ii';
    $dataParams = array_merge($params, [$per_page, $offset]);
    $dataStmt->bind_param($dataTypes, ...$dataParams);
    $dataStmt->execute();
    $logs = $dataStmt->get_result();
    $dataStmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/jpeg" href="assets/logo.jpg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System & Security Logs — Santa Fe Beach Club</title>
    <meta name="csrf-token" content="<?php echo htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="assets/css/admin.css?v=4">
    <script src="assets/js/security.js" defer></script>
</head>
<body>
    <?php $active_page = 'logs'; include __DIR__ . '/partials/_sidebar.php'; ?>

    <main class="main-content">
        <?php
        $page_title = $tab === 'security' ? 'Security & Audit Logs' : 'Activity Logs';
        $page_subtitle = number_format($total_rows).' total entries. Page '.$page_num.' of '.$total_pages.'.';
        $clearAction = $tab === 'security' ? 'clear_security_logs' : 'clear_logs';
        $header_extra_html = '
            <form method="POST" onsubmit="return false;" data-confirm-title="Clear Logs" data-confirm-msg="Clear these logs? This cannot be undone." data-confirm-icon="🗑️" data-confirm-icon-bg="#FEE2E2">
                ' . csrf_field() . '
                <input type="hidden" name="action" value="' . $clearAction . '">
                <button type="submit" style="cursor:pointer;border:1px solid #FCA5A5;color:#DC2626;background:none;padding:7px 14px;border-radius:8px;font-size:13px;display:flex;align-items:center;gap:6px;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/></svg>
                    Clear ' . ($tab === 'security' ? 'Security' : 'Activity') . ' Logs
                </button>
            </form>
        ';
        include __DIR__ . '/partials/_page_header.php';
        ?>

        <!-- Tabs Navigation -->
        <div style="display:flex; gap:10px; margin-bottom:20px; border-bottom:1px solid #E2E8F0; padding-bottom:12px;">
            <a href="admin_logs?tab=activity" style="padding:8px 16px; border-radius:8px; font-weight:600; font-size:14px; text-decoration:none; display:flex; align-items:center; gap:8px; <?php echo $tab === 'activity' ? 'background:#7C533C; color:#fff;' : 'background:#F1F5F9; color:#475569;'; ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                Activity Logs
            </a>
            <a href="admin_logs?tab=security" style="padding:8px 16px; border-radius:8px; font-weight:600; font-size:14px; text-decoration:none; display:flex; align-items:center; gap:8px; <?php echo $tab === 'security' ? 'background:#7C533C; color:#fff;' : 'background:#F1F5F9; color:#475569;'; ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                Security & Threat Monitoring
            </a>
        </div>

        <!-- Filters -->
        <form method="GET" class="filter-bar" style="display:flex; flex-wrap:wrap; gap:10px; align-items:center;">
            <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">
            <input type="text" name="user" placeholder="Filter by username/IP" value="<?php echo htmlspecialchars($filter_user); ?>">
            <input type="text" name="action_type" placeholder="Filter by event/action" value="<?php echo htmlspecialchars($filter_action); ?>">
            <input type="date" name="date" value="<?php echo htmlspecialchars($filter_date); ?>">
            <button type="submit" class="btn-primary">Filter</button>
            <a href="admin_logs?tab=<?php echo urlencode($tab); ?>" class="btn-secondary">Clear</a>
            <a href="admin_logs?tab=<?php echo urlencode($tab); ?>&export=csv&user=<?php echo urlencode($filter_user); ?>&action_type=<?php echo urlencode($filter_action); ?>&date=<?php echo urlencode($filter_date); ?>" 
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
                <?php if ($logs->num_rows === 0): ?>
                <tr><td colspan="6"><div class="empty-state"><svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg><p>No security events recorded.</p></div></td></tr>
                <?php else: ?>
                <?php while ($log = $logs->fetch_assoc()):
                    $lvl = strtoupper($log['event_level'] ?? 'INFO');
                    $badgeStyle = match($lvl) {
                        'CRITICAL' => 'background:#FEE2E2;color:#B91C1C;font-weight:700;',
                        'WARNING'  => 'background:#FEF3C7;color:#B45309;font-weight:600;',
                        default    => 'background:#E0F2FE;color:#0369A1;'
                    };
                ?>
                <tr>
                    <td style="color:var(--text-muted);font-size:12.5px;white-space:nowrap;"><?php echo date('M j, Y g:i a', strtotime($log['created_at'])); ?></td>
                    <td><span style="display:inline-block;padding:3px 8px;border-radius:6px;font-size:11px;<?php echo $badgeStyle; ?>"><?php echo htmlspecialchars($lvl); ?></span></td>
                    <td style="font-weight:600;color:#0F172A;"><?php echo htmlspecialchars($log['event_type']); ?></td>
                    <td><?php echo htmlspecialchars($log['username'] ?: 'anonymous'); ?></td>
                    <td style="font-size:13px;max-width:320px;word-break:break-word;"><?php echo htmlspecialchars($log['description'] ?? '—'); ?></td>
                    <td style="color:var(--text-muted);font-size:12.5px;"><?php echo htmlspecialchars($log['ip_address'] ?? '—'); ?></td>
                </tr>
                <?php endwhile; endif; ?>
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
                <?php if ($logs->num_rows === 0): ?>
                <tr><td colspan="5"><div class="empty-state"><svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg><p>No activity logs found.</p></div></td></tr>
                <?php else: ?>
                <?php while ($log = $logs->fetch_assoc()):
                    $dot = 'default';
                    if (stripos($log['action'], 'login')   !== false) $dot = 'login';
                    if (stripos($log['action'], 'booking') !== false) $dot = 'booking';
                    if (stripos($log['action'], 'payment') !== false) $dot = 'payment';
                    $dotColors = ['login'=>'#10B981','booking'=>'#3B82F6','payment'=>'#F59E0B','default'=>'#94A3B8'];
                ?>
                <tr>
                    <td style="color:var(--text-muted);font-size:12.5px;white-space:nowrap;"><?php echo date('M j, Y g:i a', strtotime($log['created_at'])); ?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div style="width:7px;height:7px;border-radius:50%;background:<?php echo $dotColors[$dot]; ?>;flex-shrink:0;"></div>
                            <span style="font-weight:600;"><?php echo htmlspecialchars($log['admin_username']); ?></span>
                        </div>
                    </td>
                    <td style="font-weight:500;"><?php echo htmlspecialchars($log['action']); ?></td>
                    <td style="color:var(--text-muted);font-size:13px;max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo htmlspecialchars($log['details'] ?? ''); ?>"><?php echo htmlspecialchars($log['details'] ?? '—'); ?></td>
                    <td style="color:var(--text-muted);font-size:12.5px;"><?php echo htmlspecialchars($log['ip_address'] ?? '—'); ?></td>
                </tr>
                <?php endwhile; endif; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page_num > 1): ?><a href="?tab=<?php echo urlencode($tab); ?>&p=<?php echo $page_num-1; ?>&user=<?php echo urlencode($filter_user); ?>&action_type=<?php echo urlencode($filter_action); ?>&date=<?php echo urlencode($filter_date); ?>">← Prev</a><?php endif; ?>
                <?php for ($i = max(1,$page_num-2); $i <= min($total_pages,$page_num+2); $i++): ?>
                    <?php if ($i === $page_num): ?><span class="current"><?php echo $i; ?></span>
                    <?php else: ?><a href="?tab=<?php echo urlencode($tab); ?>&p=<?php echo $i; ?>&user=<?php echo urlencode($filter_user); ?>&action_type=<?php echo urlencode($filter_action); ?>&date=<?php echo urlencode($filter_date); ?>"><?php echo $i; ?></a><?php endif; ?>
                <?php endfor; ?>
                <?php if ($page_num < $total_pages): ?><a href="?tab=<?php echo urlencode($tab); ?>&p=<?php echo $page_num+1; ?>&user=<?php echo urlencode($filter_user); ?>&action_type=<?php echo urlencode($filter_action); ?>&date=<?php echo urlencode($filter_date); ?>">Next &rarr;</a><?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </main>
<script src="assets/js/sidebar-toggle.js"></script>
</body>
</html>
