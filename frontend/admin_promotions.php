<?php
require_once __DIR__ . '/../backend/helpers/admin_auth_check.php';
require_once __DIR__ . '/../backend/config/db.php';

$admin = $_SESSION['admin_username'];
$success = $error = '';

// Ensure promotions table
$conn->query("CREATE TABLE IF NOT EXISTS promotions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    description TEXT,
    discount_type VARCHAR(20) DEFAULT 'percent',
    discount_value DECIMAL(10,2) DEFAULT 0,
    valid_from DATE,
    valid_until DATE,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_promo') {
        $title       = trim($_POST['title'] ?? '');
        $code        = strtoupper(trim($_POST['code'] ?? ''));
        $desc        = trim($_POST['description'] ?? '');
        $dtype       = in_array($_POST['discount_type'] ?? '', ['percent','fixed']) ? $_POST['discount_type'] : 'percent';
        $dval        = (float)($_POST['discount_value'] ?? 0);
        $from        = $_POST['valid_from'] ?? date('Y-m-d');
        $until       = $_POST['valid_until'] ?? date('Y-m-d');

        if (!$title) { $_SESSION['promo_error'] = 'Title is required.'; }
        else {
            $stmt2 = $conn->prepare("INSERT INTO promotions (title, code, description, discount_type, discount_value, valid_from, valid_until) VALUES (?,?,?,?,?,?,?)");
            $stmt2->bind_param("ssssdss", $title, $code, $desc, $dtype, $dval, $from, $until);
            $stmt2->execute(); $stmt2->close();
            log_activity($conn, $admin, 'Promotion Added', "Added: $title" . ($code ? " [Code: $code]" : ""));
            $_SESSION['promo_success'] = "Promotion \"$title\" created.";
        }
    }

    if ($action === 'toggle_promo') {
        $pid = (int)$_POST['promo_id'];
        $conn->query("UPDATE promotions SET is_active = 1 - is_active WHERE id = $pid");
        $_SESSION['promo_success'] = 'Promotion status updated.';
    }

    if ($action === 'delete_promo') {
        $pid = (int)$_POST['promo_id'];
        $row = $conn->query("SELECT title FROM promotions WHERE id=$pid")->fetch_assoc();
        if ($row) {
            $conn->query("DELETE FROM promotions WHERE id=$pid");
            log_activity($conn, $admin, 'Promotion Deleted', "Removed: {$row['title']}");
            $_SESSION['promo_success'] = "Promotion deleted.";
        }
    }

    header('Location: admin_promotions');
    exit;
}

if (isset($_SESSION['promo_success'])) {
    $success = $_SESSION['promo_success'];
    unset($_SESSION['promo_success']);
}
if (isset($_SESSION['promo_error'])) {
    $error = $_SESSION['promo_error'];
    unset($_SESSION['promo_error']);
}

$promos = $conn->query("SELECT * FROM promotions ORDER BY is_active DESC, valid_until DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/jpeg" href="assets/logo.jpg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Promotions — Santa Fe Beach Club</title>
    <link rel="stylesheet" href="assets/css/admin.css?v=4">
</head>
<body>
    <?php $active_page = 'promotions'; include __DIR__ . '/partials/_sidebar.php'; ?>

    <main class="main-content">
        <?php
        $page_title = 'Promotions';
        $page_subtitle = 'Manage active deals and discounts for guests.';
        $header_extra_html = '
            <button class="btn-primary" onclick="document.getElementById(\'addModal\').classList.add(\'open\')" style="padding: 8px 16px; display: flex; align-items: center; gap: 8px;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                New Promotion
            </button>
        ';
        include __DIR__ . '/partials/_page_header.php';
        ?>


        <?php if ($success): ?><div class="alert alert-success"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
        <?php if ($error):   ?><div class="alert alert-error"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/></svg><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <?php if ($promos->num_rows === 0): ?>
        <div class="admin-card"><div class="empty-state">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            <p>No promotions yet. Click <strong>New Promotion</strong> to create one.</p>
        </div></div>
        <?php else: ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:20px;">
        <?php while ($p = $promos->fetch_assoc()):
            $expired = strtotime($p['valid_until']) < strtotime(date('Y-m-d'));
            $active  = $p['is_active'] && !$expired;
        ?>
        <div class="admin-card" style="position:relative;border-top:3px solid <?php echo $active ? 'var(--primary)' : '#E5E7EB'; ?>;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px;">
                <div style="flex:1;">
                    <h3 style="font-size:15px;font-weight:700;color:var(--text-main);"><?php echo htmlspecialchars($p['title']); ?></h3>
                    <?php if (!empty($p['code'])): ?>
                        <span style="display:inline-block; margin-top:4px; font-size:11px; font-weight:800; letter-spacing:0.5px; background:#EFF6FF; color:#1D4ED8; padding:2px 8px; border-radius:4px; border:1px solid #BFDBFE;">
                            COUPON: <?php echo htmlspecialchars($p['code']); ?>
                        </span>
                    <?php endif; ?>
                </div>
                <span class="badge <?php echo $active ? 'badge-checkedin' : 'badge-checkedout'; ?>" style="margin-left:10px;flex-shrink:0;">
                    <?php echo $expired ? 'Expired' : ($p['is_active'] ? 'Active' : 'Inactive'); ?>
                </span>
            </div>

            <?php if ($p['description']): ?>
            <p style="font-size:13px;color:var(--text-muted);margin-bottom:14px;line-height:1.5;"><?php echo htmlspecialchars($p['description']); ?></p>
            <?php endif; ?>

            <div style="display:flex;gap:16px;margin-bottom:16px;">
                <div>
                    <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted);margin-bottom:3px;">Discount</div>
                    <div style="font-size:18px;font-weight:700;color:var(--primary);">
                        <?php echo $p['discount_type'] === 'percent' ? $p['discount_value'].'%' : '₱'.number_format($p['discount_value'],0); ?>
                    </div>
                </div>
                <div>
                    <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted);margin-bottom:3px;">Valid Period</div>
                    <div style="font-size:13px;color:var(--text-main);">
                        <?php echo date('M j', strtotime($p['valid_from'])); ?> – <?php echo date('M j, Y', strtotime($p['valid_until'])); ?>
                    </div>
                </div>
            </div>

            <div style="display:flex;gap:8px;">
                <form method="POST" style="flex:1;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="toggle_promo">
                    <input type="hidden" name="promo_id" value="<?php echo $p['id']; ?>">
                    <button type="submit" class="btn-secondary" style="width:100%;justify-content:center;">
                        <?php echo $p['is_active'] ? 'Deactivate' : 'Activate'; ?>
                    </button>
                </form>
                <form method="POST" onsubmit="return false;" data-confirm-title="Delete Promotion" data-confirm-msg="This promotion will be permanently deleted." data-confirm-icon="trash" data-confirm-icon-bg="#FEE2E2">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="delete_promo">
                    <input type="hidden" name="promo_id" value="<?php echo $p['id']; ?>">
                    <button type="submit" class="btn-danger">Delete</button>
                </form>
            </div>
        </div>
        <?php endwhile; ?>
        </div>
        <?php endif; ?>
    </main>

<!-- Add Promotion Modal -->
<div class="modal-overlay" id="addModal">
    <div class="modal-box">
        <button class="modal-close" onclick="document.getElementById('addModal').classList.remove('open')">×</button>
        <h3>New Promotion</h3>
        <p class="modal-sub">Create a discount offer for guests.</p>
        <form method="POST">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="add_promo">
            <div class="admin-form-group"><label>Title</label><input type="text" name="title" required placeholder="e.g. Summer Beach Deal"></div>
            <div class="admin-form-group"><label>Promo / Coupon Code (e.g. SUMMER2026)</label><input type="text" name="code" placeholder="e.g. BEACH10" style="text-transform:uppercase;"></div>
            <div class="admin-form-group"><label>Description (optional)</label><textarea name="description" rows="2" placeholder="Describe the offer..." style="resize:vertical;"></textarea></div>
            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label>Discount Type</label>
                    <select name="discount_type">
                        <option value="percent">Percentage (%)</option>
                        <option value="fixed">Fixed Amount (₱)</option>
                    </select>
                </div>
                <div class="admin-form-group"><label>Discount Value</label><input type="number" name="discount_value" min="0" step="0.01" placeholder="e.g. 20"></div>
            </div>
            <div class="admin-form-row">
                <div class="admin-form-group"><label>Valid From</label><input type="date" name="valid_from" value="<?php echo date('Y-m-d'); ?>"></div>
                <div class="admin-form-group"><label>Valid Until</label><input type="date" name="valid_until" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>"></div>
            </div>
            <button type="submit" class="btn-primary" style="width:100%;justify-content:center;">Create Promotion</button>
        </form>
    </div>
</div>
<script src="assets/js/sidebar-toggle.js"></script>
</body>
</html>
