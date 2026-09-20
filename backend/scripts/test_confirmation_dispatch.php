<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Isolated dispatch regression check: no database connection or SMTP delivery.
$source = file_get_contents(__DIR__ . '/../services/mailer.php');
$start = strpos($source, 'function sendBookingConfirmationEmail(');
$end = strpos($source, 'function sendBookingReceivedEmail(', $start);
eval(substr($source, $start, $end - $start));

class DispatchTestResult {
    public function __construct(private $value) {}
    public function fetch_row() { return [$this->value]; }
    public function fetch_assoc() { return $this->value; }
}
class DispatchTestStatement {
    public function __construct(private $db, private $sql) {}
    public function bind_param($types, &...$values) {}
    public function execute() {
        if (str_starts_with($this->sql, 'UPDATE bookings')) $this->db->sent = 'sent';
        if (str_contains($this->sql, 'RELEASE_LOCK')) $this->db->released++;
    }
    public function get_result() {
        if (str_contains($this->sql, 'GET_LOCK')) return new DispatchTestResult($this->db->locked);
        return new DispatchTestResult(['confirmation_email_sent_at' => $this->db->sent, 'verified' => $this->db->verified]);
    }
    public function close() {}
}
class DispatchTestDatabase {
    public $sent = null;
    public $verified = true;
    public $locked = 1;
    public $released = 0;
    public function prepare($sql) { return new DispatchTestStatement($this, $sql); }
}
$deliveries = 0;
$succeeds = true;
function sendBookingStatusEmail(...$args) {
    global $deliveries, $succeeds;
    $deliveries++;
    return ['success' => $succeeds, 'error' => $succeeds ? null : 'Simulated SMTP failure'];
}
function checkDispatch($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}
$conn = new DispatchTestDatabase();
$args = ['guest@example.invalid', 'Test', 'REF-001', 'Room', '2026-10-01', '2026-10-02', 100, null, null];
checkDispatch(sendBookingConfirmationEmail(...$args)['success'] && $deliveries === 1, 'First verified confirmation must send');
checkDispatch(!empty(sendBookingConfirmationEmail(...$args)['skipped']) && $deliveries === 1, 'Repeated automatic confirmation must skip');
checkDispatch(sendBookingConfirmationEmail(...array_merge($args, [true]))['success'] && $deliveries === 2, 'Explicit resend must send');
$conn->verified = false;
checkDispatch(!sendBookingConfirmationEmail(...array_merge($args, [true]))['success'] && $deliveries === 2, 'Unverified booking must not send');
$conn->verified = true;
$conn->sent = null;
$succeeds = false;
checkDispatch(!sendBookingConfirmationEmail(...$args)['success'] && $conn->sent === null, 'Failure must not mark sent');
$succeeds = true;
checkDispatch(sendBookingConfirmationEmail(...$args)['success'] && $conn->sent !== null, 'Failed send must be retryable');
$conn->locked = 0;
$before = $deliveries;
checkDispatch(!sendBookingConfirmationEmail(...$args)['success'] && $deliveries === $before, 'Contending request must not send without lock');
checkDispatch($conn->released === 6, 'Every acquired lock must be released');
echo "PASS: initial send, duplicate suppression, explicit resend, verification gate, failure retry, lock contention and release.\n";
