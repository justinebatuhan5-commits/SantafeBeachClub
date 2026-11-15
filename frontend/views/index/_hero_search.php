        <!-- Hero Stage -->
        <section class="home-hero" aria-labelledby="home-hero-title">
            <div class="hero-bg-slider" aria-hidden="true">
                <div class="hero-bg-slide is-active" data-caption="Beachfront Club at Dusk" style="background-image: url('assets/hero-slide-1-hd.png?v=<?php echo (int)@filemtime(__DIR__ . '/../../assets/hero-slide-1-hd.png'); ?>');"></div>
                <div class="hero-bg-slide" data-caption="Turquoise Bantayan Waters" style="background-image: url('assets/hero-slide-5-hd.webp?v=<?php echo (int)@filemtime(__DIR__ . '/../../assets/hero-slide-5-hd.webp'); ?>');"></div>
                <div class="hero-bg-slide" data-caption="Sunset Shoreline Horizon" style="background-image: url('assets/hero-slide-6-hd.webp?v=<?php echo (int)@filemtime(__DIR__ . '/../../assets/hero-slide-6-hd.webp'); ?>');"></div>
                <div class="hero-bg-slide" data-caption="Morning on Pantalan Beach" style="background-image: url('assets/hero-slide-7-hd.webp?v=<?php echo (int)@filemtime(__DIR__ . '/../../assets/hero-slide-7-hd.webp'); ?>');"></div>
            </div>

            <div class="home-hero-inner">
                <div class="home-hero-copy">
                    <span class="home-eyebrow">Santa Fe Beach Club &middot; Bantayan Island</span>
                    <h1 class="home-hero-title" id="home-hero-title"><?php echo __t('hero_title'); ?></h1>
                    <p class="home-hero-subtitle"><?php echo __t('hero_sub'); ?></p>

                    <?php if ((int)$review_summary['total'] > 0 || $hero_feature_cards): ?>
                    <div class="home-hero-metrics" aria-label="Highlights">
                        <?php if ((int)$review_summary['total'] > 0): ?>
                        <div class="home-hero-metric">
                            <strong><?php echo number_format((float)$review_summary['average_rating'], 1); ?> / 5.0</strong>
                            <span><?php echo htmlspecialchars(__t('guest_reviews'), ENT_QUOTES, 'UTF-8'); ?> (<?php echo (int)$review_summary['total']; ?>)</span>
                        </div>
                        <?php endif; ?>
                        <?php foreach ($hero_feature_cards as $highlight): ?>
                        <div class="home-hero-metric">
                            <strong><?php echo htmlspecialchars($highlight['title'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            <span><?php echo htmlspecialchars($highlight['description'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <div class="home-hero-actions">
                        <a href="rooms#room-choice" class="home-btn-primary">Browse Accommodations <span aria-hidden="true">&rarr;</span></a>
                        <a href="gallery" class="home-btn-hero-ghost">Explore Gallery</a>
                    </div>
                </div>

                <div class="home-hero-pager" aria-label="Featured photography slides">
                    <span class="home-hero-slide-caption" id="homeHeroCaption">Beachfront Club at Dusk</span>
                    <div class="home-slide-dots" role="tablist" aria-label="Switch hero scene">
                        <button type="button" class="home-slide-dot is-active" data-slide-target="0" aria-label="View scene 1: Beachfront Club at Dusk" aria-pressed="true">01</button>
                        <button type="button" class="home-slide-dot" data-slide-target="1" aria-label="View scene 2: Turquoise Bantayan Waters" aria-pressed="false">02</button>
                        <button type="button" class="home-slide-dot" data-slide-target="2" aria-label="View scene 3: Sunset Shoreline Horizon" aria-pressed="false">03</button>
                        <button type="button" class="home-slide-dot" data-slide-target="3" aria-label="View scene 4: Morning on Pantalan Beach" aria-pressed="false">04</button>
                    </div>
                </div>
            </div>
        </section>

        <!-- Floating Availability Search Panel -->
        <section class="home-shell home-search-shell" aria-label="Check accommodation availability">
            <div class="home-search-panel">
                <form action="rooms" method="GET" class="home-search-form">
                    <div class="home-search-grid">
                        <div class="home-search-field">
                            <label for="checkin">Check-in</label>
                            <div class="home-input-wrap">
                                <svg class="home-input-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                                <input type="date" id="checkin" name="checkin" required value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>

                        <div class="home-search-field">
                            <label for="checkout">Check-out</label>
                            <div class="home-input-wrap">
                                <svg class="home-input-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                                <input type="date" id="checkout" name="checkout" required value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                            </div>
                        </div>

                        <div class="home-search-field">
                            <label for="guests">Guests</label>
                            <div class="home-input-wrap">
                                <svg class="home-input-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                                <select id="guests" name="guests">
                                    <option value="1">1 Guest</option>
                                    <option value="2" selected>2 Guests</option>
                                    <option value="3">3 Guests</option>
                                    <option value="4">4 Guests</option>
                                    <option value="5">5+ Guests</option>
                                </select>
                            </div>
                        </div>

                        <div class="home-search-field">
                            <label for="room_type">Accommodation</label>
                            <div class="home-input-wrap">
                                <svg class="home-input-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                                <select id="room_type" name="room_type">
                                    <option value="any" selected>All Stays</option>
                                    <option value="standard_room">Standard Room</option>
                                    <option value="standard_king">Standard Family Room</option>
                                    <option value="beachview_duplex">Beachview Duplex</option>
                                    <option value="seaview_duplex">Seaview Duplex</option>
                                    <option value="beach_villa">Beach Villa</option>
                                </select>
                            </div>
                        </div>

                        <button type="submit" class="btn-search home-search-btn">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                            Check Availability
                        </button>
                    </div>

                    <div class="home-search-meta">
                        <p class="home-policy-note">
                            <span class="home-policy-check" aria-hidden="true">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            </span>
                            Reserve with a 50% deposit. Remaining balance is due 24 hours before arrival.
                        </p>
                        <a href="my_booking" class="home-search-lookup">Already booked? Manage Booking <span aria-hidden="true">&rarr;</span></a>
                    </div>
                </form>
            </div>
        </section>

        <!-- Coastal Hospitality Pillars -->
        <section class="home-shell home-pillars" aria-label="Why stay with us">
            <div class="home-pillars-grid">
                <article class="home-pillar-card">
                    <span class="home-pillar-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 18c3-3 6-3 9 0s6 3 9 0"/><path d="M3 13c3-3 6-3 9 0s6 3 9 0"/><path d="M12 3v6"/><path d="m9 6 3-3 3 3"/></svg>
                    </span>
                    <div>
                        <h3>Beachfront Setting</h3>
                        <p>Steps from the sugar-white shoreline of Pantalan, Santa Fe.</p>
                    </div>
                </article>
                <article class="home-pillar-card">
                    <span class="home-pillar-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M8.5 16a6 6 0 0 1 7 0"/><path d="M12 20h.01"/></svg>
                    </span>
                    <div>
                        <h3>Comfort Essentials</h3>
                        <p>Air-conditioned rooms, crisp linens, and steady Wi-Fi access.</p>
                    </div>
                </article>
                <article class="home-pillar-card">
                    <span class="home-pillar-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/><path d="m9 12 2 2 4-4"/></svg>
                    </span>
                    <div>
                        <h3>Protected Checkout</h3>
                        <p>Transparent rates and verified instant or manual payment options.</p>
                    </div>
                </article>
                <article class="home-pillar-card">
                    <span class="home-pillar-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/></svg>
                    </span>
                    <div>
                        <h3>Island Front Desk</h3>
                        <p>Dedicated reception team for transfers, requests, and local help.</p>
                    </div>
                </article>
            </div>
        </section>
