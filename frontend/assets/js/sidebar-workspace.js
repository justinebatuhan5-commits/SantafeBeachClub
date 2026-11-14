/* Modern, role-aware workspace navigation with search shortcut and compact icon rail. */
(function () {
    function init() {
        var sidebar = document.getElementById('appSidebar');
        if (!sidebar) return;
        sidebar.classList.add('workspace-sidebar');

        var root = document.documentElement;
        var body = sidebar.querySelector('.sidebar-scroll-body');
        var links = Array.from(body.querySelectorAll('.sidebar-link, .nav-item'));
        var brand = sidebar.querySelector('.sidebar-brand, .sidebar-logo');
        var logoImg = brand.querySelector('img');
        var logo = logoImg ? logoImg.cloneNode(true) : document.createElement('div');
        var theme = brand.querySelector('.dark-mode-btn');

        var isAdmin = sidebar.classList.contains('admin-sidebar');
        brand.replaceChildren(logo);
        if (logoImg) {
            logo.className = 'workspace-logo';
        }

        var brandGroup = document.createElement('div');
        brandGroup.className = 'workspace-brand-group';
        var name = document.createElement('span');
        name.className = 'workspace-brand-name';
        name.textContent = 'Santa Fe';
        var sub = document.createElement('span');
        sub.className = 'workspace-brand-sub';
        sub.textContent = isAdmin ? 'Beach Club \u00B7 Admin' : 'Beach Club \u00B7 Desk';
        brandGroup.appendChild(name);
        brandGroup.appendChild(sub);
        brand.appendChild(brandGroup);

        var toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'workspace-collapse';
        toggle.setAttribute('aria-controls', 'appSidebar');
        toggle.innerHTML = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>';
        brand.appendChild(toggle);

        var compact = false;
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
            if (window.matchMedia('(max-width: 1024px)').matches) {
                document.dispatchEvent(new Event('sbc:close-navigation'));
                return;
            }
            setCompact(!root.classList.contains('sbc-sidebar-compact'));
            try { localStorage.setItem('sbc_sidebar_compact', String(compact)); } catch (error) { /* Storage is optional. */ }
        });

        setCompact(window.matchMedia('(max-width: 1024px)').matches ? false : compact);

        var footer = sidebar.querySelector('.sidebar-bottom');
        if (footer && theme) {
            footer.appendChild(theme);
        }

        // Complete route groups — includes automation, sessions, chatbot & catch-all
        var groups = [
            { name: 'Front Desk', routes: ['dashboard', 'reservations', 'checkin', 'checkout', 'javascript:'] },
            { name: 'Property & Guests', routes: ['guests', 'accommodations', 'calendar', 'promotions', 'gallery', 'room_types', 'inquiries', 'live_support', 'chatbot'] },
            { name: 'Finance', routes: ['discount_requests', 'payments', 'refunds'] },
            { name: 'Insights & Updates', routes: ['reports', 'reviews', 'notifications', 'logs'] },
            { name: 'System', routes: ['automation', 'backups', 'staff', 'sessions'] }
        ];

        var placedLinks = new Set();
        body.replaceChildren();
        groups.forEach(function (group) {
            var section = document.createElement('section');
            var heading = document.createElement('p');
            heading.className = 'sidebar-section-label';
            heading.textContent = group.name;
            var list = document.createElement('ul');
            list.className = 'sidebar-nav nav-links';

            group.routes.forEach(function (route) {
                links.filter(function (link) {
                    return !placedLinks.has(link) && (link.getAttribute('href') || '').includes(route);
                }).forEach(function (link) {
                    placedLinks.add(link);
                    list.appendChild(link.closest('li'));
                });
            });

            if (list.children.length) {
                section.append(heading, list);
                body.appendChild(section);
            }
        });

        // Fallback safety group so future links never get dropped silently
        var unplaced = links.filter(function (link) { return !placedLinks.has(link); });
        if (unplaced.length) {
            var miscSection = document.createElement('section');
            var miscHeading = document.createElement('p');
            miscHeading.className = 'sidebar-section-label';
            miscHeading.textContent = 'More';
            var miscList = document.createElement('ul');
            miscList.className = 'sidebar-nav nav-links';
            unplaced.forEach(function (link) { miscList.appendChild(link.closest('li')); });
            miscSection.append(miscHeading, miscList);
            body.appendChild(miscSection);
        }

        links.forEach(function (link) {
            var caption = document.createElement('span');
            caption.className = 'workspace-link-label';
            Array.from(link.childNodes).filter(function (node) { return node.nodeType === 3; }).forEach(function (node) {
                caption.appendChild(node);
            });
            link.insertBefore(caption, link.querySelector('.sidebar-badge'));
            link.setAttribute('aria-label', link.title || caption.textContent.trim());
            if (link.classList.contains('active')) {
                link.setAttribute('aria-current', 'page');
            }
            if ((link.getAttribute('href') || '').startsWith('javascript:')) {
                var qTag = document.createElement('span');
                qTag.className = 'workspace-quick-tag';
                qTag.textContent = 'Scan';
                link.appendChild(qTag);
            }
        });

        var empty = document.createElement('p');
        empty.className = 'workspace-empty';
        empty.textContent = 'No matching pages';
        empty.hidden = true;
        body.appendChild(empty);

        var searchWrap = sidebar.querySelector('.sidebar-search-inner');
        var search = sidebar.querySelector('input');
        if (searchWrap && search) {
            search.placeholder = 'Find a page...';
            search.setAttribute('aria-label', 'Find a page');

            var kbd = document.createElement('kbd');
            kbd.className = 'workspace-search-kbd';
            kbd.textContent = '/';
            searchWrap.appendChild(kbd);

            var clearBtn = document.createElement('button');
            clearBtn.type = 'button';
            clearBtn.className = 'workspace-search-clear';
            clearBtn.setAttribute('aria-label', 'Clear navigation search');
            clearBtn.innerHTML = '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
            searchWrap.appendChild(clearBtn);

            function filterNav() {
                var query = search.value.trim().toLowerCase();
                var count = 0;
                kbd.hidden = query.length > 0;
                clearBtn.classList.toggle('visible', query.length > 0);
                links.forEach(function (link) {
                    var visible = link.textContent.toLowerCase().includes(query);
                    link.closest('li').hidden = !visible;
                    if (visible) count++;
                });
                body.querySelectorAll('section').forEach(function (section) {
                    section.hidden = !section.querySelector('li:not([hidden])');
                });
                empty.hidden = count > 0;
            }

            search.addEventListener('input', filterNav);
            clearBtn.addEventListener('click', function () {
                search.value = '';
                filterNav();
                search.focus();
            });

            document.addEventListener('keydown', function (e) {
                var activeEl = document.activeElement;
                var typing = activeEl && (activeEl.tagName === 'INPUT' || activeEl.tagName === 'TEXTAREA' || activeEl.tagName === 'SELECT' || activeEl.isContentEditable);
                if (!typing && e.key === '/' && !root.classList.contains('sbc-sidebar-compact') && window.innerWidth > 1024) {
                    e.preventDefault();
                    search.focus();
                    search.select();
                } else if (activeEl === search && e.key === 'Escape') {
                    search.value = '';
                    filterNav();
                    search.blur();
                }
            });
        }

        // Keep active page visible in sidebar scroll body if near bottom, otherwise keep brand & search at top
        var activeLink = body.querySelector('.sidebar-link.active, .nav-item.active');
        function alignSidebarScroll() {
            if (activeLink && typeof activeLink.scrollIntoView === 'function') {
                var bodyRect = body.getBoundingClientRect();
                var linkRect = activeLink.getBoundingClientRect();
                if (linkRect.bottom > bodyRect.bottom - 12 || linkRect.top < bodyRect.top + 12) {
                    activeLink.scrollIntoView({ block: 'nearest' });
                    return;
                }
            }
            body.scrollTop = 0;
        }
        alignSidebarScroll();
        window.addEventListener('load', alignSidebarScroll);

        // Enable smooth collapse/expand transitions after initial paint completes
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                root.classList.add('sbc-sidebar-ready');
            });
        });
    }

    if (!document.getElementById('appSidebar') && document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
