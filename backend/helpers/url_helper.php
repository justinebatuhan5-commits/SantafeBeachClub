<?php
declare(strict_types=1);

/**
 * Resolve the public frontend base URL (no trailing slash).
 * Uses PUBLIC_APP_URL when configured; otherwise detects the active scheme
 * (including reverse-proxy HTTPS headers) and frontend mount path.
 */
function sfbc_public_frontend_url(): string
{
    $configured = rtrim((string) (getenv('PUBLIC_APP_URL') ?: ($_ENV['PUBLIC_APP_URL'] ?? '')), '/');
    if ($configured !== '' && filter_var($configured, FILTER_VALIDATE_URL) !== false) {
        return str_ends_with($configured, '/frontend') ? $configured : $configured . '/frontend';
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? 80) == 443)
        || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    $scheme = $isHttps ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir    = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');

    if (str_contains($dir, '/backend')) {
        $dir = preg_replace('#/backend(?:/.*)?$#', '', $dir) ?? '';
    }
    if ($dir === '.' || $dir === '/') {
        $dir = '';
    }
    $frontendDir = str_ends_with($dir, '/frontend') ? $dir : $dir . '/frontend';

    return $scheme . '://' . $host . $frontendDir;
}
