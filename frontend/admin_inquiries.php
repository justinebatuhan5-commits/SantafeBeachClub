<?php
require_once __DIR__ . '/../backend/helpers/session_init.php';
require_once __DIR__ . '/../backend/config/db.php';
$active_page = 'inquiries';

// Ensure user is logged in
if (!isset($_SESSION['admin_username'])) {
    header("Location: admin_login");
    exit;
}

// Handle marking as read/resolved
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $inquiry_id = (int)($_POST['inquiry_id'] ?? 0);
    $action = $_POST['action'];

    if ($inquiry_id > 0) {
        if ($action === 'mark_read') {
            $stmt = $conn->prepare("UPDATE inquiries SET status = 'Read' WHERE id = ?");
            $stmt->bind_param("i", $inquiry_id);
            $stmt->execute();
            $stmt->close();
        } elseif ($action === 'mark_resolved') {
            $stmt = $conn->prepare("UPDATE inquiries SET status = 'Resolved' WHERE id = ?");
            $stmt->bind_param("i", $inquiry_id);
            $stmt->execute();
            $stmt->close();
        } elseif ($action === 'delete') {
            $stmt = $conn->prepare("DELETE FROM inquiries WHERE id = ?");
            $stmt->bind_param("i", $inquiry_id);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    // Redirect to refresh
    header("Location: admin_inquiries");
    exit;
}

// Fetch inquiries
$inquiriesResult = $conn->query("SELECT * FROM inquiries ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/jpeg" href="assets/logo.jpg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inquiries - Admin Dashboard</title>
    <link rel="stylesheet" href="assets/css/admin.css?v=4">
    <style>
        .inquiries-container {
            padding: 24px;
        }
        .inquiry-card {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border-left: 4px solid #E2E8F0;
        }
        .inquiry-card.unread {
            border-left-color: #3B82F6;
            background: #F8FAFC;
        }
        .inquiry-card.resolved {
            border-left-color: #10B981;
            opacity: 0.8;
        }
        .inquiry-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            align-items: flex-start;
        }
        .inquiry-meta {
            font-size: 0.9rem;
            color: #64748B;
        }
        .inquiry-meta strong {
            color: #0F172A;
            font-size: 1.1rem;
        }
        .inquiry-subject {
            font-weight: 600;
            margin-top: 10px;
            color: #1E293B;
        }
        .inquiry-message {
            margin-top: 10px;
            color: #334155;
            line-height: 1.6;
            white-space: pre-wrap;
            background: #F1F5F9;
            padding: 15px;
            border-radius: 8px;
            font-size: 0.95rem;
        }
        .inquiry-actions {
            margin-top: 15px;
            display: flex;
            gap: 10px;
        }
        .btn-action {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            border: none;
            color: #fff;
        }
        .btn-read { background: #3B82F6; }
        .btn-resolved { background: #10B981; }
        .btn-delete { background: #EF4444; }
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-unread { background: #DBEAFE; color: #1D4ED8; }
        .status-read { background: #E2E8F0; color: #475569; }
        .status-resolved { background: #D1FAE5; color: #047857; }
        
        /* Dark Mode Overrides */
        [data-theme="dark"] .inquiry-card { background: var(--card-bg, #1A1D27); border-left-color: #334155; box-shadow: 0 4px 6px rgba(0,0,0,0.3); }
        [data-theme="dark"] .inquiry-card.unread { background: #1a2235; border-left-color: #3B82F6; }
        [data-theme="dark"] .inquiry-card.resolved { opacity: 0.7; }
        [data-theme="dark"] .inquiry-meta { color: #94A3B8; }
        [data-theme="dark"] .inquiry-meta strong { color: #E2E8F0; }
        [data-theme="dark"] .inquiry-subject { color: #E2E8F0; }
        [data-theme="dark"] .inquiry-message { background: #252836; color: #E2E8F0; }
        [data-theme="dark"] .status-unread { background: rgba(59, 130, 246, 0.2); color: #93C5FD; }
        [data-theme="dark"] .status-read { background: #334155; color: #CBD5E1; }
        [data-theme="dark"] .status-resolved { background: rgba(16, 185, 129, 0.2); color: #6EE7B7; }
    </style>
</head>
<body>
    <?php
    $active_page = 'inquiries';
    require_once 'partials/_sidebar.php';
    ?>
    <div class="admin-main">
        <?php
        $page_title = 'Guest Inquiries';
        $page_subtitle = 'Review and manage guest questions and message submissions';
        $header_extra_html = '
            <div style="font-size: 13px; font-weight: 600; color: var(--text-muted); display: flex; align-items: center; gap: 6px; padding: 6px 12px; background: var(--card-bg); border: 1px solid var(--border); border-radius: var(--radius-sm);">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                <span>' . date('l, M j, Y') . '</span>
            </div>
        ';
        include __DIR__ . '/partials/_page_header.php';
        ?>

        <div class="admin-body">
        <div class="inquiries-container">
            <?php if ($inquiriesResult && $inquiriesResult->num_rows > 0): ?>
                <?php while ($inq = $inquiriesResult->fetch_assoc()): 
                    $cardClass = '';
                    if ($inq['status'] === 'Unread') $cardClass = 'unread';
                    if ($inq['status'] === 'Resolved') $cardClass = 'resolved';
                    
                    $badgeClass = '';
                    if ($inq['status'] === 'Unread') $badgeClass = 'status-unread';
                    if ($inq['status'] === 'Read') $badgeClass = 'status-read';
                    if ($inq['status'] === 'Resolved') $badgeClass = 'status-resolved';
                ?>
                    <div class="inquiry-card <?php echo $cardClass; ?>">
                        <div class="inquiry-header">
                            <div class="inquiry-meta">
                                <strong><?php echo htmlspecialchars($inq['guest_name']); ?></strong><br>
                                <a href="mailto:<?php echo htmlspecialchars($inq['guest_email']); ?>"><?php echo htmlspecialchars($inq['guest_email']); ?></a><br>
                                <span><?php echo date('F j, Y g:i A', strtotime($inq['created_at'])); ?></span>
                            </div>
                            <span class="status-badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($inq['status']); ?></span>
                        </div>
                        <div class="inquiry-subject">Subject: <?php echo htmlspecialchars($inq['subject']); ?></div>
                        <div class="inquiry-message"><?php echo htmlspecialchars($inq['message']); ?></div>
                        
                        <div class="inquiry-actions">
                            <form method="POST" style="display:inline;">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="inquiry_id" value="<?php echo $inq['id']; ?>">
                                <?php if ($inq['status'] === 'Unread'): ?>
                                    <button type="submit" name="action" value="mark_read" class="btn-action btn-read">Mark as Read</button>
                                <?php endif; ?>
                                <?php if ($inq['status'] !== 'Resolved'): ?>
                                    <button type="submit" name="action" value="mark_resolved" class="btn-action btn-resolved">Mark Resolved</button>
                                <?php endif; ?>
                                <button type="button" class="btn-action btn-delete" onclick="showConfirm({ title: 'Delete Message', message: 'Are you sure you want to delete this message? This cannot be undone.', icon: 'trash', iconBg: '#FEE2E2', confirmText: 'Delete', onConfirm: () => this.closest('form').submit() })">Delete</button>
                            </form>
                            <a href="mailto:<?php echo htmlspecialchars($inq['guest_email']); ?>?subject=Re: <?php echo urlencode($inq['subject']); ?>" class="btn-action" style="background: #475569; text-decoration: none; display: inline-block;">Reply via Email</a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #64748B;">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 10px; opacity: 0.5;"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                    <p>No guest inquiries found.</p>
                </div>
            <?php endif; ?>
        </div><!-- end inquiries-container -->
        </div><!-- end admin-body -->
    </div><!-- end admin-main -->
<script>
// User menu dropdown toggle
var userMenuTrigger = document.getElementById('userMenuTrigger');
var userMenu = document.getElementById('userMenu');
if (userMenuTrigger && userMenu) {
    userMenuTrigger.addEventListener('click', function(e) {
        e.stopPropagation();
        userMenu.classList.toggle('open');
    });
    document.addEventListener('click', function() {
        userMenu.classList.remove('open');
    });
}
</script>
<script src="assets/js/sidebar-toggle.js"></script>
</body>
</html>
