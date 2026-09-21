<?php
require_once __DIR__ . '/../backend/helpers/auth_check.php';
require_once __DIR__ . '/../backend/config/db.php';
if (!in_array($_SESSION['admin_role'] ?? '', ['admin','receptionist'],true)) { http_response_code(403); exit('Staff access required.'); }
header('Referrer-Policy: no-referrer');
function dh($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
$actor = $_SESSION['admin_username'] ?? 'Staff';
$id = (int)($_GET['booking'] ?? $_POST['booking_id'] ?? 0);
$message = $_SESSION['discount_staff_notice'] ?? ''; unset($_SESSION['discount_staff_notice']);
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
    try {
        $action = $_POST['action'] ?? '';
        if ($action === 'settings') {
            if (($_SESSION['admin_role'] ?? '') !== 'admin') throw new RuntimeException('Only administrators can change payment details.');
            foreach (['gcash_number','gcash_name','discount_bank_name','discount_bank_account','discount_bank_holder'] as $key) {
                $value = trim($_POST[$key] ?? '');
                if (strlen($value)>150) throw new RuntimeException('Payment details must be shorter than 150 characters.');
                pm_query($conn,'INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',[$key,$value]);
            }
            log_activity($conn,$actor,'Updated manual payment details','Discount payment options');
            $message = 'Payment instructions saved.';
        } elseif (in_array($action,['approve','decline','information'],true)) {
            discount_review($conn,$id,$action,trim($_POST['note'] ?? ''),$actor);
            $sent = discount_email($conn,$id);
            $message = $sent ? 'Decision saved and guest emailed with their private booking/payment link.' : 'Decision saved, but email failed. Copy the private link below or retry email.';
        } elseif ($action === 'email') {
            $message = discount_email($conn,$id) ? 'Guest emailed.' : 'Email failed. Copy the private link below.';
        } elseif ($action === 'checkout') {
            discount_start_payment($conn,$id,'PayMongo Online');
            $message = discount_email($conn,$id) ? 'PayMongo checkout created; guest emailed.' : 'Checkout created. Email failed; copy the private link below.';
        } elseif (in_array($action,['verify','reject'],true)) {
            if ($action==='verify' && empty($_POST['verified_transaction'])) throw new RuntimeException('Confirm that you checked the actual incoming account transaction.');
            discount_manual_review($conn,$id,(int)($_POST['payment_id']??0),$action==='verify',$actor,trim($_POST['note']??''));
            $sent = discount_email($conn,$id);
            $message = 'Payment decision saved.' . ($sent ? ' Guest emailed.' : ' Email failed; use the private link.');
        } else throw new RuntimeException('Unknown action.');
        $_SESSION['discount_staff_notice']=$message;
        header('Location: discount_requests.php' . ($id?'?booking='.$id:'')); exit;
    } catch(Throwable $e) { $error=$e instanceof mysqli_sql_exception ? 'The update could not be saved. Please refresh and retry.' : $e->getMessage(); }
}
$d = $id ? pm_query($conn,'SELECT d.*,b.guest_name,b.guest_email,b.accommodation_name,b.status FROM booking_discounts d JOIN bookings b ON b.id=d.booking_id WHERE d.booking_id=?',[$id])->get_result()->fetch_assoc() : null;
$settings=[]; $sr=$conn->query("SELECT setting_key,setting_value FROM settings WHERE setting_key IN ('gcash_number','gcash_name','discount_bank_name','discount_bank_account','discount_bank_holder')");
while($s=$sr->fetch_assoc()) $settings[$s['setting_key']]=$s['setting_value'];
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Discount Requests · Santa Fe Beach Club</title><link rel="stylesheet" href="assets/css/discounts.css"></head><body class="discount-page"><main class="discount-shell">
<a href="<?= ($_SESSION['admin_role']??'')==='admin'?'admin_dashboard':'dashboard' ?>">← Dashboard</a> · <a href="discount_requests.php">All discount requests</a><h1>Discount Requests</h1><p>Verify eligibility, send payment options, and confirm incoming manual payments.</p>
<?php if($message): ?><div class="discount-note" role="status"><?= dh($message) ?></div><?php endif ?><?php if($error): ?><div class="discount-note discount-error" role="alert"><?= dh($error) ?></div><?php endif ?>
<?php if($d): ?>
<section class="discount-card"><span class="discount-status"><?= dh($d['state']) ?></span><h2>REF-<?= dh(str_pad((string)$id,3,'0',STR_PAD_LEFT)) ?> · <?= dh($d['guest_name']) ?></h2><p><?= dh($d['guest_email']) ?><br><?= dh($d['accommodation_name']) ?> · <?= dh($d['check_in']) ?> to <?= dh($d['check_out']) ?><br>Eligible guest: <strong><?= dh($d['eligible_guest']) ?></strong> · <?= dh(strtoupper($d['category'])) ?><br>Hold ends: <?= dh($d['hold_until']) ?> (Philippine time)</p>
<p>Original: ₱ <?= number_format($d['original_centavos']/100,2) ?> · Discount: ₱ <?= number_format(($d['original_centavos']-$d['total_centavos'])/100,2) ?> · Total: ₱ <?= number_format($d['total_centavos']/100,2) ?> · Deposit: ₱ <?= number_format($d['deposit_centavos']/100,2) ?></p>
<?php if($d['id_file']): ?><a class="discount-button" href="../backend/api/discount_document.php?booking=<?= $id ?>" target="_blank" rel="noopener noreferrer">Review private ID</a><?php else: ?><p>ID removed under the retention policy. Request a new upload if review is still needed.</p><?php endif ?>
<?php if(in_array($d['state'],['Awaiting ID verification','More information needed'],true)): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="booking_id" value="<?= $id ?>"><label>Guest message / decision reason<textarea name="note" maxlength="2000" rows="3"></textarea></label><button name="action" value="approve">Approve 20% & send payment options</button><button name="action" value="information" class="secondary">Request information</button><button name="action" value="decline" class="secondary">Decline discount</button></form><?php endif ?>
<?php if($d['note']): ?><p>Latest message: <?= nl2br(dh($d['note'])) ?></p><?php endif ?>
<label>Private guest booking/payment link<input id="privateLink" readonly value="<?= dh(discount_link($d)) ?>"></label><button type="button" id="copyLink" class="secondary">Copy payment link</button><span id="copyResult" role="status"></span>
<form method="post"><?= csrf_field() ?><input type="hidden" name="booking_id" value="<?= $id ?>"><button name="action" value="email" class="secondary">Resend guest email</button><?php if(in_array($d['state'],['Awaiting Deposit','Confirmed'],true) && discount_due($conn,$d)>0): ?><button name="action" value="checkout">Create PayMongo checkout & email</button><p><small>Creating a checkout reserves the current payment attempt for PayMongo. Otherwise the guest can choose PayMongo or configured manual payment options.</small></p><?php endif ?></form></section>
<section class="discount-card"><h2>Payments</h2>
<?php $ps=pm_query($conn,'SELECT p.*,x.purpose,x.checkout_url FROM discount_payments x JOIN payments p ON p.id=x.payment_id WHERE x.booking_id=? ORDER BY p.id DESC',[$id])->get_result(); while($p=$ps->fetch_assoc()): ?>
<details open><summary><?= dh(ucfirst($p['purpose'])) ?> · ₱ <?= number_format($p['amount'],2) ?> · <?= dh($p['status']) ?></summary><p><?= dh($p['payment_method']) ?> · Reference: <?= dh($p['transaction_id']) ?></p>
<?php if($p['receipt_url']): ?><a href="../backend/api/discount_document.php?booking=<?= $id ?>&payment=<?= (int)$p['id'] ?>" target="_blank" rel="noopener noreferrer">View private receipt</a><?php endif ?>
<?php if($p['status']==='pending' && in_array($p['payment_method'],['GCash QR','Bank Deposit'],true)): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="booking_id" value="<?= $id ?>"><input type="hidden" name="payment_id" value="<?= (int)$p['id'] ?>"><label><input type="checkbox" name="verified_transaction" value="1"> I checked the actual incoming transaction, amount, and reference in the receiving account.</label><label>Verification note / rejection reason<textarea name="note" maxlength="2000"></textarea></label><button name="action" value="verify">Confirm payment</button><button name="action" value="reject" class="secondary">Reject receipt</button></form><?php endif ?></details><?php endwhile ?>
<?php if(in_array($d['state'],['Expired','Cancelled'],true)): ?><p>This hold has ended. Investigate any received payment and arrange a refund or a new booking; do not confirm an expired room hold.</p><?php endif ?></section>
<section class="discount-card"><h2>Audit history</h2><?php $history=pm_query($conn,'SELECT * FROM discount_history WHERE booking_id=? ORDER BY id DESC',[$id])->get_result(); while($h=$history->fetch_assoc()): ?><p><strong><?= dh($h['action']) ?></strong> · <?= dh($h['actor']) ?> · <?= dh($h['created_at']) ?><br><?= dh($h['details']) ?></p><?php endwhile ?></section>
<?php else: ?>
<section class="discount-card discount-table"><table><thead><tr><th>Booking</th><th>Guest / type</th><th>Status</th><th>Hold deadline</th><th></th></tr></thead><tbody>
<?php $rows=$conn->query('SELECT d.*,b.guest_name FROM booking_discounts d JOIN bookings b ON b.id=d.booking_id ORDER BY d.created_at DESC LIMIT 200'); while($r=$rows->fetch_assoc()): ?><tr><td>REF-<?= (int)$r['booking_id'] ?></td><td><?= dh($r['guest_name']) ?><br><small><?= dh(strtoupper($r['category'])) ?></small></td><td><?= dh($r['state']) ?></td><td><?= dh($r['hold_until']) ?></td><td><a href="?booking=<?= (int)$r['booking_id'] ?>">Review</a></td></tr><?php endwhile ?>
<?php if(!$rows->num_rows): ?><tr><td colspan="5">No discount requests yet.</td></tr><?php endif ?></tbody></table></section>
<?php if(($_SESSION['admin_role']??'')==='admin'): ?><section class="discount-card"><h2>Manual payment instructions</h2><p>Only configure accounts authorized to receive hotel payments. A method stays hidden until all its fields are supplied.</p><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="settings"><?php foreach(['gcash_number'=>'GCash number','gcash_name'=>'GCash account name','discount_bank_name'=>'Bank name','discount_bank_account'=>'Bank account number','discount_bank_holder'=>'Bank account holder'] as $key=>$label): ?><label><?= dh($label) ?><input name="<?= dh($key) ?>" value="<?= dh($settings[$key]??'') ?>" maxlength="150"></label><?php endforeach ?><button>Save payment instructions</button></form></section><?php endif ?>
<?php endif ?></main><script>document.getElementById('copyLink')?.addEventListener('click',async()=>{const field=document.getElementById('privateLink');try{await navigator.clipboard.writeText(field.value);document.getElementById('copyResult').textContent='Link copied.';}catch(e){field.select();document.getElementById('copyResult').textContent='Select and copy the link above.';}});</script></body></html>
