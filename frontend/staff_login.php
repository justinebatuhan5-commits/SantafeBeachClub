<?php
session_start();
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/helpers/security_headers.php';
require_once __DIR__ . '/../backend/helpers/csrf_helper.php';
require_once __DIR__ . '/../backend/helpers/rate_limiter.php';
require_once __DIR__ . '/../backend/helpers/security_logger.php';
require_once __DIR__ . '/../backend/helpers/validator_helper.php';
require_once __DIR__ . '/../backend/helpers/password_helper.php';

// Already logged in – redirect to correct dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    $dest = ($_SESSION['admin_role'] ?? 'receptionist') === 'admin' ? 'admin_dashboard' : 'dashboard';
    header("Location: $dest");
    exit;
}

$error = '';
$is_ajax = (isset($_POST['ajax']) && $_POST['ajax'] === '1') || 
           (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
           (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check rate limit on login attempts (max 5 attempts per 15 minutes)
    $rateStatus = RateLimiter::check($conn, 'login_attempt', 5, 900);
    if (!$rateStatus['allowed']) {
        $error = "Too many failed login attempts. Please wait {$rateStatus['retry_after']} seconds before trying again.";
        SecurityLogger::log($conn, 'RATE_LIMIT_TRIGGER', 'Login rate limit exceeded', SecurityLogger::LEVEL_WARNING);
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $error]);
            exit;
        }
    } elseif (!verify_csrf_token()) {
        $error = 'Security validation failed (Invalid or expired CSRF token). Please refresh and try again.';
        SecurityLogger::log($conn, 'CSRF_MISMATCH', 'CSRF verification failed on login attempt', SecurityLogger::LEVEL_WARNING);
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $error]);
            exit;
        }
    } else {
        $username = Validator::sanitize($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!empty($username) && !empty($password)) {
            $stmt = $conn->prepare("SELECT id, password, role FROM admins WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                if (pw_verify($password, $row['password'])) {
                    // Transparently upgrade hash if cost/algo has changed
                    if (pw_needs_rehash($row['password'])) {
                        $newHash = pw_hash($password);
                        $upd = $conn->prepare("UPDATE admins SET password = ? WHERE id = ?");
                        $upd->bind_param("si", $newHash, $row['id']);
                        $upd->execute();
                        $upd->close();
                    }

                    // Check role: prevent admin from using reception portal without clarity
                    if ($row['role'] === 'admin') {
                        RateLimiter::hit($conn, 'login_attempt');
                        $error = 'This portal is for Front Desk / Reception staff only. Administrators please use the Executive Admin Portal.';
                        SecurityLogger::log($conn, 'UNAUTHORIZED_PORTAL_ATTEMPT', "Admin account ({$username}) attempted reception login", SecurityLogger::LEVEL_INFO, $username);
                    } else {
                        // --- MFA: Password verified. Now require OTP. ---
                        // Do NOT create a full session yet.
                        require_once __DIR__ . '/../backend/helpers/otp_helper.php';
                        require_once __DIR__ . '/../backend/services/mailer.php';

                        // Fetch receptionist email for OTP delivery
                        $emailRow = null;
                        $emailStmt = $conn->prepare("SELECT email FROM admins WHERE id = ?");
                        $emailStmt->bind_param('i', $row['id']);
                        $emailStmt->execute();
                        $emailResult = $emailStmt->get_result();
                        if ($emailResult) { $emailRow = $emailResult->fetch_assoc(); }
                        $emailStmt->close();

                        $adminEmail = $emailRow['email'] ?? null;

                        // If no email set on account, fall back to username (which is an email)
                        if (empty($adminEmail)) {
                            $adminEmail = $username;
                        }

                        $rawOtp = otp_generate();
                        otp_store_for_admin((int)$row['id'], $rawOtp, $conn);
                        $sendResult = otp_send_email($adminEmail, $rawOtp, $username);

                        // Prevent session fixation before writing partial session
                        session_regenerate_id(true);

                        // Partial session — identifies who is pending MFA, NOT a full auth session
                        $_SESSION['mfa_pending_admin_id']       = (int)$row['id'];
                        $_SESSION['mfa_pending_admin_username'] = $username;
                        $_SESSION['mfa_pending_admin_role']     = $row['role'];
                        $_SESSION['mfa_pending_email_hint']     = substr($adminEmail, 0, 3) . '***' . strstr($adminEmail, '@');
                        $_SESSION['otp_sent_at']                = time();
                        $_SESSION['login_source']               = 'reception';

                        SecurityLogger::log($conn, 'MFA_OTP_SENT', "OTP dispatched for Receptionist MFA (step 2)", SecurityLogger::LEVEL_INFO, $username);
                    }
 
                    // Note: $rawOtp is NOT logged above — only a generic event is recorded.
 
                    if (empty($error)) {
                        if ($is_ajax) {
                            header('Content-Type: application/json');
                            echo json_encode([
                                'success'  => true,
                                'mfa'      => true,
                                'redirect' => 'verify_otp'
                            ]);
                            exit;
                        }

                        header("Location: verify_otp");
                        exit;
                    }
                } else {
                    RateLimiter::hit($conn, 'login_attempt');
                    $error = 'Invalid username or password.';
                    SecurityLogger::log($conn, 'FAILED_LOGIN', "Failed login attempt (bad password) for user: {$username}", SecurityLogger::LEVEL_WARNING, $username);
                }
            } else {
                RateLimiter::hit($conn, 'login_attempt');
                $error = 'Invalid username or password.';
                SecurityLogger::log($conn, 'FAILED_LOGIN', "Failed login attempt (unknown username): {$username}", SecurityLogger::LEVEL_WARNING, $username);
            }
            $stmt->close();
        } else {
            $error = 'Please enter both username and password.';
        }
    } // End CSRF verification else

    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $error
        ]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/jpeg" href="assets/logo.jpg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Login - Santa Fe Beach Club</title>
    <meta name="csrf-token" content="<?php echo htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="assets/js/security.js" defer></script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Outfit', sans-serif;
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            /* Animated Beach-themed Gradient Background */
            background: linear-gradient(-45deg, #f0e5d8, #e6d3c4, #b9c7c9, #d0dbdb);
            background-size: 400% 400%;
            animation: gradientBG 15s ease infinite;
            overflow: hidden;
        }

        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* Decorative background circles for depth */
        .bg-shape {
            position: absolute;
            border-radius: 50%;
            filter: blur(60px);
            z-index: 0;
            animation: floatShape 20s infinite alternate;
        }
        .shape-1 {
            width: 400px; height: 400px;
            background: rgba(197, 168, 142, 0.4);
            top: -100px; left: -100px;
        }
        .shape-2 {
            width: 500px; height: 500px;
            background: rgba(130, 168, 160, 0.3);
            bottom: -150px; right: -100px;
            animation-delay: -5s;
        }

        @keyframes floatShape {
            0% { transform: translate(0, 0) rotate(0deg); }
            100% { transform: translate(100px, 50px) rotate(20deg); }
        }

        .login-wrapper { 
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            border-radius: 24px; 
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.15); 
            max-width: 480px; 
            width: 90%; 
            position: relative; 
            z-index: 1; 
            padding: 50px 40px;
            
            /* Entrance Animation */
            opacity: 0;
            transform: translateY(30px) scale(0.95);
            animation: cardEnter 0.8s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
        }

        @keyframes cardEnter {
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .brand-logo-img { 
            width: 90px; 
            height: 90px;
            border-radius: 20px; 
            box-shadow: 0 10px 25px rgba(100, 75, 57, 0.2); 
            margin-bottom: 20px;
            object-fit: cover;
            
            /* Logo Pop Animation */
            opacity: 0;
            transform: scale(0.5);
            animation: logoPop 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) 0.3s forwards;
        }

        @keyframes logoPop {
            to { opacity: 1; transform: scale(1); }
        }

        .form-title { 
            font-size: 28px; 
            font-weight: 800; 
            color: #2D3748; 
            margin-bottom: 8px; 
            letter-spacing: -0.5px;
            opacity: 0;
            transform: translateY(10px);
            animation: slideUpFade 0.5s ease forwards 0.5s;
        }
        .form-subtitle { 
            font-size: 15px; 
            color: #718096; 
            opacity: 0;
            transform: translateY(10px);
            animation: slideUpFade 0.5s ease forwards 0.6s;
        }

        .form-group { 
            position: relative;
            margin-bottom: 30px; 
            opacity: 0;
            transform: translateY(10px);
            animation: slideUpFade 0.5s ease forwards;
        }
        
        .form-group:nth-child(1) { animation-delay: 0.7s; }
        .form-group:nth-child(2) { animation-delay: 0.8s; }
        .form-group:nth-child(3) { animation-delay: 0.9s; }

        /* Floating Label styling */
        .form-group input {
            width: 100%; 
            padding: 16px 16px 16px 45px; 
            background: rgba(255, 255, 255, 0.7);
            border: 2px solid transparent;
            border-radius: 12px; 
            font-family: 'Outfit', sans-serif; 
            font-size: 15px;
            color: #2D3748; 
            outline: none; 
            transition: all 0.3s ease;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);
        }

        .form-group .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #A0AEC0;
            transition: color 0.3s ease;
            pointer-events: none;
        }

        .form-group input:focus { 
            background: #ffffff;
            border-color: #644B39; 
            box-shadow: 0 0 0 4px rgba(100, 75, 57, 0.1);
        }

        .form-group input:focus + .input-icon {
            color: #644B39;
        }

        .btn-login {
            width: 100%; 
            background: linear-gradient(135deg, #644B39, #4a3527);
            color: white; 
            border: none;
            padding: 16px; 
            border-radius: 12px; 
            font-family: 'Outfit', sans-serif;
            font-size: 16px; 
            font-weight: 600; 
            cursor: pointer; 
            transition: all 0.3s cubic-bezier(0.2, 0.8, 0.2, 1); 
            box-shadow: 0 4px 15px rgba(100, 75, 57, 0.3);
            position: relative;
            overflow: hidden;
            
            opacity: 0;
            transform: translateY(10px);
            animation: slideUpFade 0.5s ease forwards 1s;
        }

        .btn-login:hover { 
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(100, 75, 57, 0.4);
        }
        .btn-login:active {
            transform: translateY(1px);
        }

        /* Button Ripple Effect */
        .btn-login::after {
            content: '';
            position: absolute;
            top: 50%; left: 50%;
            width: 5px; height: 5px;
            background: rgba(255, 255, 255, 0.5);
            opacity: 0;
            border-radius: 100%;
            transform: scale(1, 1) translate(-50%);
            transform-origin: 50% 50%;
        }
        @keyframes ripple {
            0% { transform: scale(0, 0); opacity: 0.5; }
            100% { transform: scale(100, 100); opacity: 0; }
        }
        .btn-login:focus:not(:active)::after {
            animation: ripple 1s ease-out;
        }

        .error { 
            color: #E53E3E; 
            background: #FFF5F5; 
            border-left: 4px solid #E53E3E;
            padding: 12px 16px; 
            border-radius: 8px; 
            font-size: 14px; 
            margin-bottom: 24px; 
            display: flex; 
            align-items: center; 
            gap: 10px; 
            font-weight: 500;
            animation: shake 0.5s cubic-bezier(0.36, 0.07, 0.19, 0.97) both;
        }

        @keyframes slideUpFade {
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes shake {
            10%, 90% { transform: translate3d(-1px, 0, 0); }
            20%, 80% { transform: translate3d(2px, 0, 0); }
            30%, 50%, 70% { transform: translate3d(-4px, 0, 0); }
            40%, 60% { transform: translate3d(4px, 0, 0); }
        }

        /* Loading Screen adjustments */
        .auth-loader-screen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background-color: rgba(255,255,255,0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 999999;
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition: opacity 0.35s ease, visibility 0.35s ease;
        }
        .auth-loader-screen.active { opacity: 1; visibility: visible; pointer-events: all; }
        .auth-loader-box { position: relative; width: 105px; height: 105px; display: flex; align-items: center; justify-content: center; }
        .auth-loader-track { position: absolute; width: 100%; height: 100%; border-radius: 50%; border: 2px solid rgba(100, 75, 57, 0.15); }
        .auth-loader-spinner { position: absolute; width: 100%; height: 100%; border-radius: 50%; border: 2px solid transparent; border-top-color: #644B39; border-right-color: rgba(100, 75, 57, 0.4); animation: authSpinnerRotate 1.15s linear infinite; }
        @keyframes authSpinnerRotate { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .auth-loader-center { position: relative; width: 60px; height: 60px; }
        .auth-loader-logo-img { width: 60px; height: 60px; border-radius: 50%; }
        /* ── Inactivity Timeout Banner ───────────────────────────── */
        .timeout-banner {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            background: rgba(100, 75, 57, 0.12);
            border: 1px solid rgba(132, 86, 60, 0.35);
            border-radius: 12px;
            padding: 12px 14px;
            margin-bottom: 14px;
            font-size: 13px;
            color: #D4B896;
            line-height: 1.45;
            animation: fadeSlideIn 0.35s ease;
        }
        .timeout-banner svg { flex-shrink: 0; margin-top: 1px; color: #A87B5A; }
        .timeout-banner span { flex: 1; }
        .timeout-banner-close {
            flex-shrink: 0;
            background: none;
            border: none;
            color: rgba(212, 184, 150, 0.7);
            cursor: pointer;
            font-size: 18px;
            line-height: 1;
            padding: 0 2px;
            transition: color 0.2s;
        }
        .timeout-banner-close:hover { color: #D4B896; }
        @keyframes fadeSlideIn {
            from { opacity: 0; transform: translateY(-6px); }
            to   { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <!-- Fullscreen Loading Overlay -->
    <div id="authLoader" class="auth-loader-screen" aria-hidden="true">
        <div class="auth-loader-box">
            <div class="auth-loader-track"></div>
            <div class="auth-loader-spinner"></div>
            <div class="auth-loader-center">
                <img src="assets/images/sf_logo.jpg" alt="SF Logo" class="auth-loader-logo-img">
            </div>
        </div>
    </div>

    <!-- Animated Background Shapes -->
    <div class="bg-shape shape-1"></div>
    <div class="bg-shape shape-2"></div>

    <div class="login-wrapper">
        <div class="login-header">
            <span style="display: inline-flex; align-items: center; gap: 6px; background: rgba(100, 75, 57, 0.1); color: #644B39; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21V5a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v16"></path><path d="M17 21v-8a2 2 0 0 0-2-2H9a2 2 0 0 0-2 2v8"></path></svg>
                Staff & Reception Portal
            </span>
            <br>
            <img src="assets/images/sf_logo.jpg" alt="Santa Fe Logo" class="brand-logo-img">
            <h1 class="form-title">Staff Sign In</h1>
            <p class="form-subtitle">Santa Fe Beach Club Reception & Staff</p>
        </div>

        <?php if (isset($_GET['timeout']) && $_GET['timeout'] == '1'): ?>
        <div class="timeout-banner" id="timeoutBanner" role="alert" aria-live="polite">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <span>Your session expired due to inactivity. Please sign in again to continue.</span>
            <button type="button" class="timeout-banner-close" onclick="this.parentElement.remove()" aria-label="Dismiss">&times;</button>
        </div>
        <?php endif; ?>

        <div id="errorContainer">
            <?php if ($error): ?>
                <div class="error">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>
        </div>
        
        <form id="loginForm" method="POST" action="staff_login" autocomplete="on">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="ajax" value="1">
            
            <div class="form-group">
                <input type="email" id="username" name="username" required autofocus autocomplete="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" placeholder="Staff / Reception Email Address">
                <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
            </div>
            
            <div class="form-group">
                <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="Password">
                <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
            </div>
            
            <div class="form-group">
                <button type="submit" id="submitBtn" class="btn-login">Sign In to Staff Portal</button>
            </div>
        </form>

        <div style="text-align: center; margin-top: 22px; font-size: 13px; color: #718096;">
            Management / Administrator? <a href="admin_login" style="color: #644B39; font-weight: 600; text-decoration: none;">Executive Login →</a>
        </div>
    </div>

    <script>
        const loginForm = document.getElementById('loginForm');
        const authLoader = document.getElementById('authLoader');
        const errorContainer = document.getElementById('errorContainer');
        const submitBtn = document.getElementById('submitBtn');

        function showError(msg) {
            errorContainer.innerHTML = `
                <div class="error">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    <span>${msg}</span>
                </div>
            `;
        }

        loginForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            errorContainer.innerHTML = '';

            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;

            if (!username || !password) {
                showError('Please enter both username and password.');
                return;
            }

            // Immediately show smooth loading screen
            authLoader.classList.add('active');
            authLoader.setAttribute('aria-hidden', 'false');
            submitBtn.disabled = true;

            const formData = new FormData(loginForm);
            const startTime = Date.now();

            try {
                const response = await fetch('staff_login', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const data = await response.json();

                // Ensure the loading animation plays gracefully for at least 1.2 seconds
                const elapsed = Date.now() - startTime;
                const minDisplayTime = 1200;
                const remainingTime = Math.max(0, minDisplayTime - elapsed);

                if (data && data.success) {
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, remainingTime);
                } else {
                    setTimeout(() => {
                        authLoader.classList.remove('active');
                        authLoader.setAttribute('aria-hidden', 'true');
                        submitBtn.disabled = false;
                        showError(data.message || 'Invalid username or password.');
                    }, remainingTime);
                }
            } catch (err) {
                setTimeout(() => {
                    authLoader.classList.remove('active');
                    authLoader.setAttribute('aria-hidden', 'true');
                    submitBtn.disabled = false;
                    showError('An unexpected network error occurred. Please try again.');
                }, 500);
            }
        });
    </script>
</body>
</html>
