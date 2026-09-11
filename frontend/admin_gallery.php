<?php
require_once __DIR__ . '/../backend/helpers/admin_auth_check.php';
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/helpers/cloudinary_helper.php';

$admin = $_SESSION['admin_username'];

$target_dir = "assets/gallery/";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_photo') {
        if (isset($_FILES["photo"]) && $_FILES["photo"]["error"] === UPLOAD_ERR_OK) {
            $tmpFile = $_FILES["photo"]["tmp_name"];
            $origName = basename($_FILES["photo"]["name"]);
            $filesize = $_FILES["photo"]["size"];

            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
            $allowedExts  = ['jpg', 'jpeg', 'png', 'webp'];

            // 1. Magic bytes MIME inspection
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $realMime = finfo_file($finfo, $tmpFile);
            finfo_close($finfo);

            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

            if (!in_array($ext, $allowedExts, true) || !in_array($realMime, $allowedMimes, true)) {
                $_SESSION['gal_error'] = "Error: Invalid image format. Allowed formats: JPG, PNG, WebP.";
                SecurityLogger::log($conn, 'FILE_UPLOAD_REJECTED', "Rejected invalid mime ($realMime) for $origName", SecurityLogger::LEVEL_WARNING, $admin);
            } elseif ($filesize > 5 * 1024 * 1024) {
                $_SESSION['gal_error'] = "Error: File exceeds maximum allowed size of 5MB.";
            } else {
                // Try Cloudinary first for permanent cloud storage
                $cloudResult = cloudinary_upload($tmpFile, 'sfbc_gallery');
                $savedPath = null;

                if ($cloudResult['success'] && !empty($cloudResult['url'])) {
                    $savedPath = $cloudResult['url'];
                } else {
                    // Fallback to local storage if Cloudinary fails or is unreachable
                    $new_filename = 'gal_' . bin2hex(random_bytes(16)) . '.' . $ext;
                    if (!is_dir($target_dir)) {
                        @mkdir($target_dir, 0755, true);
                    }
                    if (move_uploaded_file($tmpFile, $target_dir . $new_filename)) {
                        $savedPath = $new_filename;
                    }
                }

                if ($savedPath !== null) {
                    $stmt = $conn->prepare("INSERT INTO gallery (file_name) VALUES (?)");
                    $stmt->bind_param("s", $savedPath);
                    $stmt->execute();
                    $stmt->close();
                    log_activity($conn, $admin, 'Gallery Photo Added', "Added: $origName");
                    SecurityLogger::log($conn, 'FILE_UPLOADED', "Uploaded gallery image: $savedPath", SecurityLogger::LEVEL_INFO, $admin);
                    $_SESSION['gal_success'] = "Photo uploaded successfully.";
                } else {
                    $errDetail = $cloudResult['error'] ?? 'Local upload failed';
                    $_SESSION['gal_error'] = "File upload failed: " . htmlspecialchars($errDetail);
                }
            }
        } else {
            $_SESSION['gal_error'] = "Error: " . ($_FILES["photo"]["error"] ?? 'No file selected.');
        }
    }

    if ($action === 'delete_photo') {
        $photo_id = (int)($_POST['photo_id'] ?? 0);
        $stmt = $conn->prepare("SELECT file_name FROM gallery WHERE id = ?");
        $stmt->bind_param("i", $photo_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row) {
            $file_path = $target_dir . basename($row['file_name']);
            if (file_exists($file_path)) {
                @unlink($file_path);
            }
            $d_stmt = $conn->prepare("DELETE FROM gallery WHERE id = ?");
            $d_stmt->bind_param("i", $photo_id);
            $d_stmt->execute();
            $d_stmt->close();
            log_activity($conn, $admin, 'Gallery Photo Deleted', "Removed: {$row['file_name']}");
            SecurityLogger::log($conn, 'GALLERY_PHOTO_DELETED', "Deleted photo ID {$photo_id}", SecurityLogger::LEVEL_INFO, $admin);
            $_SESSION['gal_success'] = "Photo deleted.";
        }
    }

    header('Location: admin_gallery');
    exit;
}

$success = $_SESSION['gal_success'] ?? '';
$error   = $_SESSION['gal_error']   ?? '';
unset($_SESSION['gal_success'], $_SESSION['gal_error']);

$photos = $conn->query("SELECT * FROM gallery ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/jpeg" href="assets/logo.jpg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gallery — Santa Fe Beach Club</title>
    <link rel="stylesheet" href="assets/css/admin.css?v=4">
</head>
<body>
    <?php $active_page = 'gallery'; include __DIR__ . '/partials/_sidebar.php'; ?>

    <main class="main-content">
        <?php
        $page_title = 'Gallery';
        $page_subtitle = 'Manage photos showcased on the public gallery page.';
        $header_extra_html = '
            <button class="btn-primary" onclick="document.getElementById(\'addModal\').classList.add(\'open\')" style="padding: 8px 16px; display: flex; align-items: center; gap: 8px;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Add Photo
            </button>
        ';
        include __DIR__ . '/partials/_page_header.php';
        ?>


        <?php if ($success): ?><div class="alert alert-success"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
        <?php if ($error):   ?><div class="alert alert-error"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/></svg><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <?php if ($photos->num_rows === 0): ?>
        <div class="admin-card"><div class="empty-state">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
            <p>No photos yet. Click <strong>Add Photo</strong> to upload one.</p>
        </div></div>
        <?php else: ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:20px;">
        <?php while ($p = $photos->fetch_assoc()): ?>
        <div class="admin-card" style="padding:0;overflow:hidden;">
            <div style="height:200px;background:#f3f4f6;">
                <img src="<?php echo htmlspecialchars(get_image_url($p['file_name'], 'assets/logo.jpg', $target_dir)); ?>" style="width:100%;height:100%;object-fit:cover;" alt="Gallery Photo">
            </div>
            <div style="padding:15px;display:flex;justify-content:space-between;align-items:center;">
                <div style="font-size:12px;color:var(--text-muted);">
                    Added <?php echo date('M j, Y', strtotime($p['created_at'])); ?>
                </div>
                <form method="POST" onsubmit="return false;" data-confirm-title="Delete Photo" data-confirm-msg="This photo will be permanently deleted. This cannot be undone.">
                    <input type="hidden" name="action" value="delete_photo">
                    <input type="hidden" name="photo_id" value="<?php echo $p['id']; ?>">
                    <button type="submit" class="btn-danger" style="padding:4px 8px;font-size:12px;">Delete</button>
                </form>
            </div>
        </div>
        <?php endwhile; ?>
        </div>
        <?php endif; ?>
    </main>

<!-- Add Photo Modal -->
<div class="modal-overlay" id="addModal">
    <div class="modal-box">
        <button class="modal-close" onclick="document.getElementById('addModal').classList.remove('open')">×</button>
        <h3>Add Photo</h3>
        <p class="modal-sub">Upload a new image to the gallery.</p>
        <form method="POST" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="add_photo">
            <div class="admin-form-group">
                <label>Select Image (JPG, PNG, WebP &mdash; Max 5MB)</label>
                <input type="file" name="photo" required accept="image/jpeg,image/png,image/webp" data-max-size="5" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;">
            </div>
            <button type="submit" class="btn-primary" style="width:100%;justify-content:center;">Upload Photo</button>
        </form>
    </div>
</div>
<script src="assets/js/sidebar-toggle.js"></script>
</body>
</html>
