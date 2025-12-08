<!-- Sidebar -->
        <aside class="hidden md:flex flex-col w-72 glass-card m-4 mr-0 relative z-20">
            <div class="p-6 flex items-center gap-4 border-b border-white/10">
                <div class="relative">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500 to-cyan-400 flex items-center justify-center shadow-lg shadow-blue-500/30">
                        <i class="fas fa-shield-alt text-white text-xl"></i>
                    </div>
                    <div class="absolute -bottom-1 -right-1 w-4 h-4 bg-green-500 rounded-full border-2 border-white animate-pulse"></div>
                </div>
                <div>
                    <h1 class="text-xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-blue-600 to-cyan-500">ManResponde</h1>
                    <p class="text-xs text-gray-500 font-medium tracking-wide">EMERGENCY PORTAL</p>
                </div>
            </div>

            <nav class="flex-1 overflow-y-auto py-6 px-4 space-y-2 custom-scrollbar">
                <div class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-4 px-4">Main Menu</div>
                
                <?php $active = ($view === 'dashboard' || empty($view)) ? 'active' : ''; ?>
                <a href="dashboard?view=dashboard" class="nav-item <?php echo $active; ?> flex items-center gap-3 px-4 py-3.5 rounded-xl text-gray-600 hover:bg-blue-50 hover:text-blue-600 transition-all group relative overflow-hidden">
                    <div class="absolute inset-0 bg-blue-50 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    <i class="fas fa-th-large w-6 text-center group-hover:scale-110 transition-transform relative z-10"></i>
                    <span class="font-medium relative z-10">Dashboard</span>
                    <div class="ml-auto w-1.5 h-1.5 rounded-full bg-blue-500 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                </a>

                <?php $active = ($view === 'analytics') ? 'active' : ''; ?>
                <a href="dashboard?view=analytics" class="nav-item <?php echo $active; ?> flex items-center gap-3 px-4 py-3.5 rounded-xl text-gray-600 hover:bg-blue-50 hover:text-blue-600 transition-all group relative overflow-hidden">
                    <div class="absolute inset-0 bg-blue-50 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    <i class="fas fa-chart-pie w-6 text-center group-hover:scale-110 transition-transform relative z-10"></i>
                    <span class="font-medium relative z-10">Analytics</span>
                </a>

                <?php $active = ($view === 'map') ? 'active' : ''; ?>
                <a href="dashboard?view=map" class="nav-item <?php echo $active; ?> flex items-center gap-3 px-4 py-3.5 rounded-xl text-gray-600 hover:bg-blue-50 hover:text-blue-600 transition-all group relative overflow-hidden">
                    <div class="absolute inset-0 bg-blue-50 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    <i class="fas fa-map-marked-alt w-6 text-center group-hover:scale-110 transition-transform relative z-10"></i>
                    <span class="font-medium relative z-10">Interactive Map</span>
                </a>

                <?php $active = ($view === 'live-support') ? 'active' : ''; ?>
                <a href="dashboard?view=live-support" class="nav-item <?php echo $active; ?> flex items-center gap-3 px-4 py-3.5 rounded-xl text-gray-600 hover:bg-blue-50 hover:text-blue-600 transition-all group relative overflow-hidden">
                    <div class="absolute inset-0 bg-blue-50 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    <i class="fas fa-headset w-6 text-center group-hover:scale-110 transition-transform relative z-10"></i>
                    <span class="font-medium relative z-10">Live Support</span>
                    <span id="liveSupportBadge" class="ml-auto bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full shadow-lg shadow-red-500/30 hidden animate-pulse">0</span>
                </a>

                <?php if ($isAdmin): ?>
                <?php $active = ($view === 'create-account') ? 'active' : ''; ?>
                <a href="dashboard?view=create-account" class="nav-item <?php echo $active; ?> flex items-center gap-3 px-4 py-3.5 rounded-xl text-gray-600 hover:bg-blue-50 hover:text-blue-600 transition-all group relative overflow-hidden">
                    <div class="absolute inset-0 bg-blue-50 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    <i class="fas fa-user-plus w-6 text-center group-hover:scale-110 transition-transform relative z-10"></i>
                    <span class="font-medium relative z-10">Create Account</span>
                </a>
                <?php endif; ?>
                
                <?php if ($isAdmin || $isTanod): ?>
                <?php $active = ($view === 'verify_users') ? 'active' : ''; ?>
                <a href="verify_users.php" class="nav-item <?php echo $active; ?> flex items-center gap-3 px-4 py-3.5 rounded-xl text-gray-600 hover:bg-blue-50 hover:text-blue-600 transition-all group relative overflow-hidden">
                    <div class="absolute inset-0 bg-blue-50 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    <i class="fas fa-user-check w-6 text-center group-hover:scale-110 transition-transform relative z-10"></i>
                    <span class="font-medium relative z-10">Verify Users</span>
                    <span id="verifyUsersBadge" class="ml-auto bg-amber-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full shadow-lg shadow-amber-500/30 hidden animate-pulse">0</span>
                </a>
                <?php endif; ?>

                <?php if ($isAdmin || $isTanod): ?>
                <button onclick="showExportModal()" class="nav-item w-full text-left flex items-center gap-3 px-4 py-3.5 rounded-xl text-gray-600 hover:bg-blue-50 hover:text-blue-600 transition-all group relative overflow-hidden">
                    <div class="absolute inset-0 bg-blue-50 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    <i class="fas fa-download w-6 text-center group-hover:scale-110 transition-transform relative z-10"></i>
                    <span class="font-medium relative z-10">Export Reports</span>
                </button>
                <?php endif; ?>

                <div class="my-4 border-t border-gray-100"></div>
                <div class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-4 px-4">System</div>

                <a href="logout.php" class="flex items-center gap-3 px-4 py-3.5 rounded-xl text-red-500 hover:bg-red-50 transition-all group relative overflow-hidden">
                    <div class="absolute inset-0 bg-red-50 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    <i class="fas fa-sign-out-alt w-6 text-center group-hover:scale-110 transition-transform relative z-10"></i>
                    <span class="font-medium relative z-10">Logout</span>
                </a>
            </nav>

            <div class="p-4 mt-auto space-y-3">
                <!-- Dark Mode Toggle -->
                <div class="bg-white/50 backdrop-blur rounded-xl p-3 border border-gray-200/50">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-moon text-gray-600 text-sm"></i>
                            <span class="text-xs font-medium text-gray-700">Dark Mode</span>
                        </div>
                        <button onclick="toggleTheme()" class="theme-toggle" aria-label="Toggle dark mode">
                            <div class="theme-toggle-slider">
                                <i class="theme-toggle-icon fas fa-sun"></i>
                            </div>
                        </button>
                    </div>
                </div>
                
                <!-- User Profile Card -->
                <div class="bg-gradient-to-br from-blue-600 to-blue-700 rounded-xl p-4 text-white shadow-lg shadow-blue-500/20 relative overflow-hidden group">
                    <div class="absolute top-0 right-0 w-24 h-24 bg-white/10 rounded-full -mr-10 -mt-10 transition-transform group-hover:scale-110"></div>
                    <div class="relative z-10">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-10 h-10 rounded-lg bg-white/20 backdrop-blur flex items-center justify-center">
                                <i class="fas fa-user-circle text-xl"></i>
                            </div>
                            <div>
                                <p class="text-xs text-blue-100 font-medium">Logged in as</p>
                                <p class="font-bold truncate w-32"><?php echo htmlspecialchars($userName); ?></p>
                            </div>
                        </div>
                        <div class="flex items-center justify-between text-xs text-blue-100 bg-blue-800/30 rounded-lg p-2">
                            <span>Role</span>
                            <span class="font-bold uppercase bg-white/20 px-2 py-0.5 rounded text-white"><?php echo htmlspecialchars($userRole); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </aside>
