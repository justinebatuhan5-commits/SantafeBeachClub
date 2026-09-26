<?php
declare(strict_types=1);

/** Persistent, revocable admin/receptionist sessions and step-up authentication. */

function admin_session_hash(): string
{
    return hash('sha256', session_id());
}

function admin_session_client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

function admin_session_register(mysqli $db, int $userId, string $userType, string $username): void
{
    $_SESSION['admin_account_id'] = $userId;
    $_SESSION['admin_user_type'] = $userType;
    $hash = admin_session_hash();
    $ip = admin_session_client_ip();
    $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown device'), 0, 500);
    $stmt = $db->prepare("INSERT INTO admin_sessions (session_hash,user_id,user_type,username,ip_address,user_agent)
        VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE username=VALUES(username),ip_address=VALUES(ip_address),user_agent=VALUES(user_agent),last_seen_at=NOW(),revoked_at=NULL");
    $stmt->bind_param('sissss', $hash, $userId, $userType, $username, $ip, $ua);
    $stmt->execute();
    $stmt->close();
}

/** Returns false when this browser session has been remotely revoked. */
function admin_session_validate(mysqli $db): bool
{
    $userId = (int)($_SESSION['admin_account_id'] ?? 0);
    $userType = (string)($_SESSION['admin_user_type'] ?? (($_SESSION['admin_role'] ?? '') === 'admin' ? 'admin' : 'receptionist'));
    $username = (string)($_SESSION['admin_username'] ?? '');

    // Transparently enroll sessions created before this feature was deployed.
    if ($userId < 1 && $username !== '') {
        $table = $userType === 'admin' ? 'administrators' : 'receptionists';
        $lookup = $db->prepare("SELECT id FROM `{$table}` WHERE username=? LIMIT 1");
        $lookup->bind_param('s', $username);
        $lookup->execute();
        $row = $lookup->get_result()->fetch_assoc();
        $lookup->close();
        if ($row) {
            admin_session_register($db, (int)$row['id'], $userType, $username);
            $userId = (int)$row['id'];
        }
    }
    if ($userId < 1) return false;

    $hash = admin_session_hash();
    $stmt = $db->prepare('SELECT revoked_at FROM admin_sessions WHERE session_hash=? AND user_id=? AND user_type=? LIMIT 1');
    $stmt->bind_param('sis', $hash, $userId, $userType);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        admin_session_register($db, $userId, $userType, $username);
        return true;
    }
    if ($row['revoked_at'] !== null) return false;
    $touch = $db->prepare('UPDATE admin_sessions SET last_seen_at=NOW() WHERE session_hash=? AND last_seen_at < DATE_SUB(NOW(), INTERVAL 60 SECOND)');
    $touch->bind_param('s', $hash);
    $touch->execute();
    $touch->close();
    return true;
}

function admin_session_revoke_current(mysqli $db): void
{
    $hash = admin_session_hash();
    $stmt = $db->prepare('UPDATE admin_sessions SET revoked_at=NOW() WHERE session_hash=?');
    $stmt->bind_param('s', $hash);
    $stmt->execute();
    $stmt->close();
}

function admin_password_recent(int $seconds = 300): bool
{
    return !empty($_SESSION['password_confirmed_at']) && (time() - (int)$_SESSION['password_confirmed_at']) <= $seconds;
}

function require_recent_admin_password_json(): void
{
    if (($_SESSION['admin_role'] ?? '') !== 'admin' || !admin_password_recent()) {
        http_response_code(428);
        echo json_encode([
            'success' => false,
            'message' => 'Please confirm your administrator password before this sensitive action.',
            'reauth_required' => true,
            'redirect' => '../frontend/admin_reauth.php?return=admin_refunds'
        ]);
        exit;
    }
}
