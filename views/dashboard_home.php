<?php if ($isAdmin): ?>
    <?php
    // Build Recent Activity (latest across all categories) with 5-minute cache
    $recentFeed = cache_get('recent_feed', 300);
    if ($recentFeed === null) {
        $recentFeed = build_recent_feed($categories);
        cache_set('recent_feed', $recentFeed);
    }
    ?>

    <section class="mt-6 mb-0 animate-fade-in-up" style="--anim-delay: 150ms;">
        <div class="glass-card p-8" data-section="recent-activity">
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white shadow-lg">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-2xl font-bold text-gradient">Recent Activity</h3>
                        <p class="text-sm text-gray-500 mt-1">Live updates from emergency reports</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                        <span class="text-xs text-gray-500">Live</span>
                    </div>
                    <span class="brand-pill" id="activityCount">Last 10 updates</span>
                </div>
            </div>

            <div class="activity-filter mb-8">
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 relative overflow-hidden">
                    <!-- Decorative background element -->
                    <div class="absolute top-0 right-0 -mt-4 -mr-4 w-24 h-24 bg-gradient-to-br from-indigo-50 to-purple-50 rounded-full blur-2xl opacity-50"></div>
                    
                    <div class="flex flex-col md:flex-row md:items-center justify-between mb-6 gap-4 relative z-10">
                        <div>
                            <h4 class="text-lg font-bold text-slate-800 tracking-tight">Filter & Search</h4>
                            <p class="text-sm text-slate-500 mt-1">Refine the activity feed to find specific reports</p>
                        </div>
                        
                        <!-- Reset button moved to top right for cleaner layout -->
                        <button id="activityReset" type="button" class="group flex items-center px-4 py-2 text-sm font-medium text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 hover:text-indigo-600 hover:border-indigo-200 transition-all duration-200 shadow-sm">
                            <svg class="w-4 h-4 mr-2 text-slate-400 group-hover:text-indigo-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                            Reset Filters
                        </button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 relative z-10">
                        <!-- Search -->
                        <div class="relative group">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-slate-400 group-focus-within:text-indigo-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                            </div>
                            <input id="activitySearch" type="text" placeholder="Search by name, location..." 
                                class="block w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 placeholder-slate-400 focus:outline-none focus:bg-white focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 transition-all duration-200 sm:text-sm" />
                        </div>

                        <!-- Category -->
                        <div class="relative group">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-slate-400 group-focus-within:text-indigo-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                                </svg>
                            </div>
                            <select id="activityCategory" 
                                class="block w-full pl-10 pr-10 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:outline-none focus:bg-white focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 transition-all duration-200 sm:text-sm appearance-none cursor-pointer">
                                <option value="all">All Categories</option>
                                <?php foreach ($categories as $slug => $meta): ?>
                                    <option value="<?php echo htmlspecialchars($slug); ?>"><?php echo htmlspecialchars($meta['label']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </div>
                        </div>

                        <!-- Status -->
                        <div class="relative group">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-slate-400 group-focus-within:text-indigo-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <select id="activityStatus" 
                                class="block w-full pl-10 pr-10 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:outline-none focus:bg-white focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 transition-all duration-200 sm:text-sm appearance-none cursor-pointer">
                                <option value="all">All Statuses</option>
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="declined">Declined</option>
                                <option value="responded">Responded</option>
                                <option value="resolved">Resolved</option>
                            </select>
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="activity-scroll glass-card max-h-[500px] overflow-y-auto">
                <ul id="activityList" class="p-4 space-y-3">
                    <!-- Skeleton Loader -->
                    <div id="activitySkeleton">
                        <script>document.write(window.getSkeletonLoader(5));</script>
                    </div>
                </ul>
            </div>
            <div id="activityPagination" class="mt-6 flex items-center justify-between pt-4 border-t border-gray-200/50">
                <div id="activityRange" class="text-sm text-gray-500 font-medium">Showing 0-0 of 0</div>
                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-2">
                        <label for="activityPageSize" class="text-sm text-gray-500 hidden sm:block">Rows per page:</label>
                        <select id="activityPageSize" class="input-premium text-sm">
                            <option value="10">10</option>
                            <option value="20" selected>20</option>
                            <option value="50">50</option>
                        </select>
                    </div>
                    <div class="flex items-center gap-1">
                        <button id="activityPrev" type="button" class="btn btn-secondary">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                            </svg>
                            Previous
                        </button>
                        <button id="activityNext" type="button" class="btn btn-secondary">
                            Next
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>

<?php else: // Staff View ?>
    <div class="mb-4 rounded-xl bg-white/70 backdrop-blur-sm border border-slate-200/80 shadow-sm p-4 animate-fade-in-up" style="--anim-delay: 100ms;">
        <p class="text-sm text-slate-600">
            Your assigned categories:
            <?php 
            if (empty($userCategories)) {
                echo '<span class="font-semibold text-slate-800">None</span>';
            } else {
                foreach ($userCategories as $cat) {
                    $meta = $categories[$cat] ?? null;
                    if ($meta) {
                        echo '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-'.$meta['color'].'-100 text-'.$meta['color'].'-800 mr-2">' . 
                             htmlspecialchars($meta['label']) . '</span>';
                    }
                }
            }
            ?>
        </p>
    </div>

    <section class="space-y-6" id="staffReportCards">
        <div class="text-center py-12 text-slate-500">
            <div class="inline-flex items-center gap-3">
                <?php echo svg_icon('spinner', 'w-5 h-5 animate-spin'); ?>
                <div>
                    <div class="text-lg font-medium">Loading your reports...</div>
                    <div class="text-sm text-slate-400">Please wait a moment.</div>
                </div>
            </div>
        </div>
    </section>
<?php endif; ?>