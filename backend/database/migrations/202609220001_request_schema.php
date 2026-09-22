<?php
declare(strict_types=1);

return static function (mysqli $db): void {
    $queries = [
        "CREATE TABLE IF NOT EXISTS rooms (id INT AUTO_INCREMENT PRIMARY KEY, room_number VARCHAR(10) NOT NULL UNIQUE, name VARCHAR(100) NOT NULL, type VARCHAR(50) NOT NULL, price_per_night DECIMAL(10,2) NOT NULL, capacity INT NOT NULL, status VARCHAR(20) DEFAULT 'ready') ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS bookings (id INT AUTO_INCREMENT PRIMARY KEY, guest_name VARCHAR(100) NOT NULL, guest_type VARCHAR(50) DEFAULT 'First Visit', room_type_id INT DEFAULT NULL, check_in DATE NOT NULL, check_out DATE NOT NULL, guests_count INT NOT NULL, room_id INT DEFAULT NULL, accommodation_name VARCHAR(100) NOT NULL, eta VARCHAR(10) DEFAULT '14:00', status VARCHAR(20) DEFAULT 'Pending', cancellation_token VARCHAR(64) DEFAULT NULL, checkin_token VARCHAR(64) DEFAULT NULL, cancelled_at DATETIME DEFAULT NULL, prearrival_reminder_sent_at DATETIME DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX(room_id)) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS reviews (id INT AUTO_INCREMENT PRIMARY KEY, booking_id INT DEFAULT NULL, guest_name VARCHAR(100) NOT NULL, guest_location VARCHAR(100) DEFAULT 'Philippines', rating TINYINT UNSIGNED NOT NULL DEFAULT 5, review_text TEXT NOT NULL, is_approved TINYINT(1) DEFAULT 1, created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS promotions (id INT AUTO_INCREMENT PRIMARY KEY, code VARCHAR(50) DEFAULT NULL, title VARCHAR(150) NOT NULL, description TEXT, discount_type VARCHAR(20) DEFAULT 'percent', discount_value DECIMAL(10,2) DEFAULT 0, valid_from DATE, valid_until DATE, is_active TINYINT(1) DEFAULT 1, created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS pricing_rules (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(150) NOT NULL, room_type VARCHAR(50) DEFAULT 'all', rule_type VARCHAR(20) NOT NULL DEFAULT 'weekend', start_date DATE DEFAULT NULL, end_date DATE DEFAULT NULL, days_of_week VARCHAR(30) DEFAULT '5,6,0', adjustment_type VARCHAR(20) DEFAULT 'percent', adjustment_value DECIMAL(10,2) NOT NULL DEFAULT 0.00, is_active TINYINT(1) DEFAULT 1, created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS room_types (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL UNIQUE, total_rooms INT NOT NULL DEFAULT 0, price DECIMAL(10,2) NOT NULL DEFAULT 0.00, image_url TEXT DEFAULT NULL, gallery_images TEXT DEFAULT NULL) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS payments (id INT AUTO_INCREMENT PRIMARY KEY, booking_id INT NOT NULL, guest_name VARCHAR(100) NOT NULL, guest_email VARCHAR(150), amount DECIMAL(10,2) NOT NULL, payment_method VARCHAR(50) NOT NULL, transaction_id VARCHAR(100), status ENUM('pending','verified','rejected','refunded') DEFAULT 'pending', accounting_status VARCHAR(20) DEFAULT 'deferred', amount_tendered DECIMAL(10,2) DEFAULT NULL, change_amount DECIMAL(10,2) DEFAULT NULL, paid_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS payment_action_history (id INT AUTO_INCREMENT PRIMARY KEY, payment_id INT NOT NULL, action VARCHAR(30) NOT NULL, performed_by VARCHAR(100) NOT NULL, details TEXT DEFAULT NULL, performed_at DATETIME DEFAULT CURRENT_TIMESTAMP, INDEX idx_payment_history_payment (payment_id), INDEX idx_payment_history_time (performed_at)) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS refund_requests (id INT AUTO_INCREMENT PRIMARY KEY, booking_id INT NOT NULL, payment_id INT, requested_by VARCHAR(100) NOT NULL, request_type VARCHAR(20) DEFAULT 'guest', status VARCHAR(30) DEFAULT 'requested', reason TEXT, refund_amount DECIMAL(10,2) NOT NULL, refund_fee DECIMAL(10,2) DEFAULT 0.00, net_refund DECIMAL(10,2) NOT NULL, approved_by VARCHAR(100) DEFAULT NULL, approved_at DATETIME DEFAULT NULL, rejection_reason TEXT DEFAULT NULL, paymongo_refund_id VARCHAR(100) DEFAULT NULL, paymongo_status VARCHAR(50) DEFAULT NULL, completed_at DATETIME DEFAULT NULL, requested_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, INDEX idx_booking_status (booking_id,status), INDEX idx_status_time (status,requested_at), INDEX idx_approved_by (approved_by)) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS notifications (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(150) NOT NULL, message TEXT NOT NULL, type VARCHAR(30) DEFAULT 'info', is_read TINYINT(1) DEFAULT 0, booking_id INT DEFAULT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS administrators (id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50) NOT NULL UNIQUE, password VARCHAR(255) NOT NULL, email VARCHAR(150) DEFAULT NULL, profile_photo VARCHAR(255) DEFAULT NULL, failed_login_count INT NOT NULL DEFAULT 0, locked_until DATETIME NULL DEFAULT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS receptionists (id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50) NOT NULL UNIQUE, password VARCHAR(255) NOT NULL, email VARCHAR(150) DEFAULT NULL, profile_photo VARCHAR(255) DEFAULT NULL, failed_login_count INT NOT NULL DEFAULT 0, locked_until DATETIME NULL DEFAULT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS admin_otps (id INT AUTO_INCREMENT PRIMARY KEY, admin_id INT NOT NULL, user_type VARCHAR(20) NOT NULL DEFAULT 'admin', otp_hash VARCHAR(64) NOT NULL, expires_at DATETIME NOT NULL, attempts TINYINT NOT NULL DEFAULT 0, used TINYINT(1) NOT NULL DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_admin_otp_lookup (admin_id,used,expires_at)) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS guest_otps (id INT AUTO_INCREMENT PRIMARY KEY, booking_id INT NOT NULL, otp_hash VARCHAR(64) NOT NULL, expires_at DATETIME NOT NULL, attempts TINYINT NOT NULL DEFAULT 0, used TINYINT(1) NOT NULL DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_guest_otp_lookup (booking_id,used,expires_at)) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS password_resets (id INT AUTO_INCREMENT PRIMARY KEY, admin_id INT NOT NULL, user_type VARCHAR(20) NOT NULL DEFAULT 'admin', token_hash VARCHAR(64) NOT NULL, expires_at DATETIME NOT NULL, used TINYINT(1) NOT NULL DEFAULT 0, ip_address VARCHAR(45) DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_reset_token (token_hash,used,expires_at)) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS activity_logs (id INT AUTO_INCREMENT PRIMARY KEY, admin_username VARCHAR(50) NOT NULL, action VARCHAR(100) NOT NULL, details TEXT, ip_address VARCHAR(45), created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS settings (setting_key VARCHAR(100) PRIMARY KEY, setting_value TEXT NOT NULL, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS gallery (id INT AUTO_INCREMENT PRIMARY KEY, file_name VARCHAR(255) NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS inquiries (id INT AUTO_INCREMENT PRIMARY KEY, guest_name VARCHAR(150) NOT NULL, guest_email VARCHAR(150) NOT NULL, subject VARCHAR(200) NOT NULL, message TEXT NOT NULL, status VARCHAR(20) DEFAULT 'Unread', created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS security_logs (id INT AUTO_INCREMENT PRIMARY KEY, event_type VARCHAR(50) NOT NULL, event_level VARCHAR(20) NOT NULL DEFAULT 'INFO', username VARCHAR(100) DEFAULT 'anonymous', ip_address VARCHAR(45) NOT NULL, user_agent VARCHAR(255) DEFAULT NULL, request_uri VARCHAR(255) DEFAULT NULL, description TEXT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS rate_limits (id INT AUTO_INCREMENT PRIMARY KEY, ip_address VARCHAR(45) NOT NULL, action VARCHAR(50) NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, INDEX idx_ip_action_time (ip_address,action,created_at)) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS booking_discounts (booking_id INT PRIMARY KEY, category VARCHAR(10) NOT NULL, eligible_guest VARCHAR(150) NOT NULL, id_file VARCHAR(100) NULL, token CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE, state VARCHAR(40) NOT NULL DEFAULT 'Awaiting ID verification', original_centavos BIGINT NOT NULL, total_centavos BIGINT NOT NULL, deposit_centavos BIGINT NOT NULL, room_id INT NOT NULL, check_in DATE NOT NULL, check_out DATE NOT NULL, guests_count INT NOT NULL, hold_until DATETIME NOT NULL, note TEXT NULL, reviewed_by VARCHAR(100) NULL, reviewed_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, INDEX(state,hold_until)) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS discount_history (id INT AUTO_INCREMENT PRIMARY KEY, booking_id INT NOT NULL, actor VARCHAR(100) NOT NULL, action VARCHAR(60) NOT NULL, details TEXT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS discount_payments (payment_id INT PRIMARY KEY, booking_id INT NOT NULL, purpose VARCHAR(10) NOT NULL, checkout_url TEXT NULL, reference_key CHAR(64) NULL UNIQUE, INDEX(booking_id)) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS paymongo_checkouts (session_id VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY, payment_id INT NOT NULL UNIQUE, booking_id INT NOT NULL, amount_centavos BIGINT NOT NULL, currency CHAR(3) NOT NULL, provider_payment_id VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NULL UNIQUE) ENGINE=InnoDB",
    ];
    foreach ($queries as $query) $db->query($query);

    // One-time data migration formerly performed during checkout requests.
    $db->query("INSERT IGNORE INTO paymongo_checkouts (session_id,payment_id,booking_id,amount_centavos,currency)
        SELECT p.transaction_id,p.id,p.booking_id,ROUND(p.amount * 100),'PHP' FROM payments p
        WHERE p.payment_method='PayMongo Online' AND p.status='pending' AND p.transaction_id LIKE 'cs\\_%'");

    $columns = [
        'bookings' => ['checkin_token'=>'VARCHAR(64) DEFAULT NULL','cancellation_token'=>'VARCHAR(64) DEFAULT NULL','cancelled_at'=>'DATETIME DEFAULT NULL','guest_email'=>'VARCHAR(150) DEFAULT NULL','payment_method'=>"VARCHAR(50) DEFAULT 'Pay at Check-in'",'room_type_id'=>'INT DEFAULT NULL','checkout_notified_at'=>'DATETIME DEFAULT NULL','payment_deadline'=>'DATETIME DEFAULT NULL','cancellation_reason'=>'VARCHAR(255) DEFAULT NULL','promo_code'=>'VARCHAR(50) DEFAULT NULL','discount_amount'=>'DECIMAL(10,2) DEFAULT 0.00','confirmation_email_sent_at'=>'DATETIME DEFAULT NULL','prearrival_reminder_sent_at'=>'DATETIME DEFAULT NULL','guest_phone'=>'VARCHAR(20) DEFAULT NULL','guest_country'=>'VARCHAR(50) DEFAULT NULL','guest_special_requests'=>'TEXT DEFAULT NULL','guest_notes'=>'TEXT DEFAULT NULL','refund_status'=>'VARCHAR(50) DEFAULT NULL','refund_request_id'=>'INT DEFAULT NULL'],
        'room_types' => ['image_url'=>'TEXT DEFAULT NULL','gallery_images'=>'TEXT DEFAULT NULL'],
        'promotions' => ['code'=>'VARCHAR(50) DEFAULT NULL'],
        'payments' => ['accounting_status'=>"VARCHAR(20) DEFAULT 'deferred'",'amount_tendered'=>'DECIMAL(10,2) DEFAULT NULL','change_amount'=>'DECIMAL(10,2) DEFAULT NULL'],
        'notifications' => ['booking_id'=>'INT DEFAULT NULL'],
        'admin_otps' => ['user_type'=>"VARCHAR(20) NOT NULL DEFAULT 'admin'"],
        'password_resets' => ['user_type'=>"VARCHAR(20) NOT NULL DEFAULT 'admin'"],
    ];
    $schema = $db->query('SELECT DATABASE()')->fetch_row()[0];
    $check = $db->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema=? AND table_name=? AND column_name=?');
    foreach ($columns as $table => $definitions) foreach ($definitions as $column => $definition) {
        $check->bind_param('sss', $schema, $table, $column);
        $check->execute();
        if (!$check->get_result()->fetch_row()) $db->query("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
    }

    $rooms = [
        ['101','Beachview Duplex 101','beachview_duplex',6900.00,2], ['102','Seaview Duplex 102','seaview_duplex',7900.00,2],
        ['103','Beach Villa 103','beach_villa',7900.00,4], ['104','Beach Villa 104','beach_villa',7900.00,4],
        ['105','Beach Villa 105','beach_villa',7900.00,4], ['106','Standard Family Room 106','standard_king',4300.00,4],
        ['203','Standard Family Room 203','standard_king',4300.00,4], ['107','Standard Room 107','standard_room',2900.00,2],
        ['108','Standard Room 108','standard_room',2900.00,2], ['109','Standard Room 109','standard_room',2900.00,2],
        ['110','Standard Room 110','standard_room',2900.00,2],
    ];
    $room = $db->prepare("INSERT IGNORE INTO rooms (room_number,name,type,price_per_night,capacity,status) VALUES (?,?,?,?,?,'ready')");
    foreach ($rooms as [$number,$name,$type,$price,$capacity]) { $room->bind_param('sssdi',$number,$name,$type,$price,$capacity); $room->execute(); }

    // Seed missing derived types without overwriting administrator-managed rows.
    $db->query("INSERT IGNORE INTO room_types (name,total_rooms,price)
        SELECT type,COUNT(*),MIN(price_per_night) FROM rooms
        WHERE type IS NOT NULL AND type<>'' GROUP BY type");
    $db->query("UPDATE bookings b JOIN rooms r ON b.room_id=r.id JOIN room_types rt ON rt.name=r.type
        SET b.room_type_id=rt.id WHERE b.room_type_id IS NULL");

    $defaults = ['property_name'=>'Santa Fe Beach Club','property_address'=>'Barangay Poblacion, Santa Fe, Cebu','property_phone'=>'+63 32 123 4567','property_email'=>'info@santafebeachclub.com','checkin_time'=>'14:00','checkout_time'=>'12:00','property_timezone'=>SF_DEFAULT_PROPERTY_TIMEZONE,'currency'=>'PHP','gcash_number'=>'0950 522 3146','gcash_name'=>'Justine B'];
    $setting = $db->prepare('INSERT IGNORE INTO settings (setting_key,setting_value) VALUES (?,?)');
    foreach ($defaults as $key=>$value) { $setting->bind_param('ss',$key,$value); $setting->execute(); }
};
