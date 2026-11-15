<?php
/**
 * Admin reviews moderation controller.
 */

/**
 * Escapes text for review moderation views.
 */
function sfbc_review_html($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Handles review moderation actions and fetches reviews for the active tab.
 */
function sfbc_handle_admin_reviews_page(mysqli $conn): array
{
    $tab = ($_GET['tab'] ?? '') === 'published' ? 'published' : 'unpublished';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf_token();
        $id = filter_var($_POST['review_id'] ?? null, FILTER_VALIDATE_INT);
        $action = $_POST['action'] ?? '';
        if (!$id || $id < 1 || !in_array($action, ['approve', 'hide', 'delete'], true)) {
            http_response_code(400);
            exit('Invalid review action.');
        }

        if ($action === 'delete') {
            $stmt = $conn->prepare('DELETE FROM reviews WHERE id = ?');
            $stmt->bind_param('i', $id);
        } else {
            $approved = $action === 'approve' ? 1 : 0;
            $stmt = $conn->prepare('UPDATE reviews SET is_approved = ? WHERE id = ?');
            $stmt->bind_param('ii', $approved, $id);
        }
        $stmt->execute();
        $stmt->close();

        header('Location: admin_reviews?tab=' . $tab, true, 303);
        exit;
    }

    $reviewFilter = $tab === 'published' ? 'is_approved = 1' : '(is_approved IS NULL OR is_approved <> 1)';
    $result = $conn->query('SELECT id, guest_name, guest_location, rating, review_text, is_approved, created_at FROM reviews WHERE ' . $reviewFilter . ' ORDER BY id DESC');
    $reviews = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $reviews[] = $row;
        }
    }

    return [
        'tab'       => $tab,
        'reviews'   => $reviews,
        'csrfField' => csrf_field(),
    ];
}
