<?php
/**
 * Performance helpers — include in every page's <head> or before </body>.
 *
 * Provides:
 *  - DNS prefetch / preconnect hints for external CDNs
 *  - The instant-page.js script tag
 *  - A helper to emit the script tag before </body>
 */

/**
 * Emit resource hints in <head> for faster CDN resolution.
 * Call this inside <head> on every page.
 */
function emit_performance_head(): void {
    // DNS prefetch for domains used across all pages
    $domains = [
        'https://cdn.tailwindcss.com',
        'https://fonts.googleapis.com',
        'https://fonts.gstatic.com',
        'https://cdnjs.cloudflare.com',
        'https://cdn.jsdelivr.net',
        'https://firestore.googleapis.com',
    ];
    foreach ($domains as $domain) {
        echo '<link rel="dns-prefetch" href="' . $domain . '">' . "\n    ";
    }
    echo '<link rel="preconnect" href="https://firestore.googleapis.com" crossorigin>' . "\n    ";
}

/**
 * Emit the performance scripts and common modals.
 * Call this before </body> on every page.
 */
function emit_performance_scripts(): void {
    // Include the common modals HTML (like Export Modal)
    $modalsPath = __DIR__ . '/modals_dashboard.php';
    if (file_exists($modalsPath)) {
        include_once $modalsPath;
    }

    $scripts = [
        'assets/js/instant-page.js',
        'assets/js/common-modals.js'
    ];

    foreach ($scripts as $script) {
        $fullPath = __DIR__ . '/../' . $script;
        if (file_exists($fullPath)) {
            $version = filemtime($fullPath);
            echo '<script src="' . $script . '?v=' . $version . '" defer></script>' . "\n";
        }
    }
}
