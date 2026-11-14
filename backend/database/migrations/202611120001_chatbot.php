<?php
declare(strict_types=1);

return static function (mysqli $db): void {
    // 1. Chatbot conversation sessions (server-side history & handoff tracking)
    $db->query("CREATE TABLE IF NOT EXISTS chatbot_conversations (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        session_token CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
        language VARCHAR(30) NOT NULL DEFAULT 'auto',
        booking_id INT DEFAULT NULL,
        ip_hash CHAR(64) DEFAULT NULL,
        handed_off TINYINT(1) NOT NULL DEFAULT 0,
        inquiry_id INT DEFAULT NULL,
        booking_link_clicks INT UNSIGNED NOT NULL DEFAULT 0,
        started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_message_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_chatbot_conv_started (started_at),
        INDEX idx_chatbot_conv_handoff (handed_off, last_message_at),
        INDEX idx_chatbot_conv_booking (booking_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Stores guest AI concierge conversation sessions'");

    // 2. Chatbot individual turns (user, model, tool calls, latency, and feedback)
    $db->query("CREATE TABLE IF NOT EXISTS chatbot_messages (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        conversation_id BIGINT UNSIGNED NOT NULL,
        role VARCHAR(20) NOT NULL COMMENT 'user, model, or tool',
        content TEXT NOT NULL,
        tool_name VARCHAR(80) DEFAULT NULL,
        intent VARCHAR(60) DEFAULT NULL,
        provider VARCHAR(40) DEFAULT NULL,
        latency_ms INT UNSIGNED NOT NULL DEFAULT 0,
        tokens INT UNSIGNED NOT NULL DEFAULT 0,
        feedback TINYINT NOT NULL DEFAULT 0 COMMENT '-1 unhelpful, 0 unrated, 1 helpful',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_chatbot_msg_conv (conversation_id, id),
        INDEX idx_chatbot_msg_created (created_at),
        INDEX idx_chatbot_msg_intent (intent)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Stores messages, tool invocations, and guest feedback'");

    // 3. Admin-editable multilingual knowledge base for grounded FAQ answers
    $db->query("CREATE TABLE IF NOT EXISTS chatbot_knowledge (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        category VARCHAR(60) NOT NULL DEFAULT 'general',
        question VARCHAR(300) NOT NULL,
        answer TEXT NOT NULL,
        language VARCHAR(30) NOT NULL DEFAULT 'English',
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        updated_by VARCHAR(100) NOT NULL DEFAULT 'system',
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_chatbot_kb_lang_q (language, question(190)),
        INDEX idx_chatbot_kb_active (is_active, category, language)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Admin-managed FAQ knowledge base for the AI Guest Assistant'");

    // 4. Correct test price on standard_room in room_types if still set below ₱500
    $db->query("UPDATE room_types SET price = 2900.00 WHERE name = 'standard_room' AND price < 500.00");

    // 5. Default settings for the AI Concierge
    $defaultSettings = [
        'chatbot_enabled' => '1',
        'chatbot_welcome_message' => 'Hello! Welcome to Santa Fe Beach Club. I can check live room availability, estimate stay prices, verify your booking via OTP, or answer resort questions in English, Tagalog, or Bisaya. How may I assist you today?',
        'chatbot_daily_limit' => '500',
    ];
    $settingStmt = $db->prepare('INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)');
    foreach ($defaultSettings as $key => $value) {
        $settingStmt->bind_param('ss', $key, $value);
        $settingStmt->execute();
    }
    $settingStmt->close();

    // 6. Seed accurate multilingual knowledge entries
    $knowledgeEntries = [
        [
            'amenities',
            'What amenities and facilities are included at Santa Fe Beach Club?',
            'All accommodations include air conditioning, private bathroom, complimentary Wi-Fi, and direct beachfront access along Pantalan shoreline in Santa Fe, Bantayan Island. Select beachfront units feature private balconies or verandas.',
            'English',
        ],
        [
            'directions',
            'How do I get to Santa Fe Beach Club from Cebu City or Santa Fe Port?',
            'From Cebu City, travel north to Hagnaya Port (San Remigio) and take a ferry to Santa Fe Port on Bantayan Island. From Santa Fe Port or Bantayan Airport, take local transport to Pantalan, Santa Fe (Plus Code: R54R+R5W). Contact reception at 0950 522 3146 if you need arrival assistance.',
            'English',
        ],
        [
            'payment',
            'How does the deposit and remaining balance payment work?',
            'A 50% deposit is required upon booking via PayMongo Online Checkout, GCash QR (0950 522 3146), or Bank Deposit. The remaining 50% balance is due 24 hours before your scheduled arrival time (with a 6-hour grace period before manual cancellation review).',
            'English',
        ],
        [
            'cancellation',
            'What is the cancellation and refund policy?',
            'Cancellations made before the advance-notice deadline receive a 100% refund of the paid amount: 48 hours (2 days) before 2:00 PM check-in for 1–3 night stays, 5 days before for 4–6 night stays, and 7 days before for 7+ night stays. After the deadline, bookings are non-refundable.',
            'English',
        ],
        [
            'discounts',
            'Do you offer Senior Citizen or PWD discounts?',
            'Yes. Eligible Senior Citizen (SC) and Person with Disability (PWD) guests can submit a valid government ID through the Discount Booking page. Once verified by reception staff, the statutory discount is applied and rooms are held for up to 24 hours.',
            'English',
        ],
        [
            'house_rules',
            'What are the check-in and check-out times and house rules?',
            'Standard check-in is at 2:00 PM (14:00) and check-out is at 12:00 PM (noon). Please present your booking reference QR code and valid ID upon arrival. Extra guests beyond base room capacity incur a one-time per-stay extra-person fee.',
            'English',
        ],
        [
            'payment',
            'Paano magbayad ng deposit at kailan babayaran ang natitirang balanse?',
            'Kailangan ng 50% deposit sa pag-book gamit ang PayMongo Online, GCash QR (0950 522 3146), o Bank Transfer. Ang natitirang 50% balanse ay dapat bayaran 24 oras bago ang nakatakdang oras ng pagdating.',
            'Tagalog',
        ],
        [
            'cancellation',
            'Ano ang patakaran sa pag-cancel at refund ng booking?',
            '100% refundable ang ibinayad kung magka-cancel bago ang deadline: 48 oras (2 araw) bago ang 2:00 PM check-in para sa 1–3 gabi, 5 araw para sa 4–6 gabi, at 7 araw para sa 7+ gabi. Non-refundable na kapag lumagpas sa deadline.',
            'Tagalog',
        ],
        [
            'payment',
            'Unsaon pagbayad sa deposit ug kanus-a bayaran ang kulang nga balanse?',
            'Kinahanglan og 50% deposit sa pag-book pinaagi sa PayMongo Online, GCash QR (0950 522 3146), o Bank Transfer. Ang nahabilin nga 50% nga balanse bayaran 24 oras sa dili pa ang imong check-in.',
            'Cebuano / Bisaya',
        ],
        [
            'cancellation',
            'Unsa ang polisiya sa pag-cancel ug refund?',
            '100% ma-refund ang nabayad kung mo-cancel sa dili pa ang deadline: 48 oras (2 ka adlaw) sa dili pa ang 2:00 PM check-in para sa 1–3 ka gabii, 5 ka adlaw para sa 4–6 ka gabii, ug 7 ka adlaw para sa 7+ ka gabii.',
            'Cebuano / Bisaya',
        ],
    ];

    $kbStmt = $db->prepare(
        'INSERT IGNORE INTO chatbot_knowledge (category, question, answer, language, is_active, updated_by)
         VALUES (?, ?, ?, ?, 1, \'system\')'
    );
    foreach ($knowledgeEntries as [$cat, $q, $a, $lang]) {
        $kbStmt->bind_param('ssss', $cat, $q, $a, $lang);
        $kbStmt->execute();
    }
    $kbStmt->close();
};
