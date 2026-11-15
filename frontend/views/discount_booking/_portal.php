<main class="discount-shell">
    <a href="index.php">Santa Fe Beach Club</a>
    <h1>Your booking &amp; payment</h1>
    <p>REF-<?= dh(str_pad((string)$id, 3, '0', STR_PAD_LEFT)) ?> · <?= dh($d['accommodation_name']) ?></p>

    <?php if ($message): ?><div class="discount-note" role="status"><?= dh($message) ?></div><?php endif ?>
    <?php if ($error): ?><div class="discount-note discount-error" role="alert"><?= dh($error) ?></div><?php endif ?>

    <section class="discount-card">
        <span class="discount-status"><?= dh($d['state']) ?></span>
        <p><?= dh($d['guest_name']) ?> · <?= dh($d['check_in']) ?> to <?= dh($d['check_out']) ?></p>
        <?php if ($d['state'] !== 'Confirmed'): ?>
            <p>Room hold deadline: <strong><?= dh($d['hold_until']) ?> (Philippine time)</strong>. Approval and a verified deposit are required to confirm your reservation.</p>
        <?php endif ?>
        <?php if ($d['note']): ?><p>Staff message: <?= nl2br(dh($d['note'])) ?></p><?php endif ?>
        <p><a href="<?= dh(discount_link($d)) ?>">Your private booking link</a> — save it and keep it private.</p>
    </section>

    <div class="discount-grid">
        <section class="discount-card">
            <h2><?= $estimate ? 'Estimated price — pending approval' : 'Booking price' ?></h2>
            <div class="discount-money"><span>Original entire-room amount</span><strong>₱ <?= number_format($d['original_centavos'] / 100, 2) ?></strong></div>
            <div class="discount-money"><span><?= $d['total_centavos'] < $d['original_centavos'] ? '20% Senior Citizen / PWD discount' : 'Discount' ?></span><strong>−₱ <?= number_format(($d['original_centavos'] - $d['total_centavos']) / 100, 2) ?></strong></div>
            <div class="discount-money"><span>Total</span><strong>₱ <?= number_format($d['total_centavos'] / 100, 2) ?></strong></div>
            <div class="discount-money"><span>50% deposit</span><strong>₱ <?= number_format($d['deposit_centavos'] / 100, 2) ?></strong></div>
            <div class="discount-money"><span>Verified payments</span><strong>₱ <?= number_format($paid / 100, 2) ?></strong></div>
            <p>Remaining total: ₱ <?= number_format(max(0, $d['total_centavos'] - $paid) / 100, 2) ?>.<br>Balance due <?= dh(date('d M Y', strtotime($d['check_in'] . ' -1 day'))) ?>.</p>
        </section>

        <section class="discount-card">
            <h2>Next step</h2>
            <?php if ($pending && !in_array($d['state'], ['Expired', 'Cancelled'], true)): ?>
                <p><?= $pending['checkout_url'] ? 'Your secure checkout is ready.' : 'Your receipt is awaiting staff verification. Please do not pay again.' ?></p>
                <?php if ($pending['checkout_url']): ?>
                    <div style="display:flex; flex-wrap:wrap; gap:10px; align-items:center; margin-bottom:12px;">
                        <a class="discount-button" href="<?= dh($pending['checkout_url']) ?>">Continue payment</a>
                        <form method="post" style="display:inline;"><?= csrf_field() ?><button name="action" value="refresh_payment" class="secondary">Check payment status</button></form>
                    </div>
                    <?php if ($gcash || $bank): ?>
                        <form method="post" style="margin-top:16px; border-top:1px solid var(--line); padding-top:14px;">
                            <?= csrf_field() ?>
                            <p class="discount-muted" style="margin:0 0 8px 0; font-size:14px;">Prefer to pay directly using GCash or Bank account instead of online checkout?</p>
                            <button name="action" value="switch_manual" class="secondary" style="font-size:14px; padding:8px 14px;">Prefer to pay manually via GCash / Bank? Upload receipt instead</button>
                        </form>
                    <?php endif ?>
                <?php endif ?>
            <?php elseif ($d['state'] === 'Confirmed'): ?>
                <div style="background:#eef7f2; border:1px solid #c3e2d1; border-radius:12px; padding:20px; text-align:center;">
                    <div style="width:48px; height:48px; margin:0 auto 12px; border-radius:50%; background:#dcfce7; color:#166534; display:flex; align-items:center; justify-content:center;"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div>
                    <h3 style="margin:0 0 8px 0; color:#165e48;">Reservation Confirmed</h3>
                    <p style="margin:0 0 12px 0; color:var(--ink); font-size:15px;">Your 50% deposit has been verified and your room is reserved.</p>
                    <div style="background:#fff; border:1px solid #dce6df; border-radius:8px; padding:12px; margin-top:10px; font-size:14px; text-align:left;">
                        <div><strong>Remaining Balance:</strong> ₱ <?= number_format(max(0, $d['total_centavos'] - $paid) / 100, 2) ?></div>
                        <div style="color:var(--muted); margin-top:4px;">Please settle this remaining balance at the <strong>front desk during check-in</strong>.</div>
                    </div>
                </div>
            <?php elseif (in_array($d['state'], ['Awaiting Deposit'], true) && discount_due($conn, $d) > 0): ?>
                <p>Amount due now: <strong>₱ <?= number_format(discount_due($conn, $d) / 100, 2) ?></strong></p>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="pay"><input type="hidden" name="method" value="PayMongo Online"><button>Pay <?= $d['state'] === 'Confirmed' ? 'Balance' : 'Deposit' ?> with PayMongo</button></form>
                <?php if ($gcash || $bank): ?>
                    <details open>
                        <summary>GCash / bank transfer</summary>
                        <?php if ($gcash): ?>
                            <div style="margin:16px 0; text-align:center; background:#f4f9f6; border:1px solid var(--line); border-radius:12px; padding:16px;">
                                <p style="margin:0 0 10px; font-weight:600; color:var(--ink);">Scan to Pay with GCash</p>
                                <div style="display:inline-block; background:#fff; padding:10px; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.08); border:1px solid #e0e7e3;">
                                    <img src="assets/gcash_qr.png?v=<?= $gcashQrVersion ?>" alt="GCash QR Code" style="width:180px; height:180px; display:block; object-fit:contain;" onerror="this.src='https://api.qrserver.com/v1/create-qr-code/?size=180x180&amp;data=GCash%3A<?= urlencode($settings['gcash_number']) ?>%20Santa+Fe+Beach+Club'">
                                </div>
                                <div style="margin-top:10px; font-size:15px; color:var(--ink);">
                                    <div><strong>GCash:</strong> <?= dh($settings['gcash_number']) ?></div>
                                    <div><strong>Account Name:</strong> <?= dh($settings['gcash_name']) ?></div>
                                </div>
                            </div>
                        <?php endif ?>
                        <?php if ($bank): ?><p style="margin:12px 0;"><strong><?= dh($settings['discount_bank_name']) ?>:</strong> <?= dh($settings['discount_bank_account']) ?><br><strong>Account Name:</strong> <?= dh($settings['discount_bank_holder']) ?></p><?php endif ?>
                        <form method="post" enctype="multipart/form-data">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="pay">
                            <label>Payment method<select name="method"><?php if ($gcash): ?><option>GCash QR</option><?php endif ?><?php if ($bank): ?><option>Bank Deposit</option><?php endif ?></select></label>
                            <label>Receipt / Screenshot (JPG/PNG, up to 2 MB)<input name="receipt" type="file" accept="image/jpeg,image/png" required></label>
                            <p class="discount-muted" style="font-size:13px;">Staff will verify the actual incoming transaction in the resort GCash account before confirming your reservation.</p>
                            <button>Submit receipt</button>
                        </form>
                    </details>
                <?php endif ?>
            <?php elseif ($d['state'] === 'Discount declined'): ?>
                <p>You may accept the regular price of ₱ <?= number_format($d['original_centavos'] / 100, 2) ?> or cancel this unpaid request.</p>
                <form method="post"><?= csrf_field() ?><button name="action" value="regular">Accept regular price</button><button name="action" value="cancel" class="secondary">Cancel request</button></form>
            <?php elseif ($d['state'] === 'More information needed'): ?>
                <form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="resubmit"><label>Replacement ID (JPG/PNG, up to 2 MB)<input type="file" name="discount_id" accept="image/jpeg,image/png" required></label><button>Resubmit for verification</button></form>
            <?php else: ?>
                <p><?= in_array($d['state'], ['Expired', 'Cancelled'], true) ? 'This room hold has ended. Contact reception if you already sent a payment. Submit a new booking request to check availability.' : 'Staff will verify your ID. No payment is required while you wait.' ?></p>
            <?php endif ?>
            <?php if (!$pending && in_array($d['state'], ['Awaiting ID verification', 'More information needed', 'Awaiting Deposit'], true)): ?>
                <form method="post"><?= csrf_field() ?><button name="action" value="cancel" class="secondary">Cancel unpaid request</button></form>
            <?php endif ?>
        </section>
    </div>

    <p><a href="mailto:santafebeachclub@gmail.com">Contact reception</a> · Refresh this page to see the latest status.</p>
</main>
