<?php
// views/interactive_map.php

// Ensure we have the necessary variables from dashboard.php
$userCategories = $userCategories ?? [];
$assignedBarangay = $userProfile['assignedBarangay'] ?? '';

// Pass configuration to JavaScript
echo '<script>';
echo 'window.dashboardConfig = ' . json_encode([
    'isAdmin' => $isAdmin,
    'userCategories' => $userCategories,
    'userBarangay' => $assignedBarangay
]) . ';';
echo '</script>';
?>

<div class="space-y-6 animate-fade-in-up">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-slate-800 tracking-tight">Interactive Map</h2>
            <p class="text-sm text-slate-500 mt-1">Real-time visualization of emergency reports</p>
        </div>
        <div class="flex items-center gap-2">
            <div class="px-3 py-1 bg-emerald-100 text-emerald-700 rounded-full text-xs font-medium flex items-center gap-2">
                <span class="relative flex h-2 w-2">
                  <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                  <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                </span>
                Live Updates
            </div>
        </div>
    </div>

    <?php include 'includes/map_view.php'; ?>
</div>

<!-- Load Leaflet CSS and JS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script src="assets/js/map-dashboard.js"></script>
<script>
    // Initialize map when view is loaded
    if (typeof loadMapData === 'function') {
        loadMapData();
    }
</script>