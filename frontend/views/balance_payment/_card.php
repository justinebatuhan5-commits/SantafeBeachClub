<div class="card">
    <h1>Complete your booking payment</h1>
    <?php if ($error): ?><div class="notice error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($booking): ?>
        <div class="row"><span>Booking</span><strong>REF-<?= str_pad((string)$booking['id'], 3, '0', STR_PAD_LEFT) ?></strong></div>
        <div class="row"><span>Guest</span><strong><?= htmlspecialchars($booking['guest_name']) ?></strong></div>
        <div class="row"><span>Stay</span><strong><?= htmlspecialchars($booking['check_in']) ?> to <?= htmlspecialchars($booking['check_out']) ?></strong></div>
        <div class="row"><span>Total</span><strong>₱<?= number_format($booking['total_cost'], 2) ?></strong></div>
        <div class="row"><span>Paid</span><strong>₱<?= number_format($booking['amount_paid'], 2) ?></strong></div>
        <p>Remaining balance</p>
        <div class="due">₱<?= number_format($booking['balance_due'], 2) ?></div>
        <?php if ($booking['balance_due'] <= 0.009): ?>
            <div class="notice paid">This booking is paid in full.</div>
        <?php elseif ($booking['balance_status']==='cancellation_review'): ?>
            <div class="notice error">The payment deadline and grace period have passed. Online payment is disabled while the front desk reviews this booking.</div>
        <?php else: ?>
            <div class="notice">Payment deadline: <strong><?= htmlspecialchars($deadline) ?></strong></div>
            <form method="post">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <?= csrf_field() ?>
                <button class="btn" type="submit">Pay Remaining Balance with PayMongo</button>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</div>
