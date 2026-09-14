<?php
require_once __DIR__ . '/../backend/helpers/admin_auth_check.php';
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/helpers/cloudinary_helper.php';
require_once __DIR__ . '/../backend/helpers/security_logger.php';

$admin = $_SESSION['admin_username'];

$target_dir = "assets/gallery/";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_photo') {
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        $allowedExts  = ['jpg', 'jpeg', 'png', 'webp'];
        $uploaded = 0;
        $errors   = [];

        // Normalise $_FILES['photos'] into a list of individual file entries
        $files = [];
        if (isset($_FILES['photos']) && is_array($_FILES['photos']['name'])) {
            $count = count($_FILES['photos']['name']);
            for ($i = 0; $i < $count; $i++) {
                $files[] = [
                    'name'     => $_FILES['photos']['name'][$i],
                    'tmp_name' => $_FILES['photos']['tmp_name'][$i],
                    'error'    => $_FILES['photos']['error'][$i],
                    'size'     => $_FILES['photos']['size'][$i],
                ];
            }
        }

        if (empty($files)) {
            $_SESSION['gal_error'] = 'No files selected.';
        } else {
            foreach ($files as $file) {
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    $errors[] = "{$file['name']}: upload error code {$file['error']}";
                    continue;
                }

                $tmpFile  = $file['tmp_name'];
                $origName = basename($file['name']);
                $filesize = $file['size'];
                $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

                // Magic bytes MIME inspection
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

                require_once __DIR__ . '/../backend/helpers/file_scanner.php';
                $scanResult = scan_uploaded_file($tmpFile, $ext);
                if (!$scanResult['safe']) {
                    $errors[] = "$origName rejected: " . ($scanResult['reason'] ?? 'unsafe content detected');
                    SecurityLogger::log($conn, 'FILE_UPLOAD_MALICIOUS', "Rejected unsafe content in $origName: " . ($scanResult['reason'] ?? ''), SecurityLogger::LEVEL_WARNING, $admin);
                    continue;
                }

                // Try Cloudinary first, fall back to local
                $cloudResult = cloudinary_upload($tmpFile, 'sfbc_gallery');
                $savedPath   = null;

                if ($cloudResult['success'] && !empty($cloudResult['url'])) {
                    $savedPath = $cloudResult['url'];
                } else {
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
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-circle-32.png?v=3">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/favicon-circle-180.png?v=3">
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
    <div class="modal-box" style="max-width:520px;width:95%;">
        <button class="modal-close" onclick="closeGalleryModal()">×</button>
        <h3>Add Photos</h3>
        <p class="modal-sub">Drag &amp; drop or click to select multiple images (JPG, PNG, WebP — max 2 MB each).</p>
        <form method="POST" enctype="multipart/form-data" id="galleryUploadForm">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="add_photo">

            <!-- Drop Zone -->
            <div id="dropZone" style="
                border: 2px dashed #c8a97e;
                border-radius: 12px;
                padding: 36px 20px;
                text-align: center;
                cursor: pointer;
                transition: background .2s, border-color .2s;
                background: #fdf8f3;
                margin-bottom: 16px;
            ">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#c8a97e" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:10px">
                    <polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/>
                    <path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/>
                </svg>
                <p style="margin:0 0 6px;font-weight:600;color:#555;">Drop images here</p>
                <p style="margin:0;font-size:13px;color:#999;">or <span style="color:#c8a97e;text-decoration:underline;cursor:pointer;" onclick="document.getElementById('photoInput').click()">browse files</span></p>
                <input type="file" id="photoInput" name="photos[]" multiple accept="image/jpeg,image/png,image/webp" style="display:none;">
            </div>

            <!-- Preview Grid -->
            <div id="previewGrid" style="display:none;display:grid;grid-template-columns:repeat(auto-fill,minmax(80px,1fr));gap:8px;margin-bottom:16px;"></div>

            <!-- Upload count badge -->
            <p id="fileCountBadge" style="display:none;font-size:13px;color:#555;margin:0 0 14px;"></p>

            <button type="submit" class="btn-primary" id="uploadBtn" style="width:100%;justify-content:center;" disabled>
                Upload Photos
            </button>
        </form>
    </div>
</div>

<style>
#dropZone.drag-over { background:#fff3e5; border-color:#a0724f; }
#previewGrid .thumb-wrap {
    position:relative; border-radius:8px; overflow:hidden;
    aspect-ratio:1; background:#eee;
}
#previewGrid .thumb-wrap img { width:100%;height:100%;object-fit:cover; display:block; }
#previewGrid .thumb-remove {
    position:absolute;top:3px;right:3px;
    background:rgba(0,0,0,.55);color:#fff;
    border:none;border-radius:50%;width:20px;height:20px;
    font-size:12px;line-height:20px;text-align:center;
    cursor:pointer;padding:0;
}
</style>

<script>
(function(){
    const dropZone   = document.getElementById('dropZone');
    const input      = document.getElementById('photoInput');
    const grid       = document.getElementById('previewGrid');
    const badge      = document.getElementById('fileCountBadge');
    const uploadBtn  = document.getElementById('uploadBtn');
    const form       = document.getElementById('galleryUploadForm');
    const allowedTypes = ['image/jpeg','image/png','image/webp'];
    const maxBytes     = 2 * 1024 * 1024;

    let selectedFiles = []; // DataTransfer-backed list

    function syncInputFiles() {
        const dt = new DataTransfer();
        selectedFiles.forEach(f => dt.items.add(f));
        input.files = dt.files;
    }

    function renderPreviews() {
        grid.innerHTML = '';
        if (selectedFiles.length === 0) {
            grid.style.display = 'none';
            badge.style.display = 'none';
            uploadBtn.disabled = true;
            return;
        }
        grid.style.display = 'grid';
        badge.style.display = 'block';
        badge.textContent = selectedFiles.length + ' file(s) ready to upload';
        uploadBtn.disabled = false;

        selectedFiles.forEach((file, idx) => {
            const wrap = document.createElement('div');
            wrap.className = 'thumb-wrap';

            const img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            img.onload = () => URL.revokeObjectURL(img.src);

            const btn = document.createElement('button');
            btn.className = 'thumb-remove';
            btn.type = 'button';
            btn.textContent = '×';
            btn.title = 'Remove ' + file.name;
            btn.addEventListener('click', () => {
                selectedFiles.splice(idx, 1);
                syncInputFiles();
                renderPreviews();
            });

            wrap.appendChild(img);
            wrap.appendChild(btn);
            grid.appendChild(wrap);
        });
    }

    function addFiles(fileList) {
        Array.from(fileList).forEach(f => {
            if (!allowedTypes.includes(f.type)) { alert(f.name + ' is not a supported format.'); return; }
            if (f.size > maxBytes) { alert(f.name + ' exceeds the 2 MB limit.'); return; }
            selectedFiles.push(f);
        });
        syncInputFiles();
        renderPreviews();
    }

    // Click on drop zone opens picker
    dropZone.addEventListener('click', (e) => {
        if (e.target !== document.querySelector('#dropZone span')) input.click();
    });
    input.addEventListener('change', () => addFiles(input.files));

    // Drag events
    dropZone.addEventListener('dragover',  (e) => { e.preventDefault(); dropZone.classList.add('drag-over'); });
    dropZone.addEventListener('dragleave', ()  => dropZone.classList.remove('drag-over'));
    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('drag-over');
        addFiles(e.dataTransfer.files);
    });

    // Reset state when modal closes
    window.closeGalleryModal = function() {
        document.getElementById('addModal').classList.remove('open');
        selectedFiles = [];
        renderPreviews();
    };
})();
</script>
<script src="assets/js/sidebar-toggle.js"></script>
</body>
</html>
