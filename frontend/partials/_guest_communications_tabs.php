<?php
/** Administrator navigation for guest feedback and contact messages. */
$guest_communications_active = $guest_communications_active ?? ($active_page ?? 'reviews');
?>
<nav class="guest-communications-tabs" aria-label="Reviews and inquiries sections">
    <a href="admin_reviews"
       class="guest-communications-tab <?php echo $guest_communications_active === 'reviews' ? 'active' : ''; ?>"
       <?php echo $guest_communications_active === 'reviews' ? 'aria-current="page"' : ''; ?>>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m12 3 3 6 7 1-5 5 1 7-6-3-6 3 1-7-5-5 7-1z"/></svg>
        <span><strong>Reviews</strong><small>Moderate guest feedback</small></span>
    </a>
    <a href="admin_inquiries"
       class="guest-communications-tab <?php echo $guest_communications_active === 'inquiries' ? 'active' : ''; ?>"
       <?php echo $guest_communications_active === 'inquiries' ? 'aria-current="page"' : ''; ?>>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
        <span><strong>Inquiries</strong><small>Manage guest messages</small></span>
    </a>
</nav>
