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
    <link rel="icon" type="image/jpeg" href="assets/logo.jpg">
    <link rel="shortcut icon" type="image/jpeg" href="assets/logo.jpg">
    <link rel="apple-touch-icon" href="assets/logo.jpg">
    <link rel="stylesheet" href="assets/css/styles.css?v=<?php echo (int) filemtime(__DIR__ . '/assets/css/styles.css'); ?>">
    <style>
        .page-header {
            background: linear-gradient(rgba(15, 23, 42, 0.7), rgba(15, 23, 42, 0.7)), url('assets/hero-slide-3.jpg') center/cover;
            padding: 120px 20px 60px;
            text-align: center;
            color: white;
        }
        .page-header h1 {
            font-size: 42px;
            margin-bottom: 15px;
            font-family: 'Playfair Display', serif;
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
    </style>
</head>
<body>
    <!-- Header Navigation -->
    <header class="main-header" style="background: white; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
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
                    <div class="photo-card">
                        <img src="<?php echo htmlspecialchars(get_image_url($p['file_name'], 'assets/logo.jpg', 'assets/gallery/')); ?>" alt="Gallery Image" loading="lazy">
                    </div>
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
</body>
</html>
