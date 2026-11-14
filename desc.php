<?php
require_once __DIR__ . '/backend/helpers/admin_auth_check.php';
require_once __DIR__ . '/backend/config/db.php';

$admin = $_SESSION['admin_username'];
$success = $error = '';

$type_labels = [
    'all'              => 'All Room Types',
    'beachview_duplex' => 'Beachview Duplex',
    'seaview_duplex'   => 'Seaview Duplex',
    'beach_villa'      => 'Beach Villa',
    'standard_room'    => 'Standard Room',
    'standard_king'    => 'Standard Family Room',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_rule') {
        $title     = trim($_POST['title'] ?? '');
        $room_type = trim($_POST['room_type'] ?? 'all');
        $rule_type = in_array($_POST['rule_type'] ?? '', ['weekend', 'date_range']) ? $_POST['rule_type'] : 'weekend';
        $adj_type  = in_array($_POST['adjustment_type'] ?? '', ['percent', 'fixed']) ? $_POST['adjustment_type'] : 'percent';
        $adj_val   = (float)($_POST['adjustment_value'] ?? 0);
        $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $end_date   = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        $days_of_week = isset($_POST['days_of_week']) && is_array($_POST['days_of_week']) ? implode(',', $_POST['days_of_week']) : '5,6,0';

        if (!$title) {
            $_SESSION['pr_error'] = 'Rule title is required.';
        } else {
            $stmt = $conn->prepare("INSERT INTO pricing_rules (title, room_type, rule_type, start_date, end_date, days_of_week, adjustment_type, adjustment_value) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->bind_param("sssssssd", $title, $room_type, $rule_type, $start_date, $end_date, $days_of_week, $adj_type, $adj_val);
            $stmt->execute();
            $stmt->close();
            log_activity($conn, $admin, 'Pricing Rule Added', "Added dynamic pricing rule: $title");
            $_SESSION['pr_success'] = "Pricing rule \"$title\" created successfully.";
        }
    }

    if ($action === 'toggle_rule') {
        $rid = (int)$_POST['rule_id'];
        $conn->query("UPDATE pricing_rules SET is_active = 1 - is_active WHERE id = $rid");
        $_SESSION['pr_success'] = 'Pricing rule status updated.';
    }

    if ($action === 'delete_rule') {
        $rid = (int)$_POST['rule_id'];
        $row = $conn->query("SELECT title FROM pricing_rules WHERE id=$rid")->fetch_assoc();
        if ($row) {
            $conn->query("DELETE FROM pricing_rules WHERE id=$rid");
            log_activity($conn, $admin, 'Pricing Rule Deleted', "Deleted rule: {$row['title']}");
            $_SESSION['pr_success'] = "Pricing rule deleted.";
        }
    }

    header('Location: desc.php');
    exit;
}

if (isset($_SESSION['pr_success'])) {
    $success = $_SESSION['pr_success'];
    unset($_SESSION['pr_success']);
}
if (isset($_SESSION['pr_error'])) {
    $error = $_SESSION['pr_error'];
    unset($_SESSION['pr_error']);
}

$rules = $conn->query("SELECT * FROM pricing_rules ORDER BY is_active DESC, id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dynamic & Seasonal Pricing — Santa Fe Beach Club</title>
    <link rel="stylesheet" href="frontend/assets/css/admin.css?v=3">
</head>
<body>
    <?php $active_page = 'pricing'; include __DIR__ . '/frontend/partials/_sidebar.php'; ?>

    <main class="main-content">
        <?php
        $page_title = 'Dynamic & Seasonal Pricing';
        $page_subtitle = 'Automate weekend surcharges, seasonal holiday pricing, and room rate adjustments.';
        $header_extra_html = '
            <button class="btn-primary" onclick="document.getElementById(\'addModal\').classList.add(\'open\')" style="padding: 8px 16px; display: flex; align-items: center; gap: 8px;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                New Pricing Rule
            </button>
        ';
        include __DIR__ . '/frontend/partials/_page_header.php';
        ?>

        <?php if ($success): ?><div class="alert alert-success"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
        <?php if ($error):   ?><div class="alert alert-error"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/></svg><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <?php if ($rules->num_rows === 0): ?>
        <div class="admin-card"><div class="empty-state">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            <p>No pricing rules configured yet. Click <strong>New Pricing Rule</strong> to add weekend or holiday adjustments.</p>
        </div></div>
        <?php else: ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:20px;">
        <?php while ($r = $rules->fetch_assoc()):
            $active = (bool)$r['is_active'];
        ?>
        <div class="admin-card" style="position:relative;border-top:3px solid <?php echo $active ? '#10B981' : '#E5E7EB'; ?>;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px;">
                <div style="flex:1;">
                    <h3 style="font-size:15px;font-weight:700;color:var(--text-main);"><?php echo htmlspecialchars($r['title']); ?></h3>
                    <span style="display:inline-block; font-size:11px; color:var(--text-muted); font-weight:600; margin-top:2px;">
                        Room Target: <strong><?php echo htmlspecialchars($type_labels[$r['room_type']] ?? $r['room_type']); ?></strong>
                    </span>
                </div>
                <span class="badge <?php echo $active ? 'badge-checkedin' : 'badge-checkedout'; ?>" style="margin-left:10px;flex-shrink:0;">
                    <?php echo $active ? 'Active' : 'Disabled'; ?>
                </span>
            </div>

            <div style="display:flex;gap:16px;margin:14px 0;">
                <div>
                    <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted);margin-bottom:3px;">Adjustment</div>
                    <div style="font-size:18px;font-weight:700;color:#0284C7;">
                        +<?php echo $r['adjustment_type'] === 'percent' ? $r['adjustment_value'].'%' : '₱'.number_format($r['adjustment_value'],2); ?>
                    </div>
                </div>
                <div>
                    <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted);margin-bottom:3px;">Rule Trigger</div>
                    <div style="font-size:13px;color:var(--text-main);font-weight:600;">
                        <?php if ($r['rule_type'] === 'weekend'): ?>
                            <span>Weekend (Fri, Sat, Sun)</span>
                        <?php else: ?>
                            <span><?php echo date('M j', strtotime($r['start_date'])); ?> – <?php echo date('M j, Y', strtotime($r['end_date'])); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div style="display:flex;gap:8px;margin-top:16px;">
                <form method="POST" style="flex:1;">
                    <input type="hidden" name="action" value="toggle_rule">
                    <input type="hidden" name="rule_id" value="<?php echo $r['id']; ?>">
                    <button type="submit" class="btn-secondary" style="width:100%;justify-content:center;">
                        <?php echo $r['is_active'] ? 'Disable' : 'Enable'; ?>
                    </button>
                </form>
                <form method="POST" onsubmit="return false;" data-confirm-title="Delete Pricing Rule" data-confirm-msg="This pricing rule will be permanently deleted." data-confirm-icon="trash" data-confirm-icon-bg="#FEE2E2">
                    <input type="hidden" name="action" value="delete_rule">
                    <input type="hidden" name="rule_id" value="<?php echo $r['id']; ?>">
                    <button type="submit" class="btn-danger">Delete</button>
                </form>
            </div>
        </div>
        <?php endwhile; ?>
        </div>
        <?php endif; ?>
    </main>

<!-- Add Pricing Rule Modal -->
<div class="modal-overlay" id="addModal">
    <div class="modal-box">
        <button class="modal-close" onclick="document.getElementById('addModal').classList.remove('open')">×</button>
        <h3>New Pricing Rule</h3>
        <p class="modal-sub">Apply automatic surcharges for peak seasons or weekends.</p>
        <form method="POST">
            <input type="hidden" name="action" value="add_rule">
            <div class="admin-form-group">
                <label>Rule Title</label>
                <input type="text" name="title" required placeholder="e.g. Weekend Peak Surge, Holy Week 2026">
            </div>
            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label>Target Room</label>
                    <select name="room_type">
                        <option value="all">All Room Types</option>
                        <option value="beachview_duplex">Beachview Duplex</option>
                        <option value="seaview_duplex">Seaview Duplex</option>
                        <option value="beach_villa">Beach Villa</option>
                        <option value="standard_room">Standard Room</option>
                        <option value="standard_king">Standard Family Room</option>
                    </select>
                </div>
                <div class="admin-form-group">
                    <label>Rule Condition</label>
                    <select name="rule_type" id="ruleTypeSelect" onchange="toggleRuleFields()">
                        <option value="weekend">Weekend Days (Fri/Sat/Sun)</option>
                        <option value="date_range">Seasonal Date Range</option>
                    </select>
                </div>
            </div>
            
            <div class="admin-form-row" id="dateRangeRow" style="display:none;">
                <div class="admin-form-group"><label>Start Date</label><input type="date" name="start_date" value="<?php echo date('Y-m-d'); ?>"></div>
                <div class="admin-form-group"><label>End Date</label><input type="date" name="end_date" value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>"></div>
            </div>

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label>Adjustment Type</label>
                    <select name="adjustment_type">
                        <option value="percent">Percentage Markup (+%)</option>
                        <option value="fixed">Fixed Nightly Surcharge (+₱)</option>
                    </select>
                </div>
                <div class="admin-form-group">
                    <label>Adjustment Value</label>
                    <input type="number" name="adjustment_value" min="0" step="0.01" required placeholder="e.g. 15 for +15%">
                </div>
            </div>

            <button type="submit" class="btn-primary" style="width:100%;justify-content:center;margin-top:10px;">Create Pricing Rule</button>
        </form>
    </div>
</div>

<script>
function toggleRuleFields() {
    var select = document.getElementById('ruleTypeSelect');
    var row = document.getElementById('dateRangeRow');
    if (select.value === 'date_range') {
        row.style.display = 'grid';
    } else {
        row.style.display = 'none';
    }
}
</script>
<script src="frontend/assets/js/sidebar-toggle.js"></script>
</body>
</html>
