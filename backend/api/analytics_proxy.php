<?php
/**
 * analytics_proxy.php
 * PHP → Python Analytics Proxy
 *
 * The frontend calls this PHP file for all analytics requests.
 * It forwards the request to the Python Flask service running on port 5000 with the required API Key,
 * returns the Python response as JSON, and falls back to native PHP logic if Python is offline.
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/api_auth_helper.php';
require_once __DIR__ . '/../helpers/rate_limiter.php';

require_api_auth($conn, 'admin');
RateLimiter::enforce($conn, 'analytics_proxy', 120, 60);

$action = $_GET['action'] ?? '';

// ── Try Python service (local Flask or remote Render) ────────────────────────
if (function_exists('curl_init')) {
    $is_local_dev = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', '::1']);

    $python_url = null;

    if ($is_local_dev) {
        // On localhost: ONLY use local Flask if it's actually running on port 5000.
        // Do NOT fall back to remote Render — it cold-starts in 30+ seconds and blocks the dashboard.
        $local_test = @fsockopen('127.0.0.1', 5000, $errno, $errstr, 0.2);
        if ($local_test) {
            fclose($local_test);
            $python_url = "http://127.0.0.1:5000/api/" . urlencode($action);
        }
        // If local Flask is not running, $python_url stays null → skip to PHP fallback instantly.
    } else {
        // On production / non-local: call Render service.
        $python_url = "https://santafe-beachclub-analytics.onrender.com/api/" . urlencode($action);
    }

    if ($python_url !== null) {
        $ch = curl_init($python_url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'X-API-Key: santafe-super-secret-key-2026'
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FAILONERROR    => false,
        ]);
        $python_response = curl_exec($ch);
        $http_code       = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // If Python responded successfully (200 OK), return its output directly
        if ($python_response !== false && $http_code === 200) {
            echo $python_response;
            exit;
        }
    }
}

// ── Fallback: Native PHP analytics (if Python service is not running) ─────────
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/room_status_helper.php';

// Delegate to PHP analytics API
require __DIR__ . '/analytics_api.php';

