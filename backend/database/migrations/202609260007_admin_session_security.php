<?php
declare(strict_types=1);

return static function (mysqli $db): void {
    $db->query("CREATE TABLE IF NOT EXISTS admin_sessions (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        session_hash CHAR(64) NOT NULL UNIQUE,
        user_id INT NOT NULL,
        user_type VARCHAR(20) NOT NULL,
        username VARCHAR(150) NOT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        user_agent VARCHAR(500) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        revoked_at DATETIME DEFAULT NULL,
        INDEX idx_admin_sessions_user (user_type, user_id, revoked_at),
        INDEX idx_admin_sessions_seen (last_seen_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
};
