<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/balance_payment_helper.php';
$result = ['reminders'=>sf_send_balance_reminders($conn),'deadlines'=>sf_process_balance_deadlines($conn)];
echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
