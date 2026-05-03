<div class="relative h-[calc(100vh-140px)] w-full rounded-2xl overflow-hidden shadow-lg border border-slate-200">
    <!-- Map Container -->
    <div id="dashboardMap" class="absolute inset-0 z-0 bg-slate-100"></div>

    <!-- Floating Controls -->
    <div class="absolute top-4 left-4 z-10 flex flex-col gap-2 w-64">
        <!-- Search -->
        <div class="bg-white/90 backdrop-blur-sm p-2 rounded-lg shadow-lg border border-slate-200">
            <div class="relative">
                <input type="text" id="mapSearch" placeholder="Search location..." class="w-full pl-9 pr-3 py-2 text-sm rounded-md border border-slate-300 focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
                <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white/90 backdrop-blur-sm p-3 rounded-lg shadow-lg border border-slate-200 space-y-3">
            <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider">Filters</h3>
            
            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Category</label>
                <select id="mapCategoryFilter" class="w-full text-sm rounded-md border-slate-300 focus:ring-sky-500 focus:border-sky-500">
                    <option value="all">All Categories</option>
                    <?php foreach ($categories as $slug => $meta): ?>
                        <option value="<?php echo $slug; ?>"><?php echo htmlspecialchars($meta['label']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Status</label>
                <select id="mapStatusFilter" class="w-full text-sm rounded-md border-slate-300 focus:ring-sky-500 focus:border-sky-500">
                    <option value="all">All Statuses</option>
                    <option value="Pending">Pending</option>
                    <option value="Approved">Approved</option>
                    <option value="Responding">Responding</option>
                    <option value="Responded">Responded</option>
                    <option value="Declined">Declined</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Legend -->
    <div class="absolute bottom-4 right-4 z-10 bg-white/90 backdrop-blur-sm p-3 rounded-lg shadow-lg border border-slate-200">
        <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Legend</h3>
        <div class="space-y-1.5">
            <?php foreach ($categories as $slug => $meta): ?>
                <div class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-<?php echo $meta['color']; ?>-500"></span>
                    <span class="text-xs text-slate-700"><?php echo htmlspecialchars($meta['label']); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Loading Overlay -->
    <div id="mapLoading" class="absolute inset-0 z-20 bg-white/80 backdrop-blur-sm flex items-center justify-center">
        <div class="flex flex-col items-center">
            <svg class="w-10 h-10 text-sky-600 animate-spin mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
            </svg>
            <span class="text-sm font-medium text-slate-600">Loading Map Data...</span>
        </div>
    </div>
</div>

<!-- Report Detail Modal (Hidden) -->
<div id="mapReportModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeMapModal()"></div>
    <div class="relative bg-white rounded-xl shadow-2xl max-w-lg w-full overflow-hidden animate-fade-in-up">
        <div class="p-4 border-b border-slate-200 flex justify-between items-center bg-slate-50">
            <h3 class="font-bold text-slate-800" id="mapModalTitle">Report Details</h3>
            <button onclick="closeMapModal()" class="text-slate-400 hover:text-slate-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="p-6" id="mapModalContent">
            <!-- Content injected by JS -->
        </div>
        <div class="p-4 border-t border-slate-200 bg-slate-50 flex justify-end">
            <button onclick="closeMapModal()" class="px-4 py-2 bg-white border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50 font-medium text-sm">Close</button>
            <a id="mapModalViewBtn" href="#" class="ml-2 px-4 py-2 bg-sky-600 text-white rounded-lg hover:bg-sky-700 font-medium text-sm">View Full Report</a>
        </div>
    </div>
</div>
