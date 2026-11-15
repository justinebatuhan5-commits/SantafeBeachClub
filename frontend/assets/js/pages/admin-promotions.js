(function () {
    const addModal = document.getElementById('addModal');

    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-open-promo-modal]')) {
            if (addModal) {
                addModal.classList.add('open');
            }
            return;
        }
        if (event.target.closest('[data-close-promo-modal]')) {
            if (addModal) {
                addModal.classList.remove('open');
            }
        }
    });

    document.querySelectorAll('form.js-confirm-only').forEach((form) => {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
        });
    });
})();
