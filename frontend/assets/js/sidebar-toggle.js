/** Desktop rail and accessible mobile navigation drawer. */
(function () {
    if (window.__sbcSidebarToggleInit) return;
    window.__sbcSidebarToggleInit = true;
    function init() {
        var sidebar = document.getElementById('appSidebar');
        if (!sidebar) return;
        var root = document.documentElement;
        root.classList.remove('sbc-collapsed', 'sbc-sidebar-hidden');
        root.classList.add('sbc-sidebar-dropdown', 'sbc-sidebar-docked');
        sidebar.classList.remove('collapsed');
        var main = document.querySelector('.admin-main, .main-content');
        if (main) main.classList.remove('main-collapsed');
        var trigger = document.getElementById('sidebarToggleBtn');
        var close = sidebar.querySelector('.workspace-collapse');
        var mobile = window.matchMedia('(max-width: 1024px)');
        var backdrop = document.createElement('div');
        backdrop.className = 'mobile-nav-backdrop';
        backdrop.hidden = true;
        document.body.appendChild(backdrop);
        var previousFocus = null;
        function setOpen(open, restoreFocus) {
            open = !mobile.matches || open;
            sidebar.classList.toggle('open', open);
            sidebar.inert = !open;
            sidebar.setAttribute('aria-hidden', String(!open));
            backdrop.hidden = !mobile.matches || !open;
            root.classList.toggle('sbc-mobile-nav-open', mobile.matches && open);
            if (main) main.inert = mobile.matches && open;
            if (trigger) {
                trigger.setAttribute('aria-expanded', String(open));
                trigger.setAttribute('aria-controls', 'appSidebar');
                trigger.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
            }
            if (mobile.matches && open) {
                previousFocus = document.activeElement;
                if (close) close.focus({ preventScroll: true });
            } else if (restoreFocus && previousFocus) {
                previousFocus.focus({ preventScroll: true });
            }
        }
        function syncLayout() {
            var hadSidebarFocus = sidebar.contains(document.activeElement);
            if (mobile.matches) {
                root.classList.remove('sbc-sidebar-compact');
                if (close) {
                    close.setAttribute('aria-label', 'Close navigation');
                    close.title = 'Close navigation';
                    close.setAttribute('aria-expanded', 'true');
                }
            } else {
                var compact = false;
                try { compact = localStorage.getItem('sbc_sidebar_compact') === 'true'; } catch (error) { /* Storage is optional. */ }
                root.classList.toggle('sbc-sidebar-compact', compact);
                if (close) {
                    close.setAttribute('aria-label', compact ? 'Expand sidebar' : 'Collapse sidebar');
                    close.title = compact ? 'Expand sidebar' : 'Collapse sidebar';
                    close.setAttribute('aria-expanded', String(!compact));
                }
            }
            setOpen(false, false);
            if (mobile.matches && hadSidebarFocus && trigger) trigger.focus({ preventScroll: true });
        }
        if (trigger) trigger.addEventListener('click', function () {
            if (mobile.matches) setOpen(!sidebar.classList.contains('open'), true);
        });
        backdrop.addEventListener('click', function () { setOpen(false, true); });
        document.addEventListener('sbc:close-navigation', function () { setOpen(false, true); });
        sidebar.addEventListener('click', function (event) {
            if (mobile.matches && event.target.closest('a')) setOpen(false, false);
        });
        document.addEventListener('keydown', function (event) {
            if (!mobile.matches || !sidebar.classList.contains('open')) return;
            if (event.key === 'Escape') { event.preventDefault(); setOpen(false, true); }
            if (event.key !== 'Tab') return;
            var focusable = Array.from(sidebar.querySelectorAll('a[href], button, input, select, [tabindex="0"]'))
                .filter(function (element) { return !element.disabled && element.getClientRects().length; });
            var first = focusable[0];
            var last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        });
        mobile.addEventListener('change', syncLayout);
        window.addEventListener('pageshow', syncLayout);
        syncLayout();
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
