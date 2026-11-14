<?php
require_once __DIR__ . '/../backend/helpers/admin_auth_check.php';
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/services/mailer.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Handle POST actions for notifications
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    require_csrf_token();
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

        $stmt = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE id = ?');
        $stmt->bind_param('i', $notif_id);
        $stmt->execute();
        $stmt->close();
        $_SESSION['notif_flash'] = 'Booking notification acknowledged. Confirmation is sent automatically after payment verification.';
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
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/assets/css/admin.css'); ?>">
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

        /* Modern notification workspace */
        .notifications-page {
            max-width: 1680px;
            width: 100%;
            margin: 0 auto;
        }
        .summary-banner {
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }
        .summary-card {
            position: relative;
            min-height: 112px;
            padding: 20px 22px;
            border: 1px solid var(--border);
            border-radius: 16px;
            background: var(--card-bg);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }
        .summary-card::after {
            content: '';
            position: absolute;
            right: -18px;
            bottom: -28px;
            width: 96px;
            height: 96px;
            border-radius: 50%;
            background: var(--summary-tint);
        }
        .summary-card:nth-child(1) { --summary-color: #2563EB; --summary-tint: rgba(37, 99, 235, .09); }
        .summary-card:nth-child(2) { --summary-color: #D97706; --summary-tint: rgba(217, 119, 6, .10); }
        .summary-card:nth-child(3) { --summary-color: #DC2626; --summary-tint: rgba(220, 38, 38, .09); }
        .summary-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: var(--summary-tint) !important;
            color: var(--summary-color);
        }
        .summary-value {
            font-family: var(--font-heading);
            font-size: 26px;
            line-height: 1;
            font-weight: 800;
            color: var(--text-main);
        }
        .summary-label {
            margin-top: 7px;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
        }
        .dashboard-grid {
            grid-template-columns: minmax(0, 1.8fr) minmax(320px, .8fr) !important;
            gap: 20px;
            align-items: start;
        }
        .card {
            padding: 24px;
            border: 1px solid var(--border);
            border-radius: 18px;
            background: var(--card-bg);
            box-shadow: var(--shadow-sm);
        }
        .card-header {
            margin-bottom: 0;
            padding-bottom: 18px;
            border-bottom: 1px solid var(--border-light);
        }
        .card-header h2 {
            font-family: var(--font-heading);
            font-size: 17px;
            color: var(--text-main);
        }
        .section-count {
            display: inline-flex;
            align-items: center;
            min-height: 26px;
            padding: 0 10px;
            border-radius: 999px;
            background: var(--primary-light);
            color: var(--primary);
            font-size: 11px;
            font-weight: 700;
        }
        .notif-card-list {
            gap: 10px;
            margin-top: 16px;
        }
        .notif-item {
            position: relative;
            padding: 17px 18px 17px 20px;
            border: 1px solid var(--border-light);
            border-left-width: 3px;
            border-radius: 13px;
            background: var(--bg-surface);
            box-shadow: none;
        }
        .notif-item:hover {
            border-color: var(--border);
            box-shadow: var(--shadow-md);
            transform: translateY(-1px);
        }
        .notif-item.unread {
            background: color-mix(in srgb, var(--bg-surface) 94%, var(--notif-accent, #2563EB));
        }
        .notif-item.type-info { --notif-accent: #2563EB; border-left-color: var(--notif-accent); }
        .notif-item.type-success { --notif-accent: #059669; border-left-color: var(--notif-accent); }
        .notif-item.type-warning { --notif-accent: #D97706; border-left-color: var(--notif-accent); }
        .notif-item.type-alert { --notif-accent: #DC2626; border-left-color: var(--notif-accent); }
        .notif-item.unread::after {
            content: '';
            position: absolute;
            top: 16px;
            right: 16px;
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--notif-accent);
            box-shadow: 0 0 0 4px color-mix(in srgb, var(--notif-accent) 14%, transparent);
        }
        .notif-content { min-width: 0; padding-right: 12px; }
        .notif-item.unread .notif-content { padding-right: 28px; }
        .notif-badge {
            margin-bottom: 7px;
            padding: 4px 8px;
            border-radius: 999px;
            letter-spacing: .45px;
        }
        .notif-title {
            font-family: var(--font-heading);
            font-size: 15px;
            color: var(--text-main);
        }
        .notif-msg {
            color: var(--text-muted);
            line-height: 1.55;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .notif-time { color: var(--text-subtle); margin-top: 9px; }
        .notif-actions { align-items: center; margin-top: 2px; }
        .btn-action-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 34px;
            padding: 0 10px;
            border-color: var(--border);
            border-radius: 9px;
            background: var(--bg-surface);
            color: var(--text-muted);
        }
        .btn-action-icon:hover { background: var(--sidebar-hover); color: var(--text-main); }
        .btn-delete-notif { width: 34px; padding: 0; color: var(--red); }
        .broadcast-card { position: sticky; top: 98px; }
        .broadcast-intro {
            margin: 10px 0 18px;
            color: var(--text-muted);
            font-size: 12px;
            line-height: 1.55;
        }
        .broadcast-form { display: flex; flex-direction: column; gap: 15px; }
        .form-label {
            display: block;
            margin-bottom: 7px;
            color: var(--text-muted);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .55px;
            text-transform: uppercase;
        }
        .form-control {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid var(--border);
            border-radius: 10px;
            outline: none;
            background: var(--input-bg);
            color: var(--text-main);
            font: 500 13px/1.5 var(--font-body);
            transition: border-color .18s ease, box-shadow .18s ease;
        }
        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-glow); }
        textarea.form-control { resize: vertical; min-height: 118px; }
        .broadcast-submit {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 44px;
            margin-top: 2px;
            border: 0;
            border-radius: 11px;
            background: var(--primary-grad);
            color: #fff;
            font: 700 13px var(--font-body);
            cursor: pointer;
            box-shadow: 0 6px 16px rgba(132, 86, 60, .22);
        }
        .broadcast-submit:hover { transform: translateY(-1px); box-shadow: 0 9px 20px rgba(132, 86, 60, .3); }
        .notif-flash {
            margin-bottom: 20px;
            padding: 13px 16px;
            border: 1px solid var(--green-border);
            border-radius: 11px;
            background: var(--green-bg);
            color: var(--green);
            font-size: 13px;
            font-weight: 600;
        }
        .notif-empty { padding: 64px 20px; text-align: center; color: var(--text-muted); }
        @media (max-width: 1180px) {
            .dashboard-grid { grid-template-columns: 1fr !important; }
            .broadcast-card { position: static; }
        }
        @media (max-width: 700px) {
            .summary-banner { grid-template-columns: 1fr; }
            .summary-card { min-height: 90px; padding: 16px; }
            .card { padding: 18px 14px; border-radius: 14px; }
            .notif-item { flex-direction: column; padding: 15px; }
            .notif-actions { width: 100%; justify-content: flex-end; }
        }

    </style>
<?php require __DIR__ . '/partials/_responsive.php'; ?>
</head>
<body>

    <?php $active_page = 'notifications'; include __DIR__ . '/partials/_sidebar.php'; ?>

    <!-- Main Dashboard Panel -->
    <main class="admin-main">

        <!-- Top Bar (shared component, same as Dashboard) -->
        <?php
        $page_title = 'Admin Notifications';
        $page_subtitle = 'System alerts & booking updates';
        $header_extra_html = '
            <form method="POST" action="admin_notifications" style="margin: 0;">
                ' . csrf_field() . '
                <input type="hidden" name="action" value="mark_all_read">
                <button type="submit" class="header-icon-btn" title="Mark All as Read" style="width: auto; padding: 0 15px; gap: 8px; font-weight: 600; font-size: 13px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    Mark All Read
                </button>
            </form>
        ';
        include __DIR__ . '/partials/_page_header.php';
        echo "\n        <div class=\"admin-body notifications-page\">\n";
        ?>

        <?php if ($notif_flash): ?>
            <div class="notif-flash">
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
                    <h3 class="summary-value"><?php echo $unread_count; ?></h3>
                    <p class="summary-label">Unread Alerts</p>
                </div>
            </div>

            <div class="summary-card">
                <div class="summary-icon" style="background: #FF9800;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path></svg>
                </div>
                <div>
                    <h3 class="summary-value"><?php echo $pending_cnt; ?></h3>
                    <p class="summary-label">Pending Check-ins</p>
                </div>
            </div>

            <div class="summary-card">
                <div class="summary-icon" style="background: #F44336;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path></svg>
                </div>
                <div>
                    <h3 class="summary-value"><?php echo $maint_cnt; ?></h3>
                    <p class="summary-label">Rooms in Maintenance</p>
                </div>
            </div>
        </div>

        <section class="dashboard-grid">
            
            <!-- Notifications List -->
            <div class="card">
                <div class="card-header">
                    <h2>Recent Notifications</h2>
                    <span class="section-count"><?php echo (int)$unread_count; ?> unread</span>
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
                                <div class="notif-content" style="flex: 1; cursor: pointer;" onclick="showNotificationDetailModal({id: <?php echo $id; ?>, title: <?php echo xss_attr_json($n['title']); ?>, message: <?php echo xss_attr_json($n['message']); ?>, type: <?php echo xss_attr_json($type); ?>, booking_id: <?php echo (int)($n['booking_id'] ?? 0); ?>, time: <?php echo xss_attr_json($time); ?>})">
                                    <span class="notif-badge <?php echo $badgeClass; ?>"><?php echo strtoupper($type); ?></span>
                                    <div class="notif-title"><?php echo $title; ?></div>
                                    <div class="notif-msg"><?php echo $msg; ?></div>
                                    <div class="notif-time"><?php echo $time; ?></div>
                                </div>
                                <div class="notif-actions" onclick="event.stopPropagation();">
                                    <?php if (!$is_read): ?>
                                        <form method="POST" action="admin_notifications" style="margin:0;">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="mark_read">
                                            <input type="hidden" name="notif_id" value="<?php echo $id; ?>">
                                            <button type="submit" class="btn-action-icon" title="Mark as read">Mark Read</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="POST" action="admin_notifications" style="margin:0;">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="notif_id" value="<?php echo $id; ?>">
                                        <button type="submit" class="btn-action-icon btn-delete-notif" title="Delete notification" aria-label="Delete notification">&times;</button>
                                    </form>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="notif-empty">No notifications found</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Create New Broadcast Notification -->
            <div class="card broadcast-card">
                <div class="card-header">
                    <h2>Send Alert Broadcast</h2>
                </div>

                <p class="broadcast-intro">Share an operational update with the front desk team. Choose the alert level that matches its urgency.</p>
                <form method="POST" action="admin_notifications" class="broadcast-form">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="create_notif">
                    
                    <div>
                        <label class="form-label" for="broadcastTitle">Title</label>
                        <input class="form-control" id="broadcastTitle" type="text" name="title" required maxlength="120" placeholder="e.g. VIP transfer request">
                    </div>

                    <div>
                        <label class="form-label" for="broadcastType">Alert type</label>
                        <select class="form-control" id="broadcastType" name="type">
                            <option value="info">Info (Blue)</option>
                            <option value="success">Success (Green)</option>
                            <option value="warning">Warning (Orange)</option>
                            <option value="alert">Alert (Red)</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label" for="broadcastMessage">Message</label>
                        <textarea class="form-control" id="broadcastMessage" name="message" required rows="4" maxlength="1000" placeholder="Enter the broadcast details..."></textarea>
                    </div>

                    <button type="submit" class="broadcast-submit">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
                        Post Notification
                    </button>
                </form>
            </div>

        </section>
        </div>
    </main>


<!-- ── Custom Accept Confirmation Modal ── -->
<div id="acceptModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(15,23,42,.55);backdrop-filter:blur(4px);align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:18px;box-shadow:0 24px 60px rgba(15,23,42,.22);padding:36px 32px 28px;max-width:420px;width:calc(100% - 40px);text-align:center;animation:modalPop .18s cubic-bezier(.34,1.56,.64,1);">
        <div style="width:54px;height:54px;border-radius:50%;background:#EFF6FF;display:flex;align-items:center;justify-content:center;margin:0 auto 18px;">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#1D4ED8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
        </div>
        <h3 style="margin:0 0 8px;font-size:18px;font-weight:700;color:#0f172a;">Review booking notification?</h3>
        <p style="margin:0 0 26px;font-size:14px;color:#64748b;line-height:1.6;">Mark this booking notification as reviewed. The guest receives a confirmation automatically after payment verification.</p>
        <div style="display:flex;gap:10px;justify-content:center;">
            <button onclick="closeAcceptModal()" style="flex:1;padding:11px 0;border:1.5px solid #E2E8F0;border-radius:10px;background:#fff;font-size:14px;font-weight:600;color:#475569;cursor:pointer;transition:background .15s;">Cancel</button>
            <button id="acceptConfirmBtn" style="flex:1;padding:11px 0;border:none;border-radius:10px;background:linear-gradient(135deg,#16a34a,#22c55e);font-size:14px;font-weight:700;color:#fff;cursor:pointer;box-shadow:0 4px 12px rgba(34,197,94,.35);transition:opacity .15s;">Mark as reviewed</button>
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
