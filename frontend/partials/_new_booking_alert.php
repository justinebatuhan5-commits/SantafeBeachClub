<?php
// Included only by authenticated staff dashboards.
$new_booking_alert_rows = [];
$new_booking_alert_count = 0;
try {
    $new_booking_alert_count = (int)$conn->query("SELECT COUNT(*) FROM bookings WHERE status = 'Pending'")->fetch_row()[0];
    if ($new_booking_alert_count > 0) {
        $new_booking_alert_result = $conn->query("SELECT guest_name, accommodation_name, check_in FROM bookings WHERE status = 'Pending' ORDER BY id DESC LIMIT 5");
        while ($new_booking_alert_row = $new_booking_alert_result->fetch_assoc()) {
            $new_booking_alert_rows[] = $new_booking_alert_row;
        }
    }
} catch (Throwable $new_booking_alert_error) {
    error_log('Dashboard new booking alert failed: ' . $new_booking_alert_error->getMessage());
    $new_booking_alert_count = 0;
}
$new_booking_alert_target = ($_SESSION['admin_role'] ?? '') === 'admin' ? 'admin_reservations' : 'reservations';
?>
<?php if ($new_booking_alert_count > 0): ?>
<script src="assets/js/site-dialog.js?v=1"></script>
<script>
(() => {
    const bookings = <?= json_encode($new_booking_alert_rows, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?>;
    const count = <?= $new_booking_alert_count ?>;
    const target = <?= json_encode($new_booking_alert_target) ?>;
    let shown = false;
    async function showBookingAlert() {
        if (shown) return;
        shown = true;
        const details = bookings.map(booking => booking.guest_name + ' — ' + booking.accommodation_name + '\nCheck-in: ' + booking.check_in).join('\n\n');
        const remaining = count > bookings.length ? '\n\nAnd ' + (count - bookings.length) + ' more pending booking(s).' : '';
        const accepted = await SiteDialog.confirm(
            count + ' booking(s) are awaiting review.\n\n' + details + remaining,
            { title: 'New / pending bookings', confirmText: 'View bookings' }
        );
        if (accepted) window.location.href = target;
    }
    // Let staff handle overdue check-outs before showing another modal.
    const overdue = document.getElementById('overdueCheckoutModal');
    if (overdue && getComputedStyle(overdue).display !== 'none') {
        const observer = new MutationObserver(() => {
            if (getComputedStyle(overdue).display === 'none') {
                observer.disconnect();
                showBookingAlert();
            }
        });
        observer.observe(overdue, { attributes: true, attributeFilter: ['style', 'class'] });
    } else {
        showBookingAlert();
    }
})();
</script>
<?php endif; ?>
