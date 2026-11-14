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
        if (in_array($action,['approve','decline','information'],true)) {
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
<?php require __DIR__ . '/partials/_responsive.php'; ?>
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

<?php if($message): ?><div class="discount-note" role="status"><span><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg></span> <div><?= dh($message) ?></div></div><?php endif ?>
<?php if($error): ?><div class="discount-note discount-error" role="alert"><span><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></span> <div><?= dh($error) ?></div></div><?php endif ?>

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
                    <span><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><line x1="15" y1="9" x2="19" y2="9"/><line x1="15" y1="13" x2="19" y2="13"/><path d="M6 16c.6-1.5 2-2 3-2s2.4.5 3 2"/></svg></span> Review Private ID
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
            <button name="action" value="approve" class="discount-button success">Approve 20% & Send Link</button>
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
            <button name="action" value="email" class="discount-button secondary">Resend Guest Email</button>
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
                        <span><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:-2px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></span> View Uploaded Receipt
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
                        <button name="action" value="verify" class="discount-button success">Confirm Payment</button>
                        <button name="action" value="reject" class="discount-button danger">Reject Receipt</button>
                    </div>
                </form>
                <?php endif ?>
            </div>
        </details>
    <?php endwhile; endif; ?>
    <?php if(in_array($d['state'],['Expired','Cancelled'],true)): ?>
        <p style="color:var(--red-text); font-weight:500; margin-top:14px;">This hold has ended. Investigate any received payment and arrange a refund or a new booking; do not confirm an expired room hold.</p>
    <?php endif ?>
</section>

<section class="discount-card">
    <?php
    $historyResult=pm_query($conn,'SELECT * FROM discount_history WHERE booking_id=? ORDER BY id DESC',[$id])->get_result();
    $historyRows=$historyResult->fetch_all(MYSQLI_ASSOC);
    $historyCount=count($historyRows);
    ?>
    <div class="discount-card-title audit-heading">
        <div>
            <h2>Audit History</h2>
            <p>Latest booking activity</p>
        </div>
        <span class="audit-count"><?= $historyCount ?> <?= $historyCount === 1 ? 'event' : 'events' ?></span>
    </div>
    <div class="audit-timeline" id="auditTimeline">
    <?php if(!$historyCount): ?>
        <p style="color:var(--text-muted); margin:0;">No audit entries recorded yet.</p>
    <?php else: foreach($historyRows as $index=>$h): ?>
        <div class="audit-entry<?= $index >= 5 ? ' audit-entry-extra' : '' ?>"<?= $index >= 5 ? ' hidden' : '' ?>>
            <div class="audit-dot" aria-hidden="true"></div>
            <div class="audit-content">
                <div class="audit-title"><?= dh($h['action']) ?></div>
                <?php if($h['details']): ?>
                    <div class="audit-details"><?= dh($h['details']) ?></div>
                <?php endif; ?>
            </div>
            <div class="audit-meta">
                <span><?= dh($h['actor']) ?></span>
                <time datetime="<?= dh($h['created_at']) ?>"><?= dh(date('M j, Y · g:i A', strtotime($h['created_at']))) ?></time>
            </div>
        </div>
    <?php endforeach; endif; ?>
    </div>
    <?php if($historyCount > 5): ?>
        <button type="button" class="audit-toggle" id="auditToggle" aria-expanded="false" aria-controls="auditTimeline">
            Show <?= $historyCount - 5 ?> more events <span aria-hidden="true">&#8595;</span>
        </button>
    <?php endif; ?>
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
                <div class="document-modal-icon" aria-hidden="true"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></div>
                <strong>Document unavailable</strong>
                <p id="imageModalErrorDetail">The file could not be loaded.</p>
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
const modalErrorDetail = document.getElementById('imageModalErrorDetail');
const closeBtn = document.getElementById('closeImageModal');
let documentObjectUrl = null;
let documentRequest = null;

const auditToggle = document.getElementById('auditToggle');
auditToggle?.addEventListener('click', () => {
    const expanded = auditToggle.getAttribute('aria-expanded') === 'true';
    document.querySelectorAll('.audit-entry-extra').forEach(entry => { entry.hidden = expanded; });
    auditToggle.setAttribute('aria-expanded', String(!expanded));
    auditToggle.innerHTML = expanded
        ? `Show <?= max(0, ($historyCount ?? 0) - 5) ?> more events <span aria-hidden="true">&#8595;</span>`
        : 'Show less <span aria-hidden="true">&#8593;</span>';
});

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
            if (!response.ok) {
                const serverMessage = (await response.text()).trim().slice(0, 160);
                throw new Error(`Server returned HTTP ${response.status}${serverMessage ? `: ${serverMessage}` : ''}`);
            }
            const documentBlob = await response.blob();
            if (!documentBlob.size) throw new Error('The server returned an empty document');
            if (!documentBlob.type.startsWith('image/') && documentBlob.type !== 'application/pdf') {
                throw new Error(`The server returned an unsupported document type (${documentBlob.type || 'unknown'})`);
            }
            if (documentBlob.type.startsWith('image/')) {
                const imageDataUrl = await new Promise((resolve, reject) => {
                    const reader = new FileReader();
                    reader.onload = () => resolve(reader.result);
                    reader.onerror = () => reject(new Error('The downloaded image could not be read'));
                    reader.readAsDataURL(documentBlob);
                });
                await new Promise((resolve, reject) => {
                    modalImage.onload = resolve;
                    modalImage.onerror = () => reject(new Error('The image data is invalid'));
                    modalImage.src = imageDataUrl;
                });
                modalImage.hidden = false;
            } else {
                documentObjectUrl = URL.createObjectURL(documentBlob);
                modalPdf.src = documentObjectUrl;
                modalPdf.hidden = false;
            }
            modalLoading.hidden = true;
        } catch (error) {
            if (error.name === 'AbortError' || documentRequest !== activeRequest) return;
            modalLoading.hidden = true;
            modalErrorDetail.textContent = error instanceof Error ? error.message : 'The document could not be loaded.';
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
