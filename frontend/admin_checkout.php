<?php
require_once __DIR__ . '/../backend/helpers/admin_auth_check.php';
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/helpers/checkout_notification_helper.php';

// Handle POST request to check-out
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'checkout') {
    $booking_id = intval($_POST['booking_id']);

    $stmt = $conn->prepare("UPDATE bookings SET status = 'Checked Out' WHERE id = ?");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();

    $room_query = $conn->query("SELECT room_id FROM bookings WHERE id = $booking_id");
    if ($room_query && $room_query->num_rows > 0) {
        $room_id = $room_query->fetch_assoc()['room_id'];
        if ($room_id) {
            $conn->query("UPDATE rooms SET status = 'maintenance' WHERE id = $room_id");
        }
    }

    header("Location: admin_checkout?success=1");
    exit;
}

sf_create_due_checkout_notifications($conn);
$bookings_query = $conn->query("SELECT id, guest_name, guest_email, guest_type, accommodation_name, check_in, check_out FROM bookings WHERE status = 'Checked In' ORDER BY check_out ASC");
$checkout_time = sf_get_checkout_time_setting($conn);
$current_business_time = sf_get_current_business_datetime($conn);
$property_timezone = $current_business_time->getTimezone();
$property_timezone_name = $property_timezone->getName();
$overdue_count = 0;
$overdue_guest_names = [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/svg+xml" sizes="any" href="assets/icons/favicon-circle.svg?v=2">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/admin.css?v=4">
    <style>
        .reservations-table {
            width: 100%;
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
        .btn-checkout {
            background-color: #C62828;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
        }
        .btn-checkout:hover {
            background-color: #B71C1C;
        }
        .alert-success {
            background-color: #E8F5E9;
            color: #2E7D32;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-weight: 600;
        }
        .alert-warning {
            background-color: #FFF3E0;
            color: #B45309;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-weight: 600;
        }
        .checkout-warning-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            background: #FFF3E0;
            color: #B45309;
            font-size: 12px;
            font-weight: 700;
        }
        .checkout-overdue-time {
            display: block;
            margin-top: 6px;
            color: #B45309;
            font-size: 12px;
            font-weight: 600;
        }
        .checkout-row-overdue {
            background: #FFF9F2;
        }
        .overdue-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            align-items: center;
            justify-content: center;
            z-index: 1100;
            padding: 20px;
        }
        .overdue-modal-card {
            background: white;
            width: min(100%, 460px);
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.2);
        }
        .overdue-modal-title {
            margin: 0 0 8px 0;
            color: #B45309;
            font-size: 20px;
        }
        .overdue-modal-body {
            color: #666;
            line-height: 1.5;
            margin-bottom: 16px;
        }
        .overdue-modal-list {
            margin: 10px 0 0 18px;
            padding: 0;
            color: #444;
        }
        .overdue-modal-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 18px;
        }
        .overdue-modal-btn {
            padding: 10px 18px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            background: #C62828;
            color: white;
        }
    </style>
</head>
<body>

    <?php $active_page = 'checkout'; include __DIR__ . '/partials/_sidebar.php'; ?>

    <main class="admin-main">
        <div class="admin-body">
        <?php
        $page_title = 'Admin Check-out';
        $page_subtitle = 'Process departing guests';
        $header_extra_html = '
            <div class="search-wrapper">
                <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <input type="text" placeholder="Search guests..." class="search-input" id="checkoutSearch" oninput="filterCheckoutTable()">
            </div>
        ';
        include __DIR__ . '/partials/_page_header.php';
        ?>

        <section class="dashboard-grid" style="grid-template-columns: 1fr;">
            <?php if (isset($_GET['success'])): ?>
                <div class="alert-success">Guest successfully checked out! Room is now marked for maintenance.</div>
            <?php endif; ?>
            <?php
            if ($bookings_query && $bookings_query->num_rows > 0) {
                $bookings_query->data_seek(0);
                while ($scan_row = $bookings_query->fetch_assoc()) {
                    if (sf_is_due_for_checkout((string)$scan_row['check_out'], $checkout_time, $property_timezone, $current_business_time)) {
                        $overdue_count++;
                        $overdue_guest_names[] = (string)$scan_row['guest_name'];
                    }
                }
                $bookings_query->data_seek(0);
            }
            ?>
            <?php if ($overdue_count > 0): ?>
                <div class="alert-warning">
                    <?php echo $overdue_count; ?> guest<?php echo $overdue_count === 1 ? '' : 's'; ?> <?php echo $overdue_count === 1 ? 'is' : 'are'; ?> already due for check-out as of <?php echo htmlspecialchars($current_business_time->format('M d, Y h:i A')); ?> <?php echo htmlspecialchars($property_timezone_name); ?>.
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h2>Current Guests (Pending Departure)</h2>
                </div>

                <table class="reservations-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Guest Name</th>
                            <th>Email</th>
                            <th>Accommodation</th>
                            <th>Check-in Date</th>
                            <th>Check-out Date</th>
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
                                $accommodation = htmlspecialchars($row['accommodation_name']);
                                $checkin = htmlspecialchars($row['check_in']);
                                $checkout = htmlspecialchars($row['check_out']);
                                $is_due_for_checkout = sf_is_due_for_checkout((string)$row['check_out'], $checkout_time, $property_timezone, $current_business_time);
                                $due_checkout_at = sf_get_due_checkout_datetime((string)$row['check_out'], $checkout_time, $property_timezone);
                                $due_checkout_display = htmlspecialchars($due_checkout_at->format('M d, Y h:i A'));

                                echo "<tr" . ($is_due_for_checkout ? " class='checkout-row-overdue'" : "") . ">";
                                echo "<td>#$id</td>";
                                echo "<td><strong>{$name}</strong></td>";
                                echo "<td>{$email}</td>";
                                echo "<td>{$accommodation}</td>";
                                echo "<td>{$checkin}</td>";
                                echo "<td><strong>{$checkout}</strong>";
                                if ($is_due_for_checkout) {
                                    echo "<span class='checkout-warning-badge'>Due now</span>";
                                    echo "<span class='checkout-overdue-time'>Scheduled checkout: {$due_checkout_display} " . htmlspecialchars($property_timezone_name) . "</span>";
                                }
                                echo "</td>";
                                echo "<td>
                                    <form method='POST' action='admin_checkout' id='checkout-form-{$id}' style='display:inline;'>
                                        <input type='hidden' name='action' value='checkout'>
                                        <input type='hidden' name='booking_id' value='{$id}'>
                                        <button type='button' class='btn-checkout' onclick='confirmCheckout({$id}, \"{$name}\")'>Check-out</button>
                                    </form>
                                </td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='7' style='text-align: center; color: #888; padding: 20px;'>No current guests to check out</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </section>
        </div>
    </main>

    <div id="checkoutModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index:1000; font-family: 'Inter', sans-serif;">
        <div style="background:white; padding:30px; border-radius:12px; width:420px; box-shadow:0 10px 25px rgba(0,0,0,0.2);">
            <h3 style="margin-top:0; color:#333; font-size: 20px; margin-bottom: 10px;">Confirm Check-out</h3>
            <p style="color:#666; margin-bottom:25px; line-height: 1.5; font-size: 15px;">Are you sure you want to check out <strong id="checkoutGuestName" style="color: #111;"></strong>? This action cannot be undone and will mark the room for maintenance.</p>
            <div style="display:flex; justify-content:flex-end; gap:12px;">
                <button type="button" onclick="closeCheckoutModal()" style="padding:10px 20px; border:1px solid #ddd; background:#f9f9f9; color: #444; border-radius:6px; cursor:pointer; font-weight: 600; font-size: 14px;">Cancel</button>
                <button type="button" id="confirmCheckoutBtn" style="padding:10px 20px; border:none; background:#C62828; color:white; border-radius:6px; cursor:pointer; font-weight: 600; font-size: 14px; box-shadow: 0 2px 4px rgba(198, 40, 40, 0.2);">Yes, Check Out</button>
            </div>
        </div>
    </div>

    <div id="overdueModal" class="overdue-modal" role="dialog" aria-modal="true" aria-labelledby="overdueModalTitle">
        <div class="overdue-modal-card">
            <h3 id="overdueModalTitle" class="overdue-modal-title">Overdue Check-out Warning</h3>
            <div class="overdue-modal-body">
                The following guest(s) are already due for check-out as of <strong><?php echo htmlspecialchars($current_business_time->format('M d, Y h:i A')); ?> <?php echo htmlspecialchars($property_timezone_name); ?></strong>.
                <ul id="overdueGuestList" class="overdue-modal-list"></ul>
            </div>
            <div class="overdue-modal-actions">
                <button type="button" class="overdue-modal-btn" onclick="closeOverdueModal()">Close</button>
            </div>
        </div>
    </div>

    <script>
        let currentCheckoutFormId = null;
        const overdueGuestNames = <?php echo json_encode($overdue_guest_names, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;

        function confirmCheckout(id, name) {
            currentCheckoutFormId = 'checkout-form-' + id;
            document.getElementById('checkoutGuestName').innerText = name;
            document.getElementById('checkoutModal').style.display = 'flex';
        }

        function closeCheckoutModal() {
            document.getElementById('checkoutModal').style.display = 'none';
            currentCheckoutFormId = null;
        }

        function closeOverdueModal() {
            document.getElementById('overdueModal').style.display = 'none';
        }

        document.getElementById('confirmCheckoutBtn').addEventListener('click', function() {
            if (currentCheckoutFormId) {
                document.getElementById(currentCheckoutFormId).submit();
            }
        });

        if (overdueGuestNames.length > 0) {
            const guestList = document.getElementById('overdueGuestList');
            guestList.innerHTML = overdueGuestNames.map(function(name) {
                return '<li>' + name + '</li>';
            }).join('');
            document.getElementById('overdueModal').style.display = 'flex';
        }

        function filterCheckoutTable() {
            const q = document.getElementById('checkoutSearch').value.toLowerCase();
            document.querySelectorAll('table tbody tr').forEach(row => {
                row.style.display = row.innerText.toLowerCase().includes(q) ? '' : 'none';
            });
        }
    </script>
<script src="assets/js/sidebar-toggle.js"></script>
</body>
</html>
