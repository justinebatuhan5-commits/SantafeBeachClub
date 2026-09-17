/**
 * sidebar-toggle.js — Responsive hamburger menu for the sidebar.
 * Works for both the Reception Desk (.sidebar / .main-content) and
 * Admin Panel (.admin-sidebar / .admin-main) layouts.
 *
 * - Desktop (> 1024px): sidebar is expanded by default; the hamburger
 *   collapses/expands it, sliding it fully off-canvas and reflowing
 *   the main content to fill the freed space.
 * - Tablet/Mobile (<= 1024px): sidebar is hidden by default and opens
 *   as an overlay above the content, with a dark backdrop.
 * - Clicking outside the open overlay sidebar, or pressing Escape,
 *   closes it.
 */
(function () {
    if (window.__sbcSidebarToggleInit) return;
    window.__sbcSidebarToggleInit = true;
    function init() {
        var sidebar = document.querySelector('.admin-sidebar') || document.querySelector('.sidebar');
        if (!sidebar) return;

        var STORAGE_KEY = 'sbc_sidebar_collapsed';

        function getMainWrap() {
            return document.querySelector('.admin-main') || document.querySelector('.main-content');
        }

        function isMobile() {
            return window.innerWidth <= 1024;
        }

        // Use the hamburger button already rendered in the page header
        var toggleBtn = document.querySelector('.sidebar-toggle-btn');
        var logoBtn = sidebar.querySelector('.sidebar-logo-toggle');
        var restoreBtn;
        if (logoBtn) {
            restoreBtn = document.createElement('button');
            restoreBtn.type = 'button';
            restoreBtn.className = 'sidebar-logo-restore';
            restoreBtn.title = 'Show sidebar';
            restoreBtn.setAttribute('aria-label', 'Show sidebar');
            restoreBtn.setAttribute('aria-controls', sidebar.id);
            restoreBtn.setAttribute('aria-expanded', 'false');
            restoreBtn.appendChild(logoBtn.querySelector('img').cloneNode(true));
            restoreBtn.hidden = true;
            document.body.appendChild(restoreBtn);
        }

        function setLogoHidden(hidden) {
            document.documentElement.classList.toggle('sbc-sidebar-hidden', hidden);
            sidebar.inert = hidden;
            if (restoreBtn) restoreBtn.hidden = !hidden;
            if (logoBtn) logoBtn.setAttribute('aria-expanded', String(!hidden));
        }

        // Overlay backdrop (mobile/tablet only)
        var overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        document.body.appendChild(overlay);

        function openSidebar() {
            setLogoHidden(false);
            if (isMobile()) {
                sidebar.classList.add('open');
                overlay.classList.add('active');
            } else {
                sidebar.classList.remove('collapsed');
                document.documentElement.classList.remove('sbc-collapsed');
                var mainWrap = getMainWrap();
                if (mainWrap) mainWrap.classList.remove('main-collapsed');
                try { localStorage.setItem(STORAGE_KEY, 'false'); } catch (e) {}
            }
        }

        function closeSidebar() {
            if (isMobile()) {
                sidebar.classList.remove('open');
                overlay.classList.remove('active');
            } else {
                sidebar.classList.add('collapsed');
                document.documentElement.classList.add('sbc-collapsed');
                var mainWrap = getMainWrap();
                if (mainWrap) mainWrap.classList.add('main-collapsed');
                try { localStorage.setItem(STORAGE_KEY, 'true'); } catch (e) {}
            }
        }

        function toggleSidebar() {
            if (document.documentElement.classList.contains('sbc-sidebar-hidden')) {
                openSidebar();
                return;
            }
            var isCollapsed = sidebar.classList.contains('collapsed') || document.documentElement.classList.contains('sbc-collapsed');
            var isOpenState = isMobile() ? sidebar.classList.contains('open') : !isCollapsed;
            if (isOpenState) {
                closeSidebar();
            } else {
                openSidebar();
            }
        }

        if (toggleBtn) {
            toggleBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                toggleSidebar();
            });
        }

        if (logoBtn) {
            logoBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                sidebar.classList.remove('open');
                overlay.classList.remove('active');
                setLogoHidden(true);
                restoreBtn.focus();
            });
            restoreBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                setLogoHidden(false);
                if (isMobile()) openSidebar();
                logoBtn.focus();
            });
        }

        // Click outside closes the overlay sidebar on tablet/mobile
        document.addEventListener('click', function (e) {
            if (isMobile() && sidebar.classList.contains('open')) {
                if (!sidebar.contains(e.target) && e.target !== toggleBtn && !(toggleBtn && toggleBtn.contains(e.target))) {
                    closeSidebar();
                }
            }
        });

        overlay.addEventListener('click', closeSidebar);

        // Escape key closes the overlay sidebar
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && isMobile() && sidebar.classList.contains('open')) {
                closeSidebar();
            }
        });

        // Reset mobile-only state when resizing back to desktop, and vice versa
        window.addEventListener('resize', function () {
            if (!isMobile()) {
                sidebar.classList.remove('open');
                overlay.classList.remove('active');
            }
        });

        // Restore the desktop collapsed preference
        try {
            if (!isMobile() && localStorage.getItem(STORAGE_KEY) === 'true') {
                sidebar.classList.add('collapsed');
                document.documentElement.classList.add('sbc-collapsed');
                var mainWrap = getMainWrap();
                if (mainWrap) mainWrap.classList.add('main-collapsed');
            }
        } catch (e) {}

        // Preserve sidebar scroll position across page reloads & navigation
        var SCROLL_KEY = 'sbc_sidebar_scroll_pos';
        sidebar.addEventListener('scroll', function () {
            try { sessionStorage.setItem(SCROLL_KEY, sidebar.scrollTop); } catch (e) {}
        }, { passive: true });

        try {
            var savedScroll = sessionStorage.getItem(SCROLL_KEY);
            if (savedScroll !== null) {
                sidebar.scrollTop = parseInt(savedScroll, 10);
            }
        } catch (e) {}
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
