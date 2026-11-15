<?php
$tab       = $reviewsData['tab'];
$reviews   = $reviewsData['reviews'];
$csrfField = $reviewsData['csrfField'];
?>
<nav class="review-tabs" aria-label="Guest review status">
    <a class="review-tab" href="admin_reviews?tab=unpublished" <?= $tab === 'unpublished' ? 'aria-current="page"' : '' ?>>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
        Unpublished Reviews
    </a>
    <a class="review-tab" href="admin_reviews?tab=published" <?= $tab === 'published' ? 'aria-current="page"' : '' ?>>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m7 12 3 3 7-7"/></svg>
        Published Reviews
    </a>
</nav>

<p class="review-note">
    <?= $tab === 'published'
        ? 'These reviews are approved for display on the homepage. Hide a review to move it to Unpublished Reviews, or delete it permanently.'
        : 'These reviews are hidden or awaiting approval. Approve a review to move it to Published Reviews and make it available on the homepage, or delete it permanently.' ?>
</p>

<div class="review-list">
    <?php if (empty($reviews)): ?>
        <div class="review-card review-empty">
            <?= $tab === 'published'
                ? 'No published reviews yet. Approve a review from Unpublished Reviews to show it here.'
                : 'No unpublished reviews. New submissions and hidden reviews will appear here.' ?>
        </div>
    <?php endif; ?>

    <?php foreach ($reviews as $review):
        $published = (int)$review['is_approved'] === 1;
        $rating    = max(1, min(5, (int)$review['rating']));
    ?>
        <article class="review-card">
            <div class="review-top">
                <div class="review-person">
                    <h2><?= sfbc_review_html($review['guest_name']) ?></h2>
                    <p class="review-meta"><?= sfbc_review_html($review['guest_location'] ?: 'Location not provided') ?> &middot; <?= sfbc_review_html(date('M j, Y · g:i A', strtotime($review['created_at']))) ?></p>
                </div>
                <span class="review-status <?= $published ? 'published' : 'pending' ?>"><?= $published ? 'Published' : 'Awaiting approval' ?></span>
            </div>

            <div class="review-stars" aria-label="<?= $rating ?> out of 5 stars">
                <span class="review-stars-icons"><?= str_repeat('<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>', $rating) . str_repeat('<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true" class="review-star-empty"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>', 5 - $rating) ?></span>
                <span class="review-rating-label"><?= $rating ?>/5</span>
            </div>

            <p class="review-content"><?= sfbc_review_html($review['review_text']) ?></p>

            <div class="review-actions">
                <form method="post" action="admin_reviews?tab=<?= sfbc_review_html($tab) ?>">
                    <?= $csrfField ?>
                    <input type="hidden" name="review_id" value="<?= (int)$review['id'] ?>">
                    <button class="btn-primary" type="submit" name="action" value="<?= $published ? 'hide' : 'approve' ?>"><?= $published ? 'Hide from website' : 'Approve and publish' ?></button>
                </form>
                <form method="post" action="admin_reviews?tab=<?= sfbc_review_html($tab) ?>" data-site-title="Delete review?" data-site-button="Delete review" data-site-confirm="Permanently delete this review? It will be removed from the homepage and admin area. This cannot be undone.">
                    <?= $csrfField ?>
                    <input type="hidden" name="review_id" value="<?= (int)$review['id'] ?>">
                    <button class="review-delete" type="submit" name="action" value="delete">Delete review</button>
                </form>
            </div>
        </article>
    <?php endforeach; ?>
</div>
