<?php
/**
 * rate_limiter.php — API & Action Rate Limiter
 * Limits requests based on client IP and target action to protect against brute-force and DoS.
 */

require_once __DIR__ . '/../config/db.php';

class RateLimiter {
    /**
     * Check if client exceeded limit for an action
     * @param mysqli $conn
     * @param string $action (e.g. 'login', 'booking', 'api')
     * @param int $maxAttempts (e.g. 5)
     * @param int $decaySeconds (e.g. 900 for 15 mins)
     * @return array ['allowed' => bool, 'remaining' => int, 'retry_after' => int]
     */
    public static function check(mysqli $conn, string $action, int $maxAttempts = 60, int $decaySeconds = 60): array {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $now = time();
        $windowStart = $now - $decaySeconds;

        // Clean expired rate limit records periodically
        if (rand(1, 100) === 1) {
            $stmt = $conn->prepare("DELETE FROM rate_limits WHERE created_at < FROM_UNIXTIME(?)");
            if ($stmt) {
                $stmt->bind_param("i", $windowStart);
                $stmt->execute();
                $stmt->close();
            }
        }

        // Count attempts in window
        $stmt = $conn->prepare("
            SELECT COUNT(*) AS attempts 
            FROM rate_limits 
            WHERE ip_address = ? AND action = ? AND created_at >= FROM_UNIXTIME(?)
        ");
        
        $attempts = 0;
        if ($stmt) {
            $stmt->bind_param("ssi", $ip, $action, $windowStart);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $attempts = (int) ($res['attempts'] ?? 0);
            $stmt->close();
        }

        if ($attempts >= $maxAttempts) {
            // Find oldest attempt in window to calculate retry_after
            $stmt = $conn->prepare("
                SELECT UNIX_TIMESTAMP(created_at) AS oldest 
                FROM rate_limits 
                WHERE ip_address = ? AND action = ? AND created_at >= FROM_UNIXTIME(?)
                ORDER BY created_at ASC LIMIT 1
            ");
            $retryAfter = $decaySeconds;
            if ($stmt) {
                $stmt->bind_param("ssi", $ip, $action, $windowStart);
                $stmt->execute();
                $res = $stmt->get_result()->fetch_assoc();
                if ($res && isset($res['oldest'])) {
                    $retryAfter = max(1, ($res['oldest'] + $decaySeconds) - $now);
                }
                $stmt->close();
            }

            return [
                'allowed' => false,
                'remaining' => 0,
                'retry_after' => $retryAfter
            ];
        }

        return [
            'allowed' => true,
            'remaining' => max(0, $maxAttempts - $attempts),
            'retry_after' => 0
        ];
    }

    /**
     * Hit / record an attempt for rate limiting
     */
    public static function hit(mysqli $conn, string $action): void {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $conn->prepare("INSERT INTO rate_limits (ip_address, action, created_at) VALUES (?, ?, NOW())");
        if ($stmt) {
            $stmt->bind_param("ss", $ip, $action);
            $stmt->execute();
            $stmt->close();
        }
    }

    /**
     * Enforce rate limit on an endpoint, terminating with 429 if exceeded
     */
    public static function enforce(mysqli $conn, string $action, int $maxAttempts = 60, int $decaySeconds = 60): void {
        $status = self::check($conn, $action, $maxAttempts, $decaySeconds);
        if (!$status['allowed']) {
            http_response_code(429);
            header("Retry-After: " . $status['retry_after']);
            
            $isJson = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
                      (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

            if ($isJson) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'error' => "Rate limit exceeded. Please try again in {$status['retry_after']} seconds.",
                    'retry_after' => $status['retry_after']
                ]);
            } else {
                echo "<!DOCTYPE html><html><head><title>429 Too Many Requests</title><style>body{font-family:sans-serif;text-align:center;padding:50px;background:#f8f9fa;color:#333;}.box{max-width:480px;margin:0 auto;background:#fff;padding:30px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.1);}h1{color:#e53e3e;}</style></head><body><div class=\"box\"><h1>Too Many Requests</h1><p>You have made too many requests. Please wait {$status['retry_after']} seconds before trying again.</p></div></body></html>";
            }
            exit;
        }
        self::hit($conn, $action);
    }

    // ── Account-Level Lockout ─────────────────────────────────────────────────

    /**
     * Check if an account is currently locked out.
     * Call this BEFORE checking the password.
     *
     * @param mysqli $conn
     * @param int    $adminId
     * @return array ['locked' => bool, 'seconds_remaining' => int]
     */
    public static function checkAccountLockout(mysqli $conn, int $adminId): array {
        $stmt = $conn->prepare(
            "SELECT failed_login_count, locked_until FROM admins WHERE id = ? LIMIT 1"
        );
        if (!$stmt) {
            return ['locked' => false, 'seconds_remaining' => 0];
        }
        $stmt->bind_param('i', $adminId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row || empty($row['locked_until'])) {
            return ['locked' => false, 'seconds_remaining' => 0];
        }

        $lockedUntil = strtotime($row['locked_until']);
        $now         = time();

        if ($lockedUntil > $now) {
            return [
                'locked'            => true,
                'seconds_remaining' => (int)($lockedUntil - $now),
            ];
        }

        return ['locked' => false, 'seconds_remaining' => 0];
    }

    /**
     * Record a failed login attempt for an account.
     * Locks the account for $lockoutMinutes after $maxAttempts failures.
     *
     * @param mysqli $conn
     * @param int    $adminId
     * @param int    $maxAttempts   Default 5 consecutive failures
     * @param int    $lockoutMinutes Default 15 minutes
     */
    public static function recordFailedLogin(
        mysqli $conn,
        int $adminId,
        int $maxAttempts = 5,
        int $lockoutMinutes = 15
    ): void {
        // Increment counter
        $stmt = $conn->prepare(
            "UPDATE admins SET failed_login_count = failed_login_count + 1 WHERE id = ?"
        );
        if ($stmt) {
            $stmt->bind_param('i', $adminId);
            $stmt->execute();
            $stmt->close();
        }

        // Check new count and lock if threshold reached
        $stmt = $conn->prepare(
            "SELECT failed_login_count FROM admins WHERE id = ? LIMIT 1"
        );
        if (!$stmt) return;
        $stmt->bind_param('i', $adminId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row && (int)$row['failed_login_count'] >= $maxAttempts) {
            $lockUntil = date('Y-m-d H:i:s', time() + ($lockoutMinutes * 60));
            $stmt = $conn->prepare(
                "UPDATE admins SET locked_until = ? WHERE id = ?"
            );
            if ($stmt) {
                $stmt->bind_param('si', $lockUntil, $adminId);
                $stmt->execute();
                $stmt->close();
            }

            // Log critical security event to trigger immediate email alert
            require_once __DIR__ . '/security_logger.php';
            $username = 'Account ID #' . $adminId;
            // Fetch username if possible
            $uStmt = $conn->prepare("SELECT username FROM admins WHERE id = ? LIMIT 1");
            if ($uStmt) {
                $uStmt->bind_param('i', $adminId);
                $uStmt->execute();
                $uRow = $uStmt->get_result()->fetch_assoc();
                if ($uRow && !empty($uRow['username'])) {
                    $username = $uRow['username'];
                }
                $uStmt->close();
            }
            SecurityLogger::log(
                $conn,
                'BRUTE_FORCE_LOCKOUT',
                "Account {$username} locked for {$lockoutMinutes} minutes after {$maxAttempts} consecutive failed password attempts.",
                SecurityLogger::LEVEL_CRITICAL,
                $username
            );
        }
    }

    /**
     * Clear failed login counter after a successful login.
     *
     * @param mysqli $conn
     * @param int    $adminId
     */
    public static function clearFailedLogins(mysqli $conn, int $adminId): void {
        $stmt = $conn->prepare(
            "UPDATE admins SET failed_login_count = 0, locked_until = NULL WHERE id = ?"
        );
        if ($stmt) {
            $stmt->bind_param('i', $adminId);
            $stmt->execute();
            $stmt->close();
        }
    }
}
?>
