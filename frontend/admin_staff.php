<?php
require_once __DIR__ . '/../backend/helpers/admin_auth_check.php';
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/helpers/rbac_helper.php';
require_once __DIR__ . '/../backend/helpers/security_logger.php';
require_once __DIR__ . '/../backend/helpers/password_helper.php';

$admin = $_SESSION['admin_username'];
$success = $error = '';

// ── POST HANDLERS ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_staff') {
        $uname = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $pw    = $_POST['password'] ?? '';
        $role  = in_array($_POST['role'] ?? '', ['admin','receptionist']) ? $_POST['role'] : 'receptionist';
        $photoPath = null;

        // Check if avatar was uploaded
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['profile_photo'];
            $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (in_array($fileExt, ['jpg', 'jpeg', 'png', 'webp', 'gif']) && $file['size'] <= 5 * 1024 * 1024) {
                $uploadDir = __DIR__ . '/uploads/avatars/';
                if (!is_dir($uploadDir)) {
                    @mkdir($uploadDir, 0755, true);
                }
                $filename = 'avatar_' . md5($uname . time() . uniqid()) . '.' . $fileExt;
                if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                    $photoPath = 'uploads/avatars/' . $filename;
                }
            }
        }

        if (strlen($uname) < 3) { 
            $_SESSION['staff_error'] = 'Username must be at least 3 characters.'; 
        } elseif (!str_ends_with($uname, '@santafebeachclub.com') && !str_ends_with($uname, '@beachclub.com')) {
            $_SESSION['staff_error'] = 'Username must end with @santafebeachclub.com.';
        } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['staff_error'] = 'Please provide a valid personal/MFA email address.';
        } elseif (($pwError = pw_validate($pw)) !== null) { 
            $_SESSION['staff_error'] = $pwError; 
        } else {
            $stmt = $conn->prepare("SELECT id FROM admins WHERE username = ?");
            $stmt->bind_param("s", $uname);
            $stmt->execute();
            $chk = $stmt->get_result();
            $stmt->close();

            if ($chk->num_rows > 0) { 
                $_SESSION['staff_error'] = 'Username already exists.'; 
            } else {
                $hash = pw_hash($pw);
                $stmt = $conn->prepare("INSERT INTO admins (username, email, password, role, profile_photo) VALUES (?,?,?,?,?)");
                $stmt->bind_param("sssss", $uname, $email, $hash, $role, $photoPath);
                $stmt->execute(); 
                $stmt->close();
                log_activity($conn, $admin, 'Staff Created', "Added $role account: $uname with OTP email: $email");
                SecurityLogger::log($conn, 'STAFF_CREATED', "Added {$role} account: {$uname}", SecurityLogger::LEVEL_INFO, $admin);
                $_SESSION['staff_success'] = "Staff account \"$uname\" ($role) created.";
            }
        }
    }

    if ($action === 'update_staff_photo') {
        $target_id = (int)($_POST['staff_id'] ?? 0);
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['profile_photo'];
            $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (in_array($fileExt, ['jpg', 'jpeg', 'png', 'webp', 'gif']) && $file['size'] <= 5 * 1024 * 1024) {
                $uploadDir = __DIR__ . '/uploads/avatars/';
                if (!is_dir($uploadDir)) {
                    @mkdir($uploadDir, 0755, true);
                }

                // Delete old photo
                $oldStmt = $conn->prepare("SELECT profile_photo, username FROM admins WHERE id = ?");
                $oldStmt->bind_param("i", $target_id);
                $oldStmt->execute();
                $staffData = $oldStmt->get_result()->fetch_assoc();
                $oldStmt->close();

                if ($staffData) {
                    if (!empty($staffData['profile_photo']) && file_exists(__DIR__ . '/' . $staffData['profile_photo'])) {
                        @unlink(__DIR__ . '/' . $staffData['profile_photo']);
                    }

                    $filename = 'avatar_' . md5($staffData['username'] . time() . uniqid()) . '.' . $fileExt;
                    if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                        $webPath = 'uploads/avatars/' . $filename;
                        $upd = $conn->prepare("UPDATE admins SET profile_photo = ? WHERE id = ?");
                        $upd->bind_param("si", $webPath, $target_id);
                        $upd->execute();
                        $upd->close();

                        if ($staffData['username'] === $admin) {
                            $_SESSION['admin_profile_photo'] = $webPath;
                        }

                        log_activity($conn, $admin, 'Staff Photo Updated', "Updated photo for {$staffData['username']}");
                        $_SESSION['staff_success'] = "Profile photo updated for {$staffData['username']}.";
                    } else {
                        $_SESSION['staff_error'] = 'Failed to upload image file.';
                    }
                }
            } else {
                $_SESSION['staff_error'] = 'Invalid image format (must be JPG, PNG, WEBP, max 5MB).';
            }
        } elseif (isset($_POST['remove_photo']) && $_POST['remove_photo'] === '1') {
            $oldStmt = $conn->prepare("SELECT profile_photo, username FROM admins WHERE id = ?");
            $oldStmt->bind_param("i", $target_id);
            $oldStmt->execute();
            $staffData = $oldStmt->get_result()->fetch_assoc();
            $oldStmt->close();

            if ($staffData) {
                if (!empty($staffData['profile_photo']) && file_exists(__DIR__ . '/' . $staffData['profile_photo'])) {
                    @unlink(__DIR__ . '/' . $staffData['profile_photo']);
                }
                $upd = $conn->prepare("UPDATE admins SET profile_photo = NULL WHERE id = ?");
                $upd->bind_param("i", $target_id);
                $upd->execute();
                $upd->close();

                if ($staffData['username'] === $admin) {
                    unset($_SESSION['admin_profile_photo']);
                }

                $_SESSION['staff_success'] = "Photo removed for {$staffData['username']}.";
            }
        }
    }

    if ($action === 'edit_email') {
        $target_id  = (int)($_POST['staff_id'] ?? 0);
        $new_email  = trim($_POST['email'] ?? '');

        if (!empty($new_email) && !filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['staff_error'] = 'Please provide a valid email address for OTP delivery.';
        } else {
            $stmt = $conn->prepare("UPDATE admins SET email = ? WHERE id = ?");
            $stmt->bind_param("si", $new_email, $target_id);
            $stmt->execute();
            $stmt->close();
            log_activity($conn, $admin, 'Staff OTP Email Updated', "Updated MFA email for staff ID: $target_id");
            $_SESSION['staff_success'] = "OTP Delivery Email updated successfully.";
        }
    }

    if ($action === 'delete_staff') {
        $del_id = (int)($_POST['staff_id'] ?? 0);
        $stmt = $conn->prepare("SELECT username, role FROM admins WHERE id = ?");
        $stmt->bind_param("i", $del_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row && $row['username'] === $admin) {
            $_SESSION['staff_error'] = 'You cannot delete your own account.';
        } elseif ($row) {
            // Prevent deleting last admin
            $adminCount = (int)$conn->query("SELECT COUNT(*) AS c FROM admins WHERE role='admin'")->fetch_assoc()['c'];
            if ($row['role'] === 'admin' && $adminCount <= 1) {
                $_SESSION['staff_error'] = 'Cannot delete the last admin account.';
            } else {
                $stmt = $conn->prepare("DELETE FROM admins WHERE id = ?");
                $stmt->bind_param("i", $del_id);
                $stmt->execute();
                $stmt->close();
                log_activity($conn, $admin, 'Staff Deleted', "Removed account: {$row['username']}");
                SecurityLogger::log($conn, 'STAFF_DELETED', "Removed staff account: {$row['username']}", SecurityLogger::LEVEL_WARNING, $admin);
                $_SESSION['staff_success'] = "Staff account \"{$row['username']}\" removed.";
            }
        }
    }

    if ($action === 'change_role') {
        $target_id = (int)($_POST['staff_id'] ?? 0);
        $new_role  = in_array($_POST['new_role'] ?? '', ['admin','receptionist']) ? $_POST['new_role'] : 'receptionist';
        
        $stmt = $conn->prepare("SELECT username, role FROM admins WHERE id = ?");
        $stmt->bind_param("i", $target_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row) {
            if ($row['username'] === $admin && $new_role !== 'admin') {
                $_SESSION['staff_error'] = 'You cannot remove your own admin role.';
            } else {
                $adminCount = (int)$conn->query("SELECT COUNT(*) AS c FROM admins WHERE role='admin'")->fetch_assoc()['c'];
                if ($row['role'] === 'admin' && $new_role === 'receptionist' && $adminCount <= 1) {
                    $_SESSION['staff_error'] = 'Cannot demote the last admin account.';
                } else {
                    $stmt = $conn->prepare("UPDATE admins SET role=? WHERE id=?");
                    $stmt->bind_param("si", $new_role, $target_id);
                    $stmt->execute(); 
                    $stmt->close();
                    log_activity($conn, $admin, 'Role Changed', "{$row['username']} changed to $new_role");
                    SecurityLogger::log($conn, 'ROLE_CHANGED', "{$row['username']} changed to {$new_role}", SecurityLogger::LEVEL_INFO, $admin);
                    $_SESSION['staff_success'] = "{$row['username']}'s role updated to $new_role.";
                }
            }
        }
    }

    if ($action === 'reset_password') {
        $target_id = (int)($_POST['staff_id'] ?? 0);
        $new_pw    = $_POST['new_password'] ?? '';
        
        $stmt = $conn->prepare("SELECT username FROM admins WHERE id = ?");
        $stmt->bind_param("i", $target_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row && ($pwError = pw_validate($new_pw)) === null) {
            $hash = pw_hash($new_pw);
            $stmt = $conn->prepare("UPDATE admins SET password=? WHERE id=?");
            $stmt->bind_param("si", $hash, $target_id);
            $stmt->execute(); 
            $stmt->close();
            log_activity($conn, $admin, 'Password Reset', "Reset password for: {$row['username']}");
            SecurityLogger::log($conn, 'PASSWORD_RESET', "Reset password for staff: {$row['username']}", SecurityLogger::LEVEL_INFO, $admin);
            $_SESSION['staff_success'] = "Password reset for \"{$row['username']}\"."; 
        } elseif ($row && $pwError !== null) {
            $_SESSION['staff_error'] = $pwError;
        } else {
            $_SESSION['staff_error'] = 'Password must be at least 8 characters and meet complexity requirements.';
        }
    }

    if ($action === 'toggle_lock') {
        $target_id = (int)($_POST['staff_id'] ?? 0);
        $lock_op   = $_POST['lock_op'] ?? '';

        $stmt = $conn->prepare("SELECT username, role, locked_until FROM admins WHERE id = ?");
        $stmt->bind_param("i", $target_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row) {
            if ($row['username'] === $admin) {
                $_SESSION['staff_error'] = 'You cannot lock your own account.';
            } else {
                if ($lock_op === 'lock') {
                    // Lock indefinitely (e.g. 10 years into the future)
                    $lockUntil = date('Y-m-d H:i:s', strtotime('+10 years'));
                    $upd = $conn->prepare("UPDATE admins SET locked_until = ?, failed_login_count = 5 WHERE id = ?");
                    $upd->bind_param("si", $lockUntil, $target_id);
                    $upd->execute();
                    $upd->close();

                    log_activity($conn, $admin, 'Account Locked', "Suspended account: {$row['username']}");
                    SecurityLogger::log($conn, 'ACCOUNT_MANUALLY_LOCKED', "Admin {$admin} locked account: {$row['username']}", SecurityLogger::LEVEL_WARNING, $row['username']);
                    $_SESSION['staff_success'] = "Account \"{$row['username']}\" has been locked and suspended.";
                } elseif ($lock_op === 'unlock') {
                    // Unlock account and reset failures
                    $upd = $conn->prepare("UPDATE admins SET locked_until = NULL, failed_login_count = 0 WHERE id = ?");
                    $upd->bind_param("i", $target_id);
                    $upd->execute();
                    $upd->close();

                    log_activity($conn, $admin, 'Account Unlocked', "Restored access for: {$row['username']}");
                    SecurityLogger::log($conn, 'ACCOUNT_MANUALLY_UNLOCKED', "Admin {$admin} unlocked account: {$row['username']}", SecurityLogger::LEVEL_INFO, $row['username']);
                    $_SESSION['staff_success'] = "Account \"{$row['username']}\" has been unlocked.";
                }
            }
        }
    }

    if ($action === 'toggle_staff_maintenance') {
        $enabled = isset($_POST['maintenance_mode']) && $_POST['maintenance_mode'] === '1' ? '1' : '0';
        $message = trim($_POST['maintenance_message'] ?? '');
        if (empty($message)) {
            $message = 'Front Desk Reception Portal is currently locked for system maintenance. Please contact the Resort Administrator.';
        }

        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('staff_portal_locked', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->bind_param("s", $enabled);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('staff_portal_locked_msg', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->bind_param("s", $message);
        $stmt->execute();
        $stmt->close();

        $statusText = ($enabled === '1') ? 'LOCKED / UNDER MAINTENANCE' : 'ACTIVE / OPEN';
        log_activity($conn, $admin, 'Portal Maintenance Toggled', "Staff login portal set to: {$statusText}");
        SecurityLogger::log($conn, 'PORTAL_LOCK_CHANGED', "Staff portal maintenance mode set to {$statusText}", SecurityLogger::LEVEL_WARNING, $admin);

        $_SESSION['staff_success'] = ($enabled === '1') 
            ? "Staff login portal is now LOCKED for maintenance. Receptionists cannot sign in."
            : "Staff login portal is now OPEN and unlocked for all staff.";
    }

    header('Location: admin_staff');
    exit;
}

if (isset($_SESSION['staff_success'])) {
    $success = $_SESSION['staff_success'];
    unset($_SESSION['staff_success']);
}
if (isset($_SESSION['staff_error'])) {
    $error = $_SESSION['staff_error'];
    unset($_SESSION['staff_error']);
}

$staff_list = $conn->query("SELECT id, username, email, role, profile_photo, locked_until, failed_login_count, created_at FROM admins ORDER BY role ASC, created_at ASC");

// Fetch Staff Portal Lockdown / Maintenance Mode settings
$portalSettingsRes = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('staff_portal_locked', 'staff_portal_locked_msg')");
$portalSettings = [];
while ($row = $portalSettingsRes->fetch_assoc()) {
    $portalSettings[$row['setting_key']] = $row['setting_value'];
}
$isPortalLocked = ($portalSettings['staff_portal_locked'] ?? '0') === '1';
$portalLockMsg  = $portalSettings['staff_portal_locked_msg'] ?? 'Front Desk Reception Portal is currently locked for system maintenance. Please contact the Resort Administrator.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/jpeg" href="assets/logo.jpg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Management — Santa Fe Beach Club</title>
    <link rel="stylesheet" href="assets/css/admin.css?v=4">
</head>
<body>
    <?php $active_page = 'staff'; include __DIR__ . '/partials/_sidebar.php'; ?>

    <main class="main-content">
        <?php
        $page_title = 'Staff Management';
        $page_subtitle = 'Manage receptionist and admin accounts, profile photos, roles, and MFA verification emails.';
        include __DIR__ . '/partials/_page_header.php';
        ?>

        <?php if ($success): ?><div class="alert alert-success"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
        <?php if ($error):   ?><div class="alert alert-error"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <div style="display:grid;grid-template-columns:1.8fr 1fr;gap:24px;align-items:start;">

            <!-- Staff Table -->
            <div class="admin-card">
                <div class="admin-card-header">
                    <h3>All Staff Accounts</h3>
                    <button class="btn-primary" onclick="document.getElementById('addModal').classList.add('open')">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Add Staff
                    </button>
                </div>
                <table class="admin-table">
                    <thead><tr><th>Account</th><th>OTP Delivery Email</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php while ($s = $staff_list->fetch_assoc()): 
                        $isLocked = !empty($s['locked_until']) && (strtotime($s['locked_until']) > time());
                    ?>
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:12px;">
                                <div style="position:relative;">
                                    <?php if (!empty($s['profile_photo']) && file_exists(__DIR__ . '/' . $s['profile_photo'])): ?>
                                        <img src="<?php echo htmlspecialchars($s['profile_photo']); ?>" alt="Avatar" style="width:36px;height:36px;border-radius:10px;object-fit:cover;border:1.5px solid var(--border);box-shadow:var(--shadow-xs);">
                                    <?php else: ?>
                                        <div class="user-avatar" style="width:36px;height:36px;font-size:13px;border-radius:10px;"><?php echo strtoupper(substr($s['username'],0,1)); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div style="font-weight:600;display:flex;align-items:center;gap:6px;">
                                        <?php echo htmlspecialchars($s['username']); ?>
                                        <button type="button" style="background:none;border:none;cursor:pointer;padding:2px;color:#7C533C;" title="Update Profile Photo" onclick="openEditPhoto(<?php echo $s['id']; ?>, '<?php echo htmlspecialchars($s['username']); ?>', '<?php echo htmlspecialchars($s['profile_photo'] ?? ''); ?>')">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg>
                                        </button>
                                    </div>
                                    <?php if ($s['username'] === $admin): ?><span class="badge badge-admin" style="font-size:9px;">You</span><?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div style="display:flex;align-items:center;gap:6px;">
                                <?php if (!empty($s['email'])): ?>
                                    <span style="font-size:13px;color:#1E293B;font-weight:500;"><?php echo htmlspecialchars($s['email']); ?></span>
                                <?php else: ?>
                                    <span style="font-size:12px;color:#DC2626;background:#FEE2E2;padding:2px 6px;border-radius:4px;">No personal email</span>
                                <?php endif; ?>
                                <button type="button" style="background:none;border:none;cursor:pointer;padding:2px;color:#7C533C;" title="Change OTP Delivery Email" onclick="openEditEmail(<?php echo $s['id']; ?>, '<?php echo htmlspecialchars($s['username']); ?>', '<?php echo htmlspecialchars($s['email'] ?? ''); ?>')">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                                </button>
                            </div>
                        </td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="change_role">
                                <input type="hidden" name="staff_id" value="<?php echo $s['id']; ?>">
                                <select name="new_role" onchange="this.form.submit()" style="padding:4px 8px;border-radius:6px;border:1px solid var(--border);font-family:Outfit,sans-serif;font-size:12px;">
                                    <option value="admin" <?php echo $s['role']==='admin'?'selected':''; ?>>Admin</option>
                                    <option value="receptionist" <?php echo $s['role']==='receptionist'?'selected':''; ?>>Receptionist</option>
                                </select>
                            </form>
                        </td>
                        <td>
                            <?php if ($isLocked): ?>
                                <span style="display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:600;color:#991B1B;background:#FEE2E2;padding:3px 8px;border-radius:6px;">
                                    🔒 Locked / Suspended
                                </span>
                            <?php else: ?>
                                <span style="display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:600;color:#166534;background:#DCFCE7;padding:3px 8px;border-radius:6px;">
                                    🟢 Active
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display:flex;gap:6px;align-items:center;">
                                <?php if ($s['username'] !== $admin): ?>
                                    <form method="POST" style="display:inline;">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="toggle_lock">
                                        <input type="hidden" name="staff_id" value="<?php echo $s['id']; ?>">
                                        <?php if ($isLocked): ?>
                                            <input type="hidden" name="lock_op" value="unlock">
                                            <button type="submit" class="btn-secondary" style="padding:4px 8px;font-size:11px;background:#F0FDF4;border:1px solid #86EFAC;color:#166534;" title="Restore account login access">
                                                🔓 Unlock
                                            </button>
                                        <?php else: ?>
                                            <input type="hidden" name="lock_op" value="lock">
                                            <button type="submit" class="btn-secondary" style="padding:4px 8px;font-size:11px;background:#FEF2F2;border:1px solid #FECACA;color:#991B1B;" title="Immediately lock and suspend account">
                                                🔒 Lock
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                <?php endif; ?>

                                <button class="btn-secondary" style="padding:5px 10px;font-size:12px;" onclick="openReset(<?php echo $s['id']; ?>,'<?php echo htmlspecialchars($s['username']); ?>')">Reset PW</button>
                                <form method="POST" onsubmit="return false;" data-confirm-title="Remove Staff Account" data-confirm-msg="Remove <?php echo htmlspecialchars($s['username']); ?>? This cannot be undone." data-confirm-icon="👤" data-confirm-icon-bg="#FEE2E2">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete_staff">
                                    <input type="hidden" name="staff_id" value="<?php echo $s['id']; ?>">
                                    <button type="submit" class="btn-danger" <?php echo ($s['username']===$admin)?'disabled':''; ?>>Remove</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- Reception Portal Lockdown & Maintenance Mode Control -->
            <div class="admin-card" style="margin-bottom:24px;border:1.5px solid <?php echo $isPortalLocked ? '#FCA5A5' : 'var(--border)'; ?>;background:<?php echo $isPortalLocked ? '#FFF5F5' : 'var(--card-bg)'; ?>;">
                <div class="admin-card-header" style="border-bottom:1px solid <?php echo $isPortalLocked ? '#FEE2E2' : 'var(--border-light)'; ?>;">
                    <div style="display:flex;align-items:center;gap:12px;">
                        <img src="assets/logo.jpg" alt="Logo" style="width:38px;height:38px;border-radius:10px;object-fit:cover;box-shadow:0 2px 6px rgba(0,0,0,0.12);border:1.5px solid var(--border);">
                        <div>
                            <h3 style="margin:0;font-size:15px;color:<?php echo $isPortalLocked ? '#991B1B' : 'var(--text-main)'; ?>;">Staff Portal Lockdown</h3>
                            <p style="margin:2px 0 0;font-size:12px;color:<?php echo $isPortalLocked ? '#B91C1C' : 'var(--text-muted)'; ?>;">
                                Control reception desk access & maintenance mode
                            </p>
                        </div>
                    </div>
                    <div>
                        <?php if ($isPortalLocked): ?>
                            <span style="display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:700;color:#991B1B;background:#FEE2E2;padding:4px 10px;border-radius:20px;border:1px solid #FCA5A5;letter-spacing:0.5px;">
                                <span style="width:7px;height:7px;border-radius:50%;background:#DC2626;display:inline-block;animation:pulse 1.5s infinite;"></span>
                                LOCKED (OFFLINE)
                            </span>
                        <?php else: ?>
                            <span style="display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:700;color:#166534;background:#DCFCE7;padding:4px 10px;border-radius:20px;border:1px solid #86EFAC;letter-spacing:0.5px;">
                                <span style="width:7px;height:7px;border-radius:50%;background:#16A34A;display:inline-block;"></span>
                                PORTAL OPEN
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="padding:18px;">
                    <p style="font-size:13px;color:<?php echo $isPortalLocked ? '#7F1D1D' : 'var(--text-muted)'; ?>;margin:0 0 16px;line-height:1.5;">
                        <?php if ($isPortalLocked): ?>
                            ⚠️ <strong>Maintenance mode is currently ACTIVE.</strong> The receptionist login page is locked. Anyone visiting <code>staff_login</code> will see a maintenance popup modal and cannot sign in.
                        <?php else: ?>
                            Lock the <code>staff_login</code> page whenever the front desk is under maintenance, undergoing shift auditing, or closed. Receptionists will see your custom popup message.
                        <?php endif; ?>
                    </p>

                    <form method="POST" action="admin_staff" <?php if (!$isPortalLocked): ?>data-confirm-title="Lock Reception Portal?" data-confirm-msg="Are you sure you want to LOCK the staff login portal? All receptionist sign-ins will be blocked and an under-maintenance popup will be shown." data-confirm-icon="🔒" data-confirm-icon-bg="#FEE2E2" data-confirm-color="#DC2626" data-confirm-text="Lock Portal"<?php endif; ?>>
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="toggle_staff_maintenance">
                        
                        <div style="margin-bottom:14px;">
                            <label style="display:block;font-size:12px;font-weight:600;color:var(--text-main);margin-bottom:6px;">Popup Alert Message for Receptionists:</label>
                            <textarea name="maintenance_message" rows="2" style="width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:8px;font-family:inherit;font-size:13px;resize:vertical;" placeholder="E.g., Front Desk Reception Portal is currently locked for system maintenance. Please contact the Resort Administrator."><?php echo htmlspecialchars($portalLockMsg); ?></textarea>
                        </div>

                        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding-top:8px;">
                            <?php if ($isPortalLocked): ?>
                                <input type="hidden" name="maintenance_mode" value="0">
                                <button type="submit" class="btn-primary" style="background:#16A34A;border-color:#15803D;padding:10px 18px;font-size:13px;display:flex;align-items:center;gap:6px;width:100%;justify-content:center;">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg>
                                    Unlock Staff Portal (Restore Sign In)
                                </button>
                            <?php else: ?>
                                <input type="hidden" name="maintenance_mode" value="1">
                                <button type="submit" class="btn-danger" style="padding:10px 18px;font-size:13px;display:flex;align-items:center;gap:6px;width:100%;justify-content:center;">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                    Lock Staff Portal (Activate Maintenance Mode)
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Permissions Reference -->
            <div class="admin-card">
                <div class="admin-card-header"><h3>Permission Reference</h3></div>
                <table class="admin-table">
                    <thead><tr><th>Feature</th><th style="text-align:center;">Admin</th><th style="text-align:center;">Reception</th></tr></thead>
                    <tbody>
                    <?php
                    $perms = [
                        'Dashboard'            => ['admin'=>true, 'rec'=>true],
                        'Reservations'         => ['admin'=>true, 'rec'=>true],
                        'Check-in/out'         => ['admin'=>true, 'rec'=>true],
                        'Payments'             => ['admin'=>true, 'rec'=>true],
                        'Accommodations'       => ['admin'=>true, 'rec'=>'View Only'],
                        'Staff Management'     => ['admin'=>true, 'rec'=>false],
                        'Reports'              => ['admin'=>true, 'rec'=>'Limited'],
                        'Promotions'           => ['admin'=>true, 'rec'=>false],
                        'Activity Logs'        => ['admin'=>true, 'rec'=>false],
                        'Settings'             => ['admin'=>true, 'rec'=>true],
                    ];
                    foreach ($perms as $feat => $p):
                        $chk = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>';
                        $ex  = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#EF4444" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
                    ?>
                    <tr>
                        <td style="font-size:13px;"><?php echo $feat; ?></td>
                        <td style="text-align:center;"><?php echo $p['admin'] ? $chk : $ex; ?></td>
                        <td style="text-align:center;">
                            <?php if ($p['rec'] === true): echo $chk;
                            elseif ($p['rec'] === false): echo $ex;
                            else: echo '<span style="font-size:11px;color:var(--text-muted);">'.$p['rec'].'</span>'; endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
    </main>

<!-- Add Staff Modal -->
<div class="modal-overlay" id="addModal">
    <div class="modal-box">
        <button class="modal-close" onclick="document.getElementById('addModal').classList.remove('open')">×</button>
        <h3>Add Staff Account</h3>
        <p class="modal-sub">Create a new login for a team member.</p>
        <form method="POST" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="add_staff">
            <div class="admin-form-group"><label>System Username (Login ID)</label><input type="email" name="username" required pattern=".+@(santafebeachclub|beachclub)\.com$" title="Must end with @santafebeachclub.com" placeholder="name@santafebeachclub.com"></div>
            <div class="admin-form-group"><label>Personal Email (Receives Login OTPs)</label><input type="email" name="email" required placeholder="personal@gmail.com"></div>
            <div class="admin-form-group"><label>Password</label><input type="password" name="password" required minlength="8" placeholder="Min 8 chars: upper, lower, number, symbol" title="Must be 8+ characters with uppercase, lowercase, number, and special character"></div>
            <div class="admin-form-group">
                <label>Profile Photo (Optional)</label>
                <input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp,image/gif">
            </div>
            <div class="admin-form-group">
                <label>Role</label>
                <select name="role">
                    <option value="receptionist">Receptionist</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <button type="submit" class="btn-primary" style="width:100%;justify-content:center;">Create Account</button>
        </form>
    </div>
</div>

<!-- Edit Staff Photo Modal -->
<div class="modal-overlay" id="editPhotoModal">
    <div class="modal-box">
        <button class="modal-close" onclick="document.getElementById('editPhotoModal').classList.remove('open')">×</button>
        <h3>Update Profile Photo</h3>
        <p class="modal-sub" id="editPhotoModalSub">Upload a new photo for this staff member.</p>
        <form method="POST" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="update_staff_photo">
            <input type="hidden" name="staff_id" id="editPhotoStaffId">
            <div class="admin-form-group">
                <label>Select Photo (JPG, PNG, WEBP, max 5MB)</label>
                <input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp,image/gif" required>
            </div>
            <button type="submit" class="btn-primary" style="width:100%;justify-content:center;margin-bottom:8px;">Upload & Save</button>
        </form>
        <form method="POST" id="removePhotoForm">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="update_staff_photo">
            <input type="hidden" name="staff_id" id="removePhotoStaffId">
            <input type="hidden" name="remove_photo" value="1">
            <button type="submit" class="btn-danger" style="width:100%;justify-content:center;background:none;border:1px solid #FCA5A5;color:#DC2626;" id="removePhotoBtn">Remove Existing Photo</button>
        </form>
    </div>
</div>

<!-- Edit OTP Email Modal -->
<div class="modal-overlay" id="editEmailModal">
    <div class="modal-box">
        <button class="modal-close" onclick="document.getElementById('editEmailModal').classList.remove('open')">×</button>
        <h3>Edit OTP Delivery Email</h3>
        <p class="modal-sub" id="editEmailModalSub">Update the email address where login OTPs are sent.</p>
        <form method="POST">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="edit_email">
            <input type="hidden" name="staff_id" id="editEmailStaffId">
            <div class="admin-form-group">
                <label>Personal / Delivery Email (Gmail, etc.)</label>
                <input type="email" name="email" id="editEmailInput" required placeholder="name@gmail.com">
            </div>
            <button type="submit" class="btn-primary" style="width:100%;justify-content:center;">Save Email</button>
        </form>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal-overlay" id="resetModal">
    <div class="modal-box">
        <button class="modal-close" onclick="document.getElementById('resetModal').classList.remove('open')">×</button>
        <h3>Reset Password</h3>
        <p class="modal-sub" id="resetModalSub">Set a new password for this account.</p>
        <form method="POST">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="staff_id" id="resetStaffId">
            <div class="admin-form-group"><label>New Password</label><input type="password" name="new_password" required minlength="8" placeholder="Min 8 chars: upper, lower, number, symbol" title="Must be 8+ characters with uppercase, lowercase, number, and special character"></div>
            <button type="submit" class="btn-primary" style="width:100%;justify-content:center;">Save New Password</button>
        </form>
    </div>
</div>

<script>
function openReset(id, name) {
    document.getElementById('resetStaffId').value = id;
    document.getElementById('resetModalSub').textContent = 'Set a new password for "' + name + '".';
    document.getElementById('resetModal').classList.add('open');
}

function openEditEmail(id, username, email) {
    document.getElementById('editEmailStaffId').value = id;
    document.getElementById('editEmailInput').value = email;
    document.getElementById('editEmailModalSub').textContent = 'Update OTP delivery email for "' + username + '".';
    document.getElementById('editEmailModal').classList.add('open');
}

function openEditPhoto(id, username, currentPhoto) {
    document.getElementById('editPhotoStaffId').value = id;
    document.getElementById('removePhotoStaffId').value = id;
    document.getElementById('editPhotoModalSub').textContent = 'Upload or change profile photo for "' + username + '".';
    document.getElementById('removePhotoBtn').style.display = currentPhoto ? 'flex' : 'none';
    document.getElementById('editPhotoModal').classList.add('open');
}
</script>
<script src="assets/js/sidebar-toggle.js"></script>
</body>
</html>

