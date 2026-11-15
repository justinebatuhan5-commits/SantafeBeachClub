    <div class="bg-backdrop"></div>

    <div class="card">
        <div class="logo-wrap">
            <img src="assets/logo.jpg" alt="Logo" class="logo-img">
            <div class="logo-text">
                <h1>SANTA FE BEACH CLUB</h1>
                <p>Security &amp; Access Recovery</p>
            </div>
        </div>

        <div class="card-header">
            <h2 class="card-title">Reset Password</h2>
            <p class="card-desc">
                Enter the email address associated with your account and we'll send a secure password reset link.
            </p>
        </div>

        <div id="statusContainer">
            <?php if ($error): ?>
                <div class="alert-box alert-error">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php elseif ($success): ?>
                <div class="alert-box alert-success">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
            <?php endif; ?>
        </div>

        <form id="forgotForm" method="POST" action="forgot_password<?php echo $portal === 'admin' ? '?portal=admin' : '?portal=staff'; ?>" data-recaptcha-site-key="<?php echo htmlspecialchars(RECAPTCHA_SITE_KEY, ENT_QUOTES, 'UTF-8'); ?>" novalidate>
            <?php echo csrf_field(); ?>
            <input type="hidden" name="ajax" value="1">
            <input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response">

            <div class="input-block">
                <label class="input-label" for="email">Account Email</label>
                <div class="input-box">
                    <input type="email" id="email" name="email" autofocus placeholder="name@santabeachclub.com" data-label="Email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                </div>
            </div>

            <button type="submit" id="submitBtn" class="btn-submit">
                <span>Send Reset Link</span>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </button>
        </form>

        <a href="<?php echo $portal === 'admin' ? 'admin_login' : 'staff_login'; ?>" class="back-link">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            Back to Sign In
        </a>
    </div>
