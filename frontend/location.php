<?php
require_once __DIR__ . '/../backend/helpers/security_headers.php';
require_once __DIR__ . '/../backend/helpers/csrf_helper.php';
header('Cache-Control: no-store');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-circle-32.png?v=3">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/favicon-circle-180.png?v=3">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Find Santa Fe Beach Club in Pantalan, Santa Fe, Cebu and get practical arrival information for your stay.">
    <title>Location &amp; Directions - Santa Fe Beach Club</title>
    <link rel="stylesheet" href="assets/css/tailwind.css?v=<?php echo (int) filemtime(__DIR__ . '/assets/css/tailwind.css'); ?>">
    <link rel="stylesheet" href="assets/css/styles.css?v=<?php echo (int) filemtime(__DIR__ . '/assets/css/styles.css'); ?>">
    <link rel="stylesheet" href="assets/css/navbar.css?v=<?php echo (int) filemtime(__DIR__ . '/assets/css/navbar.css'); ?>">
    <link rel="stylesheet" href="assets/css/location.css?v=<?php echo (int) filemtime(__DIR__ . '/assets/css/location.css'); ?>">
    <?php require __DIR__ . '/partials/_responsive.php'; ?>
</head>
<body class="location-page">
    <?php $active_nav = 'location'; require __DIR__ . '/partials/_navbar.php'; ?>

    <main>
        <section class="location-hero" aria-labelledby="location-title">
            <div class="location-hero-content">
                <span class="location-eyebrow">Santa Fe Beach Club · Location</span>
                <h1 id="location-title">Your island stay starts in Santa Fe.</h1>
                <p>Find the resort, open turn-by-turn directions, and review simple arrival notes before your trip.</p>
            </div>
        </section>

        <section class="location-shell location-intro" aria-labelledby="find-us-title">
            <div>
                <span class="location-eyebrow">Find us</span>
                <h2 id="find-us-title">Pantalan, Santa Fe, Cebu</h2>
                <p>R54R+R5W Pantalan, Santa Fe<br>6047 Cebu, Philippines</p>
            </div>
            <a class="location-primary-button" href="https://www.google.com/maps/dir/?api=1&amp;destination=11.1671086%2C123.8054383" target="_blank" rel="noopener noreferrer">Get Directions <span aria-hidden="true">↗</span></a>
        </section>

        <section class="location-shell location-map-section" aria-labelledby="map-title">
            <div class="location-section-heading">
                <div>
                    <span class="location-eyebrow">Map</span>
                    <h2 id="map-title">See exactly where to go.</h2>
                </div>
                <div class="location-map-toggle" role="group" aria-label="Map view">
                    <button type="button" data-map-type="m" aria-pressed="false" aria-controls="resort-map">Map</button>
                    <button type="button" data-map-type="k" aria-pressed="true" aria-controls="resort-map">Satellite</button>
                </div>
            </div>
            <div class="location-map-frame">
                <iframe
                    id="resort-map"
                    title="Santa Fe Beach Club satellite map"
                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d1960.9161759603097!2d123.8054383!3d11.1671086!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x33a87d5228e5fbdd%3A0x4038cf5331855e76!2sSanta%20Fe%20Beach%20Club!5e1!3m2!1sen!2sph!4v1700000000000!5m2!1sen!2sph"
                    width="100%"
                    height="100%"
                    style="border:0"
                    allowfullscreen=""
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"></iframe>
            </div>
        </section>

        <section class="location-shell location-arrival" aria-labelledby="arrival-title">
            <div class="location-section-heading">
                <div>
                    <span class="location-eyebrow">Getting here</span>
                    <h2 id="arrival-title">Plan the last part of your trip.</h2>
                </div>
                <p>Routes and schedules can change, so confirm ferry and transport details before travelling.</p>
            </div>
            <div class="location-route-grid">
                <article class="location-route-card">
                    <span class="location-route-number">01</span>
                    <h3>From Santa Fe Port</h3>
                    <p>Continue to Pantalan by local transport. Show the resort name or open the directions link for your driver.</p>
                </article>
                <article class="location-route-card">
                    <span class="location-route-number">02</span>
                    <h3>From Bantayan Airport</h3>
                    <p>Arrange local transport to Pantalan, Santa Fe. Contact reception ahead if you need arrival assistance.</p>
                </article>
                <article class="location-route-card">
                    <span class="location-route-number">03</span>
                    <h3>From Cebu City</h3>
                    <p>Travel to Hagnaya Port, take the ferry to Santa Fe, then continue to the resort by local transport.</p>
                </article>
            </div>
        </section>

        <section class="location-shell location-details" aria-label="Arrival information">
            <article>
                <span class="location-eyebrow">Nearby reference points</span>
                <h2>Useful places to mention.</h2>
                <ul>
                    <li>Santa Fe Port</li>
                    <li>Pantalan shoreline</li>
                    <li>Santa Fe town center</li>
                </ul>
            </article>
            <article>
                <span class="location-eyebrow">Before you arrive</span>
                <h2>Need transport or parking help?</h2>
                <p>Contact reception before arrival to confirm current transport assistance and parking arrangements.</p>
                <a href="contact">Contact Reception <span aria-hidden="true">&rarr;</span></a>
            </article>
        </section>

        <section class="location-shell location-cta" aria-labelledby="location-cta-title">
            <div>
                <span class="location-eyebrow">Ready for the shore?</span>
                <h2 id="location-cta-title">Choose your room and plan your stay.</h2>
            </div>
            <a class="location-primary-button" href="rooms#room-choice">Book Your Stay <span aria-hidden="true">&rarr;</span></a>
        </section>
    </main>

    <?php require __DIR__ . '/partials/_footer.php'; ?>
    <script src="assets/js/location.js?v=<?php echo (int) filemtime(__DIR__ . '/assets/js/location.js'); ?>" defer></script>
</body>
</html>
