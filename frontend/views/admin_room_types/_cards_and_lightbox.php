<?php if ($roomTypesData['success'] !== ''): ?>
    <div class="alert alert-success"><?php echo $roomTypesData['success']; ?></div>
<?php endif; ?>
<?php if ($roomTypesData['error'] !== ''): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($roomTypesData['error']); ?></div>
<?php endif; ?>

<div class="rp-grid">
<?php foreach ($roomTypesData['cards'] as $card):
    $slug    = $card['slug'];
    $label   = $card['label'];
    $primary = $card['primary'];
    $display = $card['display'];
    $gallery = $card['gallery'];
?>
    <div class="rp-card">
        <div class="rp-card-header">
            <h3><?php echo htmlspecialchars($label); ?></h3>
            <span class="rp-type-badge"><?php echo htmlspecialchars($slug); ?></span>
        </div>

        <div class="rp-primary-wrap">
            <?php if ($display !== ''): ?>
                <img src="<?php echo htmlspecialchars($display); ?>" alt="<?php echo htmlspecialchars($label); ?>" class="rp-primary-img">
            <?php else: ?>
                <div style="display:flex;align-items:center;justify-content:center;height:100%;color:#9CA3AF;">
                    <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                </div>
            <?php endif; ?>
            <span class="rp-primary-badge">Primary Photo</span>
            <?php if ($primary): ?>
            <div class="rp-primary-actions">
                <form method="POST" class="js-confirm-only"
                    data-confirm-title="Reset Photo"
                    data-confirm-msg="Reset to the default photo? Your current primary photo will be removed."
                    data-confirm-icon="image"
                    data-confirm-icon-bg="#FEF3C7">
                    <?php echo $roomTypesData['csrfField']; ?>
                    <input type="hidden" name="action" value="clear_primary">
                    <input type="hidden" name="type_slug" value="<?php echo htmlspecialchars($slug); ?>">
                    <button type="submit" class="rp-btn-sm rp-btn-danger">Reset</button>
                </form>
            </div>
            <?php endif; ?>
        </div>

        <!-- Price Update -->
        <div class="rp-gallery-section" style="background:#FAFAFA; border-bottom:1px solid var(--border-color,#E5E7EB);">
            <h4>Base Price (Per Night)</h4>
            <form method="POST" class="rp-upload-row">
                <?php echo $roomTypesData['csrfField']; ?>
                <input type="hidden" name="action" value="update_price">
                <input type="hidden" name="type_slug" value="<?php echo htmlspecialchars($slug); ?>">
                <div style="display:flex; align-items:center; gap:8px; flex:1;">
                    <span style="font-weight:bold; color:#374151;">₱</span>
                    <input type="number" step="0.01" min="0" name="new_price" value="<?php echo htmlspecialchars((string)$card['price']); ?>" required class="rp-file-input" style="max-width:150px;">
                </div>
                <button type="submit" class="rp-btn-sm rp-btn-primary rp-upload-btn">Save Price</button>
            </form>
        </div>

        <!-- Upload new primary -->
        <div class="rp-gallery-section">
            <h4>Change Primary Photo</h4>
            <p style="font-size:11.5px; color:#6B7280; margin:0 0 8px;">Recommended size: <strong>800 &times; 500 px</strong> (or 1200 &times; 750 px), Aspect ratio <strong>16:10 / 16:9</strong> landscape, JPG or WebP under 300KB.</p>
            <form method="POST" enctype="multipart/form-data">
                <?php echo $roomTypesData['csrfField']; ?>
                <input type="hidden" name="action" value="set_primary">
                <input type="hidden" name="type_slug" value="<?php echo htmlspecialchars($slug); ?>">

                <div class="rp-dropzone">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    <div class="rp-dropzone-text">Drag &amp; drop photo here</div>
                    <div class="rp-dropzone-sub">or click to browse from computer</div>
                </div>

                <div class="rp-upload-row">
                    <input type="file" name="primary_photo" accept=".jpg,.jpeg,.png,.webp,.gif,image/*" class="rp-file-input">
                    <button type="submit" class="rp-btn-sm rp-btn-primary rp-upload-btn js-validate-upload">Upload Primary</button>
                </div>
            </form>
        </div>

        <!-- Gallery -->
        <div class="rp-gallery-section">
            <h4>Gallery Photos (modal slideshow)</h4>
            <div class="rp-gallery-thumbs">
                <?php if (empty($gallery)): ?>
                    <span class="rp-empty-thumb">No gallery photos yet.</span>
                <?php else: ?>
                    <?php foreach ($gallery as $gpath): ?>
                    <div class="rp-thumb-wrap">
                        <img src="<?php echo htmlspecialchars($gpath); ?>" alt="Gallery">
                        <form method="POST" class="js-confirm-only"
                            data-confirm-title="Remove Photo"
                            data-confirm-msg="Remove this gallery photo? This cannot be undone."
                            data-confirm-icon="trash"
                            data-confirm-icon-bg="#FEE2E2"
                            style="margin:0">
                            <?php echo $roomTypesData['csrfField']; ?>
                            <input type="hidden" name="action" value="remove_gallery">
                            <input type="hidden" name="type_slug" value="<?php echo htmlspecialchars($slug); ?>">
                            <input type="hidden" name="remove_path" value="<?php echo htmlspecialchars($gpath); ?>">
                            <button type="submit" class="rp-thumb-del" title="Remove">&times;</button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <?php echo $roomTypesData['csrfField']; ?>
                <input type="hidden" name="action" value="add_gallery">
                <input type="hidden" name="type_slug" value="<?php echo htmlspecialchars($slug); ?>">

                <div class="rp-dropzone">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    <div class="rp-dropzone-text">Drag &amp; drop multiple gallery photos here</div>
                    <div class="rp-dropzone-sub">or click to browse from computer (select multiple files)</div>
                </div>

                <div class="rp-upload-row">
                    <input type="file" name="gallery_photos[]" accept=".jpg,.jpeg,.png,.webp,.gif,image/*" multiple class="rp-file-input">
                    <button type="submit" class="rp-btn-sm rp-btn-secondary rp-upload-btn js-validate-upload">Upload Photos</button>
                </div>
            </form>
        </div>
    </div>
<?php endforeach; ?>
</div>

<!-- Admin Photo Lightbox -->
<div id="admin-lightbox" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.92); align-items:center; justify-content:center;">
    <button type="button" class="rp-lb-close" data-action="lightbox-close">&times;</button>
    <button type="button" id="lb-prev" class="rp-lb-nav rp-lb-prev" data-action="lightbox-prev">&#8249;</button>
    <img id="lb-img" src="" alt="" style="max-width:min(800px, calc(100vw - 130px)); max-height:calc(100vh - 120px); border-radius:10px; object-fit:contain; display:block; box-shadow:0 20px 60px rgba(0,0,0,0.6);">
    <button type="button" id="lb-next" class="rp-lb-nav rp-lb-next" data-action="lightbox-next">&#8250;</button>
    <div id="lb-caption" style="position:absolute; bottom:0; left:0; right:0; text-align:center; padding:18px; color:rgba(255,255,255,0.85); font-size:14px; letter-spacing:0.3px;"></div>
</div>
