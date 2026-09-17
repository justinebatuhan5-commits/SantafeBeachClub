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
        var property = document.createElement('div');
        property.className = 'workspace-property';
        property.innerHTML = '<span class="workspace-initial" aria-hidden="true">SF</span><div class="workspace-property-copy"><strong>Your property</strong><small>Property management workspace</small></div>';
        brand.after(property);
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
        var account = document.createElement('li');
        account.innerHTML = '<a href="settings" class="sidebar-link nav-item" title="Account Settings"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="3"/><path d="m9 3-1 3-3 1-2 5 2 5 3 1 1 3h6l1-3 3-1 2-5-2-5-3-1-1-3z"/></svg>Account Settings</a>';
        links.push(account.firstChild);
        if (location.pathname.replace(/\.php$/, '').endsWith('/settings')) account.firstChild.classList.add('active');
        var groups = [
            { name: 'Front desk', routes: ['dashboard', 'reservations', 'checkin', 'checkout', 'javascript:'] },
            { name: 'Property', routes: ['guests', 'accommodations', 'calendar', 'promotions', 'gallery', 'room_types', 'inquiries'] },
            { name: 'Finance', routes: ['payments', 'refunds'] },
            { name: 'Insights & updates', routes: ['reports', 'notifications', 'logs'] },
            { name: 'Administration', routes: ['backups', 'staff', 'settings'] }
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
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
