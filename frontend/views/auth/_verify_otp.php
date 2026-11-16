<?php
$otpRemainingSeconds    = max(0, (int)($otpRemainingSeconds ?? 600));
$otpExpiryTotalSeconds  = max(1, (int)($otpExpiryTotalSeconds ?? 600));
$resendRemainingSeconds = max(0, (int)($resendRemainingSeconds ?? 0));
$resendCooldownSeconds  = max(1, (int)($resendCooldownTotalSeconds ?? 60));
$isOtpExpiredInitially  = $otpRemainingSeconds <= 0;
$otpFormattedTime       = sprintf('%02d:%02d', intdiv($otpRemainingSeconds, 60), $otpRemainingSeconds % 60);
$otpProgressPct         = min(100, max(0, (int)round(($otpRemainingSeconds / $otpExpiryTotalSeconds) * 100)));
$otpExpiryStatusClass   = $isOtpExpiredInitially ? ' is-expired' : ($otpRemainingSeconds <= 60 ? ' is-warning' : '');
$initialErrorMessage    = $error ?: ($isOtpExpiredInitially ? 'Verification code has expired. Please click "Resend Code" to receive a new one.' : '');
$isAdminPortal          = (($loginSource ?? 'staff') === 'admin');
$portalSubtitle         = $isAdminPortal ? 'Administrator Portal' : 'Front Desk Operations';
$returnPortalName       = $isAdminPortal ? 'Executive Login' : 'Front Desk Login';
?>
    <div id="authLoader" class="auth-loader-screen" aria-hidden="true">
        <div class="loader-ring-box">
            <div class="loader-ring"></div>
            <img src="assets/images/sf_logo.jpg" alt="Santa Fe Beach Club" class="loader-logo">
        </div>
        <p class="loader-caption">Verifying Security Code...</p>
    </div>

    <div class="bg-backdrop" aria-hidden="true"></div>
    <div class="bg-overlay" aria-hidden="true"></div>

    <main class="otp-wrapper">
        <div class="otp-brand-row">
            <div class="otp-brand">
                <img src="assets/images/sf_logo.jpg" alt="Santa Fe Beach Club" class="otp-brand-logo">
                <div class="otp-brand-text">
                    <span class="otp-brand-name">Santa Fe Beach Club</span>
                    <span class="otp-brand-sub"><?php echo $portalSubtitle; ?></span>
                </div>
            </div>
            <span class="otp-portal-pill">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                </svg>
                2FA Check
            </span>
        </div>

        <header class="otp-header">
            <h1 class="form-title">Enter Verification Code</h1>
            <p class="form-subtitle">
                We sent a 6-digit security code to<br>
                <span class="email-highlight"><?php echo htmlspecialchars($emailHint); ?></span>
            </p>
        </header>

        <div
            id="otpExpiryBanner"
            class="otp-expiry-banner<?php echo $otpExpiryStatusClass; ?>"
            data-remaining="<?php echo $otpRemainingSeconds; ?>"
            data-total="<?php echo $otpExpiryTotalSeconds; ?>"
            data-resend-remaining="<?php echo $resendRemainingSeconds; ?>"
            data-resend-total="<?php echo $resendCooldownSeconds; ?>"
            role="status"
            aria-live="polite"
        >
            <div class="otp-expiry-row">
                <span class="otp-expiry-meta">
                    <svg class="otp-expiry-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                    <span id="otpExpiryLabel" class="otp-expiry-label"><?php echo $isOtpExpiredInitially ? 'Code expired' : 'Code expires in'; ?></span>
                </span>
                <span id="otpExpiryTimer" class="otp-expiry-timer"><?php echo $otpFormattedTime; ?></span>
            </div>
            <span class="otp-expiry-track" aria-hidden="true">
                <span id="otpExpiryProgress" class="otp-expiry-progress" style="width: <?php echo $otpProgressPct; ?>%;"></span>
            </span>
        </div>

        <div id="msgContainer">
            <?php if ($initialErrorMessage): ?>
                <div class="error"><?php echo htmlspecialchars($initialErrorMessage); ?></div>
            <?php endif; ?>
        </div>

        <form id="otpForm" method="POST" action="verify_otp">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="ajax" value="1">
            <input type="hidden" id="fullOtp" name="otp" value="">

            <div class="otp-inputs">
                <input type="text" maxlength="1" class="otp-digit" pattern="[0-9]" inputmode="numeric" aria-label="Digit 1" <?php echo $isOtpExpiredInitially ? 'disabled' : 'autofocus'; ?> required>
                <input type="text" maxlength="1" class="otp-digit" pattern="[0-9]" inputmode="numeric" aria-label="Digit 2" <?php echo $isOtpExpiredInitially ? 'disabled' : ''; ?> required>
                <input type="text" maxlength="1" class="otp-digit" pattern="[0-9]" inputmode="numeric" aria-label="Digit 3" <?php echo $isOtpExpiredInitially ? 'disabled' : ''; ?> required>
                <input type="text" maxlength="1" class="otp-digit" pattern="[0-9]" inputmode="numeric" aria-label="Digit 4" <?php echo $isOtpExpiredInitially ? 'disabled' : ''; ?> required>
                <input type="text" maxlength="1" class="otp-digit" pattern="[0-9]" inputmode="numeric" aria-label="Digit 5" <?php echo $isOtpExpiredInitially ? 'disabled' : ''; ?> required>
                <input type="text" maxlength="1" class="otp-digit" pattern="[0-9]" inputmode="numeric" aria-label="Digit 6" <?php echo $isOtpExpiredInitially ? 'disabled' : ''; ?> required>
            </div>

            <button type="submit" id="verifyBtn" class="btn-verify"<?php echo $isOtpExpiredInitially ? ' disabled' : ''; ?>>
                <span>Verify &amp; Sign In</span>
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                    <polyline points="12 5 19 12 12 19"></polyline>
                </svg>
            </button>
        </form>

        <footer class="otp-footer">
            <div class="resend-section">
                <span>Didn't receive the code?</span>
                <button type="button" id="resendBtn" class="btn-link"<?php echo $resendRemainingSeconds > 0 ? ' disabled' : ''; ?>>Resend Code</button>
                <span id="countdownText" class="countdown-text"<?php echo $resendRemainingSeconds > 0 ? ' style="display: inline;"' : ''; ?>>(in <span id="timer"><?php echo $resendRemainingSeconds > 0 ? $resendRemainingSeconds : $resendCooldownSeconds; ?></span>s)</span>
            </div>

            <a href="logout" class="back-link">&larr; Cancel and return to <?php echo $returnPortalName; ?></a>
        </footer>
    </main>
