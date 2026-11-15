<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../backend/helpers/refund_method_helper.php';
require_once __DIR__ . '/../backend/controllers/refund_request_controller.php';

function assert_refund_form_test(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }
    echo 'PASS: ' . $message . PHP_EOL;
}

final class FakeRefundResult
{
    /** @var array<int, array<string|int, mixed>> */
    private array $rows;
    private int $index = 0;

    /** @param array<int, array<string|int, mixed>> $rows */
    public function __construct(array $rows)
    {
        $this->rows = array_values($rows);
    }

    public function fetch_assoc(): ?array
    {
        if (!isset($this->rows[$this->index])) {
            return null;
        }
        return $this->rows[$this->index++];
    }

    public function fetch_row(): ?array
    {
        $row = $this->fetch_assoc();
        return $row === null ? null : array_values($row);
    }
}

final class FakeRefundStmt extends mysqli_stmt
{
    private FakeRefundMysqli $conn;
    private string $sql;
    /** @var array<int, mixed> */
    private array $params = [];

    public function __construct(FakeRefundMysqli $conn, string $sql)
    {
        $this->conn = $conn;
        $this->sql = $sql;
    }

    public function bind_param(string $types, mixed &...$vars): bool
    {
        $this->params = $vars;
        return true;
    }

    public function execute(?array $params = null): bool
    {
        if ($params !== null) {
            $this->params = $params;
        }
        return true;
    }

    public function get_result(): FakeRefundResult|false
    {
        return new FakeRefundResult($this->conn->rowsForPrepared($this->sql, $this->params));
    }

    public function close(): true
    {
        return true;
    }
}

final class FakeRefundMysqli extends mysqli
{
    /** @var array<int, string> */
    public array $executedSql = [];
    public ?string $databaseName = 'santafe';
    public int $preferenceColumnCount = 1;
    /** @var array<string, mixed>|null */
    public ?array $bookingRow = null;
    /** @var array<string, mixed>|null */
    public ?array $paymentRow = null;

    public function __construct()
    {
    }

    #[\ReturnTypeWillChange]
    public function query(string $query, int $result_mode = MYSQLI_STORE_RESULT)
    {
        $this->executedSql[] = $query;
        if (stripos($query, 'SELECT DATABASE()') !== false) {
            return new FakeRefundResult([[$this->databaseName]]);
        }
        if (stripos($query, 'FROM settings') !== false) {
            return new FakeRefundResult([
                ['setting_key' => 'refund_paymongo_enabled', 'setting_value' => '1'],
                ['setting_key' => 'refund_manual_gcash_enabled', 'setting_value' => '1'],
            ]);
        }
        return new FakeRefundResult([]);
    }

    public function prepare(string $query): mysqli_stmt|false
    {
        $this->executedSql[] = $query;
        if (preg_match('/LEFT\s+JOIN\s+payments\s+\w+\s+ON\s+\w+\.id\s*=\s*\(/i', $query)) {
            throw new mysqli_sql_exception('Correlated LEFT JOIN payments subquery rejected');
        }
        return new FakeRefundStmt($this, $query);
    }

    /**
     * @param array<int, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public function rowsForPrepared(string $sql, array $params): array
    {
        if (stripos($sql, 'information_schema.columns') !== false) {
            return [['cnt' => $this->preferenceColumnCount]];
        }
        if (stripos($sql, 'FROM bookings') !== false) {
            return $this->bookingRow !== null ? [$this->bookingRow] : [];
        }
        if (stripos($sql, 'FROM payments') !== false) {
            return $this->paymentRow !== null ? [$this->paymentRow] : [];
        }
        return [];
    }
}

$refundControllerSrc = file_get_contents(__DIR__ . '/../backend/controllers/refund_request_controller.php');
$invoiceControllerSrc = file_get_contents(__DIR__ . '/../backend/controllers/invoice_controller.php');

assert_refund_form_test(
    $refundControllerSrc !== false && $invoiceControllerSrc !== false,
    'Refund and invoice controllers are readable'
);
assert_refund_form_test(
    !preg_match('/LEFT\s+JOIN\s+payments\s+\w+\s+ON\s+\w+\.id\s*=\s*\(/i', (string)$refundControllerSrc),
    'refund_request_controller does not use correlated LEFT JOIN payments subquery'
);
assert_refund_form_test(
    !preg_match('/LEFT\s+JOIN\s+payments\s+\w+\s+ON\s+\w+\.id\s*=\s*\(/i', (string)$invoiceControllerSrc)
    && !str_contains((string)$invoiceControllerSrc, '(SELECT COALESCE(SUM(paid.amount)'),
    'invoice_controller does not use correlated payment subqueries'
);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_REQUEST['token'] = '077d37b6d6a390a5100fdb288899c158';
$_POST = [];

$conn = new FakeRefundMysqli();
$conn->bookingRow = [
    'id' => 120001,
    'guest_name' => 'Justine Batuhan',
    'guest_email' => 'justine@example.com',
    'accommodation_name' => 'Beach Villa <103>',
    'check_in' => '2026-11-15',
    'check_out' => '2026-11-16',
    'status' => 'Confirmed',
    'cancellation_token' => '077d37b6d6a390a5100fdb288899c158',
];
$conn->paymentRow = [
    'payment_id' => 987,
    'amount' => '3950.00',
    'payment_method' => 'GCash QR',
];

$state = sfbc_handle_refund_request_page($conn);
assert_refund_form_test(
    $state['error_message'] === ''
    && is_array($state['booking'])
    && (int)$state['booking']['payment_id'] === 987
    && (float)$state['booking']['amount'] === 3950.0
    && $state['booking']['payment_method'] === 'GCash QR'
    && $state['refund_preference_ready'] === true,
    'GET with valid token loads booking and latest verified payment via single-table queries'
);

ob_start();
extract($state, EXTR_SKIP);
require __DIR__ . '/../frontend/views/refund_request/_form.php';
$rendered = (string)ob_get_clean();
assert_refund_form_test(
    str_contains($rendered, '<form method="POST">')
    && str_contains($rendered, 'REF-120001')
    && str_contains($rendered, 'Beach Villa &lt;103&gt;'),
    'Refund form renders booking details and escapes accommodation name'
);

$noPayConn = new FakeRefundMysqli();
$noPayConn->databaseName = null;
$noPayConn->bookingRow = $conn->bookingRow;
$noPayConn->paymentRow = null;
$noPayState = sfbc_handle_refund_request_page($noPayConn);
assert_refund_form_test(
    $noPayState['refund_preference_ready'] === false
    && is_array($noPayState['booking'])
    && $noPayState['booking']['payment_id'] === null
    && $noPayState['booking']['amount'] === null
    && $noPayState['booking']['payment_method'] === null
    && $noPayState['error_message'] === '',
    'Handles null DATABASE() and missing verified payment without throwing'
);

$_REQUEST['token'] = '';
ob_start();
$error_message = '<script>alert(1)</script>';
$success_message = '';
$booking = null;
require __DIR__ . '/../frontend/views/refund_request/_form.php';
$errHtml = (string)ob_get_clean();
assert_refund_form_test(
    !str_contains($errHtml, '<script>')
    && str_contains($errHtml, '&lt;script&gt;alert(1)&lt;/script&gt;'),
    'Refund form escapes error_message safely'
);

echo "All refund request form checks passed.\n";
