<?php
/**
 * invoice.php — Official Printable Invoice & Booking Voucher
 * Accessible by guests with token validation (or admin session)
 */

require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/helpers/security_headers.php';
require_once __DIR__ . '/../backend/libs/phpqrcode/phpqrcode.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$ref = trim($_GET['ref'] ?? '');
$token = trim($_GET['token'] ?? '');

$booking_id = 0;
if (preg_match('/^REF-(\d+)$/i', $ref, $m)) {
    $booking_id = (int)$m[1];
} elseif (is_numeric($ref)) {
    $booking_id = (int)$ref;
}

if ($booking_id <= 0) {
    http_response_code(400);
    die("Invalid booking reference.");
}

// Check admin authentication or valid token / session
$isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$isGuest = isset($_SESSION['verified_guest_booking_id']) && (int)$_SESSION['verified_guest_booking_id'] === $booking_id;

$stmt = $conn->prepare("
    SELECT b.*,
           DATEDIFF(b.check_out, b.check_in)  AS nights_calc,
           p.amount                            AS deposit_paid,
           p.total_amount                      AS payment_total,
           p.status                            AS payment_status,
           p.payment_method                    AS payment_method_detail,
           p.transaction_id,
           p.paid_at
    FROM bookings b
    LEFT JOIN payments p ON b.id = p.booking_id
    WHERE b.id = ?
    LIMIT 1
");
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$booking) {
    http_response_code(404);
    die("Booking record not found.");
}

// Validate access token if not logged in as admin or session guest
if (!$isAdmin && !$isGuest) {
    $checkinToken = $booking['checkin_token'] ?? '';
    $cancelToken = $booking['cancellation_token'] ?? '';
    if (empty($token) || (!hash_equals($checkinToken, $token) && !hash_equals($cancelToken, $token))) {
        http_response_code(403);
        die("Unauthorized access to this invoice.");
    }
}

$booking_ref   = 'REF-' . str_pad($booking['id'], 3, '0', STR_PAD_LEFT);
$nights        = max(1, (int)($booking['nights_calc'] ?? $booking['nights'] ?? 1));
$deposit_paid  = (float)($booking['deposit_paid'] ?? 0);
$discount_amt  = (float)($booking['discount_amount'] ?? 0);
// Prefer an explicit total_amount stored on the payment row; fall back to 2x deposit
$total_amount  = (float)($booking['payment_total'] ?? 0);
if ($total_amount <= 0 && $deposit_paid > 0) {
    $total_amount = $deposit_paid * 2; // standard 50% deposit model
}
$remaining_balance = max(0, $total_amount - $deposit_paid);

$checkin_fmt = date('D, F j, Y', strtotime($booking['check_in']));
$checkout_fmt = date('D, F j, Y', strtotime($booking['check_out']));
$created_fmt = date('F j, Y g:i A', strtotime($booking['created_at'] ?? 'now'));

// Check-in QR Code Generation
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
          . '://' . $_SERVER['HTTP_HOST']
          . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$checkin_url = $base_url . '/checkin?ref=' . urlencode($booking_ref) . '&token=' . urlencode($booking['checkin_token'] ?? '');

$qr_dir = __DIR__ . '/assets/qrcodes/';
if (!is_dir($qr_dir)) {
    @mkdir($qr_dir, 0777, true);
}
$qr_file = $qr_dir . 'qr_booking_' . $booking['id'] . '.png';
if (!file_exists($qr_file)) {
    QRcode::png($checkin_url, $qr_file, QR_ECLEVEL_H, 6, 4);
}
$qr_src = 'assets/qrcodes/qr_booking_' . $booking['id'] . '.png?v=' . (file_exists($qr_file) ? filemtime($qr_file) : time());

$autoPrint = isset($_GET['print']) && $_GET['print'] == '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice & Voucher — <?php echo htmlspecialchars($booking_ref); ?> — Santa Fe Beach Club</title>
    <link rel="icon" type="image/jpeg" href="assets/logo.jpg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #F8FAFC;
            color: #1E293B;
            padding: 30px 16px;
            font-size: 14px;
            line-height: 1.5;
        }
        .invoice-wrapper {
            max-width: 800px;
            margin: 0 auto;
            background: #FFFFFF;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.06);
            border: 1px solid #E2E8F0;
            overflow: hidden;
        }
        .no-print-bar {
            max-width: 800px;
            margin: 0 auto 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .btn-print {
            background: #7C533C;
            color: #FFFFFF;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 13.5px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            box-shadow: 0 4px 12px rgba(124, 83, 60, 0.25);
            transition: all 0.2s ease;
        }
        .btn-print:hover { background: #64422F; transform: translateY(-1px); }
        .btn-back {
            color: #64748B;
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 600;
        }
        .btn-back:hover { color: #1E293B; }

        .invoice-header {
            padding: 32px 36px;
            background: linear-gradient(135deg, #FAF6F0 0%, #F5EBE0 100%);
            border-bottom: 2px solid #EADBCC;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .resort-brand {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .resort-logo {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #7C533C;
        }
        .resort-name {
            font-family: 'Outfit', sans-serif;
            font-size: 22px;
            font-weight: 800;
            color: #5A3E2B;
            letter-spacing: -0.3px;
        }
        .resort-sub {
            font-size: 12px;
            color: #84563C;
            margin-top: 2px;
        }
        .invoice-meta {
            text-align: right;
        }
        .invoice-badge {
            display: inline-block;
            background: #7C533C;
            color: #FFFFFF;
            padding: 4px 12px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 12px;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }
        .invoice-ref {
            font-size: 18px;
            font-weight: 800;
            color: #1E293B;
        }
        .invoice-date {
            font-size: 12px;
            color: #64748B;
            margin-top: 2px;
        }

        .invoice-body {
            padding: 32px 36px;
        }
        .section-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 28px;
        }
        .info-card {
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 12px;
            padding: 16px 20px;
        }
        .info-card-title {
            font-size: 11px;
            font-weight: 800;
            color: #64748B;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            margin-bottom: 10px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 6px;
            font-size: 13px;
        }
        .info-row:last-child { margin-bottom: 0; }
        .info-label { color: #64748B; }
        .info-value { font-weight: 600; color: #0F172A; }

        /* Itemized Table */
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 28px;
        }
        .invoice-table th {
            background: #F1F5F9;
            color: #475569;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 12px 16px;
            border-top: 1px solid #E2E8F0;
            border-bottom: 1px solid #E2E8F0;
            text-align: left;
        }
        .invoice-table th:last-child { text-align: right; }
        .invoice-table td {
            padding: 14px 16px;
            border-bottom: 1px solid #F1F5F9;
            font-size: 13.5px;
        }
        .invoice-table td:last-child { text-align: right; font-weight: 600; }

        .invoice-totals {
            margin-left: auto;
            width: 320px;
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 28px;
        }
        .tot-row {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            margin-bottom: 8px;
        }
        .tot-row.grand {
            border-top: 1px solid #CBD5E1;
            padding-top: 8px;
            font-size: 15px;
            font-weight: 800;
            color: #0F172A;
            margin-bottom: 0;
        }
        .tot-row.balance {
            background: #FFFBEB;
            border: 1px solid #FDE68A;
            padding: 8px 12px;
            border-radius: 8px;
            margin-top: 8px;
            font-weight: 700;
            color: #92400E;
        }

        /* Checkin Pass Card */
        .checkin-pass-banner {
            background: #FAF6F0;
            border: 2px dashed #D7C4B7;
            border-radius: 14px;
            padding: 20px 24px;
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .pass-qr-img {
            width: 110px;
            height: 110px;
            border-radius: 10px;
            background: #FFF;
            padding: 6px;
            border: 1px solid #E2E8F0;
            flex-shrink: 0;
        }
        .pass-text h4 {
            font-size: 16px;
            font-weight: 800;
            color: #5A3E2B;
            margin-bottom: 4px;
        }
        .pass-text p {
            font-size: 12.5px;
            color: #64748B;
            line-height: 1.4;
        }

        .invoice-footer {
            padding: 20px 36px;
            background: #F8FAFC;
            border-top: 1px solid #E2E8F0;
            font-size: 11.5px;
            color: #64748B;
            text-align: center;
        }

        @media print {
            body { background: #FFFFFF; padding: 0; }
            .no-print-bar { display: none !important; }
            .invoice-wrapper { border: none; box-shadow: none; border-radius: 0; max-width: 100%; }
            .invoice-header, .checkin-pass-banner { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>

    <div class="no-print-bar">
        <a href="my_booking" class="btn-back">&larr; Back to Guest Portal</a>
        <div style="display:flex; gap:10px;">
            <a href="<?php echo htmlspecialchars($qr_src); ?>" download="SantaFe_Pass_<?php echo htmlspecialchars($booking_ref); ?>.png" class="btn-print" style="background:#0F172A;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Download QR Pass
            </a>
            <button onclick="window.print()" class="btn-print">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Print / Save as PDF
            </button>
        </div>
    </div>

    <div class="invoice-wrapper">
        
        <!-- Header -->
        <div class="invoice-header">
            <div class="resort-brand">
                <img src="assets/logo.jpg" alt="Santa Fe Beach Club" class="resort-logo">
                <div>
                    <h1 class="resort-name">Santa Fe Beach Club</h1>
                    <div class="resort-sub">Bantayan Island, Cebu, Philippines • official voucher</div>
                </div>
            </div>
            <div class="invoice-meta">
                <span class="invoice-badge">BOOKING VOUCHER & INVOICE</span>
                <div class="invoice-ref"><?php echo htmlspecialchars($booking_ref); ?></div>
                <div class="invoice-date">Issued on <?php echo $created_fmt; ?></div>
            </div>
        </div>

        <!-- Body -->
        <div class="invoice-body">

            <!-- Guest & Stay Information Grid -->
            <div class="section-grid">
                <div class="info-card">
                    <div class="info-card-title">Guest Information</div>
                    <div class="info-row">
                        <span class="info-label">Guest Name:</span>
                        <span class="info-value"><?php echo htmlspecialchars($booking['guest_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Email:</span>
                        <span class="info-value"><?php echo htmlspecialchars($booking['guest_email']); ?></span>
                    </div>
                    <?php if (!empty($booking['guest_phone'])): ?>
                    <div class="info-row">
                        <span class="info-label">Contact Phone:</span>
                        <span class="info-value"><?php echo htmlspecialchars($booking['guest_phone']); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="info-row">
                        <span class="info-label">Status:</span>
                        <span class="info-value" style="color:#059669; font-weight:700;"><?php echo htmlspecialchars($booking['status']); ?></span>
                    </div>
                </div>

                <div class="info-card">
                    <div class="info-card-title">Stay Itinerary</div>
                    <div class="info-row">
                        <span class="info-label">Accommodation:</span>
                        <span class="info-value"><?php echo htmlspecialchars($booking['accommodation_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Check-in:</span>
                        <span class="info-value"><?php echo $checkin_fmt; ?> (from 13:30)</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Check-out:</span>
                        <span class="info-value"><?php echo $checkout_fmt; ?> (until 11:00)</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Duration & Guests:</span>
                        <span class="info-value"><?php echo $nights; ?> night<?php echo $nights>1?'s':''; ?>, <?php echo (int)$booking['guests_count']; ?> Adult<?php echo (int)$booking['guests_count']>1?'s':''; ?></span>
                    </div>
                </div>
            </div>

            <!-- Itemized Financial Breakdown Table -->
            <table class="invoice-table">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th style="text-align:center;">Qty / Nights</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($booking['accommodation_name']); ?></strong><br>
                            <small style="color:#64748B;">Room accommodation for <?php echo $nights; ?> night<?php echo $nights>1?'s':''; ?></small>
                        </td>
                        <td style="text-align:center;"><?php echo $nights; ?> night<?php echo $nights>1?'s':''; ?></td>
                        <td>₱ <?php echo number_format($total_amount, 2); ?></td>
                    </tr>
                </tbody>
            </table>

            <!-- Totals & Balances -->
            <div class="invoice-totals">
                <div class="tot-row">
                    <span style="color:#64748B;">Total Stay Cost:</span>
                    <span>₱ <?php echo number_format($total_amount + $discount_amt, 2); ?></span>
                </div>
                <?php if ($discount_amt > 0): ?>
                <div class="tot-row">
                    <span style="color:#16A34A; font-weight:600;">Promo Discount <?php echo !empty($booking['promo_code']) ? '('.$booking['promo_code'].')' : ''; ?>:</span>
                    <span style="color:#16A34A; font-weight:700;">-₱ <?php echo number_format($discount_amt, 2); ?></span>
                </div>
                <div class="tot-row" style="border-top:1px dashed #E2E8F0; padding-top:6px;">
                    <span style="color:#64748B;">After Discount:</span>
                    <span style="font-weight:600;">₱ <?php echo number_format($total_amount, 2); ?></span>
                </div>
                <?php endif; ?>
                <div class="tot-row">
                    <span style="color:#0284C7; font-weight:600;">Deposit Paid (50%):</span>
                    <span style="color:#0284C7; font-weight:700;">-₱ <?php echo number_format($deposit_paid, 2); ?></span>
                </div>
                <div class="tot-row grand">
                    <span>Total Paid:</span>
                    <span>₱ <?php echo number_format($deposit_paid, 2); ?></span>
                </div>
                <?php if ($remaining_balance > 0): ?>
                <div class="tot-row balance">
                    <span>Balance Due at Front Desk:</span>
                    <span>₱ <?php echo number_format($remaining_balance, 2); ?></span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Check-in QR Pass -->
            <div class="checkin-pass-banner">
                <img src="<?php echo htmlspecialchars($qr_src); ?>" alt="Check-in QR Pass" class="pass-qr-img">
                <div class="pass-text">
                    <h4>Express Check-in QR Pass</h4>
                    <p>Present this QR code or your reference <strong><?php echo htmlspecialchars($booking_ref); ?></strong> upon arrival at Santa Fe Beach Club front desk for instant contactless check-in.</p>
                    <p style="margin-top:6px; font-size:11.5px; color:#84563C;">Need help or late arrival? Contact front desk in advance.</p>
                </div>
            </div>

        </div>

        <!-- Footer -->
        <div class="invoice-footer">
            <p>Santa Fe Beach Club • Bantayan Island, Northern Cebu • Thank you for choosing to stay with us!</p>
        </div>

    </div>

    <?php if ($autoPrint): ?>
    <script>
        window.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => { window.print(); }, 400);
        });
    </script>
    <?php endif; ?>

</body>
</html>
