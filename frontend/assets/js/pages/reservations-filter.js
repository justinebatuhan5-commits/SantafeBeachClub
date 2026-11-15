(function () {
    const searchInput = document.getElementById('reservationSearch');
    const statusSelect = document.querySelector('[data-auto-submit-status]');

    function filterTable() {
        const filter = (searchInput ? searchInput.value : '').toLowerCase();
        const rows = document.querySelectorAll('#resTable tbody tr');

        rows.forEach((row) => {
            const text = row.innerText.toLowerCase();
            row.style.display = text.includes(filter) ? '' : 'none';
        });
    }

    window.filterTable = filterTable;

    if (searchInput) {
        searchInput.addEventListener('input', filterTable);
        searchInput.addEventListener('keyup', filterTable);
    }

    if (statusSelect && statusSelect.form) {
        statusSelect.addEventListener('change', () => {
            statusSelect.form.submit();
        });
    }
})();
