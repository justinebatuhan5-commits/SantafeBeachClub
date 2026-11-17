<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../services/booking_service.php';

// Exercise actual booking insertion without connecting to a database.
class BookingStatusTestPdo extends PDO {
    public array $inserted = [];
    public bool $occupied = false;
    public function __construct() {}
    public function beginTransaction(): bool { return true; }
    public function commit(): bool { return true; }
    public function inTransaction(): bool { return true; }
    public function rollBack(): bool { return true; }
    public function lastInsertId(?string $name = null): string|false { return '1'; }
    public function prepare(string $query, array $options = []): PDOStatement|false {
        return new BookingStatusTestStatement($this, $query);
    }
}
class BookingStatusTestStatement extends PDOStatement {
    public function __construct(private BookingStatusTestPdo $db, private string $sql) {}
    public function execute(?array $params = null): bool {
        if (str_starts_with($this->sql, 'INSERT INTO bookings')) $this->db->inserted = $params;
        if (str_starts_with($this->sql, 'UPDATE rooms')) $this->db->occupied = true;
        return true;
    }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed {
        return ['id' => 1, 'total_rooms' => 5, 'name' => 'Test Room'];
    }
    public function fetchColumn(int $column = 0): mixed {
        return str_starts_with($this->sql, 'SELECT name') ? 'Test Room' : 0;
    }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array {
        return [['id' => 1], ['id' => 2]];
    }
}
function checkBookingStatus(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
$data = ['room_type_id' => 1, 'check_in' => '2026-10-01', 'check_out' => '2026-10-02', 'guest_name' => 'Test'];
foreach ([null, 'Confirmed', 'Checked In', 'Cancelled', 'confirmed', '', ['Checked In']] as $submitted) {
    $db = new BookingStatusTestPdo();
    $result = createBooking($db, $data + ['status' => $submitted]);
    checkBookingStatus($result['success'] && $db->inserted[':status'] === 'Pending', 'Public status must always be Pending');
    checkBookingStatus(!$db->occupied, 'Public booking must not occupy a room');
}
$db = new BookingStatusTestPdo();
createBooking($db, $data);
checkBookingStatus($db->inserted[':status'] === 'Pending', 'Omitted status must be Pending');
checkBookingStatus($db->inserted[':guest_country'] === 'Philippines', 'Omitted guest_country must default to Philippines');
$db = new BookingStatusTestPdo();
createBooking($db, $data + ['guest_phone' => '09171234567', 'guest_country' => 'Japan']);
checkBookingStatus($db->inserted[':guest_phone'] === '09171234567', 'Optional guest_phone must be stored when provided');
checkBookingStatus($db->inserted[':guest_country'] === 'Japan', 'Provided guest_country must be stored');
$db = new BookingStatusTestPdo();
createBooking($db, $data, 'Checked In');
checkBookingStatus($db->inserted[':status'] === 'Checked In' && $db->occupied, 'Authorized staff check-in must occupy room');
try {
    createBooking(new BookingStatusTestPdo(), $data, 'Confirmed');
    throw new RuntimeException('Unexpected acceptance of Confirmed');
} catch (InvalidArgumentException $e) {
    // Confirmation belongs to the payment verification flow.
}
echo "PASS: public status overrides ignored, default Pending, no public room occupancy, staff check-in, invalid staff status rejected.\n";
