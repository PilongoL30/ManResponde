<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ManResponde • Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php echo csrf_meta(); ?>
    <?php emit_performance_head(); ?>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="responde.png">
    <link rel="icon" type="image/png" sizes="16x16" href="responde.png">
    <link rel="apple-touch-icon" href="responde.png">
    <link rel="shortcut icon" href="responde.png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?php echo filemtime(dirname(__DIR__) . '/assets/css/dashboard.css'); ?>">
    
    <!-- Leaflet Maps -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
</head>
<body class="antialiased">

    <div class="flex h-screen bg-slate-100">
        <aside class="hidden md:flex w-64 flex-shrink-0 bg-white text-slate-600 flex-col p-4 border-r border-slate-200">
            <div class="h-16 flex items-center justify-center px-2">
                <img src="responde.png" alt="ManResponde Logo" class="h-10 w-auto object-contain sm:h-12 md:h-14 lg:h-10" onerror="this.style.display='none'">
            </div>
            <nav class="flex-1 px-2 py-4 space-y-1.5">
                <?php $isDashActive = ($view === 'dashboard'); ?>
                <a href="dashboard.php?view=dashboard" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?php echo $isDashActive ? 'bg-sky-100 text-sky-700 font-semibold' : 'hover:bg-slate-50'; ?>">
                    <?php echo svg_icon('dashboard', 'w-5 h-5'); ?>
                    <span>Dashboard</span>
                </a>

                <?php $isAnalyticsActive = ($view === 'analytics'); ?>
                <a href="analytics.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?php echo $isAnalyticsActive ? 'bg-sky-100 text-sky-700 font-semibold' : 'hover:bg-slate-50 text-slate-600'; ?>">
                    <?php echo svg_icon('chart-pie', 'w-5 h-5'); ?>
                    <span>Analytics</span>
                </a>

                <?php $isMapActive = ($view === 'map'); ?>
                <a href="interactive_map.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?php echo $isMapActive ? 'bg-sky-100 text-sky-700 font-semibold' : 'hover:bg-slate-50 text-slate-600'; ?>">
                    <?php echo svg_icon('map', 'w-5 h-5'); ?>
                    <span>Interactive Map</span>
                </a>
                
                <?php $isLiveSupportActive = ($view === 'live-support'); ?>
                <a href="live_support.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?php echo $isLiveSupportActive ? 'bg-sky-100 text-sky-700 font-semibold' : 'hover:bg-slate-50'; ?>">
                    <?php echo svg_icon('chat', 'w-5 h-5'); ?>
                    <span>Live Support</span>
                    <span id="liveSupportBadge" class="ml-auto bg-red-500 text-white text-xs font-bold px-2 py-0.5 rounded-full hidden">0</span>
                </a>

                <?php if ($isAdmin): ?>
                <?php $isCreateAccountActive = ($view === 'create-account'); ?>
                <a href="create_account.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?php echo $isCreateAccountActive ? 'bg-sky-100 text-sky-700 font-semibold' : 'hover:bg-slate-50 text-slate-600'; ?>">
                    <?php echo svg_icon('user-plus', 'w-5 h-5'); ?>
                    <span>Create Account</span>
                </a>
                <?php endif; ?>
                
                <?php if ($isAdmin || $isTanod): ?>
                <?php $isVerifyUsersActive = ($view === 'verify-users'); ?>
                <!-- Link to new standalone Verify Users page -->
                <a href="verify_users.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?php echo $isVerifyUsersActive ? 'bg-sky-100 text-sky-700 font-semibold' : 'hover:bg-slate-50 text-slate-600'; ?>">
                    <?php echo svg_icon('user-check', 'w-5 h-5'); ?>
                    <span>Verify Users</span>
                    <span id="verifyUsersBadge" class="ml-auto bg-amber-500 text-white text-xs font-bold px-2 py-0.5 rounded-full hidden">0</span>
                </a>
                <?php endif; ?>

                <?php if ($isAdmin): ?>
                <!-- Export Reports -->
                <button onclick="showExportModal()" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-50 text-slate-600 w-full text-left">
                    <?php echo svg_icon('download', 'w-5 h-5'); ?>
                    <span>Export Reports</span>
                </button>
                <?php endif; ?>
            </nav>
            <div class="p-2 border-t border-slate-200/70 pt-4">
                 <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-full bg-sky-500 flex items-center justify-center font-bold text-white ring-2 ring-sky-200">
                        <?php echo strtoupper(substr($userName, 0, 1)); ?>
                    </div>
                    <div>
                        <p class="font-semibold text-slate-800 text-sm"><?php echo htmlspecialchars($userName); ?></p>
                        <p class="text-xs text-slate-500"><?php echo htmlspecialchars(ucfirst($userRole)); ?></p>
                    </div>
                </div>
                <a href="logout.php" class="flex items-center justify-center gap-2 rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200 w-full px-4 py-2.5 text-sm font-semibold">
                    <?php echo svg_icon('logout', 'w-5 h-5'); ?>
                    <span>Logout</span>
                </a>
            </div>
        </aside>

        <!-- Mobile Header -->
        <div class="md:hidden fixed top-0 left-0 right-0 z-50 bg-white border-b border-slate-200 px-4 py-3">
            <div class="flex items-center justify-between">
                <button id="mobileMenuBtn" class="p-2 rounded-lg text-slate-600 hover:bg-slate-100">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
                <div class="flex items-center justify-center flex-1">
                    <img src="responde.png" alt="ManResponde Logo" class="h-8 w-auto object-contain" onerror="this.style.display='none'">
                </div>
                <div class="w-10"></div> <!-- Spacer to keep logo centered -->
            </div>
        </div>

        <!-- Mobile Menu Overlay -->
        <div id="mobileMenuOverlay" class="md:hidden fixed inset-0 z-40 bg-black bg-opacity-50 hidden">
            <div class="fixed inset-y-0 left-0 w-64 bg-white shadow-xl">
                <div class="flex flex-col h-full">
                    <div class="h-16 flex items-center justify-center px-4 border-b border-slate-200">
                        <img src="responde.png" alt="ManResponde Logo" class="h-10 w-auto object-contain" onerror="this.style.display='none'">
                    </div>
                    <nav class="flex-1 px-4 py-4 space-y-1.5 overflow-y-auto">
                        <?php $isDashActive = ($view === 'dashboard'); ?>
                        <a href="dashboard.php?view=dashboard" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?php echo $isDashActive ? 'bg-sky-100 text-sky-700 font-semibold' : 'hover:bg-slate-50 text-slate-600'; ?>">
                            <?php echo svg_icon('dashboard', 'w-5 h-5'); ?>
                            <span>Dashboard</span>
                        </a>

                        <a href="analytics.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?php echo $isAnalyticsActive ? 'bg-sky-100 text-sky-700 font-semibold' : 'hover:bg-slate-50 text-slate-600'; ?>">
                            <?php echo svg_icon('chart-pie', 'w-5 h-5'); ?>
                            <span>Analytics</span>
                        </a>

                        <?php $isMapActive = ($view === 'map'); ?>
                        <a href="interactive_map.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?php echo $isMapActive ? 'bg-sky-100 text-sky-700 font-semibold' : 'hover:bg-slate-50 text-slate-600'; ?>">
                            <?php echo svg_icon('map', 'w-5 h-5'); ?>
                            <span>Interactive Map</span>
                        </a>
                        
                        <?php $isLiveSupportActive = ($view === 'live-support'); ?>
                        <a href="live_support.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?php echo $isLiveSupportActive ? 'bg-sky-100 text-sky-700 font-semibold' : 'hover:bg-slate-50 text-slate-600'; ?>">
                            <?php echo svg_icon('chat', 'w-5 h-5'); ?>
                            <span>Live Support</span>
                            <span id="liveSupportBadgeMobile" class="ml-auto bg-red-500 text-white text-xs font-bold px-2 py-0.5 rounded-full hidden">0</span>
                        </a>
                        
                        <?php if ($isAdmin): ?>
                            <?php $isCreateAccountActive = ($view === 'create-account'); ?>
                            <a href="create_account.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?php echo $isCreateAccountActive ? 'bg-sky-100 text-sky-700 font-semibold' : 'hover:bg-slate-50 text-slate-600'; ?>">
                                <?php echo svg_icon('user-plus', 'w-5 h-5'); ?>
                                <span>Create Account</span>
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($isAdmin || $isTanod): ?>
                            <?php $isVerifyUsersActive = ($view === 'verify-users'); ?>
                            <a href="verify_users.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?php echo $isVerifyUsersActive ? 'bg-sky-100 text-sky-700 font-semibold' : 'hover:bg-slate-50 text-slate-600'; ?>">
                                <?php echo svg_icon('user-check', 'w-5 h-5'); ?>
                                <span>Verify Users</span>
                                <span id="verifyUsersBadgeMobile" class="ml-auto bg-amber-500 text-white text-xs font-bold px-2 py-0.5 rounded-full hidden">0</span>
                            </a>
                        <?php endif; ?>

                        <?php if ($isAdmin): ?>
                            <!-- Export Reports for mobile -->
                            <button onclick="showExportModal(); closeMobileSidebar();" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-50 text-slate-600 w-full text-left">
                                <?php echo svg_icon('download', 'w-5 h-5'); ?>
                                <span>Export Reports</span>
                            </button>
                        <?php endif; ?>
                        
                        <div class="border-t border-slate-200 pt-4 mt-4">
                            <a href="logout.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-50">
                                <?php echo svg_icon('logout', 'w-5 h-5'); ?>
                                <span>Logout</span>
                            </a>
                        </div>
                    </nav>
                </div>
            </div>
        </div>

        <main class="flex-1 overflow-y-auto aurora-background">
            <div class="pt-20 md:pt-6 pb-6 px-4 sm:px-6 md:px-8 lg:px-10 animate-fade-in relative z-10">
                
                <header class="mb-6">
                    <div class="flex items-center justify-between">
                        <div>
                    <h1 class="text-3xl md:text-4xl font-extrabold text-slate-900 tracking-tighter">
                        <?php
                            if ($view === 'analytics') echo 'Analytics';
                            else echo 'Dashboard';
                        ?>
                    </h1>
                    <p class="text-slate-500 mt-1 text-base md:text-lg">
                        <?php
                            if ($view === 'analytics') {
                                echo 'Comprehensive statistical overview of emergency reports.';
                            } else {
                                echo 'Welcome back, '.htmlspecialchars($userName).'. Here\'s what\'s happening.';
                            }
                        ?>
                    </p>
                        </div>
                        
                        <?php if ($userRole === 'staff'): ?>
                        <div class="relative">
                            <button id="notificationBell" class="relative p-3 text-slate-600 hover:text-red-600 transition-colors bg-white/80 backdrop-blur-sm rounded-full shadow-lg border border-slate-200/80">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"></path>
                                </svg>
                                <span id="notificationBadge" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center hidden">0</span>
                            </button>
                            
                            <div id="notificationDropdown" class="absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg border border-slate-200 z-50 hidden">
                                <div class="p-4 border-b border-slate-200">
                                    <h3 class="text-lg font-semibold text-slate-800">Emergency Notifications</h3>
                                </div>
                                <div id="notificationList" class="max-h-96 overflow-y-auto">
                                    </div>
                                <div class="p-4 border-t border-slate-200">
                                    <button id="markAllRead" class="text-sm text-blue-600 hover:text-blue-800">Mark all as read</button>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </header>


                <?php if ($isAdmin): ?>
                    <?php if ($view === 'analytics'): ?>
                        <?php include __DIR__ . '/analytics.php'; ?>

                    <?php else: // Default Admin Dashboard View ?>
                        <?php 
                            // Pre-render the first page of recent activity to eliminate "Loading" spinners
                            $initialRecentFeed = [];
                            try {
                                $initialRecentFeed = build_recent_feed($categories);
                            } catch (Exception $e) {
                                error_log("Failed to pre-render recent feed: " . $e->getMessage());
                            }
                            include __DIR__ . '/dashboard_home.php'; 
                        ?>
                    <?php endif; ?>
                
                <?php else: // Staff View ?>
                    <?php if ($view === 'analytics'): ?>
                        <?php include __DIR__ . '/analytics.php'; ?>
                    <?php else: ?>
                        <div class="mb-4 rounded-xl bg-white/70 backdrop-blur-sm border border-slate-200/80 shadow-sm p-4 animate-fade-in-up" style="--anim-delay: 100ms;">
                            <p class="text-sm text-slate-600">
                                Your assigned categories:
                                <?php
                                    if (!empty($userCategories)) {
                                        foreach ($userCategories as $cat) {
                                            $catSlug = strtolower($cat);
                                            $catLabel = $categories[$catSlug]['label'] ?? ucfirst($cat);
                                            echo '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 mr-2">' . htmlspecialchars($catLabel) . '</span>';
                                        }
                                    } else {
                                        echo '<span class="text-slate-400 italic">None</span>';
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
                <?php endif; ?>

            </div>
        </main>
    </div>
    
    </div>
    
    <div id="reportModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 transition-all duration-500 opacity-0 pointer-events-none backdrop-blur-sm">
        <div class="absolute inset-0 bg-gradient-to-br from-slate-900/80 via-slate-800/70 to-slate-900/80" onclick="closeReportModal()"></div>
        <div id="modalContent" class="relative max-w-6xl w-full glass-card overflow-hidden transition-all duration-500 scale-90 opacity-0 animate-fade-in-up">
            <!-- Premium Header with Gradient -->
            <div class="relative px-8 py-6 bg-gradient-to-r from-emerald-600 via-cyan-600 to-teal-600 text-white overflow-hidden">
                <div class="absolute inset-0 bg-black/10"></div>
                <div class="relative z-10 flex items-center justify-between">
                    <div id="m_header" class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-white/20 backdrop-blur-sm flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold">Emergency Report Details</h2>
                            <p class="text-white/80 text-sm">Detailed incident information</p>
                        </div>
                    </div>
                    <button class="w-10 h-10 rounded-xl bg-white/20 hover:bg-white/30 backdrop-blur-sm transition-all duration-300 flex items-center justify-center group" onclick="closeReportModal()">
                        <svg class="w-5 h-5 group-hover:rotate-90 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <div class="absolute -bottom-1 left-0 right-0 h-1 bg-gradient-to-r from-emerald-400 via-cyan-400 to-teal-400 opacity-60"></div>
            </div>

            <!-- Content Area with Premium Cards -->
            <div class="p-8 max-h-[75vh] overflow-y-auto bg-gradient-to-br from-gray-50/50 to-white/80">
                <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
                    
                    <!-- Reporter Information Card -->
                    <div class="xl:col-span-2 space-y-6">
                        <div class="glass-card p-6">
                            <div class="flex items-center gap-3 mb-6">
                                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
                                </div>
                                <h3 class="text-lg font-bold text-gray-800">Reporter Information</h3>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-1">
                                    <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider flex items-center gap-2">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                        </svg>
                                        Full Name
                                    </label>
                                    <div id="m_fullName" class="text-xl font-bold text-gray-900 bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">—</div>
                                </div>
                                <div class="space-y-1">
                                    <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider flex items-center gap-2">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                        </svg>
                                        Contact Number
                                    </label>
                                    <div id="m_contact" class="text-lg font-semibold text-gray-700">—</div>
                                </div>
                            </div>
                        </div>

                        <!-- Location & Details Card -->
                        <div class="glass-card p-6">
                            <div class="flex items-center gap-3 mb-6">
                                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center text-white">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                </div>
                                <h3 class="text-lg font-bold text-gray-800">Incident Details</h3>
                            </div>
                            
                            <div class="space-y-6">
                                <div class="space-y-2">
                                    <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider flex items-center gap-2">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                        </svg>
                                        Location
                                    </label>
                                    <div id="m_location" class="text-base font-semibold text-gray-700 p-3 bg-gray-50/80 rounded-xl border border-gray-200">—</div>
                                    
                                    <!-- Embedded Map Container -->
                                    <div id="m_map_container" class="hidden mt-3 rounded-xl overflow-hidden border border-gray-200 shadow-sm">
                                        <div id="m_map" class="w-full h-64 z-0"></div>
                                        <div class="bg-gray-50 px-3 py-2 text-xs text-gray-500 flex justify-between items-center border-t border-gray-200">
                                            <span><?php echo svg_icon('map', 'w-3 h-3 inline-block mr-1'); ?> Incident Location</span>
                                            <span id="m_map_status">Loading map...</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="space-y-2">
                                    <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider flex items-center gap-2">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                        Incident Description
                                    </label>
                                    <div id="m_purpose" class="text-gray-700 p-4 bg-gray-50/80 rounded-xl border border-gray-200 leading-relaxed">—</div>
                                </div>
                            </div>
                        </div>

                        <!-- Metadata Card -->
                        <div class="glass-card p-6">
                            <div class="flex items-center gap-3 mb-6">
                                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-purple-500 to-pink-600 flex items-center justify-center text-white">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <h3 class="text-lg font-bold text-gray-800">Report Metadata</h3>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider flex items-center gap-2">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        Submitted At
                                    </label>
                                    <div id="m_timestamp" class="text-base font-semibold text-gray-700 p-3 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg border border-blue-200">—</div>
                                </div>
                                <div class="space-y-2">
                                    <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider flex items-center gap-2">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                        Reporter ID
                                    </label>
                                    <div id="m_reporterId" class="text-sm font-mono text-gray-600 p-3 bg-gray-50/80 rounded-lg border border-gray-200 break-all">—</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Media & Actions Sidebar -->
                    <div class="space-y-6">
                        <!-- Media Card -->
                        <div class="glass-card p-6">
                            <div class="flex items-center gap-3 mb-6">
                                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-orange-500 to-red-600 flex items-center justify-center text-white">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                </div>
                                <h3 class="text-lg font-bold text-gray-800">Attached Evidence</h3>
                            </div>
                            
                            <a id="m_image_link" href="#" target="_blank" class="group block rounded-2xl overflow-hidden border-2 border-dashed border-gray-200 bg-gradient-to-br from-gray-50 to-gray-100 aspect-[4/3] flex items-center justify-center hover:border-blue-300 hover:bg-gradient-to-br hover:from-blue-50 hover:to-indigo-50 transition-all duration-300">
                                <img id="m_image" src="" alt="Report evidence" class="w-full h-full object-cover hidden transition-all duration-500 group-hover:scale-105 rounded-xl">
                                <video id="m_video" controls class="w-full h-full object-cover hidden rounded-xl shadow-lg" preload="metadata">
                                    <source id="m_video_source" src="" type="">
                                    Your browser does not support the video tag.
                                </video>
                                <div id="m_image_none" class="text-center text-gray-400">
                                    <svg class="w-12 h-12 mx-auto mb-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                    <p class="font-medium">No Evidence Attached</p>
                                    <p class="text-sm">No media was provided with this report</p>
                                </div>
                            </a>
                            <div class="text-center mt-3">
                                <span id="m_media_hint" class="text-xs text-gray-500 bg-gray-100 px-3 py-1 rounded-full">Click to view full size</span>
                            </div>
                        </div>

                        <!-- Status Card -->
                        <div class="glass-card p-6">
                            <div class="flex items-center gap-3 mb-6">
                                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-green-500 to-emerald-600 flex items-center justify-center text-white">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <h3 class="text-lg font-bold text-gray-800">Current Status</h3>
                            </div>
                            
                            <div id="m_status_container" class="text-center">
                                <span id="m_status" class="inline-flex items-center gap-3 px-6 py-3 rounded-2xl text-base font-bold shadow-lg">—</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions Footer -->
            <div class="px-8 py-6 bg-gradient-to-r from-gray-50 to-gray-100 border-t border-gray-200/50 flex items-center justify-between">
                <div class="flex items-center gap-3 text-gray-500">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span class="text-sm font-medium">Review and take action on this emergency report</span>
                </div>
                <div id="m_actions" class="flex items-center gap-3"></div>
            </div>
        </div>
    </div>

    <div id="proofModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 transition-opacity duration-300 opacity-0 pointer-events-none">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeProofModal()"></div>
        <div id="proofModalContent" class="relative max-w-lg w-full bg-white rounded-2xl shadow-xl overflow-hidden transition-transform duration-300 scale-95 opacity-0">
            <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
                <h3 id="p_header" class="text-lg font-bold text-slate-900">Proof of Residency</h3>
                <button class="text-slate-400 hover:text-slate-800 transition-colors" onclick="closeProofModal()"><?php echo svg_icon('x-mark', 'w-6 h-6'); ?></button>
            </div>
            <div class="p-6">
                <a id="p_image_link" href="#" target="_blank" class="block rounded-lg overflow-hidden border-2 border-slate-200 bg-slate-50 aspect-w-16 aspect-h-9 flex items-center justify-center group">
                    <img id="p_image" src="" alt="Proof of Residency" class="w-full h-full object-contain transition-transform duration-300 group-hover:scale-105">
                </a>
                <div class="text-xs text-slate-400 mt-2 text-center">Click image to open in new tab.</div>
            </div>
        </div>
    </div>
    
    <div id="toastContainer" class="fixed top-5 right-5 z-[100] w-full max-w-xs space-y-3"></div>

    <script>
        // Dashboard configuration for JavaScript
        window.dashboardConfig = {
            isAdmin: <?php echo $isAdmin ? 'true' : 'false'; ?>,
            userRole: '<?php echo htmlspecialchars($userRole); ?>',
            view: '<?php echo htmlspecialchars($view); ?>',
            userCategories: <?php echo json_encode(array_values($userCategories ?? [])); ?>,
            userBarangay: <?php echo json_encode((string)($userProfile['assignedBarangay'] ?? ($_SESSION['assignedBarangay'] ?? ''))); ?>
        };
        
        // Set your Firebase Web config here to enable realtime updates (onSnapshot).
        window.FIREBASE_CLIENT_CONFIG = {
            apiKey: "AIzaSyDiNgvmttAwhAjPthjJtcZ1Hr9PLWnhErQ", // Firebase Web config
            authDomain: "ibantayv2.firebaseapp.com",
            projectId: "ibantayv2"
        };
        
        // CSRF Token Helper
        function getCsrfToken() {
            const meta = document.querySelector('meta[name="csrf-token"]');
            return meta ? meta.getAttribute('content') : '';
        }
        
        // Enhanced FormData with CSRF token
        function createFormDataWithCsrf() {
            const formData = new FormData();
            formData.append('<?php echo CSRF_TOKEN_NAME; ?>', getCsrfToken());
            return formData;
        }
        
        // Automatically add CSRF token to all FormData instances
        const originalFormData = window.FormData;
        window.FormData = function(form) {
            const formData = new originalFormData(form);
            const csrfToken = getCsrfToken();
            if (csrfToken && !formData.has('<?php echo CSRF_TOKEN_NAME; ?>')) {
                formData.append('<?php echo CSRF_TOKEN_NAME; ?>', csrfToken);
            }
            return formData;
        };
    </script>
    
    <?php if (!empty($__preloadedFeed)): ?>
    <script>
        // Pre-loaded recent feed data (server-side rendered for instant display)
        window.__preloadedRecentFeed = {
            success: true,
            data: <?php echo json_encode($__preloadedFeed, JSON_UNESCAPED_UNICODE); ?>,
            total: <?php echo count($__preloadedFeed); ?>,
            page: 1,
            pageSize: 20,
            hasMore: <?php echo count($__preloadedFeed) > 20 ? 'true' : 'false'; ?>,
            filters: { search: '', category: 'all', status: 'all' },
            preloaded: true
        };
    </script>
    <?php endif; ?>
    
    <!-- Dashboard UI JS: theme init, badges, modals -->
    <script src="assets/js/dashboard-ui.js?v=<?php echo filemtime(__DIR__ . '/assets/js/dashboard-ui.js'); ?>" defer></script>
    <script>
        <?php include __DIR__ . '/dashboard_main_scripts.php'; ?>
    </script>

    <script type="module">
        <?php include __DIR__ . '/dashboard_map_scripts.php'; ?>
    </script>
    <!-- Live support badge + end chat modal + verify users badge moved to dashboard-ui.js -->
    <!-- End Chat Confirmation Modal -->
    <div id="endChatModal" class="fixed inset-0 z-50 hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity opacity-0" id="endChatModalBackdrop"></div>

        <div class="fixed inset-0 z-10 overflow-y-auto">
            <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                <!-- Modal Panel -->
                <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-md opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" id="endChatModalPanel">
                    
                    <!-- Decorative Header Pattern -->
                    <div class="absolute top-0 left-0 w-full h-2 bg-gradient-to-r from-red-400 to-red-600"></div>

                    <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10 mb-4 sm:mb-0">
                                <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                </svg>
                            </div>
                            <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left w-full">
                                <h3 class="text-lg font-bold leading-6 text-slate-900" id="modal-title">End Live Agent</h3>
                                <div class="mt-2">
                                    <p class="text-sm text-slate-500">Do you want to end Live Agent? This will close the active chat and notify the user.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-slate-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 border-t border-slate-100">
                        <button type="button" onclick="confirmEndChatAction()" class="inline-flex w-full justify-center rounded-xl bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500 sm:ml-3 sm:w-auto transition-all hover:shadow-lg hover:shadow-red-500/30 active:scale-95">End Chat</button>
                        <button type="button" onclick="closeEndChatModal()" class="mt-3 inline-flex w-full justify-center rounded-xl bg-white px-3 py-2 text-sm font-semibold text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50 sm:mt-0 sm:w-auto transition-all hover:shadow-md active:scale-95">Cancel</button>
                    </div>
                </div>
            </div>
        </div>
    </div>



    
    <!-- Dashboard Core JS for realtime polling -->
    <script src="assets/js/dashboard-core.js?v=<?php echo filemtime(__DIR__ . '/assets/js/dashboard-core.js'); ?>" defer></script>
    <?php if (($_GET['view'] ?? 'dashboard') === 'analytics'): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="assets/js/analytics.js?v=<?php echo filemtime(__DIR__ . '/assets/js/analytics.js'); ?>"></script>
    <?php endif; ?>
    <?php emit_performance_scripts(); ?>
</body>
</html>

<?php
if (defined('DEBUG_MODE') && DEBUG_MODE) {
    $ms = round((microtime(true) - ($__reqStart ?? microtime(true))) * 1000, 2);
    $v = $_GET['view'] ?? 'dashboard';
    error_log('[perf] dashboard.php end view=' . $v . ' ms=' . $ms . ' sid=' . session_id());
}
?>