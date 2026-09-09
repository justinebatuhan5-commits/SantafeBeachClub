<?php
/**
 * error_handler.php — Secure Error and Exception Handler
 * Suppresses dangerous internal error traces in production and logs them safely.
 */

// Disable raw display of errors to browser in production to prevent information disclosure
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

// Ensure logs directory exists
$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}
ini_set('log_errors', '1');
ini_set('error_log', $logDir . '/app_errors.log');

/**
 * Custom Exception Handler
 */
function secure_exception_handler(Throwable $e): void {
    $logFile = __DIR__ . '/../logs/app_errors.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $message = sprintf(
        "[%s] [EXCEPTION] [IP: %s] %s in %s:%d\nStack trace:\n%s\n\n",
        $timestamp,
        $ip,
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    );
    @error_log($message, 3, $logFile);

    // If request is AJAX/JSON, return clean JSON
    $isJson = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
              (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) ||
              (isset($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'application/json'));

    if ($isJson) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode([
            'success' => false,
            'error'   => 'Server error: ' . $e->getMessage()
        ]);
        exit;
    }

    if (!headers_sent()) {
        http_response_code(500);
    }
    $errDetail = htmlspecialchars($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Error - Santa Fe Beach Club</title><style>body{font-family:system-ui,-apple-system,sans-serif;background:#F8FAFC;color:#334155;text-align:center;padding:60px 20px;} .box{max-width:600px;margin:0 auto;background:#fff;border-radius:12px;padding:32px;box-shadow:0 4px 20px rgba(0,0,0,0.06);} h1{color:#DC2626;font-size:22px;margin-bottom:12px;} p{font-size:14px;color:#64748B;line-height:1.6;} .detail{background:#FEF2F2;border:1px solid #FCA5A5;color:#991B1B;padding:12px;border-radius:8px;font-family:monospace;font-size:12px;margin:16px 0;text-align:left;word-break:break-all;} a{display:inline-block;margin-top:18px;color:#0284C7;text-decoration:none;font-weight:600;}</style></head><body><div class="box"><h1>Something went wrong</h1><p>We encountered an unexpected error processing your request.</p><div class="detail">' . $errDetail . '</div><a href="javascript:history.back()">← Return to previous page</a></div></body></html>';
    exit;
}

/**
 * Custom Error Handler for PHP Warnings/Notices
 */
function secure_error_handler(int $errno, string $errstr, string $errfile, int $errline): bool {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    $logFile = __DIR__ . '/../logs/app_errors.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $typeStr = match ($errno) {
        E_USER_ERROR => 'USER_ERROR',
        E_USER_WARNING => 'USER_WARNING',
        E_USER_NOTICE => 'USER_NOTICE',
        E_WARNING => 'WARNING',
        E_NOTICE => 'NOTICE',
        default => 'ERROR'
    };
    $message = sprintf("[%s] [%s] [IP: %s] %s in %s:%d\n", $timestamp, $typeStr, $ip, $errstr, $errfile, $errline);
    @error_log($message, 3, $logFile);

    if ($errno === E_USER_ERROR) {
        secure_exception_handler(new ErrorException($errstr, 0, $errno, $errfile, $errline));
    }
    return true;
}

set_exception_handler('secure_exception_handler');
set_error_handler('secure_error_handler');
?>
