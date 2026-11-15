<?php
$manual_refund_ready = $pageData['manualRefundReady'];
$is_admin_user       = $pageData['isAdminUser'];
$filter_status       = $pageData['filterStatus'];
$valid_statuses      = $pageData['validStatuses'];
$tab_labels          = $pageData['tabLabels'];
$status_counts       = $pageData['statusCounts'];
$refunds             = $pageData['refunds'];
$refund_methods      = $pageData['refundMethods'];
?>

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
    <button class="btn-process" type="button" data-action="open-reception-modal">Submit Guest Request</button>
</div>
<?php endif; ?>

<!-- Status Tabs -->
<div class="filter-tabs">
    <?php foreach ($valid_statuses as $s):
        $label = $tab_labels[$s][0] ?? $s;
        $cnt   = $status_counts[$s] ?? 0;
    ?>
        <a href="?status=<?php echo urlencode($s); ?>"
           class="filter-tab <?php echo $filter_status === $s ? 'active' : ''; ?>">
            <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
            <?php if ($cnt > 0): ?>
                <span class="tab-badge"><?php echo (int)$cnt; ?></span>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
</div>

<!-- Refund Cards -->
<?php if (empty($refunds)): ?>
    <div class="empty-refunds">
        <h3>No Refund Requests</h3>
        <p>There are no refund requests with status: <strong><?php echo htmlspecialchars($filter_status, ENT_QUOTES, 'UTF-8'); ?></strong></p>
    </div>
<?php else: ?>
    <?php foreach ($refunds as $refund): ?>
        <?php
        $refundId = (int)$refund['id'];
        $guestNameEsc = htmlspecialchars((string)$refund['guest_name'], ENT_QUOTES, 'UTF-8');
        $guestEmailEsc = htmlspecialchars((string)($refund['guest_email'] ?? ''), ENT_QUOTES, 'UTF-8');
        $netRefundFormatted = number_format((float)$refund['net_refund'], 2);
        ?>
        <div class="refund-card">
            <div class="refund-header">
                <div>
                    <div class="refund-ref">
                        REF-<?php echo str_pad((string)$refund['booking_id'], 3, '0', STR_PAD_LEFT); ?>
                        <span class="status-badge-large status-<?php echo htmlspecialchars((string)$refund['status'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars((string)$refund['status'], ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    </div>
                    <div style="font-size: 13px; color: var(--text-muted); margin-top: 3px;">
                        <?php echo $guestNameEsc; ?>
                        &nbsp;·&nbsp;
                        <a href="mailto:<?php echo $guestEmailEsc; ?>"
                           style="color: var(--primary); text-decoration: none; font-size: 12px;">
                            <?php echo $guestEmailEsc; ?>
                        </a>
                    </div>
                </div>
                <div style="text-align: right;">
                    <div class="amount-highlight">₱ <?php echo $netRefundFormatted; ?></div>
                    <?php if ((float)$refund['refund_fee'] > 0): ?>
                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">
                            Cancellation fee: ₱ <?php echo number_format((float)$refund['refund_fee'], 2); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Details Grid -->
            <div class="refund-info">
                <div class="info-item">
                    <span class="info-label">Accommodation</span>
                    <span class="info-value"><?php echo htmlspecialchars((string)$refund['accommodation_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Check-in</span>
                    <span class="info-value"><?php echo htmlspecialchars(date('M j, Y', strtotime((string)$refund['check_in'])), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Check-out</span>
                    <span class="info-value"><?php echo htmlspecialchars(date('M j, Y', strtotime((string)$refund['check_out'])), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Requested On</span>
                    <span class="info-value"><?php echo htmlspecialchars(date('M j, Y g:i A', strtotime((string)$refund['requested_at'])), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Original Payment</span>
                    <span class="info-value"><?php echo htmlspecialchars((string)($refund['payment_method'] ?? 'Unknown'), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Guest Preference</span>
                    <span class="info-value"><?php echo ($refund['preferred_refund_method'] ?? 'original_source') === 'manual_gcash' ? 'GCash' : 'Original source'; ?></span>
                </div>
                <?php if (($refund['approved_refund_method'] ?? '') === 'manual_gcash' && $refund['status'] === 'completed'): ?>
                    <div class="info-item">
                        <span class="info-label">Guest Confirmation</span>
                        <span class="info-value" style="color:<?php echo $refund['guest_confirmed_at'] ? '#047857' : '#92400e'; ?>;font-weight:700"><?php echo $refund['guest_confirmed_at'] ? 'Confirmed received' : 'Awaiting confirmation'; ?></span>
                    </div>
                <?php endif; ?>
                <?php if (($refund['approved_refund_method'] ?? '') === 'manual_gcash' && $refund['status'] === 'awaiting_guest_details'): ?>
                    <div class="info-item">
                        <span class="info-label">Email Verification</span>
                        <span class="info-value" style="font-weight:700"><?php echo $refund['email_verified_at'] ? 'Email verified' : ($refund['verification_locked_at'] ? 'Locked' : ($refund['verification_last_sent_at'] ? 'OTP sent' : 'Not started')); ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($refund['approved_by'])): ?>
                    <div class="info-item">
                        <span class="info-label">Reviewed By</span>
                        <span class="info-value"><?php echo htmlspecialchars((string)$refund['approved_by'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Reason -->
            <div class="refund-reason">
                <div class="refund-reason-label">Guest Reason</div>
                <div class="refund-reason-text"><?php echo htmlspecialchars((string)($refund['reason'] ?? 'No reason provided'), ENT_QUOTES, 'UTF-8'); ?></div>
            </div>

            <?php if ($refund['status'] === 'rejected' && !empty($refund['rejection_reason'])): ?>
                <div class="rejection-note" style="margin-bottom: 14px;">
                    <strong>Rejection Reason:</strong> <?php echo htmlspecialchars((string)$refund['rejection_reason'], ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <!-- Actions -->
            <div class="refund-actions">
                <?php if ($is_admin_user && $filter_status === 'requested'): ?>
                    <?php if ((float)$refund['net_refund'] > 0): ?>
                        <button type="button" class="btn-approve" data-action="approve-refund" data-id="<?php echo $refundId; ?>" data-guest="<?php echo $guestNameEsc; ?>" data-amount="<?php echo $netRefundFormatted; ?>">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                            Approve Request
                        </button>
                    <?php else: ?>
                        <span style="font-size:12px;color:#991b1b;font-weight:700;">Not refundable — approval disabled</span>
                    <?php endif; ?>
                    <button type="button" class="btn-reject" data-action="open-reject-modal" data-id="<?php echo $refundId; ?>" data-guest="<?php echo $guestNameEsc; ?>">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        Reject
                    </button>
                <?php elseif ($is_admin_user && $filter_status === 'approved'): ?>
                    <?php if ((float)$refund['net_refund'] > 0): ?>
                        <?php
                        $approvedPaymentMethod = (string)($refund['payment_method'] ?? '');
                        $approvedIsPayMongo = stripos($approvedPaymentMethod, 'PayMongo') !== false;
                        $approvedIsGcash = stripos($approvedPaymentMethod, 'GCash') !== false;
                        $hasAvailableApprovedRoute = false;
                        if ($approvedIsPayMongo && $refund_methods['paymongo']):
                            $hasAvailableApprovedRoute = true;
                        ?>
                            <button class="btn-approve" type="button" data-action="open-refund-link-modal" data-id="<?php echo $refundId; ?>" data-guest="<?php echo $guestNameEsc; ?>" data-email="<?php echo $guestEmailEsc; ?>">
                                Email Refund Link
                            </button>
                            <button class="btn-process" type="button" data-action="process-paymongo" data-id="<?php echo $refundId; ?>">
                                Record PayMongo Refund
                            </button>
                            <a class="btn-process" href="https://dashboard.paymongo.com/login" target="_blank" rel="noopener noreferrer" style="text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 3h7v7"/><path d="M10 14 21 3"/><path d="M21 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5"/></svg>
                                Open PayMongo Dashboard
                            </a>
                        <?php endif; ?>
                        <?php if ($approvedIsPayMongo && $approvedIsGcash && $manual_refund_ready && $refund_methods['manual_gcash']):
                            $hasAvailableApprovedRoute = true;
                        ?>
                            <button class="btn-approve" type="button" data-action="send-manual-link" data-id="<?php echo $refundId; ?>" data-fallback="1">
                                Manual GCash Fallback
                            </button>
                        <?php endif; ?>
                        <?php if (!$approvedIsPayMongo && $manual_refund_ready && $refund_methods['manual_gcash']):
                            $hasAvailableApprovedRoute = true;
                        ?>
                            <button class="btn-approve" type="button" data-action="send-manual-link" data-id="<?php echo $refundId; ?>" data-fallback="0">
                                Email GCash Details Link
                            </button>
                        <?php endif; ?>
                        <?php if (!$hasAvailableApprovedRoute): ?>
                            <span style="font-size:12px;color:#92400e;font-weight:700">The applicable refund method is currently disabled.</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span style="font-size:12px;color:#991b1b;font-weight:700;">₱0.00 refund — PayMongo actions disabled</span>
                    <?php endif; ?>
                    <button class="btn-reject" type="button" data-action="cancel-approval" data-id="<?php echo $refundId; ?>">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        Cancel Refund Approval
                    </button>
                <?php elseif ($is_admin_user && $filter_status === 'processing' && (float)$refund['net_refund'] > 0): ?>
                    <?php if ($refund_methods['paymongo']): ?>
                        <button class="btn-approve" type="button" data-action="open-refund-link-modal" data-id="<?php echo $refundId; ?>" data-guest="<?php echo $guestNameEsc; ?>" data-email="<?php echo $guestEmailEsc; ?>">
                            Email Refund Link
                        </button>
                        <button class="btn-process" type="button" data-action="sync-paymongo" data-id="<?php echo $refundId; ?>">
                            Verify PayMongo Status
                        </button>
                    <?php else: ?>
                        <span style="font-size:12px;color:#92400e;font-weight:700">PayMongo refunds are disabled.</span>
                    <?php endif; ?>
                <?php elseif ($is_admin_user && $filter_status === 'awaiting_guest_details'): ?>
                    <?php if ($refund_methods['manual_gcash']): ?>
                        <button class="btn-approve" type="button" data-action="send-manual-link" data-id="<?php echo $refundId; ?>" data-fallback="0">Resend New Secure Link</button>
                    <?php else: ?>
                        <span style="font-size:12px;color:#92400e;font-weight:700">Manual GCash refunds are disabled.</span>
                    <?php endif; ?>
                    <?php if (!empty($refund['verification_locked_at'])): ?>
                        <button class="btn-secondary-sm" type="button" data-action="reset-verification" data-id="<?php echo $refundId; ?>">Reset Verification Lock</button>
                    <?php endif; ?>
                <?php elseif ($is_admin_user && $filter_status === 'details_submitted'): ?>
                    <?php if ($refund_methods['manual_gcash']): ?>
                        <button class="btn-process" type="button" data-action="open-manual-modal" data-id="<?php echo $refundId; ?>" data-account-name="<?php echo htmlspecialchars((string)($refund['gcash_account_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-mobile="<?php echo htmlspecialchars((string)($refund['gcash_mobile'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">Record GCash Transfer</button>
                    <?php else: ?>
                        <span style="font-size:12px;color:#92400e;font-weight:700">Manual GCash refunds are disabled.</span>
                    <?php endif; ?>
                <?php endif; ?>
                <button type="button" class="btn-secondary-sm" data-action="view-details" data-id="<?php echo $refundId; ?>">
                    View Details
                </button>
                <span style="font-size: 12px; color: var(--text-muted); margin-left: auto;">
                    Original amount: ₱<?php echo number_format((float)($refund['original_amount'] ?? ($refund['refund_amount'] + $refund['refund_fee'])), 2); ?>
                </span>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
