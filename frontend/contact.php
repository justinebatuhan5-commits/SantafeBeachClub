<?php
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/helpers/security_headers.php';
require_once __DIR__ . '/../backend/helpers/csrf_helper.php';

$message_sent = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_inquiry'])) {
    require_csrf_token();
    $guest_name = trim($_POST['guest_name'] ?? '');
    $guest_email = trim($_POST['guest_email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (empty($guest_name) || empty($guest_email) || empty($subject) || empty($message)) {
        $error = 'Please fill in all fields.';
    } else {
        $stmt = $conn->prepare("INSERT INTO inquiries (guest_name, guest_email, subject, message) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $guest_name, $guest_email, $subject, $message);
        if ($stmt->execute()) {
            $message_sent = true;
        } else {
            $error = 'An error occurred while sending your message. Please try again.';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - Santa Fe Beach Club</title>
    <meta name="csrf-token" content="<?php echo htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="icon" type="image/jpeg" href="assets/logo.jpg">
    <link rel="shortcut icon" type="image/jpeg" href="assets/logo.jpg">
    <link rel="apple-touch-icon" href="assets/logo.jpg">
    <link rel="stylesheet" href="assets/css/styles.css?v=<?php echo (int) filemtime(__DIR__ . '/assets/css/styles.css'); ?>">
    <script src="assets/js/security.js" defer></script>
    <style>
        .contact-hero {
            background: linear-gradient(rgba(15, 23, 42, 0.7), rgba(15, 23, 42, 0.7)), url('assets/hero-slide-4.jpg') center/cover no-repeat;
            padding: 120px 20px 80px;
            text-align: center;
            color: #fff;
        }
        .contact-hero h1 {
            font-size: 3rem;
            margin-bottom: 1rem;
            font-weight: 700;
        }
        .contact-hero p {
            font-size: 1.2rem;
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .contact-container {
            max-width: 1200px;
            margin: -60px auto 80px;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            position: relative;
            z-index: 10;
        }

        .contact-card {
            background: #fff;
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.08);
        }

        .contact-info-list {
            margin-top: 30px;
            display: flex;
            flex-direction: column;
            gap: 25px;
        }
        .contact-info-item {
            display: flex;
            gap: 15px;
            align-items: flex-start;
        }
        .contact-info-icon {
            width: 48px;
            height: 48px;
            background: #F0F4F8;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1E3A8A;
            flex-shrink: 0;
        }
        .contact-info-text h4 {
            margin: 0 0 5px;
            font-size: 1.1rem;
            color: #0F172A;
        }
        .contact-info-text p {
            margin: 0;
            color: #64748B;
            line-height: 1.5;
        }

        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #334155;
            font-weight: 500;
            font-size: 0.95rem;
        }
        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #CBD5E1;
            border-radius: 8px;
            font-family: inherit;
            font-size: 1rem;
            color: #0F172A;
            transition: all 0.2s;
        }
        .form-control:focus {
            outline: none;
            border-color: #3B82F6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        textarea.form-control {
            resize: vertical;
            min-height: 120px;
        }
        .btn-submit {
            background: #1E3A8A;
            color: #fff;
            border: none;
            padding: 14px 24px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: background 0.2s;
        }
        .btn-submit:hover {
            background: #1E40AF;
        }

        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-weight: 500;
        }
        .alert-success {
            background: #DEF7EC;
            color: #03543F;
            border: 1px solid #31C48D;
        }
        .alert-error {
            background: #FDE8E8;
            color: #9B1C1C;
            border: 1px solid #F8B4B4;
        }

        @media (max-width: 768px) {
            .contact-container {
                grid-template-columns: 1fr;
                margin-top: -30px;
            }
            .contact-hero h1 {
                font-size: 2.2rem;
            }
        }
    </style>
</head>
<body>

    <header class="main-header site-navbar">
        <div class="brand-logo">
            <a href="index" class="logo-link">
                <img src="assets/logo.jpg" alt="Santa Fe Beach Club logo" class="logo-mark" width="56" height="56">
            </a>
        </div>
        <nav class="nav-menu">
            <ul>
                <li><a href="index">Home</a></li>
                <li><a href="rooms">Rooms</a></li>
                <li><a href="gallery">Gallery</a></li>
                <li class="active"><a href="contact">Contact</a></li>
                <li><a href="my_booking">My Booking</a></li>
            </ul>
        </nav>
        <div class="header-action">
            <a href="rooms" class="btn-book-header">Book Now</a>
        </div>
    </header>

    <div class="contact-hero">
        <h1>Get in Touch</h1>
        <p>We're here to help you plan your perfect island getaway. Reach out to us with any questions.</p>
    </div>

    <div class="contact-container">
        <!-- Contact Info -->
        <div class="contact-card">
            <h2 style="margin-top: 0; color: #0F172A;">Contact Information</h2>
            <p style="color: #64748B;">Our reception desk is open 24/7. Feel free to contact us anytime.</p>
            
            <div class="contact-info-list">
                <div class="contact-info-item">
                    <div class="contact-info-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.51 3.36a2 2 0 0 1 1.98-2.18h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.84a16 16 0 0 0 5.25 5.25l1.12-1.12a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                    </div>
                    <div class="contact-info-text">
                        <h4>Phone Number</h4>
                        <p>+63 968 878 8960<br>(032) 421 7527</p>
                    </div>
                </div>
                
                <div class="contact-info-item">
                    <div class="contact-info-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                    </div>
                    <div class="contact-info-text">
                        <h4>Email Address</h4>
                        <p>info@santafebeachclub.com<br>bookings@santafebeachclub.com</p>
                    </div>
                </div>

                <div class="contact-info-item">
                    <div class="contact-info-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                    </div>
                    <div class="contact-info-text">
                        <h4>Property Address</h4>
                        <p>R54R+R5W Pantalan, Santa Fe<br>6047 Cebu, Philippines</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contact Form -->
        <div class="contact-card">
            <h2 style="margin-top: 0; color: #0F172A;">Send us a Message</h2>
            <p style="color: #64748B;">Fill out the form below and we'll get back to you as soon as possible.</p>

            <?php if ($message_sent): ?>
                <div class="alert alert-success">
                    Thank you for reaching out! Your message has been sent successfully. We will reply shortly.
                </div>
            <?php else: ?>
                <?php if (!empty($error)): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST" action="contact">
                    <?php echo csrf_field(); ?>
                    <div class="form-group">
                        <label for="guest_name">Full Name</label>
                        <input type="text" id="guest_name" name="guest_name" class="form-control" required placeholder="John Doe" data-validate="name" data-label="Full name" value="<?php echo htmlspecialchars($_POST['guest_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="guest_email">Email Address</label>
                        <input type="email" id="guest_email" name="guest_email" class="form-control" required placeholder="john@example.com" data-validate="email" data-label="Email address" value="<?php echo htmlspecialchars($_POST['guest_email'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="subject">Subject</label>
                        <input type="text" id="subject" name="subject" class="form-control" required placeholder="e.g. Booking Inquiry" data-label="Subject" value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="message">Message</label>
                        <textarea id="message" name="message" class="form-control" required placeholder="How can we help you?" data-label="Message"><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                    </div>
                    <button type="submit" name="submit_inquiry" class="btn-submit">Send Message</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Map Section -->
    <div style="max-width: 1200px; margin: 0 auto 80px; padding: 0 20px;">
        <div style="border-radius: 16px; overflow: hidden; height: 400px; background: #E2E8F0; position: relative;">
            <iframe 
                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d1960.9161759603097!2d123.8115!3d11.1578!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x33a8864d4715bf85%3A0xc3143f65b82df211!2sSanta%20Fe%20Beach%20Club!5e0!3m2!1sen!2sph!4v1700000000000!5m2!1sen!2sph" 
                width="100%" 
                height="100%" 
                style="border:0;" 
                allowfullscreen="" 
                loading="lazy" 
                referrerpolicy="no-referrer-when-downgrade">
            </iframe>
        </div>
    </div>

    <!-- Footer Section -->
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
</body>
</html>
