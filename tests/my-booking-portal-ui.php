<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function check_portal_ui(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }
    echo 'PASS: ' . $message . PHP_EOL;
}

$root = dirname(__DIR__);
$dashboardPath = $root . '/frontend/views/my_booking/_portal_dashboard.php';
$shellSource = file_get_contents($root . '/frontend/my_booking.php') ?: '';
$controllerSource = file_get_contents($root . '/backend/controllers/my_booking_controller.php') ?: '';
$cssSource = file_get_contents($root . '/frontend/assets/css/pages/my-booking.css') ?: '';
$jsSource = file_get_contents($root . '/frontend/assets/js/pages/my-booking.js') ?: '';

// Render _portal_dashboard.php with sample confirmed guest booking + XSS payload
$booking = [
    'id' => 120001,
    'booking_ref' => 'REF-120001',
    'guest_name' => 'Justine <script>alert(1)</script> Batuhan',
    'guest_email' => 'justine@example.com',
    'guest_type' => 'First Visit',
    'guest_phone' => '09171234567',
    'guest_country' => 'Philippines',
    'guest_special_requests' => '<img src=x onerror=alert(2)> Late check-in',
    'accommodation_name' => 'Beach Villa 103',
    'check_in' => '2026-11-15',
    'check_out' => '2026-11-16',
    'eta' => '14:00',
    'guests_count' => 4,
    'payment_method' => 'GCash QR',
    'cancellation_token' => 'tok_cancel_abc',
    'checkin_token' => 'tok_checkin_xyz',
];
$discountBooking = null;
$payment = [
    'payment_method' => 'GCash QR',
    'transaction_id' => '12345678910',
    'status' => 'verified',
    'payment_status' => 'verified',
    'amount' => 3950.0,
];
$nights = 1;
$original_amount = 7900.0;
$total_amount = 7900.0;
$required_deposit = 3950.0;
$discount_amount = 0.0;
$deposit_paid = 3950.0;
$remaining = 3950.0;
$checkin_fmt = 'Sun, 15 Nov 2026';
$checkout_fmt = 'Mon, 16 Nov 2026';
$eta_display = '2:00 PM';
$bk_status = 'Confirmed';
$status_style = ['bg' => '#D1FAE5', 'color' => '#065F46', 'icon' => '<svg></svg>'];
$pay_style = ['bg' => '#D1FAE5', 'color' => '#065F46', 'label' => 'Verified'];
$checkin_url = 'checkin?ref=REF-120001';
$cancel_url = 'cancel_booking?token=tok_cancel_abc';
$qr_data_uri = 'data:image/png;base64,iVBORw0KGgo=';
$cancel_policy = ['nights' => 1];
$deadline_formatted = 'Nov 14, 2026 at 2:00 PM';
$is_cancellation_expired = true;
$hours_left_to_cancel = 0;
$days_left_to_cancel = 0;
$cancel_window_label = '48 hours';
$can_cancel = false;
$is_pending_payment = false;
$seconds_remaining = 0;
$hold_window_seconds = 1800;
$is_payment_expired = false;

ob_start();
require $dashboardPath;
$renderedConfirmed = ob_get_clean();

check_portal_ui(
    !str_contains($renderedConfirmed, '<script>alert(1)</script>') && !str_contains($renderedConfirmed, '<img src=x onerror='),
    'Guest name and special requests escape raw HTML/script payloads'
);
check_portal_ui(
    str_contains($renderedConfirmed, 'site-navbar') && str_contains($renderedConfirmed, 'portal-grid'),
    'Logged-in dashboard includes shared site navbar and two-column stay workspace'
);
check_portal_ui(
    str_contains($renderedConfirmed, '2:00 PM') && !str_contains($renderedConfirmed, '<span>14:00</span>'),
    'Estimated arrival renders formatted 12-hour time (2:00 PM)'
);
check_portal_ui(
    str_contains($renderedConfirmed, 'qr-pass-card')
        && str_contains($renderedConfirmed, 'Save QR Pass (PNG)')
        && str_contains($renderedConfirmed, 'Print / PDF Invoice'),
    'Side rail renders digital Check-In QR Pass preview with Save and Invoice actions'
);
check_portal_ui(
    str_contains($renderedConfirmed, 'Balance Due Before Arrival')
        && str_contains($renderedConfirmed, 'Pay Remaining Balance')
        && str_contains($renderedConfirmed, 'js-cancel-locked-btn')
        && !str_contains($renderedConfirmed, 'onclick='),
    'Actions use delegated button hooks without inline onclick attributes'
);

// Render pending payment + Senior/PWD discount state
$bk_status = 'Pending Payment';
$is_pending_payment = true;
$seconds_remaining = 1245;
$hold_window_seconds = 1800;
$discountBooking = ['category' => 'senior', 'state' => 'Approved'];
$original_amount = 7900.0;
$total_amount = 6320.0;
$discount_amount = 1580.0;
$required_deposit = 3160.0;
$deposit_paid = 6320.0;
$remaining = 0.0;
$can_cancel = true;
$is_cancellation_expired = false;

ob_start();
require $dashboardPath;
$renderedPending = ob_get_clean();

check_portal_ui(
    str_contains($renderedPending, 'id="paymentTimerCard"')
        && str_contains($renderedPending, '>20:45<')
        && str_contains($renderedPending, 'Manage Discount &amp; Payment')
        && str_contains($renderedPending, 'Paid in Full')
        && str_contains($renderedPending, 'js-cancel-booking-btn'),
    'Pending payment timer renders initial MM:SS clock, discount button, paid-in-full strip, and active cancel button'
);

// Verify shell, controller, JS, and CSS contracts
check_portal_ui(
    str_contains($shellSource, '<?php if (!$booking): ?>')
        && str_contains($shellSource, 'recaptcha/api.js')
        && str_contains($shellSource, 'class="portal-footer"'),
    'my_booking.php loads reCAPTCHA only on lookup view and uses themed portal footer'
);
check_portal_ui(
    str_contains($controllerSource, 'eta_display') && str_contains($controllerSource, 'hold_window_seconds'),
    'Controller prepares formatted ETA and hold window duration'
);
check_portal_ui(
    str_contains($jsSource, 'initPaymentTimer')
        && str_contains($jsSource, 'initPortalActionConfirmations')
        && str_contains($jsSource, '.js-cancel-booking-btn')
        && str_contains($jsSource, '.js-cancel-locked-btn'),
    'my-booking.js powers live payment timer countdown and delegated cancellation confirmation modals'
);
check_portal_ui(
    str_contains($cssSource, '.booking-account-page .grecaptcha-badge')
        && str_contains($cssSource, '.booking-account-page .portal-grid')
        && str_contains($cssSource, '#3E271C')
        && str_contains($cssSource, '.qr-pass-card'),
    'my-booking.css applies coastal espresso theme, two-column layout, QR pass styles, and badge safeguard'
);

echo "ALL MY BOOKING PORTAL UI TESTS PASSED\n";
