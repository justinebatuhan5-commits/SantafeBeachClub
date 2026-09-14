<?php
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/helpers/cloudinary_helper.php';
$photos = $conn->query("SELECT * FROM gallery ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gallery - Santa Fe Beach Club</title>
    <link rel="icon" type="image/svg+xml" sizes="any" href="assets/icons/favicon-circle.svg?v=2">
    <link rel="shortcut icon" type="image/svg+xml" href="assets/icons/favicon-circle.svg?v=2">
    <link rel="apple-touch-icon" href="assets/icons/icon-192x192.png?v=1">
    <link rel="stylesheet" href="assets/css/styles.css?v=<?php echo (int) filemtime(__DIR__ . '/assets/css/styles.css'); ?>">
    <style>
        .gallery-page .site-navbar {
            position: fixed;
            top: 8px;
            left: 50%;
            transform: translateX(-50%);
            margin: 0;
        }
        .page-header {
            background: linear-gradient(rgba(15, 23, 42, 0.7), rgba(15, 23, 42, 0.7)), url('assets/hero-slide-3.jpg') center/cover no-repeat;
            padding: 120px 20px 60px;
            text-align: center;
            color: white;
        }
        .page-header h1 {
            font-size: 42px;
            margin-bottom: 15px;
            font-family: 'Playfair Display', serif;
        }
        @media (max-width: 768px) {
            .page-header { padding-top: 170px; }
        }
        .gallery-container {
            max-width: 1200px;
            margin: 60px auto;
            padding: 0 20px;
        }
        .photo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        .photo-card {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            transition: transform 0.3s ease;
        }
        .photo-card:hover {
            transform: translateY(-5px);
        }
        .photo-card img {
            width: 100%;
            height: 250px;
            object-fit: cover;
            display: block;
        }
        .empty-gallery {
            text-align: center;
            padding: 60px 20px;
            background: #f9fafb;
            border-radius: 12px;
            color: #6b7280;
        }
        .photo-card { padding: 0; border: 0; background: transparent; cursor: zoom-in; }
        .photo-card:focus-visible { outline: 3px solid #7c533c; outline-offset: 4px; }
        .gallery-lightbox { margin: auto; padding: 0; border: 1px solid #ffffff26; border-radius: 16px; width: min(1200px, 94vw); max-width: 94vw; max-height: 94dvh; background: #171614; color: white; box-shadow: 0 24px 80px #0008; }
        .gallery-lightbox::backdrop { background: rgba(0,0,0,.85); }
        .lightbox-toolbar { display: flex; align-items: center; justify-content: space-between; padding: 12px 18px; gap: 16px; }
        .lightbox-toolbar h2 { font-size: 16px; color: white; margin: 0; }
        .lightbox-image { display: block; width: 100%; height: min(72dvh, 850px); object-fit: contain; background: #111; }
        .gallery-lightbox button { min-width: 44px; min-height: 44px; border: 1px solid #ffffff40; border-radius: 8px; padding: 8px 14px; color: white; background: #ffffff12; cursor: pointer; font: inherit; }
        .gallery-lightbox button:hover { background: #ffffff26; }
        .gallery-lightbox button:focus-visible { outline: 2px solid #e6c5a4; outline-offset: 3px; }
        .lightbox-controls { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 12px 18px; }
        .lightbox-controls span { font-size: 13px; color: #e0d6cc; }
        @media (max-width: 400px) { .photo-grid { grid-template-columns: minmax(0, 1fr); } }
    </style>
</head>
<body class="gallery-page">
    <!-- Header Navigation -->
    <header class="main-header site-navbar">
        <div class="brand-logo">
            <a href="index" class="logo-link">
                <img src="assets/logo.jpg" alt="Santa Fe Beach Club logo" class="logo-mark" width="56" height="56">
            </a>
        </div>
        <nav class="nav-menu">
            <ul>
                <li><a href="index" style="color: var(--text-main);">Home</a></li>
                <li><a href="rooms" style="color: var(--text-main);">Rooms</a></li>
                <li class="active"><a href="gallery" style="color: var(--text-main);">Gallery</a></li>
                <li><a href="contact" style="color: var(--text-main);">Contact</a></li>
                <li><a href="my_booking" style="color: var(--text-main);">My Booking</a></li>
            </ul>
        </nav>
        <div class="header-action">
            <a href="rooms" class="btn-book-header">Book Now</a>
        </div>
    </header>

    <div class="page-header">
        <h1>Our Gallery</h1>
        <p>Explore the beauty and tranquility of Santa Fe Beach Club.</p>
    </div>

    <div class="gallery-container">
        <?php if ($photos->num_rows === 0): ?>
            <div class="empty-gallery">
                <h3>More photos coming soon!</h3>
                <p>We are currently updating our gallery with new pictures of our beautiful resort.</p>
            </div>
        <?php else: ?>
            <div class="photo-grid">
                <?php while ($p = $photos->fetch_assoc()): ?>
                    <button type="button" class="photo-card" aria-label="Enlarge gallery photo" aria-haspopup="dialog">
                        <img src="<?php echo htmlspecialchars(get_image_url($p['file_name'], 'assets/logo.jpg', 'assets/gallery/')); ?>" alt="Gallery Image" loading="lazy">
                    </button>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer Section -->
    <footer class="main-footer">
        <div class="footer-container">
            <div class="footer-brand-col">
                <h3>Santa Fe Beach Club</h3>
                <p>Experience the ultimate coastal sophistication. A serene blend of boutique hospitality and tropical elegance.</p>
            </div>
            <div class="footer-links-col">
                <h4>LEGAL</h4>
                <ul>
                    <li><a href="#">Privacy Policy</a></li>
                    <li><a href="#">Terms of Service</a></li>
                </ul>
            </div>
            <div class="footer-links-col">
                <h4>COMPANY</h4>
                <ul>
                    <li><a href="#">Careers</a></li>
                    <li><a href="#">Sustainability</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> Santa Fe Beach Club. All rights reserved.</p>
        </div>
    </footer>
<dialog class="gallery-lightbox" aria-labelledby="lightbox-title">
    <div class="lightbox-toolbar">
        <h2 id="lightbox-title">Santa Fe Beach Club</h2>
        <button type="button" class="lightbox-close" aria-label="Close photo viewer" autofocus>&times;</button>
    </div>
    <img class="lightbox-image" alt="">
    <div class="lightbox-controls">
        <button type="button" class="lightbox-prev" aria-label="Previous photo">&larr;</button>
        <span class="lightbox-counter" aria-live="polite"></span>
        <button type="button" class="lightbox-next" aria-label="Next photo">&rarr;</button>
    </div>
</dialog>
<script>
(function () {
    const cards = Array.from(document.querySelectorAll('.photo-card'));
    const viewer = document.querySelector('.gallery-lightbox');
    const image = viewer.querySelector('.lightbox-image');
    const counter = viewer.querySelector('.lightbox-counter');
    let index = 0;
    let opener = null;
    let previousOverflow = '';
    function showPhoto(next) {
        index = (next + cards.length) % cards.length;
        const source = cards[index].querySelector('img');
        image.src = source.currentSrc || source.src;
        image.alt = source.alt + ' ' + (index + 1);
        counter.textContent = (index + 1) + ' / ' + cards.length;
    }
    cards.forEach(function (card, i) {
        card.setAttribute('aria-label', 'Enlarge gallery photo ' + (i + 1));
        card.addEventListener('click', function () {
            opener = card;
            showPhoto(i);
            previousOverflow = document.body.style.overflow;
            viewer.showModal();
            document.body.style.overflow = 'hidden';
        });
    });
    viewer.querySelector('.lightbox-close').addEventListener('click', function () { viewer.close(); });
    viewer.querySelector('.lightbox-prev').addEventListener('click', function () { showPhoto(index - 1); });
    viewer.querySelector('.lightbox-next').addEventListener('click', function () { showPhoto(index + 1); });
    viewer.querySelectorAll('.lightbox-prev, .lightbox-next').forEach(function (button) { button.hidden = cards.length < 2; });
    viewer.addEventListener('keydown', function (event) {
        if (event.key === 'ArrowLeft') { event.preventDefault(); showPhoto(index - 1); }
        if (event.key === 'ArrowRight') { event.preventDefault(); showPhoto(index + 1); }
    });
    viewer.addEventListener('click', function (event) {
        const bounds = viewer.getBoundingClientRect();
        if (event.target === viewer && (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom)) viewer.close();
    });
    viewer.addEventListener('close', function () {
        document.body.style.overflow = previousOverflow;
        image.removeAttribute('src');
        if (opener) opener.focus({ preventScroll: true });
    });
})();
</script>
</body>
</html>
