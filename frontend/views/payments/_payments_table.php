<?php
$payments = $pageData['payments'] ?? [];
$summary = $pageData['summary'] ?? [
    'total_count' => count($payments),
    'verified_count' => 0,
    'verified_total' => 0.0,
    'pending_count' => 0,
    'pending_total' => 0.0,
    'refunded_count' => 0,
    'refunded_total' => 0.0,
    'rejected_count' => 0,
    'rejected_total' => 0.0,
];
$csrfToken = $pageData['csrfToken'] ?? '';
?>

<!-- ═══ EXECUTIVE BILLING SUMMARY STRIP ═══ -->
<div class="payments-kpi-strip">
    <button type="button" class="payments-kpi-card" data-status-pill="verified" title="Filter to Verified payments">
        <div class="payments-kpi-icon payments-kpi-icon--verified">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        </div>
        <div class="payments-kpi-body">
            <span class="payments-kpi-label">Verified Collected</span>
            <strong class="payments-kpi-value">₱<?php echo number_format((float)$summary['verified_total'], 2); ?></strong>
            <span class="payments-kpi-sub"><?php echo (int)$summary['verified_count']; ?> settled receipt<?php echo (int)$summary['verified_count'] === 1 ? '' : 's'; ?></span>
        </div>
    </button>

    <button type="button" class="payments-kpi-card" data-status-pill="pending" title="Filter to Pending verification queue">
        <div class="payments-kpi-icon payments-kpi-icon--pending">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div class="payments-kpi-body">
            <span class="payments-kpi-label">Pending Verification</span>
            <strong class="payments-kpi-value" style="color:#D97706;">₱<?php echo number_format((float)$summary['pending_total'], 2); ?></strong>
            <span class="payments-kpi-sub"><?php echo (int)$summary['pending_count']; ?> awaiting staff review</span>
        </div>
    </button>

    <button type="button" class="payments-kpi-card" data-status-pill="" title="Show all payment records">
        <div class="payments-kpi-icon payments-kpi-icon--total">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
        </div>
        <div class="payments-kpi-body">
            <span class="payments-kpi-label">Total Ledger Entries</span>
            <strong class="payments-kpi-value"><?php echo (int)$summary['total_count']; ?> Bills</strong>
            <span class="payments-kpi-sub">Deposits, balances &amp; full stays</span>
        </div>
    </button>

    <button type="button" class="payments-kpi-card" data-status-pill="refunded" title="Filter to Refunded bills">
        <div class="payments-kpi-icon payments-kpi-icon--refund">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
        </div>
        <div class="payments-kpi-body">
            <span class="payments-kpi-label">Refunded / Rejected</span>
            <strong class="payments-kpi-value" style="color:#B42318;">₱<?php echo number_format((float)$summary['refunded_total'], 2); ?></strong>
            <span class="payments-kpi-sub"><?php echo (int)$summary['refunded_count']; ?> refunded • <?php echo (int)$summary['rejected_count']; ?> rejected</span>
        </div>
    </button>
</div>

<!-- ═══ BILLS LEDGER CARD ═══ -->
<div class="card payments-ledger-card">
    <div class="card-header payments-ledger-header">
        <div>
            <h2>Outstanding &amp; Settled Bills</h2>
            <p class="payments-ledger-subtitle">Verify GCash &amp; gateway references, issue official receipts, or manage refunds</p>
        </div>
    </div>

    <div class="payments-toolbar">
        <?php echo $payments_toolbar_html; ?>
    </div>

    <div class="table-responsive">
        <table class="reservations-table">
            <colgroup>
                <col style="width:18%">
                <col style="width:16%">
                <col style="width:22%">
                <col style="width:14%">
                <col style="width:15%">
                <col style="width:15%">
            </colgroup>
            <thead>
                <tr>
                    <th>Invoice &amp; Guest</th>
                    <th>Accommodation &amp; Stay</th>
                    <th>Payment Details</th>
                    <th>Amount &amp; Status</th>
                    <th>Payment History</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($payments)): ?>
                    <?php foreach ($payments as $row): ?>
                        <?php
                        $payId = (int)$row['pay_id'];
                        $invoiceCode = htmlspecialchars($row['invoice_code'], ENT_QUOTES, 'UTF-8');
                        $bookingRef = htmlspecialchars((string)($row['booking_ref'] ?? ''), ENT_QUOTES, 'UTF-8');
                        $guestName = htmlspecialchars($row['guest_name'], ENT_QUOTES, 'UTF-8');
                        $guestEmail = htmlspecialchars((string)($row['guest_email'] ?? ''), ENT_QUOTES, 'UTF-8');
                        $guestInitial = htmlspecialchars(strtoupper(substr(trim((string)$row['guest_name']) ?: 'G', 0, 1)), ENT_QUOTES, 'UTF-8');
                        $roomName = htmlspecialchars($row['room_name'], ENT_QUOTES, 'UTF-8');
                        $stayDates = htmlspecialchars((string)($row['stay_dates_display'] ?? ''), ENT_QUOTES, 'UTF-8');
                        $nightsCount = (int)($row['nights'] ?? 1);
                        $methodRaw = $row['method'];
                        $method = htmlspecialchars($methodRaw, ENT_QUOTES, 'UTF-8');
                        $txn = htmlspecialchars($row['txn'], ENT_QUOTES, 'UTF-8');
                        $amount = htmlspecialchars($row['amount_formatted'], ENT_QUOTES, 'UTF-8');
                        $statusDisplay = $row['status_display'];
                        $statusClass = $row['status_class'];
                        $paidAtDisplay = htmlspecialchars((string)($row['paid_at_display'] ?? ''), ENT_QUOTES, 'UTF-8');
                        $csrf = htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8');
                        ?>
                        <tr class="payment-row payment-row--<?php echo htmlspecialchars($statusDisplay, ENT_QUOTES, 'UTF-8'); ?>" data-method="<?php echo strtolower($method); ?>">
                            <td>
                                <div class="payment-guest-cell">
                                    <div class="payment-avatar"><?php echo $guestInitial; ?></div>
                                    <div class="payment-guest-meta">
                                        <div class="payment-inv-line">
                                            <span class="payment-primary"><?php echo $invoiceCode; ?></span>
                                            <?php if ($bookingRef !== ''): ?>
                                                <span class="payment-ref-chip"><?php echo $bookingRef; ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <span class="payment-guest-name"><?php echo $guestName; ?></span>
                                        <?php if ($guestEmail !== ''): ?>
                                            <span class="payment-secondary payment-guest-email"><?php echo $guestEmail; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="payment-primary"><?php echo $roomName; ?></span>
                                <?php if ($stayDates !== ''): ?>
                                    <span class="payment-secondary"><?php echo $stayDates; ?></span>
                                    <span class="payment-stay-pill"><?php echo $nightsCount; ?> night<?php echo $nightsCount === 1 ? '' : 's'; ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="payment-detail-stack">
                                    <div class="payment-method-line">
                                        <span class="payment-primary"><?php echo $method; ?></span>
                                        <span class="payment-type-badge<?php echo htmlspecialchars($row['purpose_badge_class'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php echo htmlspecialchars($row['purpose_label'], ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </div>
                                    <?php if ($row['is_gcash_ref']): ?>
                                        <span class="payment-gcash-ref">
                                            <span class="payment-gcash-ref-label">GCash Ref:</span>
                                            <span class="payment-gcash-ref-code"><?php echo $txn; ?></span>
                                        </span>
                                    <?php else: ?>
                                        <span class="payment-secondary payment-reference" title="<?php echo $txn; ?>">Ref: <?php echo $txn; ?></span>
                                    <?php endif; ?>

                                    <?php if ($row['receipt_url'] === ''): ?>
                                        <span class="payment-proof-chip payment-proof-chip--none">No receipt attached</span>
                                    <?php elseif ($row['receipt_available']): ?>
                                        <button
                                            type="button"
                                            class="payment-receipt-link"
                                            data-action="open-proof"
                                            data-url="<?php echo htmlspecialchars($row['receipt_url'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-ref="<?php echo $txn; ?>">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                            View receipt &rarr;
                                        </button>
                                    <?php else: ?>
                                        <span class="payment-proof-chip payment-proof-chip--missing" title="The database record exists, but the receipt file is missing from private storage.">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                            Receipt unavailable
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="payment-amount-cell">
                                    <span class="payment-amount-value">₱<?php echo $amount; ?></span>
                                    <div>
                                        <span class="status-badge <?php echo htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8'); ?>">
                                            <span class="status-dot"></span>
                                            <?php echo htmlspecialchars(ucfirst($statusDisplay), ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </div>
                                    <?php if ($paidAtDisplay !== ''): ?>
                                        <span class="payment-secondary"><?php echo $paidAtDisplay; ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="payment-history-cell">
                                <?php if (!empty($row['history'])): ?>
                                    <?php foreach ($row['history'] as $history): ?>
                                        <div class="payment-history-entry">
                                            <div class="payment-history-head">
                                                <strong><?php echo htmlspecialchars(ucfirst((string)$history['action']), ENT_QUOTES, 'UTF-8'); ?></strong>
                                            </div>
                                            <span class="payment-secondary">
                                                by <?php echo htmlspecialchars((string)$history['performed_by'], ENT_QUOTES, 'UTF-8'); ?><br>
                                                <?php echo htmlspecialchars(date('M j, Y g:i A', strtotime((string)$history['performed_at'])), ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="payment-history-empty">Awaiting verification</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($statusDisplay === 'pending'): ?>
                                    <div class="payment-actions payment-actions--pending">
                                        <form method="POST" action="payments">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                            <input type="hidden" name="action" value="verify_payment">
                                            <input type="hidden" name="payment_id" value="<?php echo $payId; ?>">
                                            <select name="payment_method" class="payment-method-select" aria-label="Verify payment channel">
                                                <option value="Front Desk Cash" <?php echo $methodRaw === 'Front Desk Cash' ? 'selected' : ''; ?>>Cash</option>
                                                <option value="PayMongo (Card)" <?php echo (stripos($methodRaw, 'Card') !== false || stripos($methodRaw, 'Visa') !== false) ? 'selected' : ''; ?>>PayMongo (Visa / Card)</option>
                                                <option value="PayMongo (GCash)" <?php echo (stripos($methodRaw, 'PayMongo') !== false && stripos($methodRaw, 'GCash') !== false) ? 'selected' : ''; ?>>PayMongo (GCash)</option>
                                                <option value="PayMongo (Maya)" <?php echo stripos($methodRaw, 'Maya') !== false ? 'selected' : ''; ?>>PayMongo (Maya)</option>
                                                <option value="GCash QR" <?php echo ($methodRaw === 'GCash' || $methodRaw === 'GCash QR') ? 'selected' : ''; ?>>GCash (Manual QR)</option>
                                                <option value="Bank Deposit" <?php echo $methodRaw === 'Bank Deposit' ? 'selected' : ''; ?>>Bank Deposit</option>
                                                <option value="Front Desk Card" <?php echo $methodRaw === 'Front Desk Card' ? 'selected' : ''; ?>>POS Card</option>
                                            </select>
                                            <div class="payment-action-pair">
                                                <button type="submit" class="btn-pay payment-action-btn">
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                                    Verify
                                                </button>
                                                <button
                                                    type="button"
                                                    class="btn-receipt payment-action-btn payment-reject-btn"
                                                    data-action="open-reject"
                                                    data-pay-id="<?php echo $payId; ?>"
                                                    data-guest="<?php echo $guestName; ?>"
                                                    data-invoice="<?php echo $invoiceCode; ?>">
                                                    Reject
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                <?php elseif ($statusDisplay === 'verified' || $statusDisplay === 'paid'): ?>
                                    <div class="payment-actions payment-actions--settled">
                                        <button
                                            type="button"
                                            class="btn-receipt payment-action-btn payment-btn-print"
                                            data-action="open-receipt"
                                            data-booking-id="<?php echo (int)$row['booking_id']; ?>"
                                            data-rcpt="<?php echo htmlspecialchars($row['rcpt_num'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-inv="<?php echo $invoiceCode; ?>"
                                            data-guest="<?php echo $guestName; ?>"
                                            data-room="<?php echo $roomName; ?>"
                                            data-total="<?php echo htmlspecialchars((string)$row['booking_total'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-guests="<?php echo (int)$row['guest_count']; ?>"
                                            data-extra-adults="<?php echo (int)$row['extra_adults']; ?>"
                                            data-extra-children="<?php echo (int)$row['extra_children']; ?>"
                                            data-discount-category="<?php echo htmlspecialchars((string)$row['discount_category'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-discount-amount="<?php echo htmlspecialchars((string)$row['discount_amount'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                                            Print Receipt
                                        </button>
                                        <div class="payment-action-pair">
                                            <a href="payments?send_booking_id=<?php echo (int)$row['booking_id']; ?>" class="btn-pay payment-action-btn payment-btn-resend" title="Resend confirmation &amp; QR pass email">
                                                Resend Email
                                            </a>
                                            <button
                                                type="button"
                                                class="btn-receipt refund-action payment-action-btn"
                                                data-action="open-refund"
                                                data-pay-id="<?php echo $payId; ?>"
                                                data-guest="<?php echo $guestName; ?>"
                                                data-amount="PHP <?php echo $amount; ?>">
                                                Refund
                                            </button>
                                        </div>
                                    </div>
                                <?php elseif ($statusDisplay === 'refunded'): ?>
                                    <span class="refunded-action">↩ Refunded</span>
                                <?php else: ?>
                                    <span class="payment-rejected-label">✕ Rejected</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="payment-empty-row">No payment records found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
