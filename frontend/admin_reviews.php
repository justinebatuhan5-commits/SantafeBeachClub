<?php
require_once __DIR__ . '/../backend/helpers/admin_auth_check.php';
require_once __DIR__ . '/../backend/config/db.php';
$active_page = 'reviews';
$tab = ($_GET['tab'] ?? '') === 'published' ? 'published' : 'unpublished';
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
    header('Location: admin_reviews?tab=' . $tab, true, 303); exit;
}
$review_filter = $tab === 'published' ? 'is_approved = 1' : '(is_approved IS NULL OR is_approved <> 1)';
$reviews = $conn->query('SELECT id, guest_name, guest_location, rating, review_text, is_approved, created_at FROM reviews WHERE ' . $review_filter . ' ORDER BY id DESC');
?>
<!DOCTYPE html>
<html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Guest Reviews - Santa Fe</title>
<link rel="stylesheet" href="assets/css/admin.css?v=<?= filemtime(__DIR__ . '/assets/css/admin.css') ?>">
<style>
.review-list {
    display:grid;
    grid-template-columns:repeat(2, minmax(0, 1fr));
    gap:16px;
}
.review-card {
    display:flex;
    flex-direction:column;
    min-width:0;
    min-height:270px;
    padding:20px;
    background:var(--bg-surface);
    border:1px solid var(--border);
    border-radius:14px;
    box-shadow:var(--shadow-xs);
    transition:transform .18s ease, box-shadow .18s ease, border-color .18s ease;
}
.review-card:hover {
    transform:translateY(-2px);
    border-color:rgba(132, 86, 60, .3);
    box-shadow:var(--shadow-md);
}
.review-card.review-empty {
    grid-column:1 / -1;
    min-height:auto;
    align-items:center;
    padding:42px 20px;
    color:var(--text-muted);
    text-align:center;
}
.review-top { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; }
.review-person { min-width:0; }
.review-meta { color:var(--text-muted); font-size:11px; margin:5px 0 0; overflow-wrap:anywhere; }
.review-content {
    flex:1;
    white-space:pre-wrap;
    overflow-wrap:anywhere;
    line-height:1.6;
    margin:14px 0 18px;
    padding:14px;
    color:var(--text-main);
    background:var(--bg-body);
    border-left:3px solid rgba(132, 86, 60, .45);
    border-radius:0 10px 10px 0;
}
.review-stars { display:flex; align-items:center; gap:7px; color:var(--primary); margin-top:12px; font-size:14px; letter-spacing:2px; }
.review-rating-label { color:var(--text-muted); font-size:10px; font-weight:700; letter-spacing:0; }
.review-status {
    flex:0 0 auto;
    display:inline-flex;
    align-items:center;
    gap:5px;
    padding:5px 9px;
    border-radius:999px;
    font-size:10px;
    font-weight:800;
    white-space:nowrap;
}
.review-status.pending { color:#9A3412; background:#FFF7ED; border:1px solid #FED7AA; }
.review-status.published { color:#166534; background:#F0FDF4; border:1px solid #BBF7D0; }
.review-card h2 { margin:0; color:var(--text-main); overflow-wrap:anywhere; font-size:16px; line-height:1.35; }
.review-note {
    margin:0 0 18px;
    padding:11px 14px;
    color:var(--text-muted);
    background:var(--bg-surface);
    border:1px solid var(--border);
    border-radius:10px;
    font-size:12px;
    line-height:1.5;
}
.review-tabs {
    display:inline-flex;
    flex-wrap:wrap;
    gap:5px;
    margin-bottom:14px;
    padding:5px;
    background:var(--bg-surface);
    border:1px solid var(--border);
    border-radius:11px;
}
.review-tab {
    display:flex;
    align-items:center;
    gap:7px;
    padding:8px 13px;
    border-radius:8px;
    color:var(--text-muted);
    font-size:12px;
    font-weight:700;
    text-decoration:none;
}
.review-tab:hover { color:var(--primary); background:var(--sidebar-hover); }
.review-tab[aria-current="page"] { background:var(--primary); color:#fff; box-shadow:0 3px 10px rgba(124,83,60,.2); }
.review-tab:focus-visible { outline:2px solid var(--primary); outline-offset:3px; }
.review-actions { display:flex; gap:8px; align-items:center; padding-top:15px; border-top:1px solid var(--border); }
.review-actions form { flex:1; margin:0; }
.review-actions button { width:100%; min-height:38px; justify-content:center; padding:8px 12px; font-size:12px; }
.review-delete { border:1px solid #fecaca; border-radius:8px; background:#fff; color:#b91c1c; font:inherit; font-weight:700; cursor:pointer; }
.review-delete:hover { background:#fef2f2; border-color:#f87171; }
.review-delete:focus-visible { outline:2px solid #b91c1c; outline-offset:3px; }
@media (max-width:1100px) { .review-list { grid-template-columns:1fr; } }
@media (max-width:560px) {
    .review-card { min-height:auto; padding:16px; }
    .review-top { flex-direction:column; }
    .review-actions { flex-direction:column; }
    .review-actions form { width:100%; }
    .review-tabs { display:grid; grid-template-columns:1fr 1fr; width:100%; }
    .review-tab { justify-content:center; text-align:center; }
}
</style><script src="assets/js/site-dialog.js?v=1"></script>
<?php require __DIR__ . '/partials/_responsive.php'; ?>
</head><body>
<?php include __DIR__ . '/partials/_sidebar.php'; ?>
<main class="admin-main">
<?php $page_title = 'Reviews & Inquiries'; $page_subtitle = 'Manage guest feedback and messages in one place.'; include __DIR__ . '/partials/_page_header.php'; ?>
<div class="admin-body">
<?php $guest_communications_active = 'reviews'; include __DIR__ . '/partials/_guest_communications_tabs.php'; ?>
<nav class="review-tabs" aria-label="Guest review status">
<a class="review-tab" href="admin_reviews?tab=unpublished" <?= $tab === 'unpublished' ? 'aria-current="page"' : '' ?>>
<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
Unpublished Reviews</a>
<a class="review-tab" href="admin_reviews?tab=published" <?= $tab === 'published' ? 'aria-current="page"' : '' ?>>
<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m7 12 3 3 7-7"/></svg>
Published Reviews</a>
</nav>
<p class="review-note"><?= $tab === 'published' ? 'These reviews are approved for display on the homepage. Hide a review to move it to Unpublished Reviews, or delete it permanently.' : 'These reviews are hidden or awaiting approval. Approve a review to move it to Published Reviews and make it available on the homepage, or delete it permanently.' ?></p>
<div class="review-list">
<?php if ($reviews->num_rows === 0): ?>
<div class="review-card review-empty"><?= $tab === 'published' ? 'No published reviews yet. Approve a review from Unpublished Reviews to show it here.' : 'No unpublished reviews. New submissions and hidden reviews will appear here.' ?></div>
<?php endif; ?>
<?php while ($review = $reviews->fetch_assoc()): $published = (int)$review['is_approved'] === 1; $rating = max(1, min(5, (int)$review['rating'])); ?>
<article class="review-card">
<div class="review-top"><div class="review-person"><h2><?= review_html($review['guest_name']) ?></h2>
<p class="review-meta"><?= review_html($review['guest_location'] ?: 'Location not provided') ?> &middot; <?= review_html(date('M j, Y · g:i A', strtotime($review['created_at']))) ?></p></div>
<span class="review-status <?= $published ? 'published' : 'pending' ?>"><?= $published ? 'Published' : 'Awaiting approval' ?></span></div>
<div class="review-stars" aria-label="<?= $rating ?> out of 5 stars"><span style="display:inline-flex;align-items:center;gap:2px;vertical-align:-2px;"><?= str_repeat('<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>', $rating) . str_repeat('<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true" style="opacity:.45;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>', 5 - $rating) ?></span><span class="review-rating-label"><?= $rating ?>/5</span></div>
<p class="review-content"><?= review_html($review['review_text']) ?></p>
<div class="review-actions">
<form method="post" action="admin_reviews?tab=<?= review_html($tab) ?>"><?= csrf_field() ?>
<input type="hidden" name="review_id" value="<?= (int)$review['id'] ?>">
<button class="btn-primary" type="submit" name="action" value="<?= $published ? 'hide' : 'approve' ?>"><?= $published ? 'Hide from website' : 'Approve and publish' ?></button>
</form>
<form method="post" action="admin_reviews?tab=<?= review_html($tab) ?>" data-site-title="Delete review?" data-site-button="Delete review" data-site-confirm="Permanently delete this review? It will be removed from the homepage and admin area. This cannot be undone."><?= csrf_field() ?>
<input type="hidden" name="review_id" value="<?= (int)$review['id'] ?>">
<button class="review-delete" type="submit" name="action" value="delete">Delete review</button>
</form>
</div></article>
<?php endwhile; ?>
</div></div></main></body></html>
