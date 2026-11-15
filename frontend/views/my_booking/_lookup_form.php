    <!-- Navbar (Lookup view only) -->
    <?php $active_nav = 'my_booking'; require __DIR__ . '/../../partials/_navbar.php'; ?>

<!-- LOOKUP FORM - Guest not authenticated yet -->
<div class="lookup-section">
    <div class="booking-welcome">
        <span class="booking-eyebrow">Santa Fe Beach Club</span>
        <h2>A little closer<br>to your getaway.</h2>
        <p>Your island plans, all in one place. View your reservation and get ready for your stay by the sea.</p>
        <div class="booking-welcome-note">Sun, sand &amp; a stay to remember.</div>
    </div>
    <div class="lookup-card">
        <div class="lookup-card-header">
            <span class="booking-eyebrow">Manage Your Booking</span>
            <h1>Find Your Reservation</h1>
            <p>Enter your email and booking reference to view your reservation</p>
        </div>
        <div class="lookup-card-body">
            <div id="lookupError" style="display:none;" class="lookup-error">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <span id="lookupErrorMsg"></span>
            </div>

            <!-- Step 1: Lookup Form -->
            <form id="lookupForm" data-recaptcha-site-key="<?php echo htmlspecialchars(RECAPTCHA_SITE_KEY, ENT_QUOTES, 'UTF-8'); ?>" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response">

                <div class="lf-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" placeholder="you@example.com" required autocomplete="email">
                    <p class="lf-hint">The email you used when booking</p>
                </div>

                <div class="lf-group">
                    <label for="booking_ref">Booking Reference</label>
                    <input type="text" id="booking_ref" name="booking_ref" placeholder="REF-001" required autocomplete="off"
                           style="text-transform: uppercase; letter-spacing: 1px;">
                    <p class="lf-hint">Found in your booking confirmation email (e.g. REF-042)</p>
                </div>

                <button type="submit" class="btn-lookup" id="lookupBtn">
                    <span id="lookupBtnText">Find Reservation</span>
                    <span class="btn-spinner" id="lookupSpinner"></span>
                </button>
            </form>

            <!-- Step 2: OTP Verification Form (Hidden Initially) -->
            <div id="otpStepContainer" style="display:none; text-align:center;">
                <div class="lookup-success-msg" id="otpSuccessBanner">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                    <span>We sent a 6-digit verification code to your email.</span>
                </div>

                <form id="guestOtpForm" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" id="guestFullOtp" name="otp" value="">

                    <div class="otp-box-group">
                        <input type="text" maxlength="1" class="otp-box-digit" pattern="[0-9]" inputmode="numeric" required>
                        <input type="text" maxlength="1" class="otp-box-digit" pattern="[0-9]" inputmode="numeric" required>
                        <input type="text" maxlength="1" class="otp-box-digit" pattern="[0-9]" inputmode="numeric" required>
                        <input type="text" maxlength="1" class="otp-box-digit" pattern="[0-9]" inputmode="numeric" required>
                        <input type="text" maxlength="1" class="otp-box-digit" pattern="[0-9]" inputmode="numeric" required>
                        <input type="text" maxlength="1" class="otp-box-digit" pattern="[0-9]" inputmode="numeric" required>
                    </div>

                    <button type="submit" class="btn-lookup" id="guestVerifyBtn">
                        <span id="guestVerifyBtnText">Verify & View Booking</span>
                        <span class="btn-spinner" id="guestVerifySpinner" style="display:none;"></span>
                    </button>
                </form>

                <div style="margin-top:14px; font-size:13px; color:#6B7280;">
                    Didn't receive code?
                    <button type="button" id="guestResendBtn" style="background:none; border:none; color:#7C533C; font-weight:700; cursor:pointer; text-decoration:underline;">Resend</button>
                    <span id="guestResendTimer" style="display:none; color:#9CA3AF;">(in <span id="guestSeconds">60</span>s)</span>
                </div>

                <div style="margin-top:16px;">
                    <button type="button" id="guestBackBtn" style="background:none; border:none; color:#94A3B8; font-size:12px; cursor:pointer;">&larr; Change email or booking ref</button>
                </div>
            </div>

            <p class="lf-recaptcha-note">Protected by reCAPTCHA. Google <a href="https://policies.google.com/privacy" target="_blank" rel="noopener noreferrer">Privacy Policy</a> and <a href="https://policies.google.com/terms" target="_blank" rel="noopener noreferrer">Terms of Service</a> apply.</p>
            <p class="lf-help-row">
                Need help? <a href="contact" class="lf-contact-link">Contact us</a>
            </p>
        </div>
    </div>
</div>
