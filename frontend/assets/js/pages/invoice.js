document.addEventListener('DOMContentLoaded', () => {
    const printBtn = document.querySelector('[data-action="print-invoice"]');
    if (printBtn) {
        printBtn.addEventListener('click', () => {
            window.print();
        });
    }

    if (document.body.dataset.autoPrint === '1') {
        setTimeout(() => {
            window.print();
        }, 400);
    }
});
