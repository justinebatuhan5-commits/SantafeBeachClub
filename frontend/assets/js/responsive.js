/* Keep wide data tables scrollable without shrinking text or hiding columns. */
(function () {
    function enhanceTables(scope) {
        scope.querySelectorAll('table').forEach(function (table) {
            if (table.closest('.mobile-table-scroll, .table-responsive, .table-wrapper, .table-container, .backup-scroll')) return;
            var wrapper = document.createElement('div');
            wrapper.className = 'mobile-table-scroll';
            table.before(wrapper);
            wrapper.appendChild(table);
            wrapper.setAttribute('role', 'region');
            wrapper.setAttribute('aria-label', 'Scrollable table');
            function update() {
                wrapper.tabIndex = wrapper.scrollWidth > wrapper.clientWidth + 1 ? 0 : -1;
            }
            if (window.ResizeObserver) {
                var observer = new ResizeObserver(update);
                observer.observe(wrapper);
                observer.observe(table);
            }
            update();
        });
    }
    enhanceTables(document);
    // Refund details and reports can insert tables after the initial render.
    new MutationObserver(function (records) {
        records.forEach(function (record) {
            record.addedNodes.forEach(function (node) {
                if (node.nodeType === 1 && node.className !== 'mobile-table-scroll') {
                    enhanceTables(node.parentElement || node);
                }
            });
        });
    }).observe(document.body, { childList: true, subtree: true });
})();
