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
<?php
$active_page = 'discount_requests';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-circle-32.png?v=3">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/favicon-circle-180.png?v=3">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discount Requests — Santa Fe Beach Club</title>
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="assets/css/discounts.css?v=<?php echo filemtime(__DIR__ . '/assets/css/discounts.css'); ?>">
</head>
<body>

<?php include __DIR__ . '/partials/_sidebar.php'; ?>

<main class="admin-main">
    <?php
    $page_title    = 'Discount Requests';
    $page_subtitle = $id ? ('Reviewing Request REF-' . str_pad((string)$id, 3, '0', STR_PAD_LEFT)) : 'Verify guest eligibility, review submitted IDs and receipts, and confirm bookings';
    include __DIR__ . '/partials/_page_header.php';
    ?>

    <div class="admin-body">
        <div class="discount-shell" style="max-width: 100%; margin: 0; padding: 0;">
            <?php if($id): ?>
            <div class="discount-nav-bar" style="margin-bottom: 20px;">
                <div class="discount-breadcrumb">
                    <a href="discount_requests.php">← Back to All Discount Requests</a>
                </div>
            </div>
            <?php endif; ?>

<?php if($message): ?><div class="discount-note" role="status"><span>ℹ️</span> <div><?= dh($message) ?></div></div><?php endif ?>
<?php if($error): ?><div class="discount-note discount-error" role="alert"><span>⚠️</span> <div><?= dh($error) ?></div></div><?php endif ?>

<?php if($d): 
    $state_class = 'status-pending';
    if(in_array($d['state'], ['Confirmed','Verified','Approved'], true)) $state_class = 'status-confirmed';
    elseif(in_array($d['state'], ['Cancelled','Declined','Expired','Rejected'], true)) $state_class = 'status-cancelled';
?>
<section class="discount-card">
    <div class="booking-hero-header">
        <div class="booking-hero-title">
            <span class="discount-status <?= $state_class ?>"><?= dh($d['state']) ?></span>
            <h2 style="margin-top: 8px;">REF-<?= dh(str_pad((string)$id,3,'0',STR_PAD_LEFT)) ?> &bull; <?= dh($d['guest_name']) ?></h2>
            <p><?= dh($d['guest_email']) ?> &bull; <?= dh($d['accommodation_name']) ?></p>
        </div>
        <div>
            <?php if($d['id_file']): ?>
                <button type="button" class="discount-button js-preview-doc" data-url="../backend/api/discount_document.php?booking=<?= $id ?>" data-title="Private ID · <?= dh($d['guest_name']) ?> (<?= dh(strtoupper($d['category'])) ?>)">
                    <span>🪪</span> Review Private ID
                </button>
            <?php else: ?>
                <span class="discount-status" style="background:#F1F5F9; color:var(--text-muted);">ID Archived</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="details-grid">
        <div class="detail-tile">
            <div class="tile-label">Stay Duration</div>
            <div class="tile-value"><?= dh($d['check_in']) ?> → <?= dh($d['check_out']) ?></div>
        </div>
        <div class="detail-tile">
            <div class="tile-label">Eligible Guest</div>
            <div class="tile-value"><?= dh($d['eligible_guest']) ?></div>
        </div>
        <div class="detail-tile">
            <div class="tile-label">Category</div>
            <div class="tile-value"><span class="discount-status" style="background:var(--primary-light); color:var(--primary);"><?= dh(strtoupper($d['category'])) ?></span></div>
        </div>
        <div class="detail-tile">
            <div class="tile-label">Hold Deadline</div>
            <div class="tile-value" style="color:var(--amber-text);"><?= dh($d['hold_until']) ?></div>
        </div>
    </div>

    <div class="pricing-breakdown-card">
        <div class="price-item">
            <span class="p-label">Original Rate</span>
            <span class="p-value">₱ <?= number_format($d['original_centavos']/100,2) ?></span>
        </div>
        <div class="price-item discount">
            <span class="p-label">Discount Applied</span>
            <span class="p-value">- ₱ <?= number_format(($d['original_centavos']-$d['total_centavos'])/100,2) ?></span>
        </div>
        <div class="price-item total">
            <span class="p-label">Total Amount</span>
            <span class="p-value">₱ <?= number_format($d['total_centavos']/100,2) ?></span>
        </div>
        <div class="price-item">
            <span class="p-label">Required Deposit</span>
            <span class="p-value">₱ <?= number_format($d['deposit_centavos']/100,2) ?></span>
        </div>
    </div>

    <?php if(in_array($d['state'],['Awaiting ID verification','More information needed'],true)): ?>
    <form method="post" style="background:#F8FAFC; padding:20px; border-radius:12px; border:1px solid var(--border); margin:20px 0;">
        <?= csrf_field() ?>
        <input type="hidden" name="booking_id" value="<?= $id ?>">
        <label style="margin-top:0;">Guest Message / Decision Reason
            <textarea name="note" maxlength="2000" rows="3" placeholder="Add any special notes or required documents..."></textarea>
        </label>
        <div class="actions-cluster">
            <button name="action" value="approve" class="discount-button success">✓ Approve 20% & Send Link</button>
            <button name="action" value="information" class="discount-button secondary">Request Information</button>
            <button name="action" value="decline" class="discount-button danger">Decline Discount</button>
        </div>
    </form>
    <?php endif ?>

    <?php if($d['note']): ?>
    <div style="background:#F8FAFC; border-left:4px solid var(--primary); padding:12px 16px; border-radius:6px; margin:16px 0; font-size:14px;">
        <strong style="color:var(--text-main);">Latest Staff Note:</strong><br>
        <span style="color:var(--text-muted);"><?= nl2br(dh($d['note'])) ?></span>
    </div>
    <?php endif ?>

    <div class="secret-link-box">
        <label>Private Guest Booking / Payment Link</label>
        <div class="secret-link-input-group">
            <input type="password" id="privateLink" readonly value="<?= dh(discount_link($d)) ?>">
            <button type="button" id="copyLink" class="discount-button secondary" style="white-space:nowrap;">Copy Link</button>
        </div>
        <span id="copyResult" role="status" style="display:block; margin-top:6px; font-size:12.5px; color:var(--green); font-weight:600;"></span>
    </div>

    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="booking_id" value="<?= $id ?>">
        <div class="actions-cluster">
            <button name="action" value="email" class="discount-button secondary">✉️ Resend Guest Email</button>
            <?php if(in_array($d['state'],['Awaiting Deposit','Confirmed'],true) && discount_due($conn,$d)>0): ?>
                <button name="action" value="checkout" class="discount-button">Create PayMongo Checkout & Email</button>
            <?php endif ?>
        </div>
        <?php if(in_array($d['state'],['Awaiting Deposit','Confirmed'],true) && discount_due($conn,$d)>0): ?>
            <small class="discount-muted" style="display:block; margin-top:8px;">Creating a checkout reserves the current payment attempt for PayMongo. Otherwise the guest can choose PayMongo or configured manual payment options.</small>
        <?php endif ?>
    </form>
</section>

<section class="discount-card">
    <div class="discount-card-title">
        <h2>Payment Records</h2>
    </div>
    <?php 
    $ps=pm_query($conn,'SELECT p.*,x.purpose,x.checkout_url FROM discount_payments x JOIN payments p ON p.id=x.payment_id WHERE x.booking_id=? ORDER BY p.id DESC',[$id])->get_result(); 
    if(!$ps->num_rows):
    ?>
        <p style="color:var(--text-muted); margin:0;">No payments logged for this booking yet.</p>
    <?php else: while($p=$ps->fetch_assoc()): 
        $p_badge = 'status-pending';
        if($p['status']==='verified' || $p['status']==='completed') $p_badge = 'status-confirmed';
        elseif($p['status']==='rejected' || $p['status']==='failed') $p_badge = 'status-cancelled';
    ?>
        <details class="payment-accordion-item" open>
            <summary>
                <div style="display:flex; align-items:center; gap:12px;">
                    <span class="discount-status <?= $p_badge ?>"><?= dh($p['status']) ?></span>
                    <strong style="color:var(--text-main); font-size:15px;"><?= dh(ucfirst($p['purpose'])) ?></strong>
                    <span style="color:var(--primary); font-weight:700;">₱ <?= number_format($p['amount'],2) ?></span>
                </div>
                <span style="font-size:13px; color:var(--text-muted); font-weight:normal;"><?= dh($p['payment_method']) ?></span>
            </summary>
            <div class="payment-accordion-body">
                <div style="margin-bottom:14px; color:var(--text-muted); font-size:14px;">
                    Method: <strong style="color:var(--text-main);"><?= dh($p['payment_method']) ?></strong> &bull; 
                    Reference: <code style="background:#F1F5F9; padding:2px 6px; border-radius:4px; font-family:monospace;"><?= dh($p['transaction_id'] ?: 'None') ?></code>
                </div>

                <?php if($p['receipt_url']): ?>
                    <button type="button" class="discount-button secondary js-preview-doc" data-url="../backend/api/discount_document.php?booking=<?= $id ?>&payment=<?= (int)$p['id'] ?>" data-title="Payment Receipt · <?= dh(ucfirst($p['purpose'])) ?> · ₱ <?= number_format($p['amount'],2) ?>">
                        <span>📄</span> View Uploaded Receipt
                    </button>
                <?php endif ?>

                <?php if($p['status']==='pending' && in_array($p['payment_method'],['GCash QR','Bank Deposit'],true)): ?>
                <form method="post" style="margin-top:18px; padding-top:16px; border-top:1px dashed var(--border);">
                    <?= csrf_field() ?>
                    <input type="hidden" name="booking_id" value="<?= $id ?>">
                    <input type="hidden" name="payment_id" value="<?= (int)$p['id'] ?>">
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-weight:500;">
                        <input type="checkbox" name="verified_transaction" value="1"> 
                        <span>I checked the actual incoming transaction, amount, and reference in the receiving account.</span>
                    </label>
                    <label style="margin-top:12px;">Verification Note / Rejection Reason
                        <textarea name="note" maxlength="2000" rows="2" placeholder="Optional notes..."></textarea>
                    </label>
                    <div class="actions-cluster">
                        <button name="action" value="verify" class="discount-button success">✓ Confirm Payment</button>
                        <button name="action" value="reject" class="discount-button danger">Reject Receipt</button>
                    </div>
                </form>
                <?php endif ?>
            </div>
        </details>
    <?php endwhile; endif; ?>
    <?php if(in_array($d['state'],['Expired','Cancelled'],true)): ?>
        <p style="color:var(--red-text); font-weight:500; margin-top:14px;">⚠️ This hold has ended. Investigate any received payment and arrange a refund or a new booking; do not confirm an expired room hold.</p>
    <?php endif ?>
</section>

<section class="discount-card">
    <div class="discount-card-title">
        <h2>Audit History</h2>
    </div>
    <div class="audit-timeline">
    <?php 
    $history=pm_query($conn,'SELECT * FROM discount_history WHERE booking_id=? ORDER BY id DESC',[$id])->get_result(); 
    if(!$history->num_rows):
    ?>
        <p style="color:var(--text-muted); margin:0;">No audit entries recorded yet.</p>
    <?php else: while($h=$history->fetch_assoc()): ?>
        <div class="audit-entry">
            <div class="audit-dot"></div>
            <div>
                <div class="audit-title"><?= dh($h['action']) ?></div>
                <div class="audit-meta"><?= dh($h['actor']) ?> &bull; <?= dh($h['created_at']) ?></div>
                <?php if($h['details']): ?>
                    <div class="audit-details"><?= dh($h['details']) ?></div>
                <?php endif; ?>
            </div>
        </div>
    <?php endwhile; endif; ?>
    </div>
</section>

<?php else: ?>
<section class="discount-card discount-table">
    <table>
        <thead>
            <tr>
                <th>Booking</th>
                <th>Guest &amp; Type</th>
                <th>Status</th>
                <th>Hold Deadline</th>
                <th style="text-align:right;">Action</th>
            </tr>
        </thead>
        <tbody>
        <?php 
        $rows=$conn->query('SELECT d.*,b.guest_name FROM booking_discounts d JOIN bookings b ON b.id=d.booking_id ORDER BY d.created_at DESC LIMIT 200'); 
        while($r=$rows->fetch_assoc()): 
            $st = 'status-pending';
            if(in_array($r['state'],['Confirmed','Verified','Approved'],true)) $st = 'status-confirmed';
            elseif(in_array($r['state'],['Cancelled','Declined','Expired','Rejected'],true)) $st = 'status-cancelled';
        ?>
            <tr>
                <td><strong>REF-<?= (int)$r['booking_id'] ?></strong></td>
                <td>
                    <div style="font-weight:600; color:var(--text-main);"><?= dh($r['guest_name']) ?></div>
                    <small style="color:var(--primary); font-weight:600;"><?= dh(strtoupper($r['category'])) ?></small>
                </td>
                <td><span class="discount-status <?= $st ?>"><?= dh($r['state']) ?></span></td>
                <td style="color:var(--text-muted); font-size:13px;"><?= dh($r['hold_until']) ?></td>
                <td style="text-align:right;">
                    <a href="?booking=<?= (int)$r['booking_id'] ?>" class="action-link">Review &rarr;</a>
                </td>
            </tr>
        <?php endwhile; ?>
        <?php if(!$rows->num_rows): ?>
            <tr><td colspan="5" style="text-align:center; padding:32px; color:var(--text-muted);">No discount requests yet.</td></tr>
        <?php endif ?>
        </tbody>
    </table>
</section>

<?php if(($_SESSION['admin_role']??'')==='admin'): ?>
<section class="discount-card">
    <div class="discount-card-title">
        <h2>Manual Payment Instructions</h2>
    </div>
    <p style="color:var(--text-muted); font-size:14px; margin-top:0;">Only configure accounts authorized to receive hotel payments. A method stays hidden until all its fields are supplied.</p>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="settings">
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:16px;">
            <?php foreach(['gcash_number'=>'GCash Number','gcash_name'=>'GCash Account Name','discount_bank_name'=>'Bank Name','discount_bank_account'=>'Bank Account Number','discount_bank_holder'=>'Bank Account Holder'] as $key=>$label): ?>
            <div>
                <label><?= dh($label) ?></label>
                <input name="<?= dh($key) ?>" value="<?= dh($settings[$key]??'') ?>" maxlength="150" placeholder="Enter <?= strtolower($label) ?>...">
            </div>
            <?php endforeach ?>
        </div>
        <div style="margin-top:20px;">
            <button class="discount-button">Save Payment Instructions</button>
        </div>
    </form>
</section>
<?php endif ?>
<?php endif ?>
        </div><!-- /.discount-shell -->
    </div><!-- /.admin-body -->
</main>
<div id="imageModal" class="image-modal-overlay" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="image-modal-content">
        <div class="image-modal-header">
            <h3 id="imageModalTitle">Document Preview</h3>
            <button type="button" class="image-modal-close" id="closeImageModal" aria-label="Close">&times;</button>
        </div>
        <div class="image-modal-body">
            <div id="imageModalLoading" class="document-modal-message" role="status">Loading document&hellip;</div>
            <img id="imageModalImage" alt="Document preview" hidden>
            <iframe id="imageModalPdf" title="PDF document preview" hidden></iframe>
            <div id="imageModalError" class="document-modal-message" role="alert" hidden>
                <div class="document-modal-icon" aria-hidden="true">&#128196;</div>
                <strong>Document unavailable</strong>
                <p>The file could not be loaded. It may have been archived or removed from private storage.</p>
            </div>
        </div>
    </div>
</div>
<script>
document.getElementById('copyLink')?.addEventListener('click',async()=>{const field=document.getElementById('privateLink');try{await navigator.clipboard.writeText(field.value);document.getElementById('copyResult').textContent='Link copied.';}catch(e){field.select();document.getElementById('copyResult').textContent='Select and copy the link above.';}});

// In-page modal preview for private ID and receipts
const modal = document.getElementById('imageModal');
const modalTitle = document.getElementById('imageModalTitle');
const modalImage = document.getElementById('imageModalImage');
const modalPdf = document.getElementById('imageModalPdf');
const modalLoading = document.getElementById('imageModalLoading');
const modalError = document.getElementById('imageModalError');
const closeBtn = document.getElementById('closeImageModal');
let documentObjectUrl = null;
let documentRequest = null;

document.querySelectorAll('.js-preview-doc').forEach(btn => {
    btn.addEventListener('click', async () => {
        const url = btn.getAttribute('data-url');
        const title = btn.getAttribute('data-title') || 'Document Preview';
        modalTitle.textContent = title;
        modalImage.hidden = true;
        modalPdf.hidden = true;
        modalError.hidden = true;
        modalLoading.hidden = false;
        modal.classList.add('active');
        modal.setAttribute('aria-hidden', 'false');

        documentRequest?.abort();
        documentRequest = new AbortController();
        const activeRequest = documentRequest;
        if (documentObjectUrl) URL.revokeObjectURL(documentObjectUrl);
        documentObjectUrl = null;

        try {
            const response = await fetch(url, {
                credentials: 'same-origin',
                cache: 'no-store',
                signal: activeRequest.signal
            });
            if (!response.ok) throw new Error(`Document request failed (${response.status})`);
            const documentBlob = await response.blob();
            if (!documentBlob.size) throw new Error('The document is empty');
            if (!documentBlob.type.startsWith('image/') && documentBlob.type !== 'application/pdf') {
                throw new Error('Unsupported document type');
            }
            documentObjectUrl = URL.createObjectURL(documentBlob);
            if (documentBlob.type.startsWith('image/')) {
                await new Promise((resolve, reject) => {
                    modalImage.onload = resolve;
                    modalImage.onerror = () => reject(new Error('The image data is invalid'));
                    modalImage.src = documentObjectUrl;
                });
                modalImage.hidden = false;
            } else {
                modalPdf.src = documentObjectUrl;
                modalPdf.hidden = false;
            }
            modalLoading.hidden = true;
        } catch (error) {
            if (error.name === 'AbortError' || documentRequest !== activeRequest) return;
            modalLoading.hidden = true;
            modalError.hidden = false;
        }
    });
});

function closeModal() {
    documentRequest?.abort();
    documentRequest = null;
    modal.classList.remove('active');
    modal.setAttribute('aria-hidden', 'true');
    modalImage.removeAttribute('src');
    modalImage.hidden = true;
    modalPdf.src = 'about:blank';
    modalPdf.hidden = true;
    modalError.hidden = true;
    modalLoading.hidden = false;
    if (documentObjectUrl) URL.revokeObjectURL(documentObjectUrl);
    documentObjectUrl = null;
}

closeBtn?.addEventListener('click', closeModal);
modal?.addEventListener('click', (e) => {
    if (e.target === modal) closeModal();
});
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && modal.classList.contains('active')) closeModal();
});
</script></body></html>
