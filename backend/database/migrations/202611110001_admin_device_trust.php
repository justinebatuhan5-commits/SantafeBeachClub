<?php
declare(strict_types=1);

return static function (mysqli $db): void {
    // Table for trusted devices — skips MFA OTP for 24 hours on the same browser
    $db->query("CREATE TABLE IF NOT EXISTS admin_device_trust (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        user_type VARCHAR(20) NOT NULL COMMENT 'admin or receptionist',
        token_hash CHAR(64) NOT NULL COMMENT 'SHA-256 of the random token stored in cookie',
        ip_address VARCHAR(45) DEFAULT NULL,
        user_agent VARCHAR(500) DEFAULT NULL,
        verified_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME NOT NULL,
        revoked_at DATETIME DEFAULT NULL,
        UNIQUE KEY uq_trust_token (token_hash),
        INDEX idx_trust_lookup (user_id, user_type, expires_at, revoked_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Stores 24-hour trusted device tokens for MFA bypass'");
};
