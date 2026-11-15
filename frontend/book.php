<?php
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/helpers/security_headers.php';
require_once __DIR__ . '/../backend/helpers/csrf_helper.php';
require_once __DIR__ . '/../backend/controllers/booking_controller.php';

$bookView = sfbc_handle_book_page($conn);
extract($bookView, EXTR_SKIP);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-circle-32.png?v=3">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/favicon-circle-180.png?v=3">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Reservation - Santa Fe Beach Club</title>
    <meta name="csrf-token" content="<?php echo htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="assets/css/tailwind.css?v=<?php echo (int) filemtime(__DIR__ . '/assets/css/tailwind.css'); ?>">
    <link rel="stylesheet" href="assets/css/styles.css?v=<?php echo (int) @filemtime(__DIR__ . '/assets/css/styles.css'); ?>">
    <link rel="stylesheet" href="assets/css/book.css?v=<?php echo (int) @filemtime(__DIR__ . '/assets/css/book.css'); ?>">
    <script src="assets/js/security.js?v=<?php echo (int) @filemtime(__DIR__ . '/assets/js/security.js'); ?>" defer></script>
    <script src="assets/js/site-dialog.js?v=1"></script>
    <?php require __DIR__ . '/partials/_responsive.php'; ?>
</head>
<body class="booking-flow-page">

    <!-- Header -->
    <header class="main-header">
        <div class="bk-brand-group">
            <a href="index" class="logo-link">
                <img src="assets/logo.jpg" alt="Santa Fe Beach Club" class="bk-brand-logo-img">
                <span>Santa Fe Beach Club</span>
            </a>
            <span class="bk-secure-pill">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                Secure Reservation
            </span>
        </div>
        <nav class="nav-menu" aria-label="Checkout navigation">
            <ul>
                <li><a href="rooms">Rooms</a></li>
                <li><a href="contact">Contact</a></li>
                <li><a href="my_booking">Manage Booking</a></li>
            </ul>
        </nav>
    </header>

    <?php if ($step === 4 && $success): ?>
        <?php include __DIR__ . '/views/book/step4_success.php'; ?>
    <?php else: ?>

    <div class="bk-layout">
        <nav class="bk-progress" aria-label="Booking progress">
            <div class="bk-step <?php echo $step === 1 ? 'bk-step--active' : ($step > 1 ? 'bk-step--done' : ''); ?>">
                <span class="bk-step-num">
                    <?php if ($step > 1): ?>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                    <?php else: ?>
                        1
                    <?php endif; ?>
                </span>
                <span class="bk-step-label">Room &amp; Extras</span>
            </div>
            <div class="bk-line" aria-hidden="true"></div>
            <div class="bk-step <?php echo $step === 2 ? 'bk-step--active' : ($step > 2 ? 'bk-step--done' : ''); ?>">
                <span class="bk-step-num">
                    <?php if ($step > 2): ?>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                    <?php else: ?>
                        2
                    <?php endif; ?>
                </span>
                <span class="bk-step-label">Guest Details</span>
            </div>
            <div class="bk-line" aria-hidden="true"></div>
            <div class="bk-step <?php echo $step === 3 ? 'bk-step--active' : ''; ?>">
                <span class="bk-step-num">3</span>
                <span class="bk-step-label">Review &amp; Payment</span>
            </div>
        </nav>

        <?php if (!empty($error)): ?>
            <div class="bk-error-banner">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="bk-columns">
            <div class="bk-main">
                <form method="POST" action="book" enctype="multipart/form-data">
                    <?php echo csrf_field(); ?>
                    <?php
                    if ($step === 1) {
                        include __DIR__ . '/views/book/step1_room.php';
                    } elseif ($step === 2) {
                        include __DIR__ . '/views/book/step2_guest.php';
                    } elseif ($step === 3) {
                        include __DIR__ . '/views/book/step3_payment.php';
                    }
                    ?>
                </form>
            </div>

            <?php include __DIR__ . '/views/book/_summary_sidebar.php'; ?>
        </div>
    </div>

    <?php endif; ?>

    <?php include __DIR__ . '/views/book/_modals.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
    <script>
    window.SFBC_BOOK_CONFIG = <?php echo json_encode([
        'bookingIdPadded' => str_pad((string) $booking_id, 3, '0', STR_PAD_LEFT),
        'roomType'        => $room_type,
        'checkin'         => $checkin,
        'checkout'        => $checkout,
        'checkinYear'     => (int) date('Y', strtotime($checkin)),
        'checkinMonth'    => (int) date('n', strtotime($checkin)),
        'guests'          => (int) $guests,
        'applicationPath' => $bookingApplicationPath,
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES); ?>;
    </script>
    <script src="assets/js/pages/book.js?v=<?php echo (int) @filemtime(__DIR__ . '/assets/js/pages/book.js'); ?>"></script>

    <?php include __DIR__ . '/partials/_chatbot_widget.php'; ?>
</body>
</html>
