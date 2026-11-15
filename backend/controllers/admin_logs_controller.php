<?php
require_once __DIR__ . '/../helpers/rbac_helper.php';
require_once __DIR__ . '/../helpers/security_logger.php';

function sfbc_logs_build_filter_where(string $tab, string $filterUser, string $filterAction, string $filterDate): array
{
    $whereParts = [];
    $types      = '';
    $params     = [];

    $userCol   = ($tab === 'security') ? 'username' : 'admin_username';
    $actionCol = ($tab === 'security') ? 'event_type' : 'action';

    if ($filterUser !== '') {
        $whereParts[] = "{$userCol} LIKE ?";
        $types       .= 's';
        $params[]     = '%' . $filterUser . '%';
    }
    if ($filterAction !== '') {
        $whereParts[] = "{$actionCol} LIKE ?";
        $types       .= 's';
        $params[]     = '%' . $filterAction . '%';
    }
    if ($filterDate !== '') {
        $whereParts[] = 'DATE(created_at) = ?';
        $types       .= 's';
        $params[]     = $filterDate;
    }

    return [
        'whereClause' => !empty($whereParts) ? 'WHERE ' . implode(' AND ', $whereParts) : '',
        'types'       => $types,
        'params'      => $params,
    ];
}

function sfbc_logs_export_csv(mysqli $conn, string $tab, string $filterUser, string $filterAction, string $filterDate): void
{
    $filename = ($tab === 'security' ? 'security_logs_' : 'activity_logs_') . date('Y-m-d_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    $filter      = sfbc_logs_build_filter_where($tab, $filterUser, $filterAction, $filterDate);
    $whereClause = $filter['whereClause'];
    $types       = $filter['types'];
    $params      = $filter['params'];

    if ($tab === 'security') {
        fputcsv($output, ['ID', 'Timestamp', 'Level', 'Event Type', 'Username', 'IP Address', 'User Agent', 'Request URI', 'Description']);
        $sql = "SELECT id, created_at, event_level, event_type, username, ip_address, user_agent, request_uri, description FROM security_logs {$whereClause} ORDER BY id DESC";
    } else {
        fputcsv($output, ['ID', 'Timestamp', 'Admin Username', 'Action', 'Details', 'IP Address']);
        $sql = "SELECT id, created_at, admin_username, action, details, ip_address FROM activity_logs {$whereClause} ORDER BY id DESC";
    }

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        if ($tab === 'security') {
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
        } else {
            fputcsv($output, [
                $row['id'],
                $row['created_at'],
                $row['admin_username'],
                $row['action'],
                $row['details'],
                $row['ip_address'],
            ]);
        }
    }

    $stmt->close();
    fclose($output);
    exit;
}

/**
 * Handles log CSV exports, log purge POST actions, and paginated activity/security log queries.
 */
function sfbc_handle_admin_logs_page(mysqli $conn): array
{
    $admin        = (string)$_SESSION['admin_username'];
    $tab          = ($_GET['tab'] ?? 'activity') === 'security' ? 'security' : 'activity';
    $filterUser   = trim((string)($_GET['user'] ?? ''));
    $filterAction = trim((string)($_GET['action_type'] ?? ''));
    $filterDate   = trim((string)($_GET['date'] ?? ''));
    $doExport     = ($_GET['export'] ?? '') === 'csv';

    if ($doExport) {
        sfbc_logs_export_csv($conn, $tab, $filterUser, $filterAction, $filterDate);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf_token();
        RBAC::requireRole('admin');
        $action = $_POST['action'] ?? '';

        if ($action === 'clear_logs') {
            $conn->query('DELETE FROM activity_logs');
            log_activity($conn, $admin, 'Logs Cleared', 'Activity log table was cleared');
            SecurityLogger::log($conn, 'LOGS_CLEARED', 'Activity log table was cleared by admin', SecurityLogger::LEVEL_WARNING, $admin);
            header('Location: admin_logs?tab=' . $tab);
            exit;
        }

        if ($action === 'clear_security_logs') {
            $conn->query('DELETE FROM security_logs');
            log_activity($conn, $admin, 'Security Logs Cleared', 'Security logs were purged');
            SecurityLogger::log($conn, 'SECURITY_LOGS_CLEARED', 'Security audit logs were cleared by admin', SecurityLogger::LEVEL_CRITICAL, $admin);
            header('Location: admin_logs?tab=security');
            exit;
        }
    }

    $perPage = 30;
    $pageNum = max(1, (int)($_GET['p'] ?? 1));
    $offset  = ($pageNum - 1) * $perPage;

    $filter      = sfbc_logs_build_filter_where($tab, $filterUser, $filterAction, $filterDate);
    $whereClause = $filter['whereClause'];
    $types       = $filter['types'];
    $params      = $filter['params'];

    $tableName = ($tab === 'security') ? 'security_logs' : 'activity_logs';
    $countSql  = "SELECT COUNT(*) AS c FROM {$tableName} {$whereClause}";
    $countStmt = $conn->prepare($countSql);
    if (!empty($params)) {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $totalRows = (int)$countStmt->get_result()->fetch_assoc()['c'];
    $countStmt->close();

    $totalPages = max(1, (int)ceil($totalRows / $perPage));

    if ($tab === 'security') {
        $dataSql = "SELECT id, event_type, event_level, username, ip_address, user_agent, description, created_at FROM security_logs {$whereClause} ORDER BY id DESC LIMIT ? OFFSET ?";
    } else {
        $dataSql = "SELECT admin_username, action, details, ip_address, created_at FROM activity_logs {$whereClause} ORDER BY id DESC LIMIT ? OFFSET ?";
    }

    $dataStmt   = $conn->prepare($dataSql);
    $dataTypes  = $types . 'ii';
    $dataParams = array_merge($params, [$perPage, $offset]);
    $dataStmt->bind_param($dataTypes, ...$dataParams);
    $dataStmt->execute();
    $res      = $dataStmt->get_result();
    $logRows  = [];
    while ($row = $res->fetch_assoc()) {
        $logRows[] = $row;
    }
    $dataStmt->close();

    $frontendDir = __DIR__ . '/../../frontend';

    return [
        'tab'          => $tab,
        'filterUser'   => $filterUser,
        'filterAction' => $filterAction,
        'filterDate'   => $filterDate,
        'pageNum'      => $pageNum,
        'totalRows'    => $totalRows,
        'totalPages'   => $totalPages,
        'logRows'      => $logRows,
        'csrfToken'    => get_csrf_token(),
        'csrfField'    => csrf_field(),
        'versions'     => [
            'adminCss' => filemtime($frontendDir . '/assets/css/admin.css'),
            'pageJs'   => file_exists($frontendDir . '/assets/js/pages/admin-logs.js')
                ? filemtime($frontendDir . '/assets/js/pages/admin-logs.js')
                : 1,
        ],
    ];
}
