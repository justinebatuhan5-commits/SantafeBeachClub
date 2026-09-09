<?php
session_start();
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/helpers/security_headers.php';
require_once __DIR__ . '/../backend/helpers/csrf_helper.php';
require_once __DIR__ . '/../backend/helpers/rate_limiter.php';
require_once __DIR__ . '/../backend/helpers/security_logger.php';
require_once __DIR__ . '/../backend/helpers/validator_helper.php';
require_once __DIR__ . '/../backend/helpers/password_helper.php';
require_once __DIR__ . '/../backend/helpers/recaptcha_helper.php';

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
    // ── reCAPTCHA v3 Verification ────────────────────────────
    $recaptchaToken = $_POST['g-recaptcha-response'] ?? '';
    $recaptchaResult = recaptcha_verify($recaptchaToken, 'staff_login');
    if (!$recaptchaResult['success']) {
        $error = 'Security check failed. Please refresh and try again.';
        SecurityLogger::log($conn, 'RECAPTCHA_FAIL', 'reCAPTCHA failed on staff login (score: ' . $recaptchaResult['score'] . ')', SecurityLogger::LEVEL_WARNING);
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $error]);
            exit;
        }
    } else {
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
    } // end else (reCAPTCHA passed)
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
    <title>Staff & Reception Portal - Santa Fe Beach Club</title>
    <meta name="csrf-token" content="<?php echo htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="assets/js/security.js" defer></script>
    <script src="https://www.google.com/recaptcha/api.js?render=6LfE7bEtAAAAKWR7cu0DZaBeVem3ZluHOyJ7zWT" async defer></script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --brand-wood: #5C4033;
            --brand-wood-dark: #422D23;
            --brand-teal: #0D9488;
            --brand-sand: #FAF7F2;
            --text-dark: #1E293B;
            --text-muted: #64748B;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: #0D1B2A;
            color: var(--text-dark);
            min-height: 100vh;
            display: flex;
            overflow-x: hidden;
            position: relative;
        }

        /* ── Auto-Sliding Background Slideshow ─────────────── */
        .bg-slideshow {
            position: fixed;
            inset: 0;
            z-index: 0;
            overflow: hidden;
        }

        .bg-slide {
            position: absolute;
            inset: 0;
            background-size: cover;
            background-position: center;
            opacity: 0;
            transition: opacity 1.5s ease-in-out;
            animation: kenBurns 14s ease-in-out infinite alternate;
        }

        .bg-slide.active {
            opacity: 1;
        }

        .bg-slide:nth-child(1) { background-image: url('assets/images/staff_bg1.jpg'); animation-delay: 0s; }
        .bg-slide:nth-child(2) { background-image: url('assets/images/staff_bg2.jpg'); animation-delay: -5s; }
        .bg-slide:nth-child(3) { background-image: url('assets/images/staff_bg3.jpg'); animation-delay: -10s; }

        @keyframes kenBurns {
            from { transform: scale(1.0) translateX(0px); }
            to   { transform: scale(1.08) translateX(-12px); }
        }

        /* Slide dot indicators */
        .slide-indicators {
            position: fixed;
            bottom: 28px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 10px;
            z-index: 5;
        }

        .slide-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: rgba(255,255,255,0.4);
            border: 1px solid rgba(255,255,255,0.6);
            cursor: pointer;
            transition: all 0.4s ease;
        }

        .slide-dot.active {
            width: 24px;
            border-radius: 4px;
            background: #0D9488;
            border-color: #0D9488;
            box-shadow: 0 0 10px rgba(13,148,136,0.6);
        }

        /* Ambient overlay */
        .bg-overlay {
            position: fixed;
            inset: 0;
            background: radial-gradient(circle at 30% 50%, rgba(15, 23, 42, 0.25) 0%, rgba(15, 23, 42, 0.58) 100%);
            z-index: 1;
            pointer-events: none;
        }

        /* ── Split Layout Container ─────────────────────── */
        .portal-container {
            display: grid;
            grid-template-columns: 1.15fr 0.85fr;
            width: 100vw;
            min-height: 100vh;
            position: relative;
            z-index: 1;
        }

        /* ── Left Hero Side (Daylight Coastal Luxury) ───── */
        .hero-section {
            position: relative;
            background: transparent;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 56px 64px;
            overflow: hidden;
        }

        .hero-content {
            position: relative;
            z-index: 2;
        }

        .hero-brand {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .hero-logo {
            width: 52px;
            height: 52px;
            border-radius: 12px;
            object-fit: cover;
            border: 2px solid rgba(255, 255, 255, 0.6);
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }

        .hero-brand-name {
            font-family: 'Cinzel', serif;
            font-size: 19px;
            letter-spacing: 2px;
            font-weight: 600;
            color: #FFFFFF;
            text-transform: uppercase;
        }

        .hero-brand-sub {
            font-size: 11px;
            letter-spacing: 3px;
            color: #99F6E4;
            text-transform: uppercase;
            font-weight: 500;
        }

        .hero-centerpiece {
            position: relative;
            z-index: 2;
            margin: auto 0;
            max-width: 540px;
        }

        .hero-badge-tag {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.4);
            color: #FFFFFF;
            font-size: 12px;
            letter-spacing: 1px;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 20px;
            backdrop-filter: blur(8px);
        }

        .hero-title {
            font-family: 'Cinzel', serif;
            font-size: 44px;
            line-height: 1.15;
            font-weight: 700;
            color: #FFFFFF;
            margin-bottom: 16px;
            text-shadow: 0 2px 20px rgba(0,0,0,0.4);
        }

        .hero-title span {
            color: #FDE047;
        }

        .hero-subtitle {
            font-size: 16px;
            line-height: 1.6;
            color: #F1F5F9;
            margin-bottom: 28px;
            font-weight: 300;
            text-shadow: 0 1px 6px rgba(0,0,0,0.3);
        }

        /* Live Clock Banner */
        .hero-clock-box {
            display: inline-flex;
            align-items: center;
            gap: 20px;
            padding: 16px 24px;
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.35);
            border-radius: 16px;
            backdrop-filter: blur(16px);
            box-shadow: 0 8px 32px rgba(0,0,0,0.15);
        }

        .clock-time {
            font-family: 'Outfit', sans-serif;
            font-size: 28px;
            font-weight: 700;
            color: #FFFFFF;
            letter-spacing: -0.5px;
        }

        .clock-divider {
            width: 1px;
            height: 32px;
            background: rgba(255, 255, 255, 0.4);
        }

        .clock-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #CCFBF1;
            font-weight: 600;
        }

        .clock-date {
            font-size: 13px;
            color: #FFFFFF;
            margin-top: 2px;
            font-weight: 400;
        }

        .hero-footer {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 12px;
            color: #CBD5E1;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
            padding-top: 20px;
        }

        .terminal-pill {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #A7F3D0;
            font-weight: 600;
        }

        .pulse-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #34D399;
            box-shadow: 0 0 10px #34D399;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.3); opacity: 0.6; }
        }

        /* ── Right Form Panel (Frosted Glass) ───────────── */
        .form-section {
            background: transparent;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 48px;
            position: relative;
        }

        .form-card {
            width: 100%;
            max-width: 440px;
            background: rgba(255, 255, 255, 0.85);
            border: 1px solid rgba(255, 255, 255, 0.6);
            border-radius: 28px;
            padding: 44px 38px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            position: relative;
            animation: formIn 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        @keyframes formIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .card-header {
            margin-bottom: 32px;
            text-align: left;
        }

        .card-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(100, 75, 57, 0.1);
            color: var(--brand-wood);
            border: 1px solid rgba(100, 75, 57, 0.25);
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 14px;
        }

        .card-title {
            font-family: 'Cinzel', serif;
            font-size: 27px;
            font-weight: 700;
            color: var(--brand-wood-dark);
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }

        .card-desc {
            font-size: 13.5px;
            color: var(--text-muted);
            line-height: 1.5;
        }

        /* Floating Input Groups */
        .input-block {
            margin-bottom: 22px;
            position: relative;
        }

        .input-label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            color: #475569;
            margin-bottom: 8px;
        }

        .input-box {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 16px;
            color: #94A3B8;
            pointer-events: none;
            transition: color 0.3s;
        }

        .input-box input {
            width: 100%;
            padding: 16px 48px 16px 46px;
            background: rgba(255, 255, 255, 0.75);
            border: 1.5px solid rgba(100, 75, 57, 0.2);
            border-radius: 14px;
            color: #1E293B;
            font-family: 'Outfit', sans-serif;
            font-size: 15px;
            outline: none;
            transition: all 0.3s ease;
        }

        .input-box input:focus {
            background: #FFFFFF;
            border-color: var(--brand-wood);
            box-shadow: 0 0 0 4px rgba(100, 75, 57, 0.15);
        }

        .input-box input:focus ~ .input-icon {
            color: var(--brand-wood);
        }

        .toggle-pw-btn {
            position: absolute;
            right: 14px;
            background: none;
            border: none;
            color: #94A3B8;
            cursor: pointer;
            padding: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.2s;
        }

        .toggle-pw-btn:hover {
            color: var(--brand-wood);
        }

        /* Caps Lock Warning */
        .caps-warning {
            display: none;
            align-items: center;
            gap: 6px;
            margin-top: 6px;
            font-size: 11.5px;
            color: #D97706;
            font-weight: 500;
        }

        .caps-warning.active {
            display: flex;
        }

        /* Submit Button */
        .btn-submit {
            width: 100%;
            padding: 16px 24px;
            background: linear-gradient(135deg, #5C4033, #3F2B22);
            border: none;
            border-radius: 14px;
            color: #FFFFFF;
            font-family: 'Outfit', sans-serif;
            font-size: 15.5px;
            font-weight: 600;
            letter-spacing: 0.5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 6px 20px rgba(92, 64, 51, 0.3);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            margin-top: 10px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(92, 64, 51, 0.45);
            background: linear-gradient(135deg, #4E362B, #33231B);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .switch-portal-wrap {
            margin-top: 26px;
            text-align: center;
            font-size: 13px;
            color: var(--text-muted);
            border-top: 1px solid #F1F5F9;
            padding-top: 20px;
        }

        .switch-portal-wrap a {
            color: var(--brand-wood);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s;
        }

        .switch-portal-wrap a:hover {
            color: #0F172A;
            text-decoration: underline;
        }

        /* ── Field Validation Errors ────────────────────── */
        .security-field-error {
            display: flex;
            align-items: center;
            gap: 5px;
            margin-top: 6px;
            font-size: 12px;
            font-weight: 500;
            color: #DC2626;
            line-height: 1.3;
            animation: errorSlideIn 0.2s ease;
        }

        .security-field-error .error-icon {
            display: inline-block;
            flex-shrink: 0;
            color: #EF4444;
        }

        .input-box input.is-invalid {
            border-color: #EF4444 !important;
            background: #FFF5F5 !important;
            box-shadow: 0 0 0 2.5px rgba(239, 68, 68, 0.15) !important;
        }

        @keyframes errorSlideIn {
            from { opacity: 0; transform: translateY(-3px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .error {
            background: #FEF2F2;
            border: 1px solid #FECACA;
            color: #DC2626;
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 13.5px;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            animation: shake 0.45s ease-in-out;
            font-weight: 500;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-5px); }
            40%, 80% { transform: translateX(5px); }
        }

        .timeout-banner {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            background: rgba(92, 64, 51, 0.08);
            border: 1px solid rgba(92, 64, 51, 0.2);
            border-radius: 12px;
            padding: 12px 14px;
            margin-bottom: 20px;
            font-size: 13px;
            color: var(--brand-wood-dark);
        }

        .timeout-banner svg { flex-shrink: 0; color: var(--brand-wood); margin-top: 2px; }
        .timeout-banner-close {
            background: none; border: none; color: var(--brand-wood); cursor: pointer; font-size: 18px; margin-left: auto;
        }

        /* ── Fullscreen Auth Loader ─────────────────────── */
        .auth-loader-screen {
            position: fixed;
            inset: 0;
            background: rgba(255, 255, 255, 0.88);
            backdrop-filter: blur(8px);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 20px;
            z-index: 999999;
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition: opacity 0.35s ease, visibility 0.35s ease;
        }

        .auth-loader-screen.active {
            opacity: 1;
            visibility: visible;
            pointer-events: all;
        }

        .loader-ring-box {
            position: relative;
            width: 100px;
            height: 100px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .loader-ring {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            border: 2.5px solid rgba(92, 64, 51, 0.15);
            border-top-color: var(--brand-wood);
            animation: spin 1s linear infinite;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        .loader-logo {
            width: 58px;
            height: 58px;
            border-radius: 50%;
            object-fit: cover;
        }

        .loader-caption {
            font-size: 14px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: var(--brand-wood-dark);
            font-weight: 600;
        }

        /* ── Responsive adjustments ─────────────────────── */
        @media (max-width: 1024px) {
            .portal-container {
                grid-template-columns: 1fr;
            }
            .hero-section {
                padding: 40px 32px;
                min-height: 380px;
            }
            .hero-title {
                font-size: 32px;
            }
            .form-section {
                padding: 36px 20px;
            }
        }

        @media (max-width: 640px) {
            .hero-section {
                display: none;
            }
            .form-card {
                padding: 32px 24px;
                border-radius: 20px;
            }
        }
    </style>
</head>
<body>
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

    <main class="portal-container">
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
                    Terminal Ready &bull; Front Desk Portal
                </div>
                <div>&copy; <?php echo date('Y'); ?> Santa Fe Beach Club</div>
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
                    <button type="button" class="timeout-banner-close" onclick="this.parentElement.remove()" aria-label="Dismiss">&times;</button>
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
                            <input type="email" id="username" name="username" autofocus autocomplete="username" data-label="Email" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" placeholder="reception@santabeachclub.com">
                            <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                        </div>
                    </div>

                    <div class="input-block">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <label class="input-label" for="password">Password</label>
                            <a href="forgot_password?portal=staff" style="font-size: 12px; color: #5C4033; text-decoration: none; font-weight: 500; transition: color 0.2s;" onmouseover="this.style.color='#3F2B22'; this.style.textDecoration='underline';" onmouseout="this.style.color='#5C4033'; this.style.textDecoration='none';">Forgot Password?</a>
                        </div>
                        <div class="input-box">
                            <input type="password" id="password" name="password" autocomplete="current-password" data-label="Password" placeholder="Enter your password">
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
                        <span>Sign In to Terminal</span>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                    </button>
                </form>

                <div class="switch-portal-wrap">
                    Management / Administrator? <a href="admin_login">Executive Login &rarr;</a>
                </div>
            </div>
        </section>
    </main>

    <script>
        // ── Auto-Sliding Background ──
        (function() {
            const slides = document.querySelectorAll('.bg-slide');
            const dots   = document.querySelectorAll('.slide-dot');
            let current = 0;
            const INTERVAL = 6000; // ms per slide

            function goToSlide(idx) {
                slides[current].classList.remove('active');
                dots[current].classList.remove('active');
                current = (idx + slides.length) % slides.length;
                slides[current].classList.add('active');
                dots[current].classList.add('active');
            }

            // Auto advance
            let timer = setInterval(() => goToSlide(current + 1), INTERVAL);

            // Dot click navigation
            dots.forEach(dot => {
                dot.addEventListener('click', () => {
                    clearInterval(timer);
                    goToSlide(parseInt(dot.dataset.slide));
                    timer = setInterval(() => goToSlide(current + 1), INTERVAL);
                });
            });
        })();
    </script>
    <script>
        // ── Realtime Bantayan Clock & Dynamic Greeting ──
        function updateClock() {
            const now = new Date();
            const timeFormatter = new Intl.DateTimeFormat('en-US', {
                timeZone: 'Asia/Manila',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            });
            const dateFormatter = new Intl.DateTimeFormat('en-US', {
                timeZone: 'Asia/Manila',
                weekday: 'short',
                month: 'short',
                day: 'numeric',
                year: 'numeric'
            });

            const clockEl = document.getElementById('liveClock');
            const dateEl = document.getElementById('liveDate');
            if (clockEl) clockEl.textContent = timeFormatter.format(now);
            if (dateEl) dateEl.textContent = dateFormatter.format(now);

            // Dynamic Greeting
            const manilaHour = parseInt(new Intl.DateTimeFormat('en-US', { timeZone: 'Asia/Manila', hour: 'numeric', hour12: false }).format(now), 10);
            const greetingEl = document.getElementById('greetingTitle');
            if (greetingEl) {
                if (manilaHour >= 5 && manilaHour < 12) {
                    greetingEl.innerHTML = 'Good <span>Morning</span>';
                } else if (manilaHour >= 12 && manilaHour < 18) {
                    greetingEl.innerHTML = 'Good <span>Afternoon</span>';
                } else {
                    greetingEl.innerHTML = 'Good <span>Evening</span>';
                }
            }
        }
        setInterval(updateClock, 1000);
        updateClock();

        // ── Password Visibility Toggle ──
        const passwordInput = document.getElementById('password');
        const togglePwBtn = document.getElementById('togglePwBtn');
        const eyeIcon = document.getElementById('eyeIcon');

        if (togglePwBtn && passwordInput) {
            togglePwBtn.addEventListener('click', function() {
                const isPassword = passwordInput.getAttribute('type') === 'password';
                passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
                if (isPassword) {
                    eyeIcon.innerHTML = `<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line>`;
                } else {
                    eyeIcon.innerHTML = `<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>`;
                }
            });
        }

        // ── Caps Lock Detector ──
        const capsWarning = document.getElementById('capsWarning');
        if (passwordInput && capsWarning) {
            passwordInput.addEventListener('keyup', function(e) {
                if (e.getModifierState && e.getModifierState('CapsLock')) {
                    capsWarning.classList.add('active');
                } else {
                    capsWarning.classList.remove('active');
                }
            });
        }

        // ── AJAX Login Submission ──
        const loginForm = document.getElementById('loginForm');
        const authLoader = document.getElementById('authLoader');
        const errorContainer = document.getElementById('errorContainer');
        const submitBtn = document.getElementById('submitBtn');

        function showError(msg) {
            errorContainer.innerHTML = `
                <div class="error">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
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

            // Get reCAPTCHA v3 token before submitting (with timeout guard)
            let recaptchaToken = '';
            try {
                recaptchaToken = await Promise.race([
                    grecaptcha.execute('6LfE7bEtAAAAKWR7cu0DZaBeVem3ZluHOyJ7zWT', { action: 'staff_login' }),
                    new Promise((_, reject) => setTimeout(() => reject(new Error('reCAPTCHA timeout')), 5000))
                ]);
                document.getElementById('g-recaptcha-response').value = recaptchaToken;
            } catch (err) {
                console.warn('reCAPTCHA failed to load, proceeding anyway.');
            }

            authLoader.classList.add('active');
            authLoader.setAttribute('aria-hidden', 'false');
            submitBtn.disabled = true;

            const formData = new FormData(loginForm);
            const startTime = Date.now();
            let redirectUrl = null;

            try {
                // Fetch with a 10-second timeout guard
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 10000);

                const response = await fetch('staff_login', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    signal: controller.signal
                });
                clearTimeout(timeoutId);

                const data = await response.json();
                const elapsed = Date.now() - startTime;
                const minDisplayTime = 1000;
                const remainingTime = Math.max(0, minDisplayTime - elapsed);

                if (data && data.success) {
                    redirectUrl = data.redirect;
                    setTimeout(() => { window.location.href = redirectUrl; }, remainingTime);
                    return; // keep loader visible during redirect
                } else {
                    await new Promise(resolve => setTimeout(resolve, remainingTime));
                    showError(data.message || 'Invalid username or password.');
                }
            } catch (err) {
                await new Promise(resolve => setTimeout(resolve, 400));
                if (err.name === 'AbortError') {
                    showError('Request timed out. Please check your connection and try again.');
                } else {
                    showError('An unexpected network error occurred. Please try again.');
                }
            } finally {
                // Always reset UI unless we are about to redirect
                if (!redirectUrl) {
                    authLoader.classList.remove('active');
                    authLoader.setAttribute('aria-hidden', 'true');
                    submitBtn.disabled = false;
                }
            }
        });
    </script>
</body>
</html>
