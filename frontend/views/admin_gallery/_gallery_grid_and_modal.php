<?php
$targetDir = $galleryData['targetDir'];
$success   = $galleryData['success'];
$error     = $galleryData['error'];
$photos    = $galleryData['photos'];
$csrfField = $galleryData['csrfField'];
?>
<?php if ($success): ?>
<div class="alert alert-success"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-error"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/></svg><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if (empty($photos)): ?>
<div class="admin-card">
    <div class="empty-state">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
        <p>No photos yet. Click <strong>Add Photo</strong> to upload one.</p>
    </div>
</div>
<?php else: ?>
<div class="responsive-stack gallery-grid">
    <?php foreach ($photos as $p): ?>
    <div class="admin-card gallery-card">
        <div class="gallery-thumb-box">
            <img src="<?php echo htmlspecialchars(get_image_url($p['file_name'], 'assets/logo.jpg', $targetDir)); ?>" class="gallery-thumb-img" alt="Gallery Photo">
        </div>
        <div class="gallery-card-footer">
            <div class="gallery-card-date">
                Added <?php echo date('M j, Y', strtotime($p['created_at'])); ?>
            </div>
            <form method="POST" class="js-confirm-only" data-confirm-title="Delete Photo" data-confirm-msg="This photo will be permanently deleted. This cannot be undone.">
                <?php echo $csrfField; ?>
                <input type="hidden" name="action" value="delete_photo">
                <input type="hidden" name="photo_id" value="<?php echo (int)$p['id']; ?>">
                <button type="submit" class="btn-danger gallery-delete-btn">Delete</button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="modal-overlay" id="addModal">
    <div class="modal-box gallery-modal-box">
        <button class="modal-close" type="button" data-close-gallery-modal>×</button>
        <h3>Add Photos</h3>
        <p class="modal-sub">Drag &amp; drop or click to select multiple images (JPG, PNG, WebP — max 2 MB each).</p>
        <form method="POST" enctype="multipart/form-data" id="galleryUploadForm">
            <?php echo $csrfField; ?>
            <input type="hidden" name="action" value="add_photo">

            <div id="dropZone" class="gallery-drop-zone">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#c8a97e" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="gallery-drop-icon">
                    <polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/>
                    <path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/>
                </svg>
                <p class="gallery-drop-title">Drop images here</p>
                <p class="gallery-drop-sub">or <span class="gallery-browse-link" data-browse-gallery>browse files</span></p>
                <input type="file" id="photoInput" name="photos[]" multiple accept="image/jpeg,image/png,image/webp" class="gallery-file-input">
            </div>

            <div id="previewGrid" class="gallery-preview-grid"></div>
            <p id="fileCountBadge" class="gallery-file-badge"></p>

            <button type="submit" class="btn-primary gallery-upload-btn" id="uploadBtn" disabled>
                Upload Photos
            </button>
        </form>
    </div>
</div>
