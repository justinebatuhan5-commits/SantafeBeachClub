<?php
/**
 * forgot_password.php
 * Secure password reset request page for Santa Fe Beach Club.
 * Works for both Administrator and Reception Staff accounts.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/helpers/error_handler.php';
require_once __DIR__ . '/../backend/helpers/csrf_helper.php';
require_once __DIR__ . '/../backend/helpers/password_reset_helper.php';
require_once __DIR__ . '/../backend/helpers/recaptcha_helper.php';

$portal = isset($_GET['portal']) && $_GET['portal'] === 'admin' ? 'admin' : 'staff';
$error = '';
$success = '';
$is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || isset($_POST['ajax']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $error = 'Invalid security token. Please refresh the page.';
    } else {
        // Verify reCAPTCHA v3
        $recaptchaToken = $_POST['g-recaptcha-response'] ?? '';
        $recaptchaResult = recaptcha_verify($recaptchaToken, 'forgot_password');

        if (!$recaptchaResult['success']) {
            $error = !empty($recaptchaResult['error']) 
                ? 'Security check failed: ' . $recaptchaResult['error'] 
                : 'Automated bot activity detected. Please try again.';
        } else {
            $email = trim($_POST['email'] ?? '');

            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email address.';
            } else {
                // Find account by email or username
                $stmt = $conn->prepare("SELECT id, username, email, role FROM admins WHERE email = ? OR username = ? LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param('ss', $email, $email);
                    $stmt->execute();
                    $user = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    if ($user) {
                        // Deliver reset link to user's registered email
                        $recipientEmail = !empty($user['email']) && filter_var($user['email'], FILTER_VALIDATE_EMAIL)
                            ? $user['email']
                            : (filter_var($user['username'], FILTER_VALIDATE_EMAIL) ? $user['username'] : '');

                        if ($recipientEmail) {
                            $rawToken = pwd_reset_create_token((int)$user['id'], $conn, $_SERVER['REMOTE_ADDR'] ?? '');

                            // Build absolute reset URL
                            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 80) == 443 ? 'https://' : 'http://';
                            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                            $pathDir = rtrim(dirname($_SERVER['REQUEST_URI'] ?? '/frontend/'), '/\\');
                            $resetUrl = $protocol . $host . $pathDir . '/reset_password?token=' . urlencode($rawToken);

                            $displayName = !empty($user['username']) ? explode('@', $user['username'])[0] : 'Staff Member';
                            pwd_reset_send_email($recipientEmail, ucfirst($displayName), $resetUrl);
                        }
                    }
                }

                // OWASP: Always display identical success message to prevent user enumeration
                $success = 'If an account exists with that email, we have sent password reset instructions to your inbox.';
            }
        }
    }

    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => empty($error),
            'message' => $error ?: $success
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
    <title>Forgot Password - Santa Fe Beach Club</title>
    <meta name="csrf-token" content="<?php echo htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="assets/js/security.js" defer></script>
    <script src="https://www.google.com/recaptcha/api.js?render=6LdQlbMtAAAAATXKv68Zmk3Bk5x16n2Fd3jT-is" async defer></script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --bg-night: #0B0F17;
            --panel-bg: rgba(15, 23, 42, 0.72);
            --gold-primary: #f59e0b;
            --gold-gradient: linear-gradient(135deg, #d97706, #f59e0b, #b45309);
            --text-primary: #F8FAFC;
            --text-muted: #94A3B8;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg-night);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            position: relative;
            overflow-x: hidden;
        }

        .bg-backdrop {
            position: fixed;
            inset: 0;
            background: linear-gradient(135deg, rgba(11, 15, 23, 0.94), rgba(20, 27, 45, 0.96)),
                        url('assets/beach_day.jpg') center/cover no-repeat;
            z-index: 0;
        }

        .card {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 460px;
            background: rgba(15, 23, 42, 0.85);
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 40px 36px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6);
        }

        .logo-wrap {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
        }

        .logo-img {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            object-fit: cover;
            border: 1px solid rgba(255, 255, 255, 0.15);
        }

        .logo-text h1 {
            font-family: 'Cinzel', serif;
            font-size: 16px;
            font-weight: 700;
            letter-spacing: 1px;
            color: #fff;
        }

        .logo-text p {
            font-size: 11px;
            color: var(--gold-primary);
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }

        .card-header {
            margin-bottom: 28px;
        }

        .card-title {
            font-family: 'Cinzel', serif;
            font-size: 24px;
            font-weight: 600;
            color: #fff;
            margin-bottom: 8px;
        }

        .card-desc {
            font-size: 14px;
            color: var(--text-muted);
            line-height: 1.5;
        }

        .input-block {
            margin-bottom: 22px;
        }

        .input-label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: #E2E8F0;
            margin-bottom: 8px;
        }

        .input-box {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-box input {
            width: 100%;
            padding: 14px 16px 14px 44px;
            background: rgba(30, 41, 59, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 12px;
            color: #fff;
            font-family: 'Outfit', sans-serif;
            font-size: 15px;
            transition: all 0.25s;
            outline: none;
        }

        .input-box input:focus {
            border-color: var(--gold-primary);
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.25);
            background: rgba(30, 41, 59, 0.95);
        }

        .input-icon {
            position: absolute;
            left: 15px;
            color: var(--text-muted);
            pointer-events: none;
        }

        .btn-submit {
            width: 100%;
            padding: 15px;
            background: var(--gold-gradient);
            border: none;
            border-radius: 12px;
            color: #fff;
            font-family: 'Outfit', sans-serif;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 4px 18px rgba(217, 119, 6, 0.35);
            transition: all 0.25s;
        }

        .btn-submit:hover {
            filter: brightness(1.1);
            transform: translateY(-1px);
            box-shadow: 0 6px 24px rgba(217, 119, 6, 0.45);
        }

        .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        /* Field Validation Errors */
        .security-field-error {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 7px;
            font-size: 12px;
            font-weight: 500;
            color: #F87171;
            line-height: 1.4;
            animation: errorSlideIn 0.22s ease;
        }
        .security-field-error .error-icon {
            flex-shrink: 0;
            color: #EF4444;
        }
        .input-box input.is-invalid {
            border-color: rgba(239, 68, 68, 0.7) !important;
            background: rgba(239, 68, 68, 0.06) !important;
            box-shadow: 0 0 0 2.5px rgba(239, 68, 68, 0.2) !important;
        }
        @keyframes errorSlideIn {
            from { opacity: 0; transform: translateY(-3px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .alert-box {
            padding: 14px 16px;
            border-radius: 10px;
            font-size: 13.5px;
            line-height: 1.5;
            margin-bottom: 22px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #6ee7b7;
        }

        .back-link {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin-top: 24px;
            font-size: 13.5px;
            color: var(--text-muted);
            text-decoration: none;
            transition: color 0.2s;
        }

        .back-link:hover {
            color: #fff;
        }
    </style>
</head>
<body>
    <div class="bg-backdrop"></div>

    <div class="card">
        <div class="logo-wrap">
            <img src="assets/logo.jpg" alt="Logo" class="logo-img">
            <div class="logo-text">
                <h1>SANTA FE BEACH CLUB</h1>
                <p>Security & Access Recovery</p>
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

        <form id="forgotForm" method="POST" action="forgot_password<?php echo $portal === 'admin' ? '?portal=admin' : '?portal=staff'; ?>" novalidate>
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

    <script>
        const form = document.getElementById('forgotForm');
        const submitBtn = document.getElementById('submitBtn');
        const statusContainer = document.getElementById('statusContainer');

        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            const emailInput = document.getElementById('email');
            const email = emailInput.value.trim();

            if (!email) {
                statusContainer.innerHTML = `
                    <div class="alert-box alert-error">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <span>Please enter your account email address.</span>
                    </div>
                `;
                return;
            }

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span>Sending instructions...</span>';

            // Fetch reCAPTCHA token
            if (typeof grecaptcha !== 'undefined') {
                try {
                    const token = await grecaptcha.execute('6LdQlbMtAAAAATXKv68Zmk3Bk5x16n2Fd3jT-is', { action: 'forgot_password' });
                    document.getElementById('g-recaptcha-response').value = token;
                } catch (e) {
                    console.warn('reCAPTCHA token error:', e);
                }
            }

            const formData = new FormData(form);

            try {
                const res = await fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                const data = await res.json();
                const feedbackMsg = data.message || data.error || 'Request completed.';
                if (data.success) {
                    statusContainer.innerHTML = `
                        <div class="alert-box alert-success">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            <span>${feedbackMsg}</span>
                        </div>
                    `;
                    form.reset();
                } else {
                    statusContainer.innerHTML = `
                        <div class="alert-box alert-error">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            <span>${feedbackMsg}</span>
                        </div>
                    `;
                }
            } catch (err) {
                statusContainer.innerHTML = `
                    <div class="alert-box alert-error">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <span>An unexpected error occurred. Please try again later.</span>
                    </div>
                `;
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<span>Send Reset Link</span><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>';
            }
        });
    </script>
</body>
</html>
