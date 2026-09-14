<?php
require_once __DIR__ . '/../backend/helpers/admin_auth_check.php';
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/services/mailer.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Handle POST actions for notifications
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'mark_read' && isset($_POST['notif_id'])) {
        $id = intval($_POST['notif_id']);
        $conn->query("UPDATE notifications SET is_read = 1 WHERE id = $id");
    } elseif ($action === 'mark_all_read') {
        $conn->query("UPDATE notifications SET is_read = 1");
    } elseif ($action === 'delete' && isset($_POST['notif_id'])) {
        $id = intval($_POST['notif_id']);
        $conn->query("DELETE FROM notifications WHERE id = $id");
    } elseif ($action === 'create_notif') {
        $title = trim($_POST['title']);
        $message = trim($_POST['message']);
        $type = $_POST['type'];
        
        if (!empty($title) && !empty($message)) {
            $stmt = $conn->prepare("INSERT INTO notifications (title, message, type) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $title, $message, $type);
            $stmt->execute();
        }
    } elseif ($action === 'accept_booking' && isset($_POST['notif_id'], $_POST['booking_id'])) {
        $notif_id = intval($_POST['notif_id']);
        $booking_id = intval($_POST['booking_id']);

        // Pull booking + payment details needed for the confirmation email
        $stmt = $conn->prepare("
            SELECT b.guest_name, b.guest_email, b.accommodation_name, b.check_in, b.check_out, b.cancellation_token, b.checkin_token,
                   COALESCE(p.amount, 0) AS amount
            FROM bookings b
            LEFT JOIN payments p ON p.booking_id = b.id
            WHERE b.id = ?
            LIMIT 1
        ");
        $stmt->bind_param("i", $booking_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $b = $result->fetch_assoc();
            $booking_ref = 'REF-' . str_pad($booking_id, 3, '0', STR_PAD_LEFT);
            $base_url = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
            $cancellation_token = $b['cancellation_token'];
            if (empty($cancellation_token)) {
                $cancellation_token = bin2hex(random_bytes(16));
                $token_stmt = $conn->prepare("UPDATE bookings SET cancellation_token = ? WHERE id = ?");
                $token_stmt->bind_param("si", $cancellation_token, $booking_id);
                $token_stmt->execute();
                $token_stmt->close();
            }
            $cancellation_url = rtrim($base_url, '/') . '/cancel_booking?token=' . $cancellation_token;
            $checkin_url = rtrim($base_url, '/') . '/checkin?ref=' . urlencode($booking_ref) . '&token=' . urlencode($b['checkin_token'] ?? '');

            $conn->query("UPDATE notifications SET is_read = 1 WHERE id = $notif_id");

            if (empty($b['guest_email'])) {
                $_SESSION['notif_flash'] = "Notification marked as read, but no email address is on file for {$b['guest_name']} — confirmation email was not sent.";
            } else {
                $send_result = sendBookingConfirmationEmail(
                    $b['guest_email'],
                    $b['guest_name'],
                    $booking_ref,
                    $b['accommodation_name'],
                    $b['check_in'],
                    $b['check_out'],
                    (float)$b['amount'],
                    $cancellation_url,
                    $checkin_url
                );

                // Booking status intentionally stays "Pending" — accepting only
                // triggers the confirmation email, matching the check-in flow.
                $_SESSION['notif_flash'] = $send_result['success']
                    ? "Confirmation email sent to {$b['guest_email']}."
                    : "Booking accepted, but the email failed to send: " . $send_result['error'];
            }
        } else {
            $_SESSION['notif_flash'] = "Could not find that booking.";
        }
    }
    header("Location: admin_notifications");
    exit;
}

// Fetch all notifications from DB
$notifs_query = $conn->query("SELECT * FROM notifications ORDER BY created_at DESC");
$unread_count_query = $conn->query("SELECT COUNT(*) as unread FROM notifications WHERE is_read = 0");
$unread_count = ($unread_count_query) ? $unread_count_query->fetch_assoc()['unread'] : 0;

// Also fetch dynamic live stats for top alerts
$pending_checkins = $conn->query("SELECT COUNT(*) as cnt FROM bookings WHERE status = 'Pending'");
$pending_cnt = ($pending_checkins) ? $pending_checkins->fetch_assoc()['cnt'] : 0;

$maint_rooms = $conn->query("SELECT COUNT(*) as cnt FROM rooms WHERE status = 'maintenance'");
$maint_cnt = ($maint_rooms) ? $maint_rooms->fetch_assoc()['cnt'] : 0;

$notif_flash = $_SESSION['notif_flash'] ?? null;
unset($_SESSION['notif_flash']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-circle-32.png?v=3">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/favicon-circle-180.png?v=3">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Notifications - Santa Fe Beach Club</title>
    <link rel="stylesheet" href="assets/css/admin.css?v=5">
    <style>
        .notif-card-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 20px;
        }
        .notif-item {
            background: white;
            border-radius: 10px;
            padding: 20px;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 15px;
            border-left: 4px solid #ccc;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            transition: all 0.2s ease;
        }
        .notif-item.unread {
            background: #FAFAFC;
        }
        .notif-item.type-info { border-left-color: #2196F3; }
        .notif-item.type-success { border-left-color: #4CAF50; }
        .notif-item.type-warning { border-left-color: #FF9800; }
        .notif-item.type-alert { border-left-color: #F44336; }

        .notif-badge {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            padding: 3px 8px;
            border-radius: 4px;
            margin-bottom: 6px;
            display: inline-block;
        }
        .badge-info { background: #E3F2FD; color: #1565C0; }
        .badge-success { background: #E8F5E9; color: #2E7D32; }
        .badge-warning { background: #FFF3E0; color: #E65100; }
        .badge-alert { background: #FFEBEE; color: #C62828; }

        .notif-title {
            font-size: 16px;
            font-weight: 700;
            color: #333;
            margin-bottom: 4px;
        }
        .notif-msg {
            font-size: 14px;
            color: #666;
            line-height: 1.4;
        }
        .notif-time {
            font-size: 11px;
            color: #999;
            margin-top: 8px;
        }
        .notif-actions {
            display: flex;
            gap: 8px;
        }
        .btn-action-icon {
            background: none;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 6px 12px;
            font-size: 12px;
            cursor: pointer;
            color: #555;
            font-weight: 600;
        }
        .btn-action-icon:hover {
            background: #f0f0f0;
        }
        .summary-banner {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        .summary-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .summary-icon {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .card-header h2 {
            font-size: 16px;
            font-weight: 700;
            color: #1F2937;
        }

        .btn-action-icon {
            background: none;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 6px 12px;
            font-size: 12px;
            cursor: pointer;
            color: #555;
            font-weight: 600;
        }

        .btn-action-icon:hover {
            background: #f0f0f0;
        }

    </style>
</head>
<body>

    <?php $active_page = 'notifications'; include __DIR__ . '/partials/_sidebar.php'; ?>

    <!-- Main Dashboard Panel -->
    <main class="admin-main">
        <div class="admin-body">
        <!-- Top Bar (shared component, same as Dashboard) -->
        <?php
        $page_title = 'Admin Notifications';
        $page_subtitle = 'System alerts & booking updates';
        $header_extra_html = '
            <form method="POST" action="admin_notifications" style="margin: 0;">
                <input type="hidden" name="action" value="mark_all_read">
                <button type="submit" class="header-icon-btn" title="Mark All as Read" style="width: auto; padding: 0 15px; gap: 8px; font-weight: 600; font-size: 13px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    Mark All Read
                </button>
            </form>
        ';
        include __DIR__ . '/partials/_page_header.php';
        ?>

        <?php if ($notif_flash): ?>
            <div style="background:#E8F5E9; color:#2E7D32; border-radius:8px; padding:12px 18px; margin-bottom:20px; font-size:14px; font-weight:600;">
                <?php echo htmlspecialchars($notif_flash); ?>
            </div>
        <?php endif; ?>

        <!-- Summary Widgets -->
        <div class="summary-banner">
            <div class="summary-card">
                <div class="summary-icon" style="background: #2196F3;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path></svg>
                </div>
                <div>
                    <h3 style="font-size: 20px; font-weight: 700; margin: 0;"><?php echo $unread_count; ?></h3>
                    <p style="font-size: 12px; color: #777; margin: 0;">Unread Alerts</p>
                </div>
            </div>

            <div class="summary-card">
                <div class="summary-icon" style="background: #FF9800;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path></svg>
                </div>
                <div>
                    <h3 style="font-size: 20px; font-weight: 700; margin: 0;"><?php echo $pending_cnt; ?></h3>
                    <p style="font-size: 12px; color: #777; margin: 0;">Pending Check-ins</p>
                </div>
            </div>

            <div class="summary-card">
                <div class="summary-icon" style="background: #F44336;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path></svg>
                </div>
                <div>
                    <h3 style="font-size: 20px; font-weight: 700; margin: 0;"><?php echo $maint_cnt; ?></h3>
                    <p style="font-size: 12px; color: #777; margin: 0;">Rooms in Maintenance</p>
                </div>
            </div>
        </div>

        <section class="dashboard-grid" style="grid-template-columns: 2fr 1fr;">
            
            <!-- Notifications List -->
            <div class="card">
                <div class="card-header">
                    <h2>Recent Notifications</h2>
                </div>

                <div class="notif-card-list">
                    <?php if ($notifs_query && $notifs_query->num_rows > 0): ?>
                        <?php while ($n = $notifs_query->fetch_assoc()): ?>
                            <?php 
                                $id = $n['id'];
                                $title = htmlspecialchars($n['title']);
                                $msg = htmlspecialchars($n['message']);
                                $type = htmlspecialchars($n['type']);
                                $is_read = $n['is_read'];
                                $time = date('M d, Y h:i A', strtotime($n['created_at']));
                                $badgeClass = 'badge-' . $type;
                            ?>
                            <div class="notif-item type-<?php echo $type; ?> <?php echo ($is_read ? '' : 'unread'); ?>">
                                <div style="flex: 1; cursor: pointer;" onclick="showNotificationDetailModal({id: <?php echo $id; ?>, title: <?php echo json_encode($n['title']); ?>, message: <?php echo json_encode($n['message']); ?>, type: <?php echo json_encode($type); ?>, booking_id: <?php echo (int)($n['booking_id'] ?? 0); ?>, time: <?php echo json_encode($time); ?>})">
                                    <span class="notif-badge <?php echo $badgeClass; ?>"><?php echo strtoupper($type); ?></span>
                                    <div class="notif-title"><?php echo $title; ?></div>
                                    <div class="notif-msg"><?php echo $msg; ?></div>
                                    <div class="notif-time"><?php echo $time; ?></div>
                                </div>
                                <div class="notif-actions" onclick="event.stopPropagation();">
                                    <?php if (!$is_read): ?>
                                        <form method="POST" action="admin_notifications" style="margin:0;">
                                            <input type="hidden" name="action" value="mark_read">
                                            <input type="hidden" name="notif_id" value="<?php echo $id; ?>">
                                            <button type="submit" class="btn-action-icon" title="Mark as read">✓ Read</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="POST" action="admin_notifications" style="margin:0;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="notif_id" value="<?php echo $id; ?>">
                                        <button type="submit" class="btn-action-icon" style="color: #c62828;" title="Delete notification">✕</button>
                                    </form>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div style="text-align: center; color: #888; padding: 40px;">No notifications found</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Create New Broadcast Notification -->
            <div class="card">
                <div class="card-header">
                    <h2>Send Alert Broadcast</h2>
                </div>

                <form method="POST" action="admin_notifications" style="display: flex; flex-direction: column; gap: 15px; margin-top: 15px;">
                    <input type="hidden" name="action" value="create_notif">
                    
                    <div>
                        <label style="font-size: 12px; font-weight: 700; color: #555; display: block; margin-bottom: 6px;">TITLE</label>
                        <input type="text" name="title" required placeholder="e.g. VIP Transfer Request" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                    </div>

                    <div>
                        <label style="font-size: 12px; font-weight: 700; color: #555; display: block; margin-bottom: 6px;">ALERT TYPE</label>
                        <select name="type" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; background: white;">
                            <option value="info">Info (Blue)</option>
                            <option value="success">Success (Green)</option>
                            <option value="warning">Warning (Orange)</option>
                            <option value="alert">Alert (Red)</option>
                        </select>
                    </div>

                    <div>
                        <label style="font-size: 12px; font-weight: 700; color: #555; display: block; margin-bottom: 6px;">MESSAGE</label>
                        <textarea name="message" required rows="4" placeholder="Enter broadcast message details..." style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; font-family: inherit;"></textarea>
                    </div>

                    <button type="submit" style="background: var(--primary); color: white; border: none; padding: 12px; border-radius: 6px; font-weight: 600; cursor: pointer; margin-top: 5px;">Post Notification</button>
                </form>
            </div>

        </section>
        </div>
    </main>
<script src="assets/js/sidebar-toggle.js"></script>

<!-- ── Custom Accept Confirmation Modal ── -->
<div id="acceptModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(15,23,42,.55);backdrop-filter:blur(4px);align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:18px;box-shadow:0 24px 60px rgba(15,23,42,.22);padding:36px 32px 28px;max-width:420px;width:calc(100% - 40px);text-align:center;animation:modalPop .18s cubic-bezier(.34,1.56,.64,1);">
        <div style="width:54px;height:54px;border-radius:50%;background:#EFF6FF;display:flex;align-items:center;justify-content:center;margin:0 auto 18px;">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#1D4ED8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
        </div>
        <h3 style="margin:0 0 8px;font-size:18px;font-weight:700;color:#0f172a;">Send Confirmation Email?</h3>
        <p style="margin:0 0 26px;font-size:14px;color:#64748b;line-height:1.6;">This will accept the booking and send a confirmation email to the guest. This action cannot be undone.</p>
        <div style="display:flex;gap:10px;justify-content:center;">
            <button onclick="closeAcceptModal()" style="flex:1;padding:11px 0;border:1.5px solid #E2E8F0;border-radius:10px;background:#fff;font-size:14px;font-weight:600;color:#475569;cursor:pointer;transition:background .15s;">Cancel</button>
            <button id="acceptConfirmBtn" style="flex:1;padding:11px 0;border:none;border-radius:10px;background:linear-gradient(135deg,#16a34a,#22c55e);font-size:14px;font-weight:700;color:#fff;cursor:pointer;box-shadow:0 4px 12px rgba(34,197,94,.35);transition:opacity .15s;">Yes, Send Email</button>
        </div>
    </div>
</div>
<style>
@keyframes modalPop {
    from { opacity:0; transform:scale(.92) translateY(10px); }
    to   { opacity:1; transform:scale(1)  translateY(0); }
}
</style>
<script>
var _acceptForm = null;
function openAcceptModal(form) {
    _acceptForm = form;
    var modal = document.getElementById('acceptModal');
    modal.style.display = 'flex';
}
function closeAcceptModal() {
    _acceptForm = null;
    document.getElementById('acceptModal').style.display = 'none';
}
document.getElementById('acceptConfirmBtn').addEventListener('click', function() {
    if (_acceptForm) {
        _acceptForm.onsubmit = null;
        _acceptForm.submit();
    }
});
document.getElementById('acceptModal').addEventListener('click', function(e) {
    if (e.target === this) closeAcceptModal();
});
</script>
</body>
</html>
