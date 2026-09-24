<?php
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/helpers/csrf_helper.php';
require_once __DIR__ . '/../backend/helpers/security_headers.php';
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');
function dh($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
if (isset($_GET['token']) || ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'access')) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') require_csrf_token();
    $token = (string)($_POST['access_token'] ?? $_GET['token'] ?? '');
    $row = preg_match('/^[a-f0-9]{64}$/D', $token) ? pm_query($conn, 'SELECT booking_id FROM booking_discounts WHERE token=?', [$token])->get_result()->fetch_assoc() : null;
    if (!$row) { http_response_code(404); exit('This private booking link is invalid.'); }
    $_SESSION['discount_tokens'][(int)$row['booking_id']] = $token;
    header('Location: discount_booking.php?booking=' . (int)$row['booking_id']); exit;
}
$id = (int)($_GET['booking'] ?? 0);
if (!$id && $_SERVER['REQUEST_METHOD'] === 'GET') {
    // Remove the fragment immediately, then exchange it in a CSRF-protected POST.
    ?>
    <!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Open your booking</title><?php require __DIR__ . '/partials/_responsive.php'; ?>
</head><body>
    <p id="accessMessage">Opening your private booking…</p>
    <form id="accessForm" method="post" hidden><?= csrf_field() ?><input type="hidden" name="action" value="access"><input type="hidden" name="access_token" id="accessToken"></form>
    <noscript>Enable JavaScript to open your private booking link, or contact reception.</noscript>
    <script>
    (function () {
        var token = new URLSearchParams(location.hash.slice(1)).get('access');
        history.replaceState(null, '', location.pathname);
        if (!token || !/^[a-f0-9]{64}$/.test(token)) {
            document.getElementById('accessMessage').textContent = 'Open the private booking link from your email, or contact reception.';
            return;
        }
        document.getElementById('accessToken').value = token;
        document.getElementById('accessForm').submit();
    })();
    </script></body></html>
    <?php exit;
}
$token = $_SESSION['discount_tokens'][$id] ?? '';
$d = pm_query($conn, 'SELECT d.*, b.guest_name,b.guest_email,b.accommodation_name,b.status FROM booking_discounts d JOIN bookings b ON b.id=d.booking_id WHERE d.booking_id=? AND d.token=?', [$id,$token])->get_result()->fetch_assoc();
if (!$d) { http_response_code(403); exit('Open the private link sent to your booking email to access this page.'); }
$message = $_SESSION['discount_notice'] ?? ''; unset($_SESSION['discount_notice']);
$error = '';
$settings = [];
$rs = $conn->query("SELECT setting_key,setting_value FROM settings WHERE setting_key IN ('gcash_number','gcash_name','discount_bank_name','discount_bank_account','discount_bank_holder')");
while ($s = $rs->fetch_assoc()) $settings[$s['setting_key']] = $s['setting_value'];
$gcash = !empty($settings['gcash_number']) && !empty($settings['gcash_name']);
$bank = !empty($settings['discount_bank_name']) && !empty($settings['discount_bank_account']) && !empty($settings['discount_bank_holder']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
    try {
        $action = $_POST['action'] ?? '';
        if ($action === 'pay') {
            $method = $_POST['method'] ?? '';
            if (($method === 'GCash QR' && !$gcash) || ($method === 'Bank Deposit' && !$bank)) throw new RuntimeException('This payment method is not configured.');
            $url = discount_start_payment($conn,$id,$method,trim($_POST['reference'] ?? ''),$_FILES['receipt'] ?? null);
            if ($url) { header('Location: ' . $url); exit; }
            $message = 'Receipt submitted. Staff will verify the incoming transaction before confirming payment.';
        } elseif ($action === 'refresh_payment') {
            require_once __DIR__ . '/../backend/services/paymongo.php';
            $checkout = pm_query($conn, "SELECT c.session_id FROM paymongo_checkouts c JOIN payments p ON p.id=c.payment_id WHERE c.booking_id=? AND p.status='pending' LIMIT 1", [$id])->get_result()->fetch_assoc();
            if ($checkout) {
                $result = PayMongoService::retrieveCheckoutSession($checkout['session_id']);
                if (!empty($result['success']) && !empty($result['is_paid'])) {
                    pm_confirm_checkout($conn,$id,$checkout['session_id'],$result['data']);
                    discount_email($conn,$id);
                    $message = 'Payment verified. Your booking is confirmed.';
                } else $message = 'Payment is not confirmed yet. Please check again shortly.';
            } else $message = 'Your latest booking status is displayed below.';
        } elseif ($action === 'switch_manual') {
            $conn->begin_transaction();
            try {
                $pendingPaymongo = pm_query($conn, "SELECT p.id FROM discount_payments x JOIN payments p ON p.id=x.payment_id WHERE x.booking_id=? AND p.status='pending' AND x.checkout_url IS NOT NULL FOR UPDATE", [$id])->get_result()->fetch_assoc();
                if ($pendingPaymongo) {
                    $pid = (int)$pendingPaymongo['id'];
                    pm_query($conn, "UPDATE payments SET status='cancelled' WHERE id=?", [$pid]);
                    pm_query($conn, "UPDATE discount_payments SET checkout_url=NULL, reference_key=NULL WHERE payment_id=?", [$pid]);
                    discount_audit($conn, $id, 'Guest', 'Switched payment method', 'Switched from PayMongo to manual transfer');
                }
                $conn->commit();
                $message = 'You can now submit your payment via GCash or Bank Transfer.';
            } catch (Throwable $e) {
                $conn->rollback();
                throw $e;
            }
        } elseif (in_array($action, ['regular','cancel','resubmit'], true)) {
            $newFile = null;
            if ($action === 'resubmit') $newFile = discount_upload($_FILES['discount_id'] ?? [], 'discount_ids');
            $conn->begin_transaction();
            try {
                $locked = discount_locked($conn,$id);
                if ($action === 'regular') {
                    if ($locked['state'] !== 'Discount declined') throw new RuntimeException('Regular-price acceptance is not available.');
                    $amounts = discount_amounts((int)$locked['original_centavos'],false);
                    pm_query($conn, "UPDATE booking_discounts SET state='Awaiting Deposit',total_centavos=?,deposit_centavos=? WHERE booking_id=?", [$amounts['total'],$amounts['deposit'],$id]);
                    pm_query($conn, 'UPDATE bookings SET discount_amount=0,promo_code=NULL WHERE id=?', [$id]);
                } elseif ($action === 'cancel') {
                    if (!in_array($locked['state'], ['Awaiting ID verification','More information needed','Discount declined','Awaiting Deposit'],true)) throw new RuntimeException('Contact reception to cancel a paid booking or a payment under review.');
                    if (pm_query($conn,"SELECT id FROM payments WHERE booking_id=? AND status IN ('pending','verified') LIMIT 1",[$id])->get_result()->fetch_assoc()) throw new RuntimeException('A payment has already been initiated. Contact reception to cancel.');
                    pm_query($conn,"UPDATE bookings SET status='Cancelled' WHERE id=?",[$id]);
                    pm_query($conn,"UPDATE booking_discounts SET state='Cancelled' WHERE booking_id=?",[$id]);
                } else {
                    if ($locked['state'] !== 'More information needed') throw new RuntimeException('A new ID has not been requested.');
                    pm_query($conn,"UPDATE booking_discounts SET state='Awaiting ID verification',id_file=?,reviewed_at=NULL,created_at=NOW() WHERE booking_id=?",[$newFile,$id]);
                }
                discount_audit($conn,$id,'Guest',$action);
                $conn->commit();
                if ($newFile && $locked['id_file']) { $old = private_storage_file('discount_ids/'.$locked['id_file']); if ($old) unlink($old); }
            } catch (Throwable $e) { $conn->rollback(); if ($newFile) @unlink(private_storage_directory('discount_ids').'/'.$newFile); throw $e; }
            $message = 'Your booking request has been updated.';
        } else throw new RuntimeException('Unknown action.');
        $_SESSION['discount_notice'] = $message;
        header('Location: discount_booking.php?booking='.$id); exit;
    } catch (Throwable $e) { $error = $e instanceof mysqli_sql_exception ? 'The request could not be completed. A payment reference may already be in use. Contact reception if needed.' : $e->getMessage(); }
}
$paid = (int)pm_query($conn,"SELECT COALESCE(ROUND(SUM(amount)*100),0) FROM payments WHERE booking_id=? AND status='verified'",[$id])->get_result()->fetch_row()[0];
$pending = pm_query($conn,"SELECT p.*,x.checkout_url FROM discount_payments x JOIN payments p ON p.id=x.payment_id WHERE x.booking_id=? AND p.status='pending' ORDER BY p.id DESC LIMIT 1",[$id])->get_result()->fetch_assoc();
$estimate = in_array($d['state'],['Awaiting ID verification','More information needed'],true);
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Your discount request · Santa Fe Beach Club</title><link rel="stylesheet" href="assets/css/discounts.css"><?php require __DIR__ . '/partials/_responsive.php'; ?>
</head>
<body class="discount-page"><main class="discount-shell"><a href="index.php">Santa Fe Beach Club</a><h1>Your booking & payment</h1><p>REF-<?= dh(str_pad((string)$id,3,'0',STR_PAD_LEFT)) ?> · <?= dh($d['accommodation_name']) ?></p>
<?php if ($message): ?><div class="discount-note" role="status"><?= dh($message) ?></div><?php endif ?>
<?php if ($error): ?><div class="discount-note discount-error" role="alert"><?= dh($error) ?></div><?php endif ?>
<section class="discount-card"><span class="discount-status"><?= dh($d['state']) ?></span><p><?= dh($d['guest_name']) ?> · <?= dh($d['check_in']) ?> to <?= dh($d['check_out']) ?></p>
<?php if ($d['state'] !== 'Confirmed'): ?><p>Room hold deadline: <strong><?= dh($d['hold_until']) ?> (Philippine time)</strong>. Approval and a verified deposit are required to confirm your reservation.</p><?php endif ?>
<?php if ($d['note']): ?><p>Staff message: <?= nl2br(dh($d['note'])) ?></p><?php endif ?>
<p><a href="<?= dh(discount_link($d)) ?>">Your private booking link</a> — save it and keep it private.</p></section>
<div class="discount-grid"><section class="discount-card"><h2><?= $estimate ? 'Estimated price — pending approval' : 'Booking price' ?></h2>
<div class="discount-money"><span>Original entire-room amount</span><strong>₱ <?= number_format($d['original_centavos']/100,2) ?></strong></div>
<div class="discount-money"><span><?= $d['total_centavos'] < $d['original_centavos'] ? '20% Senior Citizen / PWD discount' : 'Discount' ?></span><strong>−₱ <?= number_format(($d['original_centavos']-$d['total_centavos'])/100,2) ?></strong></div>
<div class="discount-money"><span>Total</span><strong>₱ <?= number_format($d['total_centavos']/100,2) ?></strong></div>
<div class="discount-money"><span>50% deposit</span><strong>₱ <?= number_format($d['deposit_centavos']/100,2) ?></strong></div>
<div class="discount-money"><span>Verified payments</span><strong>₱ <?= number_format($paid/100,2) ?></strong></div>
<p>Remaining total: ₱ <?= number_format(max(0,$d['total_centavos']-$paid)/100,2) ?>.<br>Balance due <?= dh(date('d M Y',strtotime($d['check_in'].' -1 day'))) ?>.</p></section>
<section class="discount-card"><h2>Next step</h2>
<?php if ($pending && !in_array($d['state'],['Expired','Cancelled'],true)): ?>
<p><?= $pending['checkout_url'] ? 'Your secure checkout is ready.' : 'Your receipt is awaiting staff verification. Please do not pay again.' ?></p>
<?php if ($pending['checkout_url']): ?>
<div style="display:flex; flex-wrap:wrap; gap:10px; align-items:center; margin-bottom:12px;">
    <a class="discount-button" href="<?= dh($pending['checkout_url']) ?>">Continue payment</a>
    <form method="post" style="display:inline;"><?= csrf_field() ?><button name="action" value="refresh_payment" class="secondary">Check payment status</button></form>
</div>
<?php if ($gcash || $bank): ?>
<form method="post" style="margin-top:16px; border-top:1px solid var(--line); padding-top:14px;">
    <?= csrf_field() ?>
    <p class="discount-muted" style="margin:0 0 8px 0; font-size:14px;">Prefer to pay directly using GCash or Bank account instead of online checkout?</p>
    <button name="action" value="switch_manual" class="secondary" style="font-size:14px; padding:8px 14px;">Prefer to pay manually via GCash / Bank? Upload receipt instead</button>
</form>
<?php endif ?>
<?php endif ?>
<?php elseif ($d['state']==='Confirmed'): ?>
<div style="background:#eef7f2; border:1px solid #c3e2d1; border-radius:12px; padding:20px; text-align:center;">
    <div style="font-size:32px; margin-bottom:8px;">🎉</div>
    <h3 style="margin:0 0 8px 0; color:#165e48;">Reservation Confirmed</h3>
    <p style="margin:0 0 12px 0; color:var(--ink); font-size:15px;">Your 50% deposit has been verified and your room is reserved.</p>
    <div style="background:#fff; border:1px solid #dce6df; border-radius:8px; padding:12px; margin-top:10px; font-size:14px; text-align:left;">
        <div><strong>Remaining Balance:</strong> ₱ <?= number_format(max(0, $d['total_centavos'] - $paid) / 100, 2) ?></div>
        <div style="color:var(--muted); margin-top:4px;">Please settle this remaining balance at the <strong>front desk during check-in</strong>.</div>
    </div>
</div>
<?php elseif (in_array($d['state'],['Awaiting Deposit'],true) && discount_due($conn,$d)>0): ?>
<p>Amount due now: <strong>₱ <?= number_format(discount_due($conn,$d)/100,2) ?></strong></p>
<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="pay"><input type="hidden" name="method" value="PayMongo Online"><button>Pay <?= $d['state']==='Confirmed'?'Balance':'Deposit' ?> with PayMongo</button></form>
<?php if ($gcash || $bank): ?><details open><summary>GCash / bank transfer</summary>
<?php if ($gcash): ?>
<div style="margin:16px 0; text-align:center; background:#f4f9f6; border:1px solid var(--line); border-radius:12px; padding:16px;">
    <p style="margin:0 0 10px; font-weight:600; color:var(--ink);">Scan to Pay with GCash</p>
    <div style="display:inline-block; background:#fff; padding:10px; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.08); border:1px solid #e0e7e3;">
        <img src="assets/gcash_qr.png?v=<?= file_exists(__DIR__.'/assets/gcash_qr.png') ? filemtime(__DIR__.'/assets/gcash_qr.png') : time(); ?>" alt="GCash QR Code" style="width:180px; height:180px; display:block; object-fit:contain;" onerror="this.src='https://api.qrserver.com/v1/create-qr-code/?size=180x180&amp;data=GCash%3A<?= urlencode($settings['gcash_number']) ?>%20Santa+Fe+Beach+Club'">
    </div>
    <div style="margin-top:10px; font-size:15px; color:var(--ink);">
        <div><strong>GCash:</strong> <?= dh($settings['gcash_number']) ?></div>
        <div><strong>Account Name:</strong> <?= dh($settings['gcash_name']) ?></div>
    </div>
</div>
<?php endif ?>
<?php if ($bank): ?><p style="margin:12px 0;"><strong><?= dh($settings['discount_bank_name']) ?>:</strong> <?= dh($settings['discount_bank_account']) ?><br><strong>Account Name:</strong> <?= dh($settings['discount_bank_holder']) ?></p><?php endif ?>
<form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="pay"><label>Payment method<select name="method"><?php if($gcash): ?><option>GCash QR</option><?php endif ?><?php if($bank): ?><option>Bank Deposit</option><?php endif ?></select></label><label>Receipt / Screenshot (JPG/PNG, up to 2 MB)<input name="receipt" type="file" accept="image/jpeg,image/png" required></label><p class="discount-muted" style="font-size:13px;">Staff will verify the actual incoming transaction in the resort GCash account before confirming your reservation.</p><button>Submit receipt</button></form></details><?php endif ?>
<?php elseif ($d['state']==='Discount declined'): ?><p>You may accept the regular price of ₱ <?= number_format($d['original_centavos']/100,2) ?> or cancel this unpaid request.</p><form method="post"><?= csrf_field() ?><button name="action" value="regular">Accept regular price</button><button name="action" value="cancel" class="secondary">Cancel request</button></form>
<?php elseif ($d['state']==='More information needed'): ?><form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="resubmit"><label>Replacement ID (JPG/PNG, up to 2 MB)<input type="file" name="discount_id" accept="image/jpeg,image/png" required></label><button>Resubmit for verification</button></form>
<?php else: ?><p><?= in_array($d['state'],['Expired','Cancelled'],true) ? 'This room hold has ended. Contact reception if you already sent a payment. Submit a new booking request to check availability.' : 'Staff will verify your ID. No payment is required while you wait.' ?></p><?php endif ?>
<?php if (!$pending && in_array($d['state'],['Awaiting ID verification','More information needed','Awaiting Deposit'],true)): ?><form method="post"><?= csrf_field() ?><button name="action" value="cancel" class="secondary">Cancel unpaid request</button></form><?php endif ?>
</section></div><p><a href="mailto:santafebeachclub@gmail.com">Contact reception</a> · Refresh this page to see the latest status.</p></main></body></html>
