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
$nav_base = $nav_base ?? '';
?>
<header class="main-header site-navbar">
    <div class="brand-logo">
        <a href="<?php echo htmlspecialchars($nav_base . 'index', ENT_QUOTES, 'UTF-8'); ?>" class="logo-link">
            <img src="<?php echo htmlspecialchars($nav_base . 'assets/logo.jpg', ENT_QUOTES, 'UTF-8'); ?>" alt="Santa Fe Beach Club logo" class="logo-mark" width="56" height="56">
        </a>
    </div>
    <nav class="nav-menu" id="siteNavigation" aria-label="Main navigation">
        <ul>
            <li<?php echo $active_nav === 'home'       ? ' class="active"' : ''; ?>><a href="<?php echo htmlspecialchars($nav_base . 'index', ENT_QUOTES, 'UTF-8'); ?>">Home</a></li>
            <li<?php echo $active_nav === 'rooms'      ? ' class="active"' : ''; ?>><a href="<?php echo htmlspecialchars($nav_base . 'rooms', ENT_QUOTES, 'UTF-8'); ?>">Rooms</a></li>
            <li<?php echo $active_nav === 'gallery'    ? ' class="active"' : ''; ?>><a href="<?php echo htmlspecialchars($nav_base . 'gallery', ENT_QUOTES, 'UTF-8'); ?>">Gallery</a></li>
            <li<?php echo $active_nav === 'contact'    ? ' class="active"' : ''; ?>><a href="<?php echo htmlspecialchars($nav_base . 'contact', ENT_QUOTES, 'UTF-8'); ?>">Contact</a></li>
            <li<?php echo $active_nav === 'my_booking' ? ' class="active"' : ''; ?>><a href="<?php echo htmlspecialchars($nav_base . 'my_booking', ENT_QUOTES, 'UTF-8'); ?>">My Booking</a></li>
        </ul>
    </nav>
    <div class="navbar-actions">
        <a href="<?php echo htmlspecialchars($nav_base . 'rooms#room-choice', ENT_QUOTES, 'UTF-8'); ?>" class="navbar-book">Book your stay <span aria-hidden="true">&rarr;</span></a>
        <button class="navbar-toggle" type="button" aria-expanded="false" aria-controls="siteNavigation" aria-label="Open navigation menu">
            <span></span>
            <span></span>
            <span></span>
        </button>
    </div>
</header>
<script src="<?php echo htmlspecialchars($nav_base . 'assets/js/navbar.js', ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo (int) filemtime(__DIR__ . '/../assets/js/navbar.js'); ?>" defer></script>
