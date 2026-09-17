/** Shared dropdown sidebar: overlays the page below the fixed header logo. */
(function () {
    if (window.__sbcSidebarToggleInit) return;
    window.__sbcSidebarToggleInit = true;

    function init() {
        var sidebar = document.querySelector('.admin-sidebar, .sidebar');
        if (!sidebar) return;
        var logo = sidebar.querySelector('.sidebar-logo-toggle img');
        if (!logo) return;
        var root = document.documentElement;
        var header = document.querySelector('.page-header');
        var hamburger = document.querySelector('.sidebar-toggle-btn');
        var main = document.querySelector('.admin-main, .main-content');
        root.classList.remove('sbc-collapsed', 'sbc-sidebar-hidden');
        root.classList.add('sbc-sidebar-dropdown');
        sidebar.classList.remove('collapsed', 'open');
        if (main) main.classList.remove('main-collapsed');

        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'sidebar-logo-restore';
        button.setAttribute('aria-controls', sidebar.id);
        button.appendChild(logo.cloneNode(true));
        document.body.appendChild(button);

        var overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        document.body.appendChild(overlay);
        var opened = false;

        function positionPanel() {
            var height = header ? header.getBoundingClientRect().height : 82;
            root.style.setProperty('--sidebar-popup-top', height + 'px');
            button.style.top = Math.max(8, (height - 42) / 2) + 'px';
        }

        function setOpen(value) {
            opened = value;
            sidebar.classList.toggle('open', value);
            sidebar.inert = !value;
            overlay.classList.toggle('active', value);
            [button, hamburger].forEach(function (control) {
                if (!control) return;
                control.setAttribute('aria-expanded', String(value));
                control.setAttribute('aria-controls', sidebar.id);
                control.setAttribute('aria-label', value ? 'Hide sidebar' : 'Show sidebar');
                control.title = value ? 'Hide sidebar' : 'Show sidebar';
            });
        }

        [button, hamburger].forEach(function (control) {
            if (control) control.addEventListener('click', function () { setOpen(!opened); });
        });
        overlay.addEventListener('click', function () { setOpen(false); });
        document.addEventListener('click', function (event) {
            if (opened && !sidebar.contains(event.target) && !button.contains(event.target) &&
                !(hamburger && hamburger.contains(event.target))) setOpen(false);
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && opened) {
                setOpen(false);
                button.focus();
            }
        });
        window.addEventListener('resize', positionPanel);
        if (header && window.ResizeObserver) new ResizeObserver(positionPanel).observe(header);
        setOpen(false);
        positionPanel();

        var scrollBody = sidebar.querySelector('.sidebar-scroll-body') || sidebar;
        var scrollKey = 'sbc_sidebar_scroll_pos';
        scrollBody.addEventListener('scroll', function () {
            try { sessionStorage.setItem(scrollKey, scrollBody.scrollTop); } catch (e) {}
        }, { passive: true });
        try { scrollBody.scrollTop = parseInt(sessionStorage.getItem(scrollKey), 10) || 0; } catch (e) {}
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
