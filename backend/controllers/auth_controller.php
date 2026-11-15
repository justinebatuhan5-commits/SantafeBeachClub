<?php
/**
 * Controller for authentication, OTP verification, password recovery, and step-up re-auth pages.
 */

function sfbc_is_ajax_request(): bool
{
    return (isset($_POST['ajax']) && $_POST['ajax'] === '1')
        || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
}

function sfbc_send_json_and_exit(array $payload): void
{
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

/**
 * Shared login controller for frontend/staff_login.php and frontend/admin_login.php.
 */
function sfbc_handle_portal_login_page(mysqli $conn, string $portal): array
{
    if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
        $dest = ($_SESSION['admin_role'] ?? 'receptionist') === 'admin' ? 'admin_dashboard' : 'dashboard';
        header("Location: $dest");
        exit;
    }

    $configs = [
        'staff' => [
            'table' => 'receptionists',
            'role' => 'receptionist',
            'userType' => 'receptionist',
            'loginSource' => 'reception',
            'recaptchaAction' => 'staff_login',
            'rateKey' => 'login_attempt',
            'redirectDest' => 'dashboard',
            'pageSlug' => 'staff_login',
            'checkPortalLock' => true,
            'lockLabel' => 'Receptionist logged in via 24h trusted device bypass',
            'trustedLogAction' => 'Login Trusted Device',
            'trustedLogEvent' => 'Receptionist logged in via trusted device (MFA skipped)',
            'mfaLogEvent' => 'OTP dispatched for Receptionist MFA (step 2)',
            'badPwEventPrefix' => 'Failed login attempt (bad password) for user: ',
            'unknownUserEventPrefix' => 'Failed login attempt (unknown username): ',
            'lockPermMsg' => 'Access Denied: This staff account has been administrative-locked / suspended by the Resort Administrator. Please contact management.',
            'lockTempPrefix' => 'Account Temporarily Locked: Exceeded maximum allowed login attempts. For security reasons, please wait ',
            'lockTempSuffix' => ' minute(s) before trying again.',
            'rateFailMsg' => 'Login rate limit exceeded',
            'recaptchaFailPrefix' => 'reCAPTCHA failed on staff login (score: ',
            'csrfFailMsg' => 'CSRF verification failed on login attempt',
        ],
        'admin' => [
            'table' => 'administrators',
            'role' => 'admin',
            'userType' => 'admin',
            'loginSource' => 'admin',
            'recaptchaAction' => 'admin_login',
            'rateKey' => 'login_attempt_admin',
            'redirectDest' => 'admin_dashboard',
            'pageSlug' => 'admin_login',
            'checkPortalLock' => false,
            'lockLabel' => 'Logged in via 24h trusted device bypass',
            'trustedLogAction' => 'Login Trusted Device',
            'trustedLogEvent' => 'Admin logged in via trusted device (MFA skipped)',
            'mfaLogEvent' => 'OTP dispatched for administrator MFA',
            'badPwEventPrefix' => 'Failed admin login (bad password) for user: ',
            'unknownUserEventPrefix' => 'Failed admin login (unknown username): ',
            'lockPermMsg' => 'Access denied: This administrator account has been locked or suspended. Please contact the system administrator.',
            'lockTempPrefix' => 'Account Temporarily Locked: Exceeded maximum allowed login attempts. Please wait ',
            'lockTempSuffix' => ' minute(s) before trying again.',
            'rateFailMsg' => 'Admin login rate limit exceeded',
            'recaptchaFailPrefix' => 'reCAPTCHA failed on admin login (score: ',
            'csrfFailMsg' => 'CSRF verification failed on admin login attempt',
        ],
    ];

    $cfg = $configs[$portal] ?? $configs['staff'];
    $error = '';
    $is_ajax = sfbc_is_ajax_request();

    $isStaffPortalLocked = false;
    $staffPortalLockMsg = '';

    if ($cfg['checkPortalLock']) {
        $portalLockQuery = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('staff_portal_locked', 'staff_portal_locked_msg')");
        $portalSettings = [];
        if ($portalLockQuery) {
            while ($row = $portalLockQuery->fetch_assoc()) {
                $portalSettings[$row['setting_key']] = $row['setting_value'];
            }
        }
        $isStaffPortalLocked = ($portalSettings['staff_portal_locked'] ?? '0') === '1';
        $staffPortalLockMsg = !empty($portalSettings['staff_portal_locked_msg'])
            ? $portalSettings['staff_portal_locked_msg']
            : 'Front Desk Reception Portal is currently locked for system maintenance. Please contact the Resort Administrator.';
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($cfg['checkPortalLock'] && $isStaffPortalLocked) {
            $error = $staffPortalLockMsg;
            if ($is_ajax) {
                sfbc_send_json_and_exit(['success' => false, 'message' => $error, 'portal_locked' => true]);
            }
        } else {
            $recaptchaToken = $_POST['g-recaptcha-response'] ?? '';
            $recaptchaResult = recaptcha_verify($recaptchaToken, $cfg['recaptchaAction']);
            if (!$recaptchaResult['success']) {
                $error = 'Security check failed. Please refresh and try again.';
                SecurityLogger::log($conn, 'RECAPTCHA_FAIL', $cfg['recaptchaFailPrefix'] . $recaptchaResult['score'] . ')', SecurityLogger::LEVEL_WARNING);
                if ($is_ajax) {
                    sfbc_send_json_and_exit(['success' => false, 'message' => $error]);
                }
            } else {
                $rateStatus = RateLimiter::check($conn, $cfg['rateKey'], 5, 900);
                if (!$rateStatus['allowed']) {
                    $error = "Too many failed login attempts. Please wait {$rateStatus['retry_after']} seconds before trying again.";
                    SecurityLogger::log($conn, 'RATE_LIMIT_TRIGGER', $cfg['rateFailMsg'], SecurityLogger::LEVEL_WARNING);
                    if ($is_ajax) {
                        sfbc_send_json_and_exit(['success' => false, 'message' => $error]);
                    }
                } elseif (!verify_csrf_token()) {
                    $error = 'Security validation failed (Invalid or expired CSRF token). Please refresh and try again.';
                    SecurityLogger::log($conn, 'CSRF_MISMATCH', $cfg['csrfFailMsg'], SecurityLogger::LEVEL_WARNING);
                    if ($is_ajax) {
                        sfbc_send_json_and_exit(['success' => false, 'message' => $error]);
                    }
                } else {
                    $username = Validator::sanitize($_POST['username'] ?? '');
                    $password = $_POST['password'] ?? '';

                    if (!empty($username) && !empty($password)) {
                        $table = $cfg['table'];
                        $stmt = $conn->prepare("SELECT id, password, email FROM `{$table}` WHERE username = ?");
                        $stmt->bind_param("s", $username);
                        $stmt->execute();
                        $result = $stmt->get_result();

                        if ($row = $result->fetch_assoc()) {
                            $lockStatus = RateLimiter::checkAccountLockout($conn, (int)$row['id'], $table);
                            if ($lockStatus['locked']) {
                                if (!empty($lockStatus['is_permanent'])) {
                                    $error = $cfg['lockPermMsg'];
                                } else {
                                    $mins = (int)ceil($lockStatus['seconds_remaining'] / 60);
                                    $error = $cfg['lockTempPrefix'] . $mins . $cfg['lockTempSuffix'];
                                }
                                SecurityLogger::log($conn, 'ACCOUNT_LOCKED', "Locked account login attempt for user: {$username}", SecurityLogger::LEVEL_WARNING, $username);
                                if ($is_ajax) {
                                    sfbc_send_json_and_exit(['success' => false, 'message' => $error]);
                                }
                            } elseif (pw_verify($password, $row['password'])) {
                                if (pw_needs_rehash($row['password'])) {
                                    $newHash = pw_hash($password);
                                    $upd = $conn->prepare("UPDATE `{$table}` SET password = ? WHERE id = ?");
                                    $upd->bind_param("si", $newHash, $row['id']);
                                    $upd->execute();
                                    $upd->close();
                                }

                                if (is_device_trusted((int)$row['id'], $cfg['userType'], $conn)) {
                                    session_regenerate_id(true);
                                    $_SESSION['admin_logged_in']    = true;
                                    $_SESSION['admin_username']     = $username;
                                    $_SESSION['admin_role']         = $cfg['role'];
                                    $_SESSION['session_start_time'] = time();
                                    $_SESSION['last_activity']      = time();
                                    admin_session_register($conn, (int)$row['id'], $cfg['userType'], $username);

                                    remember_trusted_device((int)$row['id'], $cfg['userType'], $conn);

                                    RateLimiter::clearFailedLogins($conn, (int)$row['id'], $table);
                                    log_activity($conn, $username, $cfg['trustedLogAction'], $cfg['lockLabel']);
                                    SecurityLogger::log($conn, 'LOGIN_SUCCESS_TRUSTED_DEVICE', $cfg['trustedLogEvent'], SecurityLogger::LEVEL_INFO, $username);

                                    if ($is_ajax) {
                                        sfbc_send_json_and_exit([
                                            'success' => true,
                                            'redirect' => $cfg['redirectDest']
                                        ]);
                                    }

                                    header('Location: ' . $cfg['redirectDest']);
                                    exit;
                                }

                                require_once dirname(__DIR__) . '/helpers/otp_helper.php';
                                require_once dirname(__DIR__) . '/services/mailer.php';

                                $adminEmail = $row['email'] ?? null;
                                if (empty($adminEmail)) {
                                    $adminEmail = $username;
                                }

                                $rawOtp = otp_generate();
                                otp_store_for_admin((int)$row['id'], $rawOtp, $conn, $cfg['userType']);
                                $sendResult = otp_send_email($adminEmail, $rawOtp, $username);

                                session_regenerate_id(true);

                                $_SESSION['mfa_pending_admin_id']       = (int)$row['id'];
                                $_SESSION['mfa_pending_admin_username'] = $username;
                                $_SESSION['mfa_pending_admin_role']     = $cfg['role'];
                                $_SESSION['mfa_pending_admin_usertype'] = $cfg['userType'];
                                $_SESSION['mfa_pending_email_hint']     = substr($adminEmail, 0, 3) . '***' . strstr($adminEmail, '@');
                                $_SESSION['otp_sent_at']                = time();
                                $_SESSION['login_source']               = $cfg['loginSource'];

                                RateLimiter::clearFailedLogins($conn, (int)$row['id'], $table);
                                SecurityLogger::log($conn, 'MFA_OTP_SENT', $cfg['mfaLogEvent'], SecurityLogger::LEVEL_INFO, $username);

                                if ($is_ajax) {
                                    sfbc_send_json_and_exit([
                                        'success' => true,
                                        'mfa' => true,
                                        'redirect' => 'verify_otp'
                                    ]);
                                }

                                header("Location: verify_otp");
                                exit;
                            } else {
                                RateLimiter::hit($conn, $cfg['rateKey']);
                                RateLimiter::recordFailedLogin($conn, (int)$row['id'], 5, 15, $table);
                                $error = 'Invalid username or password.';
                                SecurityLogger::log($conn, 'FAILED_LOGIN', $cfg['badPwEventPrefix'] . $username, SecurityLogger::LEVEL_WARNING, $username);
                            }
                        } else {
                            RateLimiter::hit($conn, $cfg['rateKey']);
                            $error = 'Invalid username or password.';
                            SecurityLogger::log($conn, 'FAILED_LOGIN', $cfg['unknownUserEventPrefix'] . $username, SecurityLogger::LEVEL_WARNING, $username);
                        }
                        $stmt->close();
                    } else {
                        $error = 'Please enter both username and password.';
                    }
                }
            }
        }

        if ($is_ajax) {
            sfbc_send_json_and_exit([
                'success' => false,
                'message' => $error
            ]);
        }
    }

    return compact('error', 'isStaffPortalLocked', 'staffPortalLockMsg');
}

function sfbc_handle_verify_otp_page(mysqli $conn): array
{
    if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
        $dest = ($_SESSION['admin_role'] ?? 'receptionist') === 'admin' ? 'admin_dashboard' : 'dashboard';
        header("Location: $dest");
        exit;
    }

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
    $is_ajax = sfbc_is_ajax_request();

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
            sfbc_send_json_and_exit($res);
        }
    }

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
                    session_regenerate_id(true);

                    $_SESSION['admin_logged_in']    = true;
                    $_SESSION['admin_username']     = $username;
                    $_SESSION['admin_role']         = $role;
                    $_SESSION['session_start_time'] = time();
                    $_SESSION['last_activity']      = time();
                    admin_session_register($conn, $adminId, $userType, $username);

                    remember_trusted_device($adminId, $userType, $conn);

                    unset($_SESSION['mfa_pending_admin_id']);
                    unset($_SESSION['mfa_pending_admin_username']);
                    unset($_SESSION['mfa_pending_admin_role']);
                    unset($_SESSION['mfa_pending_email_hint']);
                    unset($_SESSION['otp_sent_at']);

                    log_activity($conn, $username, 'Login MFA', 'Completed 2FA verification successfully');
                    SecurityLogger::log($conn, 'LOGIN_SUCCESS_MFA', "Admin completed MFA and logged in ({$role})", SecurityLogger::LEVEL_INFO, $username);

                    $dest = $role === 'admin' ? 'admin_dashboard' : 'dashboard';

                    if ($is_ajax) {
                        sfbc_send_json_and_exit([
                            'success' => true,
                            'redirect' => $dest
                        ]);
                    }

                    header("Location: $dest");
                    exit;
                } else {
                    if ($verify['reason'] === 'locked_out') {
                        unset($_SESSION['mfa_pending_admin_id']);
                        SecurityLogger::log($conn, 'MFA_LOCKOUT', "MFA locked out due to max failed attempts for user: {$username}", SecurityLogger::LEVEL_WARNING, $username);
                        $error = 'Maximum verification attempts exceeded. Please sign in again.';
                        if ($is_ajax) {
                            sfbc_send_json_and_exit(['success' => false, 'message' => $error, 'redirect' => $loginPage]);
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
            sfbc_send_json_and_exit(['success' => false, 'message' => $error]);
        }
    }

    return compact('loginSource', 'loginPage', 'emailHint', 'error');
}

function sfbc_handle_forgot_password_page(mysqli $conn): array
{
    if (empty($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true
        || empty($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'admin') {
        http_response_code(404);
        header('Location: admin_login');
        exit;
    }

    $portal = isset($_GET['portal']) && $_GET['portal'] === 'admin' ? 'admin' : 'staff';
    $error = '';
    $success = '';
    $is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || isset($_POST['ajax']);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verify_csrf_token()) {
            $error = 'Invalid security token. Please refresh the page.';
        } else {
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
                    $user = null;
                    $foundUserType = null;

                    $lookups = [
                        'admin' => 'administrators',
                        'receptionist' => 'receptionists',
                    ];

                    if ($portal === 'admin') {
                        $lookups = ['admin' => 'administrators'];
                    }

                    foreach ($lookups as $uType => $tableName) {
                        $stmt = $conn->prepare("SELECT id, username, email FROM `{$tableName}` WHERE email = ? OR username = ? LIMIT 1");
                        if ($stmt) {
                            $stmt->bind_param('ss', $email, $email);
                            $stmt->execute();
                            $row = $stmt->get_result()->fetch_assoc();
                            $stmt->close();
                            if ($row) {
                                $user = $row;
                                $foundUserType = $uType;
                                break;
                            }
                        }
                    }

                    if ($user) {
                        $recipientEmail = !empty($user['email']) && filter_var($user['email'], FILTER_VALIDATE_EMAIL)
                            ? $user['email']
                            : (filter_var($user['username'], FILTER_VALIDATE_EMAIL) ? $user['username'] : '');

                        if ($recipientEmail) {
                            $rawToken = pwd_reset_create_token((int)$user['id'], $conn, $_SERVER['REMOTE_ADDR'] ?? '', $foundUserType);

                            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 80) == 443 ? 'https://' : 'http://';
                            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                            $pathDir = rtrim(dirname($_SERVER['REQUEST_URI'] ?? '/frontend/'), '/\\');
                            $resetUrl = $protocol . $host . $pathDir . '/reset_password?token=' . urlencode($rawToken);

                            $displayName = !empty($user['username']) ? explode('@', $user['username'])[0] : 'Staff Member';
                            pwd_reset_send_email($recipientEmail, ucfirst($displayName), $resetUrl);
                        }
                    }

                    $success = 'If an account exists with that email, we have sent password reset instructions to your inbox.';
                }
            }
        }

        if ($is_ajax) {
            sfbc_send_json_and_exit([
                'success' => empty($error),
                'message' => $error ?: $success
            ]);
        }
    }

    return compact('portal', 'error', 'success');
}

function sfbc_handle_reset_password_page(mysqli $conn): array
{
    if (isset($_GET['token']) && $_GET['token'] !== '') {
        $_SESSION['pwd_reset_token'] = trim($_GET['token']);
        header('Location: reset_password');
        exit;
    }

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
            $newPassword = $_POST['password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            $result = pwd_reset_complete($rawToken, $newPassword, $confirmPassword, $conn);
            if ($result['success']) {
                $success = $result['message'];
                unset($_SESSION['pwd_reset_token']);
            } else {
                $error = $result['message'];
            }
        }

        if ($is_ajax) {
            sfbc_send_json_and_exit([
                'success' => empty($error),
                'message' => $error ?: $success,
                'error'   => $error,
                'role'    => $verification['role'] ?? 'admin'
            ]);
        }
    }

    return compact('rawToken', 'verification', 'error', 'success');
}

function sfbc_handle_admin_reauth_page(mysqli $conn): array
{
    $allowedReturns = ['admin_refunds', 'settings', 'admin_sessions'];
    $return = (string)($_GET['return'] ?? $_POST['return'] ?? 'admin_refunds');
    if (!in_array($return, $allowedReturns, true)) {
        $return = 'admin_refunds';
    }
    $error = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf_token();
        $password = (string)($_POST['password'] ?? '');
        $username = (string)($_SESSION['admin_username'] ?? '');
        $stmt = $conn->prepare('SELECT password FROM administrators WHERE username=? LIMIT 1');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row && pw_verify($password, (string)$row['password'])) {
            admin_session_revoke_current($conn);
            $_SESSION['password_confirmed_at'] = time();
            session_regenerate_id(true);
            admin_session_register($conn, (int)$_SESSION['admin_account_id'], 'admin', $username);
            SecurityLogger::log($conn, 'STEP_UP_AUTH_SUCCESS', 'Password confirmed for a sensitive administrator action.', SecurityLogger::LEVEL_INFO, $username);
            header('Location: ' . $return . (str_starts_with($return, 'settings') ? '?tab=property' : ''));
            exit;
        }
        $error = 'Password is incorrect.';
        SecurityLogger::log($conn, 'STEP_UP_AUTH_FAILED', 'Incorrect password during step-up authentication.', SecurityLogger::LEVEL_WARNING, $username);
    }

    return compact('return', 'error');
}
