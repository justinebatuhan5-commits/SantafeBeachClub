<?php
/**
 * serve_file.php — Authenticated Private File Server
 *
 * Serves uploaded files (receipts, avatars) that are stored OUTSIDE the web
 * root in the /storage/ directory, which has Require all denied in .htaccess.
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
// auth_check.php exits with 401 if the user is not authenticated

$file = trim($_GET['file'] ?? '');

// ── 1. Reject empty requests ────────────────────────────────────────────────
if (empty($file)) {
    http_response_code(400);
    header('Content-Type: text/plain');
    exit('Bad Request: missing file parameter');
}

// ── 2. Strict filename pattern — only allow known subdirs + safe filenames ──
// Format: receipts/rcpt_<hex>.ext  OR  avatars/avatar_<hex>.ext
// No slashes, no dots, no special chars beyond underscore and hyphen in name.
if (!preg_match('#^(receipts|avatars)/[a-zA-Z0-9_\-]+\.[a-zA-Z]{3,4}$#', $file)) {
    http_response_code(403);
    header('Content-Type: text/plain');
    exit('Forbidden: invalid file path format');
}

// ── 3. Resolve absolute path and verify it is inside storage/ ───────────────
$storageBase = realpath(__DIR__ . '/../../storage');
$filePath    = realpath($storageBase . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file));

if ($storageBase === false || $filePath === false) {
    http_response_code(404);
    header('Content-Type: text/plain');
    exit('Not Found');
}

// Prevent path traversal: resolved path MUST start with storageBase
if (strncmp($filePath, $storageBase, strlen($storageBase)) !== 0) {
    http_response_code(403);
    header('Content-Type: text/plain');
    exit('Forbidden: path traversal detected');
}

if (!is_file($filePath)) {
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
    http_response_code(403);
    header('Content-Type: text/plain');
    exit('Forbidden: invalid content type');
}

// ── 5. Serve the file with strict security headers ───────────────────────────
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: private, no-store, no-cache');
header('X-Content-Type-Options: nosniff');
header('Content-Security-Policy: default-src \'none\'');

readfile($filePath);
exit;
