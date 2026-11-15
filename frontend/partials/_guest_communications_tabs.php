<?php
/** Administrator navigation for guest reviews, contact inquiries, and AI concierge. */
$guest_communications_active = $guest_communications_active ?? ($active_page ?? 'reviews');
?>
<nav class="guest-communications-tabs" aria-label="Guest communications sections">
    <a href="admin_reviews"
       class="guest-communications-tab <?php echo $guest_communications_active === 'reviews' ? 'active' : ''; ?>"
       <?php echo $guest_communications_active === 'reviews' ? 'aria-current="page"' : ''; ?>>
        Reviews
    </a>
    <a href="admin_inquiries"
       class="guest-communications-tab <?php echo $guest_communications_active === 'inquiries' ? 'active' : ''; ?>"
       <?php echo $guest_communications_active === 'inquiries' ? 'aria-current="page"' : ''; ?>>
        Inquiries
    </a>
    <a href="admin_chatbot"
       class="guest-communications-tab <?php echo $guest_communications_active === 'chatbot' ? 'active' : ''; ?>"
       <?php echo $guest_communications_active === 'chatbot' ? 'aria-current="page"' : ''; ?>>
        AI Concierge
    </a>
</nav>
