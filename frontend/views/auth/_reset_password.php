    <div class="bg-backdrop"></div>

    <div class="card">
        <div class="logo-wrap">
            <img src="assets/logo.jpg" alt="Logo" class="logo-img">
            <div class="logo-text">
                <h1>SANTA FE BEACH CLUB</h1>
                <p>Security &amp; Access Recovery</p>
            </div>
        </div>

        <?php if (!$verification['valid']): ?>
            <div class="card-header">
                <h2 class="card-title">Invalid Link</h2>
                <p class="card-desc">The password reset link you clicked is invalid or has expired.</p>
            </div>
            <div class="alert-box alert-error">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <span><?php echo htmlspecialchars($verification['error']); ?></span>
            </div>
            <a href="forgot_password" class="btn-submit btn-submit--link">
                <span>Request New Reset Link</span>
            </a>
        <?php else: ?>
            <div class="card-header">
                <h2 class="card-title">Create New Password</h2>
                <p class="card-desc">
                    Resetting password for: <strong><?php echo htmlspecialchars($verification['username']); ?></strong>
                </p>
            </div>

            <div id="statusContainer">
                <?php if ($error): ?>
                    <div class="alert-box alert-error">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                <?php elseif ($success): ?>
                    <div class="alert-box alert-success alert-box--centered">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        <span class="alert-success-msg"><?php echo htmlspecialchars($success); ?></span>
                        <a href="<?php echo ($verification['role'] === 'admin') ? 'admin_login' : 'staff_login'; ?>" class="login-btn">
                            Sign In Now &rarr;
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!$success): ?>
            <form id="resetForm" method="POST" action="reset_password?token=<?php echo htmlspecialchars(urlencode($rawToken)); ?>" novalidate>
                <?php echo csrf_field(); ?>
                <input type="hidden" name="ajax" value="1">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($rawToken); ?>">

                <div class="input-block">
                    <label class="input-label" for="password">New Password</label>
                    <div class="input-box">
                        <input type="password" id="password" name="password" autofocus autocomplete="new-password" data-label="New Password" placeholder="Enter at least 8 characters">
                        <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <button type="button" class="toggle-pw-btn" data-toggle-password="password" aria-label="Toggle password">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>

                <div class="input-block">
                    <label class="input-label" for="confirm_password">Confirm New Password</label>
                    <div class="input-box">
                        <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" data-label="Confirm Password" placeholder="Confirm your password">
                        <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <button type="button" class="toggle-pw-btn" data-toggle-password="confirm_password" aria-label="Toggle password">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>

                <div class="pwd-rules">
                    <strong>Password Security Requirements:</strong>
                    <ul>
                        <li id="rule-len" class="pwd-rule-item"><span>&bull;</span> At least 8 characters</li>
                        <li id="rule-upper" class="pwd-rule-item"><span>&bull;</span> Uppercase letter</li>
                        <li id="rule-lower" class="pwd-rule-item"><span>&bull;</span> Lowercase letter</li>
                        <li id="rule-num" class="pwd-rule-item"><span>&bull;</span> Number &amp; symbol</li>
                    </ul>
                </div>

                <button type="submit" id="submitBtn" class="btn-submit">
                    <span>Save New Password</span>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </button>
            </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
