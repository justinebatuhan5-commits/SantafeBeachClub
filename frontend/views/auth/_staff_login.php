    <!-- Auto-sliding background -->
    <div class="bg-slideshow" aria-hidden="true">
        <div class="bg-slide active"></div>
        <div class="bg-slide"></div>
        <div class="bg-slide"></div>
    </div>
    <div class="bg-overlay" aria-hidden="true"></div>

    <!-- Slide progress indicators -->
    <div class="slide-indicators" aria-hidden="true">
        <div class="slide-dot active" data-slide="0"></div>
        <div class="slide-dot" data-slide="1"></div>
        <div class="slide-dot" data-slide="2"></div>
    </div>

    <!-- Fullscreen Verification Overlay -->
    <div id="authLoader" class="auth-loader-screen" aria-hidden="true">
        <div class="loader-ring-box">
            <div class="loader-ring"></div>
            <img src="assets/images/sf_logo.jpg" alt="Logo" class="loader-logo">
        </div>
        <p class="loader-caption">Verifying Staff Credentials...</p>
    </div>

    <main class="portal-container" data-portal="staff" data-endpoint="staff_login" data-recaptcha-action="staff_login" data-dynamic-greeting="1">
        <!-- ── Left Architectural Showcase ──────────────── -->
        <section class="hero-section" aria-label="Front Desk Information">
            <div class="hero-content">
                <div class="hero-brand">
                    <img src="assets/images/sf_logo.jpg" alt="Santa Fe Beach Club" class="hero-logo">
                    <div>
                        <div class="hero-brand-name">Santa Fe Beach Club</div>
                        <div class="hero-brand-sub">Front Desk Operations</div>
                    </div>
                </div>
            </div>

            <div class="hero-centerpiece">
                <div class="hero-badge-tag">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21V5a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v16"></path><path d="M17 21v-8a2 2 0 0 0-2-2H9a2 2 0 0 0-2 2v8"></path></svg>
                    Reception Desk
                </div>
                <h1 class="hero-title" id="greetingTitle">Welcome <span>Front Desk</span></h1>
                <p class="hero-subtitle">Guest registration, room reservations, active bookings, key management, and concierge hospitality services.</p>

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
                <div class="terminal-pill">
                    <span class="pulse-dot"></span>
                    Secure receptionist access
                </div>
                <div>&copy; <?php echo date('Y'); ?> Santa Fe Beach Club &bull; Developed by <span style="color:#FAF7F2; font-weight:600;">Justine Batuhan</span></div>
            </div>
        </section>

        <!-- ── Right Reception Form ─────────────────────── -->
        <section class="form-section" aria-label="Sign In Form">
            <div class="form-card">
                <div class="card-header">
                    <span class="card-badge">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                        Reception Staff
                    </span>
                    <h2 class="card-title">Staff Sign In</h2>
                    <p class="card-desc">Enter your receptionist email and password to begin your shift.</p>
                </div>

                <?php if (isset($_GET['timeout']) && $_GET['timeout'] == '1'): ?>
                <div class="timeout-banner" id="timeoutBanner" role="alert" aria-live="polite">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <span>Your shift session timed out due to inactivity. Please sign in again.</span>
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

                <form id="loginForm" method="POST" action="staff_login" autocomplete="on" novalidate>
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="ajax" value="1">
                    <input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response">

                    <div class="input-block">
                        <label class="input-label" for="username">Staff Email</label>
                        <div class="input-box">
                            <input type="email" id="username" name="username" autofocus autocomplete="username" data-label="Email" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" placeholder="reception@santabeachclub.com" <?php echo $isStaffPortalLocked ? 'disabled style="background:#F1F5F9;cursor:not-allowed;"' : ''; ?>>
                            <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                        </div>
                    </div>

                    <div class="input-block">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <label class="input-label" for="password">Password</label>
                        </div>
                        <div class="input-box">
                            <input type="password" id="password" name="password" autocomplete="current-password" data-label="Password" placeholder="Enter your password" <?php echo $isStaffPortalLocked ? 'disabled style="background:#F1F5F9;cursor:not-allowed;"' : ''; ?>>
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

                    <button type="submit" id="submitBtn" class="btn-submit" <?php echo $isStaffPortalLocked ? 'disabled style="opacity:0.6;cursor:not-allowed;"' : ''; ?>>
                        <span><?php echo $isStaffPortalLocked ? 'Portal Locked (Maintenance)' : 'Sign In'; ?></span>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                    </button>
                </form>

                <div class="switch-portal-wrap">
                    For password changes, please contact your administrator.
                </div>
            </div>
        </section>
    </main>

    <?php if ($isStaffPortalLocked): ?>
    <!-- ── Maintenance Lockout Popup Modal ── -->
    <div class="portal-lock-overlay" id="portalLockOverlay" role="dialog" aria-modal="true" aria-labelledby="lockModalTitle">
        <div class="portal-lock-modal">
            <div style="display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 16px;">
                <img src="assets/logo.jpg" alt="Santa Fe Beach Club" style="width: 38px; height: 38px; border-radius: 10px; object-fit: cover; box-shadow: 0 4px 10px rgba(0,0,0,0.12);">
                <span style="font-family: 'Outfit', sans-serif; font-size: 13px; font-weight: 700; color: #84563C; letter-spacing: 0.5px; text-transform: uppercase;">Santa Fe Beach Club</span>
            </div>

            <div class="portal-lock-badge">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                System Maintenance
            </div>

            <div class="portal-lock-icon-wrap">
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            </div>

            <h3 class="portal-lock-title" id="lockModalTitle">Staff Portal Offline</h3>

            <div class="portal-lock-msg">
                <strong style="display:flex;align-items:center;justify-content:center;gap:6px;color:#991B1B;margin-bottom:6px;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    Reception Sign-In Restricted
                </strong>
                <?php echo nl2br(htmlspecialchars($staffPortalLockMsg)); ?>
            </div>

            <div class="portal-lock-actions">
                <button type="button" class="btn-portal-dismiss" data-dismiss-overlay="portalLockOverlay">
                    Dismiss &amp; View Screen
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>
