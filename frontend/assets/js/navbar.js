(function () {
    var navbar = document.querySelector('.site-navbar');
    if (!navbar) return;

    var toggle = navbar.querySelector('.navbar-toggle');
    var menu = navbar.querySelector('.nav-menu');
    if (!toggle || !menu) return;

    function closeMenu(returnFocus) {
        menu.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', 'Open navigation menu');
        if (returnFocus) toggle.focus();
    }

    toggle.addEventListener('click', function () {
        var opening = !menu.classList.contains('is-open');
        menu.classList.toggle('is-open', opening);
        toggle.setAttribute('aria-expanded', String(opening));
        toggle.setAttribute('aria-label', opening ? 'Close navigation menu' : 'Open navigation menu');
    });

    menu.addEventListener('click', function (event) {
        if (event.target.closest('a')) closeMenu(false);
    });

    document.addEventListener('click', function (event) {
        if (!navbar.contains(event.target)) closeMenu(false);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && menu.classList.contains('is-open')) closeMenu(true);
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth > 768) closeMenu(false);
    });
})();
