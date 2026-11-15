    <div id="authLoader" class="auth-loader-screen" aria-hidden="true">
        <div class="auth-loader-box">
            <div class="auth-loader-spinner"></div>
        </div>
    </div>

    <div class="bg-shape shape-1"></div>
    <div class="bg-shape shape-2"></div>

    <div class="otp-wrapper">
        <div class="icon-badge">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
        </div>

        <h1 class="form-title">Enter Verification Code</h1>
        <p class="form-subtitle">
            We sent a 6-digit code to <br>
            <span class="email-highlight"><?php echo htmlspecialchars($emailHint); ?></span>
        </p>

        <div id="msgContainer">
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
        </div>

        <form id="otpForm" method="POST" action="verify_otp">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="ajax" value="1">
            <input type="hidden" id="fullOtp" name="otp" value="">

            <div class="otp-inputs">
                <input type="text" maxlength="1" class="otp-digit" pattern="[0-9]" inputmode="numeric" autofocus required>
                <input type="text" maxlength="1" class="otp-digit" pattern="[0-9]" inputmode="numeric" required>
                <input type="text" maxlength="1" class="otp-digit" pattern="[0-9]" inputmode="numeric" required>
                <input type="text" maxlength="1" class="otp-digit" pattern="[0-9]" inputmode="numeric" required>
                <input type="text" maxlength="1" class="otp-digit" pattern="[0-9]" inputmode="numeric" required>
                <input type="text" maxlength="1" class="otp-digit" pattern="[0-9]" inputmode="numeric" required>
            </div>

            <button type="submit" id="verifyBtn" class="btn-verify">Verify &amp; Sign In</button>
        </form>

        <div class="resend-section">
            Didn't receive the code?
            <button type="button" id="resendBtn" class="btn-link">Resend Code</button>
            <span id="countdownText" class="countdown-text">(in <span id="timer">60</span>s)</span>
        </div>

        <a href="logout" class="back-link">&larr; Cancel and return to <?php echo ($loginSource === 'admin') ? 'Executive Login' : 'Front Desk Login'; ?></a>
    </div>
