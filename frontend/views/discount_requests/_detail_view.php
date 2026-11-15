<?php
$id         = $discountData['id'];
$d          = $discountData['detail'];
$stateClass = sfbc_discount_status_class((string)$d['state']);
$dh         = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<section class="discount-card">
    <div class="booking-hero-header">
        <div class="booking-hero-title">
            <span class="discount-status <?php echo $stateClass; ?>"><?php echo $dh($d['state']); ?></span>
            <h2 style="margin-top: 8px;">REF-<?php echo $dh(str_pad((string)$id, 3, '0', STR_PAD_LEFT)); ?> &bull; <?php echo $dh($d['guest_name']); ?></h2>
            <p><?php echo $dh($d['guest_email']); ?> &bull; <?php echo $dh($d['accommodation_name']); ?></p>
        </div>
        <div>
            <?php if (!empty($d['id_file'])): ?>
                <button type="button" class="discount-button js-preview-doc" data-url="../backend/api/discount_document.php?booking=<?php echo $id; ?>" data-title="Private ID · <?php echo $dh($d['guest_name']); ?> (<?php echo $dh(strtoupper((string)$d['category'])); ?>)">
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
            <div class="tile-value"><?php echo $dh($d['check_in']); ?> &rarr; <?php echo $dh($d['check_out']); ?></div>
        </div>
        <div class="detail-tile">
            <div class="tile-label">Eligible Guest</div>
            <div class="tile-value"><?php echo $dh($d['eligible_guest']); ?></div>
        </div>
        <div class="detail-tile">
            <div class="tile-label">Category</div>
            <div class="tile-value"><span class="discount-status" style="background:var(--primary-light); color:var(--primary);"><?php echo $dh(strtoupper((string)$d['category'])); ?></span></div>
        </div>
        <div class="detail-tile">
            <div class="tile-label">Hold Deadline</div>
            <div class="tile-value" style="color:var(--amber-text);"><?php echo $dh($d['hold_until']); ?></div>
        </div>
    </div>

    <div class="pricing-breakdown-card">
        <div class="price-item">
            <span class="p-label">Original Rate</span>
            <span class="p-value">&#8369; <?php echo number_format($d['original_centavos'] / 100, 2); ?></span>
        </div>
        <div class="price-item discount">
            <span class="p-label">Discount Applied</span>
            <span class="p-value">- &#8369; <?php echo number_format(($d['original_centavos'] - $d['total_centavos']) / 100, 2); ?></span>
        </div>
        <div class="price-item total">
            <span class="p-label">Total Amount</span>
            <span class="p-value">&#8369; <?php echo number_format($d['total_centavos'] / 100, 2); ?></span>
        </div>
        <div class="price-item">
            <span class="p-label">Required Deposit</span>
            <span class="p-value">&#8369; <?php echo number_format($d['deposit_centavos'] / 100, 2); ?></span>
        </div>
    </div>

    <?php if (in_array($d['state'], ['Awaiting ID verification', 'More information needed'], true)): ?>
    <form method="post" style="background:#F8FAFC; padding:20px; border-radius:12px; border:1px solid var(--border); margin:20px 0;">
        <?php echo $discountData['csrfField']; ?>
        <input type="hidden" name="booking_id" value="<?php echo $id; ?>">
        <label style="margin-top:0;">Guest Message / Decision Reason
            <textarea name="note" maxlength="2000" rows="3" placeholder="Add any special notes or required documents..."></textarea>
        </label>
        <div class="actions-cluster">
            <button name="action" value="approve" class="discount-button success">Approve 20% &amp; Send Link</button>
            <button name="action" value="information" class="discount-button secondary">Request Information</button>
            <button name="action" value="decline" class="discount-button danger">Decline Discount</button>
        </div>
    </form>
    <?php endif; ?>

    <?php if (!empty($d['note'])): ?>
    <div style="background:#F8FAFC; border-left:4px solid var(--primary); padding:12px 16px; border-radius:6px; margin:16px 0; font-size:14px;">
        <strong style="color:var(--text-main);">Latest Staff Note:</strong><br>
        <span style="color:var(--text-muted);"><?php echo nl2br($dh($d['note'])); ?></span>
    </div>
    <?php endif; ?>

    <div class="secret-link-box">
        <label>Private Guest Booking / Payment Link</label>
        <div class="secret-link-input-group">
            <input type="password" id="privateLink" readonly value="<?php echo $dh($discountData['privateLink']); ?>">
            <button type="button" id="copyLink" class="discount-button secondary" style="white-space:nowrap;">Copy Link</button>
        </div>
        <span id="copyResult" role="status" style="display:block; margin-top:6px; font-size:12.5px; color:var(--green); font-weight:600;"></span>
    </div>

    <form method="post">
        <?php echo $discountData['csrfField']; ?>
        <input type="hidden" name="booking_id" value="<?php echo $id; ?>">
        <div class="actions-cluster">
            <button name="action" value="email" class="discount-button secondary">Resend Guest Email</button>
            <?php if (in_array($d['state'], ['Awaiting Deposit', 'Confirmed'], true) && $discountData['dueAmount'] > 0): ?>
                <button name="action" value="checkout" class="discount-button">Create PayMongo Checkout &amp; Email</button>
            <?php endif; ?>
        </div>
        <?php if (in_array($d['state'], ['Awaiting Deposit', 'Confirmed'], true) && $discountData['dueAmount'] > 0): ?>
            <small class="discount-muted" style="display:block; margin-top:8px;">Creating a checkout reserves the current payment attempt for PayMongo. Otherwise the guest can choose PayMongo or configured manual payment options.</small>
        <?php endif; ?>
    </form>
</section>

<section class="discount-card">
    <div class="discount-card-title">
        <h2>Payment Records</h2>
    </div>
    <?php if (empty($discountData['payments'])): ?>
        <p style="color:var(--text-muted); margin:0;">No payments logged for this booking yet.</p>
    <?php else: ?>
        <?php foreach ($discountData['payments'] as $p):
            $pBadge = 'status-pending';
            if ($p['status'] === 'verified' || $p['status'] === 'completed') {
                $pBadge = 'status-confirmed';
            } elseif ($p['status'] === 'rejected' || $p['status'] === 'failed') {
                $pBadge = 'status-cancelled';
            }
        ?>
        <details class="payment-accordion-item" open>
            <summary>
                <div style="display:flex; align-items:center; gap:12px;">
                    <span class="discount-status <?php echo $pBadge; ?>"><?php echo $dh($p['status']); ?></span>
                    <strong style="color:var(--text-main); font-size:15px;"><?php echo $dh(ucfirst((string)$p['purpose'])); ?></strong>
                    <span style="color:var(--primary); font-weight:700;">&#8369; <?php echo number_format((float)$p['amount'], 2); ?></span>
                </div>
                <span style="font-size:13px; color:var(--text-muted); font-weight:normal;"><?php echo $dh($p['payment_method']); ?></span>
            </summary>
            <div class="payment-accordion-body">
                <div style="margin-bottom:14px; color:var(--text-muted); font-size:14px;">
                    Method: <strong style="color:var(--text-main);"><?php echo $dh($p['payment_method']); ?></strong> &bull;
                    Reference: <code style="background:#F1F5F9; padding:2px 6px; border-radius:4px; font-family:monospace;"><?php echo $dh($p['transaction_id'] ?: 'None'); ?></code>
                </div>

                <?php if (!empty($p['receipt_url'])): ?>
                    <button type="button" class="discount-button secondary js-preview-doc" data-url="../backend/api/discount_document.php?booking=<?php echo $id; ?>&amp;payment=<?php echo (int)$p['id']; ?>" data-title="Payment Receipt · <?php echo $dh(ucfirst((string)$p['purpose'])); ?> · ₱ <?php echo number_format((float)$p['amount'], 2); ?>">
                        <span><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:-2px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></span> View Uploaded Receipt
                    </button>
                <?php endif; ?>

                <?php if ($p['status'] === 'pending' && in_array($p['payment_method'], ['GCash QR', 'Bank Deposit'], true)): ?>
                <form method="post" style="margin-top:18px; padding-top:16px; border-top:1px dashed var(--border);">
                    <?php echo $discountData['csrfField']; ?>
                    <input type="hidden" name="booking_id" value="<?php echo $id; ?>">
                    <input type="hidden" name="payment_id" value="<?php echo (int)$p['id']; ?>">
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
                <?php endif; ?>
            </div>
        </details>
        <?php endforeach; ?>
    <?php endif; ?>
    <?php if (in_array($d['state'], ['Expired', 'Cancelled'], true)): ?>
        <p style="color:var(--red-text); font-weight:500; margin-top:14px;">This hold has ended. Investigate any received payment and arrange a refund or a new booking; do not confirm an expired room hold.</p>
    <?php endif; ?>
</section>

<section class="discount-card">
    <div class="discount-card-title audit-heading">
        <div>
            <h2>Audit History</h2>
            <p>Latest booking activity</p>
        </div>
        <span class="audit-count"><?php echo (int)$discountData['historyCount']; ?> <?php echo $discountData['historyCount'] === 1 ? 'event' : 'events'; ?></span>
    </div>
    <div class="audit-timeline" id="auditTimeline">
    <?php if (!$discountData['historyCount']): ?>
        <p style="color:var(--text-muted); margin:0;">No audit entries recorded yet.</p>
    <?php else: ?>
        <?php foreach ($discountData['historyRows'] as $index => $h): ?>
        <div class="audit-entry<?php echo $index >= 5 ? ' audit-entry-extra' : ''; ?>"<?php echo $index >= 5 ? ' hidden' : ''; ?>>
            <div class="audit-dot" aria-hidden="true"></div>
            <div class="audit-content">
                <div class="audit-title"><?php echo $dh($h['action']); ?></div>
                <?php if (!empty($h['details'])): ?>
                    <div class="audit-details"><?php echo $dh($h['details']); ?></div>
                <?php endif; ?>
            </div>
            <div class="audit-meta">
                <span><?php echo $dh($h['actor']); ?></span>
                <time datetime="<?php echo $dh($h['created_at']); ?>"><?php echo $dh(date('M j, Y · g:i A', strtotime((string)$h['created_at']))); ?></time>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
    </div>
    <?php if ($discountData['historyCount'] > 5): ?>
        <button type="button" class="audit-toggle" id="auditToggle" aria-expanded="false" aria-controls="auditTimeline">
            Show <?php echo (int)($discountData['historyCount'] - 5); ?> more events <span aria-hidden="true">&#8595;</span>
        </button>
    <?php endif; ?>
</section>
