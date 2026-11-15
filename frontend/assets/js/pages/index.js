(function() {
    var config = window.SFBC_HOME_CONFIG || {};
    var checkinInput = document.getElementById('checkin');
    var checkoutInput = document.getElementById('checkout');
    var today = config.today || '';

    function nextDate(dateValue) {
        var parts = dateValue.split('-').map(Number);
        var date = new Date(parts[0], parts[1] - 1, parts[2]);
        date.setDate(date.getDate() + 1);
        return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
    }

    function syncBookingDates() {
        if (!checkinInput || !checkoutInput) return;
        checkinInput.min = today;
        var earliestCheckout = nextDate(checkinInput.value || today);
        checkoutInput.min = earliestCheckout;
        if (!checkoutInput.value || checkoutInput.value < earliestCheckout) {
            checkoutInput.value = earliestCheckout;
        }
    }

    if (checkinInput && checkoutInput) {
        syncBookingDates();
        checkinInput.addEventListener('change', syncBookingDates);
    }

    var heroSlides = document.querySelectorAll('.hero-bg-slide');
    var heroDots = document.querySelectorAll('.home-slide-dot');
    var heroCaption = document.getElementById('homeHeroCaption');
    var activeSlideIndex = 0;

    function showHeroSlide(index) {
        if (!heroSlides.length) return;
        heroSlides[activeSlideIndex].classList.remove('is-active');
        if (heroDots[activeSlideIndex]) {
            heroDots[activeSlideIndex].classList.remove('is-active');
            heroDots[activeSlideIndex].setAttribute('aria-pressed', 'false');
        }

        activeSlideIndex = ((index % heroSlides.length) + heroSlides.length) % heroSlides.length;
        var currentSlide = heroSlides[activeSlideIndex];
        currentSlide.classList.add('is-active');

        if (heroDots[activeSlideIndex]) {
            heroDots[activeSlideIndex].classList.add('is-active');
            heroDots[activeSlideIndex].setAttribute('aria-pressed', 'true');
        }
        if (heroCaption && currentSlide.dataset.caption) {
            heroCaption.textContent = currentSlide.dataset.caption;
        }
    }

    heroDots.forEach(function(dot) {
        dot.addEventListener('click', function() {
            var targetIndex = parseInt(dot.getAttribute('data-slide-target'), 10);
            if (!isNaN(targetIndex)) {
                showHeroSlide(targetIndex);
            }
        });
    });

    var prefersReducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (heroSlides.length > 1 && !prefersReducedMotion) {
        setInterval(function() {
            showHeroSlide(activeSlideIndex + 1);
        }, 5500);
    }

    function updateStarDisplay(rating) {
        var stars = document.querySelectorAll('#starRatingContainer .star-item');
        stars.forEach(function(s, idx) {
            s.style.color = (idx < rating) ? '#D4882A' : '#D6CEC5';
        });
    }

    function setRating(r) {
        document.getElementById('reviewRatingInput').value = r;
        updateStarDisplay(r);
    }

    function openReviewModal() {
        document.getElementById('reviewModal').style.display = 'flex';
        setRating(5);
    }

    function closeReviewModal() {
        document.getElementById('reviewModal').style.display = 'none';
    }

    async function handleReviewSubmit(e) {
        e.preventDefault();
        var form = e.target;
        var btn = document.getElementById('reviewSubmitBtn');
        btn.disabled = true;
        btn.textContent = 'Submitting...';

        var fd = new FormData(form);
        try {
            var res = await fetch('api_submit_review.php', {
                method: 'POST',
                body: fd
            });
            var data = await res.json();
            if (res.ok && data.success === true) {
                SiteDialog.alert('Thank you! Your review has been submitted and will appear after admin approval.');
                location.reload();
            } else {
                SiteDialog.alert(data.error || 'Failed to submit review. Please try again.');
            }
        } catch (err) {
            SiteDialog.alert('Unable to confirm your review was submitted. Please try again later.');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Submit Review';
        }
    }

    window.setRating = setRating;
    window.openReviewModal = openReviewModal;
    window.closeReviewModal = closeReviewModal;
    window.handleReviewSubmit = handleReviewSubmit;
})();
