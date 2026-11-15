        <!-- Signature Stays -->
        <section class="home-shell home-stays" id="stays" aria-labelledby="stays-heading">
            <div class="home-section-header">
                <div>
                    <span class="home-eyebrow">Signature Stays</span>
                    <h2 class="home-section-heading" id="stays-heading">Choose Your Escape</h2>
                    <p class="home-section-lead">From calm garden retreats to two-level seafront duplexes and beach villas crafted for restorative island stays.</p>
                </div>
                <a href="rooms#room-choice" class="home-btn-outline">View All Room Details <span aria-hidden="true">&rarr;</span></a>
            </div>

            <div class="home-stays-grid">
                <?php foreach ($stays_catalog as $type_key => $stay):
                    $stay_photo = $resolved_stay_photos[$type_key] ?? 'assets/hero-slide-1-hd.png';
                    $min_price = $db_prices[$type_key] ?? null;
                    $max_cap = $db_capacities[$type_key] ?? null;
                ?>
                <article class="home-stay-card">
                    <div class="home-stay-media">
                        <img class="home-stay-photo"
                             src="<?php echo htmlspecialchars($stay_photo, ENT_QUOTES, 'UTF-8'); ?>"
                             alt="<?php echo htmlspecialchars($stay['title'], ENT_QUOTES, 'UTF-8'); ?> at Santa Fe Beach Club"
                             loading="lazy">
                        <div class="home-stay-badges">
                            <span class="home-stay-wing"><?php echo htmlspecialchars($stay['wing'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php if (!empty($max_cap)): ?>
                            <span class="home-stay-capacity">Up to <?php echo (int)$max_cap; ?> <?php echo $max_cap === 1 ? 'Guest' : 'Guests'; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="home-stay-body">
                        <h3><?php echo htmlspecialchars($stay['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                        <p><?php echo htmlspecialchars($stay['desc'], ENT_QUOTES, 'UTF-8'); ?></p>

                        <div class="home-stay-tags">
                            <?php foreach ($stay['tags'] as $tag): ?>
                            <span class="home-stay-tag"><?php echo htmlspecialchars($tag, ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endforeach; ?>
                        </div>

                        <div class="home-stay-footer">
                            <div>
                                <?php if (!empty($min_price)): ?>
                                <span class="home-stay-price-label">Starting Rate</span>
                                <div class="home-stay-price-val">&#8369;<?php echo number_format($min_price); ?> <span>/ night</span></div>
                                <?php endif; ?>
                            </div>
                            <a href="<?php echo htmlspecialchars($stay['anchor'], ENT_QUOTES, 'UTF-8'); ?>" class="home-stay-link">View Room <span aria-hidden="true">&rarr;</span></a>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Discover Santa Fe - Espresso Sanctuary Band -->
        <section class="home-story-band" aria-labelledby="resort-story-title">
            <div class="home-shell home-story-grid">
                <div>
                    <span class="home-eyebrow">Discover Santa Fe</span>
                    <h2 id="resort-story-title">A serene island stay beside the shore.</h2>
                    <p class="home-story-lead">Santa Fe Beach Club brings together breezy coastal architecture, attentive reception hospitality, and a peaceful beachfront address in Pantalan, Santa Fe. Slow down, wake to sea air, and explore Bantayan Island at your own pace.</p>

                    <div class="home-story-facts">
                        <div class="home-story-fact">
                            <span>Check-in</span>
                            <strong>From <?php echo htmlspecialchars($checkin_display, ENT_QUOTES, 'UTF-8'); ?></strong>
                        </div>
                        <div class="home-story-fact">
                            <span>Check-out</span>
                            <strong>Until <?php echo htmlspecialchars($checkout_display, ENT_QUOTES, 'UTF-8'); ?></strong>
                        </div>
                        <div class="home-story-fact">
                            <span>Address</span>
                            <strong>Pantalan, Santa Fe</strong>
                        </div>
                    </div>

                    <div class="home-story-actions">
                        <a href="location" class="home-btn-champagne">Location &amp; Directions <span aria-hidden="true">&rarr;</span></a>
                        <a href="contact" class="home-btn-dark-outline">Contact Reception</a>
                    </div>
                </div>

                <div class="home-story-media">
                    <img src="assets/hero-slide-4-hd.png?v=<?php echo (int)@filemtime(__DIR__ . '/../../assets/hero-slide-4-hd.png'); ?>"
                         alt="Coconut palms and traditional outrigger boats along the Santa Fe shoreline"
                         loading="lazy">
                    <div class="home-story-caption">
                        <div>
                            <strong>Pantalan Shoreline</strong>
                            <span>Santa Fe &middot; Bantayan Island, Cebu</span>
                        </div>
                        <span aria-hidden="true">&rarr;</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Island Moments Gallery Preview -->
        <section class="home-shell home-gallery-preview" aria-labelledby="moments-heading">
            <div class="home-section-header">
                <div>
                    <span class="home-eyebrow">Resort &amp; Guest Moments</span>
                    <h2 class="home-section-heading" id="moments-heading">Captured Along the Shore</h2>
                    <p class="home-section-lead">Glimpses of golden horizons, calm coastal mornings, and our shoreline club in Santa Fe.</p>
                </div>
                <a href="gallery" class="home-btn-outline">Open Full Gallery <span aria-hidden="true">&rarr;</span></a>
            </div>

            <div class="home-gallery-strip">
                <?php foreach ($gallery_preview as $tile): ?>
                <a href="gallery" class="home-gallery-tile" aria-label="Open gallery: <?php echo htmlspecialchars($tile['label'], ENT_QUOTES, 'UTF-8'); ?>">
                    <img src="<?php echo htmlspecialchars($tile['src'], ENT_QUOTES, 'UTF-8'); ?>"
                         alt="<?php echo htmlspecialchars($tile['alt'], ENT_QUOTES, 'UTF-8'); ?>"
                         decoding="async">
                    <span class="home-gallery-label">
                        <span><?php echo htmlspecialchars($tile['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <span aria-hidden="true">&rarr;</span>
                    </span>
                </a>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Guest Reviews -->
        <section class="home-shell home-reviews" id="guest-reviews" aria-labelledby="reviews-heading">
            <div class="home-section-header">
                <div>
                    <span class="home-eyebrow">Guest Reviews</span>
                    <h2 class="home-section-heading" id="reviews-heading">What Our Guests Say</h2>
                </div>
                <button type="button" class="home-btn-primary" onclick="openReviewModal()">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    Leave a Review
                </button>
            </div>

            <?php if (empty($reviews_list)): ?>
                <div class="home-reviews-empty">
                    <div class="home-reviews-empty-icon" aria-hidden="true">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" stroke="none"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    </div>
                    <h3>No Guest Reviews Yet</h3>
                    <p>Be the first to share your stay experience at Santa Fe Beach Club.</p>
                    <button type="button" class="home-btn-primary" onclick="openReviewModal()">Write the First Review</button>
                </div>
            <?php else: ?>
                <div class="home-reviews-grid">
                    <?php foreach ($reviews_list as $rev):
                        $rating_val = max(1, min(5, (int)$rev['rating']));
                        $stars_html = str_repeat('<svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>', $rating_val)
                            . str_repeat('<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true" style="opacity:.4;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>', 5 - $rating_val);
                        $name_parts = explode(' ', trim($rev['guest_name']));
                        $initials = '';
                        foreach ($name_parts as $p) {
                            if (!empty($p)) {
                                $initials .= strtoupper(mb_substr($p, 0, 1));
                            }
                        }
                        $initials = substr($initials, 0, 2) ?: 'G';
                    ?>
                    <article class="home-review-card">
                        <div>
                            <div class="home-review-stars" aria-label="<?php echo (int)$rating_val; ?> out of 5 stars"><?php echo $stars_html; ?></div>
                            <p class="home-review-quote">&ldquo;<?php echo htmlspecialchars($rev['review_text'], ENT_QUOTES, 'UTF-8'); ?>&rdquo;</p>
                        </div>
                        <div class="home-review-author">
                            <div class="home-review-avatar" aria-hidden="true"><?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?></div>
                            <div>
                                <strong><?php echo htmlspecialchars($rev['guest_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                <span><?php echo htmlspecialchars($rev['guest_location'] ?: 'Verified Guest', ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- Closing Reservation CTA -->
        <section class="home-shell home-cta" aria-labelledby="home-cta-title">
            <div>
                <span class="home-eyebrow">Plan Your Escape</span>
                <h2 id="home-cta-title">Ready to wake up beside Pantalan Beach?</h2>
                <p>Compare room categories, check real-time availability, or view your active reservation.</p>
            </div>
            <div class="home-cta-actions">
                <a href="rooms#room-choice" class="home-btn-primary">Choose Your Room <span aria-hidden="true">&rarr;</span></a>
                <a href="my_booking" class="home-btn-secondary">Manage Booking</a>
            </div>
        </section>
