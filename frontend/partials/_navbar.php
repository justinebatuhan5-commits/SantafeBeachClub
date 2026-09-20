<?php
/**
 * partials/_navbar.php
 * Shared site navigation bar for all public-facing guest pages.
 *
 * Usage:
 *   <?php $active_nav = 'home'; require __DIR__ . '/partials/_navbar.php'; ?>
 *
 * Set $active_nav to one of: 'home' | 'rooms' | 'gallery' | 'contact' | 'my_booking'
 * before including this file to highlight the current page link.
 */
$active_nav = $active_nav ?? '';
?>
<header class="main-header site-navbar">
    <div class="brand-logo">
        <a href="index" class="logo-link">
            <img src="assets/logo.jpg" alt="Santa Fe Beach Club logo" class="logo-mark" width="56" height="56">
        </a>
    </div>
    <nav class="nav-menu" aria-label="Main navigation">
        <ul>
            <li<?php echo $active_nav === 'home'       ? ' class="active"' : ''; ?>><a href="index">Home</a></li>
            <li<?php echo $active_nav === 'rooms'      ? ' class="active"' : ''; ?>><a href="rooms">Rooms</a></li>
            <li<?php echo $active_nav === 'gallery'    ? ' class="active"' : ''; ?>><a href="gallery">Gallery</a></li>
            <li<?php echo $active_nav === 'contact'    ? ' class="active"' : ''; ?>><a href="contact">Contact</a></li>
            <li<?php echo $active_nav === 'my_booking' ? ' class="active"' : ''; ?>><a href="my_booking">My Booking</a></li>
        </ul>
    </nav>
    <div class="header-action">
        <a href="rooms" class="btn-book-header">Book Now</a>
    </div>
</header>
