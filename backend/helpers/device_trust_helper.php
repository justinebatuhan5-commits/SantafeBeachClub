<?php
declare(strict_types=1);

/**
 * device_trust_helper.php
 * Handles 24-hour device trust (MFA bypass on verified browser/device).
 */

define('DEVICE_TRUST_COOKIE_NAME', 'sf_trusted_device');
define('DEVICE_TRUST_TTL_SECONDS', 86400); // 24 hours

/**
 * Check if the current client device/browser is trusted for this user.
 * Validates the cookie, database token hash, expiration, and user matching.
 */
function is_device_trusted(int $userId, string $userType, mysqli $conn): bool {
    $rawToken = $_COOKIE[DEVICE_TRUST_COOKIE_NAME] ?? '';
    if (empty($rawToken) || strlen($rawToken) < 32) {
        return false;
    }

    $tokenHash = hash('sha256', $rawToken);

    $stmt = $conn->prepare(
        "SELECT id, expires_at FROM admin_device_trust
         WHERE user_id = ? AND user_type = ? AND token_hash = ? AND revoked_at IS NULL AND expires_at > NOW()
         LIMIT 1"
    );
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('iss', $userId, $userType, $tokenHash);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return !empty($row);
}

/**
 * Issue a 24-hour trusted device token and set an HTTP-only secure cookie.
 */
function remember_trusted_device(int $userId, string $userType, mysqli $conn): void {
    // Generate cryptographically secure 64-char token
    $rawToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);

    $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown device'), 0, 500);
    $expiresAt = date('Y-m-d H:i:s', time() + DEVICE_TRUST_TTL_SECONDS);

    $stmt = $conn->prepare(
        "INSERT INTO admin_device_trust (user_id, user_type, token_hash, ip_address, user_agent, expires_at)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    if ($stmt) {
        $stmt->bind_param('isssss', $userId, $userType, $tokenHash, $ip, $ua, $expiresAt);
        $stmt->execute();
        $stmt->close();
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
               || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

    // Set secure HTTP-only cookie valid for 24 hours
    setcookie(
        DEVICE_TRUST_COOKIE_NAME,
        $rawToken,
        [
            'expires'  => time() + DEVICE_TRUST_TTL_SECONDS,
            'path'     => '/',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax'
        ]
    );
}

/**
 * Revoke the current device trust token (e.g., on explicit security reset or logout if desired).
 */
function revoke_trusted_device(mysqli $conn): void {
    $rawToken = $_COOKIE[DEVICE_TRUST_COOKIE_NAME] ?? '';
    if (!empty($rawToken)) {
        $tokenHash = hash('sha256', $rawToken);
        $stmt = $conn->prepare("UPDATE admin_device_trust SET revoked_at = NOW() WHERE token_hash = ?");
        if ($stmt) {
            $stmt->bind_param('s', $tokenHash);
            $stmt->execute();
            $stmt->close();
        }
    }

    setcookie(DEVICE_TRUST_COOKIE_NAME, '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}
