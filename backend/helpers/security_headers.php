<?php
/**
 * security_headers.php — HTTP Security Headers Helper
 * Sets industry standard security headers to prevent XSS, Clickjacking, MIME sniffing, and more.
 */

require_once __DIR__ . '/output_helper.php';

if (!headers_sent()) {
    // Prevent Clickjacking attacks
    header('X-Frame-Options: SAMEORIGIN');

    // Prevent MIME type sniffing
    header('X-Content-Type-Options: nosniff');

    // Control Referrer information
    header('Referrer-Policy: strict-origin-when-cross-origin');

    // Disable obsolete browser XSS filters; use output encoding and CSP instead.
    header('X-XSS-Protection: 0');

    // Enforce modern permissions policy
    header("Permissions-Policy: camera=(self), microphone=(self), geolocation=()");

    // Legacy inline scripts/handlers still require unsafe-inline. Output encoding
    // remains essential until those handlers can migrate to a nonce-based policy.
    $csp = "default-src 'self'; "
         . "script-src 'self' 'unsafe-inline' https://www.google.com/recaptcha/ https://www.gstatic.com/recaptcha/ https://cdn.jsdelivr.net; "
         . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; "
         . "font-src 'self' https://fonts.gstatic.com data:; "
         . "img-src 'self' data: blob: https: https://api.qrserver.com https://images.unsplash.com; "
         . "frame-src 'self' https://www.google.com/recaptcha/ https://recaptcha.google.com/ https://www.google.com/maps/; "
         . "connect-src 'self' https://www.google.com/recaptcha/ https://api.qrserver.com; "
         . "object-src 'none'; "
         . "base-uri 'self'; "
         . "frame-ancestors 'self';";
    header("Content-Security-Policy: " . $csp);

    // HTTP Strict Transport Security (HSTS) - enforce HTTPS when connection is secure
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
             || (($_SERVER['SERVER_PORT'] ?? 80) == 443)
             || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
    if ($isSecure) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    }
}
?>
