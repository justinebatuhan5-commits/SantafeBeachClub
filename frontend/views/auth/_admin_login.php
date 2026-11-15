    <!-- Auto-sliding background -->
    <div class="bg-slideshow" aria-hidden="true">
        <div class="bg-slide active"></div>
        <div class="bg-slide"></div>
        <div class="bg-slide"></div>
        <div class="bg-slide"></div>
    </div>
    <div class="bg-overlay" aria-hidden="true"></div>

    <!-- Slide progress indicators -->
    <div class="slide-indicators" aria-hidden="true">
        <div class="slide-dot active" data-slide="0"></div>
        <div class="slide-dot" data-slide="1"></div>
        <div class="slide-dot" data-slide="2"></div>
        <div class="slide-dot" data-slide="3"></div>
    </div>

    <!-- Fullscreen Verification Overlay -->
    <div id="authLoader" class="auth-loader-screen" aria-hidden="true">
        <div class="loader-ring-box">
            <div class="loader-ring"></div>
            <img src="assets/images/sf_logo.jpg" alt="Logo" class="loader-logo">
        </div>
        <p class="loader-caption">Authenticating Credentials...</p>
    </div>

    <main class="portal-container" data-portal="admin" data-endpoint="admin_login" data-recaptcha-action="admin_login" data-dynamic-greeting="0">
        <!-- ── Left Architectural Showcase ──────────────── -->
        <section class="hero-section" aria-label="Resort Information">
            <div class="hero-content">
                <div class="hero-brand">
                    <img src="assets/images/sf_logo.jpg" alt="Santa Fe Beach Club" class="hero-logo">
                    <div>
                        <div class="hero-brand-name">Santa Fe Beach Club</div>
                        <div class="hero-brand-sub">Bantayan Island Resort</div>
                    </div>
                </div>
            </div>

            <div class="hero-centerpiece">
                <div class="hero-badge-tag">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                    Administrator Portal
                </div>
                <h1 class="hero-title" id="greetingTitle">Welcome, <span>Administrator</span></h1>
                <p class="hero-subtitle">Manage resort operations, reservations, payments, reports, and staff activity.</p>

                <div class="hero-clock-box">
                    <div class="clock-time" id="liveClock">--:--:--</div>
                    <div class="clock-divider"></div>
                    <div>
                        <div class="clock-label">Bantayan Island Time (GMT+8)</div>
                        <div class="clock-date" id="liveDate">Loading local time...</div>
                    </div>
                </div>
            </div>

            <div class="hero-footer">
                <div class="system-pill">
                    <span class="pulse-dot"></span>
                    Secure administrator access
                </div>
                <div>&copy; <?php echo date('Y'); ?> Santa Fe Beach Club</div>
            </div>
        </section>

        <!-- ── Right Security Form ──────────────────────── -->
        <section class="form-section" aria-label="Sign In Form">
            <div class="form-card">
                <div class="card-header">
                    <span class="card-badge">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                        Administrator Access
                    </span>
                    <h2 class="card-title">Sign In</h2>
                    <p class="card-desc">Enter your administrator email and password to continue.</p>
                </div>

                <?php if (isset($_GET['timeout']) && $_GET['timeout'] == '1'): ?>
                <div class="timeout-banner" id="timeoutBanner" role="alert" aria-live="polite">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <span>Your session timed out due to inactivity. Please sign in again.</span>
                    <button type="button" class="timeout-banner-close" data-dismiss-parent aria-label="Dismiss">&times;</button>
                </div>
                <?php endif; ?>

                <div id="errorContainer">
                    <?php if ($error): ?>
                        <div class="error">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                            <span><?php echo htmlspecialchars($error); ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <form id="loginForm" method="POST" action="admin_login" autocomplete="on" novalidate>
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="ajax" value="1">
                    <input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response">

                    <div class="input-block">
                        <label class="input-label" for="username">Administrator Email</label>
                        <div class="input-box">
                            <input type="email" id="username" name="username" autofocus autocomplete="username" data-label="Email" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" placeholder="admin@santabeachclub.com">
                            <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                        </div>
                    </div>

                    <div class="input-block">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <label class="input-label" for="password">Password</label>
                        </div>
                        <div class="input-box">
                            <input type="password" id="password" name="password" autocomplete="current-password" data-label="Password" placeholder="Enter administrative password">
                            <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                            <button type="button" class="toggle-pw-btn" id="togglePwBtn" aria-label="Toggle password visibility">
                                <svg id="eyeIcon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            </button>
                        </div>
                        <div class="caps-warning" id="capsWarning">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            Caps Lock is ON
                        </div>
                    </div>

                    <button type="submit" id="submitBtn" class="btn-submit">
                        <span>Sign In</span>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                    </button>
                </form>

                <div class="switch-portal-wrap">
                    Authorized personnel only. Unauthorized access is strictly prohibited.
                </div>
            </div>
        </section>
    </main>
