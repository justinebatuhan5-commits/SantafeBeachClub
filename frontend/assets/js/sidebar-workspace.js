/* Organize the existing role-authorized links into workspaces. */
(function () {
    function init() {
        var sidebar = document.getElementById('appSidebar');
        if (!sidebar) return;
        var body = sidebar.querySelector('.sidebar-scroll-body');
        var links = Array.from(body.querySelectorAll('.sidebar-link, .nav-item'));
        var groups = [
            { name: 'Front desk', icon: 'Reservations', routes: ['reservations', 'checkin', 'checkout', 'javascript:'] },
            { name: 'Operations', icon: 'Accommodations', routes: ['guests', 'accommodations', 'calendar', 'inquiries', 'promotions', 'gallery', 'room_types'] },
            { name: 'Finance', icon: 'Payments', routes: ['payments', 'refunds'] },
            { name: 'Insights', icon: 'Dashboard', routes: ['dashboard', 'reports', 'notifications', 'logs'] },
            { name: 'Settings', icon: 'Database Backups', routes: ['backups', 'staff'] }
        ];
        function groupFor(link) {
            return groups.find(function (group) {
                return group.routes.some(function (route) { return link.getAttribute('href').includes(route); });
            }) || groups[1];
        }
        var active = links.find(function (link) { return link.classList.contains('active'); });
        var selected = active ? groupFor(active) : groups[0];
        sidebar.classList.add('workspace-sidebar');
        var rail = document.createElement('nav');
        rail.className = 'workspace-rail';
        rail.setAttribute('aria-label', 'Workspaces');
        var logo = sidebar.querySelector('.sidebar-logo-toggle img').cloneNode(true);
        logo.className = 'workspace-logo';
        rail.appendChild(logo);
        var panel = document.createElement('div');
        panel.className = 'workspace-panel';
        while (sidebar.firstChild) panel.appendChild(sidebar.firstChild);
        sidebar.append(rail, panel);
        var brand = panel.querySelector('.sidebar-brand, .sidebar-logo');
        var title = document.createElement('div');
        title.className = 'workspace-heading';
        title.innerHTML = '<span>Santa Fe Beach Club</span><h2></h2>';
        brand.appendChild(title);
        var footer = panel.querySelector('.sidebar-bottom');
        footer.appendChild(brand.querySelector('.dark-mode-btn'));
        var property = document.createElement('div');
        property.className = 'workspace-property';
        property.innerHTML = '<span aria-hidden="true">≈</span><div>Santa Fe Beach Club<small>Property workspace</small></div>';
        footer.insertBefore(property, footer.firstChild);
        body.replaceChildren();
        var label = document.createElement('p');
        label.className = 'sidebar-section-label';
        body.appendChild(label);
        var list = document.createElement('ul');
        list.className = 'sidebar-nav nav-links';
        links.forEach(function (link) { list.appendChild(link.closest('li')); });
        body.appendChild(list);
        var quickLabel = document.createElement('p');
        quickLabel.className = 'sidebar-section-label workspace-quick-label';
        quickLabel.textContent = 'Quick access';
        var quickList = document.createElement('ul');
        quickList.className = 'sidebar-nav nav-links';
        body.append(quickLabel, quickList);
        var empty = document.createElement('p');
        empty.className = 'workspace-empty';
        empty.textContent = 'No matching pages';
        body.appendChild(empty);
        var search = sidebar.querySelector('input');
        search.placeholder = 'Find a page…';
        search.setAttribute('aria-label', 'Find a page');
        function render() {
            var query = search.value.trim().toLowerCase();
            title.querySelector('h2').textContent = query ? 'Search pages' : selected.name;
            label.textContent = query ? 'Search results' : 'Your workspace';
            var count = 0;
            links.forEach(function (link) {
                var quick = !query && selected === groups[0] && /guests|calendar/.test(link.getAttribute('href'));
                (quick ? quickList : list).appendChild(link.closest('li'));
                var visible = quick || (query ? link.textContent.toLowerCase().includes(query) : groupFor(link) === selected);
                link.closest('li').hidden = !visible;
                if (visible) count++;
                if (link.classList.contains('active')) link.setAttribute('aria-current', 'page');
            });
            empty.hidden = count > 0;
            quickLabel.hidden = !quickList.children.length;
            rail.querySelectorAll('button').forEach(function (button) {
                button.setAttribute('aria-pressed', String(!query && button.textContent === selected.name));
            });
        }
        groups.forEach(function (group) {
            if (group.name === 'Settings') return;
            if (!links.some(function (link) { return groupFor(link) === group; })) return;
            var button = document.createElement('button');
            button.type = 'button';
            var sample = links.find(function (link) { return link.title.includes(group.icon); }) || links.find(function (link) { return groupFor(link) === group; });
            button.appendChild(sample.querySelector('svg').cloneNode(true));
            var caption = document.createElement('span');
            caption.textContent = group.name;
            button.appendChild(caption);
            button.addEventListener('click', function () { selected = group; search.value = ''; render(); });
            rail.appendChild(button);
        });
        var settings = document.createElement('button');
        settings.type = 'button';
        settings.className = 'workspace-settings';
        settings.innerHTML = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="3"/><path d="m9 3-1 3-3 1-2 5 2 5 3 1 1 3h6l1-3 3-1 2-5-2-5-3-1-1-3z"/></svg><span>Settings</span>';
        var account = document.createElement('li');
        account.innerHTML = '<a href="settings" class="sidebar-link nav-item" title="Account Settings">Account Settings</a>';
        list.appendChild(account);
        groups[4].routes.push('settings');
        links.push(account.firstChild);
        settings.addEventListener('click', function () { selected = groups[4]; search.value = ''; render(); });
        rail.appendChild(settings);
        search.addEventListener('input', render);
        render();
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
