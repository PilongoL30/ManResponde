<section class="space-y-6">
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
                <option value="Responding">Responding</option>
                <option value="Responded">Responded</option>
                <option value="Declined">Declined</option>
                <option value="Failed">Failed</option>
            </select>
            <button id="activityReset" type="button" class="px-3 py-2 rounded-lg border border-slate-300 bg-white text-slate-700 text-sm">Reset</button>
        </div>

        <ul id="activityList" class="divide-y divide-slate-100">
            <?php if (!empty($initialRecentFeed)): ?>
                <?php foreach ($initialRecentFeed as $index => $row): 
                    $stRaw = strtolower(trim((string)($row['status'] ?? 'Pending')));
                    $st = ($stRaw === 'faile') ? 'failed' : $stRaw;
                    
                    // Status styling config
                    $statusConfig = [
                        'bgColor' => 'from-yellow-500 to-amber-600',
                        'textColor' => 'text-yellow-700',
                        'dotColor' => 'bg-yellow-500',
                        'borderColor' => 'border-yellow-200',
                        'label' => 'Pending'
                    ];
                    
                    switch($st) {
                        case 'approved':
                            $statusConfig = [
                                'bgColor' => 'from-green-500 to-emerald-600',
                                'textColor' => 'text-green-700',
                                'dotColor' => 'bg-green-500',
                                'borderColor' => 'border-green-200',
                                'label' => 'Approved'
                            ];
                            break;
                        case 'declined':
                        case 'failed':
                            $statusConfig = [
                                'bgColor' => 'from-red-500 to-rose-600',
                                'textColor' => 'text-red-700',
                                'dotColor' => 'bg-red-500',
                                'borderColor' => 'border-red-200',
                                'label' => ucfirst($st)
                            ];
                            break;
                        case 'responding':
                            $statusConfig = [
                                'bgColor' => 'from-purple-500 to-fuchsia-600',
                                'textColor' => 'text-purple-700',
                                'dotColor' => 'bg-purple-500',
                                'borderColor' => 'border-purple-200',
                                'label' => 'Responding'
                            ];
                            break;
                        case 'responded':
                            $statusConfig = [
                                'bgColor' => 'from-blue-500 to-cyan-600',
                                'textColor' => 'text-blue-700',
                                'dotColor' => 'bg-blue-500',
                                'borderColor' => 'border-blue-200',
                                'label' => 'Responded'
                            ];
                            break;
                        case 'resolved':
                            $statusConfig = [
                                'bgColor' => 'from-gray-500 to-slate-600',
                                'textColor' => 'text-gray-700',
                                'dotColor' => 'bg-gray-500',
                                'borderColor' => 'border-gray-200',
                                'label' => 'Resolved'
                            ];
                            break;
                    }

                    $esc = function($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
                ?>
                <li
                    onclick="showReportModal(this)" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();showReportModal(this);}"
                    role="button" tabindex="0"
                    data-slug="<?php echo $esc($row['slug'] ?? ''); ?>" 
                    data-id="<?php echo $esc($row['id'] ?? ''); ?>" 
                    data-collection="<?php echo $esc($row['collection'] ?? ''); ?>"
                    data-fullname="<?php echo $esc($row['fullName'] ?? ''); ?>" 
                    data-contact="<?php echo $esc($row['contact'] ?? ''); ?>"
                    data-location="<?php echo $esc($row['location'] ?? ''); ?>" 
                    data-purpose="<?php echo $esc($row['purpose'] ?? ''); ?>"
                    data-reporterid="<?php echo $esc($row['reporterId'] ?? ''); ?>" 
                    data-imageurl="<?php echo $esc($row['imageUrl'] ?? ''); ?>"
                    data-status="<?php echo $esc($st); ?>" 
                    data-timestamp="<?php echo $esc($row['tsDisplay'] ?? ''); ?>"
                    data-lat="<?php echo $esc($row['lat'] ?? ''); ?>" 
                    data-lng="<?php echo $esc($row['lng'] ?? ''); ?>"
                    class="glass-card p-5 cursor-pointer animate-fade-in-up group hover:scale-[1.02] transition-all duration-300"
                    style="--anim-delay: <?php echo $index * 50; ?>ms"
                >
                    <div class="flex items-start gap-4">
                        <div class="relative flex-shrink-0">
                            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br <?php echo $statusConfig['bgColor']; ?> flex items-center justify-center text-white shadow-lg">
                                <?php echo ($row['iconSvg'] ?? ''); ?>
                            </div>
                            <div class="absolute -top-1 -right-1 w-5 h-5 <?php echo $statusConfig['dotColor']; ?> rounded-full border-2 border-white shadow-sm animate-pulse"></div>
                        </div>
                        
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-3 mb-2">
                                <div>
                                    <h4 class="text-base font-bold text-gray-800 mb-1"><?php echo $esc($row['label'] ?? ''); ?></h4>
                                    <p class="text-sm font-semibold text-gray-600"><?php echo $esc($row['fullName'] ?? 'Unknown'); ?></p>
                                </div>
                                <div class="text-right flex-shrink-0">
                                    <span class="text-xs text-gray-500 font-medium"><?php echo $esc($row['tsDisplay'] ?? ''); ?></span>
                                </div>
                            </div>
                            
                            <div class="flex items-center gap-2 mb-3">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                                <span class="text-sm text-gray-500 truncate"><?php echo $esc($row['location'] ?? 'No location specified'); ?></span>
                            </div>
                            
                            <div class="flex items-center justify-between">
                                <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-semibold <?php echo $statusConfig['textColor']; ?> bg-gradient-to-r from-white to-gray-50 border <?php echo $statusConfig['borderColor']; ?> shadow-sm">
                                    <span class="w-2 h-2 rounded-full <?php echo $statusConfig['dotColor']; ?> animate-pulse"></span>
                                    <?php echo $statusConfig['label']; ?>
                                </span>
                                
                                <div class="opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>

        <div class="mt-4 flex flex-col md:flex-row md:items-center md:justify-between gap-3 text-sm">
            <div id="activityCount" class="text-slate-500"><?php echo count($initialRecentFeed ?? []); ?> updates</div>
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
