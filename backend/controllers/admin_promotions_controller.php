<?php
/**
 * Admin promotions page controller.
 *
 * Ensures schema compatibility, handles promo CRUD actions, and fetches usage counts.
 */

/**
 * Ensures the promotions table and usage_limit column exist.
 */
function sfbc_promotions_ensure_schema(mysqli $conn): void
{
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

    $promoSchema = $conn->query('SELECT DATABASE()')->fetch_row()[0] ?? '';
    $usageLimitColumn = $conn->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema=? AND table_name='promotions' AND column_name='usage_limit'");
    $usageLimitColumn->bind_param('s', $promoSchema);
    $usageLimitColumn->execute();
    if (!$usageLimitColumn->get_result()->fetch_row()) {
        $conn->query('ALTER TABLE promotions ADD COLUMN usage_limit INT UNSIGNED NULL DEFAULT NULL');
    }
    $usageLimitColumn->close();
}

/**
 * Processes POST actions for promotions management.
 */
function sfbc_promotions_handle_post(mysqli $conn, string $admin): void
{
    require_csrf_token();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_promo') {
        $title      = trim($_POST['title'] ?? '');
        $code       = strtoupper(trim($_POST['code'] ?? ''));
        $desc       = trim($_POST['description'] ?? '');
        $dtype      = in_array($_POST['discount_type'] ?? '', ['percent', 'fixed'], true) ? $_POST['discount_type'] : 'percent';
        $dval       = (float)($_POST['discount_value'] ?? 0);
        $usageLimit = ($_POST['usage_limit'] ?? '') === '' ? null : max(1, (int)$_POST['usage_limit']);
        $from       = $_POST['valid_from'] ?? date('Y-m-d');
        $until      = $_POST['valid_until'] ?? date('Y-m-d');

        if (!$title) {
            $_SESSION['promo_error'] = 'Title is required.';
        } else {
            $stmt = $conn->prepare("INSERT INTO promotions (title, code, description, discount_type, discount_value, usage_limit, valid_from, valid_until) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->bind_param("ssssdiss", $title, $code, $desc, $dtype, $dval, $usageLimit, $from, $until);
            $stmt->execute();
            $stmt->close();
            log_activity($conn, $admin, 'Promotion Added', "Added: $title" . ($code ? " [Code: $code]" : ""));
            $_SESSION['promo_success'] = "Promotion \"$title\" created.";
        }
    }

    if ($action === 'toggle_promo') {
        $pid = (int)($_POST['promo_id'] ?? 0);
        $stmt = $conn->prepare("UPDATE promotions SET is_active = 1 - is_active WHERE id = ?");
        $stmt->bind_param('i', $pid);
        $stmt->execute();
        $stmt->close();
        $_SESSION['promo_success'] = 'Promotion status updated.';
    }

    if ($action === 'update_limit') {
        $pid = (int)($_POST['promo_id'] ?? 0);
        $usageLimit = ($_POST['usage_limit'] ?? '') === '' ? null : max(1, (int)$_POST['usage_limit']);
        $limitStmt = $conn->prepare('UPDATE promotions SET usage_limit=? WHERE id=?');
        $limitStmt->bind_param('ii', $usageLimit, $pid);
        $limitStmt->execute();
        $limitStmt->close();
        $_SESSION['promo_success'] = 'Promotion usage limit updated.';
    }

    if ($action === 'delete_promo') {
        $pid = (int)($_POST['promo_id'] ?? 0);
        $selStmt = $conn->prepare("SELECT title FROM promotions WHERE id = ?");
        $selStmt->bind_param('i', $pid);
        $selStmt->execute();
        $row = $selStmt->get_result()->fetch_assoc();
        $selStmt->close();

        if ($row) {
            $delStmt = $conn->prepare("DELETE FROM promotions WHERE id = ?");
            $delStmt->bind_param('i', $pid);
            $delStmt->execute();
            $delStmt->close();
            log_activity($conn, $admin, 'Promotion Deleted', "Removed: {$row['title']}");
            $_SESSION['promo_success'] = "Promotion deleted.";
        }
    }

    header('Location: admin_promotions');
    exit;
}

/**
 * Loads promotions and flash messages for the admin promotions page.
 */
function sfbc_handle_admin_promotions_page(mysqli $conn): array
{
    $admin = $_SESSION['admin_username'];
    sfbc_promotions_ensure_schema($conn);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        sfbc_promotions_handle_post($conn, $admin);
    }

    $success = $_SESSION['promo_success'] ?? '';
    $error   = $_SESSION['promo_error'] ?? '';
    unset($_SESSION['promo_success'], $_SESSION['promo_error']);

    $promosResult = $conn->query("SELECT p.*,
        (SELECT COUNT(*) FROM bookings b
         WHERE UPPER(COALESCE(b.promo_code, '')) = UPPER(COALESCE(NULLIF(p.code, ''), p.title))
           AND LOWER(COALESCE(b.status, '')) NOT IN ('cancelled', 'canceled')) AS usage_count
        FROM promotions p
        ORDER BY p.is_active DESC, p.valid_until DESC");

    $promos = [];
    if ($promosResult) {
        while ($row = $promosResult->fetch_assoc()) {
            $promos[] = $row;
        }
    }

    return [
        'success'   => $success,
        'error'     => $error,
        'promos'    => $promos,
        'csrfField' => csrf_field(),
    ];
}
