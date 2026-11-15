(function () {
    let currentCheckoutFormId = null;

    function confirmCheckout(id, name) {
        currentCheckoutFormId = 'checkout-form-' + id;
        const guestNameEl = document.getElementById('checkoutGuestName');
        const modal = document.getElementById('checkoutModal');
        if (guestNameEl) {
            guestNameEl.textContent = name;
        }
        if (modal) {
            modal.style.display = 'flex';
        }
    }

    function closeCheckoutModal() {
        const modal = document.getElementById('checkoutModal');
        if (modal) {
            modal.style.display = 'none';
        }
        currentCheckoutFormId = null;
    }

    function closeOverdueModal() {
        const modal = document.getElementById('overdueModal');
        if (modal) {
            modal.style.display = 'none';
        }
    }

    function filterCheckoutTable() {
        const input = document.getElementById('checkoutSearch');
        const q = (input ? input.value : '').toLowerCase();
        document.querySelectorAll('.reservations-table tbody tr').forEach((row) => {
            row.style.display = row.innerText.toLowerCase().includes(q) ? '' : 'none';
        });
    }

    window.confirmCheckout = confirmCheckout;
    window.closeCheckoutModal = closeCheckoutModal;
    window.closeOverdueModal = closeOverdueModal;
    window.filterCheckoutTable = filterCheckoutTable;

    document.addEventListener('click', (event) => {
        const checkoutBtn = event.target.closest('[data-checkout-id]');
        if (checkoutBtn) {
            confirmCheckout(checkoutBtn.dataset.checkoutId, checkoutBtn.dataset.checkoutName || '');
            return;
        }
        if (event.target.closest('[data-close-checkout-modal]')) {
            closeCheckoutModal();
            return;
        }
        if (event.target.closest('[data-close-overdue-modal]')) {
            closeOverdueModal();
        }
    });

    const confirmBtn = document.getElementById('confirmCheckoutBtn');
    if (confirmBtn) {
        confirmBtn.addEventListener('click', () => {
            if (currentCheckoutFormId) {
                const form = document.getElementById(currentCheckoutFormId);
                if (form) {
                    form.submit();
                }
            }
        });
    }

    const searchInput = document.getElementById('checkoutSearch');
    if (searchInput) {
        searchInput.addEventListener('input', filterCheckoutTable);
    }

    const overdueModal = document.getElementById('overdueModal');
    const guestList = document.getElementById('overdueGuestList');
    if (overdueModal && guestList) {
        let overdueGuestNames = [];
        try {
            overdueGuestNames = JSON.parse(overdueModal.dataset.overdueGuests || '[]');
        } catch (e) {
            overdueGuestNames = [];
        }

        if (Array.isArray(overdueGuestNames) && overdueGuestNames.length > 0) {
            overdueGuestNames.forEach((name) => {
                const item = document.createElement('li');
                item.textContent = name;
                guestList.appendChild(item);
            });
            overdueModal.style.display = 'flex';
        }
    }
})();
