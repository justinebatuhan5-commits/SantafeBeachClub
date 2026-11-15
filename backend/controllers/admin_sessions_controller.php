<?php
/**
 * Admin active browser sessions controller.
 */

/**
 * Formats a concise browser label from a user agent string.
 */
function sfbc_session_device_label(string $ua): string
{
    if (stripos($ua, 'Edg/') !== false) {
        return 'Microsoft Edge';
    }
    if (stripos($ua, 'Chrome/') !== false) {
        return 'Google Chrome';
    }
    if (stripos($ua, 'Firefox/') !== false) {
        return 'Mozilla Firefox';
    }
    if (stripos($ua, 'Safari/') !== false) {
        return 'Safari';
    }
    return 'Unknown browser';
}

/**
 * Handles remote session revocation and fetches active admin sessions.
 */
function sfbc_handle_admin_sessions_page(mysqli $conn): array
{
    $message     = '';
    $currentHash = admin_session_hash();
    $userId      = (int)$_SESSION['admin_account_id'];
    $userType    = (string)$_SESSION['admin_user_type'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf_token();
        if (!admin_password_recent()) {
            header('Location: admin_reauth.php?return=admin_sessions');
            exit;
        }
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'revoke') {
            $id = (int)($_POST['session_id'] ?? 0);
            $stmt = $conn->prepare('UPDATE admin_sessions SET revoked_at=NOW() WHERE id=? AND user_id=? AND user_type=? AND session_hash<>? AND revoked_at IS NULL');
            $stmt->bind_param('iiss', $id, $userId, $userType, $currentHash);
            $stmt->execute();
            $stmt->close();
            $message = 'The selected session has been signed out.';
        } elseif ($action === 'revoke_others') {
            $stmt = $conn->prepare('UPDATE admin_sessions SET revoked_at=NOW() WHERE user_id=? AND user_type=? AND session_hash<>? AND revoked_at IS NULL');
            $stmt->bind_param('iss', $userId, $userType, $currentHash);
            $stmt->execute();
            $stmt->close();
            $message = 'All other sessions have been signed out.';
        }
        SecurityLogger::log($conn, 'ADMIN_SESSIONS_REVOKED', $message, SecurityLogger::LEVEL_WARNING, (string)$_SESSION['admin_username']);
    }

    $stmt = $conn->prepare('SELECT id,session_hash,ip_address,user_agent,created_at,last_seen_at FROM admin_sessions WHERE user_id=? AND user_type=? AND revoked_at IS NULL AND created_at >= DATE_SUB(NOW(), INTERVAL 8 HOUR) AND last_seen_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE) ORDER BY last_seen_at DESC');
    $stmt->bind_param('is', $userId, $userType);
    $stmt->execute();
    $sessions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return [
        'message'     => $message,
        'currentHash' => $currentHash,
        'sessions'    => $sessions,
        'csrfField'   => csrf_field(),
    ];
}
