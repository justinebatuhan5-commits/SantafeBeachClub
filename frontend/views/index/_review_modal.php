    <!-- Guest Review Submission Modal -->
    <div id="reviewModal" class="home-review-modal" role="dialog" aria-modal="true" aria-labelledby="reviewModalTitle">
        <div class="home-review-dialog">
            <button type="button" class="home-review-close" onclick="closeReviewModal()" aria-label="Close review form">&times;</button>
            <div class="home-review-modal-head">
                <img src="assets/logo.jpg" alt="Santa Fe Beach Club Logo" class="home-review-modal-logo">
                <h3 id="reviewModalTitle">Share Your Experience</h3>
                <p>Tell future guests how your stay at Santa Fe Beach Club felt.</p>
            </div>
            <form id="reviewSubmitForm" onsubmit="handleReviewSubmit(event)">
                <?php echo csrf_field(); ?>
                <div class="home-modal-field">
                    <label>Your Rating</label>
                    <div id="starRatingContainer" class="home-modal-stars">
                        <span onclick="setRating(1)" class="star-item"><svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true" style="display:block;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></span>
                        <span onclick="setRating(2)" class="star-item"><svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true" style="display:block;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></span>
                        <span onclick="setRating(3)" class="star-item"><svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true" style="display:block;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></span>
                        <span onclick="setRating(4)" class="star-item"><svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true" style="display:block;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></span>
                        <span onclick="setRating(5)" class="star-item"><svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true" style="display:block;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></span>
                    </div>
                    <input type="hidden" name="rating" id="reviewRatingInput" value="5" required>
                </div>
                <div class="mobile-form-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div class="home-modal-field">
                        <label for="reviewGuestName">Your Name *</label>
                        <input id="reviewGuestName" type="text" name="guest_name" required placeholder="e.g. Maria R." autocomplete="off">
                    </div>
                    <div class="home-modal-field">
                        <label for="reviewGuestLocation">Location</label>
                        <input id="reviewGuestLocation" type="text" name="guest_location" placeholder="e.g. Cebu, Philippines" autocomplete="off">
                    </div>
                </div>
                <div class="home-modal-field">
                    <label for="reviewTextArea">Your Review *</label>
                    <textarea id="reviewTextArea" name="review_text" rows="4" required placeholder="Tell future guests about the views, staff, or rooms..."></textarea>
                </div>
                <button type="submit" id="reviewSubmitBtn" class="home-btn-primary home-review-submit">
                    Submit Review
                </button>
            </form>
        </div>
    </div>
