<?php
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/helpers/security_headers.php';
require_once __DIR__ . '/../backend/helpers/csrf_helper.php';

$review_summary = $conn->query("SELECT COUNT(*) AS total, AVG(rating) AS average_rating FROM reviews WHERE is_approved = 1")->fetch_assoc();
$hero_highlights = [];
$highlight_rows = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('hero_stay_title', 'hero_stay_description', 'hero_support_title', 'hero_support_description')");
while ($highlight_row = $highlight_rows->fetch_assoc()) {
    $hero_highlights[$highlight_row['setting_key']] = $highlight_row['setting_value'];
}
$hero_feature_cards = [];
foreach (['stay', 'support'] as $highlight_type) {
    $highlight_title = trim($hero_highlights['hero_' . $highlight_type . '_title'] ?? '');
    $highlight_description = trim($hero_highlights['hero_' . $highlight_type . '_description'] ?? '');
    if ($highlight_title !== '' && $highlight_description !== '') {
        $hero_feature_cards[] = ['title' => $highlight_title, 'description' => $highlight_description];
    }
}

// Fetch custom room type primary photos from room_types
$db_photos = [];
$photo_q = @$conn->query("SELECT name, image_url FROM room_types");
if ($photo_q) {
    while ($row = $photo_q->fetch_assoc()) {
        $key = strtolower(str_replace(' ', '_', trim($row['name'])));
        if (!empty($row['image_url'])) {
            $db_photos[$key] = $row['image_url'];
        }
    }
}

// Fetch min price per room type from rooms table
$db_prices = [];
$price_q = @$conn->query("SELECT type, MIN(price_per_night) AS min_price FROM rooms WHERE price_per_night > 0 GROUP BY type");
if ($price_q) {
    while ($row = $price_q->fetch_assoc()) {
        $key = strtolower(str_replace(' ', '_', trim($row['type'])));
        $db_prices[$key] = (float)$row['min_price'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-circle-32.png?v=3">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/favicon-circle-180.png?v=3">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#7C533C">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Santa Fe Beach Club - Escape to Paradise</title>
    <link rel="stylesheet" href="assets/css/tailwind.css?v=<?php echo (int) filemtime(__DIR__ . '/assets/css/tailwind.css'); ?>">
    <link rel="stylesheet" href="assets/css/styles.css?v=<?php echo (int) filemtime(__DIR__ . '/assets/css/styles.css'); ?>">
    <link rel="stylesheet" href="assets/css/navbar.css?v=<?php echo (int) filemtime(__DIR__ . '/assets/css/navbar.css'); ?>">
    <script src="assets/js/dark-mode-toggle.js" defer></script>
    <style>
        .mobile-menu-btn {
            display: none;
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 8px;
            padding: 7px;
            color: var(--color-primary);
            cursor: pointer;
            align-items: center;
            justify-content: center;
        }
        @media (max-width: 768px) {
            .mobile-menu-btn {
                display: inline-flex;
            }
        }
    </style>
<script src="assets/js/site-dialog.js?v=1"></script>
<?php require __DIR__ . '/partials/_responsive.php'; ?>
</head>
<body class="home-page">

    <!-- Shared Header Navigation -->
    <?php $active_nav = 'home'; require __DIR__ . '/partials/_navbar.php'; ?>

<!-- Hero Banner Section -->
    <section class="hero-section">
        <div class="hero-bg-slider" aria-hidden="true">
            <div class="hero-bg-slide is-active" style="background-image: url('assets/hero-slide-1-hd.png?v=<?php echo (int)@filemtime(__DIR__ . '/assets/hero-slide-1-hd.png'); ?>');"></div>
            <div class="hero-bg-slide" style="background-image: url('assets/hero-slide-5-hd.webp?v=<?php echo (int)@filemtime(__DIR__ . '/assets/hero-slide-5-hd.webp'); ?>');"></div>
            <div class="hero-bg-slide" style="background-image: url('assets/hero-slide-6-hd.webp?v=<?php echo (int)@filemtime(__DIR__ . '/assets/hero-slide-6-hd.webp'); ?>');"></div>
            <div class="hero-bg-slide" style="background-image: url('assets/hero-slide-7-hd.webp?v=<?php echo (int)@filemtime(__DIR__ . '/assets/hero-slide-7-hd.webp'); ?>');"></div>
        </div>
        <div class="hero-content">
            <p class="hero-kicker">Luxury resort escape</p>
            <h1 class="hero-title"><?php echo __t('hero_title'); ?></h1>
            <p class="hero-subtitle"><?php echo __t('hero_sub'); ?></p>
            <?php if ((int)$review_summary['total'] > 0 || $hero_feature_cards): ?>
            <div class="hero-stats" aria-label="Highlights">
                <?php if ((int)$review_summary['total'] > 0): ?>
                <div class="hero-stat">
                    <span class="hero-stat-value"><?php echo number_format((float)$review_summary['average_rating'], 1); ?>/5</span>
                    <span class="hero-stat-label"><?php echo __t('guest_reviews'); ?></span>
                </div>
                <?php endif; ?>
                <?php foreach ($hero_feature_cards as $highlight): ?>
                <div class="hero-stat">
                    <span class="hero-stat-value"><?php echo htmlspecialchars($highlight['title'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="hero-stat-label"><?php echo htmlspecialchars($highlight['description'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="hero-buttons">
                <a href="rooms" class="btn-hero-primary">Browse Rooms</a>
            </div>
        </div>

        <!-- Scroll hint -->
        <div class="hero-scroll-hint" aria-hidden="true">
            <span class="hero-scroll-text">Scroll to explore</span>
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><polyline points="19 12 12 19 5 12"></polyline></svg>
        </div>

        <!-- Float Search Booking Card -->
        <div class="search-card-container">
            <form action="rooms" method="GET" class="search-card">
                <!-- Check-in Field -->
                <div class="search-field">
                    <label for="checkin">CHECK-IN</label>
                    <div class="input-with-icon">
                        <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                        <input type="date" id="checkin" name="checkin" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>

                <!-- Check-out Field -->
                <div class="search-field">
                    <label for="checkout">CHECK-OUT</label>
                    <div class="input-with-icon">
                        <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                        <input type="date" id="checkout" name="checkout" required value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                    </div>
                </div>

                <!-- Guests Field -->
                <div class="search-field">
                    <label for="guests">GUESTS</label>
                    <div class="input-with-icon">
                        <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                        <select id="guests" name="guests">
                            <option value="1">1 Guest</option>
                            <option value="2" selected>2 Guests</option>
                            <option value="3">3 Guests</option>
                            <option value="4">4 Guests</option>
                            <option value="5">5+ Guests</option>
                        </select>
                    </div>
                </div>

                <!-- Search Button -->
                <button type="submit" class="btn-search">
                    <svg class="search-btn-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    Search
                </button>
                <p class="booking-policy-note">
                    <span aria-hidden="true"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:-2px;"><polyline points="20 6 9 17 4 12"/></svg></span>
                    Reserve with a 50% deposit. The remaining balance is due 24 hours before arrival.
                </p>
            </form>
        </div>
    </section>

    <section class="amenities-strip" aria-label="Why stay with us">
        <div class="amenities-inner">
            <div class="amenity-item">
                <span class="amenity-icon-wrap"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 18c3-3 6-3 9 0s6 3 9 0"/><path d="M3 13c3-3 6-3 9 0s6 3 9 0"/><path d="M12 3v6"/><path d="m9 6 3-3 3 3"/></svg></span>
                <span class="amenity-text"><strong>Beachfront Setting</strong><span>Stay close to the Santa Fe shoreline</span></span>
            </div>
            <span class="amenity-divider" aria-hidden="true"></span>
            <div class="amenity-item">
                <span class="amenity-icon-wrap"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M8.5 16a6 6 0 0 1 7 0"/><path d="M12 20h.01"/></svg></span>
                <span class="amenity-text"><strong>Comfort Essentials</strong><span>Air-conditioned rooms and Wi-Fi access</span></span>
            </div>
            <span class="amenity-divider" aria-hidden="true"></span>
            <div class="amenity-item">
                <span class="amenity-icon-wrap"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/><path d="m9 12 2 2 4-4"/></svg></span>
                <span class="amenity-text"><strong>Secure Booking</strong><span>Protected checkout and verified payments</span></span>
            </div>
            <span class="amenity-divider" aria-hidden="true"></span>
            <div class="amenity-item">
                <span class="amenity-icon-wrap"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/></svg></span>
                <span class="amenity-text"><strong>Front-Desk Support</strong><span>Assistance before and during your stay</span></span>
            </div>
        </div>
    </section>
    <!-- Stays / Rooms Section -->
    <section class="stays-section py-20 px-[5%]" id="stays">
        <div class="stays-inner">
            <div class="stays-header text-center max-w-2xl mx-auto mb-14">
                <p class="section-kicker stays-kicker text-bronze uppercase tracking-[2px] text-xs font-heading font-bold mb-2">Signature Stays</p>
                <h2 class="stays-heading font-serif text-3xl md:text-4xl text-resort-dark mb-4">Choose Your Escape</h2>
                <p class="stays-subtitle font-body text-slate-600 text-base leading-relaxed">From cozy standard rooms to private beach villas, every stay is crafted for comfort and unforgettable memories.</p>
            </div>
            <div class="stays-grid grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="stay-card bg-white rounded-luxury shadow-card hover:shadow-hover transition-all duration-300 overflow-hidden flex flex-col border border-warm-gray/60 group">
                    <div class="stay-card-image-wrap h-64 overflow-hidden relative">
                        <div class="stay-card-image w-full h-full bg-cover bg-center transition-transform duration-500 group-hover:scale-105" style="background-image: url('<?php echo htmlspecialchars($db_photos['standard_room'] ?? 'assets/rooms/standard/standard-room-1.png'); ?>');"></div>
                    </div>
                    <div class="stay-card-body p-6 flex flex-col flex-1">
                        <h3>Standard Room</h3>
                        <p>A cozy retreat with all essentials, air conditioning, and garden views, perfect for couples or solo travelers.</p>
                        <div class="stay-card-footer flex items-center justify-between pt-4 border-t border-warm-gray/60">
                            <div class="stay-price">
                                <?php if (!empty($db_prices['standard_room'])): ?>
                                <span class="stay-price-from">From</span>
                                <strong>&#8369;<?php echo number_format($db_prices['standard_room']); ?> <span>/ night</span></strong>
                                <?php endif; ?>
                            </div>
                            <a href="rooms#room-standard-room" class="btn-primary !px-4 !py-2 !text-xs !rounded-lg inline-flex items-center">View Room</a>
                        </div>
                    </div>
                </div>
                <div class="stay-card bg-white rounded-luxury shadow-card hover:shadow-hover transition-all duration-300 overflow-hidden flex flex-col border border-warm-gray/60 group">
                    <div class="stay-card-image-wrap h-64 overflow-hidden relative">
                        <div class="stay-card-image w-full h-full bg-cover bg-center transition-transform duration-500 group-hover:scale-105" style="background-image: url('<?php echo htmlspecialchars($db_photos['beachview_duplex'] ?? 'assets/hero-slide-2.jpg'); ?>');"></div>
                    </div>
                    <div class="stay-card-body p-6 flex flex-col flex-1">
                        <h3>Beachview Duplex</h3>
                        <p>A spacious two-floor unit with sweeping beach views, a private terrace, and room for the whole family.</p>
                        <div class="stay-card-footer flex items-center justify-between pt-4 border-t border-warm-gray/60">
                            <div class="stay-price">
                                <?php if (!empty($db_prices['beachview_duplex'])): ?>
                                <span class="stay-price-from">From</span>
                                <strong>&#8369;<?php echo number_format($db_prices['beachview_duplex']); ?> <span>/ night</span></strong>
                                <?php endif; ?>
                            </div>
                            <a href="rooms#room-beachview-duplex" class="btn-primary !px-4 !py-2 !text-xs !rounded-lg inline-flex items-center">View Room</a>
                        </div>
                    </div>
                </div>
                <div class="stay-card bg-white rounded-luxury shadow-card hover:shadow-hover transition-all duration-300 overflow-hidden flex flex-col border border-warm-gray/60 group">
                    <div class="stay-card-image-wrap h-64 overflow-hidden relative">
                        <div class="stay-card-image w-full h-full bg-cover bg-center transition-transform duration-500 group-hover:scale-105" style="background-image: url('<?php echo htmlspecialchars($db_photos['seaview_duplex'] ?? 'assets/hero-slide-3.jpg'); ?>');"></div>
                    </div>
                    <div class="stay-card-body p-6 flex flex-col flex-1">
                        <h3>Seaview Duplex</h3>
                        <p>Wake up to ocean panoramas. This split-level duplex blends open-air living with cool interior comfort.</p>
                        <div class="stay-card-footer flex items-center justify-between pt-4 border-t border-warm-gray/60">
                            <div class="stay-price">
                                <?php if (!empty($db_prices['seaview_duplex'])): ?>
                                <span class="stay-price-from">From</span>
                                <strong>&#8369;<?php echo number_format($db_prices['seaview_duplex']); ?> <span>/ night</span></strong>
                                <?php endif; ?>
                            </div>
                            <a href="rooms#room-seaview-duplex" class="btn-primary !px-4 !py-2 !text-xs !rounded-lg inline-flex items-center">View Room</a>
                        </div>
                    </div>
                </div>
                <div class="stay-card bg-white rounded-luxury shadow-card hover:shadow-hover transition-all duration-300 overflow-hidden flex flex-col border border-warm-gray/60 group">
                    <div class="stay-card-image-wrap h-64 overflow-hidden relative">
                        <div class="stay-card-image w-full h-full bg-cover bg-center transition-transform duration-500 group-hover:scale-105" style="background-image: url('<?php echo htmlspecialchars($db_photos['beach_villa'] ?? 'assets/hero-slide-1.jpg'); ?>');"></div>
                    </div>
                    <div class="stay-card-body p-6 flex flex-col flex-1">
                        <h3>Beach Villa</h3>
                        <p>The pinnacle of resort living. A private villa steps from the shoreline, ideal for special occasions.</p>
                        <div class="stay-card-footer flex items-center justify-between pt-4 border-t border-warm-gray/60">
                            <div class="stay-price">
                                <?php if (!empty($db_prices['beach_villa'])): ?>
                                <span class="stay-price-from">From</span>
                                <strong>&#8369;<?php echo number_format($db_prices['beach_villa']); ?> <span>/ night</span></strong>
                                <?php endif; ?>
                            </div>
                            <a href="rooms#room-beach-villa" class="btn-primary !px-4 !py-2 !text-xs !rounded-lg inline-flex items-center">View Room</a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="stays-cta-row text-center mt-12">
                <a href="rooms" class="btn-secondary !inline-flex items-center gap-2">View All Room Details &rarr;</a>
            </div>
        </div>
    </section>

    <section class="experience-section py-20 px-[5%] bg-cream border-y border-warm-gray" aria-labelledby="resort-story-title">
        <div class="experience-inner max-w-[1400px] mx-auto grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
            <div class="experience-copy">
                <span class="experience-kicker text-bronze uppercase tracking-[2px] text-xs font-heading font-bold mb-2 block">Discover Santa Fe</span>
                <h2 class="experience-heading font-serif text-3xl md:text-5xl text-resort-dark mb-6 leading-tight" id="resort-story-title">A relaxed island stay near the shore.</h2>
                <p class="experience-lead font-body text-slate-600 text-base md:text-lg leading-relaxed mb-8">Santa Fe Beach Club brings together comfortable rooms, warm front-desk service, and an easy coastal setting in Pantalan, Santa Fe. Settle in, slow down, and keep the best of Bantayan Island within reach.</p>
                <div class="experience-actions flex flex-wrap gap-4 items-center">
                    <a href="location" class="btn-primary !inline-flex items-center gap-2">View Location <span aria-hidden="true">&rarr;</span></a>
                    <a href="contact" class="btn-secondary !inline-flex items-center">Contact Reception</a>
                </div>
            </div>
            <div class="experience-image-wrap relative rounded-luxury overflow-hidden shadow-card border border-warm-gray">
                <img class="experience-image w-full h-[420px] object-cover" src="assets/hero-slide-4-hd.png" alt="Coastal scenery near Santa Fe Beach Club" loading="lazy">
                <div class="experience-badge absolute bottom-6 left-6 bg-white/95 backdrop-blur-md px-4 py-2.5 rounded-xl shadow-md border border-warm-gray flex flex-col"><span class="experience-badge-value font-heading font-bold text-resort-dark text-sm">Santa Fe</span><span class="experience-badge-label text-[11px] text-bronze uppercase tracking-wider font-semibold">Bantayan Island</span></div>
            </div>
        </div>
    </section>

            <!-- Testimonials Section (100% Dynamic from Database) -->
    <?php
    $reviews_query = $conn->query("SELECT guest_name, guest_location, rating, review_text, created_at FROM reviews WHERE is_approved = 1 ORDER BY id DESC LIMIT 9");
    $reviews_list = [];
    if ($reviews_query) {
        while ($rev_row = $reviews_query->fetch_assoc()) {
            $reviews_list[] = $rev_row;
        }
    }
    ?>
    <section class="testimonials-section py-20 px-[5%]" id="guest-reviews">
        <div class="testimonials-inner">
            <div class="testimonials-heading-row flex flex-col md:flex-row md:items-end justify-between gap-6 mb-12">
                <div>
                    <p class="section-kicker testimonials-kicker text-bronze uppercase tracking-[2px] text-xs font-heading font-bold mb-2">Guest Reviews</p>
                    <h2 class="testimonials-heading font-serif text-3xl md:text-4xl text-resort-dark">What Our Guests Say</h2>
                </div>
                <button type="button" class="btn-primary !inline-flex items-center gap-2 cursor-pointer" onclick="openReviewModal()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg> Leave a Review
                </button>
            </div>

            <?php if (empty($reviews_list)): ?>
                <div class="testimonials-empty">
                    <div class="testimonials-empty-icon" aria-hidden="true"><svg width="32" height="32" viewBox="0 0 24 24" fill="currentColor" stroke="none" style="display:block;margin:0 auto;color:#C8996F;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></div>
                    <h3>No Guest Reviews Yet</h3>
                    <p>Be the first to share your experience staying at Santa Fe Beach Club!</p>
                    <button type="button" class="btn btn-primary btn-review" onclick="openReviewModal()">
                        Write the First Review
                    </button>
                </div>
            <?php else: ?>
                <div class="testimonials-grid grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($reviews_list as $rev):
                        $rating_val = max(1, min(5, (int)$rev['rating']));
                        $stars_html = str_repeat('<svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true" style="display:inline-block;vertical-align:-2px;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>', $rating_val) . str_repeat('<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true" style="display:inline-block;vertical-align:-2px;opacity:.45;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>', 5 - $rating_val);
                        $name_parts = explode(' ', trim($rev['guest_name']));
                        $initials = '';
                        foreach ($name_parts as $p) {
                            if (!empty($p)) $initials .= strtoupper(mb_substr($p, 0, 1));
                        }
                        $initials = substr($initials, 0, 2) ?: 'G';
                    ?>
                    <div class="testimonial-card bg-white rounded-luxury p-6 shadow-card border border-warm-gray/60 flex flex-col justify-between hover:shadow-hover transition-shadow duration-300">
                        <div class="testimonial-stars text-amber-500 tracking-wider text-base mb-3" aria-label="<?php echo $rating_val; ?> out of 5 stars"><?php echo $stars_html; ?></div>
                        <p class="testimonial-text font-body text-slate-700 italic text-sm leading-relaxed mb-6 flex-1">"<?php echo htmlspecialchars($rev['review_text'], ENT_QUOTES, 'UTF-8'); ?>"</p>
                        <div class="testimonial-author flex items-center gap-3 pt-4 border-t border-warm-gray/40">
                            <div class="testimonial-avatar w-10 h-10 rounded-full bg-bronze/10 text-bronze font-heading font-bold flex items-center justify-center text-sm border border-bronze/20" aria-hidden="true"><?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?></div>
                            <div>
                                <strong><?php echo htmlspecialchars($rev['guest_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                <span><?php echo htmlspecialchars($rev['guest_location'] ?: 'Verified Guest', ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php require __DIR__ . '/partials/_footer.php'; ?>

    <script>
    (function () {
        var checkinInput = document.getElementById('checkin');
        var checkoutInput = document.getElementById('checkout');
        var today = <?php echo json_encode(date('Y-m-d')); ?>;

        function nextDate(dateValue) {
            var parts = dateValue.split('-').map(Number);
            var date = new Date(parts[0], parts[1] - 1, parts[2]);
            date.setDate(date.getDate() + 1);
            return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
        }

        function syncBookingDates() {
            if (!checkinInput || !checkoutInput) return;
            checkinInput.min = today;
            var earliestCheckout = nextDate(checkinInput.value || today);
            checkoutInput.min = earliestCheckout;
            if (!checkoutInput.value || checkoutInput.value < earliestCheckout) checkoutInput.value = earliestCheckout;
        }

        if (checkinInput && checkoutInput) {
            syncBookingDates();
            checkinInput.addEventListener('change', syncBookingDates);
        }

        var heroSlides = document.querySelectorAll('.hero-bg-slide');
        if (heroSlides.length > 1) {
            var activeSlideIndex = 0;
            setInterval(function () {
                heroSlides[activeSlideIndex].classList.remove('is-active');
                activeSlideIndex = (activeSlideIndex + 1) % heroSlides.length;
                heroSlides[activeSlideIndex].classList.add('is-active');
            }, 5000);
        }

    })();
    </script>

    <!-- Guest Review Submission Modal -->
    <div id="reviewModal" style="display:none; position:fixed; z-index:9999; inset:0; background:rgba(0,0,0,0.6); backdrop-filter:blur(4px); align-items:center; justify-content:center; padding:16px;">
        <div style="background:#fff; width:100%; max-width:480px; border-radius:18px; padding:28px; box-shadow:0 20px 40px rgba(0,0,0,0.25); position:relative; animation:slideUp 0.3s ease;">
            <button type="button" onclick="closeReviewModal()" style="position:absolute; top:18px; right:18px; background:none; border:none; font-size:22px; color:#9CA3AF; cursor:pointer;">&times;</button>
            <div style="text-align:center; margin-bottom:20px;">
                <img src="assets/logo.jpg" alt="Santa Fe Beach Club Logo" style="width:72px; height:72px; border-radius:50%; object-fit:cover; border:3px solid #7C533C; box-shadow:0 4px 14px rgba(124,83,60,0.25); margin-bottom:10px;">
                <h3 style="font-size:20px; font-weight:700; color:#1F2937; margin:0;">Share Your Experience</h3>
                <p style="font-size:13px; color:#6B7280; margin-top:4px;">We would love to hear how your stay at Santa Fe Beach Club was!</p>
            </div>
            <form id="reviewSubmitForm" onsubmit="handleReviewSubmit(event)">
                <?php echo csrf_field(); ?>
                <div style="margin-bottom:14px;">
                    <label style="display:block; font-size:12px; font-weight:700; color:#374151; margin-bottom:4px;">YOUR RATING</label>
                    <div style="display:flex; gap:8px; font-size:26px; cursor:pointer; color:#D1D5DB;" id="starRatingContainer">
                        <span onclick="setRating(1)" class="star-item"><svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true" style="display:block;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></span>
                        <span onclick="setRating(2)" class="star-item"><svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true" style="display:block;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></span>
                        <span onclick="setRating(3)" class="star-item"><svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true" style="display:block;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></span>
                        <span onclick="setRating(4)" class="star-item"><svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true" style="display:block;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></span>
                        <span onclick="setRating(5)" class="star-item"><svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true" style="display:block;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></span>
                    </div>
                    <input type="hidden" name="rating" id="reviewRatingInput" value="5" required>
                </div>
                <div class="mobile-form-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px;">
                    <div>
                        <label style="display:block; font-size:12px; font-weight:700; color:#374151; margin-bottom:4px;">YOUR NAME *</label>
                        <input type="text" name="guest_name" required placeholder="e.g. Maria R." autocomplete="off" style="width:100%; padding:10px 12px; border:1px solid #D1D5DB; border-radius:8px; font-size:14px; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:12px; font-weight:700; color:#374151; margin-bottom:4px;">LOCATION</label>
                        <input type="text" name="guest_location" placeholder="e.g. Cebu, Philippines" autocomplete="off" style="width:100%; padding:10px 12px; border:1px solid #D1D5DB; border-radius:8px; font-size:14px; box-sizing:border-box;">
                    </div>
                </div>
                <div style="margin-bottom:18px;">
                    <label style="display:block; font-size:12px; font-weight:700; color:#374151; margin-bottom:4px;">YOUR REVIEW *</label>
                    <textarea name="review_text" rows="4" required placeholder="Tell future guests about the views, staff, food, or amenities..." style="width:100%; padding:10px 12px; border:1px solid #D1D5DB; border-radius:8px; font-size:14px; box-sizing:border-box; resize:vertical;"></textarea>
                </div>
                <button type="submit" id="reviewSubmitBtn" style="width:100%; padding:12px; background:#644B39; color:#fff; border:none; border-radius:10px; font-size:15px; font-weight:700; cursor:pointer;">
                    Submit Review
                </button>
            </form>
        </div>
    </div>

    <script>
        let currentRating = 5;
        function updateStarDisplay(rating) {
            const stars = document.querySelectorAll("#starRatingContainer .star-item");
            stars.forEach((s, idx) => {
                s.style.color = (idx < rating) ? "#F59E0B" : "#D1D5DB";
            });
        }
        function setRating(r) {
            currentRating = r;
            document.getElementById("reviewRatingInput").value = r;
            updateStarDisplay(r);
        }
        function openReviewModal() {
            document.getElementById("reviewModal").style.display = "flex";
            setRating(5);
        }
        function closeReviewModal() {
            document.getElementById("reviewModal").style.display = "none";
        }
        async function handleReviewSubmit(e) {
            e.preventDefault();
            const form = e.target;
            const btn = document.getElementById("reviewSubmitBtn");
            btn.disabled = true;
            btn.textContent = "Submitting...";

            const fd = new FormData(form);
            try {
                const res = await fetch("api_submit_review.php", {
                    method: "POST",
                    body: fd
                });
                const data = await res.json();
                if (res.ok && data.success === true) {
                    SiteDialog.alert("Thank you! Your review has been submitted and will appear after admin approval.");
                    location.reload();
                } else {
                    SiteDialog.alert(data.error || "Failed to submit review. Please try again.");
                }
            } catch (err) {
                SiteDialog.alert("Unable to confirm your review was submitted. Please try again later.");
            } finally {
                btn.disabled = false;
                btn.textContent = "Submit Review";
            }
        }

    </script>

</body>
</html>

