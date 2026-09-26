<?php
declare(strict_types=1);

return static function (mysqli $db): void {
    $schema = $db->query('SELECT DATABASE()')->fetch_row()[0];
    $columns = [
        'preferred_refund_method' => "VARCHAR(30) NOT NULL DEFAULT 'original_source'",
        'approved_refund_method' => 'VARCHAR(30) DEFAULT NULL',
    ];
    $check = $db->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema=? AND table_name=\'refund_requests\' AND column_name=?');
    foreach ($columns as $column => $definition) {
        $check->bind_param('ss', $schema, $column);
        $check->execute();
        if (!$check->get_result()->fetch_row()) {
            $db->query("ALTER TABLE refund_requests ADD COLUMN `{$column}` {$definition}");
        }
    }
    $check->close();

    $db->query("CREATE TABLE IF NOT EXISTS manual_refund_details (
        refund_request_id INT PRIMARY KEY,
        token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL UNIQUE,
        token_expires_at DATETIME DEFAULT NULL,
        submitted_at DATETIME DEFAULT NULL,
        gcash_account_name VARCHAR(120) DEFAULT NULL,
        gcash_mobile VARCHAR(20) DEFAULT NULL,
        ownership_confirmed TINYINT(1) NOT NULL DEFAULT 0,
        transfer_reference VARCHAR(100) DEFAULT NULL UNIQUE,
        receipt_path VARCHAR(255) DEFAULT NULL,
        processed_by VARCHAR(100) DEFAULT NULL,
        processed_at DATETIME DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_manual_refund_request FOREIGN KEY (refund_request_id) REFERENCES refund_requests(id) ON DELETE CASCADE,
        INDEX idx_manual_refund_expiry (token_expires_at)
    ) ENGINE=InnoDB");
};
