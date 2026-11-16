<?php
require_once __DIR__ . '/../services/mailer.php';

/**
 * Handles POST actions for admin_notifications.php and notifications.php,
 * then fetches notifications and summary metrics for rendering.
 */
function sfbc_handle_notifications_page(mysqli $conn, string $redirectRoute, bool $includeAdminStats): array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        require_csrf_token();
        $action = (string)$_POST['action'];

        if ($action === 'mark_read' && isset($_POST['notif_id'])) {
            $id   = (int)$_POST['notif_id'];
            $stmt = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
        } elseif ($action === 'mark_all_read') {
            $conn->query('UPDATE notifications SET is_read = 1');
            $_SESSION['notif_flash'] = 'All notifications marked as read.';
        } elseif ($action === 'delete' && isset($_POST['notif_id'])) {
            $id   = (int)$_POST['notif_id'];
            $stmt = $conn->prepare('DELETE FROM notifications WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
        } elseif ($includeAdminStats && $action === 'create_notif') {
            $title   = trim((string)($_POST['title'] ?? ''));
            $message = trim((string)($_POST['message'] ?? ''));
            $rawType = strtolower(trim((string)($_POST['type'] ?? 'info')));
            $type    = in_array($rawType, ['info', 'success', 'warning', 'alert'], true) ? $rawType : 'info';

            if ($title !== '' && $message !== '') {
                $stmt = $conn->prepare('INSERT INTO notifications (title, message, type) VALUES (?, ?, ?)');
                $stmt->bind_param('sss', $title, $message, $type);
                $stmt->execute();
                $stmt->close();
                $_SESSION['notif_flash'] = 'Broadcast alert posted to the team feed.';
            }
        } elseif ($action === 'accept_booking' && isset($_POST['notif_id'], $_POST['booking_id'])) {
            $notifId = (int)$_POST['notif_id'];
            $stmt    = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE id = ?');
            $stmt->bind_param('i', $notifId);
            $stmt->execute();
            $stmt->close();
            $_SESSION['notif_flash'] = 'Booking notification acknowledged. Confirmation is sent automatically after payment verification.';
        }

        header('Location: ' . $redirectRoute);
        exit;
    }

    $notifications     = [];
    $urgentCount       = 0;
    $urgentUnreadCount = 0;
    $todayCount        = 0;
    $todayDate         = date('Y-m-d');
    $notifsQuery       = $conn->query('SELECT * FROM notifications ORDER BY created_at DESC');
    if ($notifsQuery) {
        while ($row = $notifsQuery->fetch_assoc()) {
            $createdTs     = strtotime((string)$row['created_at']);
            $timeFormatted = date('M j, Y · g:i A', $createdTs);
            $isToday       = ($createdTs !== false && date('Y-m-d', $createdTs) === $todayDate);
            $rawType       = strtolower(trim((string)$row['type']));
            $type          = in_array($rawType, ['info', 'success', 'warning', 'alert'], true) ? $rawType : 'info';
            $isRead        = !empty($row['is_read']);
            $bookingId     = (int)($row['booking_id'] ?? 0);

            if ($isToday) {
                $todayCount++;
            }

            if ($type === 'alert' || $type === 'warning') {
                $urgentCount++;
                if (!$isRead) {
                    $urgentUnreadCount++;
                }
            }

            $detailJson = json_encode([
                'id'         => (int)$row['id'],
                'title'      => (string)$row['title'],
                'message'    => (string)$row['message'],
                'type'       => $type,
                'booking_id' => $bookingId,
                'time'       => $timeFormatted,
            ]);
            $notifications[] = [
                'id'         => (int)$row['id'],
                'title'      => (string)$row['title'],
                'message'    => (string)$row['message'],
                'type'       => $type,
                'booking_id' => $bookingId,
                'is_read'    => $isRead,
                'is_today'   => $isToday,
                'time'       => $timeFormatted,
                'badgeClass' => 'badge-' . $type,
                'detailJson' => $detailJson ?: '{}',
            ];
        }
    }

    $unreadQuery = $conn->query('SELECT COUNT(*) as unread FROM notifications WHERE is_read = 0');
    $unreadCount = $unreadQuery ? (int)$unreadQuery->fetch_assoc()['unread'] : 0;

    $pendingCount = 0;
    $maintCount   = 0;
    if ($includeAdminStats) {
        $pendingQuery = $conn->query("SELECT COUNT(*) as cnt FROM bookings WHERE status = 'Pending'");
        $pendingCount = $pendingQuery ? (int)$pendingQuery->fetch_assoc()['cnt'] : 0;

        $maintQuery = $conn->query("SELECT COUNT(*) as cnt FROM rooms WHERE status = 'maintenance'");
        $maintCount = $maintQuery ? (int)$maintQuery->fetch_assoc()['cnt'] : 0;
    }

    $notifFlash = $_SESSION['notif_flash'] ?? null;
    unset($_SESSION['notif_flash']);

    $frontendDir = __DIR__ . '/../../frontend';

    return [
        'notifications'     => $notifications,
        'totalCount'        => count($notifications),
        'todayCount'        => $todayCount,
        'unreadCount'       => $unreadCount,
        'urgentCount'       => $urgentCount,
        'urgentUnreadCount' => $urgentUnreadCount,
        'pendingCount'      => $pendingCount,
        'maintCount'        => $maintCount,
        'notifFlash'        => $notifFlash,
        'csrfField'         => csrf_field(),
        'versions'      => [
            'adminCss'     => filemtime($frontendDir . '/assets/css/admin.css'),
            'dashboardCss' => filemtime($frontendDir . '/assets/css/dashboard.css'),
            'pageCss'      => file_exists($frontendDir . '/assets/css/pages/admin-notifications.css')
                ? filemtime($frontendDir . '/assets/css/pages/admin-notifications.css')
                : 1,
            'pageJs'       => file_exists($frontendDir . '/assets/js/pages/admin-notifications.js')
                ? filemtime($frontendDir . '/assets/js/pages/admin-notifications.js')
                : 1,
        ],
    ];
}
