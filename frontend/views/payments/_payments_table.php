<?php
$payments = $pageData['payments'] ?? [];
$csrfToken = $pageData['csrfToken'] ?? '';
?>
<div class="card">
    <div class="card-header">
        <h2>Outstanding &amp; Settled Bills</h2>
    </div>

    <div class="payments-toolbar">
        <?php echo $payments_toolbar_html; ?>
    </div>

    <div class="table-responsive">
        <table class="reservations-table">
            <colgroup>
                <col style="width:17%">
                <col style="width:15%">
                <col style="width:21%">
                <col style="width:13%">
                <col style="width:20%">
                <col style="width:14%">
            </colgroup>
            <thead>
                <tr>
                    <th>Invoice &amp; Guest</th>
                    <th>Accommodation</th>
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
                        $guestName = htmlspecialchars($row['guest_name'], ENT_QUOTES, 'UTF-8');
                        $roomName = htmlspecialchars($row['room_name'], ENT_QUOTES, 'UTF-8');
                        $methodRaw = $row['method'];
                        $method = htmlspecialchars($methodRaw, ENT_QUOTES, 'UTF-8');
                        $txn = htmlspecialchars($row['txn'], ENT_QUOTES, 'UTF-8');
                        $amount = htmlspecialchars($row['amount_formatted'], ENT_QUOTES, 'UTF-8');
                        $statusDisplay = $row['status_display'];
                        $statusClass = $row['status_class'];
                        $csrf = htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8');
                        ?>
                        <tr>
                            <td>
                                <span class="payment-primary"><?php echo $invoiceCode; ?></span>
                                <span class="payment-secondary"><?php echo $guestName; ?></span>
                            </td>
                            <td>
                                <span class="payment-primary"><?php echo $roomName; ?></span>
                            </td>
                            <td>
                                <span class="payment-primary"><?php echo $method; ?></span>
                                <?php if ($row['is_gcash_ref']): ?>
                                    <span class="payment-gcash-ref">
                                        <span class="payment-gcash-ref-label">GCash Ref:</span>
                                        <span class="payment-gcash-ref-code"><?php echo $txn; ?></span>
                                    </span>
                                <?php else: ?>
                                    <span class="payment-secondary payment-reference" title="<?php echo $txn; ?>"><?php echo $txn; ?></span>
                                <?php endif; ?>
                                <span class="payment-type-badge<?php echo htmlspecialchars($row['purpose_badge_class'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars($row['purpose_label'], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                                <?php if ($row['receipt_url'] === ''): ?>
                                    <span class="payment-secondary">No receipt attached</span>
                                <?php elseif ($row['receipt_available']): ?>
                                    <button
                                        type="button"
                                        class="payment-receipt-link"
                                        style="background:none; border:none; padding:0; cursor:pointer; font-family:inherit;"
                                        data-action="open-proof"
                                        data-url="<?php echo htmlspecialchars($row['receipt_url'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-ref="<?php echo $txn; ?>">
                                        View receipt →
                                    </button>
                                <?php else: ?>
                                    <span class="payment-secondary payment-receipt-missing" title="The database record exists, but the receipt file is missing from private storage.">Receipt unavailable</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="payment-primary">PHP <?php echo $amount; ?></span>
                                <span class="payment-secondary">
                                    <span class="status-badge <?php echo htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars(ucfirst($statusDisplay), ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </span>
                            </td>
                            <td class="payment-history-cell">
                                <?php if (!empty($row['history'])): ?>
                                    <?php foreach ($row['history'] as $history): ?>
                                        <div class="payment-history-entry">
                                            <strong><?php echo htmlspecialchars(ucfirst((string)$history['action']), ENT_QUOTES, 'UTF-8'); ?></strong>
                                            <span class="payment-secondary">
                                                by <?php echo htmlspecialchars((string)$history['performed_by'], ENT_QUOTES, 'UTF-8'); ?><br>
                                                <?php echo htmlspecialchars(date('M j, Y g:i A', strtotime((string)$history['performed_at'])), ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="payment-history-empty">No actions recorded</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($statusDisplay === 'pending'): ?>
                                    <div class="payment-actions">
                                        <form method="POST" action="payments">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                            <input type="hidden" name="action" value="verify_payment">
                                            <input type="hidden" name="payment_id" value="<?php echo $payId; ?>">
                                            <select name="payment_method" class="payment-method-select">
                                                <option value="Front Desk Cash" <?php echo $methodRaw === 'Front Desk Cash' ? 'selected' : ''; ?>>Cash</option>
                                                <option value="PayMongo (Card)" <?php echo (stripos($methodRaw, 'Card') !== false || stripos($methodRaw, 'Visa') !== false) ? 'selected' : ''; ?>>PayMongo (Visa / Card)</option>
                                                <option value="PayMongo (GCash)" <?php echo (stripos($methodRaw, 'PayMongo') !== false && stripos($methodRaw, 'GCash') !== false) ? 'selected' : ''; ?>>PayMongo (GCash)</option>
                                                <option value="PayMongo (Maya)" <?php echo stripos($methodRaw, 'Maya') !== false ? 'selected' : ''; ?>>PayMongo (Maya)</option>
                                                <option value="GCash QR" <?php echo ($methodRaw === 'GCash' || $methodRaw === 'GCash QR') ? 'selected' : ''; ?>>GCash (Manual QR)</option>
                                                <option value="Bank Deposit" <?php echo $methodRaw === 'Bank Deposit' ? 'selected' : ''; ?>>Bank Deposit</option>
                                                <option value="Front Desk Card" <?php echo $methodRaw === 'Front Desk Card' ? 'selected' : ''; ?>>POS Card</option>
                                            </select>
                                            <button type="submit" class="btn-pay payment-action-btn">Verify</button>
                                        </form>
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
                                <?php elseif ($statusDisplay === 'verified' || $statusDisplay === 'paid'): ?>
                                    <div class="payment-actions">
                                        <button
                                            type="button"
                                            class="btn-receipt payment-action-btn"
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
                                            Print Receipt
                                        </button>
                                        <a href="payments?send_booking_id=<?php echo (int)$row['booking_id']; ?>" class="btn-pay payment-action-btn">Resend Confirmation</a>
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
                                <?php elseif ($statusDisplay === 'refunded'): ?>
                                    <span class="refunded-action">↩ Refunded</span>
                                <?php else: ?>
                                    <span class="payment-rejected-label">Rejected</span>
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
