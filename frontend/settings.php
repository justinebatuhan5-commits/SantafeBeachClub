<?php
require_once __DIR__ . '/../backend/helpers/auth_check.php';
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/helpers/business_time_helper.php';
require_once __DIR__ . '/../backend/helpers/security_logger.php';
require_once __DIR__ . '/../backend/helpers/rbac_helper.php';
require_once __DIR__ . '/../backend/helpers/password_helper.php';
require_once __DIR__ . '/../backend/helpers/cloudinary_helper.php';

$current_admin = $_SESSION['admin_username'];
$success = '';
$error = '';

// ── POST HANDLERS ──────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
    $action = $_POST['action'] ?? '';

    // --- Change own password ---
    if ($action === 'change_password') {
        $current_pw  = $_POST['current_password'] ?? '';
        $new_pw      = $_POST['new_password'] ?? '';
        $confirm_pw  = $_POST['confirm_password'] ?? '';

        $stmt = $conn->prepare("SELECT password FROM admins WHERE username = ?");
        $stmt->bind_param("s", $current_admin);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row || !pw_verify($current_pw, $row['password'])) {
            $error = 'Current password is incorrect.';
            SecurityLogger::log($conn, 'PASSWORD_CHANGE_FAILED', 'Incorrect current password attempt', SecurityLogger::LEVEL_WARNING, $current_admin);
        } elseif ($new_pw !== $confirm_pw) {
            $error = 'New passwords do not match.';
        } elseif (($pwError = pw_validate($new_pw)) !== null) {
            $error = $pwError;
        } else {
            $hash = pw_hash($new_pw);
            $stmt = $conn->prepare("UPDATE admins SET password = ? WHERE username = ?");
            $stmt->bind_param("ss", $hash, $current_admin);
            $stmt->execute();
            $stmt->close();
            // Upgrade hash if stored with old algorithm
            SecurityLogger::log($conn, 'PASSWORD_CHANGED', 'User successfully changed password', SecurityLogger::LEVEL_INFO, $current_admin);
            $success = 'Password updated successfully.';
        }
    }

    // --- Change own username ---
    if ($action === 'change_username') {
        $new_username = trim($_POST['new_username'] ?? '');
        $pw_confirm   = $_POST['password_for_username'] ?? '';

        $stmt = $conn->prepare("SELECT id, password FROM admins WHERE username = ?");
        $stmt->bind_param("s", $current_admin);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row || !password_verify($pw_confirm, $row['password'])) {
            $error = 'Password confirmation is incorrect.';
        } elseif (strlen($new_username) < 3) {
            $error = 'Username must be at least 3 characters.';
        } elseif (!str_ends_with($new_username, '@santafebeachclub.com') && !str_ends_with($new_username, '@beachclub.com')) {
            $error = 'Username must end with @santafebeachclub.com.';
        } else {
            $stmt = $conn->prepare("SELECT id FROM admins WHERE username = ? AND id != ?");
            $stmt->bind_param("si", $new_username, $row['id']);
            $stmt->execute();
            $check = $stmt->get_result();
            $stmt->close();

            if ($check->num_rows > 0) {
                $error = 'That username is already taken.';
            } else {
                $stmt = $conn->prepare("UPDATE admins SET username = ? WHERE id = ?");
                $stmt->bind_param("si", $new_username, $row['id']);
                $stmt->execute();
                $stmt->close();
                SecurityLogger::log($conn, 'USERNAME_CHANGED', "Username changed from {$current_admin} to {$new_username}", SecurityLogger::LEVEL_INFO, $new_username);
                $_SESSION['admin_username'] = $new_username;
                $current_admin = $new_username;
                $success = 'Username updated successfully.';
            }
        }
    }

    // --- Add new admin ---
    if ($action === 'add_admin') {
        RBAC::requireRole('admin');
        $new_user = trim($_POST['admin_username'] ?? '');
        $new_pass = $_POST['admin_password'] ?? '';

        if (strlen($new_user) < 3) {
            $error = 'Username must be at least 3 characters.';
        } elseif (!str_ends_with($new_user, '@santafebeachclub.com') && !str_ends_with($new_user, '@beachclub.com')) {
            $error = 'Username must end with @santafebeachclub.com.';
        } elseif (($pwError = pw_validate($new_pass)) !== null) {
            $error = $pwError;
        } else {
            $stmt = $conn->prepare("SELECT id FROM admins WHERE username = ?");
            $stmt->bind_param("s", $new_user);
            $stmt->execute();
            $check = $stmt->get_result();
            $stmt->close();

            if ($check->num_rows > 0) {
                $error = 'An account with that username already exists.';
            } else {
                $hash = pw_hash($new_pass);
                $stmt = $conn->prepare("INSERT INTO admins (username, password) VALUES (?, ?)");
                $stmt->bind_param("ss", $new_user, $hash);
                $stmt->execute();
                $stmt->close();
                SecurityLogger::log($conn, 'ADMIN_CREATED', "New admin user {$new_user} created", SecurityLogger::LEVEL_INFO, $current_admin);
                $success = "Admin account \"$new_user\" created successfully.";
            }
        }
    }

    // --- Delete admin ---
    if ($action === 'delete_admin') {
        RBAC::requireRole('admin');
        $del_id = (int)($_POST['admin_id'] ?? 0);
        $stmt = $conn->prepare("SELECT username FROM admins WHERE id = ?");
        $stmt->bind_param("i", $del_id);
        $stmt->execute();
        $del_user_row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($del_user_row && $del_user_row['username'] === $current_admin) {
            $error = 'You cannot delete your own account.';
        } elseif ($del_id > 0) {
            $stmt = $conn->prepare("DELETE FROM admins WHERE id = ?");
            $stmt->bind_param("i", $del_id);
            $stmt->execute();
            $stmt->close();
            SecurityLogger::log($conn, 'ADMIN_DELETED', "Admin user ID {$del_id} deleted", SecurityLogger::LEVEL_WARNING, $current_admin);
            $success = 'Admin account removed.';
        }
    }

    // --- Upload / Update Profile Photo ---
    if ($action === 'update_profile_photo') {
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['profile_photo'];
            $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            
            $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($fileExt, $allowedExts) || !in_array($mimeType, $allowedMimes)) {
                $error = 'Invalid image format. Allowed formats: JPG, PNG, WEBP, GIF.';
            } elseif ($file['size'] > 5 * 1024 * 1024) { // 5MB max
                $error = 'Profile photo exceeds maximum size of 5MB.';
            } else {
                // Delete old local photo if exists
                $oldStmt = $conn->prepare("SELECT profile_photo FROM admins WHERE username = ?");
                $oldStmt->bind_param("s", $current_admin);
                $oldStmt->execute();
                $oldPhoto = $oldStmt->get_result()->fetch_assoc()['profile_photo'] ?? null;
                $oldStmt->close();

                if ($oldPhoto && !is_remote_image($oldPhoto) && file_exists(__DIR__ . '/' . $oldPhoto)) {
                    @unlink(__DIR__ . '/' . $oldPhoto);
                }

                $cloudResult = cloudinary_upload($file['tmp_name'], 'sfbc_avatars');
                $webPath = null;
                if ($cloudResult['success'] && !empty($cloudResult['url'])) {
                    $webPath = $cloudResult['url'];
                } else {
                    $uploadDir = __DIR__ . '/uploads/avatars/';
                    if (!is_dir($uploadDir)) {
                        @mkdir($uploadDir, 0755, true);
                    }
                    $filename = 'avatar_' . md5($current_admin . time() . uniqid()) . '.' . $fileExt;
                    $targetPath = $uploadDir . $filename;
                    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                        $webPath = 'uploads/avatars/' . $filename;
                    }
                }

                if ($webPath !== null) {
                    $updStmt = $conn->prepare("UPDATE admins SET profile_photo = ? WHERE username = ?");
                    $updStmt->bind_param("ss", $webPath, $current_admin);
                    $updStmt->execute();
                    $updStmt->close();

                    $_SESSION['admin_profile_photo'] = $webPath;
                    $my_profile_photo = $webPath;
                    SecurityLogger::log($conn, 'PROFILE_PHOTO_UPDATED', "Updated profile photo", SecurityLogger::LEVEL_INFO, $current_admin);
                    $success = 'Profile photo updated successfully.';
                } else {
                    $error = 'Failed to save uploaded image.';
                }
            }
        } else {
            $error = 'Please select a valid image file to upload.';
        }
    }

    // --- Remove Profile Photo ---
    if ($action === 'remove_profile_photo') {
        $oldStmt = $conn->prepare("SELECT profile_photo FROM admins WHERE username = ?");
        $oldStmt->bind_param("s", $current_admin);
        $oldStmt->execute();
        $oldPhoto = $oldStmt->get_result()->fetch_assoc()['profile_photo'] ?? null;
        $oldStmt->close();

        if ($oldPhoto && !is_remote_image($oldPhoto) && file_exists(__DIR__ . '/' . $oldPhoto)) {
            @unlink(__DIR__ . '/' . $oldPhoto);
        }

        $updStmt = $conn->prepare("UPDATE admins SET profile_photo = NULL WHERE username = ?");
        $updStmt->bind_param("s", $current_admin);
        $updStmt->execute();
        $updStmt->close();

        unset($_SESSION['admin_profile_photo']);
        $my_profile_photo = null;
        SecurityLogger::log($conn, 'PROFILE_PHOTO_REMOVED', "Removed profile photo", SecurityLogger::LEVEL_INFO, $current_admin);
        $success = 'Profile photo removed.';
    }

    // --- Save property settings ---
    if ($action === 'save_property') {
        $fields = ['property_name', 'property_address', 'property_phone', 'property_email', 'checkin_time', 'checkout_time', 'property_timezone', 'currency', 'gcash_number', 'gcash_name'];
        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        foreach ($fields as $key) {
            $val = $key === 'property_timezone'
                ? sf_normalize_timezone_identifier($_POST[$key] ?? null)
                : trim($_POST[$key] ?? '');
            $stmt->bind_param("ss", $key, $val);
            $stmt->execute();
        }
        $stmt->close();
        date_default_timezone_set(sf_normalize_timezone_identifier($_POST['property_timezone'] ?? null));
        $success = 'Property settings saved successfully.';
    }
}

// ── FETCH DATA ─────────────────────────────────────────────────────────────────
$admins = $conn->query("SELECT id, username, profile_photo, created_at FROM admins ORDER BY created_at ASC");

$myProfileStmt = $conn->prepare("SELECT profile_photo, role, email FROM admins WHERE username = ? LIMIT 1");
$myProfileStmt->bind_param("s", $current_admin);
$myProfileStmt->execute();
$myProfileData = $myProfileStmt->get_result()->fetch_assoc();
$myProfileStmt->close();

$my_profile_photo = $myProfileData['profile_photo'] ?? null;
if ($my_profile_photo) {
    $_SESSION['admin_profile_photo'] = $my_profile_photo;
}

$settings_raw = $conn->query("SELECT setting_key, setting_value FROM settings");
$settings = [];
while ($row = $settings_raw->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$current_property_timezone = sf_normalize_timezone_identifier($settings['property_timezone'] ?? null);
$property_timezone_options = sf_get_supported_property_timezones();

$active_tab = $_GET['tab'] ?? 'profile';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/jpeg" href="assets/logo.jpg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=4">
    <style>
        /* ── Settings Page Layout ── */
        .settings-wrapper {
            max-width: 860px;
        }

        .page-header {
            margin-bottom: 28px;
        }

        .page-header h1 {
            font-size: 24px;
            font-weight: 700;
            color: #1F2937;
        }

        .page-header p {
            font-size: 14px;
            color: var(--color-text-muted);
            margin-top: 4px;
        }

        /* ── Tab navigation ── */
        .tab-nav {
            display: flex;
            gap: 4px;
            margin-bottom: 28px;
            border-bottom: 1px solid #E5E7EB;
        }

        .tab-btn {
            padding: 10px 20px;
            border: none;
            background: none;
            font-family: 'Outfit', sans-serif;
            font-size: 14px;
            font-weight: 500;
            color: var(--color-text-muted);
            cursor: pointer;
            border-bottom: 2px solid transparent;
            margin-bottom: -1px;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tab-btn:hover { color: var(--color-text-main); }

        .tab-btn.active {
            color: var(--color-primary);
            border-bottom-color: var(--color-primary);
            font-weight: 600;
        }

        .tab-panel { display: none; }
        .tab-panel.active { display: block; }

        /* ── Settings Cards ── */
        .settings-card {
            background: white;
            border-radius: 12px;
            padding: 28px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }

        .settings-card h3 {
            font-size: 15px;
            font-weight: 700;
            color: #1F2937;
            margin-bottom: 4px;
        }

        .settings-card .card-desc {
            font-size: 13px;
            color: var(--color-text-muted);
            margin-bottom: 22px;
            padding-bottom: 18px;
            border-bottom: 1px solid #F3F4F6;
        }

        /* ── Form fields ── */
        .settings-form .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }

        .settings-form .form-row.single { grid-template-columns: 1fr; }

        .settings-form label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .settings-form input,
        .settings-form select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #D1D5DB;
            border-radius: 8px;
            font-family: 'Outfit', sans-serif;
            font-size: 14px;
            color: #374151;
            outline: none;
            transition: border-color 0.2s;
            box-sizing: border-box;
        }

        .settings-form input:focus,
        .settings-form select:focus {
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(124,83,60,0.08);
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .btn-save {
            background: var(--color-primary);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 10px 24px;
            font-family: 'Outfit', sans-serif;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-save:hover { background: var(--color-primary-hover); }

        /* ── Alerts ── */
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success { background: #ECFDF5; color: #065F46; border: 1px solid #6EE7B7; }
        .alert-error   { background: #FEF2F2; color: #991B1B; border: 1px solid #FCA5A5; }

        /* ── Admin users table ── */
        .admin-table {
            width: 100%;
            border-collapse: collapse;
        }

        .admin-table th {
            font-size: 11px;
            font-weight: 700;
            color: var(--color-text-muted);
            text-transform: uppercase;
            letter-spacing: 0.6px;
            padding: 0 0 12px 0;
            border-bottom: 1px solid #F3F4F6;
            text-align: left;
        }

        .admin-table td {
            padding: 14px 0;
            border-bottom: 1px solid #F3F4F6;
            font-size: 14px;
            color: #374151;
            vertical-align: middle;
        }

        .admin-table tr:last-child td { border-bottom: none; }

        .badge-you {
            display: inline-block;
            background: var(--color-sidebar-active);
            color: var(--color-primary);
            font-size: 10px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 20px;
            margin-left: 8px;
            text-transform: uppercase;
        }

        .btn-delete {
            background: none;
            border: 1px solid #FCA5A5;
            color: #DC2626;
            border-radius: 6px;
            padding: 5px 14px;
            font-family: 'Outfit', sans-serif;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-delete:hover {
            background: #FEF2F2;
        }

        .btn-delete:disabled {
            opacity: 0.35;
            cursor: not-allowed;
        }

        /* ── Avatar initial circle ── */
        .admin-avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: var(--color-sidebar-active);
            color: var(--color-primary);
            font-size: 13px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .admin-row-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        /* ── Divider ── */
        .section-divider {
            border: none;
            border-top: 1px solid #F3F4F6;
            margin: 24px 0;
        }

        .subsection-title {
            font-size: 13px;
            font-weight: 700;
            color: #374151;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* ── Property icon grid ── */
        .property-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: var(--color-sidebar-active);
            color: var(--color-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 22px;
        }

        /* ── Notification Preferences Toggles ─────────────────────── */
        .notif-pref-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 0;
            border-bottom: 1px solid #F3F4F6;
        }
        .notif-pref-row:last-of-type { border-bottom: none; }
        .notif-pref-info { max-width: 76%; }
        .notif-pref-info strong {
            font-size: 14px;
            font-weight: 700;
            color: #1F2937;
            display: block;
            margin-bottom: 3px;
        }
        .notif-pref-info span {
            font-size: 12.5px;
            color: var(--color-text-muted);
            line-height: 1.5;
        }
        /* iOS-style toggle switch */
        .toggle-switch { position: relative; display: inline-block; width: 44px; height: 24px; flex-shrink: 0; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; position: absolute; }
        .toggle-track {
            position: absolute; inset: 0;
            background: #D1D5DB;
            border-radius: 24px;
            cursor: pointer;
            transition: background .2s;
        }
        .toggle-track::after {
            content: '';
            position: absolute;
            left: 3px; top: 3px;
            width: 18px; height: 18px;
            border-radius: 50%;
            background: #fff;
            box-shadow: 0 1px 3px rgba(0,0,0,.25);
            transition: transform .2s;
        }
        .toggle-switch input:checked + .toggle-track { background: #84563C; }
        .toggle-switch input:checked + .toggle-track::after { transform: translateX(20px); }
        .notif-pref-status {
            font-size: 12px;
            font-weight: 700;
            margin-top: 3px;
        }
        .notif-pref-status.on  { color: #059669; }
        .notif-pref-status.off { color: #9CA3AF; }
        .notif-test-btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 16px;
            border: 1px solid var(--color-primary);
            background: transparent;
            color: var(--color-primary);
            border-radius: 8px;
            font-family: 'Outfit', sans-serif;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: all .15s;
        }
        .notif-test-btn:hover { background: var(--color-sidebar-active); }
        .notif-permission-note {
            background: #FFF7ED;
            border: 1px solid #FED7AA;
            border-radius: 10px;
            padding: 14px 16px;
            font-size: 13px;
            color: #92400E;
            margin-top: 18px;
            display: none;
        }
        .notif-permission-note.visible { display: flex; gap: 10px; align-items: flex-start; }
    </style>
</head>
<body>

    <?php $active_page = 'settings'; include __DIR__ . '/partials/_sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <div class="settings-wrapper">

            <div class="page-header">
                <h1>Settings</h1>
                <p>Manage your account, team access, and property configuration.</p>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Tab Nav -->
            <div class="tab-nav">
                <button class="tab-btn <?php echo $active_tab === 'profile' ? 'active' : ''; ?>" onclick="switchTab('profile')">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                    My Profile
                </button>
                <?php if ($_is_admin): ?>
                <button class="tab-btn <?php echo $active_tab === 'users' ? 'active' : ''; ?>" onclick="switchTab('users')">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    User Management
                </button>
                <button class="tab-btn <?php echo $active_tab === 'property' ? 'active' : ''; ?>" onclick="switchTab('property')">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                    Property
                </button>
                <?php endif; ?>
                <button class="tab-btn <?php echo $active_tab === 'notifications' ? 'active' : ''; ?>" onclick="switchTab('notifications')">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    Notifications
                </button>
            </div>

            <!-- ═══════════════════════════════════════════ -->
            <!-- TAB: My Profile                             -->
            <!-- ═══════════════════════════════════════════ -->
            <div class="tab-panel <?php echo $active_tab === 'profile' ? 'active' : ''; ?>" id="tab-profile">

                <!-- Profile Photo Card -->
                <div class="settings-card">
                    <h3>Profile Photo</h3>
                    <p class="card-desc">Upload a personal photo for your admin/receptionist profile displayed across the dashboard, sidebar, and headers.</p>
                    <div style="display:flex; align-items:center; gap:24px; flex-wrap:wrap; margin-top:16px;">
                        <div style="position:relative;">
                            <?php if (!empty($my_profile_photo) && (is_remote_image($my_profile_photo) || file_exists(__DIR__ . '/' . $my_profile_photo))): ?>
                                <img src="<?php echo htmlspecialchars($my_profile_photo); ?>" alt="Profile Photo" style="width:84px; height:84px; border-radius:50%; object-fit:cover; border:3px solid var(--color-primary, #7C533C); box-shadow:0 4px 12px rgba(0,0,0,0.1);">
                            <?php else: ?>
                                <div style="width:84px; height:84px; border-radius:50%; background:linear-gradient(135deg, #7C533C, #5C3D2B); color:#FFF; display:flex; align-items:center; justify-content:center; font-size:32px; font-weight:700; box-shadow:0 4px 12px rgba(0,0,0,0.1);">
                                    <?php echo strtoupper(substr($current_admin !== '' ? $current_admin : 'U', 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div style="flex:1; min-width:260px;">
                            <form method="POST" enctype="multipart/form-data" style="display:flex; flex-direction:column; gap:10px;">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="update_profile_photo">
                                <label style="font-size:13px; font-weight:600; color:#374151;">Select New Image (JPG, PNG, WEBP, max 5MB):</label>
                                <div style="display:flex; gap:10px; align-items:center;">
                                    <input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp,image/gif" required style="padding:8px 12px; font-size:13px; border:1px solid #D1D5DB; border-radius:8px; background:#F9FAFB; flex:1;">
                                    <button type="submit" class="btn-save" style="padding:9px 18px; font-size:13px; white-space:nowrap;">Upload Photo</button>
                                </div>
                            </form>

                            <?php if (!empty($my_profile_photo) && (is_remote_image($my_profile_photo) || file_exists(__DIR__ . '/' . $my_profile_photo))): ?>
                            <form method="POST" style="margin-top:8px;">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="remove_profile_photo">
                                <button type="submit" style="background:none; border:none; color:#DC2626; font-size:12px; font-weight:600; cursor:pointer; padding:0; display:flex; align-items:center; gap:4px;">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                    Remove Current Photo
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Change Username -->
                <div class="settings-card">
                    <h3>Username</h3>
                    <p class="card-desc">Update the username you use to sign in.</p>
                    <form method="POST" class="settings-form">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="change_username">
                        <div class="form-row">
                            <div>
                                <label>Current Username</label>
                                <input type="text" value="<?php echo htmlspecialchars($current_admin); ?>" disabled style="background:#F9FAFB; color:#888;">
                            </div>
                            <div>
                                <label>New Username</label>
                                <input type="email" name="new_username" required pattern=".+@(santafebeachclub|beachclub)\.com$" title="Must end with @santafebeachclub.com" placeholder="name@santafebeachclub.com">
                            </div>
                        </div>
                        <div class="form-row single">
                            <div>
                                <label>Confirm with Password</label>
                                <input type="password" name="password_for_username" required placeholder="Your current password">
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn-save">Update Username</button>
                        </div>
                    </form>
                </div>

                <!-- Change Password -->
                <div class="settings-card">
                    <h3>Password</h3>
                    <p class="card-desc">Choose a strong password. Must be at least 8 characters and include uppercase, lowercase, a number, and a special character (e.g. !@#$%).</p>
                    <form method="POST" class="settings-form">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="change_password">
                        <div class="form-row single">
                            <div>
                                <label>Current Password</label>
                                <input type="password" name="current_password" required placeholder="Enter current password">
                            </div>
                        </div>
                        <div class="form-row">
                            <div>
                                <label>New Password</label>
                                <input type="password" name="new_password" required minlength="8" placeholder="Min. 8 chars, upper, lower, number, symbol">
                            </div>
                            <div>
                                <label>Confirm New Password</label>
                                <input type="password" name="confirm_password" required minlength="8" placeholder="Repeat new password">
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn-save">Update Password</button>
                        </div>
                    </form>
                </div>

            </div>

            <!-- ═══════════════════════════════════════════ -->
            <!-- TAB: User Management                        -->
            <!-- ═══════════════════════════════════════════ -->
            <?php if ($_is_admin): ?>
            <div class="tab-panel <?php echo $active_tab === 'users' ? 'active' : ''; ?>" id="tab-users">

                <!-- Admin Accounts List -->
                <div class="settings-card">
                    <h3>Admin Accounts</h3>
                    <p class="card-desc">All reception staff with dashboard access. You cannot remove your own account.</p>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Account</th>
                                <th>Created</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($admin = $admins->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="admin-row-info">
                                        <div class="admin-avatar"><?php echo strtoupper(substr($admin['username'], 0, 1)); ?></div>
                                        <div>
                                            <?php echo htmlspecialchars($admin['username']); ?>
                                            <?php if ($admin['username'] === $current_admin): ?>
                                                <span class="badge-you">You</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td style="color: var(--color-text-muted); font-size:13px;">
                                    <?php echo date('M j, Y', strtotime($admin['created_at'])); ?>
                                </td>
                                <td style="text-align:right;">
                                    <form method="POST" onsubmit="return false;" onsubmit="confirmDelete('<?php echo htmlspecialchars($admin['username']); ?>', this); return false;">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="delete_admin">
                                        <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                                        <button type="submit" class="btn-delete"
                                            <?php echo ($admin['username'] === $current_admin) ? 'disabled title="Cannot delete your own account"' : ''; ?>>
                                            Remove
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Add New Admin -->
                <div class="settings-card">
                    <h3>Add New Admin</h3>
                    <p class="card-desc">Create a new reception staff account. Share credentials securely.</p>
                    <form method="POST" class="settings-form">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="add_admin">
                        <div class="form-row">
                            <div>
                                <label>Username</label>
                                <input type="email" name="admin_username" required pattern=".+@(santafebeachclub|beachclub)\.com$" title="Must end with @santafebeachclub.com" placeholder="name@santafebeachclub.com">
                            </div>
                            <div>
                                <label>Password</label>
                                <input type="password" name="admin_password" required minlength="8" placeholder="Min. 8 chars, upper, lower, number, symbol">
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn-save">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;vertical-align:middle;"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                                Create Account
                            </button>
                        </div>
                    </form>
                </div>

            </div>
            <?php endif; ?>

            <!-- ═══════════════════════════════════════════ -->
            <!-- TAB: Property                               -->
            <!-- ═══════════════════════════════════════════ -->
            <?php if ($_is_admin): ?>
            <div class="tab-panel <?php echo $active_tab === 'property' ? 'active' : ''; ?>" id="tab-property">

                <div class="settings-card">
                    <h3>Property Information</h3>
                    <p class="card-desc">These details appear on receipts, emails, and throughout the dashboard.</p>

                    <form method="POST" class="settings-form">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="save_property">

                        <p class="subsection-title">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                            Venue Details
                        </p>

                        <div class="form-row single">
                            <div>
                                <label>Property Name</label>
                                <input type="text" name="property_name" value="<?php echo htmlspecialchars($settings['property_name'] ?? ''); ?>" placeholder="e.g. Santa Fe Beach Club">
                            </div>
                        </div>
                        <div class="form-row single">
                            <div>
                                <label>Address</label>
                                <input type="text" name="property_address" value="<?php echo htmlspecialchars($settings['property_address'] ?? ''); ?>" placeholder="Full property address">
                            </div>
                        </div>
                        <div class="form-row">
                            <div>
                                <label>Phone</label>
                                <input type="text" name="property_phone" value="<?php echo htmlspecialchars($settings['property_phone'] ?? ''); ?>" placeholder="+63 32 000 0000">
                            </div>
                            <div>
                                <label>Email</label>
                                <input type="email" name="property_email" value="<?php echo htmlspecialchars($settings['property_email'] ?? ''); ?>" placeholder="info@yourproperty.com">
                            </div>
                        </div>

                        <hr class="section-divider">

                        <p class="subsection-title">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                            GCash Payment Details (Guest Checkout)
                        </p>

                        <div class="form-row">
                            <div>
                                <label>GCash Mobile Number</label>
                                <input type="text" name="gcash_number" value="<?php echo htmlspecialchars($settings['gcash_number'] ?? '0950 522 3146'); ?>" placeholder="0950 522 3146">
                            </div>
                            <div>
                                <label>GCash Account Name</label>
                                <input type="text" name="gcash_name" value="<?php echo htmlspecialchars($settings['gcash_name'] ?? 'Justine B'); ?>" placeholder="e.g. Justine B">
                            </div>
                        </div>

                        <hr class="section-divider">

                        <p class="subsection-title">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                            Default Times, Timezone &amp; Currency
                        </p>

                        <div class="form-row">
                            <div>
                                <label>Default Check-in Time</label>
                                <input type="time" name="checkin_time" value="<?php echo htmlspecialchars($settings['checkin_time'] ?? '14:00'); ?>">
                            </div>
                            <div>
                                <label>Default Check-out Time</label>
                                <input type="time" name="checkout_time" value="<?php echo htmlspecialchars($settings['checkout_time'] ?? '12:00'); ?>">
                            </div>
                        </div>
                        <div class="form-row single">
                            <div>
                                <label>Business Timezone</label>
                                <select name="property_timezone">
                                    <?php foreach ($property_timezone_options as $timezone_value => $timezone_label): ?>
                                    <option value="<?php echo htmlspecialchars($timezone_value); ?>" <?php echo ($current_property_timezone === $timezone_value) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($timezone_label); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <small style="display:block; margin-top:6px; color:#6B7280; font-size:12px;">Check-out due notifications use this timezone.</small>
                            </div>
                        </div>
                        <div class="form-row single">
                            <div>
                                <label>Currency</label>
                                <select name="currency">
                                    <?php
                                    $currencies = ['PHP' => 'PHP – Philippine Peso', 'USD' => 'USD – US Dollar', 'EUR' => 'EUR – Euro', 'SGD' => 'SGD – Singapore Dollar'];
                                    $current_currency = $settings['currency'] ?? 'PHP';
                                    foreach ($currencies as $code => $label):
                                    ?>
                                    <option value="<?php echo $code; ?>" <?php echo ($current_currency === $code) ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn-save">Save Property Settings</button>
                        </div>
                    </form>
                </div>

            </div>
            <?php endif; ?>

            <!-- ═════════════════════════════════════════════ -->
            <!-- TAB: Notifications                         -->
            <!-- ═════════════════════════════════════════════ -->
            <div class="tab-panel <?php echo $active_tab === 'notifications' ? 'active' : ''; ?>" id="tab-notifications">

                <div class="settings-card">
                    <h3>Notification Sound</h3>
                    <p class="card-desc">Control the audible alert that plays when a new notification arrives while you are on any admin page.</p>

                    <div class="notif-pref-row">
                        <div class="notif-pref-info">
                            <strong>Notification Sound</strong>
                            <span>Plays a short chime when new alerts arrive. Applies to this browser only.</span>
                            <div class="notif-pref-status" id="sound-status">Loading…</div>
                        </div>
                        <label class="toggle-switch" aria-label="Toggle notification sound">
                            <input type="checkbox" id="toggle-sound">
                            <span class="toggle-track"></span>
                        </label>
                    </div>

                    <div style="margin-top:20px;">
                        <button type="button" class="notif-test-btn" id="test-sound-btn">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/></svg>
                            Test Sound
                        </button>
                    </div>
                </div>

                <div class="settings-card">
                    <h3>Desktop Popup Notifications</h3>
                    <p class="card-desc">Show a native OS notification popup when a new alert arrives, even if you are on a different browser tab.</p>

                    <div class="notif-pref-row">
                        <div class="notif-pref-info">
                            <strong>Desktop Popups</strong>
                            <span>Requires browser permission. Enabling this will prompt the browser to request permission if not already granted.</span>
                            <div class="notif-pref-status" id="desktop-status">Loading…</div>
                        </div>
                        <label class="toggle-switch" aria-label="Toggle desktop notifications">
                            <input type="checkbox" id="toggle-desktop">
                            <span class="toggle-track"></span>
                        </label>
                    </div>

                    <div class="notif-permission-note" id="permission-note">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;margin-top:1px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <div>
                            <strong>Permission blocked by browser.</strong> To enable desktop popups, you must allow notifications for this site in your browser settings
                            (<em>Site Settings &rarr; Notifications &rarr; Allow</em>), then reload this page.
                        </div>
                    </div>

                    <div style="margin-top:20px;">
                        <button type="button" class="notif-test-btn" id="test-desktop-btn">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                            Send Test Popup
                        </button>
                    </div>
                </div>

                <div class="settings-card">
                    <h3>In-Page Toast Banners</h3>
                    <p class="card-desc">The dark slide-up banner at the bottom-right of the screen. This is always shown and cannot be disabled — it is the primary notification indicator when sound is muted.</p>
                    <div class="notif-pref-row">
                        <div class="notif-pref-info">
                            <strong>Toast Banners</strong>
                            <span>Always enabled — provides a visual alert when a new notification arrives.</span>
                            <div class="notif-pref-status on">Always on</div>
                        </div>
                        <label class="toggle-switch" aria-label="Toast banners always on">
                            <input type="checkbox" checked disabled>
                            <span class="toggle-track" style="opacity:.5;cursor:not-allowed;"></span>
                        </label>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <script>
        function switchTab(tab) {
            document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.getElementById('tab-' + tab).classList.add('active');
            document.querySelector('[onclick="switchTab(\'' + tab + '\')"]').classList.add('active');
            history.replaceState(null, '', '?tab=' + tab);
        }

        function confirmDelete(username, formEl) {
            showConfirm({
                title: 'Remove Admin Account',
                message: 'Remove admin account "' + username + '"? This cannot be undone.',
                icon: '👤',
                iconBg: '#FEE2E2',
                confirmText: 'Remove',
                onConfirm: () => formEl.submit()
            });
        }

        // ── Notification Preference Controls ─────────────────────────────
        (function() {
            var soundToggle   = document.getElementById('toggle-sound');
            var desktopToggle = document.getElementById('toggle-desktop');
            var soundStatus   = document.getElementById('sound-status');
            var desktopStatus = document.getElementById('desktop-status');
            var permNote      = document.getElementById('permission-note');
            var testSoundBtn  = document.getElementById('test-sound-btn');
            var testDesktopBtn= document.getElementById('test-desktop-btn');

            if (!soundToggle) return; // not on notifications tab

            // ── Helpers ───────────────────────────────────────────────────
            function setSoundUI(muted) {
                soundToggle.checked = !muted;
                soundStatus.textContent = muted ? 'Muted' : 'Sound On';
                soundStatus.className   = 'notif-pref-status ' + (muted ? 'off' : 'on');
            }

            function setDesktopUI(enabled) {
                desktopToggle.checked = enabled;
                desktopStatus.textContent = enabled ? 'Enabled' : 'Disabled';
                desktopStatus.className   = 'notif-pref-status ' + (enabled ? 'on' : 'off');
            }

            function playTestSound() {
                try {
                    var AC = window.AudioContext || window.webkitAudioContext;
                    if (!AC) { alert('Web Audio not supported in this browser.'); return; }
                    var ctx  = new AC();
                    var now  = ctx.currentTime;
                    var osc  = ctx.createOscillator();
                    var gain = ctx.createGain();
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(880, now);
                    osc.frequency.exponentialRampToValueAtTime(660, now + 0.18);
                    gain.gain.setValueAtTime(0.0001, now);
                    gain.gain.exponentialRampToValueAtTime(0.08, now + 0.01);
                    gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.22);
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.start(now);
                    osc.stop(now + 0.24);
                } catch (e) { console.warn('Audio test error:', e); }
            }

            function checkPermission() {
                if (!('Notification' in window)) {
                    desktopToggle.disabled = true;
                    desktopStatus.textContent = 'Not supported';
                    desktopStatus.className = 'notif-pref-status off';
                    return;
                }
                if (Notification.permission === 'denied') {
                    permNote.classList.add('visible');
                    setDesktopUI(false);
                    desktopToggle.disabled = true;
                } else {
                    permNote.classList.remove('visible');
                    desktopToggle.disabled = false;
                }
            }

            // ── Initialise from localStorage ──────────────────────────────
            var isMuted   = localStorage.getItem('sbc_notif_sound_muted') === '1';
            var isDesktop = localStorage.getItem('sbc_notif_desktop_enabled') === '1';
            setSoundUI(isMuted);
            setDesktopUI(isDesktop);
            checkPermission();

            // ── Sound Toggle ──────────────────────────────────────────────
            soundToggle.addEventListener('change', function() {
                var muted = !soundToggle.checked;
                localStorage.setItem('sbc_notif_sound_muted', muted ? '1' : '0');
                setSoundUI(muted);
            });

            // ── Desktop Toggle ────────────────────────────────────────────
            desktopToggle.addEventListener('change', function() {
                if (!desktopToggle.checked) {
                    localStorage.setItem('sbc_notif_desktop_enabled', '0');
                    setDesktopUI(false);
                    return;
                }
                if (Notification.permission === 'granted') {
                    localStorage.setItem('sbc_notif_desktop_enabled', '1');
                    setDesktopUI(true);
                } else {
                    Notification.requestPermission().then(function(p) {
                        if (p === 'granted') {
                            localStorage.setItem('sbc_notif_desktop_enabled', '1');
                            setDesktopUI(true);
                            permNote.classList.remove('visible');
                        } else {
                            localStorage.setItem('sbc_notif_desktop_enabled', '0');
                            setDesktopUI(false);
                            if (p === 'denied') { permNote.classList.add('visible'); desktopToggle.disabled = true; }
                        }
                    });
                }
            });

            // ── Test Buttons ──────────────────────────────────────────────
            testSoundBtn.addEventListener('click', function() {
                playTestSound();
                testSoundBtn.textContent = '✓ Played!';
                setTimeout(function() {
                    testSoundBtn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/></svg> Test Sound';
                }, 1600);
            });

            testDesktopBtn.addEventListener('click', function() {
                if (Notification.permission !== 'granted') {
                    alert('Desktop notifications are not permitted. Enable them first using the toggle above.');
                    return;
                }
                var n = new Notification('🔔 Santa Fe Beach Club', {
                    body: 'This is a test desktop notification. You are all set!',
                    tag: 'sbc-test-notif'
                });
                setTimeout(function() { n.close(); }, 5000);
                testDesktopBtn.textContent = '✓ Sent!';
                setTimeout(function() {
                    testDesktopBtn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg> Send Test Popup';
                }, 1600);
            });
        })();
    </script>

<script src="assets/js/sidebar-toggle.js"></script>
</body>
</html>
