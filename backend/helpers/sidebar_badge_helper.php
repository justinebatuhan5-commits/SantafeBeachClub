<?php
/**
 * sidebar_badge_helper.php — Shared role, avatar, and unread badge queries for Admin & Reception sidebars.
 */

if (!function_exists('_sb_active')) {
    function _sb_active($page, $current)
    {
        return $page === $current ? 'active' : '';
    }
}

if (!function_exists('_sb_badge')) {
    function _sb_badge($count, $type = '')
    {
        if ($count <= 0) {
            return '';
        }
        $display = $count > 99 ? '99+' : (string)$count;
        $typeAttr = $type !== '' ? ' data-badge-type="' . htmlspecialchars($type) . '"' : '';
        return '<span class="sidebar-badge"' . $typeAttr . '>' . htmlspecialchars($display) . '</span>';
    }
}

if (!function_exists('sfbc_resolve_sidebar_state')) {
    function sfbc_resolve_sidebar_state(?mysqli $conn): array
    {
        $role = $_SESSION['admin_role'] ?? 'receptionist';
        $user = $_SESSION['admin_username'] ?? '';
        $sidebarPhoto = $_SESSION['admin_profile_photo'] ?? null;

        if (!empty($user) && $conn !== null) {
            // Check administrators first, then receptionists
            $userTable = ($role === 'admin') ? 'administrators' : 'receptionists';
            $photoFound = null;

            if ($roleStmt = $conn->prepare("SELECT profile_photo FROM `{$userTable}` WHERE username = ? LIMIT 1")) {
                $roleStmt->bind_param("s", $user);
                $roleStmt->execute();
                $res = $roleStmt->get_result()->fetch_assoc();
                $roleStmt->close();
                if ($res) {
                    $photoFound = $res['profile_photo'] ?? null;
                }
            }

            // Fallback if not found in expected table (e.g. role changed in another session)
            if ($photoFound === null) {
                $otherTable = ($userTable === 'administrators') ? 'receptionists' : 'administrators';
                if ($otherStmt = $conn->prepare("SELECT profile_photo FROM `{$otherTable}` WHERE username = ? LIMIT 1")) {
                    $otherStmt->bind_param("s", $user);
                    $otherStmt->execute();
                    $res = $otherStmt->get_result()->fetch_assoc();
                    $otherStmt->close();
                    if ($res) {
                        $photoFound = $res['profile_photo'] ?? null;
                        $role = ($otherTable === 'administrators') ? 'admin' : 'receptionist';
                        $_SESSION['admin_role'] = $role;
                    }
                }
            }

            if (!empty($photoFound)) {
                $sidebarPhoto = $photoFound;
                $_SESSION['admin_profile_photo'] = $sidebarPhoto;
            }
        }

        // Unread notification count
        $unreadCount = 0;
        if ($conn !== null && $countStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM notifications WHERE is_read = 0")) {
            $countStmt->execute();
            $countResult = $countStmt->get_result()->fetch_assoc();
            $unreadCount = (int)($countResult['cnt'] ?? 0);
            $countStmt->close();
        }

        // Unread inquiries & live chats count
        $unreadInquiries = 0;
        $unreadLiveChats = 0;
        if ($conn !== null) {
            $tableCheck = $conn->query("SHOW TABLES LIKE 'inquiries'");
            if ($tableCheck && $tableCheck->num_rows > 0) {
                $hasChatTblCheck = @$conn->query("SHOW TABLES LIKE 'chatbot_conversations'");
                $liveChatCond = ($hasChatTblCheck && $hasChatTblCheck->num_rows > 0)
                    ? "(i.subject LIKE '[AI Concierge Handoff]%' OR EXISTS (SELECT 1 FROM chatbot_conversations c WHERE c.inquiry_id = i.id))"
                    : "(i.subject LIKE '[AI Concierge Handoff]%')";
                if ($countLiveStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM inquiries i WHERE i.status = 'Unread' AND {$liveChatCond}")) {
                    $countLiveStmt->execute();
                    $countLiveResult = $countLiveStmt->get_result()->fetch_assoc();
                    $unreadLiveChats = (int)($countLiveResult['cnt'] ?? 0);
                    $countLiveStmt->close();
                }
                if ($countInqStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM inquiries i WHERE i.status = 'Unread' AND NOT {$liveChatCond}")) {
                    $countInqStmt->execute();
                    $countInqResult = $countInqStmt->get_result()->fetch_assoc();
                    $unreadInquiries = (int)($countInqResult['cnt'] ?? 0);
                    $countInqStmt->close();
                }
            }
        }

        // Pending bookings count
        $pendingBookings = 0;
        if ($conn !== null && $countBkgStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM bookings WHERE status = 'Pending'")) {
            $countBkgStmt->execute();
            $countBkgResult = $countBkgStmt->get_result()->fetch_assoc();
            $pendingBookings = (int)($countBkgResult['cnt'] ?? 0);
            $countBkgStmt->close();
        }

        // Pending / Unverified payments count
        $pendingPayments = 0;
        if ($conn !== null) {
            $tableCheckPay = $conn->query("SHOW TABLES LIKE 'payments'");
            if ($tableCheckPay && $tableCheckPay->num_rows > 0) {
                if ($countPayStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM payments WHERE LOWER(status) = 'pending'")) {
                    $countPayStmt->execute();
                    $countPayResult = $countPayStmt->get_result()->fetch_assoc();
                    $pendingPayments = (int)($countPayResult['cnt'] ?? 0);
                    $countPayStmt->close();
                }
            }
        }

        // Pending refund requests count
        $pendingRefunds = 0;
        if ($conn !== null) {
            $tableCheckRefund = @$conn->query("SHOW TABLES LIKE 'refund_requests'");
            if ($tableCheckRefund && $tableCheckRefund->num_rows > 0) {
                if ($countRefStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM refund_requests WHERE status = 'requested'")) {
                    $countRefStmt->execute();
                    $countRefResult = $countRefStmt->get_result()->fetch_assoc();
                    $pendingRefunds = (int)($countRefResult['cnt'] ?? 0);
                    $countRefStmt->close();
                }
            }
        }

        return [
            'role'              => $role,
            'user'              => $user,
            'sidebar_photo'     => $sidebarPhoto,
            'is_admin'          => ($role === 'admin'),
            'unread_count'      => $unreadCount,
            'unread_inquiries'  => $unreadInquiries,
            'unread_live_chats' => $unreadLiveChats,
            'pending_bookings'  => $pendingBookings,
            'pending_payments'  => $pendingPayments,
            'pending_refunds'   => $pendingRefunds,
        ];
    }
}
