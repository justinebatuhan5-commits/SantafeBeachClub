<?php
$success   = $promosData['success'];
$error     = $promosData['error'];
$promos    = $promosData['promos'];
$csrfField = $promosData['csrfField'];
?>
<?php if ($success): ?>
<div class="alert alert-success"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-error"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/></svg><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if (empty($promos)): ?>
<div class="admin-card">
    <div class="empty-state">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
        <p>No promotions yet. Click <strong>New Promotion</strong> to create one.</p>
    </div>
</div>
<?php else: ?>
<div class="responsive-stack promo-grid">
    <?php foreach ($promos as $p):
        $expired = strtotime($p['valid_until']) < strtotime(date('Y-m-d'));
        $active  = $p['is_active'] && !$expired;
    ?>
    <div class="admin-card promo-card<?php echo $active ? ' promo-card--active' : ''; ?>">
        <div class="promo-card-header">
            <div class="promo-card-title-col">
                <h3 class="promo-card-title"><?php echo htmlspecialchars($p['title']); ?></h3>
                <?php if (!empty($p['code'])): ?>
                    <span class="promo-coupon-badge">
                        COUPON: <?php echo htmlspecialchars($p['code']); ?>
                    </span>
                <?php endif; ?>
            </div>
            <span class="badge <?php echo $active ? 'badge-checkedin' : 'badge-checkedout'; ?> promo-status-badge">
                <?php echo $expired ? 'Expired' : ($p['is_active'] ? 'Active' : 'Inactive'); ?>
            </span>
        </div>

        <?php if (!empty($p['description'])): ?>
        <p class="promo-description"><?php echo htmlspecialchars($p['description']); ?></p>
        <?php endif; ?>

        <div class="promo-metrics-row">
            <div>
                <div class="promo-metric-label">Discount</div>
                <div class="promo-discount-val">
                    <?php echo $p['discount_type'] === 'percent' ? $p['discount_value'] . '%' : '₱' . number_format($p['discount_value'], 0); ?>
                </div>
            </div>
            <div>
                <div class="promo-metric-label">Valid Period</div>
                <div class="promo-date-val">
                    <?php echo date('M j', strtotime($p['valid_from'])); ?> – <?php echo date('M j, Y', strtotime($p['valid_until'])); ?>
                </div>
            </div>
            <div>
                <div class="promo-metric-label">Code Usage</div>
                <div class="promo-usage-val">
                    <?php echo number_format((int)$p['usage_count']); ?> used
                    <?php if ($p['usage_limit'] !== null): ?> / <?php echo number_format((int)$p['usage_limit']); ?> limit<?php else: ?> / Unlimited<?php endif; ?>
                </div>
                <?php if ($p['usage_limit'] !== null):
                    $remainingUses = max(0, (int)$p['usage_limit'] - (int)$p['usage_count']); ?>
                    <div class="promo-usage-rem <?php echo $remainingUses > 0 ? 'promo-usage-rem--ok' : 'promo-usage-rem--full'; ?>">
                        <?php echo $remainingUses > 0 ? number_format($remainingUses) . ' remaining' : 'Limit reached'; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <form method="POST" class="promo-limit-form">
            <?php echo $csrfField; ?>
            <input type="hidden" name="action" value="update_limit">
            <input type="hidden" name="promo_id" value="<?php echo (int)$p['id']; ?>">
            <label class="promo-limit-label">USAGE LIMIT
                <input type="number" name="usage_limit" min="1" step="1" value="<?php echo $p['usage_limit'] === null ? '' : (int)$p['usage_limit']; ?>" placeholder="Unlimited" class="promo-limit-input">
            </label>
            <button type="submit" class="btn-secondary promo-limit-save-btn">Save Limit</button>
        </form>

        <div class="promo-actions-row">
            <form method="POST" class="promo-toggle-form">
                <?php echo $csrfField; ?>
                <input type="hidden" name="action" value="toggle_promo">
                <input type="hidden" name="promo_id" value="<?php echo (int)$p['id']; ?>">
                <button type="submit" class="btn-secondary promo-toggle-btn">
                    <?php echo $p['is_active'] ? 'Deactivate' : 'Activate'; ?>
                </button>
            </form>
            <form method="POST" class="js-confirm-only" data-confirm-title="Delete Promotion" data-confirm-msg="This promotion will be permanently deleted." data-confirm-icon="trash" data-confirm-icon-bg="#FEE2E2">
                <?php echo $csrfField; ?>
                <input type="hidden" name="action" value="delete_promo">
                <input type="hidden" name="promo_id" value="<?php echo (int)$p['id']; ?>">
                <button type="submit" class="btn-danger">Delete</button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="modal-overlay" id="addModal">
    <div class="modal-box">
        <button class="modal-close" type="button" data-close-promo-modal>×</button>
        <h3>New Promotion</h3>
        <p class="modal-sub">Create a discount offer for guests.</p>
        <form method="POST">
            <?php echo $csrfField; ?>
            <input type="hidden" name="action" value="add_promo">
            <div class="admin-form-group"><label>Title</label><input type="text" name="title" required placeholder="e.g. Summer Beach Deal"></div>
            <div class="admin-form-group"><label>Promo / Coupon Code (e.g. SUMMER2026)</label><input type="text" name="code" placeholder="e.g. BEACH10" class="promo-code-input"></div>
            <div class="admin-form-group"><label>Description (optional)</label><textarea name="description" rows="2" placeholder="Describe the offer..." class="promo-desc-textarea"></textarea></div>
            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label>Discount Type</label>
                    <select name="discount_type">
                        <option value="percent">Percentage (%)</option>
                        <option value="fixed">Fixed Amount (₱)</option>
                    </select>
                </div>
                <div class="admin-form-group"><label>Discount Value</label><input type="number" name="discount_value" min="0" step="0.01" placeholder="e.g. 20"></div>
            </div>
            <div class="admin-form-group">
                <label>Maximum Number of Uses</label>
                <input type="number" name="usage_limit" min="1" step="1" placeholder="Leave blank for unlimited">
            </div>
            <div class="admin-form-row">
                <div class="admin-form-group"><label>Valid From</label><input type="date" name="valid_from" value="<?php echo date('Y-m-d'); ?>"></div>
                <div class="admin-form-group"><label>Valid Until</label><input type="date" name="valid_until" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>"></div>
            </div>
            <button type="submit" class="btn-primary promo-create-btn">Create Promotion</button>
        </form>
    </div>
</div>
