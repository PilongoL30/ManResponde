<section class="space-y-6">
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3" id="topKpiContainer">
        <div class="kpi-card"><div class="kpi-label">Pending</div><div class="kpi-value text-amber-600">0</div></div>
        <div class="kpi-card"><div class="kpi-label">Approved</div><div class="kpi-value text-emerald-600">0</div></div>
        <div class="kpi-card"><div class="kpi-label">Responded</div><div class="kpi-value text-cyan-600">0</div></div>
        <div class="kpi-card"><div class="kpi-label">Declined</div><div class="kpi-value text-rose-600">0</div></div>
        <div class="kpi-card"><div class="kpi-label">Total</div><div class="kpi-value text-slate-700">0</div></div>
    </div>

    <div class="flex flex-wrap gap-2">
        <button type="button" onclick="refreshAdminStats()" class="px-3 py-2 rounded-lg bg-sky-600 text-white text-sm font-semibold hover:bg-sky-700">Refresh Stats</button>
        <button type="button" onclick="clearAllCache()" class="px-3 py-2 rounded-lg bg-white border border-slate-300 text-slate-700 text-sm font-semibold hover:bg-slate-50">Clear Cache</button>
    </div>

    <div id="adminStatsContainer" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        <div class="col-span-full text-center py-6 text-slate-500">
            <div class="inline-flex items-center gap-2"><?php echo svg_icon('spinner', 'w-4 h-4 animate-spin'); ?> Loading statistics...</div>
        </div>
    </div>

    <div id="emergencyAlertsContainer"></div>

    <div class="glass-card p-4 md:p-6">
        <div class="flex flex-col md:flex-row md:items-end gap-3 md:gap-4 mb-4">
            <div class="flex-1">
                <h3 class="text-lg font-bold text-slate-800">Recent Activity</h3>
                <p class="text-xs text-slate-500">Latest emergency reports and status updates</p>
            </div>
            <input id="activitySearch" type="text" placeholder="Search..." class="w-full md:w-52 border border-slate-300 rounded-lg px-3 py-2 text-sm">
            <select id="activityCategory" class="w-full md:w-44 border border-slate-300 rounded-lg px-3 py-2 text-sm">
                <option value="all">All Categories</option>
                <?php foreach (($categories ?? []) as $slug => $meta): ?>
                    <option value="<?php echo htmlspecialchars((string)$slug); ?>"><?php echo htmlspecialchars((string)($meta['label'] ?? $slug)); ?></option>
                <?php endforeach; ?>
            </select>
            <select id="activityStatus" class="w-full md:w-36 border border-slate-300 rounded-lg px-3 py-2 text-sm">
                <option value="all">All Status</option>
                <option value="Pending">Pending</option>
                <option value="Approved">Approved</option>
                <option value="Responded">Responded</option>
                <option value="Declined">Declined</option>
            </select>
            <button id="activityReset" type="button" class="px-3 py-2 rounded-lg border border-slate-300 bg-white text-slate-700 text-sm">Reset</button>
        </div>

        <ul id="activityList" class="divide-y divide-slate-100"></ul>

        <div class="mt-4 flex flex-col md:flex-row md:items-center md:justify-between gap-3 text-sm">
            <div id="activityCount" class="text-slate-500">0 items</div>
            <div class="flex items-center gap-2">
                <label class="text-slate-500" for="activityPageSize">Page size</label>
                <select id="activityPageSize" class="border border-slate-300 rounded-lg px-2 py-1 text-sm">
                    <option value="10">10</option>
                    <option value="20" selected>20</option>
                    <option value="50">50</option>
                </select>
                <button id="activityPrev" type="button" class="px-3 py-1.5 rounded-lg border border-slate-300 bg-white text-slate-700">Prev</button>
                <span id="activityRange" class="text-slate-500 px-2">0-0</span>
                <button id="activityNext" type="button" class="px-3 py-1.5 rounded-lg border border-slate-300 bg-white text-slate-700">Next</button>
            </div>
        </div>

        <div id="recentActivityList" class="hidden"></div>
    </div>
</section>
