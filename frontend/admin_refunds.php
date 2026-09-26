<?php
require_once __DIR__ . '/../backend/helpers/auth_check.php';
require_once __DIR__ . '/../backend/config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$page_title = 'Refund Requests Management';
$is_admin_user = (($_SESSION['admin_role'] ?? 'receptionist') === 'admin');
$csrf_token = get_csrf_token();
$schema_name = $conn->query('SELECT DATABASE()')->fetch_row()[0];
$manual_refund_ready = false;
$schema_check = $conn->prepare("SELECT
    EXISTS(SELECT 1 FROM information_schema.tables WHERE table_schema=? AND table_name='manual_refund_details') AS has_table,
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=? AND table_name='refund_requests' AND column_name IN ('preferred_refund_method','approved_refund_method')) AS column_count");
$schema_check->bind_param('ss', $schema_name, $schema_name);
$schema_check->execute();
$schema_state = $schema_check->get_result()->fetch_assoc();
$schema_check->close();
$manual_refund_ready = ((int)($schema_state['has_table'] ?? 0) === 1 && (int)($schema_state['column_count'] ?? 0) === 2);
$noshow_handoff = null;
if (($_SESSION['admin_role'] ?? '') === 'receptionist' && isset($_GET['noshow_handoff'])) {
    $noshow_handoff = $_SESSION['noshow_refund_handoff'] ?? null;
    unset($_SESSION['noshow_refund_handoff']);
}

// Get filter status
$filter_status = trim($_GET['status'] ?? 'requested');
$valid_statuses = ['requested', 'approved', 'awaiting_guest_details', 'details_submitted', 'processing', 'completed', 'rejected'];
if (!in_array($filter_status, $valid_statuses)) {
    $filter_status = 'requested';
}

// Fetch refund requests with the selected status
$query = "
    SELECT r.id, r.booking_id, r.requested_by, r.status, r.reason,
           r.refund_amount, r.refund_fee, r.net_refund,
           r.approved_by, r.approved_at, r.rejection_reason, r.requested_at,
           " . ($manual_refund_ready ? 'r.preferred_refund_method, r.approved_refund_method,' : "'original_source' AS preferred_refund_method, NULL AS approved_refund_method,") . "
           b.guest_name, b.guest_email, b.accommodation_name,
           b.check_in, b.check_out, p.amount as original_amount, p.payment_method,
           " . ($manual_refund_ready ? 'd.gcash_account_name,d.gcash_mobile,d.transfer_reference,d.processed_at' : 'NULL AS gcash_account_name,NULL AS gcash_mobile,NULL AS transfer_reference,NULL AS processed_at') . "
    FROM refund_requests r
    JOIN bookings b ON r.booking_id = b.id
    LEFT JOIN payments p ON r.payment_id = p.id
    " . ($manual_refund_ready ? 'LEFT JOIN manual_refund_details d ON d.refund_request_id = r.id' : '') . "
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
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-circle-32.png?v=3">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/favicon-circle-180.png?v=3">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Refund Requests — Admin</title>
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/assets/css/admin.css'); ?>">
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

        .modal-field input {
            width: 100%;
            padding: 11px 12px;
            border: 1.5px solid var(--border);
            border-radius: 7px;
            font-family: inherit;
            font-size: 14px;
            background: var(--input-bg, #fff);
            color: var(--text-main);
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .modal-field input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(137, 86, 58, 0.12);
        }
        .modal-help {
            margin: -4px 0 18px;
            padding: 12px 14px;
            border-radius: 8px;
            background: var(--bg-alt, #f8fafc);
            color: var(--text-muted);
            font-size: 12.5px;
            line-height: 1.55;
        }
        .modal-error {
            display: none;
            margin-top: 7px;
            color: #b91c1c;
            font-size: 12px;
            font-weight: 600;
        }
        .modal-error.show { display: block; }

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
        .reception-refund-tools {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 20px;
            padding: 16px 18px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: var(--card-bg);
        }
        .reception-refund-tools strong { display: block; color: var(--text-main); margin-bottom: 3px; }
        .reception-refund-tools span { color: var(--text-muted); font-size: 12.5px; }
        @media (max-width: 640px) {
            .reception-refund-tools { align-items: stretch; flex-direction: column; }
            .reception-refund-tools .btn-process { justify-content: center; }
        }

        .rejection-note {
            background: #fee2e2;
            border-left: 3px solid #f87171;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
            color: #991b1b;
        }
    </style>
<script src="assets/js/site-dialog.js?v=1"></script>
<?php require __DIR__ . '/partials/_responsive.php'; ?>
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

            <?php if (!$manual_refund_ready): ?>
            <div class="reception-refund-tools" style="border-color:#f59e0b;background:#fffbeb">
                <div><strong>Refund database update required</strong><span>The dashboard remains available, but secure manual GCash refunds are disabled until migration <code>202609260001_manual_refund_workflow</code> is run on the production database.</span></div>
            </div>
            <?php endif; ?>

            <?php if (!$is_admin_user): ?>
            <div class="reception-refund-tools">
                <div>
                    <strong>Receptionist refund assistance</strong>
                    <span>You may view refund progress and submit a request for a guest. An administrator must review and process it.</span>
                </div>
                <button class="btn-process" type="button" onclick="openReceptionRefundModal()">Submit Guest Request</button>
            </div>
            <?php endif; ?>

            <!-- Status Tabs -->
            <div class="filter-tabs">
                <?php
                $tab_labels = [
                    'requested'  => ['Pending', 'Awaiting approval'],
                    'approved'   => ['Approved', 'Awaiting refund processing'],
                    'awaiting_guest_details' => ['Awaiting Details', 'Secure link sent'],
                    'details_submitted' => ['Details Ready', 'GCash details submitted'],
                    'processing' => ['Processing', 'PayMongo verification pending'],
                    'completed'  => ['Completed', 'Refund sent'],
                    'rejected'   => ['Rejected', 'Denied'],
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
                        <div class="info-item">
                            <span class="info-label">Original Payment</span>
                            <span class="info-value"><?php echo htmlspecialchars($refund['payment_method'] ?? 'Unknown'); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Guest Preference</span>
                            <span class="info-value"><?php echo ($refund['preferred_refund_method'] ?? 'original_source') === 'manual_gcash' ? 'GCash' : 'Original source'; ?></span>
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
                        <?php if ($is_admin_user && $filter_status === 'requested'): ?>
                            <?php if ((float)$refund['net_refund'] > 0): ?>
                            <button class="btn-approve" onclick="approveRefund(<?php echo $refund['id']; ?>, <?php echo xss_attr_json($refund['guest_name']); ?>, '<?php echo number_format($refund['net_refund'], 2); ?>', this)">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                Approve Request
                            </button>
                            <?php else: ?>
                            <span style="font-size:12px;color:#991b1b;font-weight:700;">Not refundable — approval disabled</span>
                            <?php endif; ?>
                            <button class="btn-reject" onclick="openRejectModal(<?php echo $refund['id']; ?>, <?php echo xss_attr_json($refund['guest_name']); ?>)">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                Reject
                            </button>
                        <?php elseif ($is_admin_user && $filter_status === 'approved'): ?>
                            <?php if ((float)$refund['net_refund'] > 0): ?>
                            <?php if (stripos((string)($refund['payment_method'] ?? ''), 'PayMongo') !== false): ?>
                            <button class="btn-approve" type="button" onclick="openRefundLinkModal(<?php echo (int)$refund['id']; ?>, <?php echo xss_attr_json($refund['guest_name']); ?>, <?php echo xss_attr_json($refund['guest_email']); ?>)">
                                Email Refund Link
                            </button>
                            <button class="btn-process" type="button" onclick="processPayMongoRefund(<?php echo $refund['id']; ?>)">
                                Record PayMongo Refund
                            </button>
                            <a class="btn-process" href="https://dashboard.paymongo.com/login" target="_blank" rel="noopener noreferrer" style="text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 3h7v7"/><path d="M10 14 21 3"/><path d="M21 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5"/></svg>
                                Open PayMongo Dashboard
                            </a>
                            <?php if ($manual_refund_ready && stripos((string)($refund['payment_method'] ?? ''), 'GCash') !== false): ?>
                            <button class="btn-approve" type="button" onclick="sendManualRefundLink(<?php echo (int)$refund['id']; ?>, this, true)">
                                Manual GCash Fallback
                            </button>
                            <?php endif; ?>
                            <?php elseif ($manual_refund_ready): ?>
                            <button class="btn-approve" type="button" onclick="sendManualRefundLink(<?php echo (int)$refund['id']; ?>, this)">
                                Email GCash Details Link
                            </button>
                            <?php else: ?>
                            <span style="font-size:12px;color:#92400e;font-weight:700">Run the refund database migration to enable Manual GCash.</span>
                            <?php endif; ?>
                            <?php else: ?>
                            <span style="font-size:12px;color:#991b1b;font-weight:700;">₱0.00 refund — PayMongo actions disabled</span>
                            <?php endif; ?>
                            <button class="btn-reject" type="button" onclick="cancelRefundApproval(<?php echo $refund['id']; ?>, this)">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                Cancel Refund Approval
                            </button>
                        <?php elseif ($is_admin_user && $filter_status === 'processing' && (float)$refund['net_refund'] > 0): ?>
                            <button class="btn-approve" type="button" onclick="openRefundLinkModal(<?php echo (int)$refund['id']; ?>, <?php echo xss_attr_json($refund['guest_name']); ?>, <?php echo xss_attr_json($refund['guest_email']); ?>)">
                                Email Refund Link
                            </button>
                            <button class="btn-process" type="button" onclick="syncPayMongoRefund(<?php echo $refund['id']; ?>, this)">
                                Verify PayMongo Status
                            </button>
                        <?php elseif ($is_admin_user && $filter_status === 'awaiting_guest_details'): ?>
                            <button class="btn-approve" type="button" onclick="sendManualRefundLink(<?php echo (int)$refund['id']; ?>, this)">Resend New Secure Link</button>
                        <?php elseif ($is_admin_user && $filter_status === 'details_submitted'): ?>
                            <button class="btn-process" type="button" onclick="openManualRefundModal(<?php echo (int)$refund['id']; ?>, <?php echo xss_attr_json($refund['gcash_account_name'] ?? ''); ?>, <?php echo xss_attr_json($refund['gcash_mobile'] ?? ''); ?>)">Record GCash Transfer</button>
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
            <div class="modal-title">Reject Refund Request</div>
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

    <?php if (!$is_admin_user): ?>
    <!-- Receptionist Submit Refund Modal -->
    <div id="receptionRefundModal" class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="receptionRefundTitle">
        <div class="modal-box">
            <div class="modal-title" id="receptionRefundTitle">Submit Guest Refund Request</div>
            <div class="modal-help">
                Confirm the guest's booking reference and reason. The payment and refundable amount are selected and calculated by the server. This does not approve or send money.
            </div>
            <div class="modal-field">
                <label for="receptionBookingRef">Booking reference</label>
                <input id="receptionBookingRef" type="text" autocomplete="off" spellcheck="false" placeholder="REF-025" maxlength="20">
                <div id="receptionBookingError" class="modal-error">Enter a valid booking reference such as REF-025.</div>
            </div>
            <div class="modal-field">
                <label for="receptionRefundReason">Guest's reason</label>
                <textarea id="receptionRefundReason" placeholder="Enter the guest's reason for requesting a refund..." maxlength="1000"></textarea>
                <div id="receptionReasonError" class="modal-error">Enter the guest's reason for the request.</div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary-sm" type="button" onclick="closeReceptionRefundModal()">Cancel</button>
                <button class="btn-process" type="button" id="submitReceptionRefundBtn" onclick="submitReceptionRefund()">Submit Request</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Record PayMongo Refund Modal -->
    <div id="paymongoRefundModal" class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="paymongoRefundTitle">
        <div class="modal-box">
            <div class="modal-title" id="paymongoRefundTitle">Record PayMongo Refund</div>
            <div class="modal-help">
                Create the refund in PayMongo Dashboard first. Then paste the PayMongo refund ID below. The system will verify the payment, amount, currency, and status directly with PayMongo.
            </div>
            <div class="modal-field">
                <label for="paymongoRefundId">PayMongo Refund ID</label>
                <input id="paymongoRefundId" type="text" inputmode="text" autocomplete="off" spellcheck="false" placeholder="ref_..." maxlength="100">
                <div id="paymongoRefundError" class="modal-error">Enter a valid refund ID beginning with ref_.</div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary-sm" type="button" onclick="closePayMongoRefundModal()">Cancel</button>
                <button class="btn-process" type="button" id="verifyPayMongoRefundBtn" onclick="submitPayMongoRefund()">Verify Refund</button>
            </div>
        </div>
    </div>

    <!-- Email PayMongo Refund Link Modal -->
    <div id="refundLinkModal" class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="refundLinkTitle">
        <div class="modal-box">
            <div class="modal-title" id="refundLinkTitle">Email PayMongo Refund Link</div>
            <div class="modal-help">
                Copy the complete link from PayMongo's “Refund Transfers Link Generated” window and paste it here. It will be emailed to the guest's saved address with instructions and the 3-day expiry notice.
            </div>
            <div class="modal-field">
                <label>Send to</label>
                <div id="refundLinkRecipient" style="padding:10px 12px;background:var(--bg-alt, #f8fafc);border-radius:7px;font-size:13px;color:var(--text-main);"></div>
            </div>
            <div class="modal-field">
                <label for="paymongoTransferLink">PayMongo Refund Transfers Link</label>
                <input id="paymongoTransferLink" type="url" inputmode="url" autocomplete="off" spellcheck="false" placeholder="https://transfer.paymongo.com/..." maxlength="2048">
                <div id="paymongoTransferLinkError" class="modal-error">Paste a valid https://transfer.paymongo.com/ link.</div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary-sm" type="button" onclick="closeRefundLinkModal()">Cancel</button>
                <button class="btn-approve" type="button" id="sendRefundLinkBtn" onclick="sendRefundLinkEmail()">Send to Guest Email</button>
            </div>
        </div>
    </div>

    <!-- Complete Manual GCash Refund Modal -->
    <div id="manualRefundModal" class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="manualRefundTitle">
        <div class="modal-box">
            <div class="modal-title" id="manualRefundTitle">Record Manual GCash Refund</div>
            <div class="modal-help">Transfer the exact approved amount first. Then record the unique GCash reference and upload the genuine transfer receipt. This action completes the refund.</div>
            <div class="modal-field"><label>Recipient</label><div id="manualRefundRecipient" style="padding:10px 12px;background:var(--bg-alt,#f8fafc);border-radius:7px"></div></div>
            <div class="modal-field"><label for="manualTransferReference">GCash reference number</label><input id="manualTransferReference" maxlength="100" autocomplete="off" placeholder="e.g. 1234 567 890123"></div>
            <div class="modal-field"><label for="manualReceipt">Transfer receipt (JPG/PNG, max 2 MB)</label><input id="manualReceipt" type="file" accept="image/jpeg,image/png"></div>
            <div class="modal-field"><label for="manualRefundNotes">Internal notes (optional)</label><textarea id="manualRefundNotes" maxlength="1000"></textarea></div>
            <div class="modal-footer"><button class="btn-secondary-sm" type="button" onclick="closeModal('manualRefundModal')">Cancel</button><button class="btn-process" id="completeManualRefundBtn" type="button" onclick="completeManualRefund()">Confirm Money Sent</button></div>
        </div>
    </div>

    <!-- Details Modal -->
    <div id="detailsModal" class="modal-backdrop">
        <div class="modal-box" style="max-width: 520px;">
            <div class="modal-title">Refund Request Details</div>
            <div id="detailsContent" style="font-size: 13.5px; line-height: 1.7; color: var(--text-main);"></div>
            <div class="modal-footer">
                <button class="btn-secondary-sm" onclick="closeModal('detailsModal')">Close</button>
            </div>
        </div>
    </div>

    <script>
        let currentRefundId = null;
        const refundCsrfToken = <?php echo json_encode($csrf_token, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

        function openReceptionRefundModal(handoff = null) {
            const modal = document.getElementById('receptionRefundModal');
            if (!modal) return;
            document.getElementById('receptionBookingRef').value = handoff ? 'REF-' + String(handoff.booking_id).padStart(3, '0') : '';
            document.getElementById('receptionRefundReason').value = handoff ? handoff.reason : '';
            document.getElementById('receptionBookingError').classList.remove('show');
            document.getElementById('receptionReasonError').classList.remove('show');
            modal.classList.add('show');
            setTimeout(() => document.getElementById('receptionBookingRef').focus(), 50);
        }

        function closeReceptionRefundModal() {
            const modal = document.getElementById('receptionRefundModal');
            if (modal) modal.classList.remove('show');
        }

        const noshowHandoff = <?php echo json_encode($noshow_handoff, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        if (noshowHandoff) openReceptionRefundModal(noshowHandoff);

        async function submitReceptionRefund() {
            const referenceInput = document.getElementById('receptionBookingRef');
            const reasonInput = document.getElementById('receptionRefundReason');
            const match = referenceInput.value.trim().match(/^(?:REF-)?0*([1-9][0-9]*)$/i);
            const reason = reasonInput.value.trim();
            document.getElementById('receptionBookingError').classList.toggle('show', !match);
            document.getElementById('receptionReasonError').classList.toggle('show', reason === '');
            if (!match || reason === '') return;

            const btn = document.getElementById('submitReceptionRefundBtn');
            const original = btn.textContent;
            btn.disabled = true;
            btn.textContent = 'Submitting...';
            const fd = new FormData();
            fd.append('booking_id', match[1]);
            fd.append('reason', reason);
            fd.append('csrf_token', refundCsrfToken);
            try {
                const response = await fetch('../backend/api/refund_request_api.php?action=submit_request', { method: 'POST', body: fd });
                const data = await response.json();
                if (!data.success) throw new Error(data.message || 'Could not submit the refund request.');
                showToast('Refund request submitted for administrator review.', 'success');
                setTimeout(() => { window.location.href = 'admin_refunds?status=requested'; }, 1000);
            } catch (error) {
                btn.disabled = false;
                btn.textContent = original;
                showToast(error.message || 'Network error. Please try again.', 'error');
            }
        }

        async function approveRefund(refundId, guestName, amount, btn) {
            if (!await SiteDialog.confirm(`Approve the refund request for ${guestName}?\n\nAmount to be refunded: ₱${amount}\n\nThis will notify the guest and move the request to "Approved".`, { title: 'Approve refund?', confirmText: 'Approve refund' })) return;
            const origHTML = btn.innerHTML;
            btn.innerHTML = '<span class="loading-dot"></span> Approving...';
            btn.disabled = true;

            const fd = new FormData();
            fd.append('request_id', refundId);
            fd.append('csrf_token', refundCsrfToken);

            fetch('../backend/api/refund_request_api.php?action=approve', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        showToast('Refund approved. Payment processing remains pending.', 'success');
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
            fd.append('csrf_token', refundCsrfToken);

            fetch('../backend/api/refund_request_api.php?action=reject', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        closeModal('rejectModal');
                        showToast('Refund rejected. Guest has been notified.', 'success');
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

        async function cancelRefundApproval(refundId, btn) {
            if (!await SiteDialog.confirm('Cancel this refund approval? No payment or booking record will be changed. The request will move to Rejected.', { title: 'Cancel refund approval?', confirmText: 'Cancel approval', danger: true })) return;
            const original = btn.innerHTML;
            btn.disabled = true;
            btn.textContent = 'Cancelling...';
            const fd = new FormData();
            fd.append('request_id', refundId);
            fd.append('reason', 'Refund approval cancelled by management before payment processing.');
            fd.append('csrf_token', refundCsrfToken);
            try {
                const response = await fetch('../backend/api/refund_request_api.php?action=cancel_approval', { method: 'POST', body: fd });
                const data = await response.json();
                if (!data.success) throw new Error(data.message || 'Could not cancel approval.');
                showToast('Refund approval cancelled. No money was refunded.', 'success');
                setTimeout(() => location.reload(), 1000);
            } catch (error) {
                btn.disabled = false;
                btn.innerHTML = original;
                showToast(error.message || 'Network error. Please try again.', 'error');
            }
        }

        function processPayMongoRefund(refundId) {
            currentRefundId = refundId;
            const input = document.getElementById('paymongoRefundId');
            input.value = '';
            document.getElementById('paymongoRefundError').classList.remove('show');
            document.getElementById('paymongoRefundModal').classList.add('show');
            setTimeout(() => input.focus(), 50);
        }

        function openRefundLinkModal(refundId, guestName, guestEmail) {
            currentRefundId = refundId;
            const input = document.getElementById('paymongoTransferLink');
            document.getElementById('refundLinkRecipient').textContent = guestName + ' — ' + (guestEmail || 'No email address');
            input.value = '';
            document.getElementById('paymongoTransferLinkError').classList.remove('show');
            document.getElementById('refundLinkModal').classList.add('show');
            setTimeout(() => input.focus(), 50);
        }

        function closeRefundLinkModal() {
            document.getElementById('refundLinkModal').classList.remove('show');
            document.getElementById('paymongoTransferLinkError').classList.remove('show');
            currentRefundId = null;
        }

        async function sendRefundLinkEmail() {
            const input = document.getElementById('paymongoTransferLink');
            const link = input.value.trim();
            const error = document.getElementById('paymongoTransferLinkError');
            let valid = false;
            try {
                const parsed = new URL(link);
                valid = parsed.protocol === 'https:' && parsed.hostname.toLowerCase() === 'transfer.paymongo.com';
            } catch (_) {}
            if (!valid) {
                error.classList.add('show');
                input.focus();
                return;
            }
            error.classList.remove('show');
            const btn = document.getElementById('sendRefundLinkBtn');
            const original = btn.textContent;
            btn.disabled = true;
            btn.textContent = 'Sending...';
            const fd = new FormData();
            fd.append('request_id', currentRefundId);
            fd.append('refund_link', link);
            fd.append('csrf_token', refundCsrfToken);
            try {
                const response = await fetch('../backend/api/refund_request_api.php?action=send_refund_link', { method: 'POST', body: fd });
                const data = await response.json();
                if (!data.success) throw new Error(data.message || 'Could not email the refund link.');
                closeRefundLinkModal();
                showToast(data.message, 'success');
            } catch (sendError) {
                showToast(sendError.message || 'Network error. Please try again.', 'error');
            } finally {
                btn.disabled = false;
                btn.textContent = original;
            }
        }

        function closePayMongoRefundModal() {
            document.getElementById('paymongoRefundModal').classList.remove('show');
            document.getElementById('paymongoRefundError').classList.remove('show');
            currentRefundId = null;
        }

        async function submitPayMongoRefund() {
            const input = document.getElementById('paymongoRefundId');
            const providerRefundId = input.value.trim();
            const error = document.getElementById('paymongoRefundError');
            if (!/^ref_[A-Za-z0-9]+$/.test(providerRefundId)) {
                error.classList.add('show');
                input.focus();
                return;
            }
            error.classList.remove('show');
            const btn = document.getElementById('verifyPayMongoRefundBtn');
            await runRefundAction('process_paymongo', currentRefundId, btn, 'Verifying...', 'PayMongo refund verified.', { paymongo_refund_id: providerRefundId });
        }

        async function syncPayMongoRefund(refundId, btn) {
            await runRefundAction('sync_paymongo', refundId, btn, 'Verifying...', 'PayMongo refund status verified.');
        }

        async function sendManualRefundLink(refundId, btn, paymongoFallback = false) {
            const warning = paymongoFallback
                ? 'This payment originated from GCash through PayMongo. Use this manual fallback only if you will NOT also create a PayMongo refund, or the guest could be refunded twice. Email the secure GCash details link?'
                : 'Email a secure, single-use GCash details link to this guest? The link expires after 48 hours.';
            if (!await SiteDialog.confirm(warning, { title: paymongoFallback ? 'Use manual GCash fallback?' : 'Send GCash details link?', confirmText: 'Send link' })) return;
            const original = btn.innerHTML; btn.disabled = true; btn.textContent = 'Sending...';
            const fd = new FormData(); fd.append('request_id', refundId); fd.append('csrf_token', refundCsrfToken);
            try {
                const response = await fetch('../backend/api/manual_refund_api.php?action=generate_link', {method:'POST',body:fd});
                const data = await response.json(); if (!data.success) throw new Error(data.message || 'Could not send the link.');
                showToast(data.message, 'success'); setTimeout(() => location.reload(), 1000);
            } catch (error) { btn.disabled=false; btn.innerHTML=original; showToast(error.message || 'Network error.', 'error'); }
        }

        function openManualRefundModal(refundId, accountName, mobile) {
            currentRefundId = refundId;
            const masked = mobile ? mobile.slice(0,2) + '•• •••• ' + mobile.slice(-3) : 'No mobile number';
            document.getElementById('manualRefundRecipient').textContent = accountName + ' — ' + masked;
            document.getElementById('manualTransferReference').value=''; document.getElementById('manualReceipt').value=''; document.getElementById('manualRefundNotes').value='';
            document.getElementById('manualRefundModal').classList.add('show');
        }

        async function completeManualRefund() {
            const reference=document.getElementById('manualTransferReference').value.trim(), receipt=document.getElementById('manualReceipt').files[0], notes=document.getElementById('manualRefundNotes').value.trim();
            if (reference.length < 6) { showToast('Enter the GCash transfer reference.', 'warning'); return; }
            if (!receipt) { showToast('Upload the GCash transfer receipt.', 'warning'); return; }
            if (!await SiteDialog.confirm('Confirm that the money was actually sent to the displayed GCash account. This will mark the payment refunded.', {title:'Complete manual refund?',confirmText:'Confirm completed'})) return;
            const btn=document.getElementById('completeManualRefundBtn'), original=btn.textContent; btn.disabled=true; btn.textContent='Saving...';
            const fd=new FormData(); fd.append('request_id',currentRefundId); fd.append('transfer_reference',reference); fd.append('notes',notes); fd.append('receipt',receipt); fd.append('csrf_token',refundCsrfToken);
            try { const response=await fetch('../backend/api/manual_refund_api.php?action=complete',{method:'POST',body:fd}),data=await response.json(); if(!data.success)throw new Error(data.message||'Could not complete refund.'); showToast(data.message,'success'); setTimeout(()=>location.href='admin_refunds?status=completed',1000); }
            catch(error){btn.disabled=false;btn.textContent=original;showToast(error.message||'Network error.','error');}
        }

        async function runRefundAction(action, refundId, btn, busyText, fallbackMessage, extraFields = {}) {
            const original = btn.innerHTML;
            btn.disabled = true;
            btn.textContent = busyText;
            const fd = new FormData();
            fd.append('request_id', refundId);
            fd.append('csrf_token', refundCsrfToken);
            Object.entries(extraFields).forEach(([key, value]) => fd.append(key, value));
            try {
                const response = await fetch('../backend/api/refund_request_api.php?action=' + encodeURIComponent(action), { method: 'POST', body: fd });
                const data = await response.json();
                if (!data.success) throw new Error(data.message || 'Refund action failed.');
                showToast(data.message || fallbackMessage, 'success');
                setTimeout(() => location.reload(), 1200);
            } catch (error) {
                btn.disabled = false;
                btn.innerHTML = original;
                showToast(error.message || 'Network error. Please try again.', 'error');
            }
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
                                <tr><td style="padding:7px 0; color:var(--text-muted); width:140px;">Request ID</td><td style="font-weight:600;">#${Security.escapeHTML(r.id)}</td></tr>
                                <tr><td style="padding:7px 0; color:var(--text-muted);">Booking Ref</td><td style="font-weight:600;">REF-${Security.escapeHTML(String(r.booking_id).padStart(3,'0'))}</td></tr>
                                <tr><td style="padding:7px 0; color:var(--text-muted);">Guest</td><td>${Security.escapeHTML(r.guest_name)} (${Security.escapeHTML(r.guest_email)})</td></tr>
                                <tr><td style="padding:7px 0; color:var(--text-muted);">Accommodation</td><td>${Security.escapeHTML(r.accommodation_name)}</td></tr>
                                <tr><td style="padding:7px 0; color:var(--text-muted);">Check-in</td><td>${Security.escapeHTML(r.check_in)}</td></tr>
                                <tr><td style="padding:7px 0; color:var(--text-muted);">Refund Amount</td><td style="font-weight:700; color:var(--primary);">${fmtAmt(r.net_refund)}</td></tr>
                                <tr><td style="padding:7px 0; color:var(--text-muted);">Cancellation Fee</td><td>${fmtAmt(r.refund_fee)}</td></tr>
                                <tr><td style="padding:7px 0; color:var(--text-muted);">Original Paid</td><td>${fmtAmt(r.original_amount)}</td></tr>
                                <tr><td style="padding:7px 0; color:var(--text-muted);">Status</td><td><strong style="text-transform:uppercase;">${Security.escapeHTML(r.status)}</strong></td></tr>
                                <tr><td style="padding:7px 0; color:var(--text-muted);">Requested</td><td>${Security.escapeHTML(r.requested_at)}</td></tr>
                                ${r.approved_by ? `<tr><td style="padding:7px 0; color:var(--text-muted);">Reviewed By</td><td>${Security.escapeHTML(r.approved_by)} on ${Security.escapeHTML(r.approved_at)}</td></tr>` : ''}
                                ${r.rejection_reason ? `<tr><td style="padding:7px 0; color:var(--text-muted);">Reject Reason</td><td style="color:#991b1b;">${Security.escapeHTML(r.rejection_reason)}</td></tr>` : ''}
                                <tr><td style="padding:7px 0; color:var(--text-muted);" colspan="2"><strong>Guest Reason:</strong><br><span style="color:var(--text-main); line-height:1.5;">${Security.escapeHTML(r.reason || '—')}</span></td></tr>
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
