<?php
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/helpers/csrf_helper.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$token = trim((string)($_GET['token'] ?? ''));
$valid = false;
$refund = null;
if (preg_match('/^[a-f0-9]{64}$/', $token)) {
    $hash = hash('sha256', $token);
    $stmt = $conn->prepare("SELECT r.net_refund,r.booking_id,b.guest_name,d.token_expires_at FROM manual_refund_details d JOIN refund_requests r ON r.id=d.refund_request_id JOIN bookings b ON b.id=r.booking_id WHERE d.token_hash=? AND d.token_expires_at>NOW() AND d.submitted_at IS NULL AND r.status='awaiting_guest_details' LIMIT 1");
    $stmt->bind_param('s', $hash); $stmt->execute(); $refund = $stmt->get_result()->fetch_assoc(); $stmt->close();
    $valid = (bool)$refund;
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>GCash Refund Details</title>
<style>*{box-sizing:border-box}body{margin:0;background:#f5f7fb;color:#172033;font-family:Segoe UI,Arial,sans-serif;padding:24px}.shell{max-width:620px;margin:40px auto}.card{background:#fff;border:1px solid #e4e8f0;border-radius:18px;box-shadow:0 18px 45px rgba(15,23,42,.08);overflow:hidden}.head{padding:26px 30px;background:linear-gradient(135deg,#0668d8,#08a4ef);color:#fff}.head h1{margin:0 0 7px;font-size:24px}.body{padding:28px 30px}.summary{background:#f0f7ff;border:1px solid #cfe5ff;border-radius:12px;padding:15px;margin-bottom:22px;line-height:1.6}.field{margin:0 0 17px}.field label{display:block;font-weight:700;font-size:14px;margin-bottom:7px}.field input{width:100%;padding:12px 13px;border:1.5px solid #ccd4e1;border-radius:9px;font-size:15px}.check{display:flex;gap:10px;align-items:flex-start;font-size:13px;line-height:1.5;margin:18px 0}.check input{margin-top:3px}.warning{font-size:13px;color:#7c4a03;background:#fff8e7;border-left:4px solid #f59e0b;padding:12px;margin:18px 0}.btn{width:100%;border:0;border-radius:9px;background:#087ce0;color:#fff;padding:13px;font-weight:800;font-size:15px;cursor:pointer}.btn:disabled{opacity:.6}.msg{display:none;padding:13px;border-radius:9px;margin-bottom:16px}.msg.ok{display:block;background:#eafaf1;color:#11683b}.msg.err{display:block;background:#fff0f0;color:#9b2020}</style></head><body><main class="shell"><section class="card"><div class="head"><h1>GCash refund details</h1><div>Santa Fe Beach Club secure refund form</div></div><div class="body">
<?php if (!$valid): ?><div class="msg err">This link is invalid, expired, or has already been used. Please contact the resort for a new link.</div>
<?php else: ?><div class="summary"><strong>REF-<?= str_pad((string)$refund['booking_id'],3,'0',STR_PAD_LEFT) ?></strong><br>Guest: <?= htmlspecialchars($refund['guest_name']) ?><br>Approved refund: <strong>₱<?= number_format((float)$refund['net_refund'],2) ?></strong><br>Link expires: <?= htmlspecialchars(date('M j, Y g:i A',strtotime($refund['token_expires_at']))) ?></div>
<div id="message" class="msg"></div><form id="detailsForm"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(get_csrf_token()) ?>"><input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
<div class="field"><label for="accountName">GCash account name</label><input id="accountName" name="gcash_account_name" maxlength="120" autocomplete="name" required></div>
<div class="field"><label for="mobile">GCash mobile number</label><input id="mobile" name="gcash_mobile" inputmode="numeric" autocomplete="tel" maxlength="11" placeholder="09XXXXXXXXX" required></div>
<label class="check"><input type="checkbox" name="ownership_confirmed" value="1" required><span>I confirm that I own or am authorized to receive the refund through this GCash account and that the details are correct.</span></label>
<div class="warning"><strong>Security reminder:</strong> We will never ask for your GCash MPIN, password, OTP, or wallet balance.</div><button class="btn" id="submitBtn">Submit refund details</button></form>
<script>document.getElementById('detailsForm').addEventListener('submit',async e=>{e.preventDefault();const b=document.getElementById('submitBtn'),m=document.getElementById('message');b.disabled=true;b.textContent='Submitting...';m.className='msg';try{const r=await fetch('../backend/api/manual_refund_api.php?action=guest_submit',{method:'POST',body:new FormData(e.target)}),d=await r.json();if(!d.success)throw new Error(d.message||'Submission failed.');m.className='msg ok';m.textContent=d.message;e.target.remove();}catch(x){m.className='msg err';m.textContent=x.message||'Please try again.';b.disabled=false;b.textContent='Submit refund details';}});</script>
<?php endif; ?></div></section></main></body></html>
