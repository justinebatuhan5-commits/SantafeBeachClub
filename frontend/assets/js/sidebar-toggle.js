/** Keep shared navigation visible on every page. */
(function () {
    if (window.__sbcSidebarToggleInit) return;
    window.__sbcSidebarToggleInit = true;
    function init() {
        var sidebar = document.getElementById('appSidebar');
        if (!sidebar) return;
        document.documentElement.classList.remove('sbc-collapsed', 'sbc-sidebar-hidden');
        document.documentElement.classList.add('sbc-sidebar-dropdown', 'sbc-sidebar-docked');
        sidebar.classList.remove('collapsed');
        sidebar.classList.add('open');
        sidebar.inert = false;
        var main = document.querySelector('.admin-main, .main-content');
        if (main) main.classList.remove('main-collapsed');
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
    window.addEventListener('pageshow', init);
})();
