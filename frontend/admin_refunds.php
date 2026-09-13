<?php
require_once __DIR__ . '/../backend/helpers/admin_auth_check.php';
require_once __DIR__ . '/../backend/config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$page_title = 'Refund Requests Management';

// Get filter status
$filter_status = trim($_GET['status'] ?? 'requested');
$valid_statuses = ['requested', 'approved', 'processing', 'completed', 'rejected'];
if (!in_array($filter_status, $valid_statuses)) {
    $filter_status = 'requested';
}

// Fetch refund requests with the selected status
$query = "
    SELECT r.id, r.booking_id, r.requested_by, r.status, r.reason,
           r.refund_amount, r.refund_fee, r.net_refund,
           r.approved_by, r.approved_at, r.rejection_reason, r.requested_at,
           b.guest_name, b.guest_email, b.accommodation_name,
           b.check_in, b.check_out, p.amount as original_amount
    FROM refund_requests r
    JOIN bookings b ON r.booking_id = b.id
    LEFT JOIN payments p ON r.payment_id = p.id
    WHERE r.status = ?
    ORDER BY
        CASE WHEN r.status = 'requested' THEN 1 ELSE 2 END,
        r.requested_at DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $filter_status);
$stmt->execute();
$result = $stmt->get_result();
$refunds = [];
while ($row = $result->fetch_assoc()) {
    $refunds[] = $row;
}
$stmt->close();

// Get count for each status
$status_counts = [];
foreach ($valid_statuses as $s) {
    $count_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM refund_requests WHERE status = ?");
    $count_stmt->bind_param("s", $s);
    $count_stmt->execute();
    $status_counts[$s] = $count_stmt->get_result()->fetch_assoc()['cnt'];
    $count_stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/jpeg" href="assets/logo.jpg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Refund Requests — Admin</title>
    <link rel="stylesheet" href="assets/css/admin.css?v=4">
    <style>
        .filter-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .filter-tab {
            padding: 8px 16px;
            border: 1.5px solid var(--border);
            background: var(--card-bg);
            color: var(--text-muted);
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            border-radius: 6px;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .filter-tab:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .filter-tab.active {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
        }

        .tab-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 18px;
            height: 18px;
            padding: 0 4px;
            border-radius: 9px;
            font-size: 10px;
            font-weight: 700;
            background: rgba(255,255,255,0.25);
            color: inherit;
        }

        .filter-tab:not(.active) .tab-badge {
            background: var(--bg-alt, #f1f5f9);
            color: var(--text-muted);
        }

        .refund-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 14px;
            transition: box-shadow 0.2s ease, border-color 0.2s ease;
        }

        .refund-card:hover {
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
            border-color: #cbd5e1;
        }

        .refund-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 14px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .refund-ref {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-main);
        }

        .status-badge-large {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-left: 8px;
        }

        .status-requested  { background: #fef08a; color: #854d0e; }
        .status-approved   { background: #dcfce7; color: #15803d; }
        .status-processing { background: #e0e7ff; color: #3730a3; }
        .status-completed  { background: #dbeafe; color: #0c4a6e; }
        .status-rejected   { background: #fee2e2; color: #991b1b; }

        .refund-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 14px;
            margin-bottom: 14px;
        }

        .info-item { display: flex; flex-direction: column; }

        .info-label {
            font-size: 11px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 3px;
        }

        .info-value {
            font-size: 13.5px;
            color: var(--text-main);
            font-weight: 500;
        }

        .amount-highlight {
            font-size: 20px;
            font-weight: 700;
            color: var(--primary);
        }

        .refund-reason {
            background: var(--bg-alt, #f8fafc);
            padding: 10px 14px;
            border-radius: 8px;
            margin-bottom: 14px;
            border-left: 3px solid var(--primary);
        }

        .refund-reason-label {
            font-size: 10px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            margin-bottom: 3px;
        }

        .refund-reason-text {
            font-size: 13px;
            color: var(--text-main);
            line-height: 1.5;
        }

        .refund-actions {
            display: flex;
            gap: 10px;
            padding-top: 14px;
            border-top: 1px solid var(--border);
            flex-wrap: wrap;
            align-items: center;
        }

        .btn-approve {
            padding: 8px 16px;
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-approve:hover { background: #bbf7d0; }

        .btn-reject {
            padding: 8px 16px;
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-reject:hover { background: #fecaca; }

        .btn-process {
            padding: 8px 16px;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-process:hover { opacity: 0.9; }
        .btn-process:disabled { opacity: 0.6; cursor: not-allowed; }

        .btn-secondary-sm {
            padding: 8px 14px;
            background: var(--card-bg);
            color: var(--text-muted);
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-secondary-sm:hover { background: var(--bg-alt, #f1f5f9); }

        /* Modal */
        .modal-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 1000;
            background: rgba(0,0,0,0.45);
            backdrop-filter: blur(2px);
            animation: fadeIn 0.2s ease;
        }
        .modal-backdrop.show { display: flex; align-items: center; justify-content: center; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        .modal-box {
            background: var(--card-bg);
            border-radius: 14px;
            padding: 28px;
            max-width: 480px;
            width: 92%;
            box-shadow: 0 20px 40px rgba(0,0,0,0.18);
            animation: slideUp 0.25s ease;
        }
        @keyframes slideUp {
            from { transform: translateY(16px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-title {
            font-size: 17px;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 16px;
        }

        .modal-field {
            margin-bottom: 14px;
        }

        .modal-field label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        .modal-field textarea {
            width: 100%;
            padding: 10px;
            border: 1.5px solid var(--border);
            border-radius: 6px;
            font-family: inherit;
            font-size: 13px;
            resize: vertical;
            min-height: 80px;
            background: var(--input-bg, #fff);
            color: var(--text-main);
        }

        .modal-field textarea:focus {
            outline: none;
            border-color: var(--primary);
        }

        .modal-footer {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .loading-dot {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        .empty-refunds {
            text-align: center;
            padding: 56px 20px;
            background: var(--card-bg);
            border: 2px dashed var(--border);
            border-radius: 12px;
            color: var(--text-muted);
        }
        .empty-refunds-icon { font-size: 44px; margin-bottom: 12px; }
        .empty-refunds h3 { font-size: 17px; font-weight: 600; color: var(--text-main); margin-bottom: 6px; }
        .empty-refunds p { font-size: 13px; }

        .rejection-note {
            background: #fee2e2;
            border-left: 3px solid #f87171;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
            color: #991b1b;
        }
    </style>
</head>
<body>

    <?php $active_page = 'admin_refunds'; include __DIR__ . '/partials/_sidebar.php'; ?>

    <main class="admin-main">
        <?php
        $page_title    = 'Refund Requests';
        $page_subtitle = 'Review and process guest refund requests with multi-step approval workflow';
        include __DIR__ . '/partials/_page_header.php';
        ?>

        <div class="admin-body">

            <!-- Status Tabs -->
            <div class="filter-tabs">
                <?php
                $tab_labels = [
                    'requested'  => ['📋 Pending', 'Awaiting approval'],
                    'approved'   => ['✅ Approved', 'Ready to process'],
                    'processing' => ['⏳ Processing', 'PayMongo initiated'],
                    'completed'  => ['✓ Completed', 'Refund sent'],
                    'rejected'   => ['❌ Rejected', 'Denied'],
                ];
                foreach ($valid_statuses as $s):
                    $label = $tab_labels[$s][0] ?? $s;
                    $cnt   = $status_counts[$s] ?? 0;
                ?>
                <a href="?status=<?php echo urlencode($s); ?>"
                   class="filter-tab <?php echo $filter_status === $s ? 'active' : ''; ?>">
                    <?php echo $label; ?>
                    <?php if ($cnt > 0): ?>
                        <span class="tab-badge"><?php echo $cnt; ?></span>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- Refund Cards -->
            <?php if (empty($refunds)): ?>
                <div class="empty-refunds">
                    <div class="empty-refunds-icon">📭</div>
                    <h3>No Refund Requests</h3>
                    <p>There are no refund requests with status: <strong><?php echo htmlspecialchars($filter_status); ?></strong></p>
                </div>
            <?php else: ?>
                <?php foreach ($refunds as $refund): ?>
                <div class="refund-card">
                    <div class="refund-header">
                        <div>
                            <div class="refund-ref">
                                REF-<?php echo str_pad($refund['booking_id'], 3, '0', STR_PAD_LEFT); ?>
                                <span class="status-badge-large status-<?php echo htmlspecialchars($refund['status']); ?>">
                                    <?php echo htmlspecialchars($refund['status']); ?>
                                </span>
                            </div>
                            <div style="font-size: 13px; color: var(--text-muted); margin-top: 3px;">
                                <?php echo htmlspecialchars($refund['guest_name']); ?>
                                &nbsp;·&nbsp;
                                <a href="mailto:<?php echo htmlspecialchars($refund['guest_email']); ?>"
                                   style="color: var(--primary); text-decoration: none; font-size: 12px;">
                                    <?php echo htmlspecialchars($refund['guest_email']); ?>
                                </a>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <div class="amount-highlight">₱ <?php echo number_format($refund['net_refund'], 2); ?></div>
                            <?php if ($refund['refund_fee'] > 0): ?>
                            <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">
                                Cancellation fee: ₱ <?php echo number_format($refund['refund_fee'], 2); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Details Grid -->
                    <div class="refund-info">
                        <div class="info-item">
                            <span class="info-label">Accommodation</span>
                            <span class="info-value"><?php echo htmlspecialchars($refund['accommodation_name']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Check-in</span>
                            <span class="info-value"><?php echo date('M j, Y', strtotime($refund['check_in'])); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Check-out</span>
                            <span class="info-value"><?php echo date('M j, Y', strtotime($refund['check_out'])); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Requested On</span>
                            <span class="info-value"><?php echo date('M j, Y g:i A', strtotime($refund['requested_at'])); ?></span>
                        </div>
                        <?php if ($refund['approved_by']): ?>
                        <div class="info-item">
                            <span class="info-label">Reviewed By</span>
                            <span class="info-value"><?php echo htmlspecialchars($refund['approved_by']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Reason -->
                    <div class="refund-reason">
                        <div class="refund-reason-label">Guest Reason</div>
                        <div class="refund-reason-text"><?php echo htmlspecialchars($refund['reason'] ?? 'No reason provided'); ?></div>
                    </div>

                    <!-- Rejection note (if rejected) -->
                    <?php if ($refund['status'] === 'rejected' && $refund['rejection_reason']): ?>
                    <div class="rejection-note" style="margin-bottom: 14px;">
                        <strong>Rejection Reason:</strong> <?php echo htmlspecialchars($refund['rejection_reason']); ?>
                    </div>
                    <?php endif; ?>

                    <!-- Actions -->
                    <div class="refund-actions">
                        <?php if ($filter_status === 'requested'): ?>
                            <button class="btn-approve" onclick="approveRefund(<?php echo $refund['id']; ?>, '<?php echo htmlspecialchars(addslashes($refund['guest_name'])); ?>', '<?php echo number_format($refund['net_refund'], 2); ?>')">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                Approve Request
                            </button>
                            <button class="btn-reject" onclick="openRejectModal(<?php echo $refund['id']; ?>, '<?php echo htmlspecialchars(addslashes($refund['guest_name'])); ?>')">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                Reject
                            </button>
                        <?php elseif ($filter_status === 'approved'): ?>
                            <button class="btn-process" id="process-btn-<?php echo $refund['id']; ?>" onclick="processRefund(<?php echo $refund['id']; ?>)">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="4" width="20" height="16" rx="2"/><line x1="12" y1="10" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                                Process to PayMongo
                            </button>
                        <?php endif; ?>
                        <button class="btn-secondary-sm" onclick="viewDetails(<?php echo $refund['id']; ?>)">
                            View Details
                        </button>
                        <span style="font-size: 12px; color: var(--text-muted); margin-left: auto;">
                            Original amount: ₱<?php echo number_format($refund['original_amount'] ?? $refund['refund_amount'] + $refund['refund_fee'], 2); ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>

        </div><!-- /.admin-body -->
    </main>

    <!-- Reject Modal -->
    <div id="rejectModal" class="modal-backdrop">
        <div class="modal-box">
            <div class="modal-title">❌ Reject Refund Request</div>
            <div class="modal-field">
                <label>Guest</label>
                <div id="rejectGuestName" style="padding: 8px 12px; background: var(--bg-alt, #f8fafc); border-radius: 6px; font-size: 13.5px; font-weight: 600; color: var(--text-main);"></div>
            </div>
            <div class="modal-field">
                <label for="rejectionReason">Rejection Reason *</label>
                <textarea id="rejectionReason" placeholder="Explain why this refund is being declined. The guest will be notified with this reason..." required></textarea>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary-sm" onclick="closeModal('rejectModal')">Cancel</button>
                <button class="btn-reject" id="rejectBtn" onclick="submitReject()">Reject Request</button>
            </div>
        </div>
    </div>

    <!-- Details Modal -->
    <div id="detailsModal" class="modal-backdrop">
        <div class="modal-box" style="max-width: 520px;">
            <div class="modal-title">📋 Refund Request Details</div>
            <div id="detailsContent" style="font-size: 13.5px; line-height: 1.7; color: var(--text-main);"></div>
            <div class="modal-footer">
                <button class="btn-secondary-sm" onclick="closeModal('detailsModal')">Close</button>
            </div>
        </div>
    </div>

    <script>
        let currentRefundId = null;

        function approveRefund(refundId, guestName, amount) {
            if (!confirm(`Approve the refund request for ${guestName}?\n\nAmount to be refunded: ₱${amount}\n\nThis will notify the guest and move the request to "Approved".`)) return;

            const btn = event.target.closest('button');
            const origHTML = btn.innerHTML;
            btn.innerHTML = '<span class="loading-dot"></span> Approving...';
            btn.disabled = true;

            const fd = new FormData();
            fd.append('request_id', refundId);

            fetch('../backend/api/refund_request_api.php?action=approve', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        showToast('✓ Refund approved! Ready for PayMongo processing.', 'success');
                        setTimeout(() => location.reload(), 1200);
                    } else {
                        btn.innerHTML = origHTML;
                        btn.disabled = false;
                        showToast('Error: ' + data.message, 'error');
                    }
                })
                .catch(() => {
                    btn.innerHTML = origHTML;
                    btn.disabled = false;
                    showToast('Network error. Please try again.', 'error');
                });
        }

        function openRejectModal(refundId, guestName) {
            currentRefundId = refundId;
            document.getElementById('rejectGuestName').textContent = guestName;
            document.getElementById('rejectionReason').value = '';
            document.getElementById('rejectModal').classList.add('show');
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('show');
            currentRefundId = null;
        }

        function submitReject() {
            const reason = document.getElementById('rejectionReason').value.trim();
            if (!reason) { showToast('Please enter a rejection reason.', 'warning'); return; }

            const btn = document.getElementById('rejectBtn');
            btn.innerHTML = '<span class="loading-dot"></span> Rejecting...';
            btn.disabled = true;

            const fd = new FormData();
            fd.append('request_id', currentRefundId);
            fd.append('rejection_reason', reason);

            fetch('../backend/api/refund_request_api.php?action=reject', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        closeModal('rejectModal');
                        showToast('✓ Refund rejected. Guest has been notified.', 'success');
                        setTimeout(() => location.reload(), 1200);
                    } else {
                        btn.innerHTML = 'Reject Request';
                        btn.disabled = false;
                        showToast('Error: ' + data.message, 'error');
                    }
                })
                .catch(() => {
                    btn.innerHTML = 'Reject Request';
                    btn.disabled = false;
                    showToast('Network error. Please try again.', 'error');
                });
        }

        function processRefund(refundId) {
            if (!confirm('Process this refund to PayMongo?\n\nThis will initiate the actual payment refund and notify the guest by email.\n\nThis action cannot be undone.')) return;

            const btn = document.getElementById(`process-btn-${refundId}`);
            btn.innerHTML = '<span class="loading-dot"></span> Processing...';
            btn.disabled = true;

            const fd = new FormData();
            fd.append('request_id', refundId);

            fetch('../backend/api/refund_request_api.php?action=process_paymongo', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        showToast('✓ Refund processed. Guest will receive it within 3-5 business days.', 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="4" width="20" height="16" rx="2"/><line x1="12" y1="10" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg> Process to PayMongo';
                        btn.disabled = false;
                        showToast('Error: ' + data.message, 'error');
                    }
                })
                .catch(() => {
                    btn.innerHTML = 'Process to PayMongo';
                    btn.disabled = false;
                    showToast('Network error. Please try again.', 'error');
                });
        }

        function viewDetails(refundId) {
            fetch(`../backend/api/refund_request_api.php?action=get_request&request_id=${refundId}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const r = data.data;
                        const fmtAmt = v => v ? '₱' + parseFloat(v).toLocaleString('en-PH', {minimumFractionDigits:2}) : '—';
                        document.getElementById('detailsContent').innerHTML = `
                            <table style="width:100%; border-collapse:collapse; font-size:13px;">
                                <tr><td style="padding:7px 0; color:var(--text-muted); width:140px;">Request ID</td><td style="font-weight:600;">#${r.id}</td></tr>
                                <tr><td style="padding:7px 0; color:var(--text-muted);">Booking Ref</td><td style="font-weight:600;">REF-${String(r.booking_id).padStart(3,'0')}</td></tr>
                                <tr><td style="padding:7px 0; color:var(--text-muted);">Guest</td><td>${r.guest_name} (${r.guest_email})</td></tr>
                                <tr><td style="padding:7px 0; color:var(--text-muted);">Accommodation</td><td>${r.accommodation_name}</td></tr>
                                <tr><td style="padding:7px 0; color:var(--text-muted);">Check-in</td><td>${r.check_in}</td></tr>
                                <tr><td style="padding:7px 0; color:var(--text-muted);">Refund Amount</td><td style="font-weight:700; color:var(--primary);">${fmtAmt(r.net_refund)}</td></tr>
                                <tr><td style="padding:7px 0; color:var(--text-muted);">Cancellation Fee</td><td>${fmtAmt(r.refund_fee)}</td></tr>
                                <tr><td style="padding:7px 0; color:var(--text-muted);">Original Paid</td><td>${fmtAmt(r.original_amount)}</td></tr>
                                <tr><td style="padding:7px 0; color:var(--text-muted);">Status</td><td><strong style="text-transform:uppercase;">${r.status}</strong></td></tr>
                                <tr><td style="padding:7px 0; color:var(--text-muted);">Requested</td><td>${r.requested_at}</td></tr>
                                ${r.approved_by ? `<tr><td style="padding:7px 0; color:var(--text-muted);">Reviewed By</td><td>${r.approved_by} on ${r.approved_at}</td></tr>` : ''}
                                ${r.rejection_reason ? `<tr><td style="padding:7px 0; color:var(--text-muted);">Reject Reason</td><td style="color:#991b1b;">${r.rejection_reason}</td></tr>` : ''}
                                <tr><td style="padding:7px 0; color:var(--text-muted);" colspan="2"><strong>Guest Reason:</strong><br><span style="color:var(--text-main); line-height:1.5;">${r.reason || '—'}</span></td></tr>
                            </table>
                        `;
                        document.getElementById('detailsModal').classList.add('show');
                    } else {
                        showToast('Could not load details: ' + data.message, 'error');
                    }
                })
                .catch(() => showToast('Network error loading details.', 'error'));
        }

        // Close modals on backdrop click
        document.querySelectorAll('.modal-backdrop').forEach(m => {
            m.addEventListener('click', e => { if (e.target === m) m.classList.remove('show'); });
        });

        // Toast notification helper
        function showToast(msg, type = 'info') {
            const colors = { success: '#15803d', error: '#991b1b', warning: '#92400e', info: '#1e40af' };
            const t = document.createElement('div');
            t.textContent = msg;
            Object.assign(t.style, {
                position: 'fixed', bottom: '24px', right: '24px', zIndex: '9999',
                padding: '12px 20px', borderRadius: '8px',
                background: colors[type] || colors.info, color: '#fff',
                fontSize: '13px', fontWeight: '600',
                boxShadow: '0 8px 24px rgba(0,0,0,0.2)',
                animation: 'slideUp 0.3s ease',
                maxWidth: '360px', lineHeight: '1.5'
            });
            document.body.appendChild(t);
            setTimeout(() => { t.style.opacity = '0'; t.style.transition = 'opacity 0.3s'; setTimeout(() => t.remove(), 300); }, 3500);
        }
    </script>
</body>
</html>
