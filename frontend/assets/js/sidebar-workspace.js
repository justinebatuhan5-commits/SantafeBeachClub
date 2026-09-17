/* A single, role-aware navigation column with a compact icon view. */
(function () {
    function init() {
        var sidebar = document.getElementById('appSidebar');
        if (!sidebar) return;
        sidebar.classList.add('workspace-sidebar');
        var root = document.documentElement;
        var body = sidebar.querySelector('.sidebar-scroll-body');
        var links = Array.from(body.querySelectorAll('.sidebar-link, .nav-item'));
        var brand = sidebar.querySelector('.sidebar-brand, .sidebar-logo');
        var logo = brand.querySelector('img').cloneNode(true);
        var theme = brand.querySelector('.dark-mode-btn');
        brand.replaceChildren(logo);
        logo.className = 'workspace-logo';
        var name = document.createElement('strong');
        name.className = 'workspace-brand-name';
        name.textContent = 'Santa Fe.';
        brand.appendChild(name);
        var toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'workspace-collapse';
        toggle.setAttribute('aria-controls', 'appSidebar');
        toggle.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m14 7-5 5 5 5"/></svg>';
        sidebar.appendChild(toggle);
        var compact = window.matchMedia('(max-width: 600px)').matches;
        try {
            var saved = localStorage.getItem('sbc_sidebar_compact');
            if (saved !== null) compact = saved === 'true';
        } catch (error) { /* Storage is optional. */ }
        function setCompact(value) {
            compact = value;
            root.classList.toggle('sbc-sidebar-compact', value);
            toggle.setAttribute('aria-expanded', String(!value));
            toggle.setAttribute('aria-label', value ? 'Expand sidebar' : 'Collapse sidebar');
            toggle.title = value ? 'Expand sidebar' : 'Collapse sidebar';
        }
        toggle.addEventListener('click', function () {
            setCompact(!compact);
            try { localStorage.setItem('sbc_sidebar_compact', String(compact)); } catch (error) { /* Storage is optional. */ }
        });
        setCompact(compact);
        var footer = sidebar.querySelector('.sidebar-bottom');
        footer.appendChild(theme);
        var groups = [
            { name: 'Front desk', routes: ['dashboard', 'reservations', 'checkin', 'checkout', 'javascript:'] },
            { name: 'Property', routes: ['guests', 'accommodations', 'calendar', 'promotions', 'gallery', 'room_types', 'inquiries'] },
            { name: 'Finance', routes: ['payments', 'refunds'] },
            { name: 'Insights & updates', routes: ['reports', 'reviews', 'notifications', 'logs'] },
            { name: 'Administration', routes: ['backups', 'staff'] }
        ];
        body.replaceChildren();
        groups.forEach(function (group) {
            var section = document.createElement('section');
            var heading = document.createElement('p');
            heading.className = 'sidebar-section-label';
            heading.textContent = group.name;
            var list = document.createElement('ul');
            list.className = 'sidebar-nav nav-links';
            group.routes.forEach(function (route) {
                links.filter(function (link) { return link.getAttribute('href').includes(route); }).forEach(function (link) {
                    list.appendChild(link.closest('li'));
                });
            });
            if (list.children.length) {
                section.append(heading, list);
                body.appendChild(section);
            }
        });
        links.forEach(function (link) {
            var caption = document.createElement('span');
            caption.className = 'workspace-link-label';
            Array.from(link.childNodes).filter(function (node) { return node.nodeType === 3; }).forEach(function (node) { caption.appendChild(node); });
            link.insertBefore(caption, link.querySelector('.sidebar-badge'));
            link.setAttribute('aria-label', link.title || caption.textContent.trim());
            if (link.classList.contains('active')) link.setAttribute('aria-current', 'page');
        });
        var empty = document.createElement('p');
        empty.className = 'workspace-empty';
        empty.textContent = 'No matching pages';
        empty.hidden = true;
        body.appendChild(empty);
        var search = sidebar.querySelector('input');
        search.placeholder = 'Find a page…';
        search.setAttribute('aria-label', 'Find a page');
        search.addEventListener('input', function () {
            var query = search.value.trim().toLowerCase();
            var count = 0;
            links.forEach(function (link) {
                var visible = link.textContent.toLowerCase().includes(query);
                link.closest('li').hidden = !visible;
                if (visible) count++;
            });
            body.querySelectorAll('section').forEach(function (section) { section.hidden = !section.querySelector('li:not([hidden])'); });
            empty.hidden = count > 0;
        });

        // Page navigation rebuilds this scroll container; restore it after grouping links.
        var scrollKey = 'sbc_sidebar_scroll:' + location.pathname.slice(0, location.pathname.lastIndexOf('/') + 1);
        try {
            var savedScroll = sessionStorage.getItem(scrollKey);
            var scrollTop = Number(savedScroll);
            if (savedScroll !== null && Number.isFinite(scrollTop) && scrollTop >= 0) {
                body.scrollTop = scrollTop;
            }
        } catch (error) { /* Storage is optional. */ }
        function saveScroll() {
            // Filtering temporarily shrinks the menu; keep the unfiltered position.
            if (search.value.trim()) return;
            try { sessionStorage.setItem(scrollKey, String(body.scrollTop)); } catch (error) { /* Storage is optional. */ }
        }
        body.addEventListener('scroll', saveScroll, { passive: true });
        sidebar.addEventListener('click', saveScroll, true);
        window.addEventListener('pagehide', saveScroll);
    }
    // The shared partial loads this script after the sidebar markup. Build it now
    // so the original menu is not displayed while the rest of the page loads.
    if (!document.getElementById('appSidebar') && document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
