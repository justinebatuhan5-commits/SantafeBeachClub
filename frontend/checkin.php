<?php
require_once __DIR__ . '/../backend/helpers/auth_check.php';
require_once __DIR__ . '/../backend/config/db.php';

// Handle POST request to check-in
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'checkin') {
    require_csrf_token();
    $booking_id = intval($_POST['booking_id']);
    $print_params = '';
    
    // Check if there is a payment to record
    if (isset($_POST['collect_payment']) && $_POST['collect_payment'] === '1') {
        $payment_method = $_POST['payment_method'] ?? 'Front Desk Cash';
        $balance_amount = floatval($_POST['balance_amount'] ?? 0);
        
        $amount_tendered = isset($_POST['amount_tendered']) && $_POST['amount_tendered'] !== '' ? floatval($_POST['amount_tendered']) : $balance_amount;
        $change_amount = isset($_POST['change_amount']) && $_POST['change_amount'] !== '' ? floatval($_POST['change_amount']) : 0;
        $ref_no = trim($_POST['reference_number'] ?? '');
        
        if ($balance_amount > 0) {
            // Fetch guest info for payment record
            $g_stmt = $conn->prepare("SELECT guest_name, guest_email FROM bookings WHERE id = ?");
            $g_stmt->bind_param("i", $booking_id);
            $g_stmt->execute();
            $g_res = $g_stmt->get_result()->fetch_assoc();
            $g_stmt->close();
            
            if ($g_res) {
                $guest_name = $g_res['guest_name'];
                $guest_email = $g_res['guest_email'];
                $txn_id = (!empty($ref_no) && $payment_method !== 'Front Desk Cash') ? $ref_no : 'TXN-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
                
                $p_stmt = $conn->prepare("INSERT INTO payments (booking_id, guest_name, guest_email, amount, payment_method, transaction_id, status, accounting_status, amount_tendered, change_amount) VALUES (?, ?, ?, ?, ?, ?, 'verified', 'deferred', ?, ?)");
                $p_stmt->bind_param("issdssdd", $booking_id, $guest_name, $guest_email, $balance_amount, $payment_method, $txn_id, $amount_tendered, $change_amount);
                $p_stmt->execute();
                $p_stmt->close();
                
                $print_params = "&print_rcpt=1&bid=" . urlencode($booking_id) . "&txn=" . urlencode($txn_id) . "&method=" . urlencode($payment_method) . "&amount=" . urlencode($balance_amount) . "&tendered=" . urlencode($amount_tendered) . "&change=" . urlencode($change_amount);
                
                // Add a notification about balance collected
                $notif_title = 'Balance Payment Collected';
                $notif_type = 'info';
                $notif_message = 'Collected remaining balance of ₱' . number_format($balance_amount, 2) . ' via ' . $payment_method . ' for guest ' . htmlspecialchars($guest_name) . ' (REF-' . str_pad($booking_id, 3, '0', STR_PAD_LEFT) . ') at check-in.';
                
                $n_stmt = $conn->prepare("INSERT INTO notifications (title, message, type, booking_id) VALUES (?, ?, ?, ?)");
                $n_stmt->bind_param("sssi", $notif_title, $notif_message, $notif_type, $booking_id);
                $n_stmt->execute();
                $n_stmt->close();
            }
        }
    }
    
    // Update booking status
    $stmt = $conn->prepare("UPDATE bookings SET status = 'Checked In' WHERE id = ?");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $stmt->close();
    
    // Also update room status to occupied
    $stmt = $conn->prepare("SELECT room_id FROM bookings WHERE id = ?");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $room_query = $stmt->get_result();
    if ($room_query && $room_query->num_rows > 0) {
        $room_id = (int) $room_query->fetch_assoc()['room_id'];
        if ($room_id > 0) {
            $uStmt = $conn->prepare("UPDATE rooms SET status = 'occupied' WHERE id = ?");
            $uStmt->bind_param("i", $room_id);
            $uStmt->execute();
            $uStmt->close();
        }
    }
    $stmt->close();
    
    // Log activity
    $admin_user = $_SESSION['admin_username'] ?? 'System';
    log_activity($conn, $admin_user, 'Check-in Guest', 'Checked in booking ID #' . $booking_id);
    
    // Redirect or return JSON
    if (isset($_POST['format']) && $_POST['format'] === 'json') {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }
    
    header("Location: checkin?success=1" . $print_params);
    exit;
}

// Handle POST request for Walk-in / Express Check-in
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'walkin_checkin') {
    $guest_name = ucwords(strtolower(trim($_POST['guest_name'] ?? '')));
    $guest_email = trim($_POST['guest_email'] ?? '');
    $guest_phone = trim($_POST['guest_phone'] ?? '');
    $guest_type = trim($_POST['guest_type'] ?? 'Walk-in');
    $room_id = intval($_POST['room_id'] ?? 0);
    $check_in = trim($_POST['check_in'] ?? date('Y-m-d'));
    $check_out = trim($_POST['check_out'] ?? date('Y-m-d', strtotime('+1 day')));
    $guests_count = max(1, intval($_POST['guests_count'] ?? 1));
    $payment_method = trim($_POST['payment_method'] ?? 'Front Desk Cash');
    $amount_tendered = isset($_POST['amount_tendered']) && $_POST['amount_tendered'] !== '' ? floatval($_POST['amount_tendered']) : 0;
    $ref_no = trim($_POST['reference_number'] ?? '');
    
    if (!empty($guest_name) && $room_id > 0) {
        // Fetch room info
        $r_stmt = $conn->prepare("SELECT r.*, rt.id as room_type_id FROM rooms r LEFT JOIN room_types rt ON r.type = rt.name WHERE r.id = ?");
        $r_stmt->bind_param("i", $room_id);
        $r_stmt->execute();
        $room_info = $r_stmt->get_result()->fetch_assoc();
        $r_stmt->close();
        
        if ($room_info) {
            $nights = max(1, (int)ceil((strtotime($check_out) - strtotime($check_in)) / 86400));
            $price_per_night = floatval($room_info['price_per_night']);
            $total_cost = $nights * $price_per_night;
            $accommodation_name = $room_info['name'];
            $room_type_id = $room_info['room_type_id'] ?? null;
            
            $change_amount = max(0, $amount_tendered - $total_cost);
            if ($payment_method !== 'Front Desk Cash') {
                $amount_tendered = $total_cost;
                $change_amount = 0;
            }
            
            $checkin_token = bin2hex(random_bytes(16));
            $cancellation_token = bin2hex(random_bytes(16));
            $eta = date('H:i');
            
            // Insert booking directly as Checked In
            $b_stmt = $conn->prepare("
                INSERT INTO bookings (guest_name, guest_email, guest_phone, guest_type, room_type_id, room_id, accommodation_name, check_in, check_out, guests_count, eta, status, payment_method, checkin_token, cancellation_token)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Checked In', ?, ?, ?)
            ");
            $b_stmt->bind_param(
                "ssssiisssissss",
                $guest_name,
                $guest_email,
                $guest_phone,
                $guest_type,
                $room_type_id,
                $room_id,
                $accommodation_name,
                $check_in,
                $check_out,
                $guests_count,
                $eta,
                $payment_method,
                $checkin_token,
                $cancellation_token
            );
            $b_stmt->execute();
            $booking_id = $conn->insert_id;
            $b_stmt->close();
            
            // Update room status to occupied
            $u_stmt = $conn->prepare("UPDATE rooms SET status = 'occupied' WHERE id = ?");
            $u_stmt->bind_param("i", $room_id);
            $u_stmt->execute();
            $u_stmt->close();
            
            // Record full verified payment
            $txn_id = (!empty($ref_no) && $payment_method !== 'Front Desk Cash') ? $ref_no : 'TXN-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
            $p_stmt = $conn->prepare("INSERT INTO payments (booking_id, guest_name, guest_email, amount, payment_method, transaction_id, status, accounting_status, amount_tendered, change_amount) VALUES (?, ?, ?, ?, ?, ?, 'verified', 'deferred', ?, ?)");
            $p_stmt->bind_param("issdssdd", $booking_id, $guest_name, $guest_email, $total_cost, $payment_method, $txn_id, $amount_tendered, $change_amount);
            $p_stmt->execute();
            $p_stmt->close();
            
            // Add notification
            $notif_title = 'New Walk-in Check-in';
            $notif_type = 'success';
            $notif_message = 'Walk-in guest ' . htmlspecialchars($guest_name) . ' checked into ' . htmlspecialchars($accommodation_name) . ' for ' . $nights . ' night(s). ₱' . number_format($total_cost, 2) . ' collected via ' . $payment_method . '.';
            $n_stmt = $conn->prepare("INSERT INTO notifications (title, message, type, booking_id) VALUES (?, ?, ?, ?)");
            $n_stmt->bind_param("sssi", $notif_title, $notif_message, $notif_type, $booking_id);
            $n_stmt->execute();
            $n_stmt->close();
            
            // Audit Log
            $admin_user = $_SESSION['admin_username'] ?? 'Reception';
            log_activity($conn, $admin_user, 'Walk-in Check-in', 'Checked in walk-in guest ' . $guest_name . ' into ' . $accommodation_name . ' (Booking #' . $booking_id . ')');
            
            $print_params = "&print_rcpt=1&bid=" . urlencode($booking_id) . "&txn=" . urlencode($txn_id) . "&method=" . urlencode($payment_method) . "&amount=" . urlencode($total_cost) . "&tendered=" . urlencode($amount_tendered) . "&change=" . urlencode($change_amount);
            
            header("Location: checkin?success=walkin" . $print_params);
            exit;
        }
    }
}

// Check if a specific QR code token was scanned
$ref = isset($_GET['ref']) ? $_GET['ref'] : '';
$token = isset($_GET['token']) ? $_GET['token'] : '';
$specific_booking = null;

if (!empty($ref) && !empty($token)) {
    // Validate token and compute cost details
    $stmt = $conn->prepare("
        SELECT 
            b.*,
            DATEDIFF(b.check_out, b.check_in) AS nights,
            COALESCE(r.price_per_night, rt.price, 0) AS price_per_night,
            (DATEDIFF(b.check_out, b.check_in) * COALESCE(r.price_per_night, rt.price, 0)) AS total_cost,
            (SELECT COALESCE(SUM(p.amount), 0) FROM payments p WHERE p.booking_id = b.id AND p.status = 'verified') AS amount_paid
        FROM bookings b
        LEFT JOIN rooms r ON b.room_id = r.id
        LEFT JOIN room_types rt ON b.room_type_id = rt.id
        WHERE b.checkin_token = ? AND b.status IN ('Pending', 'Confirmed')
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $specific_booking = $result->fetch_assoc();
    }
    $stmt->close();
}

// Fetch all available/ready rooms for Walk-in selector (rooms not currently occupied or booked for today)
$available_rooms_query = $conn->query("
    SELECT r.id, r.room_number, r.name, r.type, r.price_per_night, r.capacity, rt.id AS room_type_id
    FROM rooms r
    LEFT JOIN room_types rt ON r.type = rt.name
    WHERE r.status = 'ready'
      AND r.id NOT IN (
          SELECT b.room_id 
          FROM bookings b 
          WHERE b.room_id IS NOT NULL 
            AND b.status IN ('Checked In', 'Confirmed', 'Pending', 'Pending Payment')
            AND b.check_in <= CURDATE()
            AND b.check_out > CURDATE()
      )
    ORDER BY r.room_number ASC
");
$available_rooms = $available_rooms_query ? $available_rooms_query->fetch_all(MYSQLI_ASSOC) : [];

// Fetch all pending/confirmed reservations with rate and payment balance details
$bookings_query = $conn->query("
    SELECT 
        b.id, b.guest_name, b.guest_email, b.guest_type, b.accommodation_name, b.check_in, b.check_out, b.status,
        DATEDIFF(b.check_out, b.check_in) AS nights,
        COALESCE(r.price_per_night, rt.price, 0) AS price_per_night,
        (DATEDIFF(b.check_out, b.check_in) * COALESCE(r.price_per_night, rt.price, 0)) AS total_cost,
        (SELECT COALESCE(SUM(p.amount), 0) FROM payments p WHERE p.booking_id = b.id AND p.status = 'verified') AS amount_paid
    FROM bookings b
    LEFT JOIN rooms r ON b.room_id = r.id
    LEFT JOIN room_types rt ON b.room_type_id = rt.id
    WHERE b.status IN ('Pending', 'Confirmed') 
    ORDER BY b.check_in ASC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/jpeg" href="assets/logo.jpg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <title>Guest Check-in — Santa Fe Beach Club</title>
    <link rel="stylesheet" href="assets/css/dashboard.css?v=4">
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .reservations-table {
            width: 100%;
            min-width: 750px;
            border-collapse: collapse;
        }
        .reservations-table th, .reservations-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .reservations-table th {
            color: #888;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 12px;
        }
        .btn-checkin {
            background-color: #2E7D32;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
        }
        .btn-checkin:hover {
            background-color: #1B5E20;
        }
        .alert-success {
            background-color: #E8F5E9;
            color: #2E7D32;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-weight: 600;
        }
        .specific-checkin-card {
            background-color: #FDF4EC;
            border: 2px solid var(--color-primary);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        /* Balance payment modal and method selector */
        .gcash-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.55);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }
        .gcash-overlay.active { display: flex; }
        .gcash-modal {
            background: #fff;
            border-radius: 16px;
            padding: 32px 36px;
            max-width: 440px;
            width: 90%;
            text-align: left;
            box-shadow: 0 8px 40px rgba(0,0,0,0.18);
            animation: popIn 0.22s ease;
        }
        @keyframes popIn {
            from { transform: scale(0.88); opacity: 0; }
            to   { transform: scale(1);    opacity: 1; }
        }
        .btn-receipt {
            background-color: transparent;
            color: #666;
            border: 1px solid #ccc;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
        }
        .btn-receipt:hover {
            background-color: #f5f5f5;
        }
        .pay-method-card {
            cursor: pointer;
            display: block;
        }
        .pay-method-card input:checked + .pay-card-inner {
            border-color: #2E7D32;
            background: #E8F5E9;
            color: #1B5E20;
        }
        .pay-card-inner {
            border: 2px solid #E5E7EB;
            border-radius: 8px;
            padding: 12px 8px;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        .pay-card-inner:hover {
            border-color: #BDC3C7;
            background: #FAFAFA;
        }
        .pay-card-inner .icon { font-size: 20px; }
        .pay-card-inner .label { font-size: 12px; }

    </style>
</head>
<body>

    <?php $active_page = 'checkin'; include __DIR__ . '/partials/_sidebar.php'; ?>

    <!-- Main Dashboard Panel -->
    <main class="main-content">
        <!-- Top Bar (shared component, same as Dashboard) -->
        <?php
        $page_title = 'Check-in';
        $page_subtitle = 'Process arriving guests';
        $header_extra_html = '
            <div class="search-wrapper">
                <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <input type="text" placeholder="Search by name or ref..." class="search-input" id="reservationSearch" oninput="filterCheckinTable()">
            </div>
        ';
        include __DIR__ . '/partials/_page_header.php';
        ?>

        <section class="dashboard-grid" style="grid-template-columns: 1fr;">
            
            <?php if (isset($_GET['success'])): ?>
                <?php if ($_GET['success'] === 'walkin'): ?>
                    <div class="alert-success" style="display:flex; align-items:center; gap:8px;">
                        <span>⚡</span>
                        <span>Walk-in guest successfully registered and checked in! Room is now marked occupied.</span>
                    </div>
                <?php else: ?>
                    <div class="alert-success">
                        Guest successfully checked in!
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($specific_booking): ?>
                <?php
                $spec_id = htmlspecialchars($specific_booking['id']);
                $spec_name = htmlspecialchars($specific_booking['guest_name']);
                $spec_acc = htmlspecialchars($specific_booking['accommodation_name']);
                $spec_total = floatval($specific_booking['total_cost']);
                $spec_paid = floatval($specific_booking['amount_paid']);
                $spec_balance = max(0, $spec_total - $spec_paid);
                ?>
                <div class="specific-checkin-overlay" style="position: fixed; inset: 0; background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); display: flex; align-items: center; justify-content: center; z-index: 10000;">
                    <div style="background: #ffffff; border-radius: 22px; width: 100%; max-width: 440px; padding: 34px 28px 26px; box-shadow: 0 25px 60px -15px rgba(15, 23, 42, 0.28), 0 0 1px rgba(15, 23, 42, 0.1); position: relative; text-align: center; animation: popIn 0.25s cubic-bezier(0.16, 1, 0.3, 1);">
                        <a href="checkin" style="position: absolute; top: 16px; right: 16px; width: 32px; height: 32px; border-radius: 50%; background: #F1F5F9; color: #64748B; display: flex; align-items: center; justify-content: center; text-decoration: none; font-size: 18px; font-weight: 600; transition: all 0.2s;" onmouseover="this.style.background='#E2E8F0'; this.style.color='#0F172A';" onmouseout="this.style.background='#F1F5F9'; this.style.color='#64748B';" title="Close">&times;</a>
                        
                        <div style="width: 70px; height: 70px; border-radius: 50%; margin: 0 auto 18px; overflow: hidden; border: 3px solid #EFE4D6; box-shadow: 0 10px 25px -5px rgba(200, 153, 111, 0.4); background: #ffffff; display: flex; align-items: center; justify-content: center;">
                            <img src="assets/logo.jpg" alt="Santa Fe Beach Club" style="width: 100%; height: 100%; object-fit: cover;">
                        </div>
                        
                        <h2 style="margin: 0 0 6px; font-size: 21px; font-weight: 800; color: #0F172A; letter-spacing: -0.01em;">Guest Scanned!</h2>
                        <p style="margin: 0 0 20px; color: #64748B; font-size: 13.5px;">Reservation identified and verified successfully.</p>
                        
                        <div style="background: #F8FAFC; border: 1.5px solid #E2E8F0; border-radius: 14px; padding: 14px 16px; margin-bottom: 18px; text-align: left;">
                            <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 10px; margin-bottom: 10px; border-bottom: 1px dashed #E2E8F0;">
                                <span style="font-size: 12px; color: #64748B; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Guest</span>
                                <strong style="font-size: 14px; color: #0F172A; font-weight: 800;"><?php echo $spec_name; ?></strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 10px; margin-bottom: 10px; border-bottom: 1px dashed #E2E8F0;">
                                <span style="font-size: 12px; color: #64748B; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Room</span>
                                <strong style="font-size: 13.5px; color: #0F172A; font-weight: 700;"><?php echo $spec_acc; ?></strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span style="font-size: 12px; color: #64748B; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Booking Ref</span>
                                <span style="background: #EFF6FF; color: #1D4ED8; font-weight: 800; font-size: 12px; padding: 3px 8px; border-radius: 6px; letter-spacing: 0.5px; font-family: monospace;"><?php echo htmlspecialchars($ref); ?></span>
                            </div>
                        </div>
                        
                        <?php if ($spec_balance > 0): ?>
                            <div style="background: linear-gradient(135deg, #FFF7ED 0%, #FFEDD5 100%); border: 1.5px solid #FDBA74; border-radius: 14px; padding: 14px 16px; margin-bottom: 22px; text-align: left; display: flex; align-items: center; justify-content: space-between;">
                                <div>
                                    <div style="font-size: 11px; font-weight: 800; color: #9A3412; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px;">⚠️ Balance Due</div>
                                    <div style="font-size: 12px; color: #C2410C; font-weight: 500;">Collect before room turnover</div>
                                </div>
                                <div style="font-size: 20px; font-weight: 900; color: #C2410C; letter-spacing: -0.02em;">₱<?php echo number_format($spec_balance, 2); ?></div>
                            </div>
                        <?php else: ?>
                            <div style="background: linear-gradient(135deg, #F0FDF4 0%, #DCFCE7 100%); border: 1.5px solid #86EFAC; border-radius: 14px; padding: 14px 16px; margin-bottom: 22px; text-align: left; display: flex; align-items: center; justify-content: space-between;">
                                <div>
                                    <div style="font-size: 11px; font-weight: 800; color: #166534; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px;">Payment Status</div>
                                    <div style="font-size: 12px; color: #15803D; font-weight: 500;">All charges fully settled</div>
                                </div>
                                <span style="background: #15803D; color: #fff; font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 6px; letter-spacing: 0.5px;">✓ PAID IN FULL</span>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="checkin" style="margin: 0;">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="checkin">
                            <input type="hidden" name="booking_id" value="<?php echo $spec_id; ?>">
                            <button type="submit" style="width: 100%; padding: 13px 0; background: linear-gradient(135deg, #15803D, #166534); color: #ffffff; border: none; border-radius: 12px; font-size: 15px; font-weight: 800; cursor: pointer; box-shadow: 0 4px 16px rgba(22, 101, 52, 0.35); display: flex; align-items: center; justify-content: center; gap: 8px; transition: transform 0.15s ease, box-shadow 0.15s ease;" onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 6px 20px rgba(22, 101, 52, 0.45)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 16px rgba(22, 101, 52, 0.35)';" onclick="handleCheckinClick(event, <?php echo $spec_id; ?>, '<?php echo addslashes($spec_name); ?>', '<?php echo addslashes($spec_acc); ?>', <?php echo $spec_total; ?>, <?php echo $spec_balance; ?>)">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                Check In Guest Now
                            </button>
                        </form>
                    </div>
                </div>
            <?php elseif (!empty($ref)): ?>
                <div class="alert-success" style="background-color: #FFEBEE; color: #C62828;">
                    Invalid or already processed QR code token.
                </div>
            <?php endif; ?>

            <div class="card" style="background:#fff; border-radius:12px; padding:24px; box-shadow:0 1px 3px rgba(0,0,0,0.05); margin-top:20px;">
                <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:20px; border-bottom:1px solid #F3F4F6; padding-bottom:16px;">
                    <div>
                        <h2 style="font-size:20px; font-weight:800; color:#1F2937; margin:0;">Pending Arrivals</h2>
                        <p style="color:#6B7280; font-size:13px; margin:4px 0 0;"><?php echo count($available_rooms); ?> room(s) currently ready for walk-in guests</p>
                    </div>
                    <div style="display:flex; gap:10px; flex-wrap:wrap;">
                        <button type="button" class="btn-walkin-primary" onclick="openWalkinModal()" style="display:inline-flex; align-items:center; gap:8px; background:#7C533C; color:#ffffff; border:none; padding:11px 22px; border-radius:8px; font-weight:700; font-size:14px; cursor:pointer; box-shadow:0 4px 12px rgba(124,83,60,0.3); transition:all 0.2s ease;" onmouseover="this.style.background='#5C3D2B'" onmouseout="this.style.background='#7C533C'">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                            <span>⚡ Express Walk-in Check-in</span>
                        </button>
                    </div>

                <div class="table-responsive">
                <table class="reservations-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Guest Name</th>
                            <th>Email</th>
                            <th>Type</th>
                            <th>Accommodation</th>
                            <th>Check-in Date</th>
                            <th>Balance Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($bookings_query && $bookings_query->num_rows > 0) {
                            while ($row = $bookings_query->fetch_assoc()) {
                                $id = htmlspecialchars($row['id']);
                                $name = htmlspecialchars($row['guest_name']);
                                $email = htmlspecialchars($row['guest_email']);
                                $type = htmlspecialchars($row['guest_type']);
                                $accommodation = htmlspecialchars($row['accommodation_name']);
                                $checkin = htmlspecialchars($row['check_in']);
                                $total_cost = floatval($row['total_cost']);
                                $amount_paid = floatval($row['amount_paid']);
                                $balance_due = max(0, $total_cost - $amount_paid);

                                echo "<tr>";
                                echo "<td>#{$id}</td>";
                                echo "<td><strong>{$name}</strong></td>";
                                echo "<td>{$email}</td>";
                                echo "<td>{$type}</td>";
                                echo "<td>{$accommodation}</td>";
                                echo "<td>{$checkin}</td>";
                                
                                // Render balance badge
                                if ($balance_due > 0) {
                                    echo "<td><span style='background: #FFF3E0; color: #E65100; padding: 4px 8px; border-radius: 4px; font-weight: 700; font-size: 12px;'>₱" . number_format($balance_due, 2) . " Due</span></td>";
                                } else {
                                    echo "<td><span style='background: #E8F5E9; color: #2E7D32; padding: 4px 8px; border-radius: 4px; font-weight: 700; font-size: 12px;'>Paid In Full</span></td>";
                                }
                                
                                $csrf = htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8');
                                echo "<td>
                                    <form method='POST' action='checkin' style='display:inline;'>
                                        <input type='hidden' name='csrf_token' value='{$csrf}'>
                                        <input type='hidden' name='action' value='checkin'>
                                        <input type='hidden' name='booking_id' value='{$id}'>
                                        <button type='submit' class='btn-checkin' onclick=\"handleCheckinClick(event, {$id}, '" . addslashes($name) . "', '" . addslashes($accommodation) . "', {$total_cost}, {$balance_due})\">Check-in</button>
                                    </form>
                                </td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='8' style='text-align: center; color: #888; padding: 30px 20px;'>No pending arrivals</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
                </div>
            </div>
        </section>
    </main>

    <!-- Walk-in / Express Check-in Modal -->
    <div class="gcash-overlay" id="walkinModalOverlay">
        <div class="gcash-modal" style="max-width: 580px;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; border-bottom: 1px solid #eee; padding-bottom: 14px;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 42px; height: 42px; background: #F2EBE5; border-radius: 10px; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                        <img src="assets/logo.jpg" alt="Santa Fe Beach Club logo" style="width: 100%; height: 100%; object-fit: cover;">
                    </div>
                    <div>
                        <h2 style="font-size: 19px; font-weight: 800; color: #1f2937; margin: 0;">Express Walk-in Check-in</h2>
                        <p style="color: #6b7280; font-size: 13px; margin: 2px 0 0;">Register, collect payment, &amp; check in arriving guest</p>
                    </div>
                </div>
                <button type="button" onclick="closeWalkinModal()" style="background:none; border:none; font-size:22px; color:#9ca3af; cursor:pointer; line-height:1;">&times;</button>
            </div>

            <form method="POST" id="walkinForm" action="checkin">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="walkin_checkin">
                
                <!-- Guest Information -->
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                    <div style="grid-column: span 2;">
                        <label style="display:block; font-size:11px; font-weight:700; color:#374151; margin-bottom:5px; text-transform:uppercase; letter-spacing:0.5px;">Guest Full Name *</label>
                        <input type="text" name="guest_name" id="walkinGuestName" required placeholder="e.g. Maria Santos" style="text-transform: capitalize; width:100%; padding:10px 12px; border:1.5px solid #D1D5DB; border-radius:8px; font-size:14px; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:11px; font-weight:700; color:#374151; margin-bottom:5px; text-transform:uppercase; letter-spacing:0.5px;">Contact Phone</label>
                        <input type="tel" name="guest_phone" id="walkinGuestPhone" placeholder="0917 123 4567" style="width:100%; padding:10px 12px; border:1.5px solid #D1D5DB; border-radius:8px; font-size:14px; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:11px; font-weight:700; color:#374151; margin-bottom:5px; text-transform:uppercase; letter-spacing:0.5px;">Email (Optional)</label>
                        <input type="email" name="guest_email" id="walkinGuestEmail" placeholder="guest@example.com" style="width:100%; padding:10px 12px; border:1.5px solid #D1D5DB; border-radius:8px; font-size:14px; box-sizing:border-box;">
                    </div>
                </div>

                <!-- Stay Details -->
                <div style="background:#F9FAFB; border:1px solid #E5E7EB; border-radius:10px; padding:14px; margin-bottom:16px;">
                    <div style="margin-bottom:12px;">
                        <label style="display:block; font-size:11px; font-weight:700; color:#374151; margin-bottom:5px; text-transform:uppercase; letter-spacing:0.5px;">Select Available Room *</label>
                        <select name="room_id" id="walkinRoomSelect" required onchange="onWalkinRoomChange()" style="width:100%; padding:10px 12px; border:1.5px solid #D1D5DB; border-radius:8px; font-size:14px; font-weight:600; background:#fff;">
                            <?php if (empty($available_rooms)): ?>
                                <option value="" disabled selected>No ready rooms available right now</option>
                            <?php else: ?>
                                <option value="" disabled selected>-- Choose an available room --</option>
                                <?php foreach ($available_rooms as $r): ?>
                                    <option value="<?php echo $r['id']; ?>" 
                                            data-price="<?php echo $r['price_per_night']; ?>" 
                                            data-capacity="<?php echo $r['capacity']; ?>" 
                                            data-name="<?php echo htmlspecialchars($r['name']); ?>">
                                        <?php echo htmlspecialchars($r['name']) . ' (₱' . number_format($r['price_per_night'], 2) . '/nt - Max ' . $r['capacity'] . ' Guests)'; ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:10px;">
                        <div>
                            <label style="display:block; font-size:11px; font-weight:700; color:#374151; margin-bottom:5px; text-transform:uppercase;">Check-in</label>
                            <input type="date" name="check_in" id="walkinCheckIn" value="<?php echo date('Y-m-d'); ?>" min="<?php echo date('Y-m-d'); ?>" onchange="updateWalkinTotals()" style="width:100%; padding:8px 10px; border:1px solid #D1D5DB; border-radius:6px; font-size:13px; box-sizing:border-box;">
                        </div>
                        <div>
                            <label style="display:block; font-size:11px; font-weight:700; color:#374151; margin-bottom:5px; text-transform:uppercase;">Check-out</label>
                            <input type="date" name="check_out" id="walkinCheckOut" value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" onchange="updateWalkinTotals()" style="width:100%; padding:8px 10px; border:1px solid #D1D5DB; border-radius:6px; font-size:13px; box-sizing:border-box;">
                        </div>
                        <div>
                            <label style="display:block; font-size:11px; font-weight:700; color:#374151; margin-bottom:5px; text-transform:uppercase;">Guests</label>
                            <input type="number" name="guests_count" id="walkinGuestsCount" value="2" min="1" max="10" style="width:100%; padding:8px 10px; border:1px solid #D1D5DB; border-radius:6px; font-size:13px; box-sizing:border-box;">
                        </div>
                    </div>

                    <!-- Live Cost Banner -->
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:12px; padding-top:10px; border-top:1px dashed #D1D5DB;">
                        <div style="font-size:13px; color:#4B5563;">
                            <span id="walkinNightsDisplay">1 night</span> &bull; <span id="walkinRateDisplay">₱0.00/night</span>
                        </div>
                        <div style="text-align:right;">
                            <span style="font-size:11px; color:#6B7280; text-transform:uppercase; font-weight:700; display:block;">Total Amount</span>
                            <strong style="font-size:19px; color:#7C533C; font-weight:800;" id="walkinTotalCostDisplay">₱0.00</strong>
                        </div>
                    </div>
                </div>

                <!-- Payment Method Section -->
                <div style="margin-bottom: 16px;">
                    <label style="display:block; font-size:11px; font-weight:700; color:#374151; margin-bottom:8px; text-transform:uppercase; letter-spacing:0.5px;">Payment Method</label>
                    <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap: 10px;">
                        <label class="pay-method-card">
                            <input type="radio" name="payment_method" value="Front Desk Cash" checked style="display:none;" onchange="updateWalkinPaySelectors()">
                            <div class="pay-card-inner">
                                <span class="icon">💵</span>
                                <span class="label">Cash</span>
                            </div>
                        </label>
                        <label class="pay-method-card">
                            <input type="radio" name="payment_method" value="GCash QR" style="display:none;" onchange="updateWalkinPaySelectors()">
                            <div class="pay-card-inner">
                                <span class="icon"><img src="assets/images/gcash_logo.png?v=<?= time(); ?>" alt="GCash" style="width:22px; height:22px; border-radius:4px; object-fit:contain; vertical-align:middle;"></span>
                                <span class="label">GCash</span>
                            </div>
                        </label>
                        <label class="pay-method-card">
                            <input type="radio" name="payment_method" value="Front Desk Card" style="display:none;" onchange="updateWalkinPaySelectors()">
                            <div class="pay-card-inner">
                                <span class="icon">💳</span>
                                <span class="label">Card</span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Cash Tendered -->
                <div id="walkinCashSection" style="margin-bottom:16px;">
                    <label style="display:block; font-size:11px; font-weight:700; color:#555; margin-bottom:6px; text-transform:uppercase;">Amount Tendered</label>
                    <input type="number" step="0.01" id="walkinAmountTendered" name="amount_tendered" placeholder="0.00" style="width:100%; padding:10px 12px; border:1.5px solid #E5E7EB; border-radius:8px; font-size:15px; font-weight:600; box-sizing:border-box;" oninput="calculateWalkinChange()">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:8px; background:#FDF8F5; border:1px solid #EAD8CE; border-radius:8px; padding:8px 12px;">
                        <span style="font-size:13px; color:#7C533C; font-weight:600;">Change</span>
                        <div style="display:flex; align-items:center;">
                            <span style="font-size:17px; font-weight:800; color:#7C533C;">&#8369;</span>
                            <input type="text" id="walkinChangeAmount" name="change_amount" value="0.00" readonly style="border:none; background:transparent; font-size:17px; font-weight:800; color:#7C533C; width:90px; text-align:right;">
                        </div>
                    </div>
                </div>

                <!-- GCash Reference -->
                <div id="walkinGcashSection" style="display:none; margin-bottom:16px; text-align:center;">
                    <p style="font-size:12px; color:#555; margin-bottom:8px;">Present QR to guest or send payment to GCash:</p>
                    <div style="display:inline-block; background:#f0f4ff; border:2px dashed #007AFF; border-radius:10px; padding:10px; margin-bottom:8px;">
                        <img src="assets/gcash_qr.png?v=<?= time(); ?>" alt="GCash QR Code" width="130" height="130" style="display:block;" onerror="this.src='https://api.qrserver.com/v1/create-qr-code/?size=130x130&amp;data=GCash%3A09505223146%20Santa+Fe+Beach+Club'">
                    </div>
                    <div style="background:#f0f4ff; border-radius:8px; padding:6px 14px; margin:0 auto 10px; max-width:240px; font-weight:700; color:#007AFF; font-size:15px;">
                        09505223146
                    </div>
                    <div style="text-align:left;">
                        <label style="display:block; font-size:11px; font-weight:700; color:#555; margin-bottom:4px; text-transform:uppercase;">GCash Reference Number</label>
                        <input type="text" name="walkin_reference_number_gcash" placeholder="e.g. 1234 5678 9012" style="width:100%; padding:9px 12px; border:1.5px solid #E5E7EB; border-radius:8px; font-size:13px; box-sizing:border-box;">
                    </div>
                </div>

                <!-- Card Reference -->
                <div id="walkinCardSection" style="display:none; margin-bottom:16px;">
                    <label style="display:block; font-size:11px; font-weight:700; color:#555; margin-bottom:6px; text-transform:uppercase;">Card Reference / Approval Code</label>
                    <input type="text" name="walkin_reference_number_card" placeholder="e.g. AUTH-123456" style="width:100%; padding:9px 12px; border:1.5px solid #E5E7EB; border-radius:8px; font-size:13px; box-sizing:border-box;">
                </div>

                <input type="hidden" name="reference_number" id="walkinFinalReferenceNumber" value="">

                <button type="submit" id="btnSubmitWalkin" <?php echo empty($available_rooms) ? 'disabled' : ''; ?> style="width:100%; background:#7C533C; color:#fff; border:none; padding:13px; border-radius:10px; font-size:15px; font-weight:700; cursor:pointer; margin-bottom:8px; box-shadow:0 2px 8px rgba(124,83,60,0.3);" onmouseover="this.style.background='#5C3D2B'" onmouseout="this.style.background='#7C533C'">
                    ⚡ Complete Walk-in Check-in
                </button>
                <button type="button" onclick="closeWalkinModal()" style="width:100%; background:transparent; border:1px solid #E5E7EB; color:#666; padding:9px; border-radius:10px; font-size:13px; font-weight:600; cursor:pointer;">
                    Cancel
                </button>
            </form>
        </div>
    </div>

    <!-- Balance Collection Modal -->
    <div class="gcash-overlay" id="balanceModalOverlay">
        <div class="gcash-modal">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                <div style="width: 40px; height: 40px; background: #E8F5E9; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #2E7D32; flex-shrink: 0;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="1" x2="12" y2="23"></line>
                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                    </svg>
                </div>
                <div>
                    <h2 style="font-size: 20px; font-weight: 800; color: #333; margin: 0;">Collect Balance Payment</h2>
                    <p style="color: #666; font-size: 13px; margin: 2px 0 0;">Verify payment details before guest check-in</p>
                </div>
            </div>
            
            <div style="background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 12px; padding: 16px; margin-bottom: 20px; color: #333;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px;">
                    <span style="color: #666;">Guest Name:</span>
                    <strong id="modalGuestName">John Doe</strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px;">
                    <span style="color: #666;">Accommodation:</span>
                    <strong id="modalAccName">Standard Room</strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 14px; border-bottom: 1px dashed #E5E7EB; padding-bottom: 12px;">
                    <span style="color: #666;">Total Cost:</span>
                    <strong id="modalTotalCost">₱0.00</strong>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="color: #D32F2F; font-weight: 700; font-size: 15px;">Balance Due:</span>
                    <strong style="color: #D32F2F; font-size: 22px; font-weight: 800;" id="modalBalanceDue">₱0.00</strong>
                </div>
            </div>
            
            <form method="POST" id="balanceForm" action="checkin">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="checkin">
                <input type="hidden" name="booking_id" id="modalBookingId" value="">
                <input type="hidden" name="collect_payment" value="1">
                <input type="hidden" name="balance_amount" id="modalBalanceAmountInput" value="">
                
                <div style="margin-bottom: 24px;">
                    <label style="display: block; font-size: 11px; font-weight: 700; color: #555; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px;">Select Payment Method</label>
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;">
                        <label class="pay-method-card">
                            <input type="radio" name="payment_method" value="Front Desk Cash" checked style="display:none;" onchange="updatePaySelectors()">
                            <div class="pay-card-inner">
                                <span class="icon">💵</span>
                                <span class="label">Cash</span>
                            </div>
                        </label>
                        <label class="pay-method-card">
                            <input type="radio" name="payment_method" value="GCash QR" style="display:none;" onchange="updatePaySelectors()">
                            <div class="pay-card-inner">
                                <span class="icon"><img src="assets/images/gcash_logo.png?v=<?= time(); ?>" alt="GCash" style="width:22px; height:22px; border-radius:4px; object-fit:contain; vertical-align:middle;"></span>
                                <span class="label">GCash</span>
                            </div>
                        </label>
                        <label class="pay-method-card">
                            <input type="radio" name="payment_method" value="Front Desk Card" style="display:none;" onchange="updatePaySelectors()">
                            <div class="pay-card-inner">
                                <span class="icon">💳</span>
                                <span class="label">Card</span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Cash Section -->
                <div id="cashSection" style="margin-bottom:18px;">
                    <label style="display:block; font-size:11px; font-weight:700; color:#555; margin-bottom:6px; text-transform:uppercase; letter-spacing:0.5px;">Amount Tendered</label>
                    <input type="number" step="0.01" id="amountTendered" name="amount_tendered" placeholder="0.00" style="width:100%; padding:10px 12px; border:1.5px solid #E5E7EB; border-radius:8px; font-size:15px; font-weight:600; box-sizing:border-box;" oninput="calculateChange()">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:10px; background:#F0FFF4; border:1px solid #A7F3D0; border-radius:8px; padding:10px 14px;">
                        <span style="font-size:13px; color:#065F46; font-weight:600;">Change</span>
                        <div style="display:flex; align-items:center;">
                            <span style="font-size:18px; font-weight:800; color:#065F46;">&#8369;</span>
                            <input type="text" id="changeAmount" name="change_amount" value="0.00" readonly style="border:none; background:transparent; font-size:18px; font-weight:800; color:#065F46; width:80px; text-align:right;">
                        </div>
                    </div>
                </div>

                <!-- GCash Section -->
                <div id="gcashSection" style="display:none; margin-bottom:18px; text-align:center;">
                    <p style="font-size:13px; color:#555; margin-bottom:12px;">Ask guest to scan the QR code or send to the number below, then enter the reference number.</p>
                    <div style="display:inline-block; background:#f0f4ff; border:2px dashed #007AFF; border-radius:12px; padding:14px; margin-bottom:12px;">
                        <img src="assets/gcash_qr.png?v=<?= time(); ?>" alt="GCash QR Code" width="160" height="160" style="display:block;" onerror="this.src='https://api.qrserver.com/v1/create-qr-code/?size=160x160&amp;data=GCash%3A09505223146%20Santa+Fe+Beach+Club'">
                    </div>
                    <div style="background:#f0f4ff; border-radius:10px; padding:10px 20px; margin:0 auto 12px; max-width:260px;">
                        <div style="font-size:11px; color:#888; text-transform:uppercase; letter-spacing:0.5px;">GCash Number</div>
                        <div style="font-size:22px; font-weight:800; color:#007AFF; letter-spacing:2px;">09505223146</div>
                        <div style="font-size:13px; color:#333; font-weight:600; margin-top:2px;">Santa Fe Beach Club</div>
                    </div>
                    <div style="text-align:left; margin-bottom:6px;">
                        <label style="display:block; font-size:11px; font-weight:700; color:#555; margin-bottom:6px; text-transform:uppercase; letter-spacing:0.5px;">GCash Reference Number</label>
                        <input type="text" name="reference_number_gcash" placeholder="e.g. 1234 5678 9012" style="width:100%; padding:10px 12px; border:1.5px solid #E5E7EB; border-radius:8px; font-size:14px; box-sizing:border-box;">
                    </div>
                </div>

                <!-- Card Section -->
                <div id="cardSection" style="display:none; margin-bottom:18px;">
                    <label style="display:block; font-size:11px; font-weight:700; color:#555; margin-bottom:6px; text-transform:uppercase; letter-spacing:0.5px;">Card Reference / Approval Code</label>
                    <input type="text" name="reference_number_card" placeholder="e.g. AUTH-123456" style="width:100%; padding:10px 12px; border:1.5px solid #E5E7EB; border-radius:8px; font-size:14px; box-sizing:border-box;">
                </div>

                <input type="hidden" name="reference_number" id="finalReferenceNumber" value="">
                
                <button type="submit" class="btn-checkin" style="width: 100%; font-size: 16px; padding: 14px; border-radius: 8px; font-weight: 700; border: none; cursor: pointer;">Complete Check-in</button>
                <button type="button" class="btn-receipt" onclick="closeBalanceModal()" style="width: 100%; border: none; background: transparent; color: #888; font-weight: 600; margin-top: 10px; padding: 8px; cursor: pointer;">Cancel</button>
            </form>
        </div>
    </div>

<script src="assets/js/sidebar-toggle.js"></script>
<script>
// --- Walk-in Modal Logic ---
function openWalkinModal() {
    document.getElementById('walkinModalOverlay').classList.add('active');
    onWalkinRoomChange();
    updateWalkinPaySelectors();
}

function closeWalkinModal() {
    document.getElementById('walkinModalOverlay').classList.remove('active');
}

function onWalkinRoomChange() {
    const sel = document.getElementById('walkinRoomSelect');
    if (!sel || !sel.selectedOptions.length) return;
    const opt = sel.selectedOptions[0];
    const price = parseFloat(opt.getAttribute('data-price')) || 0;
    const cap = parseInt(opt.getAttribute('data-capacity')) || 2;
    
    const guestsInput = document.getElementById('walkinGuestsCount');
    if (guestsInput) {
        guestsInput.max = cap;
        if (parseInt(guestsInput.value) > cap) guestsInput.value = cap;
    }
    
    updateWalkinTotals();
}

function updateWalkinTotals() {
    const sel = document.getElementById('walkinRoomSelect');
    let price = 0;
    if (sel && sel.selectedOptions.length) {
        price = parseFloat(sel.selectedOptions[0].getAttribute('data-price')) || 0;
    }
    
    const cinVal = document.getElementById('walkinCheckIn').value;
    const coutVal = document.getElementById('walkinCheckOut').value;
    
    let nights = 1;
    if (cinVal && coutVal) {
        const cin = new Date(cinVal);
        const cout = new Date(coutVal);
        const diffTime = cout - cin;
        nights = Math.max(1, Math.ceil(diffTime / (1000 * 60 * 60 * 24)));
    }
    
    const total = nights * price;
    
    document.getElementById('walkinNightsDisplay').textContent = nights + (nights === 1 ? ' night' : ' nights');
    document.getElementById('walkinRateDisplay').textContent = '₱' + price.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '/night';
    document.getElementById('walkinTotalCostDisplay').textContent = '₱' + total.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    
    calculateWalkinChange();
}

function updateWalkinPaySelectors() {
    const radios = document.querySelectorAll('#walkinForm input[name="payment_method"]');
    document.getElementById('walkinCashSection').style.display = 'none';
    document.getElementById('walkinGcashSection').style.display = 'none';
    document.getElementById('walkinCardSection').style.display = 'none';
    
    radios.forEach(radio => {
        const inner = radio.nextElementSibling;
        if (radio.checked) {
            inner.style.borderColor = '#2E7D32';
            inner.style.background = '#E8F5E9';
            inner.style.color = '#1B5E20';
            if (radio.value === 'Front Desk Cash') document.getElementById('walkinCashSection').style.display = 'block';
            if (radio.value === 'GCash QR') document.getElementById('walkinGcashSection').style.display = 'block';
            if (radio.value === 'Front Desk Card') document.getElementById('walkinCardSection').style.display = 'block';
        } else {
            inner.style.borderColor = '#E5E7EB';
            inner.style.background = '#fff';
            inner.style.color = '#333';
        }
    });
}

function calculateWalkinChange() {
    const sel = document.getElementById('walkinRoomSelect');
    let price = 0;
    if (sel && sel.selectedOptions.length) {
        price = parseFloat(sel.selectedOptions[0].getAttribute('data-price')) || 0;
    }
    const cinVal = document.getElementById('walkinCheckIn').value;
    const coutVal = document.getElementById('walkinCheckOut').value;
    let nights = 1;
    if (cinVal && coutVal) {
        const cin = new Date(cinVal);
        const cout = new Date(coutVal);
        nights = Math.max(1, Math.ceil((cout - cin) / (1000 * 60 * 60 * 24)));
    }
    const total = nights * price;
    const tendered = parseFloat(document.getElementById('walkinAmountTendered').value) || 0;
    let change = tendered - total;
    if (change < 0) change = 0;
    document.getElementById('walkinChangeAmount').value = change.toFixed(2);
}

document.getElementById('walkinForm').addEventListener('submit', function(e) {
    const sel = document.getElementById('walkinRoomSelect');
    if (!sel || !sel.value) {
        e.preventDefault();
        alert('Please select an available room for the walk-in guest.');
        return;
    }
    
    const radios = document.querySelectorAll('#walkinForm input[name="payment_method"]');
    let selected = '';
    radios.forEach(r => { if(r.checked) selected = r.value; });
    let refVal = '';
    if (selected === 'GCash QR') {
        refVal = document.querySelector('input[name="walkin_reference_number_gcash"]').value;
    } else if (selected === 'Front Desk Card') {
        refVal = document.querySelector('input[name="walkin_reference_number_card"]').value;
    }
    document.getElementById('walkinFinalReferenceNumber').value = refVal;
});

document.getElementById('walkinModalOverlay').addEventListener('click', function(e) {
    if (e.target === this) closeWalkinModal();
});

// --- Existing Balance Checkin Modal Logic ---
function handleCheckinClick(event, bookingId, guestName, accName, totalCost, balanceDue) {
    if (balanceDue > 0.01) {
        event.preventDefault();
        
        document.getElementById('modalBookingId').value = bookingId;
        document.getElementById('modalGuestName').textContent = guestName;
        document.getElementById('modalAccName').textContent = accName;
        document.getElementById('modalTotalCost').textContent = '₱' + totalCost.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('modalBalanceDue').textContent = '₱' + balanceDue.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('modalBalanceAmountInput').value = balanceDue;
        
        // Reset selected radio
        const radios = document.querySelectorAll('#balanceForm input[name="payment_method"]');
        radios[0].checked = true;
        updatePaySelectors();
        
        document.getElementById('balanceModalOverlay').classList.add('active');
    }
}

function closeBalanceModal() {
    document.getElementById('balanceModalOverlay').classList.remove('active');
}

function updatePaySelectors() {
    const radios = document.querySelectorAll('#balanceForm input[name="payment_method"]');
    document.getElementById('cashSection').style.display = 'none';
    document.getElementById('gcashSection').style.display = 'none';
    document.getElementById('cardSection').style.display = 'none';
    radios.forEach(radio => {
        const inner = radio.nextElementSibling;
        if (radio.checked) {
            inner.style.borderColor = '#2E7D32';
            inner.style.background = '#E8F5E9';
            inner.style.color = '#1B5E20';
            if (radio.value === 'Front Desk Cash') document.getElementById('cashSection').style.display = 'block';
            if (radio.value === 'GCash QR') document.getElementById('gcashSection').style.display = 'block';
            if (radio.value === 'Front Desk Card') document.getElementById('cardSection').style.display = 'block';
        } else {
            inner.style.borderColor = '#E5E7EB';
            inner.style.background = 'white';
            inner.style.color = '#333';
        }
    });
}

function calculateChange() {
    const balanceDue = parseFloat(document.getElementById('modalBalanceAmountInput').value) || 0;
    const tendered = parseFloat(document.getElementById('amountTendered').value) || 0;
    let change = tendered - balanceDue;
    if (change < 0) change = 0;
    document.getElementById('changeAmount').value = change.toFixed(2);
}

document.getElementById('balanceForm').addEventListener('submit', function(e) {
    const radios = document.querySelectorAll('#balanceForm input[name="payment_method"]');
    let selected = '';
    radios.forEach(r => { if(r.checked) selected = r.value; });

    const balanceDue = parseFloat(document.getElementById('modalBalanceAmountInput').value) || 0;
    const tendered = parseFloat(document.getElementById('amountTendered').value) || 0;
    
    if (selected === 'Front Desk Cash' && tendered < balanceDue) {
        e.preventDefault();
        alert('Amount tendered (₱' + tendered.toFixed(2) + ') cannot be less than the balance due (₱' + balanceDue.toFixed(2) + ').');
        document.getElementById('amountTendered').focus();
        return;
    }

    let refVal = '';
    if (selected === 'GCash QR') {
        refVal = document.querySelector('input[name="reference_number_gcash"]').value;
    } else if (selected === 'Front Desk Card') {
        refVal = document.querySelector('input[name="reference_number_card"]').value;
    }
    document.getElementById('finalReferenceNumber').value = refVal;
});

// Close modal if clicked outside
document.getElementById('balanceModalOverlay').addEventListener('click', function(e) {
    if (e.target === this) closeBalanceModal();
});

// Search filter
const searchInput = document.getElementById('reservationSearch');
if (searchInput) {
    searchInput.addEventListener('input', function() {
        const query = this.value.toLowerCase();
        document.querySelectorAll('.reservations-table tbody tr').forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(query) ? '' : 'none';
        });
    });
}
</script>

<?php if (isset($_GET['print_rcpt']) && $_GET['print_rcpt'] == '1'):
    $bid = $_GET['bid'] ?? '';
    $txn = $_GET['txn'] ?? '';
    $method = $_GET['method'] ?? '';
    $amountDue = (float)($_GET['amount'] ?? 0);
    $tendered = (float)($_GET['tendered'] ?? $amountDue);
    $change = (float)($_GET['change'] ?? 0);
    
    // Get basic booking details for print
    $pr_stmt = $conn->prepare("SELECT guest_name, accommodation_name FROM bookings WHERE id = ?");
    $pr_stmt->bind_param("i", $bid);
    $pr_stmt->execute();
    $pr_res = $pr_stmt->get_result()->fetch_assoc();
    $pr_stmt->close();
    
    if ($pr_res) {
        $guest_name = htmlspecialchars($pr_res['guest_name']);
        $room = htmlspecialchars($pr_res['accommodation_name']);
        $rcpt = str_pad($bid, 4, '0', STR_PAD_LEFT);
        $dateStr = date('M d, Y');
        $timeStr = date('h:i A');
        $admin_name = $_SESSION['admin_username'] ?? 'Receptionist';
?>
<script>
window.onload = function() {
    const fmt = val => '₱' + parseFloat(val).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const sep = '-'.repeat(32);
    const printWin = window.open('', '', 'width=420,height=700');
    printWin.document.write(`<html>
    <head>
    <link rel="icon" type="image/jpeg" href="assets/logo.jpg">
        <title>Receipt - <?php echo $rcpt; ?></title>
        <style>
            @page { size: 80mm auto; margin: 0; }
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body { font-family: 'Courier New', Courier, monospace; background: #fff; color: #000; padding: 16px 8px; font-size: 13px; line-height: 1.6; width: 80mm; margin: 0; }
            .center { text-align: center; }
            .sep { color: #000; letter-spacing: 2px; margin: 6px 0; text-align: center; }
            .row { display: flex; justify-content: space-between; }
            .row strong { text-align: right; }
            .brand { font-size: 16px; font-weight: 800; letter-spacing: 1px; }
            .subtitle { font-size: 12px; color: #666; }
            .total-label { font-size: 15px; font-weight: 800; letter-spacing: 1px; margin-top: 10px; }
            .total-amount { font-size: 20px; font-weight: 900; letter-spacing: 1px; margin-bottom: 6px; }
            .footer { color: #666; font-size: 12px; margin-top: 10px; }
            .footer-note { color: #888; font-size: 11px; margin-top: 8px; }
        </style>
    </head>
    <body>
        <div class="center">
            <div class="brand">SANTA FE BEACH CLUB</div>
            <div class="subtitle">Bantayan Island, Cebu</div>
            <div class="subtitle">Official Payment Receipt</div>
        </div>
        <div class="sep">${sep}</div>
        <div class="row"><span>Receipt #:</span><strong><?php echo $rcpt; ?></strong></div>
        <div class="row"><span>Date &amp; Time:</span><strong><?php echo "$dateStr, $timeStr"; ?></strong></div>
        <div class="sep">${sep}</div>
        <div class="row"><span>Guest Name:</span><strong><?php echo addslashes($guest_name); ?></strong></div>
        <div class="row"><span>Accommodation:</span><strong><?php echo addslashes($room); ?></strong></div>
        <div class="sep">${sep}</div>
        <div class="row"><span>Payment Type:</span><strong><?php echo addslashes($method); ?></strong></div>
        <div class="row"><span>Ref/Txn #:</span><strong><?php echo addslashes($txn); ?></strong></div>
        <div class="sep">${sep}</div>
        <div class="row"><span>Amount Due:</span><strong>${fmt(<?php echo $amountDue; ?>)}</strong></div>
        <div class="row"><span>Amount Tendered:</span><strong>${fmt(<?php echo $tendered; ?>)}</strong></div>
        <div class="row"><span>Change:</span><strong>${fmt(<?php echo $change; ?>)}</strong></div>
        <div class="sep">${sep}</div>
        <div class="center">
            <div class="total-label">TOTAL PAID</div>
            <div class="total-amount">${fmt(<?php echo $amountDue; ?>)}</div>
        </div>
        <div class="sep">${sep}</div>
        <div class="row"><span>Staff:</span><strong><?php echo addslashes($admin_name); ?></strong></div>
        <div class="sep">${sep}</div>
        <div class="center footer">Thank you for staying with us!<br>Have a safe trip!</div>
        <div class="center footer-note">This is an official receipt.</div>
        <script>window.onload = function() { window.print(); }<\/script>
    </body>
    </html>`);
    printWin.document.close();
};
</script>
<?php 
    }
endif; 
?>

<script>
function filterCheckinTable() {
    const q = document.getElementById('reservationSearch').value.toLowerCase();
    document.querySelectorAll('.reservations-table tbody tr').forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(q) ? '' : 'none';
    });
}
</script>
</body>
</html>
