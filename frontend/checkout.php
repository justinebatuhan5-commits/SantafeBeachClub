<?php
require_once __DIR__ . '/../backend/helpers/auth_check.php';
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/helpers/checkout_notification_helper.php';

// Handle POST request to check-out
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'checkout') {
    require_csrf_token();
    $booking_id = intval($_POST['booking_id']);
    
    // Update booking status
    $stmt = $conn->prepare("UPDATE bookings SET status = 'Checked Out' WHERE id = ?");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $stmt->close();
    
    // Recognize revenue: update payment accounting_status to 'earned'
    $stmt = $conn->prepare("UPDATE payments SET accounting_status = 'earned' WHERE booking_id = ? AND status = 'verified'");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $stmt->close();
    
    // Also update room status to maintenance (needs cleaning)
    $stmt = $conn->prepare("SELECT room_id FROM bookings WHERE id = ?");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $room_query = $stmt->get_result();
    if ($room_query && $room_query->num_rows > 0) {
        $room_id = (int) $room_query->fetch_assoc()['room_id'];
        if ($room_id > 0) {
            $uStmt = $conn->prepare("UPDATE rooms SET status = 'maintenance' WHERE id = ?");
            $uStmt->bind_param("i", $room_id);
            $uStmt->execute();
            $uStmt->close();
        }
    }
    $stmt->close();
    
    // Redirect to avoid form resubmission
    header("Location: checkout?success=1");
    exit;
}

// Fetch all currently checked-in reservations for the list
sf_create_due_checkout_notifications($conn);
$bookings_query = $conn->query("SELECT id, guest_name, guest_email, guest_type, accommodation_name, check_in, check_out FROM bookings WHERE status = 'Checked In' ORDER BY check_out ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="192x192" href="assets/icons/icon-192x192.png?v=1">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=4">
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
    </style>
</head>
<body>

    <?php $active_page = 'checkout'; include __DIR__ . '/partials/_sidebar.php'; ?>

    <!-- Main Dashboard Panel -->
    <main class="main-content">
        <!-- Top Bar (shared component, same as Dashboard) -->
        <?php
        $page_title = 'Check-out';
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
                <div class="alert-success">
                    Guest successfully checked out! Room is now marked for maintenance.
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

                                echo "<tr>";
                                echo "<td>#{$id}</td>";
                                echo "<td><strong>{$name}</strong></td>";
                                echo "<td>{$email}</td>";
                                echo "<td>{$accommodation}</td>";
                                echo "<td>{$checkin}</td>";
                                echo "<td><strong>{$checkout}</strong></td>";
                                $csrf = htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8');
                                echo "<td>
                                    <form method='POST' action='checkout' id='checkout-form-{$id}' style='display:inline;'>
                                        <input type='hidden' name='csrf_token' value='{$csrf}'>
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
    </main>

    <!-- Custom Checkout Modal -->
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

    <script>
        let currentCheckoutFormId = null;

        function confirmCheckout(id, name) {
            currentCheckoutFormId = 'checkout-form-' + id;
            document.getElementById('checkoutGuestName').innerText = name;
            document.getElementById('checkoutModal').style.display = 'flex';
        }

        function closeCheckoutModal() {
            document.getElementById('checkoutModal').style.display = 'none';
            currentCheckoutFormId = null;
        }

        document.getElementById('confirmCheckoutBtn').addEventListener('click', function() {
            if (currentCheckoutFormId) {
                document.getElementById(currentCheckoutFormId).submit();
            }
        });
        function filterCheckoutTable() {
            const q = document.getElementById('checkoutSearch').value.toLowerCase();
            document.querySelectorAll('.reservations-table tbody tr').forEach(row => {
                row.style.display = row.innerText.toLowerCase().includes(q) ? '' : 'none';
            });
        }
    </script>
<script src="assets/js/sidebar-toggle.js"></script>
</body>
</html>
