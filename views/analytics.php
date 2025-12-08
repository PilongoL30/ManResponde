<?php
// views/analytics.php

// Ensure we have the necessary variables from dashboard.php
// $categories, $isAdmin, $isTanod, $userCategories, etc.

// --- ANALYTICS DATA FETCHING ---
// We'll fetch the data here specifically for the analytics view
// This avoids loading heavy analytics data on the main dashboard

// Helper to get stats for a collection
function get_collection_stats_analytics($collection) {
    $stats = ['pending' => 0, 'approved' => 0, 'declined' => 0, 'responded' => 0, 'total' => 0];
    
    // Use the optimized count function if available, otherwise fallback
    if (function_exists('firestore_count_documents')) {
        // This is a hypothetical optimized function. 
        // In reality, we might need to run a query or use the existing list_latest_reports logic but just count.
        // For now, let's reuse the logic from dashboard.php but maybe we can optimize it later.
        // Actually, dashboard.php already calculates these stats. 
        // Let's assume the main dashboard logic for fetching stats is moved here or we re-fetch.
        
        // To keep it simple and consistent, we'll use the same method as the dashboard.
        // However, since we are in a separate view, we might need to re-run the queries 
        // if they weren't run in the main dashboard.php before including this view.
        
        // Let's check if $cards data is available (from dashboard.php)
        // If dashboard.php logic runs BEFORE including the view, we might have the data.
        // But typically, views are included at the end.
        
        // Let's assume we need to fetch the data if it's not already there.
        // But wait, dashboard.php fetches data for the 'dashboard' view. 
        // If view is 'analytics', dashboard.php might skip fetching the main dashboard data.
        
        // Let's implement a lightweight fetch here.
        $items = list_latest_reports($collection, 100); // Fetch last 100 for stats
        foreach ($items as $item) {
            $status = strtolower($item['status'] ?? 'pending');
            if ($status === 'approved') $stats['approved']++;
            elseif ($status === 'declined') $stats['declined']++;
            elseif ($status === 'responded') $stats['responded']++;
            else $stats['pending']++; // Default to pending
            $stats['total']++;
        }
    } else {
        // Fallback
        $items = list_latest_reports($collection, 50);
        foreach ($items as $item) {
            $status = strtolower($item['status'] ?? 'pending');
            if ($status === 'approved') $stats['approved']++;
            elseif ($status === 'declined') $stats['declined']++;
            elseif ($status === 'responded') $stats['responded']++;
            else $stats['pending']++;
            $stats['total']++;
        }
    }
    return $stats;
}

// Calculate stats for all categories
$analyticsData = [];
$grandTotal = 0;
$grandPending = 0;
$grandApproved = 0;
$grandDeclined = 0;
$grandResponded = 0;

foreach ($categories as $slug => $meta) {
    // Filter for staff: only show assigned categories
    if (!$isAdmin && !in_array($slug, $userCategories)) {
        continue;
    }
    
    $stats = get_collection_stats_analytics($meta['collection']);
    $analyticsData[$slug] = $stats;
    
    $grandTotal += $stats['total'];
    $grandPending += $stats['pending'];
    $grandApproved += $stats['approved'];
    $grandDeclined += $stats['declined'];
    $grandResponded += $stats['responded'];
}

?>

<div class="space-y-6 animate-fade-in-up">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-slate-800 tracking-tight">ManResponde Command Center</h2>
            <p class="text-sm text-slate-500 mt-1">Realtime snapshot of total reports and decisions.</p>
        </div>
        <div class="flex items-center gap-2">
            <button onclick="location.reload()" class="p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-all" title="Refresh Data">
                <i class="fas fa-sync-alt"></i>
            </button>
        </div>
    </div>

    <!-- Overview Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <!-- Total Reports -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                    <i class="fas fa-folder-open text-xl"></i>
                </div>
                <span class="text-xs font-bold px-2 py-1 rounded-lg bg-blue-50 text-blue-600">Total</span>
            </div>
            <h3 class="text-3xl font-bold text-slate-800 mb-1" id="analytics-total"><?php echo $grandTotal; ?></h3>
            <p class="text-sm text-slate-500">All Reports</p>
        </div>

        <!-- Pending -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                    <i class="fas fa-clock text-xl"></i>
                </div>
                <span class="text-xs font-bold px-2 py-1 rounded-lg bg-amber-50 text-amber-600">Pending</span>
            </div>
            <h3 class="text-3xl font-bold text-slate-800 mb-1" id="analytics-pending"><?php echo $grandPending; ?></h3>
            <p class="text-sm text-slate-500">Awaiting Action</p>
        </div>

        <!-- Approved -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <i class="fas fa-check-circle text-xl"></i>
                </div>
                <span class="text-xs font-bold px-2 py-1 rounded-lg bg-emerald-50 text-emerald-600">Approved</span>
            </div>
            <h3 class="text-3xl font-bold text-slate-800 mb-1" id="analytics-approved"><?php echo $grandApproved; ?></h3>
            <p class="text-sm text-slate-500">Resolved/Active</p>
        </div>

        <!-- Responded -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 rounded-xl bg-cyan-50 text-cyan-600 flex items-center justify-center">
                    <i class="fas fa-truck-medical text-xl"></i>
                </div>
                <span class="text-xs font-bold px-2 py-1 rounded-lg bg-cyan-50 text-cyan-600">Responded</span>
            </div>
            <h3 class="text-3xl font-bold text-slate-800 mb-1" id="analytics-responded"><?php echo $grandResponded; ?></h3>
            <p class="text-sm text-slate-500">Action Taken</p>
        </div>

        <!-- Declined -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 rounded-xl bg-red-50 text-red-600 flex items-center justify-center">
                    <i class="fas fa-times-circle text-xl"></i>
                </div>
                <span class="text-xs font-bold px-2 py-1 rounded-lg bg-red-50 text-red-600">Declined</span>
            </div>
            <h3 class="text-3xl font-bold text-slate-800 mb-1" id="analytics-declined"><?php echo $grandDeclined; ?></h3>
            <p class="text-sm text-slate-500">Rejected Reports</p>
        </div>
    </div>

    <!-- Detailed Statistics -->
    <div id="analytics-detailed-stats">
        <h3 class="text-lg font-bold text-slate-800 mb-4">Report Statistics</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($analyticsData as $slug => $stats): 
                $meta = $categories[$slug];
                $total = $stats['total'];
                $pendingPct = $total > 0 ? round(($stats['pending'] / $total) * 100) : 0;
                $approvedPct = $total > 0 ? round(($stats['approved'] / $total) * 100) : 0;
                $respondedPct = $total > 0 ? round(($stats['responded'] / $total) * 100) : 0;
                $declinedPct = $total > 0 ? round(($stats['declined'] / $total) * 100) : 0;
            ?>
            <div id="stat-card-<?php echo $slug; ?>" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 hover:shadow-md transition-shadow">
                <div class="flex items-center gap-4 mb-6">
                    <div class="w-12 h-12 rounded-xl bg-<?php echo $meta['color']; ?>-100 text-<?php echo $meta['color']; ?>-600 flex items-center justify-center">
                        <?php echo svg_icon($meta['icon'], 'w-6 h-6'); ?>
                    </div>
                    <div>
                        <h4 class="font-bold text-slate-800"><?php echo $meta['label']; ?></h4>
                        <p class="text-xs text-slate-500">Overview</p>
                    </div>
                    <div class="ml-auto text-right">
                        <span class="block text-2xl font-bold text-slate-800" data-status="total"><?php echo $total; ?></span>
                        <span class="text-xs text-slate-500">Total</span>
                    </div>
                </div>

                <!-- Mini Stats Grid -->
                <div class="grid grid-cols-4 gap-2 mb-4 text-center">
                    <div class="bg-amber-50 rounded-lg p-2">
                        <span class="block text-lg font-bold text-amber-600" data-status="pending"><?php echo $stats['pending']; ?></span>
                        <span class="text-[10px] font-bold text-amber-600/70 uppercase">Pend</span>
                    </div>
                    <div class="bg-emerald-50 rounded-lg p-2">
                        <span class="block text-lg font-bold text-emerald-600" data-status="approved"><?php echo $stats['approved']; ?></span>
                        <span class="text-[10px] font-bold text-emerald-600/70 uppercase">Appr</span>
                    </div>
                    <div class="bg-cyan-50 rounded-lg p-2">
                        <span class="block text-lg font-bold text-cyan-600" data-status="responded"><?php echo $stats['responded']; ?></span>
                        <span class="text-[10px] font-bold text-cyan-600/70 uppercase">Resp</span>
                    </div>
                    <div class="bg-red-50 rounded-lg p-2">
                        <span class="block text-lg font-bold text-red-600" data-status="declined"><?php echo $stats['declined']; ?></span>
                        <span class="text-[10px] font-bold text-red-600/70 uppercase">Decl</span>
                    </div>
                </div>

                <!-- Progress Bars -->
                <div class="space-y-2">
                    <div class="flex items-center gap-2 text-xs">
                        <span class="w-16 font-medium text-slate-600">Pending</span>
                        <div class="flex-1 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                            <div class="h-full bg-amber-500 rounded-full progress-seg pending" style="width: <?php echo $pendingPct; ?>%"></div>
                        </div>
                        <span class="w-8 text-right text-slate-500 text-pending-pct"><?php echo $pendingPct; ?>%</span>
                    </div>
                    <div class="flex items-center gap-2 text-xs">
                        <span class="w-16 font-medium text-slate-600">Approved</span>
                        <div class="flex-1 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                            <div class="h-full bg-emerald-500 rounded-full progress-seg approved" style="width: <?php echo $approvedPct; ?>%"></div>
                        </div>
                        <span class="w-8 text-right text-slate-500 text-approved-pct"><?php echo $approvedPct; ?>%</span>
                    </div>
                    <div class="flex items-center gap-2 text-xs">
                        <span class="w-16 font-medium text-slate-600">Responded</span>
                        <div class="flex-1 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                            <div class="h-full bg-cyan-500 rounded-full progress-seg responded" style="width: <?php echo $respondedPct; ?>%"></div>
                        </div>
                        <span class="w-8 text-right text-slate-500 text-responded-pct"><?php echo $respondedPct; ?>%</span>
                    </div>
                    <div class="flex items-center gap-2 text-xs">
                        <span class="w-16 font-medium text-slate-600">Declined</span>
                        <div class="flex-1 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                            <div class="h-full bg-red-500 rounded-full progress-seg declined" style="width: <?php echo $declinedPct; ?>%"></div>
                        </div>
                        <span class="w-8 text-right text-slate-500 text-declined-pct"><?php echo $declinedPct; ?>%</span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Realtime updates for Analytics
    const updateAnalytics = async () => {
        try {
            const formData = new FormData();
            formData.append('api_action', 'load_admin_stats');
            formData.append('force_refresh', 'true');
            
            const response = await fetch('dashboard.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                const stats = result.data;
                let grandTotal = 0;
                let grandPending = 0;
                let grandApproved = 0;
                let grandResponded = 0;
                let grandDeclined = 0;
                
                // Update detailed cards
                Object.entries(stats).forEach(([slug, stat]) => {
                    const card = document.getElementById(`stat-card-${slug}`);
                    if (card) {
                        const total = parseInt(stat.total) || 0;
                        const pending = parseInt(stat.pending) || 0;
                        const approved = parseInt(stat.approved) || 0;
                        const responded = parseInt(stat.responded) || 0;
                        const declined = parseInt(stat.declined) || 0;
                        
                        grandTotal += total;
                        grandPending += pending;
                        grandApproved += approved;
                        grandResponded += responded;
                        grandDeclined += declined;
                        
                        // Update numbers
                        const totalEl = card.querySelector('[data-status="total"]');
                        const pendingEl = card.querySelector('[data-status="pending"]');
                        const approvedEl = card.querySelector('[data-status="approved"]');
                        const respondedEl = card.querySelector('[data-status="responded"]');
                        const declinedEl = card.querySelector('[data-status="declined"]');
                        
                        if (totalEl) totalEl.textContent = total;
                        if (pendingEl) pendingEl.textContent = pending;
                        if (approvedEl) approvedEl.textContent = approved;
                        if (respondedEl) respondedEl.textContent = responded;
                        if (declinedEl) declinedEl.textContent = declined;
                        
                        // Update percentages
                        const pendingPct = total > 0 ? Math.round((pending / total) * 100) : 0;
                        const approvedPct = total > 0 ? Math.round((approved / total) * 100) : 0;
                        const respondedPct = total > 0 ? Math.round((responded / total) * 100) : 0;
                        const declinedPct = total > 0 ? Math.round((declined / total) * 100) : 0;
                        
                        const pendingBar = card.querySelector('.progress-seg.pending');
                        const approvedBar = card.querySelector('.progress-seg.approved');
                        const respondedBar = card.querySelector('.progress-seg.responded');
                        const declinedBar = card.querySelector('.progress-seg.declined');
                        
                        if (pendingBar) pendingBar.style.width = `${pendingPct}%`;
                        if (approvedBar) approvedBar.style.width = `${approvedPct}%`;
                        if (respondedBar) respondedBar.style.width = `${respondedPct}%`;
                        if (declinedBar) declinedBar.style.width = `${declinedPct}%`;
                        
                        const pendingText = card.querySelector('.text-pending-pct');
                        const approvedText = card.querySelector('.text-approved-pct');
                        const respondedText = card.querySelector('.text-responded-pct');
                        const declinedText = card.querySelector('.text-declined-pct');
                        
                        if (pendingText) pendingText.textContent = `${pendingPct}%`;
                        if (approvedText) approvedText.textContent = `${approvedPct}%`;
                        if (respondedText) respondedText.textContent = `${respondedPct}%`;
                        if (declinedText) declinedText.textContent = `${declinedPct}%`;
                    }
                });
                
                // Update overview cards
                const grandTotalEl = document.getElementById('analytics-total');
                const grandPendingEl = document.getElementById('analytics-pending');
                const grandApprovedEl = document.getElementById('analytics-approved');
                const grandRespondedEl = document.getElementById('analytics-responded');
                const grandDeclinedEl = document.getElementById('analytics-declined');
                
                if (grandTotalEl) grandTotalEl.textContent = grandTotal;
                if (grandPendingEl) grandPendingEl.textContent = grandPending;
                if (grandApprovedEl) grandApprovedEl.textContent = grandApproved;
                if (grandRespondedEl) grandRespondedEl.textContent = grandResponded;
                if (grandDeclinedEl) grandDeclinedEl.textContent = grandDeclined;
            }
        } catch (e) {
            console.error('Failed to update analytics:', e);
        }
    };
    
    // Initial update
    // updateAnalytics(); // Already loaded by PHP, but we can refresh
    
    // Poll every 5 seconds
    setInterval(updateAnalytics, 5000);
});
</script>
