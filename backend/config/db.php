<?php
require_once __DIR__ . '/../helpers/error_handler.php';
require_once __DIR__ . '/../helpers/business_time_helper.php';
require_once __DIR__ . '/../helpers/password_helper.php';

// MySQL database connection configuration (Supports Local XAMPP & Live InfinityFree MySQL)
$is_local_env = in_array($_SERVER['HTTP_HOST'] ?? '127.0.0.1', ['localhost', '127.0.0.1', '::1']);

// Suppress strict reporting during initial connection attempts
mysqli_report(MYSQLI_REPORT_OFF);

$conn = null;

if ($is_local_env) {
    // 1. Try Local XAMPP Database
    $conn = @new mysqli('127.0.0.1', 'root', '', 'santafe_beach_club', 3307);
    if ($conn && !$conn->connect_error) {
        $dbname = 'santafe_beach_club';
    }
}

// 2. Connect to Live InfinityFree MySQL Database if hosted online or local is offline
if (!$conn || $conn->connect_error) {
    $inf_host = 'sql111.infinityfree.com';
    $inf_user = 'if0_42717273';
    $inf_pass = 'ndAuPvlRiQVG';
    $dbname   = 'if0_42717273_santafebeachclub_db';

    $conn = @new mysqli($inf_host, $inf_user, $inf_pass, $dbname, 3306);
}

// Restore strict reporting for application queries
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    if (!$conn || $conn->connect_error) {
        throw new mysqli_sql_exception("Connection failed: " . ($conn ? $conn->connect_error : 'Unknown error'));
    }
    
    $conn->query("CREATE DATABASE IF NOT EXISTS $dbname");
    $conn->select_db($dbname);

    // Verify tables exist, else run the schema
    $tableCheck = $conn->query("SHOW TABLES LIKE 'rooms'");
    if ($tableCheck->num_rows == 0) {
        $schema = file_get_contents(__DIR__ . '/../database/database.sql');
        $queries = explode(';', $schema);
        foreach ($queries as $q) {
            $q = trim($q);
            if (!empty($q)) {
                try { $conn->query($q); } catch (Exception $e) {}
            }
        }
    }

    // Ensure token & email & payment columns exist (safe to run on every load)
    $conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS checkin_token VARCHAR(64) DEFAULT NULL");
    $conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS cancellation_token VARCHAR(64) DEFAULT NULL");
    $conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS cancelled_at DATETIME DEFAULT NULL");
    $conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS guest_email VARCHAR(150) DEFAULT NULL");
    $conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS payment_method VARCHAR(50) DEFAULT 'Pay at Check-in'");
    $conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS room_type_id INT DEFAULT NULL");
    $conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS checkout_notified_at DATETIME DEFAULT NULL");
    $conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS payment_deadline DATETIME DEFAULT NULL");
    $conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS cancellation_reason VARCHAR(255) DEFAULT NULL");
    $conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS promo_code VARCHAR(50) DEFAULT NULL");
    $conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS discount_amount DECIMAL(10,2) DEFAULT 0.00");
    $conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS confirmation_email_sent_at DATETIME DEFAULT NULL");
    
    // Guest information columns for View Profile feature
    $conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS guest_phone VARCHAR(20) DEFAULT NULL");
    $conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS guest_country VARCHAR(50) DEFAULT NULL");
    $conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS guest_special_requests TEXT DEFAULT NULL");
    $conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS guest_notes TEXT DEFAULT NULL");

    // Reviews table schema
    $conn->query("CREATE TABLE IF NOT EXISTS reviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        booking_id INT DEFAULT NULL,
        guest_name VARCHAR(100) NOT NULL,
        guest_location VARCHAR(100) DEFAULT 'Philippines',
        rating TINYINT UNSIGNED NOT NULL DEFAULT 5,
        review_text TEXT NOT NULL,
        is_approved TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    // Seed default reviews if empty
    $chkRev = $conn->query("SELECT COUNT(*) as cnt FROM reviews");
    if ($chkRev && $chkRev->fetch_assoc()['cnt'] == 0) {
        $conn->query("INSERT INTO reviews (guest_name, guest_location, rating, review_text, is_approved) VALUES
            ('Maria R.', 'Cebu, Philippines', 5, 'Absolutely breathtaking. We woke up to the sound of waves every morning. The staff was warm, attentive, and made our anniversary truly unforgettable.', 1),
            ('James L.', 'Manila, Philippines', 5, 'The Beachview Duplex was perfect for our family. The kids loved the beach access, and the room was immaculate. We\'ll definitely be back next summer!', 1),
            ('Sarah C.', 'Makati, Philippines', 5, 'Santa Fe Beach Club is a hidden gem. The calmer waters were perfect for swimming, and the whole property has a peaceful, boutique hotel feel.', 1)");
    }

    // Promotions table schema support (promo code)
    $conn->query("CREATE TABLE IF NOT EXISTS promotions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(50) DEFAULT NULL,
        title VARCHAR(150) NOT NULL,
        description TEXT,
        discount_type VARCHAR(20) DEFAULT 'percent',
        discount_value DECIMAL(10,2) DEFAULT 0,
        valid_from DATE,
        valid_until DATE,
        is_active TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    $conn->query("ALTER TABLE promotions ADD COLUMN IF NOT EXISTS code VARCHAR(50) DEFAULT NULL");

    // Pricing Rules table schema support (seasonal and weekend pricing)
    $conn->query("CREATE TABLE IF NOT EXISTS pricing_rules (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(150) NOT NULL,
        room_type VARCHAR(50) DEFAULT 'all',
        rule_type VARCHAR(20) NOT NULL DEFAULT 'weekend', -- 'weekend', 'date_range'
        start_date DATE DEFAULT NULL,
        end_date DATE DEFAULT NULL,
        days_of_week VARCHAR(30) DEFAULT '5,6,0', -- Friday=5, Saturday=6, Sunday=0
        adjustment_type VARCHAR(20) DEFAULT 'percent', -- 'percent', 'fixed'
        adjustment_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        is_active TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Room types table for availability detection
    $conn->query("CREATE TABLE IF NOT EXISTS room_types (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        total_rooms INT NOT NULL DEFAULT 0,
        price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
        image_url TEXT DEFAULT NULL,
        gallery_images TEXT DEFAULT NULL
    )");

    // Ensure image columns exist on upgrading existing tables
    $conn->query("ALTER TABLE room_types ADD COLUMN IF NOT EXISTS image_url TEXT DEFAULT NULL");
    $conn->query("ALTER TABLE room_types ADD COLUMN IF NOT EXISTS gallery_images TEXT DEFAULT NULL");

    // Enforce current room catalog, counts, capacities and rates.
    $catalogRooms = [
        ['101', 'Beachview Duplex 101', 'beachview_duplex', 6900.00, 2],
        ['102', 'Seaview Duplex 102', 'seaview_duplex', 7900.00, 2],
        ['103', 'Beach Villa 103', 'beach_villa', 7900.00, 4],
        ['104', 'Beach Villa 104', 'beach_villa', 7900.00, 4],
        ['105', 'Beach Villa 105', 'beach_villa', 7900.00, 4],
        ['106', 'Standard Family Room 106', 'standard_king', 4300.00, 4],
        ['203', 'Standard Family Room 203', 'standard_king', 4300.00, 4],
        ['107', 'Standard Room 107', 'standard_room', 2900.00, 2],
        ['108', 'Standard Room 108', 'standard_room', 2900.00, 2],
        ['109', 'Standard Room 109', 'standard_room', 2900.00, 2],
        ['110', 'Standard Room 110', 'standard_room', 2900.00, 2],
    ];

    $catalogUpsertStmt = $conn->prepare("
        INSERT INTO rooms (room_number, name, type, price_per_night, capacity, status)
        VALUES (?, ?, ?, ?, ?, 'ready')
        ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            type = VALUES(type),
            capacity = VALUES(capacity)
    ");
    foreach ($catalogRooms as [$roomNumber, $roomName, $roomType, $roomPrice, $roomCapacity]) {
        $catalogUpsertStmt->bind_param("sssdi", $roomNumber, $roomName, $roomType, $roomPrice, $roomCapacity);
        $catalogUpsertStmt->execute();
    }
    $catalogUpsertStmt->close();

    $catalogRoomNumbers = [];
    foreach ($catalogRooms as [$roomNumber]) {
        $catalogRoomNumbers[] = "'" . $conn->real_escape_string($roomNumber) . "'";
    }
    $catalogRoomNumbersSql = implode(',', $catalogRoomNumbers);
    $conn->query("DELETE FROM rooms WHERE room_number NOT IN ($catalogRoomNumbersSql)");

    // Keep room type inventory synced from the current rooms table.
    $roomTypesResult = $conn->query("
        SELECT type AS name, COUNT(*) AS total_rooms, MIN(price_per_night) AS price
        FROM rooms
        WHERE type IS NOT NULL AND type <> ''
        GROUP BY type
    ");
    if ($roomTypesResult) {
        $roomTypeStmt = $conn->prepare("
            INSERT INTO room_types (name, total_rooms, price)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
                total_rooms = VALUES(total_rooms)
        ");
        while ($roomType = $roomTypesResult->fetch_assoc()) {
            $name = $roomType['name'];
            $totalRooms = (int)$roomType['total_rooms'];
            $price = (float)$roomType['price'];
            $roomTypeStmt->bind_param("sid", $name, $totalRooms, $price);
            $roomTypeStmt->execute();
        }
        $roomTypeStmt->close();
    }
    $conn->query("DELETE FROM room_types WHERE name NOT IN ('beachview_duplex','seaview_duplex','beach_villa','standard_room','standard_king')");

    // Backfill room_type_id from the current rooms table where possible
    $conn->query("
        UPDATE bookings b
        JOIN rooms r ON b.room_id = r.id
        JOIN room_types rt ON rt.name = r.type
        SET b.room_type_id = rt.id
        WHERE b.room_type_id IS NULL
    ");

    // Ensure payments table exists
    $conn->query("CREATE TABLE IF NOT EXISTS payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        booking_id INT NOT NULL,
        guest_name VARCHAR(100) NOT NULL,
        guest_email VARCHAR(150),
        amount DECIMAL(10, 2) NOT NULL,
        payment_method VARCHAR(50) NOT NULL,
        transaction_id VARCHAR(100),
        status ENUM('pending', 'verified', 'rejected', 'refunded') DEFAULT 'pending',
        accounting_status VARCHAR(20) DEFAULT 'deferred',
        paid_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Add accounting_status to payments if upgrading from older schema
    $conn->query("ALTER TABLE payments ADD COLUMN IF NOT EXISTS accounting_status VARCHAR(20) DEFAULT 'deferred'");
    $conn->query("ALTER TABLE payments ADD COLUMN IF NOT EXISTS amount_tendered DECIMAL(10,2) DEFAULT NULL");
    $conn->query("ALTER TABLE payments ADD COLUMN IF NOT EXISTS change_amount DECIMAL(10,2) DEFAULT NULL");

    // Keep an immutable audit trail for every payment status action.
    $conn->query("CREATE TABLE IF NOT EXISTS payment_action_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        payment_id INT NOT NULL,
        action VARCHAR(30) NOT NULL,
        performed_by VARCHAR(100) NOT NULL,
        details TEXT DEFAULT NULL,
        performed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_payment_history_payment (payment_id),
        INDEX idx_payment_history_time (performed_at)
    )");

    // Ensure notifications table exists
    $conn->query("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(150) NOT NULL,
        message TEXT NOT NULL,
        type VARCHAR(30) DEFAULT 'info',
        is_read TINYINT(1) DEFAULT 0,
        booking_id INT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    $conn->query("ALTER TABLE notifications ADD COLUMN IF NOT EXISTS booking_id INT DEFAULT NULL");

    // Ensure admins table exists
    $conn->query("CREATE TABLE IF NOT EXISTS admins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role VARCHAR(20) DEFAULT 'receptionist',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Add role column if upgrading from older schema
    $conn->query("ALTER TABLE admins ADD COLUMN IF NOT EXISTS role VARCHAR(20) DEFAULT 'receptionist'");
    // Add email column to admins if not present (needed for MFA OTP delivery)
    $conn->query("ALTER TABLE admins ADD COLUMN IF NOT EXISTS email VARCHAR(150) DEFAULT NULL");
    // Add profile_photo column to admins
    $conn->query("ALTER TABLE admins ADD COLUMN IF NOT EXISTS profile_photo VARCHAR(255) DEFAULT NULL");

    // -----------------------------------------------------------------------
    // MFA: admin_otps â€” stores hashed OTPs for two-factor admin login
    // Raw OTP codes are NEVER stored here; only SHA-256 hashes.
    // -----------------------------------------------------------------------
    $conn->query("CREATE TABLE IF NOT EXISTS admin_otps (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        admin_id   INT          NOT NULL,
        otp_hash   VARCHAR(64)  NOT NULL,
        expires_at DATETIME     NOT NULL,
        attempts   TINYINT      NOT NULL DEFAULT 0,
        used       TINYINT(1)   NOT NULL DEFAULT 0,
        created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_admin_otp_lookup (admin_id, used, expires_at),
        FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
    )");

    // -----------------------------------------------------------------------
    // MFA: guest_otps â€” stores hashed OTPs for customer booking portal login
    // -----------------------------------------------------------------------
    $conn->query("CREATE TABLE IF NOT EXISTS guest_otps (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        booking_id INT          NOT NULL,
        otp_hash   VARCHAR(64)  NOT NULL,
        expires_at DATETIME     NOT NULL,
        attempts   TINYINT      NOT NULL DEFAULT 0,
        used       TINYINT(1)   NOT NULL DEFAULT 0,
        created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_guest_otp_lookup (booking_id, used, expires_at),
        FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
    )");

    // Seed default admin if no admins exist.
    // Default credentials: admin@beachclub.com / BeachAdmin@2024!
    // IMPORTANT: Change this password immediately after first login.
    $adminCheck = $conn->query("SELECT id FROM admins LIMIT 1");
    if ($adminCheck->num_rows == 0) {
        $hashedPassword = pw_hash('BeachAdmin@2024!');
        $conn->query("INSERT INTO admins (username, password, role) VALUES ('admin@beachclub.com', '$hashedPassword', 'admin')");
    } else {
        // Ensure at least one admin-role user exists (upgrade guard)
        $adminRoleCheck = $conn->query("SELECT id FROM admins WHERE role = 'admin' LIMIT 1");
        if ($adminRoleCheck->num_rows == 0) {
            $conn->query("UPDATE admins SET role = 'admin' WHERE id = (SELECT id FROM (SELECT id FROM admins ORDER BY id ASC LIMIT 1) t)");
        }
    }

    // Ensure activity_logs table exists
    $conn->query("CREATE TABLE IF NOT EXISTS activity_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_username VARCHAR(50) NOT NULL,
        action VARCHAR(100) NOT NULL,
        details TEXT,
        ip_address VARCHAR(45),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Ensure property settings table exists (key/value store)
    $conn->query("CREATE TABLE IF NOT EXISTS settings (
        setting_key VARCHAR(100) PRIMARY KEY,
        setting_value TEXT NOT NULL,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    // Ensure gallery table exists
    $conn->query("CREATE TABLE IF NOT EXISTS gallery (
        id INT AUTO_INCREMENT PRIMARY KEY,
        file_name VARCHAR(255) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Ensure inquiries table exists
    $conn->query("CREATE TABLE IF NOT EXISTS inquiries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        guest_name VARCHAR(150) NOT NULL,
        guest_email VARCHAR(150) NOT NULL,
        subject VARCHAR(200) NOT NULL,
        message TEXT NOT NULL,
        status VARCHAR(20) DEFAULT 'Unread',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Ensure security_logs table exists
    $conn->query("CREATE TABLE IF NOT EXISTS security_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_type VARCHAR(50) NOT NULL,
        event_level VARCHAR(20) NOT NULL DEFAULT 'INFO',
        username VARCHAR(100) DEFAULT 'anonymous',
        ip_address VARCHAR(45) NOT NULL,
        user_agent VARCHAR(255) DEFAULT NULL,
        request_uri VARCHAR(255) DEFAULT NULL,
        description TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Ensure rate_limits table exists
    $conn->query("CREATE TABLE IF NOT EXISTS rate_limits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45) NOT NULL,
        action VARCHAR(50) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ip_action_time (ip_address, action, created_at)
    )");

    // Seed any missing property settings without overwriting existing values
    $defaults = [
        ['property_name',     'Santa Fe Beach Club'],
        ['property_address',  'Barangay Poblacion, Santa Fe, Cebu'],
        ['property_phone',    '+63 32 123 4567'],
        ['property_email',    'info@santafebeachclub.com'],
        ['checkin_time',      '14:00'],
        ['checkout_time',     '12:00'],
        ['property_timezone', SF_DEFAULT_PROPERTY_TIMEZONE],
        ['currency',          'PHP'],
        ['gcash_number',      '0950 522 3146'],
        ['gcash_name',        'Justine B'],
    ];
    $stmt = $conn->prepare("
        INSERT INTO settings (setting_key, setting_value)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = setting_value
    ");
    foreach ($defaults as [$k, $v]) {
        $stmt->bind_param("ss", $k, $v);
        $stmt->execute();
    }
    $stmt->close();

    $propertyTimezone = sf_get_property_timezone_setting($conn);
    date_default_timezone_set($propertyTimezone);

    $timezoneOffset = (new DateTimeImmutable('now', new DateTimeZone($propertyTimezone)))->format('P');
    $conn->query("SET time_zone = '" . $conn->real_escape_string($timezoneOffset) . "'");

} catch (mysqli_sql_exception $e) {
    echo "<!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Database Connection Error</title>
        <link rel='stylesheet' href='styles.css'>
        <style>
            body { font-family: 'Outfit', sans-serif; background: #F3F4F6; }
            .error-container {
                max-width: 600px; margin: 100px auto; padding: 30px;
                background: white; border-radius: 12px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.05); text-align: center;
            }
            .error-title { color: #D84315; font-size: 24px; font-weight: 700; margin-bottom: 15px; }
            .error-body { font-size: 15px; color: #555; line-height: 1.6; margin-bottom: 25px; }
            .xampp-instructions {
                background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 8px;
                padding: 15px; text-align: left; font-size: 13px; color: #374151;
            }
            .xampp-instructions ol { margin-left: 20px; margin-top: 8px; }
            .xampp-instructions li { margin-bottom: 5px; }
        </style>
    </head>
    <body>
        <div class='error-container'>
            <div class='error-title'>MySQL Database Offline</div>
            <div class='error-body'>Cannot connect to MySQL. Please make sure MySQL is started in your XAMPP Control Panel.</div>
            <div class='xampp-instructions'>
                <strong>How to fix:</strong>
                <ol>
                    <li>Open the <strong>XAMPP Control Panel</strong>.</li>
                    <li>Click <strong>Start</strong> next to <strong>MySQL</strong> until it turns green.</li>
                    <li><strong>Refresh this page</strong>.</li>
                </ol>
            </div>
        </div>
    </body>
    </html>";
    exit;
}

// Helper: write an entry to activity_logs
function log_activity($conn, $username, $action, $details = '') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = $conn->prepare("INSERT INTO activity_logs (admin_username, action, details, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $username, $action, $details, $ip);
    $stmt->execute();
    $stmt->close();
}
?>

