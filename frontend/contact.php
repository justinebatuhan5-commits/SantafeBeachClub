<?php
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/helpers/security_headers.php';
require_once __DIR__ . '/../backend/helpers/csrf_helper.php';
require_once __DIR__ . '/../backend/helpers/inquiry_guard.php';
require_once __DIR__ . '/../backend/helpers/recaptcha_helper.php';
header('Cache-Control: no-store');

$message_sent = false;
$error = '';
$_SESSION['inquiry_tokens'] = array_filter($_SESSION['inquiry_tokens'] ?? [], static function ($issued) {
    return time() - $issued <= 7200;
});

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_inquiry'])) {
    require_csrf_token();
    $error = inquiry_validate($_POST, $_SESSION['inquiry_tokens'], time());
    if ($error === '') {
        $guest_name = trim($_POST['guest_name']);
        $guest_email = trim($_POST['guest_email']);
        $subject = trim($_POST['subject']);
        $message = trim($_POST['message']);
        unset($_SESSION['inquiry_tokens'][$_POST['inquiry_token']]);
        try {
            $token = $_POST['g-recaptcha-response'] ?? '';
            $verification = recaptcha_verify(is_string($token) ? $token : '', 'guest_inquiry', true);
            if (!$verification['success']) {
                $error = 'Security verification failed. Please try again shortly or contact us by phone.';
            } elseif (!inquiry_reserve($conn, $_SERVER['REMOTE_ADDR'] ?? 'unknown', $guest_email, $subject, $message)) {
                http_response_code(429);
                $error = 'You have sent too many messages or already sent this message. Please try again later.';
            }
        } catch (Throwable $e) {
            error_log('[Inquiry protection] ' . $e->getMessage());
            $error = 'Unable to send your message right now. Please try again later.';
        }
    }
    if ($error === '') {
        try {
            $stmt = $conn->prepare("INSERT INTO inquiries (guest_name, guest_email, subject, message) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $guest_name, $guest_email, $subject, $message);
            if ($stmt->execute()) {
                $message_sent = true;
            } else {
                $error = 'An error occurred while sending your message. Please try again.';
            }
            $stmt->close();
        } catch (Throwable $e) {
            error_log('[Inquiry submission] ' . $e->getMessage());
            $error = 'Unable to send your message right now. Please try again later.';
        }
    }
}
$inquiry_token = bin2hex(random_bytes(32));
$_SESSION['inquiry_tokens'][$inquiry_token] = time();
$_SESSION['inquiry_tokens'] = array_slice($_SESSION['inquiry_tokens'], -10, null, true);
function inquiry_value(string $field): string {
    return htmlspecialchars(is_string($_POST[$field] ?? null) ? $_POST[$field] : '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-circle-32.png?v=3">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/favicon-circle-180.png?v=3">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - Santa Fe Beach Club</title>
    <meta name="csrf-token" content="<?php echo htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="assets/css/styles.css?v=<?php echo (int) filemtime(__DIR__ . '/assets/css/styles.css'); ?>">
    <script src="assets/js/security.js" defer></script>
    <style>
        .contact-hero {
            background: linear-gradient(rgba(15, 23, 42, 0.7), rgba(15, 23, 42, 0.7)), url('assets/hero-slide-4-hd.png') center/cover no-repeat;
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
    <link rel="stylesheet" href="assets/css/contact.css?v=<?php echo (int) filemtime(__DIR__ . '/assets/css/contact.css'); ?>">
<?php require __DIR__ . '/partials/_responsive.php'; ?>
</head>
<body class="contact-page">

    <!-- ── Header ── -->
    <?php $active_nav = 'contact'; require __DIR__ . '/partials/_navbar.php'; ?>

    <div class="contact-hero">
        <span class="contact-eyebrow">Santa Fe Beach Club · Contact</span>
        <h1>Your island escape starts here.</h1>
        <p>We're here to help you plan your perfect island getaway. Reach out to us with any questions.</p>
    </div>

    <div class="contact-container">
        <!-- Contact Info -->
        <div class="contact-card contact-details">
            <span class="contact-eyebrow">We're here for you</span>
            <h2>A little closer to paradise.</h2>
            <p class="contact-intro">From planning your stay to finding your way here, our reception team is available 24/7.</p>
            
            <div class="contact-info-list">
                <div class="contact-info-item">
                    <div class="contact-info-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.51 3.36a2 2 0 0 1 1.98-2.18h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.84a16 16 0 0 0 5.25 5.25l1.12-1.12a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                    </div>
                    <div class="contact-info-text">
                        <h4>Phone Number</h4>
                        <p><a href="tel:+639688788960">+63 968 878 8960</a><br><a href="tel:+63324217527">(032) 421 7527</a></p>
                    </div>
                </div>
                
                <div class="contact-info-item">
                    <div class="contact-info-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                    </div>
                    <div class="contact-info-text">
                        <h4>Email Address</h4>
                        <p><a href="mailto:info@santafebeachclub.com">info@santafebeachclub.com</a><br><a href="mailto:bookings@santafebeachclub.com">bookings@santafebeachclub.com</a></p>
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
            <span class="contact-eyebrow">Let's plan your stay</span>
            <h2>Send us a message</h2>
            <p class="contact-intro">Tell us what you have in mind. We'll help with the details.</p>

            <?php if ($message_sent): ?>
                <div class="alert alert-success">
                    Thank you for reaching out! Your message has been sent successfully. We will reply shortly.
                </div>
            <?php else: ?>
                <?php if (!empty($error)): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST" action="contact" id="inquiry-form" data-site-key="<?php echo htmlspecialchars(RECAPTCHA_SITE_KEY, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="submit_inquiry" value="1">
                    <input type="hidden" name="inquiry_token" value="<?php echo $inquiry_token; ?>">
                    <input type="hidden" name="g-recaptcha-response" id="inquiry-captcha">
                    <div aria-hidden="true" style="position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden">
                        <label for="website">Leave this field empty</label>
                        <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                    </div>
                    <p id="inquiry-security-error" role="alert" hidden></p>
                    <noscript>Please enable JavaScript to complete the security check, or contact us by phone.</noscript>
                    <div class="form-group">
                        <label for="guest_name">Full Name</label>
                        <input type="text" id="guest_name" name="guest_name" maxlength="150" class="form-control" required placeholder="John Doe" data-validate="name" data-label="Full name" value="<?php echo inquiry_value('guest_name'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="guest_email">Email Address</label>
                        <input type="email" id="guest_email" name="guest_email" maxlength="150" class="form-control" required placeholder="john@example.com" data-validate="email" data-label="Email address" value="<?php echo inquiry_value('guest_email'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="subject">Subject</label>
                        <input type="text" id="subject" name="subject" maxlength="200" class="form-control" required placeholder="e.g. Booking Inquiry" data-label="Subject" value="<?php echo inquiry_value('subject'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="message">Message</label>
                        <textarea id="message" name="message" maxlength="5000" class="form-control" required placeholder="How can we help you?" data-label="Message"><?php echo inquiry_value('message'); ?></textarea>
                    </div>
                    <button type="submit" name="submit_inquiry" class="btn-submit">Send Message</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Map Section -->
    <div class="contact-map-section">
        <div class="contact-map-heading">
            <div><span class="contact-eyebrow">Find your way to the shore</span><h2>Meet us in Santa Fe</h2></div>
            <p>Pantalan, Santa Fe · Cebu, Philippines</p>
        </div>
        <div class="contact-map-controls">
            <div role="group" aria-label="Map view">
                <button type="button" data-map-type="m" aria-pressed="false" aria-controls="resort-map">Map</button>
                <button type="button" data-map-type="k" aria-pressed="true" aria-controls="resort-map">Satellite</button>
            </div>
            <a href="https://www.google.com/maps/dir/?api=1&amp;destination=11.1671086%2C123.8054383" target="_blank" rel="noopener noreferrer">Get Directions ↗</a>
        </div>
        <div class="contact-map-frame">
            <iframe 
                id="resort-map" title="Santa Fe Beach Club satellite map"
                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d1960.9161759603097!2d123.8054383!3d11.1671086!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x33a87d5228e5fbdd%3A0x4038cf5331855e76!2sSanta%20Fe%20Beach%20Club!5e1!3m2!1sen!2sph!4v1700000000000!5m2!1sen!2sph"
                width="100%" 
                height="100%" 
                style="border:0;" 
                allowfullscreen="" 
                loading="lazy" 
                referrerpolicy="no-referrer-when-downgrade">
            </iframe>
        </div>
    </div>

    <style>
        .contact-map-controls { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:12px; margin-bottom:16px; }
        .contact-map-controls [role="group"] { display:flex; gap:8px; }
        .contact-map-controls button, .contact-map-controls a { display:inline-flex; align-items:center; justify-content:center; min-height:44px; padding:10px 18px; border:1px solid #7c533c; border-radius:10px; background:#fffaf4; color:#5c3d2b; font:inherit; font-weight:600; cursor:pointer; text-decoration:none; }
        .contact-map-controls button[aria-pressed="true"] { background:#7c533c; color:#fff; }
        .contact-map-controls :focus-visible { outline:3px solid #0b6e8f; outline-offset:3px; }
    </style>
    <script>
        (() => {
            const frame = document.getElementById('resort-map');
            const buttons = document.querySelectorAll('[data-map-type]');
            buttons.forEach(button => button.addEventListener('click', () => {
                if (button.getAttribute('aria-pressed') === 'true') return;
                const url = new URL(frame.src);
                url.searchParams.set('pb', url.searchParams.get('pb').replace(/!5e[01]!/, button.dataset.mapType === 'k' ? '!5e1!' : '!5e0!'));
                frame.src = url.toString();
                frame.title = 'Santa Fe Beach Club ' + (button.dataset.mapType === 'k' ? 'satellite' : 'road') + ' map';
                buttons.forEach(item => item.setAttribute('aria-pressed', String(item === button)));
            }));
        })();
    </script>

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
                    <a href="https://www.instagram.com/santafebeachclub/" class="footer-social-link" aria-label="Instagram">
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
            <p>&copy; <?php echo date('Y'); ?> Santa Fe Beach Club. All rights reserved. &bull; Developed by <strong>Justine Batuhan</strong></p>
        </div>
    </footer>
<script src="https://www.google.com/recaptcha/api.js?render=<?php echo rawurlencode(RECAPTCHA_SITE_KEY); ?>" defer></script>
<script src="assets/js/inquiry.js" defer></script>
</body>
</html>
