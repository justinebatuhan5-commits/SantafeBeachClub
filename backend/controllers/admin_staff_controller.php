<?php
require_once __DIR__ . '/../helpers/rbac_helper.php';
require_once __DIR__ . '/../helpers/security_logger.php';
require_once __DIR__ . '/../helpers/password_helper.php';
require_once __DIR__ . '/../helpers/cloudinary_helper.php';
require_once __DIR__ . '/../helpers/file_scanner.php';

/**
 * Validates, scans, and stores an uploaded staff profile photo.
 * Returns ['path' => ?string, 'error' => ?string].
 */
function sfbc_staff_upload_avatar(array $file, string $username, string $frontendDir): array
{
    $allowedExts  = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $fileExt      = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    $finfo        = finfo_open(FILEINFO_MIME_TYPE);
    $realMime     = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';
    if ($finfo) {
        finfo_close($finfo);
    }

    $scanResult = scan_uploaded_file($file['tmp_name'], $fileExt);
    if (!$scanResult['safe']) {
        return ['path' => null, 'error' => 'File rejected: ' . ($scanResult['reason'] ?? 'unsafe content detected')];
    }

    if (!in_array($fileExt, $allowedExts, true) || !in_array($realMime, $allowedMimes, true) || (int)$file['size'] > 5 * 1024 * 1024) {
        return ['path' => null, 'error' => 'Invalid image format or file too large. Allowed: JPG, PNG, WebP, GIF (max 5MB).'];
    }

    $cloudResult = cloudinary_upload($file['tmp_name'], 'sfbc_avatars');
    if ($cloudResult['success'] && !empty($cloudResult['url'])) {
        return ['path' => $cloudResult['url'], 'error' => null];
    }

    $uploadDir = $frontendDir . '/uploads/avatars/';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }
    $filename = 'avatar_' . md5($username . time() . uniqid()) . '.' . $fileExt;
    if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        return ['path' => 'uploads/avatars/' . $filename, 'error' => null];
    }

    return ['path' => null, 'error' => 'Failed to upload image file.'];
}

function sfbc_staff_remove_local_avatar(?string $photoPath, string $frontendDir): void
{
    if (!empty($photoPath) && !is_remote_image($photoPath)) {
        $fullPath = $frontendDir . '/' . ltrim($photoPath, '/');
        if (file_exists($fullPath)) {
            @unlink($fullPath);
        }
    }
}

function sfbc_staff_handle_post(mysqli $conn, string $admin, string $frontendDir): void
{
    require_csrf_token();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_staff') {
        $uname       = trim((string)($_POST['username'] ?? ''));
        $email       = trim((string)($_POST['email'] ?? ''));
        $pw          = (string)($_POST['password'] ?? '');
        $role        = in_array($_POST['role'] ?? '', ['admin', 'receptionist'], true) ? $_POST['role'] : 'receptionist';
        $targetTable = ($role === 'admin') ? 'administrators' : 'receptionists';
        $photoPath   = null;

        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $upload = sfbc_staff_upload_avatar($_FILES['profile_photo'], $uname, $frontendDir);
            if ($upload['error'] !== null) {
                $_SESSION['staff_error'] = $upload['error'];
            } else {
                $photoPath = $upload['path'];
            }
        }

        if (strlen($uname) < 3) {
            $_SESSION['staff_error'] = 'Username must be at least 3 characters.';
        } elseif (!str_ends_with($uname, '@santafebeachclub.com') && !str_ends_with($uname, '@beachclub.com')) {
            $_SESSION['staff_error'] = 'Username must end with @santafebeachclub.com.';
        } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['staff_error'] = 'Please provide a valid personal/MFA email address.';
        } elseif (($pwError = pw_validate($pw)) !== null) {
            $_SESSION['staff_error'] = $pwError;
        } else {
            $dupFound = false;
            foreach (['administrators', 'receptionists'] as $tbl) {
                $stmt = $conn->prepare("SELECT id FROM `{$tbl}` WHERE username = ?");
                $stmt->bind_param('s', $uname);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    $dupFound = true;
                }
                $stmt->close();
                if ($dupFound) {
                    break;
                }
            }

            if ($dupFound) {
                $_SESSION['staff_error'] = 'Username already exists.';
            } else {
                $hash = pw_hash($pw);
                $stmt = $conn->prepare("INSERT INTO `{$targetTable}` (username, email, password, profile_photo) VALUES (?,?,?,?)");
                $stmt->bind_param('ssss', $uname, $email, $hash, $photoPath);
                $stmt->execute();
                $stmt->close();
                log_activity($conn, $admin, 'Staff Created', "Added $role account: $uname with OTP email: $email");
                SecurityLogger::log($conn, 'STAFF_CREATED', "Added {$role} account: {$uname}", SecurityLogger::LEVEL_INFO, $admin);
                $_SESSION['staff_success'] = "Staff account \"$uname\" ($role) created.";
            }
        }
    }

    if ($action === 'update_staff_photo') {
        $targetId   = (int)($_POST['staff_id'] ?? 0);
        $staffUtype = in_array($_POST['staff_type'] ?? '', ['admin', 'receptionist'], true) ? $_POST['staff_type'] : 'receptionist';
        $staffTable = ($staffUtype === 'admin') ? 'administrators' : 'receptionists';

        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $oldStmt = $conn->prepare("SELECT profile_photo, username FROM `{$staffTable}` WHERE id = ?");
            $oldStmt->bind_param('i', $targetId);
            $oldStmt->execute();
            $staffData = $oldStmt->get_result()->fetch_assoc();
            $oldStmt->close();

            if ($staffData) {
                $upload = sfbc_staff_upload_avatar($_FILES['profile_photo'], (string)$staffData['username'], $frontendDir);
                if ($upload['error'] === null && $upload['path'] !== null) {
                    sfbc_staff_remove_local_avatar($staffData['profile_photo'] ?? null, $frontendDir);

                    $webPath = $upload['path'];
                    $upd = $conn->prepare("UPDATE `{$staffTable}` SET profile_photo = ? WHERE id = ?");
                    $upd->bind_param('si', $webPath, $targetId);
                    $upd->execute();
                    $upd->close();

                    if ($staffData['username'] === $admin) {
                        $_SESSION['admin_profile_photo'] = $webPath;
                    }

                    log_activity($conn, $admin, 'Staff Photo Updated', "Updated photo for {$staffData['username']}");
                    $_SESSION['staff_success'] = "Profile photo updated for {$staffData['username']}.";
                } else {
                    $_SESSION['staff_error'] = $upload['error'] ?: 'Failed to upload image file.';
                }
            }
        } elseif (isset($_POST['remove_photo']) && $_POST['remove_photo'] === '1') {
            $oldStmt = $conn->prepare("SELECT profile_photo, username FROM `{$staffTable}` WHERE id = ?");
            $oldStmt->bind_param('i', $targetId);
            $oldStmt->execute();
            $staffData = $oldStmt->get_result()->fetch_assoc();
            $oldStmt->close();

            if ($staffData) {
                sfbc_staff_remove_local_avatar($staffData['profile_photo'] ?? null, $frontendDir);
                $upd = $conn->prepare("UPDATE `{$staffTable}` SET profile_photo = NULL WHERE id = ?");
                $upd->bind_param('i', $targetId);
                $upd->execute();
                $upd->close();

                if ($staffData['username'] === $admin) {
                    unset($_SESSION['admin_profile_photo']);
                }

                $_SESSION['staff_success'] = "Photo removed for {$staffData['username']}.";
            }
        } else {
            $_SESSION['staff_error'] = 'No photo uploaded or upload error.';
        }
    }

    if ($action === 'edit_email') {
        $targetId   = (int)($_POST['staff_id'] ?? 0);
        $newEmail   = trim((string)($_POST['email'] ?? ''));
        $staffUtype = in_array($_POST['staff_type'] ?? '', ['admin', 'receptionist'], true) ? $_POST['staff_type'] : 'receptionist';
        $staffTable = ($staffUtype === 'admin') ? 'administrators' : 'receptionists';

        if ($newEmail !== '' && !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['staff_error'] = 'Please provide a valid email address for OTP delivery.';
        } else {
            $stmt = $conn->prepare("UPDATE `{$staffTable}` SET email = ? WHERE id = ?");
            $stmt->bind_param('si', $newEmail, $targetId);
            $stmt->execute();
            $stmt->close();
            log_activity($conn, $admin, 'Staff OTP Email Updated', "Updated MFA email for staff ID: $targetId ($staffUtype)");
            $_SESSION['staff_success'] = 'OTP Delivery Email updated successfully.';
        }
    }

    if ($action === 'delete_staff') {
        $delId      = (int)($_POST['staff_id'] ?? 0);
        $staffUtype = in_array($_POST['staff_type'] ?? '', ['admin', 'receptionist'], true) ? $_POST['staff_type'] : 'receptionist';
        $delTable   = ($staffUtype === 'admin') ? 'administrators' : 'receptionists';

        $stmt = $conn->prepare("SELECT username FROM `{$delTable}` WHERE id = ?");
        $stmt->bind_param('i', $delId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row && $row['username'] === $admin) {
            $_SESSION['staff_error'] = 'You cannot delete your own account.';
        } elseif ($row) {
            if ($staffUtype === 'admin') {
                $adminCount = (int)$conn->query('SELECT COUNT(*) AS c FROM administrators')->fetch_assoc()['c'];
                if ($adminCount <= 1) {
                    $_SESSION['staff_error'] = 'Cannot delete the last admin account.';
                    header('Location: admin_staff');
                    exit;
                }
            }
            $stmt = $conn->prepare("DELETE FROM `{$delTable}` WHERE id = ?");
            $stmt->bind_param('i', $delId);
            $stmt->execute();
            $stmt->close();
            log_activity($conn, $admin, 'Staff Deleted', "Removed {$staffUtype} account: {$row['username']}");
            SecurityLogger::log($conn, 'STAFF_DELETED', "Removed {$staffUtype} account: {$row['username']}", SecurityLogger::LEVEL_WARNING, $admin);
            $_SESSION['staff_success'] = "Staff account \"{$row['username']}\" removed.";
        }
    }

    if ($action === 'change_role') {
        $targetId    = (int)($_POST['staff_id'] ?? 0);
        $currentType = in_array($_POST['staff_type'] ?? '', ['admin', 'receptionist'], true) ? $_POST['staff_type'] : 'receptionist';
        $newRole     = in_array($_POST['new_role'] ?? '', ['admin', 'receptionist'], true) ? $_POST['new_role'] : 'receptionist';
        $newType     = ($newRole === 'admin') ? 'admin' : 'receptionist';

        if ($currentType === $newType) {
            header('Location: admin_staff');
            exit;
        }

        $srcTable = ($currentType === 'admin') ? 'administrators' : 'receptionists';
        $dstTable = ($newType === 'admin') ? 'administrators' : 'receptionists';

        $stmt = $conn->prepare("SELECT username, password, email, profile_photo FROM `{$srcTable}` WHERE id = ?");
        $stmt->bind_param('i', $targetId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row) {
            if ($row['username'] === $admin && $newType !== 'admin') {
                $_SESSION['staff_error'] = 'You cannot remove your own admin role.';
            } else {
                if ($currentType === 'admin') {
                    $adminCount = (int)$conn->query('SELECT COUNT(*) AS c FROM administrators')->fetch_assoc()['c'];
                    if ($adminCount <= 1) {
                        $_SESSION['staff_error'] = 'Cannot demote the last admin account.';
                        header('Location: admin_staff');
                        exit;
                    }
                }

                $ins = $conn->prepare("INSERT INTO `{$dstTable}` (username, password, email, profile_photo) VALUES (?,?,?,?)");
                $ins->bind_param('ssss', $row['username'], $row['password'], $row['email'], $row['profile_photo']);
                $ins->execute();
                $ins->close();

                $del = $conn->prepare("DELETE FROM `{$srcTable}` WHERE id = ?");
                $del->bind_param('i', $targetId);
                $del->execute();
                $del->close();

                log_activity($conn, $admin, 'Role Changed', "{$row['username']} changed to $newRole");
                SecurityLogger::log($conn, 'ROLE_CHANGED', "{$row['username']} changed to {$newRole}", SecurityLogger::LEVEL_INFO, $admin);
                $_SESSION['staff_success'] = "{$row['username']}'s role updated to $newRole.";
            }
        }
    }

    if ($action === 'reset_password') {
        $targetId   = (int)($_POST['staff_id'] ?? 0);
        $newPw      = (string)($_POST['new_password'] ?? '');
        $staffUtype = in_array($_POST['staff_type'] ?? '', ['admin', 'receptionist'], true) ? $_POST['staff_type'] : 'receptionist';
        $staffTable = ($staffUtype === 'admin') ? 'administrators' : 'receptionists';

        $stmt = $conn->prepare("SELECT username FROM `{$staffTable}` WHERE id = ?");
        $stmt->bind_param('i', $targetId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row && ($pwError = pw_validate($newPw)) === null) {
            $hash = pw_hash($newPw);
            $stmt = $conn->prepare("UPDATE `{$staffTable}` SET password=? WHERE id=?");
            $stmt->bind_param('si', $hash, $targetId);
            $stmt->execute();
            $stmt->close();
            log_activity($conn, $admin, 'Password Reset', "Reset password for: {$row['username']}");
            SecurityLogger::log($conn, 'PASSWORD_RESET', "Reset password for {$staffUtype}: {$row['username']}", SecurityLogger::LEVEL_INFO, $admin);
            $_SESSION['staff_success'] = "Password reset for \"{$row['username']}\".";
        } elseif ($row && $pwError !== null) {
            $_SESSION['staff_error'] = $pwError;
        } else {
            $_SESSION['staff_error'] = 'Password must be at least 8 characters and meet complexity requirements.';
        }
    }

    if ($action === 'toggle_lock') {
        $targetId   = (int)($_POST['staff_id'] ?? 0);
        $lockOp     = $_POST['lock_op'] ?? '';
        $staffUtype = in_array($_POST['staff_type'] ?? '', ['admin', 'receptionist'], true) ? $_POST['staff_type'] : 'receptionist';
        $staffTable = ($staffUtype === 'admin') ? 'administrators' : 'receptionists';

        $stmt = $conn->prepare("SELECT username, locked_until FROM `{$staffTable}` WHERE id = ?");
        $stmt->bind_param('i', $targetId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row) {
            if ($row['username'] === $admin) {
                $_SESSION['staff_error'] = 'You cannot lock your own account.';
            } elseif ($lockOp === 'lock') {
                $lockUntil = date('Y-m-d H:i:s', strtotime('+10 years'));
                $upd = $conn->prepare("UPDATE `{$staffTable}` SET locked_until = ?, failed_login_count = 5 WHERE id = ?");
                $upd->bind_param('si', $lockUntil, $targetId);
                $upd->execute();
                $upd->close();

                log_activity($conn, $admin, 'Account Locked', "Suspended account: {$row['username']}");
                SecurityLogger::log($conn, 'ACCOUNT_MANUALLY_LOCKED', "Admin {$admin} locked account: {$row['username']}", SecurityLogger::LEVEL_WARNING, $row['username']);
                $_SESSION['staff_success'] = "Account \"{$row['username']}\" has been locked and suspended.";
            } elseif ($lockOp === 'unlock') {
                $upd = $conn->prepare("UPDATE `{$staffTable}` SET locked_until = NULL, failed_login_count = 0 WHERE id = ?");
                $upd->bind_param('i', $targetId);
                $upd->execute();
                $upd->close();

                log_activity($conn, $admin, 'Account Unlocked', "Restored access for: {$row['username']}");
                SecurityLogger::log($conn, 'ACCOUNT_MANUALLY_UNLOCKED', "Admin {$admin} unlocked account: {$row['username']}", SecurityLogger::LEVEL_INFO, $row['username']);
                $_SESSION['staff_success'] = "Account \"{$row['username']}\" has been unlocked.";
            }
        }
    }

    if ($action === 'toggle_staff_maintenance') {
        $enabled = isset($_POST['maintenance_mode']) && $_POST['maintenance_mode'] === '1' ? '1' : '0';
        $message = trim((string)($_POST['maintenance_message'] ?? ''));
        if ($message === '') {
            $message = 'Front Desk Reception Portal is currently locked for system maintenance. Please contact the Resort Administrator.';
        }

        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('staff_portal_locked', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->bind_param('s', $enabled);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('staff_portal_locked_msg', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->bind_param('s', $message);
        $stmt->execute();
        $stmt->close();

        $statusText = ($enabled === '1') ? 'LOCKED / UNDER MAINTENANCE' : 'ACTIVE / OPEN';
        log_activity($conn, $admin, 'Portal Maintenance Toggled', "Staff login portal set to: {$statusText}");
        SecurityLogger::log($conn, 'PORTAL_LOCK_CHANGED', "Staff portal maintenance mode set to {$statusText}", SecurityLogger::LEVEL_WARNING, $admin);

        $_SESSION['staff_success'] = ($enabled === '1')
            ? 'Staff login portal is now LOCKED for maintenance. Receptionists cannot sign in.'
            : 'Staff login portal is now OPEN and unlocked for all staff.';
    }

    header('Location: admin_staff');
    exit;
}

/**
 * Handles staff management POST operations and gathers page data for admin_staff.php.
 */
function sfbc_handle_admin_staff_page(mysqli $conn): array
{
    $admin = (string)$_SESSION['admin_username'];
    $frontendDir = __DIR__ . '/../../frontend';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        sfbc_staff_handle_post($conn, $admin, $frontendDir);
    }

    $success = (string)($_SESSION['staff_success'] ?? '');
    $error   = (string)($_SESSION['staff_error'] ?? '');
    unset($_SESSION['staff_success'], $_SESSION['staff_error']);

    $staffRes = $conn->query("
        SELECT id, username, email, 'admin' AS role, 'admin' AS user_type, profile_photo, locked_until, failed_login_count, created_at FROM administrators
        UNION ALL
        SELECT id, username, email, 'receptionist' AS role, 'receptionist' AS user_type, profile_photo, locked_until, failed_login_count, created_at FROM receptionists
        ORDER BY role ASC, created_at ASC
    ");

    $staffRows = [];
    if ($staffRes) {
        while ($row = $staffRes->fetch_assoc()) {
            $photo = (string)($row['profile_photo'] ?? '');
            $hasAvatar = $photo !== '' && (is_remote_image($photo) || file_exists($frontendDir . '/' . ltrim($photo, '/')));
            $row['is_locked']  = !empty($row['locked_until']) && (strtotime((string)$row['locked_until']) > time());
            $row['is_me']      = ($row['username'] === $admin);
            $row['has_avatar'] = $hasAvatar;
            $row['short_name'] = explode('@', (string)$row['username'])[0];
            $staffRows[] = $row;
        }
    }

    $portalSettingsRes = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('staff_portal_locked', 'staff_portal_locked_msg')");
    $portalSettings = [];
    if ($portalSettingsRes) {
        while ($row = $portalSettingsRes->fetch_assoc()) {
            $portalSettings[$row['setting_key']] = $row['setting_value'];
        }
    }

    $isPortalLocked = ($portalSettings['staff_portal_locked'] ?? '0') === '1';
    $portalLockMsg  = $portalSettings['staff_portal_locked_msg'] ?? 'Front Desk Reception Portal is currently locked for system maintenance. Please contact the Resort Administrator.';

    $perms = [
        'Dashboard'        => ['admin' => true, 'rec' => true],
        'Reservations'     => ['admin' => true, 'rec' => true],
        'Check-in/out'     => ['admin' => true, 'rec' => true],
        'Payments'         => ['admin' => true, 'rec' => true],
        'Accommodations'   => ['admin' => true, 'rec' => 'View Only'],
        'Staff Management' => ['admin' => true, 'rec' => false],
        'Reports'          => ['admin' => true, 'rec' => 'Limited'],
        'Promotions'       => ['admin' => true, 'rec' => false],
        'Activity Logs'    => ['admin' => true, 'rec' => false],
        'Settings'         => ['admin' => true, 'rec' => true],
    ];

    return [
        'admin'          => $admin,
        'success'        => $success,
        'error'          => $error,
        'staffRows'      => $staffRows,
        'isPortalLocked' => $isPortalLocked,
        'portalLockMsg'  => $portalLockMsg,
        'perms'          => $perms,
        'csrfField'      => csrf_field(),
        'versions'       => [
            'adminCss' => filemtime($frontendDir . '/assets/css/admin.css'),
            'pageCss'  => file_exists($frontendDir . '/assets/css/pages/admin-staff.css')
                ? filemtime($frontendDir . '/assets/css/pages/admin-staff.css')
                : 1,
            'pageJs'   => file_exists($frontendDir . '/assets/js/pages/admin-staff.js')
                ? filemtime($frontendDir . '/assets/js/pages/admin-staff.js')
                : 1,
        ],
    ];
}
