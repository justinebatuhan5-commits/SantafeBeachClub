<?php
require_once __DIR__ . '/../backend/helpers/admin_auth_check.php';
require_once __DIR__ . '/../backend/config/db.php';
$active_page = 'reviews';
function review_html($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
    $id = filter_var($_POST['review_id'] ?? null, FILTER_VALIDATE_INT);
    $action = $_POST['action'] ?? '';
    if (!$id || $id < 1 || !in_array($action, ['approve', 'hide', 'delete'], true)) {
        http_response_code(400); exit('Invalid review action.');
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
    header('Location: admin_reviews', true, 303); exit;
}
$reviews = $conn->query('SELECT id, guest_name, guest_location, rating, review_text, is_approved, created_at FROM reviews ORDER BY is_approved ASC, id DESC');
?>
<!DOCTYPE html>
<html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Guest Reviews - Santa Fe</title>
<link rel="stylesheet" href="assets/css/admin.css?v=<?= filemtime(__DIR__ . '/assets/css/admin.css') ?>">
<style>
.review-list { display:grid; gap:16px; }
.review-card { padding:24px; background:var(--bg-surface); border:1px solid var(--border); border-radius:16px; min-width:0; }
.review-top { display:flex; justify-content:space-between; gap:16px; flex-wrap:wrap; }
.review-meta { color:var(--text-muted); font-size:13px; margin-top:6px; }
.review-content { white-space:pre-wrap; overflow-wrap:anywhere; line-height:1.7; margin:18px 0; }
.review-stars { color:var(--primary); margin-top:12px; }
.review-status { color:var(--text-muted); font-size:13px; }
.review-card h2 { overflow-wrap:anywhere; font-size:17px; }
.review-note { color:var(--text-muted); margin-bottom:20px; }
.review-actions { display:flex; flex-wrap:wrap; gap:12px; align-items:center; }
.review-delete { padding:10px 18px; border:1px solid #b91c1c; border-radius:8px; background:#fff; color:#b91c1c; font:inherit; font-weight:600; cursor:pointer; }
.review-delete:hover { background:#fef2f2; }
.review-delete:focus-visible { outline:2px solid #b91c1c; outline-offset:3px; }
</style></head><body>
<?php include __DIR__ . '/partials/_sidebar.php'; ?>
<main class="admin-main">
<?php $page_title = 'Guest Reviews'; $page_subtitle = 'Review guest feedback and control what appears on your website.'; include __DIR__ . '/partials/_page_header.php'; ?>
<div class="admin-body">
<p class="review-note">New reviews wait for approval. Hidden / awaiting approval reviews are not shown on the website. Approve to publish, hide a published review, or delete a review permanently.</p>
<div class="review-list">
<?php if ($reviews->num_rows === 0): ?>
<div class="review-card">No guest reviews yet. Submitted reviews will appear here.</div>
<?php endif; ?>
<?php while ($review = $reviews->fetch_assoc()): $published = (int)$review['is_approved'] === 1; $rating = max(1, min(5, (int)$review['rating'])); ?>
<article class="review-card">
<div class="review-top"><div><h2><?= review_html($review['guest_name']) ?></h2>
<p class="review-meta"><?= review_html($review['guest_location'] ?: 'Location not provided') ?> &middot; <?= review_html($review['created_at']) ?></p></div>
<span class="review-status"><?= $published ? 'Published' : 'Hidden / awaiting approval' ?></span></div>
<div class="review-stars" aria-label="<?= $rating ?> out of 5 stars"><?= str_repeat('&#9733;', $rating) . str_repeat('&#9734;', 5 - $rating) ?></div>
<p class="review-content"><?= review_html($review['review_text']) ?></p>
<div class="review-actions">
<form method="post"><?= csrf_field() ?>
<input type="hidden" name="review_id" value="<?= (int)$review['id'] ?>">
<button class="btn-primary" type="submit" name="action" value="<?= $published ? 'hide' : 'approve' ?>"><?= $published ? 'Hide from website' : 'Approve and publish' ?></button>
</form>
<form method="post" onsubmit="return confirm('Permanently delete this review? It will be removed from the homepage and admin area. This cannot be undone.');"><?= csrf_field() ?>
<input type="hidden" name="review_id" value="<?= (int)$review['id'] ?>">
<button class="review-delete" type="submit" name="action" value="delete">Delete review</button>
</form>
</div></article>
<?php endwhile; ?>
</div></div></main></body></html>
