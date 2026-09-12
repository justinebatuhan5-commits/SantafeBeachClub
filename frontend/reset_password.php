<?php
/**
 * reset_password.php
 * Secure password reset completion page for Santa Fe Beach Club.
 * Validates token, enforces OWASP password policies, and updates password.
 */

require_once __DIR__ . '/../backend/helpers/session_init.php';

require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/helpers/error_handler.php';
require_once __DIR__ . '/../backend/helpers/csrf_helper.php';
require_once __DIR__ . '/../backend/helpers/password_helper.php';
require_once __DIR__ . '/../backend/helpers/password_reset_helper.php';

// ── Token-to-Session: absorb GET token into session, then redirect to clean URL ──
// This prevents the token from sitting in browser history, server access logs,
// and leaking via HTTP Referrer headers.
if (isset($_GET['token']) && $_GET['token'] !== '') {
    $_SESSION['pwd_reset_token'] = trim($_GET['token']);
    header('Location: reset_password');
    exit;
}

// Read token from session (after redirect) or POST fallback
$rawToken = trim($_SESSION['pwd_reset_token'] ?? $_POST['token'] ?? '');
$verification = pwd_reset_verify_token($rawToken, $conn);


$error = '';
$success = '';
$is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || isset($_POST['ajax']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $error = 'Invalid security token. Please refresh the page.';
    } elseif (!$verification['valid']) {
        $error = $verification['error'];
    } else {
        $newPassword     = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        $result = pwd_reset_complete($rawToken, $newPassword, $confirmPassword, $conn);
        if ($result['success']) {
            $success = $result['message'];
            // Clear token from session after successful reset (prevent replay)
            unset($_SESSION['pwd_reset_token']);
        } else {
            $error = $result['message'];
        }
    }

    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => empty($error),
            'message' => $error ?: $success,
            'error'   => $error,
            'role'    => $verification['role'] ?? 'admin'
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
    <title>Set New Password - Santa Fe Beach Club</title>
    <meta name="csrf-token" content="<?php echo htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="assets/js/security.js" defer></script>
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
            max-width: 480px;
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
            margin-bottom: 26px;
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
            margin-bottom: 18px;
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
            padding: 14px 44px 14px 44px;
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

        .toggle-pw-btn {
            position: absolute;
            right: 14px;
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 4px;
            display: flex;
            align-items: center;
        }

        .toggle-pw-btn:hover {
            color: #fff;
        }

        /* Password criteria hints */
        .pwd-rules {
            background: rgba(30, 41, 59, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 22px;
            font-size: 12px;
            color: var(--text-muted);
        }

        .pwd-rules ul {
            list-style: none;
            margin-top: 6px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px;
        }

        .pwd-rule-item {
            display: flex;
            align-items: center;
            gap: 6px;
            transition: color 0.2s;
        }

        .pwd-rule-item.valid {
            color: #10B981;
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

        .login-btn {
            display: inline-block;
            margin-top: 14px;
            padding: 12px 28px;
            background: #10B981;
            color: #fff;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            transition: background 0.2s;
        }

        .login-btn:hover {
            background: #059669;
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

        <?php if (!$verification['valid']): ?>
            <div class="card-header">
                <h2 class="card-title">Invalid Link</h2>
                <p class="card-desc">The password reset link you clicked is invalid or has expired.</p>
            </div>
            <div class="alert-box alert-error">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <span><?php echo htmlspecialchars($verification['error']); ?></span>
            </div>
            <a href="forgot_password" class="btn-submit" style="text-decoration: none;">
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
                    <div class="alert-box alert-success" style="flex-direction: column; align-items: center; text-align: center;">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        <span style="margin-top: 8px;"><?php echo htmlspecialchars($success); ?></span>
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
                        <button type="button" class="toggle-pw-btn" onclick="toggleVisibility('password', this)" aria-label="Toggle password">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>

                <div class="input-block">
                    <label class="input-label" for="confirm_password">Confirm New Password</label>
                    <div class="input-box">
                        <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" data-label="Confirm Password" placeholder="Confirm your password">
                        <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <button type="button" class="toggle-pw-btn" onclick="toggleVisibility('confirm_password', this)" aria-label="Toggle password">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>

                <div class="pwd-rules">
                    <strong>Password Security Requirements:</strong>
                    <ul>
                        <li id="rule-len" class="pwd-rule-item"><span>•</span> At least 8 characters</li>
                        <li id="rule-upper" class="pwd-rule-item"><span>•</span> Uppercase letter</li>
                        <li id="rule-lower" class="pwd-rule-item"><span>•</span> Lowercase letter</li>
                        <li id="rule-num" class="pwd-rule-item"><span>•</span> Number & symbol</li>
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

    <script>
        function toggleVisibility(inputId, btn) {
            const input = document.getElementById(inputId);
            if (!input) return;
            const isText = input.type === 'text';
            input.type = isText ? 'password' : 'text';
            btn.style.color = isText ? 'var(--text-muted)' : 'var(--gold-primary)';
        }

        // Live password criteria validation
        const pwdInput = document.getElementById('password');
        if (pwdInput) {
            pwdInput.addEventListener('input', function() {
                const val = this.value;
                document.getElementById('rule-len').classList.toggle('valid', val.length >= 8);
                document.getElementById('rule-upper').classList.toggle('valid', /[A-Z]/.test(val));
                document.getElementById('rule-lower').classList.toggle('valid', /[a-z]/.test(val));
                document.getElementById('rule-num').classList.toggle('valid', /[0-9]/.test(val) && /[\W_]/.test(val));
            });
        }

        const form = document.getElementById('resetForm');
        const submitBtn = document.getElementById('submitBtn');
        const statusContainer = document.getElementById('statusContainer');

        if (form) {
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                const pwd = document.getElementById('password').value;
                const confirmPwd = document.getElementById('confirm_password').value;

                if (!pwd || !confirmPwd) {
                    showError('Please fill in both password fields.');
                    return;
                }

                if (pwd !== confirmPwd) {
                    showError('Passwords do not match.');
                    return;
                }

                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span>Updating password...</span>';

                const formData = new FormData(form);

                try {
                    const res = await fetch(form.action, {
                        method: 'POST',
                        body: formData,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });

                    const data = await res.json();
                    const feedbackMsg = data.message || data.error || 'Password update failed.';
                    if (data.success) {
                        form.style.display = 'none';
                        const portalUrl = (data.role === 'admin') ? 'admin_login' : 'staff_login';
                        statusContainer.innerHTML = `
                            <div class="alert-box alert-success" style="flex-direction: column; align-items: center; text-align: center;">
                                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                <span style="margin-top: 10px; font-size: 15px;">${feedbackMsg}</span>
                                <a href="${portalUrl}" class="login-btn">
                                    Sign In Now &rarr;
                                </a>
                            </div>
                        `;
                    } else {
                        showError(feedbackMsg);
                    }
                } catch (err) {
                    showError('An unexpected network error occurred. Please try again.');
                } finally {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<span>Save New Password</span><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>';
                }
            });
        }

        function showError(msg) {
            statusContainer.innerHTML = `
                <div class="alert-box alert-error">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <span>${msg}</span>
                </div>
            `;
        }
    </script>
</body>
</html>
