<main class="shell">
    <section class="card">
        <div class="head">
            <h1>Confirm GCash refund</h1>
            <div>Santa Fe Beach Club secure confirmation</div>
        </div>
        <div class="body">
            <?php if ($confirmed): ?>
                <div class="notice success">
                    <strong>Thank you—your refund receipt is confirmed.</strong><br>
                    The resort has been notified that you received the GCash transfer.
                </div>
            <?php elseif ($error !== ''): ?>
                <div class="notice error">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?> Please contact the resort if you still need assistance.
                </div>
            <?php else: ?>
                <div class="summary">
                    <strong>REF-<?= str_pad((string)$refund['booking_id'], 3, '0', STR_PAD_LEFT) ?></strong><br>
                    Guest: <?= htmlspecialchars($refund['guest_name'], ENT_QUOTES, 'UTF-8') ?><br>
                    Refund amount: <strong>₱<?= number_format((float)$refund['net_refund'], 2) ?></strong><br>
                    GCash reference: <strong><?= htmlspecialchars($refund['transfer_reference'], ENT_QUOTES, 'UTF-8') ?></strong>
                </div>
                <p class="hint">Please check your GCash account and confirm only if the refund shown above was received. Never share your MPIN, password, or OTP.</p>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
                    <button class="btn" type="submit">I received this GCash refund</button>
                </form>
            <?php endif; ?>
        </div>
    </section>
</main>
