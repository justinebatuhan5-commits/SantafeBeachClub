<?php if ($photoTotal > 0): ?>
<dialog class="gallery-lightbox" aria-labelledby="lightbox-title" aria-modal="true">
    <div class="lightbox-toolbar">
        <div class="lightbox-heading">
            <span class="lightbox-kicker">Santa Fe Beach Club</span>
            <div class="lightbox-title-row">
                <h2 id="lightbox-title" class="lightbox-counter" aria-live="polite">Photo 1 of <?php echo (int) $photoTotal; ?></h2>
                <span class="lightbox-date" id="lightbox-date"></span>
            </div>
        </div>
        <button type="button" class="lightbox-close" aria-label="Close photo viewer" autofocus>
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    </div>

    <div class="lightbox-stage" id="lightboxStage">
        <button type="button" class="lightbox-nav lightbox-prev" aria-label="Previous photograph">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
        </button>
        <img class="lightbox-image" alt="">
        <button type="button" class="lightbox-nav lightbox-next" aria-label="Next photograph">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
        </button>
    </div>

    <div class="lightbox-footer">
        <div class="lightbox-thumbs" role="group" aria-label="Select photograph">
            <?php foreach ($photoItems as $idx => $p):
                $thumbSrc = get_image_url($p['file_name'], 'assets/logo.jpg', 'assets/gallery/');
            ?>
            <button type="button" class="lightbox-thumb" data-thumb-index="<?php echo (int) $idx; ?>" aria-label="View photograph <?php echo (int) ($idx + 1); ?>">
                <img src="<?php echo htmlspecialchars($thumbSrc, ENT_QUOTES, 'UTF-8'); ?>" alt="" loading="lazy">
            </button>
            <?php endforeach; ?>
        </div>
    </div>
</dialog>
<?php endif; ?>
