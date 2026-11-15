(function () {
    document.addEventListener('submit', function (e) {
        if (e.target.matches('form.js-confirm-only')) {
            e.preventDefault();
        }
    });
})();
