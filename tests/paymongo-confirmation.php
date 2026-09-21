<?php
require_once __DIR__ . '/../backend/services/paymongo_confirmation.php';

function check_pm(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}
function reject_pm(callable $action, string $message): void {
    try { $action(); } catch (Throwable $e) { return; }
    throw new RuntimeException($message);
}
function fixture_pm(string $session = 'cs_one', string $txn = 'pay_one', string $booking = '1'): array {
    return ['id' => $session, 'attributes' => ['metadata' => ['booking_id' => $booking],
        'payments' => [['id' => $txn, 'attributes' => ['status' => 'paid', 'amount' => 10000,
            'currency' => 'PHP', 'source' => ['type' => 'gcash']]]]]];
}
$checkout = ['session_id' => 'cs_one', 'booking_id' => 1, 'amount_centavos' => 10000, 'currency' => 'PHP'];
check_pm(pm_validate_checkout($checkout, fixture_pm())['id'] === 'pay_one', 'Valid checkout rejected.');
$invalid = [];
$s = fixture_pm(); $s['id'] = 'cs_other'; $invalid[] = $s;
$s = fixture_pm(); $s['attributes']['metadata']['booking_id'] = '2'; $invalid[] = $s;
$s = fixture_pm(); unset($s['attributes']['metadata']); $invalid[] = $s;
foreach (['amount' => 9999, 'currency' => 'USD', 'status' => 'pending'] as $key => $value) {
    $s = fixture_pm(); $s['attributes']['payments'][0]['attributes'][$key] = $value; $invalid[] = $s;
}
$s = fixture_pm(); $s['attributes']['payments'] = []; $invalid[] = $s;
$s = fixture_pm(); $s['attributes']['payments'][] = $s['attributes']['payments'][0]; $invalid[] = $s;
$s = fixture_pm(); unset($s['attributes']['payments'][0]['id']); $invalid[] = $s;
foreach ($invalid as $s) reject_pm(fn() => pm_validate_checkout($checkout, $s), 'Invalid provider response accepted.');
echo "Checkout validator: valid payment and 9 rejection cases passed.\n";

if (!in_array('--database', $argv, true)) exit;
// Explicit local-only connection, no application bootstrap or production fallback.
// Temporary tables shadow application tables and disappear when this connection closes.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('127.0.0.1', 'root', '', 'santafe_beach_club', 3307);
$db->query("CREATE TEMPORARY TABLE payments (id INT PRIMARY KEY, booking_id INT, amount DECIMAL(10,2),
    payment_method VARCHAR(50), transaction_id VARCHAR(100), status VARCHAR(30)) ENGINE=InnoDB");
$db->query("CREATE TEMPORARY TABLE bookings (id INT PRIMARY KEY, status VARCHAR(30)) ENGINE=InnoDB");
$db->query("CREATE TEMPORARY TABLE paymongo_checkouts (session_id VARCHAR(100) COLLATE utf8mb4_bin PRIMARY KEY,
    payment_id INT UNIQUE, booking_id INT, amount_centavos BIGINT, currency CHAR(3),
    provider_payment_id VARCHAR(100) COLLATE utf8mb4_bin NULL UNIQUE) ENGINE=InnoDB");
$db->query("CREATE TEMPORARY TABLE payment_action_history (payment_id INT, action VARCHAR(30), performed_by VARCHAR(100), details TEXT) ENGINE=InnoDB");
$db->query("CREATE TEMPORARY TABLE notifications (title VARCHAR(100), message TEXT, type VARCHAR(30), booking_id INT) ENGINE=InnoDB");
$db->query("INSERT INTO bookings VALUES (1,'Pending Payment'),(2,'Pending Payment'),(3,'Cancelled')");
$db->query("INSERT INTO payments VALUES (1,1,100,'PayMongo Online','cs_one','pending'),
    (2,2,100,'PayMongo Online','cs_two','pending'),(3,3,100,'PayMongo Online','cs_three','pending'),
    (4,1,200,'Front Desk Cash','cash_other','pending')");
pm_checkout_schema($db);
reject_pm(fn() => pm_confirm_checkout($db, 2, 'cs_one', fixture_pm()), 'Cross-booking session accepted.');
$s = fixture_pm(); $s['attributes']['payments'][0]['attributes']['amount'] = 9999;
reject_pm(fn() => pm_confirm_checkout($db, 1, 'cs_one', $s), 'Wrong amount accepted.');
check_pm(pm_confirm_checkout($db, 1, 'cs_one', fixture_pm())['new'], 'First confirmation not applied.');
check_pm(!pm_confirm_checkout($db, 1, 'cs_one', fixture_pm())['new'], 'Duplicate confirmation applied.');
reject_pm(fn() => pm_confirm_checkout($db, 2, 'cs_two', fixture_pm('cs_two', 'pay_one', '2')), 'Transaction reused.');
reject_pm(fn() => pm_confirm_checkout($db, 3, 'cs_three', fixture_pm('cs_three', 'pay_three', '3')), 'Cancelled booking confirmed.');
check_pm($db->query('SELECT COUNT(*) FROM payment_action_history')->fetch_row()[0] == 1, 'Duplicate audit records.');
check_pm($db->query('SELECT COUNT(*) FROM notifications')->fetch_row()[0] == 1, 'Duplicate notifications.');
check_pm($db->query("SELECT status FROM payments WHERE id=4")->fetch_row()[0] === 'pending', 'Unrelated payment changed.');
check_pm($db->query("SELECT status FROM bookings WHERE id=2")->fetch_row()[0] === 'Pending Payment', 'Rejected booking changed.');
// Force a failure after claiming a provider ID; all changes must roll back.
$db->query("ALTER TABLE notifications ADD CONSTRAINT reject_booking_two CHECK (booking_id <> 2)");
reject_pm(fn() => pm_confirm_checkout($db, 2, 'cs_two', fixture_pm('cs_two', 'pay_two', '2')), 'Expected rollback failure.');
check_pm($db->query("SELECT provider_payment_id FROM paymongo_checkouts WHERE session_id='cs_two'")->fetch_row()[0] === null, 'Failed transaction retained claim.');
check_pm($db->query('SELECT status FROM payments WHERE id=2')->fetch_row()[0] === 'pending', 'Failed transaction retained payment update.');
echo "Temporary-table integration: binding, idempotency, transaction reuse, cancellation, exact payment targeting and rollback passed.\n";
$db->close();
