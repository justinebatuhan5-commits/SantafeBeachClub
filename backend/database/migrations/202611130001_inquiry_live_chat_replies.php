<?php
declare(strict_types=1);

return static function (mysqli $db): void {
    // 1. Track two-way staff replies and live guest follow-ups linked to inquiries and chatbot sessions
    $db->query("CREATE TABLE IF NOT EXISTS inquiry_replies (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        inquiry_id INT NOT NULL,
        conversation_id BIGINT UNSIGNED DEFAULT NULL,
        sender_type VARCHAR(20) NOT NULL DEFAULT 'staff',
        sender_name VARCHAR(100) NOT NULL,
        message TEXT NOT NULL,
        delivered_live TINYINT(1) NOT NULL DEFAULT 0,
        delivered_email TINYINT(1) NOT NULL DEFAULT 0,
        email_error VARCHAR(255) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_inquiry_replies_inq (inquiry_id, id),
        INDEX idx_inquiry_replies_conv (conversation_id, id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Stores staff-to-guest replies (live chat + SMTP email) and live guest follow-ups'");

    // 2. Add live_chat_active flag on chatbot_conversations while keeping handed_off intact for analytics
    $schema = $db->query('SELECT DATABASE()')->fetch_row()[0];
    $chk = $db->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ?');
    if ($chk) {
        $tbl = 'chatbot_conversations';
        $col = 'live_chat_active';
        $chk->bind_param('sss', $schema, $tbl, $col);
        $chk->execute();
        $exists = $chk->get_result()->fetch_row();
        $chk->close();
        if (!$exists) {
            $db->query("ALTER TABLE chatbot_conversations ADD COLUMN live_chat_active TINYINT(1) NOT NULL DEFAULT 0 AFTER handed_off");
        }
    }

    // 3. Activate live mode for open handed-off conversations whose linked inquiry is not yet resolved
    $db->query("
        UPDATE chatbot_conversations c
        INNER JOIN inquiries i ON i.id = c.inquiry_id
        SET c.live_chat_active = 1
        WHERE c.handed_off = 1
          AND (i.status IS NULL OR i.status <> 'Resolved')
    ");
};
