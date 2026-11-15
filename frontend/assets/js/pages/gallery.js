(function () {
    const grid = document.getElementById('photoGrid');
    const layoutButtons = Array.from(document.querySelectorAll('[data-gallery-layout]'));
    const cards = Array.from(document.querySelectorAll('.photo-card'));
    const viewer = document.querySelector('.gallery-lightbox');
    const slideshowBtn = document.getElementById('gallerySlideshowBtn');

    if (grid && layoutButtons.length > 0) {
        layoutButtons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                const mode = btn.getAttribute('data-gallery-layout');
                const isUniform = mode === 'uniform';
                grid.classList.toggle('is-uniform', isUniform);
                layoutButtons.forEach(function (other) {
                    other.setAttribute('aria-pressed', other === btn ? 'true' : 'false');
                });
            });
        });
    }

    if (!viewer || cards.length === 0) return;

    const image = viewer.querySelector('.lightbox-image');
    const counter = viewer.querySelector('.lightbox-counter');
    const dateEl = document.getElementById('lightbox-date');
    const thumbs = Array.from(viewer.querySelectorAll('.lightbox-thumb'));
    const prevBtn = viewer.querySelector('.lightbox-prev');
    const nextBtn = viewer.querySelector('.lightbox-next');
    const closeBtn = viewer.querySelector('.lightbox-close');
    const stage = document.getElementById('lightboxStage');

    let index = 0;
    let opener = null;
    let previousOverflow = '';
    let touchStartX = null;

    function showPhoto(next) {
        index = (next + cards.length) % cards.length;
        const card = cards[index];
        const source = card.querySelector('img');
        image.src = source.currentSrc || source.src;
        image.alt = source.alt;
        counter.textContent = 'Photo ' + (index + 1) + ' of ' + cards.length;
        if (dateEl) {
            const added = card.getAttribute('data-date') || '';
            dateEl.textContent = added ? 'Added ' + added : '';
        }
        thumbs.forEach(function (thumb, tIdx) {
            const active = tIdx === index;
            thumb.classList.toggle('is-active', active);
            if (active && typeof thumb.scrollIntoView === 'function') {
                thumb.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
            }
        });
    }

    function openAt(startIndex, triggerEl) {
        opener = triggerEl || cards[startIndex] || null;
        showPhoto(startIndex);
        previousOverflow = document.body.style.overflow;
        if (!viewer.open) {
            viewer.showModal();
        }
        document.body.style.overflow = 'hidden';
    }

    cards.forEach(function (card, i) {
        card.addEventListener('click', function () {
            openAt(i, card);
        });
    });

    if (slideshowBtn) {
        slideshowBtn.addEventListener('click', function () {
            openAt(0, slideshowBtn);
        });
    }

    thumbs.forEach(function (thumb, i) {
        thumb.addEventListener('click', function () {
            showPhoto(i);
        });
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', function () { viewer.close(); });
    }
    if (prevBtn) {
        prevBtn.addEventListener('click', function () { showPhoto(index - 1); });
    }
    if (nextBtn) {
        nextBtn.addEventListener('click', function () { showPhoto(index + 1); });
    }
    viewer.querySelectorAll('.lightbox-prev, .lightbox-next').forEach(function (button) {
        button.hidden = cards.length < 2;
    });

    viewer.addEventListener('keydown', function (event) {
        if (event.key === 'ArrowLeft') { event.preventDefault(); showPhoto(index - 1); }
        if (event.key === 'ArrowRight') { event.preventDefault(); showPhoto(index + 1); }
        if (event.key === 'Home') { event.preventDefault(); showPhoto(0); }
        if (event.key === 'End') { event.preventDefault(); showPhoto(cards.length - 1); }
    });

    if (stage) {
        stage.addEventListener('touchstart', function (event) {
            if (event.touches && event.touches.length === 1) {
                touchStartX = event.touches[0].clientX;
            }
        }, { passive: true });

        stage.addEventListener('touchend', function (event) {
            if (touchStartX === null || !event.changedTouches || event.changedTouches.length === 0) return;
            const deltaX = event.changedTouches[0].clientX - touchStartX;
            touchStartX = null;
            if (Math.abs(deltaX) >= 45 && cards.length > 1) {
                showPhoto(deltaX < 0 ? index + 1 : index - 1);
            }
        }, { passive: true });
    }

    viewer.addEventListener('click', function (event) {
        const bounds = viewer.getBoundingClientRect();
        if (
            event.target === viewer &&
            (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom)
        ) {
            viewer.close();
        }
    });

    viewer.addEventListener('close', function () {
        document.body.style.overflow = previousOverflow;
        image.removeAttribute('src');
        if (opener) opener.focus({ preventScroll: true });
    });
})();
