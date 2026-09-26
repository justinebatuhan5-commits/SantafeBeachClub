<?php
/**
 * Shared navigation for the combined arrivals and departures workspace.
 * The processing pages remain separate so their existing actions stay isolated.
 */
$stay_operations_active = $stay_operations_active ?? ($active_page ?? 'checkin');
$stay_operations_role = $_SESSION['admin_role'] ?? 'receptionist';
$stay_operations_checkout_url = $stay_operations_role === 'admin' ? 'admin_checkout' : 'checkout';
?>
<nav class="stay-operations-tabs" aria-label="Check-in and check-out sections">
    <a href="admin_checkin"
       class="stay-operations-tab <?php echo $stay_operations_active === 'checkin' ? 'active' : ''; ?>"
       <?php echo $stay_operations_active === 'checkin' ? 'aria-current="page"' : ''; ?>>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
        <span><strong>Arrivals</strong><small>Check-in guests</small></span>
    </a>
    <a href="<?php echo htmlspecialchars($stay_operations_checkout_url, ENT_QUOTES, 'UTF-8'); ?>"
       class="stay-operations-tab <?php echo $stay_operations_active === 'checkout' ? 'active' : ''; ?>"
       <?php echo $stay_operations_active === 'checkout' ? 'aria-current="page"' : ''; ?>>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        <span><strong>Departures</strong><small>Check-out guests</small></span>
    </a>
</nav>
