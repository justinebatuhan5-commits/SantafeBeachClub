<?php
require_once __DIR__ . '/../backend/helpers/session_init.php';
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/helpers/security_headers.php';
require_once __DIR__ . '/../backend/helpers/csrf_helper.php';
require_once __DIR__ . '/../backend/helpers/rate_limiter.php';
require_once __DIR__ . '/../backend/helpers/security_logger.php';
require_once __DIR__ . '/../backend/helpers/otp_helper.php';

// If already fully logged in, redirect
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    $dest = ($_SESSION['admin_role'] ?? 'receptionist') === 'admin' ? 'admin_dashboard' : 'dashboard';
    header("Location: $dest");
    exit;
}

// Must have pending MFA session
$loginSource = $_SESSION['login_source'] ?? (($_SESSION['mfa_pending_admin_role'] ?? '') === 'admin' ? 'admin' : 'staff');
$loginPage   = ($loginSource === 'admin') ? 'admin_login' : 'staff_login';

if (!isset($_SESSION['mfa_pending_admin_id'])) {
    header("Location: $loginPage");
    exit;
}

$adminId   = (int)$_SESSION['mfa_pending_admin_id'];
$username  = $_SESSION['mfa_pending_admin_username'] ?? 'Admin';
$role      = $_SESSION['mfa_pending_admin_role'] ?? 'receptionist';
$userType  = $_SESSION['mfa_pending_admin_usertype'] ?? ($role === 'admin' ? 'admin' : 'receptionist');
$userTable = ($userType === 'receptionist') ? 'receptionists' : 'administrators';
$emailHint = $_SESSION['mfa_pending_email_hint'] ?? 'your registered email';

$error = '';
$is_ajax = (isset($_POST['ajax']) && $_POST['ajax'] === '1') || 
           (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
           (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

// Handle Resend Request
if (isset($_POST['action']) && $_POST['action'] === 'resend') {
    if (!verify_csrf_token()) {
        $res = ['success' => false, 'message' => 'Security token invalid. Please refresh.'];
    } else {
        $lastSent = $_SESSION['otp_sent_at'] ?? 0;
        $elapsed = time() - $lastSent;
        if ($elapsed < OTP_RESEND_COOLDOWN) {
            $wait = OTP_RESEND_COOLDOWN - $elapsed;
            $res = ['success' => false, 'message' => "Please wait {$wait} seconds before requesting a new code."];
        } else {
            // Find destination email from correct table
            $stmt = $conn->prepare("SELECT email FROM `{$userTable}` WHERE id = ?");
            $stmt->bind_param('i', $adminId);
            $stmt->execute();
            $emailRes = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            $targetEmail = !empty($emailRes['email']) ? $emailRes['email'] : $username;
            
            $newOtp = otp_generate();
            otp_store_for_admin($adminId, $newOtp, $conn, $userType);
            $sendRes = otp_send_email($targetEmail, $newOtp, $username);
            
            $_SESSION['otp_sent_at'] = time();
            SecurityLogger::log($conn, 'MFA_OTP_RESEND', "User requested OTP resend ({$userType})", SecurityLogger::LEVEL_INFO, $username);
            
            if ($sendRes['success']) {
                $res = ['success' => true, 'message' => 'A new 6-digit code has been sent to your email.'];
            } else {
                $res = ['success' => false, 'message' => 'Failed to send email. Please try again later.'];
            }
        }
    }
    
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode($res);
        exit;
    }
}

// Handle OTP Verification Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['action']) || $_POST['action'] === 'verify')) {
    if (!verify_csrf_token()) {
        $error = 'Security validation failed (Invalid or expired CSRF token).';
        SecurityLogger::log($conn, 'CSRF_MISMATCH', 'CSRF verification failed on OTP verify', SecurityLogger::LEVEL_WARNING);
    } else {
        $otp = trim($_POST['otp'] ?? '');
        
        if (empty($otp)) {
            $error = 'Please enter the 6-digit verification code.';
        } elseif (!preg_match('/^[0-9]{6}$/', $otp)) {
            $error = 'Verification code must be exactly 6 numeric digits.';
        } else {
            $verify = otp_verify_admin($adminId, $otp, $conn, $userType);
            
            if ($verify['success']) {
                // Complete login
                session_regenerate_id(true);
                
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username']  = $username;
                $_SESSION['admin_role']      = $role;
                
                // Clear MFA pending state
                unset($_SESSION['mfa_pending_admin_id']);
                unset($_SESSION['mfa_pending_admin_username']);
                unset($_SESSION['mfa_pending_admin_role']);
                unset($_SESSION['mfa_pending_email_hint']);
                unset($_SESSION['otp_sent_at']);
                
                log_activity($conn, $username, 'Login MFA', 'Completed 2FA verification successfully');
                SecurityLogger::log($conn, 'LOGIN_SUCCESS_MFA', "Admin completed MFA and logged in ({$role})", SecurityLogger::LEVEL_INFO, $username);
                
                $dest = $role === 'admin' ? 'admin_dashboard' : 'dashboard';
                
                if ($is_ajax) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success'  => true,
                        'redirect' => $dest
                    ]);
                    exit;
                }
                
                header("Location: $dest");
                exit;
            } else {
                if ($verify['reason'] === 'locked_out') {
                    // Invalidate MFA state and force relogin
                    unset($_SESSION['mfa_pending_admin_id']);
                    SecurityLogger::log($conn, 'MFA_LOCKOUT', "MFA locked out due to max failed attempts for user: {$username}", SecurityLogger::LEVEL_WARNING, $username);
                    $error = 'Maximum verification attempts exceeded. Please sign in again.';
                    if ($is_ajax) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'message' => $error, 'redirect' => $loginPage]);
                        exit;
                    }
                    header("Location: $loginPage");
                    exit;
                } elseif ($verify['reason'] === 'expired_or_not_found') {
                    $error = 'Verification code expired or not found. Please click "Resend Code".';
                } else {
                    $rem = $verify['remaining'] ?? 0;
                    $error = "Invalid code. {$rem} attempt(s) remaining.";
                    SecurityLogger::log($conn, 'MFA_FAILED_ATTEMPT', "Failed MFA OTP attempt for user: {$username}", SecurityLogger::LEVEL_WARNING, $username);
                }
            }
        }
    }
    
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $error]);
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
    <title>Two-Factor Authentication - Santa Fe Beach Club</title>
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

        .otp-wrapper { 
            background: rgba(255, 255, 255, 0.88);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            border-radius: 24px; 
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.15); 
            max-width: 460px; 
            width: 90%; 
            position: relative; 
            z-index: 1; 
            padding: 45px 35px;
            text-align: center;
            opacity: 0;
            transform: translateY(30px) scale(0.95);
            animation: cardEnter 0.8s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
        }

        @keyframes cardEnter {
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .icon-badge {
            width: 70px;
            height: 70px;
            background: rgba(100, 75, 57, 0.1);
            color: #644B39;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }

        .form-title { 
            font-size: 24px; 
            font-weight: 800; 
            color: #2D3748; 
            margin-bottom: 8px; 
        }
        .form-subtitle { 
            font-size: 14px; 
            color: #718096; 
            margin-bottom: 25px;
            line-height: 1.5;
        }
        .email-highlight {
            font-weight: 600;
            color: #4A5568;
        }

        /* 6 Digit Input Group */
        .otp-inputs {
            display: flex;
            gap: 8px;
            justify-content: center;
            margin-bottom: 25px;
        }
        .otp-digit {
            width: 50px;
            height: 58px;
            font-size: 24px;
            font-weight: 700;
            text-align: center;
            border: 2px solid #E2E8F0;
            border-radius: 12px;
            background: #fff;
            color: #2D3748;
            outline: none;
            transition: all 0.2s ease;
        }
        .otp-digit:focus {
            border-color: #644B39;
            box-shadow: 0 0 0 4px rgba(100, 75, 57, 0.15);
        }

        .btn-verify {
            width: 100%; 
            background: linear-gradient(135deg, #644B39, #4a3527);
            color: white; 
            border: none;
            padding: 15px; 
            border-radius: 12px; 
            font-family: 'Outfit', sans-serif;
            font-size: 16px; 
            font-weight: 600; 
            cursor: pointer; 
            transition: all 0.3s ease; 
            box-shadow: 0 4px 15px rgba(100, 75, 57, 0.3);
            margin-bottom: 15px;
        }
        .btn-verify:hover { 
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(100, 75, 57, 0.4);
        }

        .resend-section {
            font-size: 14px;
            color: #718096;
            margin-top: 15px;
        }
        .btn-link {
            background: none;
            border: none;
            color: #644B39;
            font-weight: 600;
            cursor: pointer;
            text-decoration: underline;
            padding: 0;
            font-family: 'Outfit', sans-serif;
            font-size: 14px;
        }
        .btn-link:disabled {
            color: #A0AEC0;
            cursor: not-allowed;
            text-decoration: none;
        }

        .back-link {
            display: inline-block;
            margin-top: 20px;
            font-size: 13px;
            color: #718096;
            text-decoration: none;
        }
        .back-link:hover {
            color: #2D3748;
        }

        .error { 
            color: #E53E3E; 
            background: #FFF5F5; 
            border-left: 4px solid #E53E3E;
            padding: 12px; 
            border-radius: 8px; 
            font-size: 14px; 
            margin-bottom: 20px; 
            text-align: left;
        }
        .success-box {
            color: #276749;
            background: #F0FFF4;
            border-left: 4px solid #38A169;
            padding: 12px;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 20px;
            text-align: left;
        }

        /* Loading Screen */
        .auth-loader-screen {
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            background-color: rgba(255,255,255,0.85); display: flex; align-items: center; justify-content: center;
            z-index: 999999; opacity: 0; visibility: hidden; pointer-events: none;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }
        .auth-loader-screen.active { opacity: 1; visibility: visible; pointer-events: all; }
        .auth-loader-box { position: relative; width: 80px; height: 80px; }
        .auth-loader-spinner { position: absolute; width: 100%; height: 100%; border-radius: 50%; border: 3px solid transparent; border-top-color: #644B39; animation: spin 1s linear infinite; }
        @keyframes spin { 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>
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

            <button type="submit" id="verifyBtn" class="btn-verify">Verify & Sign In</button>
        </form>

        <div class="resend-section">
            Didn't receive the code? 
            <button type="button" id="resendBtn" class="btn-link">Resend Code</button>
            <span id="countdownText" style="display:none;">(in <span id="timer">60</span>s)</span>
        </div>

        <a href="logout" class="back-link">← Cancel and return to <?php echo ($loginSource === 'admin') ? 'Executive Login' : 'Front Desk Login'; ?></a>
    </div>

    <script>
        const digits = document.querySelectorAll('.otp-digit');
        const fullOtp = document.getElementById('fullOtp');
        const otpForm = document.getElementById('otpForm');
        const msgContainer = document.getElementById('msgContainer');
        const authLoader = document.getElementById('authLoader');
        const verifyBtn = document.getElementById('verifyBtn');
        const resendBtn = document.getElementById('resendBtn');
        const countdownText = document.getElementById('countdownText');
        const timerSpan = document.getElementById('timer');

        // Auto tab across inputs and handle paste
        digits.forEach((input, index) => {
            input.addEventListener('input', (e) => {
                const val = e.target.value;
                if (val.length === 1 && index < digits.length - 1) {
                    digits[index + 1].focus();
                }
                updateFullOtp();
            });

            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !input.value && index > 0) {
                    digits[index - 1].focus();
                }
            });

            input.addEventListener('paste', (e) => {
                e.preventDefault();
                const pasteData = e.clipboardData.getData('text').trim();
                if (/^[0-9]{6}$/.test(pasteData)) {
                    pasteData.split('').forEach((char, idx) => {
                        if (digits[idx]) digits[idx].value = char;
                    });
                    updateFullOtp();
                    digits[digits.length - 1].focus();
                }
            });
        });

        function updateFullOtp() {
            let combined = '';
            digits.forEach(d => combined += d.value);
            fullOtp.value = combined;
        }

        function showMessage(msg, isSuccess = false) {
            msgContainer.innerHTML = `<div class="${isSuccess ? 'success-box' : 'error'}">${msg}</div>`;
        }

        // Handle OTP verification submit
        otpForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            updateFullOtp();

            if (fullOtp.value.length !== 6) {
                showMessage('Please enter all 6 digits.');
                return;
            }

            authLoader.classList.add('active');
            verifyBtn.disabled = true;

            try {
                const formData = new FormData(otpForm);
                const response = await fetch('verify_otp', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                const data = await response.json();

                if (data.success) {
                    window.location.href = data.redirect;
                } else {
                    authLoader.classList.remove('active');
                    verifyBtn.disabled = false;
                    showMessage(data.message || 'Verification failed.');
                    if (data.redirect) {
                        setTimeout(() => window.location.href = data.redirect, 2000);
                    }
                }
            } catch (err) {
                authLoader.classList.remove('active');
                verifyBtn.disabled = false;
                showMessage('Network error occurred. Please try again.');
            }
        });

        // Handle Resend
        let cooldown = 0;
        function startCooldown(sec) {
            cooldown = sec;
            resendBtn.disabled = true;
            countdownText.style.display = 'inline';
            timerSpan.textContent = cooldown;

            const interval = setInterval(() => {
                cooldown--;
                timerSpan.textContent = cooldown;
                if (cooldown <= 0) {
                    clearInterval(interval);
                    resendBtn.disabled = false;
                    countdownText.style.display = 'none';
                }
            }, 1000);
        }

        resendBtn.addEventListener('click', async () => {
            if (cooldown > 0) return;

            const formData = new FormData();
            formData.append('action', 'resend');
            formData.append('ajax', '1');
            const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            formData.append('csrf_token', token);

            try {
                const response = await fetch('verify_otp', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await response.json();
                if (data.success) {
                    showMessage(data.message, true);
                    startCooldown(60);
                } else {
                    showMessage(data.message);
                }
            } catch (e) {
                showMessage('Failed to request new code. Try again.');
            }
        });
    </script>
</body>
</html>
