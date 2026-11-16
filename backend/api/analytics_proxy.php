<?php
/**
 * analytics_proxy.php
 * PHP → Python Analytics Proxy
 *
 * The frontend calls this PHP file for all analytics requests.
 * It forwards the request to the Python Flask service running on port 5000 with the required API Key,
 * returns the Python response as JSON, and falls back to native PHP logic unless
 * ANALYTICS_REQUIRE_PYTHON is enabled for connector verification.
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/api_auth_helper.php';
require_once __DIR__ . '/../helpers/rate_limiter.php';

require_api_auth($conn, 'admin');
RateLimiter::enforce($conn, 'analytics_proxy', 120, 60);

$action = $_GET['action'] ?? '';
$forwardParams = $_GET;
unset($forwardParams['action']);
$forwardQueryString = http_build_query($forwardParams);
$forwardSuffix = $forwardQueryString !== '' ? ('?' . $forwardQueryString) : '';
$requirePython = filter_var(getenv('ANALYTICS_REQUIRE_PYTHON') ?: false, FILTER_VALIDATE_BOOLEAN);
$pythonFailure = 'The Python analytics service is not configured or unavailable.';

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
            $python_url = "http://127.0.0.1:5000/api/" . urlencode($action) . $forwardSuffix;
        }
        // If local Flask is not running, $python_url stays null → skip to PHP fallback instantly.
    } else {
        // A remote analytics service is optional. Its URL and credential must be
        // supplied by the deployment environment, never committed to source.
        $analyticsBaseUrl = rtrim((string)(getenv('ANALYTICS_SERVICE_URL') ?: ''), '/');
        $analyticsApiKey = (string)(getenv('ANALYTICS_API_KEY') ?: '');
        if ($analyticsBaseUrl !== '' && $analyticsApiKey !== '') {
            $python_url = $analyticsBaseUrl . '/api/' . urlencode($action) . $forwardSuffix;
        } elseif ($requirePython) {
            $pythonFailure = 'Python analytics is required but its service URL or API key is missing.';
        }
    }

    if ($python_url !== null) {
        $ch = curl_init($python_url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'X-API-Key: ' . (string)(getenv('ANALYTICS_API_KEY') ?: '')
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
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
        $pythonFailure = 'Python analytics did not return a successful response (HTTP ' . (int)$http_code . ').';
    }
}

// During connector verification, never serve PHP results in place of Python.
if ($requirePython) {
    error_log('[analytics_proxy] ' . $pythonFailure);
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => $pythonFailure]);
    exit;
}

// ── Fallback: Native PHP analytics (if Python service is not running) ─────────
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/room_status_helper.php';

// Delegate to PHP analytics API
require __DIR__ . '/analytics_api.php';

