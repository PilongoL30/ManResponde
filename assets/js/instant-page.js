/**
 * Instant Page Prefetcher
 * Prefetches pages when user hovers over navigation links.
 * This makes page transitions feel nearly instant (100-300ms instead of 2-5s).
 *
 * How it works:
 * 1. When user hovers over a link, we start fetching the page in the background
 * 2. By the time they click, the page is already in the browser cache
 * 3. Navigation feels instant
 */
(function() {
    'use strict';

    const prefetchedUrls = new Set();
    let prefetchTimer = null;

    // Only prefetch same-origin links
    function isInternalUrl(url) {
        try {
            const linkUrl = new URL(url, window.location.origin);
            return linkUrl.origin === window.location.origin;
        } catch {
            return false;
        }
    }

    // Should we prefetch this link?
    function shouldPrefetch(anchor) {
        if (!anchor || !anchor.href) return false;
        if (anchor.hasAttribute('download')) return false;
        if (anchor.getAttribute('target') === '_blank') return false;
        if (anchor.href === window.location.href) return false;
        if (anchor.href.includes('#')) return false;
        if (anchor.href.includes('logout')) return false;
        if (anchor.href.startsWith('javascript:')) return false;
        if (!isInternalUrl(anchor.href)) return false;
        if (prefetchedUrls.has(anchor.href)) return false;
        return true;
    }

    // Prefetch using link element (browser-native, respects cache)
    function prefetchUrl(url) {
        if (prefetchedUrls.has(url)) return;
        prefetchedUrls.add(url);

        // Method 1: Use <link rel="prefetch"> (best for full pages)
        const link = document.createElement('link');
        link.rel = 'prefetch';
        link.href = url;
        link.as = 'document';
        document.head.appendChild(link);

        console.log('[InstantPage] Prefetching:', url);
    }

    // Listen for hover on all internal links
    document.addEventListener('pointerenter', function(e) {
        const anchor = e.target.closest('a');
        if (!shouldPrefetch(anchor)) return;

        // Small delay to avoid prefetching on accidental hover-throughs
        clearTimeout(prefetchTimer);
        prefetchTimer = setTimeout(() => {
            prefetchUrl(anchor.href);
        }, 65);
    }, { passive: true, capture: true });

    // Also prefetch on touchstart (mobile)
    document.addEventListener('touchstart', function(e) {
        const anchor = e.target.closest('a');
        if (!shouldPrefetch(anchor)) return;
        prefetchUrl(anchor.href);
    }, { passive: true, capture: true });

    // Prefetch visible sidebar links immediately after page load
    // (these are the most likely navigation targets)
    function prefetchSidebarLinks() {
        const sidebarLinks = document.querySelectorAll('aside a[href], nav a[href]');
        sidebarLinks.forEach(anchor => {
            if (shouldPrefetch(anchor)) {
                prefetchUrl(anchor.href);
            }
        });
    }

    // Wait for page to be fully idle before prefetching sidebar
    if ('requestIdleCallback' in window) {
        requestIdleCallback(prefetchSidebarLinks, { timeout: 3000 });
    } else {
        setTimeout(prefetchSidebarLinks, 2000);
    }
})();
