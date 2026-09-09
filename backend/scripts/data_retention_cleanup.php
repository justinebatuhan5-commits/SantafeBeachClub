<?php
/**
 * data_retention_cleanup.php — Automated Data Retention & Privacy Cleanup
 *
 * Implements Section 2.3 & 2.4 of the Data Security & Privacy Checklist:
 * - Purges expired guest OTPs and admin MFA OTPs.
 * - Purges expired password reset tokens.
 * - Cleans old rate-limit attempts (> 7 days).
 * - Archives/prunes system & security audit logs older than 90 days.
 * - Enforces privacy retention rules specified in Terms & Privacy Policy (RA 10173).
 *
 * Usage:
 *   CLI / Cron: php backend/scripts/data_retention_cleanup.php
 *   Web/Task: GET /backend/scripts/data_retention_cleanup.php?key=YOUR_CRON_SECRET (optional token check)
 */

if (php_sapi_name() !== 'cli') {
    // Basic protection if called via HTTP
    $expectedKey = getenv('CRON_SECRET_KEY') ?: 'sfbc_retention_cleanup_secure_cron';
    $providedKey = $_GET['key'] ?? '';
    if (!hash_equals($expectedKey, $providedKey)) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/security_logger.php';

$stats = [
    'expired_guest_otps'     => 0,
    'expired_admin_otps'     => 0,
    'expired_pwd_resets'     => 0,
    'old_rate_limits'        => 0,
    'old_security_logs'      => 0,
    'old_activity_logs'      => 0,
];

try {
    // 1. Delete used or expired guest OTPs (> 24 hours ago)
    $stmt = $conn->prepare("DELETE FROM guest_otps WHERE used = 1 OR expires_at < (NOW() - INTERVAL 1 DAY)");
    if ($stmt) {
        $stmt->execute();
        $stats['expired_guest_otps'] = $stmt->affected_rows;
        $stmt->close();
    }

    // 2. Delete used or expired admin MFA OTPs (> 24 hours ago)
    $stmt = $conn->prepare("DELETE FROM admin_otps WHERE used = 1 OR expires_at < (NOW() - INTERVAL 1 DAY)");
    if ($stmt) {
        $stmt->execute();
        $stats['expired_admin_otps'] = $stmt->affected_rows;
        $stmt->close();
    }

    // 3. Delete used or expired password reset tokens (> 24 hours ago)
    $checkTable = $conn->query("SHOW TABLES LIKE 'password_resets'");
    if ($checkTable && $checkTable->num_rows > 0) {
        $stmt = $conn->prepare("DELETE FROM password_resets WHERE used = 1 OR expires_at < (NOW() - INTERVAL 1 DAY)");
        if ($stmt) {
            $stmt->execute();
            $stats['expired_pwd_resets'] = $stmt->affected_rows;
            $stmt->close();
        }
    }

    // 4. Purge old rate limit logs (> 7 days)
    $checkRateTable = $conn->query("SHOW TABLES LIKE 'rate_limits'");
    if ($checkRateTable && $checkRateTable->num_rows > 0) {
        $stmt = $conn->prepare("DELETE FROM rate_limits WHERE created_at < (NOW() - INTERVAL 7 DAY)");
        if ($stmt) {
            $stmt->execute();
            $stats['old_rate_limits'] = $stmt->affected_rows;
            $stmt->close();
        }
    }

    // 5. Purge old security logs (> 90 days retention per policy)
    $checkSecTable = $conn->query("SHOW TABLES LIKE 'security_logs'");
    if ($checkSecTable && $checkSecTable->num_rows > 0) {
        $stmt = $conn->prepare("DELETE FROM security_logs WHERE created_at < (NOW() - INTERVAL 90 DAY)");
        if ($stmt) {
            $stmt->execute();
            $stats['old_security_logs'] = $stmt->affected_rows;
            $stmt->close();
        }
    }

    // 6. Purge old activity logs (> 180 days)
    $checkActTable = $conn->query("SHOW TABLES LIKE 'activity_logs'");
    if ($checkActTable && $checkActTable->num_rows > 0) {
        $stmt = $conn->prepare("DELETE FROM activity_logs WHERE created_at < (NOW() - INTERVAL 180 DAY)");
        if ($stmt) {
            $stmt->execute();
            $stats['old_activity_logs'] = $stmt->affected_rows;
            $stmt->close();
        }
    }

    $logSummary = sprintf(
        "Data retention cleanup completed: GuestOTPs=%d, AdminOTPs=%d, PwdResets=%d, RateLimits=%d, SecLogs=%d, ActLogs=%d",
        $stats['expired_guest_otps'],
        $stats['expired_admin_otps'],
        $stats['expired_pwd_resets'],
        $stats['old_rate_limits'],
        $stats['old_security_logs'],
        $stats['old_activity_logs']
    );

    if (function_exists('security_log')) {
        security_log('DATA_RETENTION_CLEANUP', 'INFO', $logSummary);
    }

    $response = [
        'success'   => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'summary'   => $logSummary,
        'stats'     => $stats,
    ];

    if (php_sapi_name() === 'cli') {
        echo "[" . date('Y-m-d H:i:s') . "] ✅ " . $logSummary . PHP_EOL;
    } else {
        header('Content-Type: application/json');
        echo json_encode($response);
    }

} catch (Exception $e) {
    $errMsg = "Data retention cleanup error: " . $e->getMessage();
    error_log($errMsg);
    if (php_sapi_name() === 'cli') {
        echo "[" . date('Y-m-d H:i:s') . "] ❌ " . $errMsg . PHP_EOL;
        exit(1);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $errMsg]);
    }
}
