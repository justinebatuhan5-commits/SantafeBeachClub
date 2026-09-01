<?php
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/helpers/security_headers.php';
require_once __DIR__ . '/../backend/helpers/csrf_helper.php';

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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#7C533C">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Santa Fe BC">
    <link rel="manifest" href="camera_test.html">
    <title>Santa Fe Beach Club - Escape to Paradise</title>
    <link rel="icon" type="image/jpeg" href="assets/logo.jpg">
    <link rel="shortcut icon" type="image/jpeg" href="assets/logo.jpg">
    <link rel="apple-touch-icon" href="assets/logo.jpg">
    <link rel="stylesheet" href="assets/css/styles.css?v=<?php echo (int) filemtime(__DIR__ . '/assets/css/styles.css'); ?>">
    <script src="assets/js/dark-mode-toggle.js" defer></script>
    <style>
        .chatbot-toggle {
            position: fixed;
            right: 22px;
            bottom: 22px;
            z-index: 1000;
            border: none;
            border-radius: 50%;
            padding: 0;
            width: 60px;
            height: 60px;
            overflow: hidden;
            background: #fff;
            cursor: pointer;
            box-shadow: 0 14px 28px rgba(15, 23, 42, 0.35);
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .chatbot-toggle:hover {
            transform: translateY(-1px);
            box-shadow: 0 18px 30px rgba(15, 23, 42, 0.4);
        }
        .chatbot-panel {
            position: fixed;
            right: 22px;
            bottom: 74px;
            width: 360px;
            max-width: calc(100vw - 28px);
            background: #f8fafc;
            border: 1px solid #dbe2ec;
            border-radius: 18px;
            box-shadow: 0 28px 54px rgba(15, 23, 42, 0.25);
            z-index: 1001;
            display: none;
            overflow: hidden;
        }
        .chatbot-panel.open { display: block; }
        .chatbot-header {
            padding: 12px 12px 12px 14px;
            background: linear-gradient(135deg, #7C533C 0%, #5C3D2B 100%);
            color: #fff;
            font-size: 14px;
            font-weight: 700;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .chatbot-header-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }
        .chatbot-logo {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(255,255,255,0.7);
            box-shadow: 0 4px 10px rgba(0,0,0,0.25);
        }
        .chatbot-brand-text {
            min-width: 0;
            display: flex;
            flex-direction: column;
            line-height: 1.1;
        }
        .chatbot-brand-name {
            font-size: 13px;
            font-weight: 700;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .chatbot-brand-sub {
            font-size: 10px;
            opacity: 0.88;
            font-weight: 500;
        }
        .chatbot-header-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .chatbot-new-chat {
            border: 1px solid rgba(255,255,255,0.35);
            border-radius: 6px;
            background: transparent;
            color: #fff;
            font-size: 11px;
            padding: 4px 7px;
            cursor: pointer;
        }
        .chatbot-close {
            border: none;
            background: transparent;
            color: #fff;
            font-size: 20px;
            cursor: pointer;
            line-height: 1;
        }
        .chatbot-messages {
            height: 250px;
            overflow-y: auto;
            padding: 12px;
            background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .chatbot-msg {
            max-width: 86%;
            padding: 8px 10px;
            border-radius: 10px;
            font-size: 13px;
            line-height: 1.4;
            white-space: pre-line;
        }
        .chatbot-msg.user {
            align-self: flex-end;
            background: linear-gradient(135deg, #7C533C 0%, #5C3D2B 100%);
            color: #fff;
            border-bottom-right-radius: 4px;
        }
        .chatbot-msg.bot {
            align-self: flex-start;
            background: #fff;
            color: #0f172a;
            border: 1px solid #dbe2ec;
            border-bottom-left-radius: 4px;
        }
        .chatbot-input-row {
            border-top: 1px solid #e2e8f0;
            padding: 10px;
            background: #fff;
            display: flex;
            gap: 8px;
        }
        .chatbot-quick-menu {
            padding: 8px 10px 0;
            background: #fff;
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        .chatbot-quick-btn {
            border: 1px solid #cbd5e1;
            border-radius: 16px;
            background: #f8fafc;
            color: #0f172a;
            padding: 6px 10px;
            font-size: 12px;
            cursor: pointer;
        }
        .chatbot-input {
            flex: 1;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 8px 10px;
            font-size: 13px;
            outline: none;
            background: #fff;
        }
        .chatbot-send {
            border: none;
            border-radius: 8px;
            padding: 8px 12px;
            background: linear-gradient(135deg, #7C533C 0%, #5C3D2B 100%);
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }
    </style>
</head>
<body class="home-page">

    <!-- Header Navigation -->
    <header class="main-header">
        <div class="brand-logo">
            <a href="index" class="logo-link">
                <img src="assets/logo.jpg" alt="Santa Fe Beach Club logo" class="logo-mark" width="56" height="56">
            </a>
        </div>
        <nav class="nav-menu">
            <ul>
                <li class="active"><a href="index"><?php echo __t('home'); ?></a></li>
                <li><a href="rooms"><?php echo __t('rooms'); ?></a></li>
                <li><a href="gallery"><?php echo __t('gallery'); ?></a></li>
                <li><a href="contact"><?php echo __t('contact'); ?></a></li>
                <li><a href="my_booking"><?php echo __t('my_booking'); ?></a></li>
            </ul>
        </nav>
        <div class="header-action" style="display:flex; align-items:center; gap:12px;">
            <a href="rooms" class="btn-book-header"><?php echo __t('book_now'); ?></a>
        </div>
    </header>

    <!-- Hero Banner Section -->
    <section class="hero-section">
        <div class="hero-bg-slider" aria-hidden="true">
            <div class="hero-bg-slide is-active" style="background-image: url('assets/hero-slide-1.jpg');"></div>
            <div class="hero-bg-slide" style="background-image: url('assets/hero-slide-2.jpg');"></div>
            <div class="hero-bg-slide" style="background-image: url('assets/hero-slide-3.jpg');"></div>
            <div class="hero-bg-slide" style="background-image: url('assets/hero-slide-4.jpg');"></div>
        </div>
        <div class="hero-content">
            <p class="hero-kicker">Luxury resort escape</p>
            <h1 class="hero-title"><?php echo __t('hero_title'); ?></h1>
            <p class="hero-subtitle"><?php echo __t('hero_sub'); ?></p>
            <div class="hero-stats" aria-label="Highlights">
                <div class="hero-stat">
                    <span class="hero-stat-value">4.8/5</span>
                    <span class="hero-stat-label"><?php echo __t('guest_reviews'); ?></span>
                </div>
                <div class="hero-stat">
                    <span class="hero-stat-value">Oceanfront</span>
                    <span class="hero-stat-label">Premium stay</span>
                </div>
                <div class="hero-stat">
                    <span class="hero-stat-value">24/7</span>
                    <span class="hero-stat-label">Concierge support</span>
                </div>
            </div>
            <div class="hero-buttons">
                <a href="rooms" class="btn-hero-primary"><?php echo __t('book_now'); ?></a>
                <a href="rooms" class="btn-hero-secondary"><?php echo __t('view_all_rooms'); ?></a>
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
            </form>
        </div>
    </section>





    <!-- Stays / Rooms Section -->
    <section class="stays-section" id="gallery">
        <div class="stays-inner">
            <div class="stays-header">
                <p class="section-kicker stays-kicker">Signature Stays</p>
                <h2 class="stays-heading">Choose Your Escape</h2>
                <p class="stays-subtitle">From cozy standard rooms to private beach villas â€” every stay is crafted for comfort and unforgettable memories.</p>
            </div>
            <div class="stays-grid">
                <div class="stay-card">
                    <div class="stay-card-image-wrap">
                        <div class="stay-card-image" style="background-image: url('<?php echo htmlspecialchars($db_photos['standard_room'] ?? 'assets/rooms/standard/standard-room-1.png'); ?>');"></div>
                    </div>
                    <div class="stay-card-body">
                        <h3>Standard Room</h3>
                        <p>A cozy retreat with all essentials, air conditioning, and garden views â€” perfect for couples or solo travelers.</p>
                        <div class="stay-card-footer">
                            <div class="stay-price">
                                <?php if (!empty($db_prices['standard_room'])): ?>
                                <span class="stay-price-from">From</span>
                                <strong>&#8369;<?php echo number_format($db_prices['standard_room']); ?> <span>/ night</span></strong>
                                <?php endif; ?>
                            </div>
                            <a href="rooms" class="btn-stay-book">Book Now</a>
                        </div>
                    </div>
                </div>
                <div class="stay-card">
                    <div class="stay-card-image-wrap">
                        <div class="stay-card-image" style="background-image: url('<?php echo htmlspecialchars($db_photos['beachview_duplex'] ?? 'assets/hero-slide-2.jpg'); ?>');"></div>
                    </div>
                    <div class="stay-card-body">
                        <h3>Beachview Duplex</h3>
                        <p>A spacious two-floor unit with sweeping beach views, a private terrace, and room for the whole family.</p>
                        <div class="stay-card-footer">
                            <div class="stay-price">
                                <?php if (!empty($db_prices['beachview_duplex'])): ?>
                                <span class="stay-price-from">From</span>
                                <strong>&#8369;<?php echo number_format($db_prices['beachview_duplex']); ?> <span>/ night</span></strong>
                                <?php endif; ?>
                            </div>
                            <a href="rooms" class="btn-stay-book">Book Now</a>
                        </div>
                    </div>
                </div>
                <div class="stay-card">
                    <div class="stay-card-image-wrap">
                        <div class="stay-card-image" style="background-image: url('<?php echo htmlspecialchars($db_photos['seaview_duplex'] ?? 'assets/hero-slide-3.jpg'); ?>');"></div>
                    </div>
                    <div class="stay-card-body">
                        <h3>Seaview Duplex</h3>
                        <p>Wake up to ocean panoramas. This split-level duplex blends open-air living with cool interior comfort.</p>
                        <div class="stay-card-footer">
                            <div class="stay-price">
                                <?php if (!empty($db_prices['seaview_duplex'])): ?>
                                <span class="stay-price-from">From</span>
                                <strong>&#8369;<?php echo number_format($db_prices['seaview_duplex']); ?> <span>/ night</span></strong>
                                <?php endif; ?>
                            </div>
                            <a href="rooms" class="btn-stay-book">Book Now</a>
                        </div>
                    </div>
                </div>
                <div class="stay-card">
                    <div class="stay-card-image-wrap">
                        <div class="stay-card-image" style="background-image: url('<?php echo htmlspecialchars($db_photos['beach_villa'] ?? 'assets/hero-slide-1.jpg'); ?>');"></div>
                    </div>
                    <div class="stay-card-body">
                        <h3>Beach Villa</h3>
                        <p>The pinnacle of resort living. A private villa steps from the shoreline, ideal for special occasions.</p>
                        <div class="stay-card-footer">
                            <div class="stay-price">
                                <?php if (!empty($db_prices['beach_villa'])): ?>
                                <span class="stay-price-from">From</span>
                                <strong>&#8369;<?php echo number_format($db_prices['beach_villa']); ?> <span>/ night</span></strong>
                                <?php endif; ?>
                            </div>
                            <a href="rooms" class="btn-stay-book">Book Now</a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="stays-cta-row">
                <a href="rooms" class="btn-see-all-rooms">View All Room Details &rarr;</a>
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
    <section class="testimonials-section" id="guest-reviews">
        <div class="testimonials-inner">
            <div style="display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:16px; margin-bottom:24px;">
                <div>
                    <p class="section-kicker testimonials-kicker">Guest Reviews</p>
                    <h2 class="testimonials-heading" style="margin-bottom:0;">What Our Guests Say</h2>
                </div>
                <button type="button" class="btn btn-primary" onclick="openReviewModal()" style="padding:10px 22px; font-size:14px; font-weight:600; border-radius:30px; cursor:pointer;">
                    &#9733; Leave a Review
                </button>
            </div>

            <?php if (empty($reviews_list)): ?>
                <div style="text-align:center; padding:48px 20px; background:rgba(255,255,255,0.7); border:1px dashed #D1D5DB; border-radius:16px; margin-top:20px;">
                    <div style="font-size:36px; margin-bottom:12px;">ðŸŒŸ</div>
                    <h3 style="font-size:18px; color:#4B5563; margin-bottom:6px;">No Guest Reviews Yet</h3>
                    <p style="font-size:14px; color:#6B7280; max-width:440px; margin:0 auto 20px;">Be the first to share your experience staying at Santa Fe Beach Club!</p>
                    <button type="button" class="btn btn-primary" onclick="openReviewModal()" style="padding:10px 24px; font-size:14px; border-radius:30px; cursor:pointer;">
                        Write the First Review
                    </button>
                </div>
            <?php else: ?>
                <div class="testimonials-grid">
                    <?php foreach ($reviews_list as $rev):
                        $rating_val = max(1, min(5, (int)$rev['rating']));
                        $stars_html = str_repeat('&#9733;', $rating_val) . str_repeat('&#9734;', 5 - $rating_val);
                        $name_parts = explode(' ', trim($rev['guest_name']));
                        $initials = '';
                        foreach ($name_parts as $p) {
                            if (!empty($p)) $initials .= strtoupper(mb_substr($p, 0, 1));
                        }
                        $initials = substr($initials, 0, 2) ?: 'G';
                    ?>
                    <div class="testimonial-card">
                        <div class="testimonial-stars" aria-label="<?php echo $rating_val; ?> out of 5 stars"><?php echo $stars_html; ?></div>
                        <p class="testimonial-text">"<?php echo htmlspecialchars($rev['review_text'], ENT_QUOTES, 'UTF-8'); ?>"</p>
                        <div class="testimonial-author">
                            <div class="testimonial-avatar" aria-hidden="true"><?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?></div>
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

    <!-- Gallery Strip -->
    <section class="gallery-strip" aria-label="Property photo gallery">
        <div class="gallery-grid">
            <div class="gallery-item" style="background-image: url('assets/hero-slide-1.jpg');">
                <div class="gallery-item-overlay"><span>Shoreline View</span></div>
            </div>
            <div class="gallery-item" style="background-image: url('assets/hero-slide-3.jpg');">
                <div class="gallery-item-overlay"><span>Tropical Gardens</span></div>
            </div>
            <div class="gallery-item" style="background-image: url('assets/hero-slide-4.jpg');">
                <div class="gallery-item-overlay"><span>Sunset Serenity</span></div>
            </div>
        </div>
    </section>



    <button type="button" class="chatbot-toggle" id="chatbotToggle"><img src="assets/logo.jpg" alt="Chat" style="width: 100%; height: 100%; object-fit: cover;"></button>
    <div class="chatbot-panel" id="chatbotPanel" aria-live="polite">
        <div class="chatbot-header">
            <div class="chatbot-header-brand">
                <img src="assets/logo.jpg" alt="Santa Fe Beach Club logo" class="chatbot-logo" width="34" height="34">
                <div class="chatbot-brand-text">
                    <span class="chatbot-brand-sub">Booking Assistant</span>
                </div>
            </div>
            <div class="chatbot-header-actions">
                <button type="button" class="chatbot-new-chat" id="chatbotNewChat">New chat</button>
                <button type="button" class="chatbot-close" id="chatbotClose" aria-label="Close chat">&times;</button>
            </div>
        </div>
        <div class="chatbot-messages" id="chatbotMessages"></div>
        <div class="chatbot-quick-menu" id="chatbotQuickMenu"></div>
        <div class="chatbot-input-row">
            <input type="text" id="chatbotInput" class="chatbot-input" placeholder="Type your message..." autocomplete="off">
            <button type="button" id="chatbotSend" class="chatbot-send">Send</button>
        </div>
    </div>

    <!-- Footer Section matching reference image -->
    <footer class="main-footer">
        <div class="footer-container">
            <div class="footer-brand-col">
                <h3>Santa Fe Beach Club</h3>
                <p>Experience the ultimate coastal sophistication. A serene blend of boutique hospitality and tropical elegance.</p>
                <div class="footer-social">
                    <a href="https://www.facebook.com/SantaFeBeachClub" target="_blank" rel="noopener noreferrer" class="footer-social-link" aria-label="Facebook">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path></svg>
                    </a>
                    <a href="#" class="footer-social-link" aria-label="Instagram">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg>
                    </a>
                </div>
            </div>
            <div class="footer-links-col">
                <h4>LEGAL</h4>
                <ul>
                    <li><a href="#privacy">Privacy Policy</a></li>
                    <li><a href="#terms">Terms of Service</a></li>
                </ul>
            </div>
            <div class="footer-links-col">
                <h4>COMPANY</h4>
                <ul>
                    <li><a href="#careers">Careers</a></li>
                    <li><a href="#sustainability">Sustainability</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2024 Santa Fe Beach Club. All rights reserved.</p>
        </div>
    </footer>

    <script>
    (function () {
        var heroSlides = document.querySelectorAll('.hero-bg-slide');
        if (heroSlides.length > 1) {
            var activeSlideIndex = 0;
            setInterval(function () {
                heroSlides[activeSlideIndex].classList.remove('is-active');
                activeSlideIndex = (activeSlideIndex + 1) % heroSlides.length;
                heroSlides[activeSlideIndex].classList.add('is-active');
            }, 5000);
        }

        var toggleBtn = document.getElementById('chatbotToggle');
        var closeBtn = document.getElementById('chatbotClose');
        var newChatBtn = document.getElementById('chatbotNewChat');
        var panel = document.getElementById('chatbotPanel');
        var messagesEl = document.getElementById('chatbotMessages');
        var quickMenuEl = document.getElementById('chatbotQuickMenu');
        var inputEl = document.getElementById('chatbotInput');
        var sendBtn = document.getElementById('chatbotSend');
        var greeted = false;

        function addMessage(role, text) {
            var msg = document.createElement('div');
            msg.className = 'chatbot-msg ' + role;
            msg.textContent = text;
            messagesEl.appendChild(msg);
            messagesEl.scrollTop = messagesEl.scrollHeight;
        }

        function renderQuickMenu(items) {
            quickMenuEl.innerHTML = '';
            if (!items || !items.length) {
                return;
            }

            items.forEach(function (item) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'chatbot-quick-btn';
                btn.textContent = item;
                btn.onclick = function () {
                    sendMessage(item);
                };
                quickMenuEl.appendChild(btn);
            });
        }

        function sendMessage(forcedMessage, silentUserMessage) {
            var message = forcedMessage || inputEl.value.trim();
            if (!message) {
                return;
            }

            if (!silentUserMessage) {
                addMessage('user', message);
            }
            inputEl.value = '';
            sendBtn.disabled = true;

            fetch('chatbot', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message: message })
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                var reply = data && data.reply ? data.reply : 'Sorry, I could not process that.';
                addMessage('bot', reply);
                renderQuickMenu(data && data.quick_menu ? data.quick_menu : []);
            })
            .catch(function () {
                addMessage('bot', 'Sorry, chatbot is unavailable right now.');
                renderQuickMenu([]);
            })
            .finally(function () {
                sendBtn.disabled = false;
                inputEl.focus();
            });
        }

        function startFreshChat() {
            messagesEl.innerHTML = '';
            renderQuickMenu([]);
            sendMessage('restart', true);
        }

        toggleBtn.addEventListener('click', function () {
            panel.classList.toggle('open');
            if (panel.classList.contains('open') && !greeted) {
                greeted = true;
                startFreshChat();
                inputEl.focus();
            }
        });

        closeBtn.addEventListener('click', function () {
            panel.classList.remove('open');
        });

        newChatBtn.addEventListener('click', function () {
            startFreshChat();
            inputEl.focus();
        });

        sendBtn.addEventListener('click', sendMessage);
        inputEl.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                sendMessage();
            }
        });
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
                        <span onclick="setRating(1)" class="star-item">&#9733;</span>
                        <span onclick="setRating(2)" class="star-item">&#9733;</span>
                        <span onclick="setRating(3)" class="star-item">&#9733;</span>
                        <span onclick="setRating(4)" class="star-item">&#9733;</span>
                        <span onclick="setRating(5)" class="star-item">&#9733;</span>
                    </div>
                    <input type="hidden" name="rating" id="reviewRatingInput" value="5" required>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px;">
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
                const res = await fetch("api/submit_review", {
                    method: "POST",
                    body: fd
                });
                const data = await res.json();
                if (data.success) {
                    alert("Thank you! Your review has been posted successfully.");
                    location.reload();
                } else {
                    alert(data.error || "Failed to submit review. Please try again.");
                }
            } catch (err) {
                alert("Review submitted successfully! Refreshing...");
                location.reload();
            } finally {
                btn.disabled = false;
                btn.textContent = "Submit Review";
            }
        }
    </script>

</body>
</html>

