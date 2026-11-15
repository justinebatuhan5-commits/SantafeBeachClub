<?php
/**
 * Admin gallery page controller.
 *
 * Handles gallery photo uploads (Cloudinary with local fallback) and deletions.
 */

/**
 * Normalizes $_FILES['photos'] into individual upload descriptors.
 */
function sfbc_gallery_normalize_files(?array $filesInput): array
{
    if (!isset($filesInput['name']) || !is_array($filesInput['name'])) {
        return [];
    }

    $files = [];
    $count = count($filesInput['name']);
    for ($i = 0; $i < $count; $i++) {
        $files[] = [
            'name'     => $filesInput['name'][$i],
            'tmp_name' => $filesInput['tmp_name'][$i],
            'error'    => $filesInput['error'][$i],
            'size'     => $filesInput['size'][$i],
        ];
    }

    return $files;
}

/**
 * Processes POST actions for the admin gallery page.
 */
function sfbc_gallery_handle_post(mysqli $conn, string $admin, string $diskDir): void
{
    require_csrf_token();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_photo') {
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        $allowedExts  = ['jpg', 'jpeg', 'png', 'webp'];
        $uploaded     = 0;
        $errors       = [];
        $files        = sfbc_gallery_normalize_files($_FILES['photos'] ?? null);

        if (empty($files)) {
            $_SESSION['gal_error'] = 'No files selected.';
        } else {
            require_once __DIR__ . '/../helpers/file_scanner.php';

            foreach ($files as $file) {
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    $errors[] = "{$file['name']}: upload error code {$file['error']}";
                    continue;
                }

                $tmpFile  = $file['tmp_name'];
                $origName = basename($file['name']);
                $filesize = $file['size'];
                $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

                $finfo    = finfo_open(FILEINFO_MIME_TYPE);
                $realMime = finfo_file($finfo, $tmpFile);
                finfo_close($finfo);

                if (!in_array($ext, $allowedExts, true) || !in_array($realMime, $allowedMimes, true)) {
                    $errors[] = "$origName: invalid format (only JPG, PNG, WebP).";
                    SecurityLogger::log($conn, 'FILE_UPLOAD_REJECTED', "Rejected invalid mime ($realMime) for $origName", SecurityLogger::LEVEL_WARNING, $admin);
                    continue;
                }

                if ($filesize > 2 * 1024 * 1024) {
                    $errors[] = "$origName: exceeds 2 MB limit.";
                    continue;
                }

                $scanResult = scan_uploaded_file($tmpFile, $ext);
                if (!$scanResult['safe']) {
                    $errors[] = "$origName rejected: " . ($scanResult['reason'] ?? 'unsafe content detected');
                    SecurityLogger::log($conn, 'FILE_UPLOAD_MALICIOUS', "Rejected unsafe content in $origName: " . ($scanResult['reason'] ?? ''), SecurityLogger::LEVEL_WARNING, $admin);
                    continue;
                }

                $cloudResult = cloudinary_upload($tmpFile, 'sfbc_gallery');
                $savedPath   = null;

                if ($cloudResult['success'] && !empty($cloudResult['url'])) {
                    $savedPath = $cloudResult['url'];
                } else {
                    $newFilename = 'gal_' . bin2hex(random_bytes(16)) . '.' . $ext;
                    if (!is_dir($diskDir)) {
                        @mkdir($diskDir, 0755, true);
                    }
                    if (move_uploaded_file($tmpFile, $diskDir . $newFilename)) {
                        $savedPath = $newFilename;
                    }
                }

                if ($savedPath !== null) {
                    $stmt = $conn->prepare("INSERT INTO gallery (file_name) VALUES (?)");
                    $stmt->bind_param("s", $savedPath);
                    $stmt->execute();
                    $stmt->close();
                    log_activity($conn, $admin, 'Gallery Photo Added', "Added: $origName");
                    SecurityLogger::log($conn, 'FILE_UPLOADED', "Uploaded gallery image: $savedPath", SecurityLogger::LEVEL_INFO, $admin);
                    $uploaded++;
                } else {
                    $errDetail = $cloudResult['error'] ?? 'Local upload failed';
                    $errors[]  = "$origName: " . htmlspecialchars($errDetail);
                }
            }

            if ($uploaded > 0) {
                $_SESSION['gal_success'] = "$uploaded photo(s) uploaded successfully." . (count($errors) ? ' Some files had errors.' : '');
            }
            if (!empty($errors) && $uploaded === 0) {
                $_SESSION['gal_error'] = implode(' | ', $errors);
            }
        }
    }

    if ($action === 'delete_photo') {
        $photoId = (int)($_POST['photo_id'] ?? 0);
        $stmt = $conn->prepare("SELECT file_name FROM gallery WHERE id = ?");
        $stmt->bind_param("i", $photoId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row) {
            $filePath = $diskDir . basename($row['file_name']);
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
            $delStmt = $conn->prepare("DELETE FROM gallery WHERE id = ?");
            $delStmt->bind_param("i", $photoId);
            $delStmt->execute();
            $delStmt->close();
            log_activity($conn, $admin, 'Gallery Photo Deleted', "Removed: {$row['file_name']}");
            SecurityLogger::log($conn, 'GALLERY_PHOTO_DELETED', "Deleted photo ID {$photoId}", SecurityLogger::LEVEL_INFO, $admin);
            $_SESSION['gal_success'] = "Photo deleted.";
        }
    }

    header('Location: admin_gallery');
    exit;
}

/**
 * Loads gallery records and flash messages for the admin gallery view.
 */
function sfbc_handle_admin_gallery_page(mysqli $conn): array
{
    $admin     = $_SESSION['admin_username'];
    $targetDir = 'assets/gallery/';
    $diskDir   = dirname(__DIR__, 2) . '/frontend/assets/gallery/';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        sfbc_gallery_handle_post($conn, $admin, $diskDir);
    }

    $success = $_SESSION['gal_success'] ?? '';
    $error   = $_SESSION['gal_error']   ?? '';
    unset($_SESSION['gal_success'], $_SESSION['gal_error']);

    $photosResult = $conn->query("SELECT * FROM gallery ORDER BY created_at DESC");
    $photos = [];
    if ($photosResult) {
        while ($row = $photosResult->fetch_assoc()) {
            $photos[] = $row;
        }
    }

    return [
        'targetDir' => $targetDir,
        'success'   => $success,
        'error'     => $error,
        'photos'    => $photos,
        'csrfField' => csrf_field(),
    ];
}
