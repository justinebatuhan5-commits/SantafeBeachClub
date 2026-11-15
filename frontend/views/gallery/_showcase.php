    <main>
        <section class="gallery-hero" aria-labelledby="gallery-title">
            <div class="gallery-hero-content">
                <span class="gallery-eyebrow">Santa Fe Beach Club &middot; Gallery</span>
                <h1 id="gallery-title">Coastal moments captured along the shore.</h1>
                <p>Sunlit mornings, golden Bantayan horizons, and serene island stays across our beachfront club.</p>
            </div>
        </section>

        <section class="gallery-shell gallery-intro" aria-labelledby="gallery-collection-title">
            <div class="gallery-intro-copy">
                <span class="gallery-eyebrow">Resort &amp; Guest Moments</span>
                <div class="gallery-intro-title-row">
                    <h2 id="gallery-collection-title">Captured on Bantayan Island</h2>
                    <span class="gallery-count-pill" id="gallery-count-pill"><?php echo htmlspecialchars($countLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <p>Select any photograph to open the full-screen viewer with keyboard, touch swipe, and filmstrip navigation.</p>
            </div>
            <?php if ($photoTotal > 0): ?>
            <div class="gallery-controls">
                <div class="gallery-layout-toggle" role="group" aria-label="Gallery grid layout">
                    <button type="button" class="gallery-layout-btn" data-gallery-layout="editorial" aria-pressed="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="8" height="7" rx="1.5"/><rect x="13" y="3" width="8" height="7" rx="1.5"/><rect x="3" y="13" width="5" height="8" rx="1.5"/><rect x="10" y="13" width="4" height="8" rx="1.5"/><rect x="16" y="13" width="5" height="8" rx="1.5"/></svg>
                        Editorial
                    </button>
                    <button type="button" class="gallery-layout-btn" data-gallery-layout="uniform" aria-pressed="false">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
                        Uniform Grid
                    </button>
                </div>
                <button type="button" class="gallery-btn-primary" id="gallerySlideshowBtn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>
                    Start Viewer
                </button>
            </div>
            <?php endif; ?>
        </section>

        <section class="gallery-shell gallery-showcase" aria-label="Resort photographs">
            <?php if ($photoTotal === 0): ?>
                <div class="gallery-empty">
                    <div class="gallery-empty-icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="12" cy="12" r="3.5"/><path d="M7 5l1.2-2h7.6L17 5"/></svg>
                    </div>
                    <h3>More photographs coming soon</h3>
                    <p>We are currently curating new scenes from across the shoreline and accommodations. Browse our rooms in the meantime.</p>
                    <a class="gallery-btn-primary" href="rooms#room-choice">Browse Rooms <span aria-hidden="true">&rarr;</span></a>
                </div>
            <?php else: ?>
                <div class="photo-grid" id="photoGrid">
                    <?php foreach ($photoItems as $idx => $p):
                        $imgSrc = get_image_url($p['file_name'], 'assets/logo.jpg', 'assets/gallery/');
                        $createdTs = !empty($p['created_at']) ? strtotime($p['created_at']) : false;
                        $dateLabel = $createdTs ? date('M j, Y', $createdTs) : 'Resort Collection';
                        $shortDate = $createdTs ? date('M Y', $createdTs) : 'Collection';
                        $frameLabel = sprintf('%02d / %02d', $idx + 1, $photoTotal);

                        $spanVal = $editorialSpans[$idx] ?? 2;
                        if ($spanVal === 6) {
                            $spanClass = 'photo-card--solo';
                        } elseif ($spanVal === 3) {
                            $spanClass = 'photo-card--span3';
                        } else {
                            $spanClass = 'photo-card--span2';
                        }

                        if ($photoTotal > 1 && ($photoTotal % 2 === 1) && $idx === ($photoTotal - 1)) {
                            $spanClass .= ' photo-card--tail-wide';
                        }
                    ?>
                    <button type="button"
                            class="photo-card <?php echo htmlspecialchars($spanClass, ENT_QUOTES, 'UTF-8'); ?>"
                            data-index="<?php echo (int) $idx; ?>"
                            data-date="<?php echo htmlspecialchars($dateLabel, ENT_QUOTES, 'UTF-8'); ?>"
                            aria-label="Enlarge photograph <?php echo (int) ($idx + 1); ?> of <?php echo (int) $photoTotal; ?> (<?php echo htmlspecialchars($dateLabel, ENT_QUOTES, 'UTF-8'); ?>)"
                            aria-haspopup="dialog">
                        <img src="<?php echo htmlspecialchars($imgSrc, ENT_QUOTES, 'UTF-8'); ?>"
                             alt="Santa Fe Beach Club photograph <?php echo (int) ($idx + 1); ?>"
                             loading="<?php echo $idx < 5 ? 'eager' : 'lazy'; ?>">
                        <span class="photo-card-overlay" aria-hidden="true">
                            <span class="photo-card-meta">
                                <span class="photo-card-index"><?php echo htmlspecialchars($frameLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="photo-card-date"><?php echo htmlspecialchars($shortDate, ENT_QUOTES, 'UTF-8'); ?></span>
                            </span>
                            <span class="photo-card-expand">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>
                                View
                            </span>
                        </span>
                    </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="gallery-shell gallery-cta" aria-labelledby="gallery-cta-title">
            <div class="gallery-cta-copy">
                <span class="gallery-eyebrow">Plan Your Visit</span>
                <h2 id="gallery-cta-title">Ready to wake up by the shoreline?</h2>
                <p>Compare oceanfront villas, beachfront duplexes, and family suites with live availability and instant booking.</p>
            </div>
            <div class="gallery-cta-actions">
                <a class="gallery-btn-secondary" href="location">View Location</a>
                <a class="gallery-btn-primary" href="rooms#room-choice">Explore Rooms <span aria-hidden="true">&rarr;</span></a>
            </div>
        </section>
    </main>
