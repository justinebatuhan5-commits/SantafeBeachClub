<?php
/**
 * my_booking.php — Guest Portal
 * Guests enter email + booking reference to view their booking details,
 * download their QR code, cancel, or rebook.
 */

require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/helpers/security_headers.php';
require_once __DIR__ . '/../backend/helpers/csrf_helper.php';
require_once __DIR__ . '/../backend/helpers/guest_auth_helper.php';
require_once __DIR__ . '/../backend/libs/phpqrcode/phpqrcode.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Handle logout action
if (isset($_GET['logout'])) {
    clear_guest_booking();
    header('Location: my_booking');
    exit;
}

$guest_data = get_guest_booking();
$booking    = $guest_data['booking'] ?? null;
$payment    = $guest_data['payment'] ?? null;

// If a verified session exists, compute extra display values
if ($booking) {
    $nights        = max(1, (int)($booking['nights'] ?? 1));
    $total_amount  = $payment ? (float)$payment['amount'] * 2 : 0; // deposit was 50%
    $deposit_paid  = $payment ? (float)$payment['amount'] : 0;
    $remaining     = $total_amount - $deposit_paid;
    $checkin_fmt   = date('D, d M Y', strtotime($booking['check_in']));
    $checkout_fmt  = date('D, d M Y', strtotime($booking['check_out']));

    // Status badge colours
    $status_colors = [
        'Pending Payment' => ['bg' => '#FFF3CD', 'color' => '#856404', 'icon' => '⏳'],
        'Pending'         => ['bg' => '#FFF3CD', 'color' => '#856404', 'icon' => '⏳'],
        'Confirmed'       => ['bg' => '#D1FAE5', 'color' => '#065F46', 'icon' => '✅'],
        'Checked In'      => ['bg' => '#DBEAFE', 'color' => '#1E40AF', 'icon' => '🏖️'],
        'Checked Out'     => ['bg' => '#F3F4F6', 'color' => '#374151', 'icon' => '🏁'],
        'Cancelled'       => ['bg' => '#FEE2E2', 'color' => '#991B1B', 'icon' => '❌'],
    ];
    $bk_status     = $booking['status'] ?? 'Pending';
    $status_style  = $status_colors[$bk_status] ?? $status_colors['Pending'];

    $pay_status_colors = [
        'pending'  => ['bg' => '#FFF3CD', 'color' => '#856404', 'label' => 'Pending Verification'],
        'verified' => ['bg' => '#D1FAE5', 'color' => '#065F46', 'label' => 'Verified ✅'],
        'rejected' => ['bg' => '#FEE2E2', 'color' => '#991B1B', 'label' => 'Rejected ❌'],
        'refunded' => ['bg' => '#EDE9FE', 'color' => '#5B21B6', 'label' => 'Refunded'],
    ];
    $pay_status    = strtolower($payment['payment_status'] ?? 'pending');
    $pay_style     = $pay_status_colors[$pay_status] ?? $pay_status_colors['pending'];

    // QR Code — regenerate on-the-fly into a temp buffer, output as data URI
    $base_url     = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                  . '://' . $_SERVER['HTTP_HOST']
                  . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    $checkin_url  = $base_url . '/checkin?ref=' . urlencode($booking['booking_ref']) . '&token=' . urlencode($booking['checkin_token'] ?? '');
    $cancel_url   = $booking['cancel_url'] ?? ($base_url . '/cancel_booking?token=' . urlencode($booking['cancellation_token'] ?? ''));

    // Reuse saved QR file if it exists, otherwise generate
    $qr_file = __DIR__ . '/assets/qrcodes/qr_booking_' . $booking['id'] . '.png';
    if (!file_exists($qr_file)) {
        $qr_dir = __DIR__ . '/assets/qrcodes/';
        if (!is_dir($qr_dir)) {
            mkdir($qr_dir, 0777, true);
        }
        QRcode::png($checkin_url, $qr_file, QR_ECLEVEL_H, 6, 4);
    }
    $qr_data_uri = file_exists($qr_file)
        ? 'data:image/png;base64,' . base64_encode(file_get_contents($qr_file))
        : '';

    // Dynamic Cancellation Policy (based on nights booked)
    $cancel_policy = sf_get_cancellation_policy($booking['check_in'] ?? '', $booking['check_out'] ?? '');
    $cancellation_deadline_hours = $cancel_policy['deadline_hours'];
    $deadline_formatted = $cancel_policy['deadline_formatted'];
    $is_cancellation_expired = $cancel_policy['is_expired'];
    $hours_left_to_cancel = $cancel_policy['hours_left'];
    $days_left_to_cancel = $cancel_policy['days_left'];
    $cancel_policy_name = $cancel_policy['policy_name'];
    $cancel_window_label = $cancel_policy['window_label'];

    // Can the guest still cancel self-service?
    $can_cancel = in_array($bk_status, ['Pending Payment', 'Pending', 'Confirmed']) && !$is_cancellation_expired;

    // Payment Expiry Countdown (30 minute hold window from created_at)
    $is_pending_payment = in_array($bk_status, ['Pending Payment', 'Pending']);
    $created_timestamp  = !empty($booking['created_at']) ? strtotime($booking['created_at']) : time();
    $expiry_timestamp   = $created_timestamp + (30 * 60);
    $seconds_remaining  = max(0, $expiry_timestamp - time());
    $is_payment_expired = ($seconds_remaining <= 0);
}

$csrf_token = get_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Booking — Santa Fe Beach Club</title>
    <link rel="icon" type="image/jpeg" href="assets/logo.jpg">
    <link rel="shortcut icon" type="image/jpeg" href="assets/logo.jpg">
    <link rel="apple-touch-icon" href="assets/logo.jpg">
    <link rel="stylesheet" href="assets/css/styles.css?v=<?php echo (int)@filemtime(__DIR__ . '/assets/css/styles.css'); ?>">
    <style>
        /* ── Page shell ── */
        .portal-wrapper {
            min-height: 100vh;
            background: linear-gradient(145deg, #f5ede6 0%, #ede0d4 100%);
            display: flex;
            flex-direction: column;
        }

        /* ── Lookup Form Card ── */
        .lookup-section {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 60px 16px;
        }
        .lookup-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.10);
            width: 100%;
            max-width: 460px;
            overflow: hidden;
        }
        .lookup-card-header {
            background: linear-gradient(160deg, #7C533C 0%, #4e3226 100%);
            padding: 36px 36px 28px;
            color: #fff;
            text-align: center;
        }
        .lookup-card-header .portal-icon {
            font-size: 36px;
            margin-bottom: 10px;
        }
        .lookup-card-header h1 {
            font-size: 22px;
            font-weight: 700;
            margin: 0 0 6px;
        }
        .lookup-card-header p {
            font-size: 13px;
            opacity: 0.75;
            margin: 0;
        }
        .lookup-card-body {
            padding: 32px 36px 36px;
        }
        .lf-group {
            margin-bottom: 18px;
        }
        .lf-group label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #374151;
            margin-bottom: 7px;
        }
        .lf-group input {
            width: 100%;
            padding: 11px 14px;
            border: 1.5px solid #E5E7EB;
            border-radius: 9px;
            font-size: 14px;
            font-family: 'Outfit', sans-serif;
            color: #1E293B;
            outline: none;
            transition: border-color 0.2s;
            box-sizing: border-box;
        }
        .lf-group input:focus { border-color: #7C533C; box-shadow: 0 0 0 3px rgba(124,83,60,0.10); }
        .lf-hint {
            font-size: 11px;
            color: #94A3B8;
            margin-top: 5px;
        }
        .btn-lookup {
            width: 100%;
            padding: 13px;
            background: #7C533C;
            color: #fff;
            border: none;
            border-radius: 9px;
            font-size: 15px;
            font-weight: 700;
            font-family: 'Outfit', sans-serif;
            cursor: pointer;
            transition: background 0.2s;
            margin-top: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-lookup:hover { background: #5C3D2B; }
        .btn-lookup:disabled { background: #B08060; cursor: not-allowed; }
        .lookup-error {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #FEF2F2;
            border: 1px solid #FCA5A5;
            color: #DC2626;
            padding: 11px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 18px;
            animation: fadeIn 0.25s ease;
        }
        .lookup-success-msg {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #F0FDF4;
            border: 1px solid #86EFAC;
            color: #166534;
            padding: 11px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 18px;
            animation: fadeIn 0.25s ease;
        }
        .otp-box-group {
            display: flex;
            gap: 6px;
            justify-content: center;
            margin: 18px 0;
        }
        .otp-box-digit {
            width: 44px;
            height: 52px;
            font-size: 22px;
            font-weight: 700;
            text-align: center;
            border: 1.5px solid #D1D5DB;
            border-radius: 8px;
            background: #fff;
            outline: none;
            transition: all 0.2s ease;
        }
        .otp-box-digit:focus {
            border-color: #7C533C;
            box-shadow: 0 0 0 3px rgba(124, 83, 60, 0.12);
        }
        @keyframes fadeIn { from { opacity:0; transform:translateY(-4px); } to { opacity:1; transform:translateY(0); } }

        /* ── Portal Dashboard ── */
        .portal-section {
            flex: 1;
            padding: 40px 16px 60px;
            max-width: 800px;
            margin: 0 auto;
            width: 100%;
        }
        .portal-top-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 28px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .portal-welcome {
            font-size: 22px;
            font-weight: 700;
            color: #1E293B;
        }
        .portal-welcome span { color: #7C533C; }
        .btn-portal-logout {
            font-size: 12px;
            color: #94A3B8;
            border: 1px solid #E5E7EB;
            background: #fff;
            padding: 6px 14px;
            border-radius: 20px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
            font-family: 'Outfit', sans-serif;
        }
        .btn-portal-logout:hover { border-color: #7C533C; color: #7C533C; }

        /* ── Cards ── */
        .portal-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.07);
            margin-bottom: 20px;
            overflow: hidden;
        }
        .portal-card-header {
            padding: 16px 24px;
            border-bottom: 1px solid #F1F5F9;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .portal-card-header h2 {
            font-size: 15px;
            font-weight: 700;
            color: #1E293B;
            margin: 0;
        }
        .portal-card-icon {
            font-size: 18px;
        }
        .portal-card-body {
            padding: 20px 24px;
        }

        /* ── Status badge ── */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }

        /* ── Info grid ── */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 16px;
        }
        .info-item label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #94A3B8;
            margin-bottom: 4px;
        }
        .info-item span {
            font-size: 14px;
            font-weight: 600;
            color: #1E293B;
        }

        /* ── QR Section ── */
        .qr-section {
            display: flex;
            align-items: center;
            gap: 28px;
            flex-wrap: wrap;
        }
        .qr-img-wrap {
            background: #fff;
            border: 2px solid #E5E7EB;
            border-radius: 12px;
            padding: 10px;
            flex-shrink: 0;
        }
        .qr-img-wrap img {
            width: 130px;
            height: 130px;
            display: block;
        }
        .qr-info h3 {
            font-size: 15px;
            font-weight: 700;
            color: #1E293B;
            margin: 0 0 6px;
        }
        .qr-info p {
            font-size: 13px;
            color: #64748B;
            margin: 0 0 14px;
            line-height: 1.5;
        }
        .btn-download-qr {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: #7C533C;
            color: #fff;
            text-decoration: none;
            padding: 10px 18px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            transition: background 0.2s;
        }
        .btn-download-qr:hover { background: #5C3D2B; }

        /* ── Action buttons ── */
        .portal-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 4px;
        }
        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 10px 20px;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 700;
            font-family: 'Outfit', sans-serif;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
            border: none;
        }
        .btn-action-primary { background: #7C533C; color: #fff; }
        .btn-action-primary:hover { background: #5C3D2B; }
        .btn-action-outline { background: #fff; color: #374151; border: 1.5px solid #E5E7EB; }
        .btn-action-outline:hover { border-color: #7C533C; color: #7C533C; }
        .btn-action-danger { background: #FEF2F2; color: #DC2626; border: 1.5px solid #FCA5A5; }
        .btn-action-danger:hover { background: #DC2626; color: #fff; border-color: #DC2626; }
        .btn-action-disabled { background: #F8FAFC; color: #94A3B8; border: 1.5px solid #CBD5E1; cursor: not-allowed; opacity: 0.85; }
        .btn-action-disabled:hover { background: #F1F5F9; border-color: #94A3B8; color: #64748B; }

        /* ── Payment row ── */
        .payment-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #F1F5F9;
            font-size: 14px;
        }
        .payment-row:last-child { border-bottom: none; }
        .payment-row label { color: #64748B; font-size: 13px; }
        .payment-row strong { color: #1E293B; }
        .balance-row { background: #FFFBEB; margin: 0 -24px; padding: 12px 24px; border-radius: 0 0 8px 8px; }
        .balance-row label { color: #B45309; font-weight: 700; }
        .balance-row strong { color: #B45309; font-size: 16px; }

        /* ── Ref chip ── */
        .ref-chip {
            display: inline-block;
            font-size: 13px;
            font-weight: 700;
            color: #7C533C;
            background: #FDF4EE;
            border: 1.5px solid #F0D9C8;
            border-radius: 6px;
            padding: 3px 10px;
            letter-spacing: 0.5px;
        }

        /* ── Loading spinner ── */
        .btn-spinner {
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255,255,255,0.4);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
            display: none;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* ── Responsive ── */
        @media (max-width: 520px) {
            .lookup-card-body { padding: 24px 20px 28px; }
            .lookup-card-header { padding: 28px 20px 22px; }
            .portal-card-body { padding: 16px; }
            .info-grid { grid-template-columns: 1fr 1fr; }
            .qr-section { flex-direction: column; align-items: flex-start; }
        }

        /* ── Payment Countdown Timer Card ── */
        .payment-timer-card {
            background: linear-gradient(135deg, #FFFBEB 0%, #FEF3C7 100%);
            border: 1.5px solid #FCD34D;
            padding: 18px 22px;
            margin-bottom: 20px;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(217, 119, 6, 0.08);
        }
        .payment-timer-card.timer-expired {
            background: linear-gradient(135deg, #FEF2F2 0%, #FEE2E2 100%);
            border-color: #FCA5A5;
        }
        .timer-flex {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .timer-icon-wrap {
            position: relative;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            flex-shrink: 0;
            font-size: 20px;
        }
        .timer-pulse-ring {
            position: absolute;
            inset: -3px;
            border-radius: 50%;
            border: 2px solid #F59E0B;
            animation: ringPulse 2s infinite;
        }
        @keyframes ringPulse {
            0% { transform: scale(1); opacity: 0.8; }
            100% { transform: scale(1.35); opacity: 0; }
        }
        .timer-text {
            flex: 1;
            min-width: 0;
        }
        .timer-title {
            font-weight: 700;
            font-size: 15px;
            color: #92400E;
            margin-bottom: 2px;
        }
        .timer-expired .timer-title {
            color: #991B1B;
        }
        .timer-desc {
            font-size: 13px;
            color: #B45309;
            line-height: 1.4;
        }
        .timer-expired .timer-desc {
            color: #B91C1C;
        }
        .timer-clock-badge {
            background: #fff;
            border: 1px solid #FDE68A;
            border-radius: 12px;
            padding: 8px 16px;
            text-align: center;
            flex-shrink: 0;
            box-shadow: 0 2px 6px rgba(0,0,0,0.04);
        }
        .timer-expired .timer-clock-badge {
            border-color: #FECACA;
        }
        .timer-clock-digits {
            display: block;
            font-family: monospace, monospace;
            font-weight: 800;
            font-size: 20px;
            color: #D97706;
            letter-spacing: 1px;
        }
        .timer-expired .timer-clock-digits {
            color: #DC2626;
        }
        .timer-clock-sub {
            font-size: 10px;
            text-transform: uppercase;
            font-weight: 700;
            color: #9CA3AF;
            letter-spacing: 0.5px;
        }
        .timer-progress-bar {
            margin-top: 14px;
            height: 6px;
            background: rgba(217, 119, 6, 0.15);
            border-radius: 999px;
            overflow: hidden;
        }
        .timer-expired .timer-progress-bar {
            background: rgba(220, 38, 38, 0.15);
        }
        .timer-progress-fill {
            height: 100%;
            background: #D97706;
            border-radius: 999px;
            transition: width 1s linear;
        }
        .timer-expired .timer-progress-fill {
            background: #DC2626;
        }
    </style>
</head>
<body>

<!-- Header Navigation -->
<header class="main-header">
    <div class="brand-logo">
        <a href="index" class="logo-link">
            <img src="assets/logo.jpg" alt="Santa Fe Beach Club logo" class="logo-mark" width="56" height="56">
        </a>
    </div>
    <nav class="nav-menu">
        <ul>
            <li><a href="index">Home</a></li>
            <li><a href="rooms">Rooms</a></li>
            <li><a href="gallery">Gallery</a></li>
            <li><a href="contact">Contact</a></li>
            <li class="active"><a href="my_booking">My Booking</a></li>
        </ul>
    </nav>
    <div class="header-action">
        <a href="rooms" class="btn-book-header">Book Now</a>
    </div>
</header>

<div class="portal-wrapper">

<?php if (!$booking): ?>
<!-- ═══════════════════════════════════════════
     LOOKUP FORM — Guest not authenticated yet
════════════════════════════════════════════ -->
<div class="lookup-section">
    <div class="lookup-card">
        <div class="lookup-card-header">
            <h1>My Booking</h1>
            <p>Enter your email and booking reference to view your reservation</p>
        </div>
        <div class="lookup-card-body">
            <div id="lookupError" style="display:none;" class="lookup-error">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <span id="lookupErrorMsg"></span>
            </div>

            <!-- Step 1: Lookup Form -->
            <form id="lookupForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">

                <div class="lf-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" placeholder="you@example.com" required autocomplete="email">
                    <p class="lf-hint">The email you used when booking</p>
                </div>

                <div class="lf-group">
                    <label for="booking_ref">Booking Reference</label>
                    <input type="text" id="booking_ref" name="booking_ref" placeholder="REF-001" required autocomplete="off"
                           style="text-transform: uppercase; letter-spacing: 1px;">
                    <p class="lf-hint">Found in your booking confirmation email (e.g. REF-042)</p>
                </div>

                <button type="submit" class="btn-lookup" id="lookupBtn">
                    <span id="lookupBtnText">Find My Booking</span>
                    <span class="btn-spinner" id="lookupSpinner"></span>
                </button>
            </form>

            <!-- Step 2: OTP Verification Form (Hidden Initially) -->
            <div id="otpStepContainer" style="display:none; text-align:center;">
                <div class="lookup-success-msg" id="otpSuccessBanner">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                    <span>We sent a 6-digit verification code to your email.</span>
                </div>

                <form id="guestOtpForm" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" id="guestFullOtp" name="otp" value="">

                    <div class="otp-box-group">
                        <input type="text" maxlength="1" class="otp-box-digit" pattern="[0-9]" inputmode="numeric" required>
                        <input type="text" maxlength="1" class="otp-box-digit" pattern="[0-9]" inputmode="numeric" required>
                        <input type="text" maxlength="1" class="otp-box-digit" pattern="[0-9]" inputmode="numeric" required>
                        <input type="text" maxlength="1" class="otp-box-digit" pattern="[0-9]" inputmode="numeric" required>
                        <input type="text" maxlength="1" class="otp-box-digit" pattern="[0-9]" inputmode="numeric" required>
                        <input type="text" maxlength="1" class="otp-box-digit" pattern="[0-9]" inputmode="numeric" required>
                    </div>

                    <button type="submit" class="btn-lookup" id="guestVerifyBtn">
                        <span id="guestVerifyBtnText">Verify & View Booking</span>
                        <span class="btn-spinner" id="guestVerifySpinner" style="display:none;"></span>
                    </button>
                </form>

                <div style="margin-top:14px; font-size:13px; color:#6B7280;">
                    Didn't receive code? 
                    <button type="button" id="guestResendBtn" style="background:none; border:none; color:#7C533C; font-weight:700; cursor:pointer; text-decoration:underline;">Resend</button>
                    <span id="guestResendTimer" style="display:none; color:#9CA3AF;">(in <span id="guestSeconds">60</span>s)</span>
                </div>

                <div style="margin-top:16px;">
                    <button type="button" id="guestBackBtn" style="background:none; border:none; color:#94A3B8; font-size:12px; cursor:pointer;">← Change email or booking ref</button>
                </div>
            </div>

            <p style="text-align:center; margin-top:20px; font-size:12px; color:#94A3B8;">
                Need help? <a href="contact" style="color:#7C533C; font-weight:600;">Contact us</a>
            </p>
        </div>
    </div>
</div>

<?php else: ?>
<!-- ═══════════════════════════════════════════
     PORTAL DASHBOARD — Guest authenticated
════════════════════════════════════════════ -->
<div class="portal-section">

    <!-- Top bar -->
    <div class="portal-top-bar">
        <div>
            <p style="font-size:13px; color:#94A3B8; margin:0 0 4px;">Welcome back,</p>
            <p class="portal-welcome">
                <?php echo htmlspecialchars($booking['guest_name']); ?>
                <span class="ref-chip"><?php echo htmlspecialchars($booking['booking_ref']); ?></span>
            </p>
        </div>
        <a href="my_booking?logout=1" class="btn-portal-logout">
            🔒 Look Up Different Booking
        </a>
    </div>

    <!-- ── Payment Countdown Timer Banner (if pending) ── -->
    <?php if ($is_pending_payment): ?>
    <div class="portal-card payment-timer-card <?php echo $is_payment_expired ? 'timer-expired' : ''; ?>" id="paymentTimerCard" data-remaining="<?php echo $seconds_remaining; ?>">
        <div class="timer-flex">
            <div class="timer-icon-wrap">
                <span class="timer-pulse-ring"></span>
                <span class="timer-icon"><?php echo $is_payment_expired ? '⚠️' : '⏱️'; ?></span>
            </div>
            <div class="timer-text">
                <div class="timer-title" id="timerTitle">
                    <?php echo $is_payment_expired ? 'Payment Window Expired' : 'Complete Your Payment Verification'; ?>
                </div>
                <div class="timer-desc" id="timerDesc">
                    <?php echo $is_payment_expired 
                        ? 'Your 30-minute provisional reservation hold has expired. Please contact front desk if you have already sent payment.' 
                        : 'Your provisional room hold is reserved for 30 minutes while we verify your deposit.'; ?>
                </div>
            </div>
            <div class="timer-clock-badge" id="timerClockBadge">
                <span class="timer-clock-digits" id="timerDigits">--:--</span>
                <span class="timer-clock-sub" id="timerSub"><?php echo $is_payment_expired ? 'Expired' : 'Remaining'; ?></span>
            </div>
        </div>
        <div class="timer-progress-bar">
            <div class="timer-progress-fill" id="timerProgressFill" style="width: <?php echo min(100, round(($seconds_remaining / 1800) * 100)); ?>%;"></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Booking Summary Card ── -->
    <div class="portal-card">
        <div class="portal-card-header">
            <span class="portal-card-icon">🏠</span>
            <h2>Booking Summary</h2>
            <span class="status-badge" style="margin-left:auto; background:<?php echo $status_style['bg']; ?>; color:<?php echo $status_style['color']; ?>;">
                <?php echo $status_style['icon']; ?> <?php echo htmlspecialchars($bk_status); ?>
            </span>
        </div>
        <div class="portal-card-body">
            <div class="info-grid">
                <div class="info-item">
                    <label>Accommodation</label>
                    <span><?php echo htmlspecialchars($booking['accommodation_name']); ?></span>
                </div>
                <div class="info-item">
                    <label>Check-in</label>
                    <span><?php echo htmlspecialchars($checkin_fmt); ?></span>
                </div>
                <div class="info-item">
                    <label>Check-out</label>
                    <span><?php echo htmlspecialchars($checkout_fmt); ?></span>
                </div>
                <div class="info-item">
                    <label>Duration</label>
                    <span><?php echo $nights; ?> night<?php echo $nights > 1 ? 's' : ''; ?></span>
                </div>
                <div class="info-item">
                    <label>Guests</label>
                    <span><?php echo (int)$booking['guests_count']; ?> adult<?php echo $booking['guests_count'] > 1 ? 's' : ''; ?></span>
                </div>
                <div class="info-item">
                    <label>Estimated Arrival</label>
                    <span><?php echo htmlspecialchars($booking['eta'] ?? '14:00'); ?></span>
                </div>
                <?php if (!empty($booking['guest_type'])): ?>
                <div class="info-item">
                    <label>Guest Type</label>
                    <span><?php echo htmlspecialchars($booking['guest_type']); ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($booking['guest_special_requests'])): ?>
                <div class="info-item" style="grid-column: 1 / -1;">
                    <label>Special Requests</label>
                    <span><?php echo htmlspecialchars($booking['guest_special_requests']); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── Payment Card ── -->
    <?php if ($payment): ?>
    <div class="portal-card">
        <div class="portal-card-header">
            <span class="portal-card-icon">💳</span>
            <h2>Payment</h2>
            <span class="status-badge" style="margin-left:auto; background:<?php echo $pay_style['bg']; ?>; color:<?php echo $pay_style['color']; ?>;">
                <?php echo $pay_style['label']; ?>
            </span>
        </div>
        <div class="portal-card-body" style="padding-bottom:0;">
            <div class="payment-row">
                <label>Payment Method</label>
                <strong><?php echo htmlspecialchars($payment['payment_method'] ?? $booking['payment_method'] ?? '—'); ?></strong>
            </div>
            <div class="payment-row">
                <label>Reference / Transaction ID</label>
                <strong><?php echo htmlspecialchars($payment['transaction_id'] ?? '—'); ?></strong>
            </div>
            <div class="payment-row">
                <label>Deposit Paid (50%)</label>
                <strong style="color:#0284c7;">₱ <?php echo number_format($deposit_paid, 2); ?></strong>
            </div>
            <div class="payment-row">
                <label>Total Stay Cost</label>
                <strong>₱ <?php echo number_format($total_amount, 2); ?></strong>
            </div>
            <?php if ($remaining > 0 && !in_array($bk_status, ['Checked Out', 'Cancelled'])): ?>
            <div class="payment-row balance-row" style="margin-left:-24px; margin-right:-24px; padding-left:24px; padding-right:24px;">
                <label>Balance Due at Front Desk</label>
                <strong>₱ <?php echo number_format($remaining, 2); ?></strong>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>


    <!-- ── Actions Card ── -->
    <div class="portal-card">
        <div class="portal-card-header">
            <span class="portal-card-icon">⚡</span>
            <h2>Quick Actions</h2>
        </div>
        <div class="portal-card-body">
            <div class="portal-actions">
                <!-- Print / Save Invoice & Voucher -->
                <a href="invoice?ref=<?php echo urlencode($booking['booking_ref']); ?>&token=<?php echo urlencode($booking['checkin_token'] ?? ''); ?>"
                   target="_blank"
                   class="btn-action" style="background:#7C533C; color:#fff;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                    Print / PDF Invoice
                </a>

                <!-- Download QR Pass -->
                <?php if (!empty($qr_data_uri)): ?>
                <a href="<?php echo htmlspecialchars($qr_data_uri); ?>"
                   download="SantaFe_Pass_<?php echo htmlspecialchars($booking['booking_ref']); ?>.png"
                   class="btn-action" style="background:#0F172A; color:#fff;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Save QR Pass (PNG)
                </a>
                <?php endif; ?>

                <!-- Resume Payment -->
                <?php 
                if ($bk_status === 'Pending Payment' && !empty($payment['receipt_url']) && strpos($payment['receipt_url'], 'http') === 0): 
                ?>
                <a href="<?php echo htmlspecialchars($payment['receipt_url']); ?>"
                   class="btn-action" style="background:#0ea5e9; color:#fff;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="4" width="18" height="16" rx="2" ry="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    Complete Payment
                </a>
                <?php endif; ?>

                <!-- Rebook: pre-fill booking form with same guest details -->
                <a href="book?rebook=1&email=<?php echo urlencode($booking['guest_email']); ?>&first_name=<?php echo urlencode(strtok($booking['guest_name'], ' ')); ?>&last_name=<?php echo urlencode(strstr($booking['guest_name'], ' ')); ?>&phone=<?php echo urlencode($booking['guest_phone'] ?? ''); ?>&country=<?php echo urlencode($booking['guest_country'] ?? ''); ?>"
                   class="btn-action btn-action-primary">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                    Book Again
                </a>

                <!-- Contact us -->
                <a href="contact" class="btn-action btn-action-outline">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    Contact Us
                </a>

                <!-- Cancel Booking (Active or Locked state) -->
                <?php if ($can_cancel): ?>
                <a href="javascript:void(0)"
                   class="btn-action btn-action-danger"
                   data-cancel-url="<?php echo htmlspecialchars($cancel_url); ?>"
                   onclick="(function(el){ showConfirm({ title: 'Cancel Booking', message: 'Are you sure you want to cancel this booking? This cannot be undone.', icon: '❌', iconBg: '#FEE2E2', confirmText: 'Yes, Cancel It', onConfirm: function(){ window.location.href = el.dataset.cancelUrl; } }); })(this)">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                    Cancel Booking
                </a>
                <?php elseif ($is_cancellation_expired && in_array($bk_status, ['Pending Payment', 'Pending', 'Confirmed'])): ?>
                <a href="javascript:void(0)"
                   class="btn-action btn-action-disabled"
                   title="Cancellation locked (<?php echo htmlspecialchars($cancel_window_label); ?> deadline passed on <?php echo htmlspecialchars($deadline_formatted); ?>)"
                   onclick="showConfirm({ title: 'Cancellation Locked 🔒', message: 'Self-service cancellation closed on <?php echo addslashes($deadline_formatted); ?> (<?php echo addslashes($cancel_window_label); ?> prior to arrival for a <?php echo (int)$cancel_policy['nights']; ?>-night stay).\n\nTo cancel or reschedule your reservation, please reach out to our Front Desk directly.', icon: '🔒', iconBg: '#FEF3C7', confirmText: 'Contact Front Desk', confirmColor: '#7C533C', onConfirm: function(){ window.location.href = 'contact'; } })">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    Cancel Booking 🔒
                </a>
                <?php endif; ?>
            </div>

            <!-- Cancellation Deadline Notification -->
            <?php if ($bk_status === 'Cancelled'): ?>
                <div style="background:#FEF2F2; border:1px solid #FECACA; border-radius:10px; padding:12px 14px; margin-top:16px; font-size:12.5px; color:#991B1B; display:flex; align-items:flex-start; gap:10px;">
                    <span style="font-size:15px;">❌</span>
                    <div>
                        <strong>Reservation Cancelled</strong>
                        <div style="margin-top:2px; font-size:12px; color:#B91C1C;">
                            <?php if (!empty($booking['cancellation_reason'])): ?>
                                Reason: <?php echo htmlspecialchars($booking['cancellation_reason']); ?>
                            <?php else: ?>
                                This booking has been cancelled and room inventory was released.
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php elseif ($can_cancel): ?>
                <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:10px; padding:11px 14px; margin-top:16px; font-size:12.5px; color:#475569; display:flex; align-items:center; gap:10px;">
                    <span style="font-size:16px;">⏰</span>
                    <div>
                        <strong style="color:#0F172A;">Free Cancellation Window (<?php echo htmlspecialchars($cancel_window_label); ?> policy):</strong>
                        <span>Online cancellation is open until <strong><?php echo htmlspecialchars($deadline_formatted); ?></strong> (<?php echo $hours_left_to_cancel >= 24 ? $days_left_to_cancel . ' days left' : ($hours_left_to_cancel > 0 ? $hours_left_to_cancel . ' hrs left' : 'closing soon'); ?>).</span>
                    </div>
                </div>
            <?php elseif ($bk_status !== 'Checked Out'): ?>
                <div style="background:#FFFBEB; border:1px solid #FDE68A; border-radius:10px; padding:12px 14px; margin-top:16px; font-size:12.5px; color:#92400E; display:flex; align-items:flex-start; gap:10px;">
                    <span style="font-size:16px; margin-top:1px;">⚠️</span>
                    <div>
                        <strong style="display:block; margin-bottom:2px;">Cancellation Deadline Passed</strong>
                        <span>Online cancellations closed on <strong><?php echo htmlspecialchars($deadline_formatted); ?></strong> (<?php echo htmlspecialchars($cancel_window_label); ?> prior to arrival for a <?php echo (int)$cancel_policy['nights']; ?>-night stay). Please contact the Front Desk if you need assistance.</span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div><!-- /.portal-section -->
<?php endif; ?>

</div><!-- /.portal-wrapper -->

<!-- ── Footer ── -->
<footer style="background:#1E293B; color:#94A3B8; text-align:center; padding:20px 16px; font-size:12px;">
    &copy; <?php echo date('Y'); ?> Santa Fe Beach Club. All rights reserved.
</footer>

<?php if (!$booking): ?>
<script>
const lookupForm  = document.getElementById('lookupForm');
const lookupBtn   = document.getElementById('lookupBtn');
const lookupText  = document.getElementById('lookupBtnText');
const lookupSpin  = document.getElementById('lookupSpinner');
const errorBox    = document.getElementById('lookupError');
const errorMsg    = document.getElementById('lookupErrorMsg');

const otpContainer = document.getElementById('otpStepContainer');
const guestOtpForm = document.getElementById('guestOtpForm');
const guestFullOtp = document.getElementById('guestFullOtp');
const guestOtpDigits = document.querySelectorAll('.otp-box-digit');
const guestVerifyBtn = document.getElementById('guestVerifyBtn');
const guestVerifyText = document.getElementById('guestVerifyBtnText');
const guestVerifySpin = document.getElementById('guestVerifySpinner');
const guestResendBtn  = document.getElementById('guestResendBtn');
const guestResendTimer = document.getElementById('guestResendTimer');
const guestSeconds    = document.getElementById('guestSeconds');
const guestBackBtn    = document.getElementById('guestBackBtn');

function showError(msg) {
    errorMsg.textContent = msg;
    errorBox.style.display = 'flex';
    errorBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}
function hideError() {
    errorBox.style.display = 'none';
}

lookupForm.addEventListener('submit', async function(e) {
    e.preventDefault();
    hideError();

    const email = document.getElementById('email').value.trim();
    const ref   = document.getElementById('booking_ref').value.trim().toUpperCase();

    if (!email || !ref) {
        showError('Please enter both your email and booking reference.');
        return;
    }
    if (!/^REF-\d+$/i.test(ref)) {
        showError('Booking reference format is invalid. It should look like REF-001.');
        return;
    }

    // Show loading state
    lookupBtn.disabled = true;
    lookupText.textContent = 'Sending Code...';
    lookupSpin.style.display = 'block';

    const formData = new FormData(lookupForm);
    formData.set('booking_ref', ref);

    try {
        const response = await fetch('../backend/api/guest_lookup_api.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await response.json();

        if (data.success && data.mfa) {
            // Transition to Step 2: OTP
            lookupForm.style.display = 'none';
            otpContainer.style.display = 'block';
            guestOtpDigits[0].focus();
            startGuestCooldown(60);
        } else {
            showError(data.error || 'Booking not found. Please check your details and try again.');
            lookupBtn.disabled = false;
            lookupText.textContent = 'Find My Booking';
            lookupSpin.style.display = 'none';
        }
    } catch (err) {
        showError('A network error occurred. Please check your connection and try again.');
        lookupBtn.disabled = false;
        lookupText.textContent = 'Find My Booking';
        lookupSpin.style.display = 'none';
    }
});

// Auto tab digits in OTP step
guestOtpDigits.forEach((input, idx) => {
    input.addEventListener('input', (e) => {
        if (e.target.value.length === 1 && idx < guestOtpDigits.length - 1) {
            guestOtpDigits[idx + 1].focus();
        }
        updateGuestFullOtp();
    });
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Backspace' && !input.value && idx > 0) {
            guestOtpDigits[idx - 1].focus();
        }
    });
    input.addEventListener('paste', (e) => {
        e.preventDefault();
        const pasteData = e.clipboardData.getData('text').trim();
        if (/^[0-9]{6}$/.test(pasteData)) {
            pasteData.split('').forEach((c, i) => {
                if (guestOtpDigits[i]) guestOtpDigits[i].value = c;
            });
            updateGuestFullOtp();
            guestOtpDigits[guestOtpDigits.length - 1].focus();
        }
    });
});

function updateGuestFullOtp() {
    let combined = '';
    guestOtpDigits.forEach(d => combined += d.value);
    guestFullOtp.value = combined;
}

// Handle OTP Verification Form Submit
guestOtpForm.addEventListener('submit', async function(e) {
    e.preventDefault();
    hideError();
    updateGuestFullOtp();

    if (guestFullOtp.value.length !== 6) {
        showError('Please enter all 6 digits.');
        return;
    }

    guestVerifyBtn.disabled = true;
    guestVerifyText.textContent = 'Verifying...';
    guestVerifySpin.style.display = 'inline-block';

    const formData = new FormData(guestOtpForm);

    try {
        const response = await fetch('../backend/api/guest_verify_otp_api.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await response.json();

        if (data.success) {
            window.location.reload();
        } else {
            showError(data.error || 'Verification failed.');
            guestVerifyBtn.disabled = false;
            guestVerifyText.textContent = 'Verify & View Booking';
            guestVerifySpin.style.display = 'none';

            if (data.reset) {
                setTimeout(() => {
                    otpContainer.style.display = 'none';
                    lookupForm.style.display = 'block';
                    lookupBtn.disabled = false;
                    lookupText.textContent = 'Find My Booking';
                    lookupSpin.style.display = 'none';
                }, 2000);
            }
        }
    } catch (err) {
        showError('A network error occurred. Please try again.');
        guestVerifyBtn.disabled = false;
        guestVerifyText.textContent = 'Verify & View Booking';
        guestVerifySpin.style.display = 'none';
    }
});

// Resend timer for guest
let guestCooldown = 0;
function startGuestCooldown(sec) {
    guestCooldown = sec;
    guestResendBtn.disabled = true;
    guestResendTimer.style.display = 'inline';
    guestSeconds.textContent = guestCooldown;

    const interval = setInterval(() => {
        guestCooldown--;
        guestSeconds.textContent = guestCooldown;
        if (guestCooldown <= 0) {
            clearInterval(interval);
            guestResendBtn.disabled = false;
            guestResendTimer.style.display = 'none';
        }
    }, 1000);
}

guestResendBtn.addEventListener('click', async () => {
    if (guestCooldown > 0) return;
    hideError();

    const formData = new FormData();
    formData.append('action', 'resend');
    const token = document.querySelector('input[name="csrf_token"]').value;
    formData.append('csrf_token', token);

    try {
        const response = await fetch('../backend/api/guest_verify_otp_api.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await response.json();
        if (data.success) {
            startGuestCooldown(60);
            alert(data.message || 'New code sent to your email.');
        } else {
            showError(data.error || 'Failed to resend code.');
        }
    } catch (e) {
        showError('Network error resending code.');
    }
});

// Back to Lookup
guestBackBtn.addEventListener('click', () => {
    otpContainer.style.display = 'none';
    lookupForm.style.display = 'block';
    lookupBtn.disabled = false;
    lookupText.textContent = 'Find My Booking';
    lookupSpin.style.display = 'none';
});

// Auto-uppercase the booking ref input as the user types
document.getElementById('booking_ref').addEventListener('input', function() {
    const pos = this.selectionStart;
    this.value = this.value.toUpperCase();
    this.setSelectionRange(pos, pos);
});
</script>
<?php endif; ?>

<!-- Custom Confirmation Modal -->
<div id="sfbc-confirm-overlay" style="
    display:none; position:fixed; inset:0;
    background:rgba(0,0,0,0.55);
    backdrop-filter:blur(4px); -webkit-backdrop-filter:blur(4px);
    z-index:99999; align-items:center; justify-content:center;">
    <div style="
        background:#fff; border-radius:20px; padding:32px 28px 24px;
        max-width:400px; width:calc(100% - 40px);
        box-shadow:0 24px 60px rgba(0,0,0,0.18);
        text-align:center; animation:sfbcSlideUp 0.2s cubic-bezier(0.16,1,0.3,1);">
        <div id="sfbc-confirm-icon" style="width:60px;height:60px;border-radius:50%;background:#FEE2E2;display:flex;align-items:center;justify-content:center;margin:0 auto 18px;font-size:28px;">❌</div>
        <h3 id="sfbc-confirm-title" style="margin:0 0 8px;font-size:18px;font-weight:800;color:#111827;"></h3>
        <p id="sfbc-confirm-message" style="margin:0 0 24px;font-size:14px;color:#6B7280;line-height:1.6;"></p>
        <div style="display:flex;gap:12px;">
            <button id="sfbc-confirm-cancel" style="flex:1;padding:12px;background:#F3F4F6;color:#374151;border:none;border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;">Keep Booking</button>
            <button id="sfbc-confirm-ok" style="flex:1;padding:12px;background:#DC2626;color:#fff;border:none;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;">Yes, Cancel</button>
        </div>
    </div>
</div>
<style>
@keyframes sfbcSlideUp { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:translateY(0)} }
</style>
<script>
function showConfirm(opts) {
    var overlay  = document.getElementById('sfbc-confirm-overlay');
    var icon     = document.getElementById('sfbc-confirm-icon');
    var title    = document.getElementById('sfbc-confirm-title');
    var message  = document.getElementById('sfbc-confirm-message');
    var okBtn    = document.getElementById('sfbc-confirm-ok');
    var cancelBtn = document.getElementById('sfbc-confirm-cancel');

    icon.textContent      = opts.icon    || '❌';
    icon.style.background = opts.iconBg  || '#FEE2E2';
    title.textContent     = opts.title   || 'Are you sure?';
    message.textContent   = opts.message || 'This action cannot be undone.';
    okBtn.textContent     = opts.confirmText || 'Confirm';

    overlay.style.display = 'flex';

    var newOk = okBtn.cloneNode(true);
    okBtn.parentNode.replaceChild(newOk, okBtn);
    newOk.addEventListener('click', function() {
        overlay.style.display = 'none';
        if (typeof opts.onConfirm === 'function') opts.onConfirm();
    });

    var newCancel = cancelBtn.cloneNode(true);
    cancelBtn.parentNode.replaceChild(newCancel, cancelBtn);
    newCancel.addEventListener('click', function() { overlay.style.display = 'none'; });
    overlay.onclick = function(e) { if (e.target === overlay) overlay.style.display = 'none'; };
}
</script>

</body>
</html>
