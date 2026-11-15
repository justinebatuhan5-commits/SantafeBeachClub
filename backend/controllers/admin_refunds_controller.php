<?php

/**
 * Admin Refunds controller: schema readiness check, no-show handoff intake,
 * status counts, and filtered refund request listing.
 */

function sfbc_handle_admin_refunds_page(mysqli $conn): array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $is_admin_user = (($_SESSION['admin_role'] ?? 'receptionist') === 'admin');
    $csrf_token = get_csrf_token();
    $schema_name = $conn->query('SELECT DATABASE()')->fetch_row()[0];

    $schema_check = $conn->prepare("SELECT
        EXISTS(SELECT 1 FROM information_schema.tables WHERE table_schema=? AND table_name='manual_refund_details') AS has_table,
        (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=? AND table_name='refund_requests' AND column_name IN ('preferred_refund_method','approved_refund_method')) AS column_count,
        (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=? AND table_name='manual_refund_details' AND column_name IN ('confirmation_token_hash','confirmation_token_expires_at','guest_confirmed_at')) AS confirmation_column_count,
        (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=? AND table_name='manual_refund_details' AND column_name IN ('verification_otp_hash','verification_otp_expires_at','verification_attempts','verification_send_count','verification_last_sent_at','verification_locked_at','email_verified_at')) AS mfa_column_count");
    $schema_check->bind_param('ssss', $schema_name, $schema_name, $schema_name, $schema_name);
    $schema_check->execute();
    $schema_state = $schema_check->get_result()->fetch_assoc();
    $schema_check->close();

    $manual_refund_ready = (
        (int)($schema_state['has_table'] ?? 0) === 1
        && (int)($schema_state['column_count'] ?? 0) === 2
        && (int)($schema_state['confirmation_column_count'] ?? 0) === 3
        && (int)($schema_state['mfa_column_count'] ?? 0) === 7
    );

    $refund_methods = sf_refund_method_settings($conn);
    $noshow_handoff = null;
    if (($_SESSION['admin_role'] ?? '') === 'receptionist' && isset($_GET['noshow_handoff'])) {
        $noshow_handoff = $_SESSION['noshow_refund_handoff'] ?? null;
        unset($_SESSION['noshow_refund_handoff']);
    }

    $filter_status = trim($_GET['status'] ?? 'requested');
    $valid_statuses = ['requested', 'approved', 'awaiting_guest_details', 'details_submitted', 'processing', 'completed', 'rejected'];
    if (!in_array($filter_status, $valid_statuses, true)) {
        $filter_status = 'requested';
    }

    $query = "
        SELECT r.id, r.booking_id, r.requested_by, r.status, r.reason,
               r.refund_amount, r.refund_fee, r.net_refund,
               r.approved_by, r.approved_at, r.rejection_reason, r.requested_at,
               " . ($manual_refund_ready ? 'r.preferred_refund_method, r.approved_refund_method,' : "'original_source' AS preferred_refund_method, NULL AS approved_refund_method,") . "
               b.guest_name, b.guest_email, b.accommodation_name,
               b.check_in, b.check_out, p.amount as original_amount, p.payment_method,
               " . ($manual_refund_ready ? 'd.gcash_account_name,d.gcash_mobile,d.transfer_reference,d.processed_at,d.guest_confirmed_at,d.email_verified_at,d.verification_locked_at,d.verification_last_sent_at' : 'NULL AS gcash_account_name,NULL AS gcash_mobile,NULL AS transfer_reference,NULL AS processed_at,NULL AS guest_confirmed_at,NULL AS email_verified_at,NULL AS verification_locked_at,NULL AS verification_last_sent_at') . "
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

    $status_counts = [];
    foreach ($valid_statuses as $s) {
        $count_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM refund_requests WHERE status = ?");
        $count_stmt->bind_param("s", $s);
        $count_stmt->execute();
        $status_counts[$s] = (int)$count_stmt->get_result()->fetch_assoc()['cnt'];
        $count_stmt->close();
    }

    $tab_labels = [
        'requested'              => ['Pending', 'Awaiting approval'],
        'approved'               => ['Approved', 'Awaiting refund processing'],
        'awaiting_guest_details' => ['Awaiting Details', 'Secure link sent'],
        'details_submitted'      => ['Details Ready', 'GCash details submitted'],
        'processing'             => ['Processing', 'PayMongo verification pending'],
        'completed'              => ['Completed', 'Refund sent'],
        'rejected'               => ['Rejected', 'Denied'],
    ];

    $frontendRoot = dirname(__DIR__, 2) . '/frontend';

    return [
        'isAdminUser' => $is_admin_user,
        'csrfToken' => $csrf_token,
        'manualRefundReady' => $manual_refund_ready,
        'refundMethods' => $refund_methods,
        'noshowHandoff' => $noshow_handoff,
        'filterStatus' => $filter_status,
        'validStatuses' => $valid_statuses,
        'tabLabels' => $tab_labels,
        'refunds' => $refunds,
        'statusCounts' => $status_counts,
        'adminCssVersion' => file_exists($frontendRoot . '/assets/css/admin.css') ? filemtime($frontendRoot . '/assets/css/admin.css') : 1,
        'cssVersion' => file_exists($frontendRoot . '/assets/css/pages/admin-refunds.css') ? filemtime($frontendRoot . '/assets/css/pages/admin-refunds.css') : 1,
        'jsVersion' => file_exists($frontendRoot . '/assets/js/pages/admin-refunds.js') ? filemtime($frontendRoot . '/assets/js/pages/admin-refunds.js') : 1,
    ];
}
