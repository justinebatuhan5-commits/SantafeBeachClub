<?php
declare(strict_types=1);

/**
 * Regression & render checks for the redesigned booking step 4 confirmation screen.
 */

$root = dirname(__DIR__);

$assert = static function (bool $condition, string $label): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    fwrite(STDOUT, "PASS: {$label}\n");
};

$viewPath = $root . '/frontend/views/book/step4_success.php';
$cssPath = $root . '/frontend/assets/css/book.css';
$jsPath = $root . '/frontend/assets/js/pages/book.js';

$viewSource = file_get_contents($viewPath);
$cssSource = file_get_contents($cssPath);
$jsSource = file_get_contents($jsPath);

$assert(is_string($viewSource) && $viewSource !== '', 'step4_success.php is readable');
$assert(is_string($cssSource) && $cssSource !== '', 'book.css is readable');
$assert(is_string($jsSource) && $jsSource !== '', 'book.js is readable');

$assert(
    !str_contains($viewSource, 'Booking Confirmed!'),
    'Step 4 heading matches pending payment verification status (Booking Received!)'
);
$assert(
    str_contains($viewSource, 'class="ticket-pass"')
    && str_contains($viewSource, 'class="ticket-details-grid"')
    && str_contains($viewSource, 'class="ticket-qr-panel"'),
    'Boarding pass preserves .ticket-pass for PDF export and adds stay grid + QR panel'
);
$assert(
    str_contains($viewSource, 'class="bk-copy-cancel-btn"')
    && str_contains($viewSource, 'data-copy-url=')
    && !str_contains($viewSource, '><?php echo htmlspecialchars($cancel_url); ?></a>'),
    'Cancellation box replaces long unwrapped URL text with compact copy and cancel actions'
);
$assert(
    str_contains($cssSource, '.booking-success-actions .btn-success-primary')
    && str_contains($cssSource, '.ticket-qr-panel')
    && str_contains($cssSource, '.bk-status-notice'),
    'book.css includes styling rules for action hierarchy, QR panel, and status notice'
);
$assert(
    str_contains($jsSource, 'bk-copy-cancel-btn[data-copy-url]')
    && str_contains($jsSource, '.bk-qr-img'),
    'book.js handles copy-link clicks and QR fallback gracefully without inline handlers'
);

// Render step4_success.php cleanly under strict error handling.
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    $booking_id = 120001;
    $booking_ref = 'REF-120001';
    $guest_name = 'Justine <Test> Batuhan';
    $guest_email = 'justine@example.com';
    $accommodation_name = 'Beach Villa 103';
    $checkin_fmt = 'Sun, 15 Nov 2026';
    $checkout_fmt = 'Mon, 16 Nov 2026';
    $nights = 1;
    $guests_total = 4;
    $booking_eta = '14:00';
    $total_amount = 7900.00;
    $deposit_amount = 3950.00;
    $remaining_balance = 3950.00;
    $payment_method = 'GCash QR';
    $checkin_token = 'tok_checkin_123';
    $cancel_url = 'https://santafebeachclub.onrender.com/frontend/cancel_booking?token=abc123&x=1';

    ob_start();
    include $viewPath;
    $rendered = (string) ob_get_clean();
} finally {
    restore_error_handler();
}

$assert(str_contains($rendered, '<h2>Booking Received!</h2>'), 'Rendered output displays Booking Received heading');
$assert(str_contains($rendered, 'REF-120001'), 'Rendered output contains formatted booking reference');
$assert(str_contains($rendered, '2:00 PM'), 'Rendered output formats 14:00 arrival time as 2:00 PM');
$assert(str_contains($rendered, 'Justine &lt;Test&gt; Batuhan'), 'Rendered output escapes guest name HTML');
$assert(str_contains($rendered, 'qr_booking_120001.png'), 'Rendered output binds QR pass preview and download link');
$assert(str_contains($rendered, 'Manage Booking'), 'Rendered output includes Manage Booking button');

fwrite(STDOUT, "ALL BOOKING SUCCESS UI TESTS PASSED\n");
