/**
 * Santa Fe Beach Club - Warm Resort Beige Page Transition Handler
 */
document.addEventListener('DOMContentLoaded', function () {
    // Intercept internal page navigation links
    document.addEventListener('click', function (e) {
        const link = e.target.closest('a');
        if (!link) return;

        const href = link.getAttribute('href');
        if (!href) return;

        // Skip anchors, hashes, JS, mailto, tel, external sites, target="_blank", downloads
        if (
            href.startsWith('#') ||
            href.startsWith('javascript:') ||
            href.startsWith('mailto:') ||
            href.startsWith('tel:') ||
            link.getAttribute('target') === '_blank' ||
            link.hasAttribute('download') ||
            e.metaKey || e.ctrlKey || e.shiftKey || e.altKey
        ) {
            return;
        }

        // Check if origin matches
        const destination = new URL(link.href, window.location.href);
        if (destination.origin !== window.location.origin) {
            return;
        }

        // Don't re-animate if staying on same page/anchor
        if (destination.pathname === window.location.pathname && destination.search === window.location.search) {
            return;
        }

        // Smooth warm fade out
        e.preventDefault();
        document.body.classList.add('page-leaving');

        setTimeout(function () {
            window.location.href = link.href;
        }, 180);
    });

    // Handle browser back/forward cache
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            document.body.classList.remove('page-leaving');
        }
    });

    // Safety: if page-leaving class is still present after a timeout (e.g. navigation
    // was cancelled or delayed), remove it so the page doesn't stay dimmed.
    window.addEventListener('load', function () {
        if (document.body.classList.contains('page-leaving')) {
            document.body.classList.remove('page-leaving');
        }
    });
});
