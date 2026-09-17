<?php
require_once __DIR__ . '/../helpers/pc_backup_helper.php';
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); header('Allow: POST'); exit; }
$authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
$token = str_starts_with($authorization, 'Bearer ') ? substr($authorization, 7) : '';
try {
    if (!pc_backup_authorized($token, pc_backup_read())) { http_response_code(401); exit('Access denied.'); }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? 0) == 443)
        || (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    if (!$https) { http_response_code(403); exit('HTTPS is required.'); }
    set_time_limit(600);
    $entry = pc_backup_prepare($token, $_SERVER['HTTP_X_BACKUP_REQUEST'] ?? '');
    $path = backup_directory() . '/' . $entry['name'];
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $entry['name'] . '"');
    header('Content-Length: ' . filesize($path));
    header('X-Backup-Name: ' . $entry['name']);
    header('X-Backup-SHA256: ' . $entry['sha256']);
    readfile($path);
} catch (Throwable $e) {
    http_response_code(503);
    header('Retry-After: 300');
    echo 'Backup unavailable. Check the admin backup configuration and retry.';
}
