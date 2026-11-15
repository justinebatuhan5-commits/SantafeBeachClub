<main class="shell">
    <section class="card" id="refundDetailsCard"<?php if ($valid): ?> data-csrf="<?= htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8') ?>" data-token="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>"<?php endif; ?>>
        <div class="head">
            <h1>GCash refund details</h1>
            <div>Santa Fe Beach Club secure refund form</div>
        </div>
        <div class="body">
            <?php if (!$valid): ?>
                <div class="msg err">This link is invalid, expired, or has already been used. Please contact the resort for a new link.</div>
            <?php else: ?>
                <div class="summary">
                    <strong>REF-<?= str_pad((string)$refund['booking_id'], 3, '0', STR_PAD_LEFT) ?></strong><br>
                    Guest: <?= htmlspecialchars($refund['guest_name']) ?><br>
                    Approved refund: <strong>₱<?= number_format((float)$refund['net_refund'], 2) ?></strong><br>
                    Link expires: <?= htmlspecialchars(date('M j, Y g:i A', strtotime($refund['token_expires_at']))) ?>
                </div>
                <div id="message" class="msg"></div>

                <?php if (!$verified): ?>
                    <div class="verify">
                        <h2>Verify your booking email</h2>
                        <p>We will send a separate six-digit code to <strong><?= htmlspecialchars($maskedEmail) ?></strong>. The destination cannot be changed here.</p>
                        <?php if (!empty($refund['verification_locked_at'])): ?>
                            <div class="msg err">Verification is locked after too many failed attempts. Please contact the resort.</div>
                        <?php elseif (!$emailReady): ?>
                            <div class="msg err">Email verification is temporarily unavailable or the booking email is invalid. Please contact the resort.</div>
                        <?php else: ?>
                            <button type="button" class="btn" id="sendOtpBtn">Send verification code</button>
                            <div class="otp-row">
                                <input id="otp" inputmode="numeric" maxlength="6" placeholder="000000" aria-label="Six-digit verification code">
                                <button type="button" class="btn small" id="verifyOtpBtn">Verify</button>
                            </div>
                            <p>Codes expire in 5 minutes. Resending is available after 60 seconds, up to three sends.</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="verified">Booking email verified</div>
                    <form id="detailsForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(get_csrf_token()) ?>">
                        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                        <div class="field">
                            <label for="accountName">GCash account name</label>
                            <input id="accountName" name="gcash_account_name" maxlength="120" autocomplete="name" required>
                        </div>
                        <div class="field">
                            <label for="mobile">GCash mobile number</label>
                            <input id="mobile" name="gcash_mobile" inputmode="numeric" autocomplete="tel" maxlength="11" placeholder="09XXXXXXXXX" required>
                        </div>
                        <label class="check">
                            <input type="checkbox" name="ownership_confirmed" value="1" required>
                            <span>I confirm that I own or am authorized to receive the refund through this GCash account and that the details are correct.</span>
                        </label>
                        <div class="warning"><strong>Security reminder:</strong> We will never ask for your GCash MPIN, password, OTP, or wallet balance.</div>
                        <button class="btn" id="submitBtn">Submit refund details</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>
</main>
