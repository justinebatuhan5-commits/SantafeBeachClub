<?php

/**
 * Settings page controller: account credentials, profile photo, admin users,
 * property/payment/refund settings, and notification preferences view state.
 */

function sfbc_handle_settings_page(mysqli $conn, bool $isAdminFlag = false): array
{
    $current_admin = (string)($_SESSION['admin_username'] ?? '');
    $current_role  = (string)($_SESSION['admin_role'] ?? 'receptionist');
    $current_table = ($current_role === 'admin') ? 'administrators' : 'receptionists';
    $isAdmin       = $isAdminFlag || ($current_role === 'admin');
    $frontendRoot  = dirname(__DIR__, 2) . '/frontend';

    $success = '';
    $error   = '';
    $my_profile_photo = null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf_token();
        $action = $_POST['action'] ?? '';
        $stepUpActions = ['add_admin', 'delete_admin', 'save_payment_instructions', 'save_refund_methods'];
        if ($current_role === 'admin' && in_array($action, $stepUpActions, true) && !admin_password_recent()) {
            $error = 'Confirm your administrator password before changing accounts, payment, or refund security settings.';
            $action = '__reauth_required__';
        }

        if ($action === 'change_password') {
            if ($current_role !== 'admin') {
                $error = 'Receptionist passwords can only be changed by an administrator.';
            } else {
                $current_pw = $_POST['current_password'] ?? '';
                $new_pw     = $_POST['new_password'] ?? '';
                $confirm_pw = $_POST['confirm_password'] ?? '';

                $stmt = $conn->prepare("SELECT password FROM `{$current_table}` WHERE username = ?");
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
                    $stmt = $conn->prepare("UPDATE `{$current_table}` SET password = ? WHERE username = ?");
                    $stmt->bind_param("ss", $hash, $current_admin);
                    $stmt->execute();
                    $stmt->close();
                    SecurityLogger::log($conn, 'PASSWORD_CHANGED', 'User successfully changed password', SecurityLogger::LEVEL_INFO, $current_admin);
                    $success = 'Password updated successfully.';
                }
            }
        }

        if ($action === 'change_username') {
            if ($current_role !== 'admin') {
                $error = 'Only administrators are permitted to change usernames. Please contact an admin.';
            } else {
                $new_username = trim($_POST['new_username'] ?? '');
                $pw_confirm   = $_POST['password_for_username'] ?? '';

                $stmt = $conn->prepare("SELECT id, password FROM `{$current_table}` WHERE username = ?");
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
                    $stmtA = $conn->prepare("SELECT id FROM administrators WHERE username = ? AND NOT (id = ? AND '{$current_table}' = 'administrators')");
                    $stmtA->bind_param("si", $new_username, $row['id']);
                    $stmtA->execute();
                    $existsA = $stmtA->get_result()->num_rows > 0;
                    $stmtA->close();

                    $stmtR = $conn->prepare("SELECT id FROM receptionists WHERE username = ? AND NOT (id = ? AND '{$current_table}' = 'receptionists')");
                    $stmtR->bind_param("si", $new_username, $row['id']);
                    $stmtR->execute();
                    $existsR = $stmtR->get_result()->num_rows > 0;
                    $stmtR->close();

                    if ($existsA || $existsR) {
                        $error = 'That username is already taken.';
                    } else {
                        $stmt = $conn->prepare("UPDATE `{$current_table}` SET username = ? WHERE id = ?");
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
        }

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
                $stmtA = $conn->prepare("SELECT id FROM administrators WHERE username = ?");
                $stmtA->bind_param("s", $new_user);
                $stmtA->execute();
                $existsA = $stmtA->get_result()->num_rows > 0;
                $stmtA->close();

                $stmtR = $conn->prepare("SELECT id FROM receptionists WHERE username = ?");
                $stmtR->bind_param("s", $new_user);
                $stmtR->execute();
                $existsR = $stmtR->get_result()->num_rows > 0;
                $stmtR->close();

                if ($existsA || $existsR) {
                    $error = 'An account with that username already exists.';
                } else {
                    $hash = pw_hash($new_pass);
                    $stmt = $conn->prepare("INSERT INTO administrators (username, password) VALUES (?, ?)");
                    $stmt->bind_param("ss", $new_user, $hash);
                    $stmt->execute();
                    $stmt->close();
                    SecurityLogger::log($conn, 'ADMIN_CREATED', "New admin user {$new_user} created", SecurityLogger::LEVEL_INFO, $current_admin);
                    $success = "Admin account \"$new_user\" created successfully.";
                }
            }
        }

        if ($action === 'delete_admin') {
            RBAC::requireRole('admin');
            $del_id = (int)($_POST['admin_id'] ?? 0);
            $stmt = $conn->prepare("SELECT username FROM administrators WHERE id = ?");
            $stmt->bind_param("i", $del_id);
            $stmt->execute();
            $del_user_row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($del_user_row && $del_user_row['username'] === $current_admin) {
                $error = 'You cannot delete your own account.';
            } elseif ($del_id > 0) {
                $adminCount = (int)$conn->query("SELECT COUNT(*) AS c FROM administrators")->fetch_assoc()['c'];
                if ($adminCount <= 1) {
                    $error = 'Cannot delete the last admin account.';
                } else {
                    $stmt = $conn->prepare("DELETE FROM administrators WHERE id = ?");
                    $stmt->bind_param("i", $del_id);
                    $stmt->execute();
                    $stmt->close();
                    SecurityLogger::log($conn, 'ADMIN_DELETED', "Admin user ID {$del_id} deleted", SecurityLogger::LEVEL_WARNING, $current_admin);
                    $success = 'Admin account removed.';
                }
            }
        }

        if ($action === 'update_profile_photo') {
            if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
                $file         = $_FILES['profile_photo'];
                $allowedExts  = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

                $fileExt  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $finfo    = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);

                if (!in_array($fileExt, $allowedExts, true) || !in_array($mimeType, $allowedMimes, true)) {
                    $error = 'Invalid image format. Allowed formats: JPG, PNG, WEBP, GIF.';
                } elseif ($file['size'] > 5 * 1024 * 1024) {
                    $error = 'Profile photo exceeds maximum size of 5MB.';
                } else {
                    require_once __DIR__ . '/../helpers/file_scanner.php';
                    $scanResult = scan_uploaded_file($file['tmp_name'], $fileExt);
                    if (!$scanResult['safe']) {
                        $error = 'File rejected: ' . ($scanResult['reason'] ?? 'unsafe content detected');
                    } else {
                        $oldStmt = $conn->prepare("SELECT profile_photo FROM `{$current_table}` WHERE username = ?");
                        $oldStmt->bind_param("s", $current_admin);
                        $oldStmt->execute();
                        $oldPhoto = $oldStmt->get_result()->fetch_assoc()['profile_photo'] ?? null;
                        $oldStmt->close();

                        if ($oldPhoto && !is_remote_image($oldPhoto) && file_exists($frontendRoot . '/' . $oldPhoto)) {
                            @unlink($frontendRoot . '/' . $oldPhoto);
                        }

                        $cloudResult = cloudinary_upload($file['tmp_name'], 'sfbc_avatars');
                        $webPath     = null;
                        if ($cloudResult['success'] && !empty($cloudResult['url'])) {
                            $webPath = $cloudResult['url'];
                        } else {
                            $uploadDir = $frontendRoot . '/uploads/avatars/';
                            if (!is_dir($uploadDir)) {
                                @mkdir($uploadDir, 0755, true);
                            }
                            $filename   = 'avatar_' . md5($current_admin . time() . uniqid()) . '.' . $fileExt;
                            $targetPath = $uploadDir . $filename;
                            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                                $webPath = 'uploads/avatars/' . $filename;
                            }
                        }

                        if ($webPath !== null) {
                            $updStmt = $conn->prepare("UPDATE `{$current_table}` SET profile_photo = ? WHERE username = ?");
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
                }
            } else {
                $error = 'Please select a valid image file to upload.';
            }
        }

        if ($action === 'remove_profile_photo') {
            $oldStmt = $conn->prepare("SELECT profile_photo FROM `{$current_table}` WHERE username = ?");
            $oldStmt->bind_param("s", $current_admin);
            $oldStmt->execute();
            $oldPhoto = $oldStmt->get_result()->fetch_assoc()['profile_photo'] ?? null;
            $oldStmt->close();

            if ($oldPhoto && !is_remote_image($oldPhoto) && file_exists($frontendRoot . '/' . $oldPhoto)) {
                @unlink($frontendRoot . '/' . $oldPhoto);
            }

            $updStmt = $conn->prepare("UPDATE `{$current_table}` SET profile_photo = NULL WHERE username = ?");
            $updStmt->bind_param("s", $current_admin);
            $updStmt->execute();
            $updStmt->close();

            unset($_SESSION['admin_profile_photo']);
            $my_profile_photo = null;
            SecurityLogger::log($conn, 'PROFILE_PHOTO_REMOVED', "Removed profile photo", SecurityLogger::LEVEL_INFO, $current_admin);
            $success = 'Profile photo removed.';
        }

        if ($action === 'save_highlights') {
            RBAC::requireRole('admin');
            $highlight_values = [];
            foreach (['hero_stay_title', 'hero_stay_description', 'hero_support_title', 'hero_support_description'] as $key) {
                $value = $_POST[$key] ?? '';
                if (!is_string($value) || !preg_match('/\A.{0,60}\z/us', trim($value))) {
                    $error = 'Each highlight field must be text with no more than 60 characters.';
                    break;
                }
                $highlight_values[$key] = trim($value);
            }
            if ($error === '') {
                $conn->begin_transaction();
                try {
                    $stmt = $conn->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
                    foreach ($highlight_values as $key => $value) {
                        $stmt->bind_param('ss', $key, $value);
                        $stmt->execute();
                    }
                    $stmt->close();
                    $conn->commit();
                    $success = 'Homepage highlights saved successfully.';
                } catch (Throwable $e) {
                    $conn->rollback();
                    $error = 'Unable to save homepage highlights. Please try again.';
                }
            }
        }

        if ($action === 'save_property') {
            RBAC::requireRole('admin');
            $fields = ['property_name', 'property_address', 'property_phone', 'property_email', 'checkin_time', 'checkout_time', 'property_timezone', 'currency'];
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

        if ($action === 'save_payment_instructions') {
            RBAC::requireRole('admin');
            $paymentFields = ['gcash_number', 'gcash_name', 'discount_bank_name', 'discount_bank_account', 'discount_bank_holder'];
            $paymentValues = [];
            foreach ($paymentFields as $key) {
                $value = trim((string)($_POST[$key] ?? ''));
                if (strlen($value) > 150) {
                    $error = 'Payment details must be no longer than 150 characters.';
                    break;
                }
                $paymentValues[$key] = $value;
            }
            if ($error === '') {
                $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                foreach ($paymentValues as $key => $value) {
                    $stmt->bind_param('ss', $key, $value);
                    $stmt->execute();
                }
                $stmt->close();
                SecurityLogger::log($conn, 'PAYMENT_INSTRUCTIONS_UPDATED', 'Updated manual payment instructions', SecurityLogger::LEVEL_INFO, $current_admin);
                $success = 'Payment instructions saved successfully.';
            }
        }

        if ($action === 'save_refund_methods') {
            RBAC::requireRole('admin');
            $refundMethodValues = [
                'refund_paymongo_enabled'     => ($_POST['refund_paymongo_enabled'] ?? '0') === '1' ? '1' : '0',
                'refund_manual_gcash_enabled' => ($_POST['refund_manual_gcash_enabled'] ?? '0') === '1' ? '1' : '0',
            ];
            $stmt = $conn->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
            foreach ($refundMethodValues as $key => $value) {
                $stmt->bind_param('ss', $key, $value);
                $stmt->execute();
            }
            $stmt->close();
            SecurityLogger::log(
                $conn,
                'REFUND_METHODS_UPDATED',
                'PayMongo refunds: ' . ($refundMethodValues['refund_paymongo_enabled'] === '1' ? 'enabled' : 'disabled')
                    . '; Manual GCash refunds: ' . ($refundMethodValues['refund_manual_gcash_enabled'] === '1' ? 'enabled' : 'disabled') . '.',
                SecurityLogger::LEVEL_INFO,
                $current_admin
            );
            $success = 'Refund method availability saved successfully.';
        }
    }

    $adminsList = [];
    $adminsRes  = $conn->query("SELECT id, username, profile_photo, created_at FROM administrators ORDER BY created_at ASC");
    if ($adminsRes) {
        while ($adminRow = $adminsRes->fetch_assoc()) {
            $adminsList[] = $adminRow;
        }
    }

    $myProfileStmt = $conn->prepare("SELECT profile_photo, email FROM `{$current_table}` WHERE username = ? LIMIT 1");
    $myProfileStmt->bind_param("s", $current_admin);
    $myProfileStmt->execute();
    $myProfileData = $myProfileStmt->get_result()->fetch_assoc();
    $myProfileStmt->close();

    $my_profile_photo = $myProfileData['profile_photo'] ?? null;
    if ($my_profile_photo) {
        $_SESSION['admin_profile_photo'] = $my_profile_photo;
    }

    $hasProfilePhoto = !empty($my_profile_photo)
        && (is_remote_image($my_profile_photo) || file_exists($frontendRoot . '/' . $my_profile_photo));

    $settings_raw = $conn->query("SELECT setting_key, setting_value FROM settings");
    $settings = [];
    if ($settings_raw) {
        while ($row = $settings_raw->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }

    $current_property_timezone = sf_normalize_timezone_identifier($settings['property_timezone'] ?? null);
    $property_timezone_options = sf_get_supported_property_timezones();

    $active_tab = $_GET['tab'] ?? 'profile';
    $allowedTabs = $isAdmin ? ['profile', 'users', 'property', 'notifications'] : ['profile', 'notifications'];
    if (!in_array($active_tab, $allowedTabs, true)) {
        $active_tab = 'profile';
    }

    return [
        'currentAdmin' => $current_admin,
        'currentRole' => $current_role,
        'isAdmin' => $isAdmin,
        'success' => $success,
        'error' => $error,
        'needsReauth' => $error !== '' && str_contains($error, 'Confirm your administrator password'),
        'myProfilePhoto' => $my_profile_photo,
        'hasProfilePhoto' => $hasProfilePhoto,
        'admins' => $adminsList,
        'settings' => $settings,
        'currentPropertyTimezone' => $current_property_timezone,
        'propertyTimezoneOptions' => $property_timezone_options,
        'activeTab' => $active_tab,
        'adminCssVersion' => file_exists($frontendRoot . '/assets/css/admin.css') ? filemtime($frontendRoot . '/assets/css/admin.css') : 1,
        'dashboardCssVersion' => file_exists($frontendRoot . '/assets/css/dashboard.css') ? filemtime($frontendRoot . '/assets/css/dashboard.css') : 1,
        'cssVersion' => file_exists($frontendRoot . '/assets/css/pages/settings.css') ? filemtime($frontendRoot . '/assets/css/pages/settings.css') : 1,
        'jsVersion' => file_exists($frontendRoot . '/assets/js/pages/settings.js') ? filemtime($frontendRoot . '/assets/js/pages/settings.js') : 1,
    ];
}
