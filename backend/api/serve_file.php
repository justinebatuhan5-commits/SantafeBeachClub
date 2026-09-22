<?php
$initialOutputLevel = ob_get_level();
ob_start();
ini_set('display_errors', '0');

function private_file_clean_output(int $initialOutputLevel): void
{
    while (ob_get_level() > $initialOutputLevel) ob_end_clean();
}

/**
 * serve_file.php — Authenticated Private File Server
 *
 * Serves uploaded files (receipts, avatars) that are stored OUTSIDE the web
 * root in the configured PRIVATE_STORAGE_DIR directory.
 *
 * Security controls:
 *   - Requires active authenticated session (via auth_check.php)
 *   - Validates the requested filename against a strict allowlist pattern
 *   - Uses realpath() to prevent ALL path-traversal attacks (../../etc)
 *   - Re-validates MIME type via finfo before serving (never trusts extension)
 *   - Sends X-Content-Type-Options: nosniff to prevent MIME sniffing
 *   - Sets Cache-Control: private, no-store to prevent caching of sensitive files
 */

require_once __DIR__ . '/../helpers/auth_check.php';
require_once __DIR__ . '/../helpers/private_storage_helper.php';
// auth_check.php exits with 401 if the user is not authenticated

$file = trim($_GET['file'] ?? '');

// ── 1. Reject empty requests ────────────────────────────────────────────────
if (empty($file)) {
    private_file_clean_output($initialOutputLevel);
    http_response_code(400);
    header('Content-Type: text/plain');
    exit('Bad Request: missing file parameter');
}

// ── 2. Strict filename pattern — only allow known subdirs + safe filenames ──
// Format: receipts/rcpt_<hex>.ext  OR  avatars/avatar_<hex>.ext
// No slashes, no dots, no special chars beyond underscore and hyphen in name.
if (!preg_match('#^(receipts|avatars)/[a-zA-Z0-9_\-]+\.[a-zA-Z]{3,4}$#', $file)) {
    private_file_clean_output($initialOutputLevel);
    http_response_code(403);
    header('Content-Type: text/plain');
    exit('Forbidden: invalid file path format');
}

// Resolve only files within the private category directory, including symlink checks.
try {
    $filePath = private_storage_file($file);
} catch (RuntimeException $e) {
    error_log('serve_file: Private storage unavailable: ' . $e->getMessage());
    private_file_clean_output($initialOutputLevel);
    http_response_code(503);
    header('Content-Type: text/plain');
    exit('Private file storage is unavailable.');
}
if ($filePath === null) {
    error_log('serve_file: File not found for validated key [' . $file . '].');
    private_file_clean_output($initialOutputLevel);
    http_response_code(404);
    header('Content-Type: text/plain');
    exit('Not Found');
}

// ── 4. Re-validate MIME type via magic bytes (never trust extension) ─────────
$finfo    = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $filePath);
finfo_close($finfo);

$allowedMimes = [
    'image/jpeg',
    'image/png',
    'image/webp',
    'image/gif',
    'application/pdf',
];

if (!in_array($mimeType, $allowedMimes, true)) {
    error_log('serve_file: Invalid MIME type [' . $mimeType . '] for key [' . $file . '].');
    private_file_clean_output($initialOutputLevel);
    http_response_code(403);
    header('Content-Type: text/plain');
    exit('Forbidden: invalid content type');
}

// ── 5. Serve the file with strict security headers ───────────────────────────
// Discard whitespace, notices or warnings emitted by included configuration.
// Any leading output corrupts an otherwise valid image/PDF response.
private_file_clean_output($initialOutputLevel);
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: private, no-store, no-cache');
header('X-Content-Type-Options: nosniff');
header('Content-Security-Policy: default-src \'none\'');

if (readfile($filePath) === false) {
    error_log('serve_file: Failed while streaming key [' . $file . '].');
}
exit;
