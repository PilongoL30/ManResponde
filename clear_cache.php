<?php
session_start();

// Clear all cached data
if (isset($_SESSION['__cache'])) {
    unset($_SESSION['__cache']);
    echo "Session cache cleared successfully!\n";
} else {
    echo "No session cache found to clear.\n";
}

// Clear admin stats cache specifically
if (isset($_SESSION['__cache']['admin_stats'])) {
    unset($_SESSION['__cache']['admin_stats']);
    echo "Admin stats cache cleared!\n";
}

// Clear recent feed cache
if (isset($_SESSION['__cache']['recent_feed'])) {
    unset($_SESSION['__cache']['recent_feed']);
    echo "Recent feed cache cleared!\n";
}

echo "Cache clearing complete. Please refresh your browser.\n";
?>
