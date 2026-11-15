(function () {
    let lbImages = [];
    let lbIndex = 0;

    function validateFileInput(btn) {
        const form = btn.closest('form');
        const input = form?.querySelector('.rp-file-input');
        if (!input || !input.files || input.files.length === 0) {
            const zone = form?.querySelector('.rp-dropzone');
            if (zone) {
                zone.style.borderColor = '#EF4444';
                zone.style.background = '#FEF2F2';
                setTimeout(function () {
                    zone.style.borderColor = '';
                    zone.style.background = '';
                }, 2500);
            }
            SiteDialog.alert('Please select or drop a photo first.');
            return false;
        }
        return true;
    }

    function renderLightbox() {
        const img = lbImages[lbIndex];
        if (!img) return;
        document.getElementById('lb-img').src = img.src;
        const total = lbImages.length;
        document.getElementById('lb-caption').textContent = (lbIndex + 1) + ' / ' + total + (img.caption ? '  •  ' + img.caption : '');
        document.getElementById('lb-prev').style.display = total > 1 ? 'flex' : 'none';
        document.getElementById('lb-next').style.display = total > 1 ? 'flex' : 'none';
    }

    function lbKeyHandler(e) {
        if (e.key === 'ArrowRight') lightboxNav(1);
        if (e.key === 'ArrowLeft') lightboxNav(-1);
        if (e.key === 'Escape') closeAdminLightbox();
    }

    function openAdminLightbox(imgEl) {
        const card = imgEl.closest('.rp-card');
        const allImgs = card ? Array.from(card.querySelectorAll('.rp-gallery-thumbs img, .rp-primary-img')) : [];
        lbImages = allImgs.map(i => ({ src: i.src, caption: i.alt || '' }));
        if (!lbImages.length) {
            lbImages = [{ src: imgEl.src, caption: imgEl.alt || '' }];
        }
        lbIndex = lbImages.findIndex(i => i.src === imgEl.src);
        if (lbIndex < 0) lbIndex = 0;
        renderLightbox();
        const lb = document.getElementById('admin-lightbox');
        lb.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        document.addEventListener('keydown', lbKeyHandler);
    }

    function closeAdminLightbox() {
        const lb = document.getElementById('admin-lightbox');
        if (lb) lb.style.display = 'none';
        document.body.style.overflow = '';
        document.removeEventListener('keydown', lbKeyHandler);
    }

    function lightboxNav(dir) {
        if (!lbImages.length) return;
        lbIndex = (lbIndex + dir + lbImages.length) % lbImages.length;
        renderLightbox();
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.rp-dropzone').forEach(zone => {
            const form = zone.closest('form');
            const input = form?.querySelector('.rp-file-input');
            const textEl = zone.querySelector('.rp-dropzone-text');
            if (!input) return;

            ['dragenter', 'dragover'].forEach(eventName => {
                zone.addEventListener(eventName, function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    zone.classList.add('drag-over');
                });
            });

            ['dragleave', 'drop'].forEach(eventName => {
                zone.addEventListener(eventName, function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    zone.classList.remove('drag-over');
                });
            });

            zone.addEventListener('click', function () {
                input.click();
            });

            zone.addEventListener('drop', function (e) {
                const files = e.dataTransfer?.files;
                if (files && files.length > 0) {
                    input.files = files;
                    if (textEl) {
                        textEl.textContent = files.length === 1
                            ? 'Selected: ' + files[0].name
                            : 'Selected ' + files.length + ' photos ready to upload';
                    }
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });

            input.addEventListener('change', function () {
                if (this.files && this.files.length > 0 && textEl) {
                    textEl.textContent = this.files.length === 1
                        ? 'Selected: ' + this.files[0].name
                        : 'Selected ' + this.files.length + ' photos ready to upload';
                }
            });
        });

        const lb = document.getElementById('admin-lightbox');
        if (lb) {
            lb.addEventListener('click', function (e) {
                if (e.target === lb) closeAdminLightbox();
            });
        }
    });

    document.addEventListener('click', function (e) {
        const thumbImg = e.target.closest('.rp-gallery-thumbs img');
        if (thumbImg) {
            openAdminLightbox(thumbImg);
            return;
        }

        const uploadBtn = e.target.closest('.js-validate-upload');
        if (uploadBtn) {
            if (!validateFileInput(uploadBtn)) {
                e.preventDefault();
            }
            return;
        }

        const actionEl = e.target.closest('[data-action]');
        if (!actionEl) return;

        const action = actionEl.getAttribute('data-action');
        if (action === 'lightbox-close') {
            closeAdminLightbox();
        } else if (action === 'lightbox-prev') {
            lightboxNav(-1);
        } else if (action === 'lightbox-next') {
            lightboxNav(1);
        }
    });

    document.addEventListener('submit', function (e) {
        if (e.target.matches('form.js-confirm-only')) {
            e.preventDefault();
        }
    });

    window.validateFileInput = validateFileInput;
    window.openAdminLightbox = openAdminLightbox;
    window.closeAdminLightbox = closeAdminLightbox;
    window.lightboxNav = lightboxNav;
})();
