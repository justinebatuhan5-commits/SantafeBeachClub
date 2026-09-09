<?php
session_start();
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/helpers/security_headers.php';
require_once __DIR__ . '/../backend/helpers/csrf_helper.php';
require_once __DIR__ . '/../backend/helpers/rate_limiter.php';
require_once __DIR__ . '/../backend/helpers/security_logger.php';
require_once __DIR__ . '/../backend/helpers/validator_helper.php';
require_once __DIR__ . '/../backend/helpers/password_helper.php';

// Already logged in – if admin, redirect to admin_dashboard; otherwise dashboard
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
    $rateStatus = RateLimiter::check($conn, 'login_attempt_admin', 5, 900);
    if (!$rateStatus['allowed']) {
        $error = "Too many failed login attempts. Please wait {$rateStatus['retry_after']} seconds before trying again.";
        SecurityLogger::log($conn, 'RATE_LIMIT_TRIGGER', 'Admin login rate limit exceeded', SecurityLogger::LEVEL_WARNING);
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $error]);
            exit;
        }
    } elseif (!verify_csrf_token()) {
        $error = 'Security validation failed (Invalid or expired CSRF token). Please refresh and try again.';
        SecurityLogger::log($conn, 'CSRF_MISMATCH', 'CSRF verification failed on admin login attempt', SecurityLogger::LEVEL_WARNING);
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $error]);
            exit;
        }
    } else {
        $username = Validator::sanitize($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!empty($username) && !empty($password)) {
            $stmt = $conn->prepare("SELECT id, password, role, email FROM admins WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                if (pw_verify($password, $row['password'])) {
                    // Check if role is admin
                    if ($row['role'] !== 'admin') {
                        RateLimiter::hit($conn, 'login_attempt_admin');
                        $error = 'Access restricted. This portal is for Administrators only. Receptionists must sign in via the Front Desk portal.';
                        SecurityLogger::log($conn, 'UNAUTHORIZED_PORTAL_ATTEMPT', "Non-admin user ({$username}) attempted admin login", SecurityLogger::LEVEL_WARNING, $username);
                    } else {
                        // Transparently upgrade hash if cost/algo has changed
                        if (pw_needs_rehash($row['password'])) {
                            $newHash = pw_hash($password);
                            $upd = $conn->prepare("UPDATE admins SET password = ? WHERE id = ?");
                            $upd->bind_param("si", $newHash, $row['id']);
                            $upd->execute();
                            $upd->close();
                        }

                        // --- MFA: Password verified. Now require OTP. ---
                        require_once __DIR__ . '/../backend/helpers/otp_helper.php';
                        require_once __DIR__ . '/../backend/services/mailer.php';

                        $adminEmail = $row['email'] ?? null;
                        if (empty($adminEmail)) {
                            $adminEmail = $username;
                        }

                        $rawOtp = otp_generate();
                        otp_store_for_admin((int)$row['id'], $rawOtp, $conn);
                        $sendResult = otp_send_email($adminEmail, $rawOtp, $username);

                        // Prevent session fixation
                        session_regenerate_id(true);

                        // Partial session identifying MFA status and login portal
                        $_SESSION['mfa_pending_admin_id']       = (int)$row['id'];
                        $_SESSION['mfa_pending_admin_username'] = $username;
                        $_SESSION['mfa_pending_admin_role']     = 'admin';
                        $_SESSION['mfa_pending_email_hint']     = substr($adminEmail, 0, 3) . '***' . strstr($adminEmail, '@');
                        $_SESSION['otp_sent_at']                = time();
                        $_SESSION['login_source']               = 'admin';

                        SecurityLogger::log($conn, 'MFA_OTP_SENT', "OTP dispatched for Executive Admin MFA", SecurityLogger::LEVEL_INFO, $username);

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
                    RateLimiter::hit($conn, 'login_attempt_admin');
                    $error = 'Invalid username or password.';
                    SecurityLogger::log($conn, 'FAILED_LOGIN', "Failed admin login (bad password) for user: {$username}", SecurityLogger::LEVEL_WARNING, $username);
                }
            } else {
                RateLimiter::hit($conn, 'login_attempt_admin');
                $error = 'Invalid username or password.';
                SecurityLogger::log($conn, 'FAILED_LOGIN', "Failed admin login (unknown username): {$username}", SecurityLogger::LEVEL_WARNING, $username);
            }
            $stmt->close();
        } else {
            $error = 'Please enter both username and password.';
        }
    }

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
    <title>Executive Admin Portal - Santa Fe Beach Club</title>
    <meta name="csrf-token" content="<?php echo htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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
            background: linear-gradient(135deg, #0f172a, #1e293b, #1e1b4b);
            background-size: 400% 400%;
            animation: gradientBG 15s ease infinite;
            overflow: hidden;
            color: #f8fafc;
        }

        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .bg-shape {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            z-index: 0;
            animation: floatShape 20s infinite alternate;
        }
        .shape-1 {
            width: 420px; height: 420px;
            background: rgba(217, 119, 6, 0.15);
            top: -100px; left: -100px;
        }
        .shape-2 {
            width: 500px; height: 500px;
            background: rgba(99, 102, 241, 0.18);
            bottom: -150px; right: -100px;
            animation-delay: -5s;
        }

        @keyframes floatShape {
            0% { transform: translate(0, 0) rotate(0deg); }
            100% { transform: translate(80px, 40px) rotate(20deg); }
        }

        .login-wrapper { 
            background: rgba(30, 41, 59, 0.75);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px; 
            box-shadow: 0 25px 60px -15px rgba(0,0,0,0.6); 
            max-width: 480px; 
            width: 90%; 
            position: relative; 
            z-index: 1; 
            padding: 50px 40px;
            opacity: 0;
            transform: translateY(30px) scale(0.95);
            animation: cardEnter 0.8s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
        }

        @keyframes cardEnter {
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .login-header {
            text-align: center;
            margin-bottom: 35px;
        }

        .portal-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(217, 119, 6, 0.15);
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.3);
            padding: 5px 14px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-bottom: 18px;
        }

        .brand-logo-img { 
            width: 86px; 
            height: 86px;
            border-radius: 20px; 
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4); 
            margin-bottom: 16px;
            object-fit: cover;
            border: 2px solid rgba(245, 158, 11, 0.3);
            opacity: 0;
            transform: scale(0.5);
            animation: logoPop 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) 0.3s forwards;
        }

        @keyframes logoPop {
            to { opacity: 1; transform: scale(1); }
        }

        .form-title { 
            font-size: 26px; 
            font-weight: 800; 
            color: #ffffff; 
            margin-bottom: 6px; 
            letter-spacing: -0.5px;
            opacity: 0;
            transform: translateY(10px);
            animation: slideUpFade 0.5s ease forwards 0.5s;
        }
        .form-subtitle { 
            font-size: 14px; 
            color: #94a3b8; 
            opacity: 0;
            transform: translateY(10px);
            animation: slideUpFade 0.5s ease forwards 0.6s;
        }

        .form-group { 
            position: relative;
            margin-bottom: 24px; 
            opacity: 0;
            transform: translateY(10px);
            animation: slideUpFade 0.5s ease forwards;
        }
        
        .form-group:nth-child(1) { animation-delay: 0.7s; }
        .form-group:nth-child(2) { animation-delay: 0.8s; }
        .form-group:nth-child(3) { animation-delay: 0.9s; }

        .form-group input {
            width: 100%; 
            padding: 16px 16px 16px 45px; 
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 12px; 
            font-family: 'Outfit', sans-serif; 
            font-size: 15px;
            color: #f8fafc; 
            outline: none; 
            transition: all 0.3s ease;
        }

        .form-group .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            transition: color 0.3s ease;
            pointer-events: none;
        }

        .form-group input:focus { 
            background: rgba(15, 23, 42, 0.85);
            border-color: #f59e0b; 
            box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.15);
        }

        .form-group input:focus + .input-icon {
            color: #f59e0b;
        }

        .btn-login {
            width: 100%; 
            background: linear-gradient(135deg, #d97706, #b45309);
            color: white; 
            border: none;
            padding: 16px; 
            border-radius: 12px; 
            font-family: 'Outfit', sans-serif;
            font-size: 16px; 
            font-weight: 600; 
            cursor: pointer; 
            transition: all 0.3s cubic-bezier(0.2, 0.8, 0.2, 1); 
            box-shadow: 0 4px 20px rgba(217, 119, 6, 0.35);
        }

        .btn-login:hover { 
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(217, 119, 6, 0.5);
            background: linear-gradient(135deg, #b45309, #92400e);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .switch-portal {
            text-align: center;
            margin-top: 25px;
            font-size: 13px;
            color: #94a3b8;
        }

        .switch-portal a {
            color: #38bdf8;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }

        .switch-portal a:hover {
            color: #7dd3fc;
            text-decoration: underline;
        }

        .error { 
            background: rgba(239, 68, 68, 0.15); 
            color: #fca5a5; 
            padding: 14px 16px; 
            border-radius: 12px; 
            margin-bottom: 24px; 
            font-size: 14px; 
            border: 1px solid rgba(239, 68, 68, 0.3);
            display: flex;
            align-items: center;
            gap: 12px;
            animation: shake 0.5s ease-in-out;
        }

        @keyframes slideUpFade {
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes shake {
            0%, 100% { transform: translate3d(0, 0, 0); }
            20%, 80% { transform: translate3d(-4px, 0, 0); }
            40%, 60% { transform: translate3d(4px, 0, 0); }
        }

        .auth-loader-screen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background-color: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(8px);
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
        .auth-loader-track { position: absolute; width: 100%; height: 100%; border-radius: 50%; border: 2px solid rgba(245, 158, 11, 0.15); }
        .auth-loader-spinner { position: absolute; width: 100%; height: 100%; border-radius: 50%; border: 2px solid transparent; border-top-color: #f59e0b; border-right-color: rgba(245, 158, 11, 0.4); animation: authSpinnerRotate 1.15s linear infinite; }
        @keyframes authSpinnerRotate { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .auth-loader-center { position: relative; width: 60px; height: 60px; }
        .auth-loader-logo-img { width: 60px; height: 60px; border-radius: 50%; }
        /* ── Inactivity Timeout Banner ───────────────────────────── */
        .timeout-banner {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            background: rgba(245, 158, 11, 0.12);
            border: 1px solid rgba(245, 158, 11, 0.35);
            border-radius: 12px;
            padding: 12px 14px;
            margin-bottom: 14px;
            font-size: 13px;
            color: #FCD34D;
            line-height: 1.45;
            animation: fadeSlideIn 0.35s ease;
        }
        .timeout-banner svg { flex-shrink: 0; margin-top: 1px; color: #F59E0B; }
        .timeout-banner span { flex: 1; }
        .timeout-banner-close {
            flex-shrink: 0;
            background: none;
            border: none;
            color: rgba(252, 211, 77, 0.7);
            cursor: pointer;
            font-size: 18px;
            line-height: 1;
            padding: 0 2px;
            transition: color 0.2s;
        }
        .timeout-banner-close:hover { color: #FCD34D; }
        @keyframes fadeSlideIn {
            from { opacity: 0; transform: translateY(-6px); }
            to   { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div id="authLoader" class="auth-loader-screen" aria-hidden="true">
        <div class="auth-loader-box">
            <div class="auth-loader-track"></div>
            <div class="auth-loader-spinner"></div>
            <div class="auth-loader-center">
                <img src="assets/images/sf_logo.jpg" alt="SF Logo" class="auth-loader-logo-img">
            </div>
        </div>
    </div>

    <div class="bg-shape shape-1"></div>
    <div class="bg-shape shape-2"></div>

    <div class="login-wrapper">
        <div class="login-header">
            <span class="portal-badge">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                Management Portal
            </span>
            <br>
            <img src="assets/images/sf_logo.jpg" alt="Santa Fe Logo" class="brand-logo-img">
            <h1 class="form-title">Executive Command</h1>
            <p class="form-subtitle">Santa Fe Beach Club Administration</p>
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
        
        <form id="loginForm" method="POST" action="admin_login" autocomplete="on">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="ajax" value="1">
            
            <div class="form-group">
                <input type="email" id="username" name="username" required autofocus autocomplete="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" placeholder="Admin Email Address">
                <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
            </div>
            
            <div class="form-group">
                <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="Admin Password">
                <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
            </div>
            
            <div class="form-group">
                <button type="submit" id="submitBtn" class="btn-login">Sign In to Admin Console</button>
            </div>
        </form>

        <div class="switch-portal">
            Looking for Front Desk / Staff? <a href="staff_login">Go to Staff Login →</a>
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

            authLoader.classList.add('active');
            authLoader.setAttribute('aria-hidden', 'false');
            submitBtn.disabled = true;

            const formData = new FormData(loginForm);
            const startTime = Date.now();

            try {
                const response = await fetch('admin_login', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const data = await response.json();
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
