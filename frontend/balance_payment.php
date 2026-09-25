<?php
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/services/paymongo.php';
require_once __DIR__ . '/../backend/services/paymongo_confirmation.php';
require_once __DIR__ . '/../backend/helpers/balance_payment_helper.php';

if (session_status() === PHP_SESSION_NONE) session_start();
header('Cache-Control: no-store');
$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$error = '';
$booking = null;
if (preg_match('/\A[a-f0-9]{32}\z/', $token)) {
    $stmt=$conn->prepare("SELECT id FROM bookings WHERE cancellation_token=? AND status IN ('Pending','Confirmed') LIMIT 1");
    $stmt->bind_param('s',$token); $stmt->execute(); $row=$stmt->get_result()->fetch_assoc(); $stmt->close();
    if ($row) { sf_initialize_balance_deadline($conn,(int)$row['id']); $booking=sf_refresh_balance_status($conn,(int)$row['id']); }
}
if (!$booking) { http_response_code(404); $error='This balance-payment link is invalid or the booking is no longer active.'; }

if ($_SERVER['REQUEST_METHOD']==='POST' && $booking) {
    require_csrf_token();
    if ($booking['balance_due'] <= 0.009) $error='This booking is already paid in full.';
    elseif ($booking['balance_status']==='cancellation_review') $error='This booking requires front-desk review. Please contact Santa Fe Beach Club.';
    else {
        $conn->begin_transaction();
        try {
            $locked=sf_booking_financials($conn,(int)$booking['id'],true);
            $pending=pm_query($conn,"SELECT p.id,c.checkout_url FROM payments p LEFT JOIN paymongo_checkouts c ON c.payment_id=p.id WHERE p.booking_id=? AND p.purpose='balance' AND p.status='pending' ORDER BY p.id DESC LIMIT 1",[(int)$booking['id']])->get_result()->fetch_assoc();
            if ($pending && !empty($pending['checkout_url'])) { $conn->commit(); header('Location: '.$pending['checkout_url']); exit; }
            if ($pending) throw new RuntimeException('A balance checkout is already active. Please contact the front desk if it cannot be opened.');
            $amount=(float)$locked['balance_due'];
            if ($amount < 1) throw new RuntimeException('No payable balance remains.');
            pm_query($conn,"INSERT INTO payments (booking_id,guest_name,guest_email,amount,payment_method,status,purpose) VALUES (?,?,?,?, 'PayMongo Online','pending','balance')",[(int)$booking['id'],$booking['guest_name'],$booking['guest_email'],$amount]);
            $paymentId=(int)$conn->insert_id;
            $base=sf_balance_public_url();
            $return=$base.'/payment_success.php?booking_id='.(int)$booking['id'].'&session_id={CHECKOUT_SESSION_ID}';
            $cancel=$base.'/balance_payment.php?token='.urlencode($token);
            $result=PayMongoService::createCheckoutSession($amount,'Remaining balance for REF-'.str_pad((string)$booking['id'],3,'0',STR_PAD_LEFT),(int)$booking['id'],$booking['guest_name'],$booking['guest_email'],$booking['guest_phone']??'',$return,$cancel,'Remaining Booking Balance — Santa Fe Beach Club');
            if (!$result['success']) throw new RuntimeException($result['error']??'Could not create PayMongo checkout.');
            pm_store_checkout($conn,(string)$result['session_id'],$paymentId,(int)$booking['id'],$amount);
            pm_query($conn,'UPDATE paymongo_checkouts SET checkout_url=? WHERE session_id=?',[(string)$result['checkout_url'],(string)$result['session_id']]);
            pm_query($conn,"UPDATE payments SET transaction_id=? WHERE id=? AND status='pending'",[(string)$result['session_id'],$paymentId]);
            $conn->commit();
            header('Location: '.$result['checkout_url']); exit;
        } catch (Throwable $e) { $conn->rollback(); $error=$e->getMessage(); }
    }
}
$deadline = $booking && $booking['balance_due_at'] ? date('M j, Y g:i A',strtotime($booking['balance_due_at'])) : '—';
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Booking Balance — Santa Fe Beach Club</title>
<style>body{font-family:Arial,sans-serif;background:#f4faf7;color:#17202a;margin:0;padding:30px}.card{max-width:620px;margin:30px auto;background:#fff;border:1px solid #dbe7e1;border-radius:18px;padding:30px;box-shadow:0 12px 35px #163c2a12}h1{margin-top:0}.row{display:flex;justify-content:space-between;padding:12px 0;border-bottom:1px solid #edf2ef}.due{font-size:28px;font-weight:800;color:#9a3412}.notice{padding:14px;border-radius:9px;background:#fff7ed;color:#9a3412;margin:18px 0}.error{background:#fef2f2;color:#991b1b}.btn{width:100%;border:0;border-radius:9px;padding:14px;background:#15803d;color:#fff;font-size:16px;font-weight:700;cursor:pointer}.paid{background:#ecfdf5;color:#166534}</style></head><body><div class="card">
<h1>Complete your booking payment</h1>
<?php if($error): ?><div class="notice error"><?=htmlspecialchars($error)?></div><?php endif; ?>
<?php if($booking): ?><div class="row"><span>Booking</span><strong>REF-<?=str_pad((string)$booking['id'],3,'0',STR_PAD_LEFT)?></strong></div><div class="row"><span>Guest</span><strong><?=htmlspecialchars($booking['guest_name'])?></strong></div><div class="row"><span>Stay</span><strong><?=htmlspecialchars($booking['check_in'])?> to <?=htmlspecialchars($booking['check_out'])?></strong></div><div class="row"><span>Total</span><strong>₱<?=number_format($booking['total_cost'],2)?></strong></div><div class="row"><span>Paid</span><strong>₱<?=number_format($booking['amount_paid'],2)?></strong></div><p>Remaining balance</p><div class="due">₱<?=number_format($booking['balance_due'],2)?></div>
<?php if($booking['balance_due']<=0.009): ?><div class="notice paid">This booking is paid in full.</div><?php else: ?><div class="notice">Payment deadline: <strong><?=htmlspecialchars($deadline)?></strong></div><form method="post"><input type="hidden" name="token" value="<?=htmlspecialchars($token)?>"><?=csrf_field()?><button class="btn" type="submit">Pay Remaining Balance with PayMongo</button></form><?php endif; ?>
<?php endif; ?></div></body></html>
