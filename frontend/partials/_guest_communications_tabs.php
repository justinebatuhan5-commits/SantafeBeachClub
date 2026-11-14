<?php
/** Administrator navigation for guest feedback, contact inquiries, live chats, and AI concierge. */
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
    <a href="admin_live_support"
       class="guest-communications-tab <?php echo $guest_communications_active === 'live_support' ? 'active' : ''; ?>"
       <?php echo $guest_communications_active === 'live_support' ? 'aria-current="page"' : ''; ?>>
        Live Chats
    </a>
    <a href="admin_chatbot"
       class="guest-communications-tab <?php echo $guest_communications_active === 'chatbot' ? 'active' : ''; ?>"
       <?php echo $guest_communications_active === 'chatbot' ? 'aria-current="page"' : ''; ?>>
        AI Concierge
    </a>
</nav>
