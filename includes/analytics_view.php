<div class="space-y-6 animate-fade-in-up">
    <!-- Analytics Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Analytics Dashboard</h1>
            <p class="text-slate-500">Real-time insights and statistical reports</p>
        </div>
        <div class="flex items-center gap-3">
            <select id="analyticsTimeRange" class="form-select text-sm border-slate-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="today">Today</option>
                <option value="week" selected>This Week</option>
                <option value="month">This Month</option>
                <option value="year">This Year</option>
            </select>
            <button onclick="exportAnalytics('pdf')" class="btn btn-secondary text-sm">
                <?php echo svg_icon('download', 'w-4 h-4'); ?>
                Export Report
            </button>
        </div>
    </div>

    <!-- Key Metrics Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Total Reports -->
        <div class="glass-card p-6 relative overflow-hidden">
            <div class="absolute top-0 right-0 p-4 opacity-10">
                <?php echo svg_icon('document-text', 'w-24 h-24 text-blue-600'); ?>
            </div>
            <div class="relative z-10">
                <p class="text-sm font-medium text-slate-500 uppercase tracking-wider">Total Reports</p>
                <h3 class="text-3xl font-bold text-slate-800 mt-2" id="totalReportsCount">0</h3>
                <div class="flex items-center mt-2 text-sm">
                    <span class="text-emerald-600 font-medium flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                        <span id="totalReportsTrend">0%</span>
                    </span>
                    <span class="text-slate-400 ml-2">vs last period</span>
                </div>
            </div>
        </div>

        <!-- Response Rate -->
        <div class="glass-card p-6 relative overflow-hidden">
            <div class="absolute top-0 right-0 p-4 opacity-10">
                <?php echo svg_icon('truck', 'w-24 h-24 text-emerald-600'); ?>
            </div>
            <div class="relative z-10">
                <p class="text-sm font-medium text-slate-500 uppercase tracking-wider">Response Rate</p>
                <h3 class="text-3xl font-bold text-slate-800 mt-2" id="responseRate">0%</h3>
                <div class="flex items-center mt-2 text-sm">
                    <span class="text-emerald-600 font-medium flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                        <span id="responseRateTrend">0%</span>
                    </span>
                    <span class="text-slate-400 ml-2">vs last period</span>
                </div>
            </div>
        </div>

        <!-- Avg Response Time -->
        <div class="glass-card p-6 relative overflow-hidden">
            <div class="absolute top-0 right-0 p-4 opacity-10">
                <?php echo svg_icon('clock', 'w-24 h-24 text-amber-600'); ?>
            </div>
            <div class="relative z-10">
                <p class="text-sm font-medium text-slate-500 uppercase tracking-wider">Avg Response Time</p>
                <h3 class="text-3xl font-bold text-slate-800 mt-2" id="avgResponseTime">0m</h3>
                <div class="flex items-center mt-2 text-sm">
                    <span class="text-red-600 font-medium flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path></svg>
                        <span id="responseTimeTrend">0%</span>
                    </span>
                    <span class="text-slate-400 ml-2">vs last period</span>
                </div>
            </div>
        </div>

        <!-- Active Responders -->
        <div class="glass-card p-6 relative overflow-hidden">
            <div class="absolute top-0 right-0 p-4 opacity-10">
                <?php echo svg_icon('user-group', 'w-24 h-24 text-purple-600'); ?>
            </div>
            <div class="relative z-10">
                <p class="text-sm font-medium text-slate-500 uppercase tracking-wider">Active Responders</p>
                <h3 class="text-3xl font-bold text-slate-800 mt-2" id="activeRespondersCount">0</h3>
                <div class="flex items-center mt-2 text-sm">
                    <span class="text-slate-500">Currently online</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Reports by Category (Pie Chart) -->
        <div class="glass-card p-6">
            <h3 class="text-lg font-bold text-slate-800 mb-4">Reports by Category</h3>
            <div class="relative h-64 w-full">
                <canvas id="reportsByCategoryChart"></canvas>
            </div>
        </div>

        <!-- Reports Trend (Line Chart) -->
        <div class="glass-card p-6">
            <h3 class="text-lg font-bold text-slate-800 mb-4">Reports Trend</h3>
            <div class="relative h-64 w-full">
                <canvas id="reportsTrendChart"></canvas>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Response Time Analysis (Bar Chart) -->
        <div class="glass-card p-6">
            <h3 class="text-lg font-bold text-slate-800 mb-4">Response Time Analysis</h3>
            <div class="relative h-64 w-full">
                <canvas id="responseTimeChart"></canvas>
            </div>
        </div>

        <!-- Heatmap Placeholder (Future) -->
        <div class="glass-card p-6">
            <h3 class="text-lg font-bold text-slate-800 mb-4">Incident Heatmap</h3>
            <div class="relative h-64 w-full bg-slate-100 rounded-lg flex items-center justify-center text-slate-400">
                <div class="text-center">
                    <?php echo svg_icon('map', 'w-12 h-12 mx-auto mb-2 opacity-50'); ?>
                    <p>Geographic distribution visualization coming soon</p>
                </div>
            </div>
        </div>
    </div>
</div>
