        <!-- Mobile Header -->
        <header class="md:hidden fixed top-0 left-0 right-0 z-50 glass-card m-4 mt-0 rounded-xl px-4 py-3 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-blue-500 to-cyan-400 flex items-center justify-center shadow-lg shadow-blue-500/30">
                    <i class="fas fa-shield-alt text-white"></i>
                </div>
                <span class="font-bold text-gray-800 bg-clip-text text-transparent bg-gradient-to-r from-blue-600 to-cyan-500">ManResponde</span>
            </div>
            <div class="flex items-center gap-2">
                <!-- Dark Mode Toggle -->
                <button onclick="toggleTheme()" class="theme-toggle" aria-label="Toggle dark mode">
                    <div class="theme-toggle-slider">
                        <i class="theme-toggle-icon fas fa-sun"></i>
                    </div>
                </button>
                <button onclick="toggleMobileMenu()" class="p-2 text-gray-600 hover:bg-blue-50 rounded-lg transition-colors">
                    <i class="fas fa-bars text-xl"></i>
                </button>
            </div>
        </header>

        <!-- Mobile Menu Overlay -->
        <div id="mobileMenuOverlay" class="md:hidden fixed inset-0 z-40 bg-gray-900/50 backdrop-blur-sm hidden transition-opacity" onclick="toggleMobileMenu()">
            <div class="fixed inset-y-0 left-0 w-72 bg-white shadow-2xl transform -translate-x-full transition-transform duration-300 ease-in-out" id="mobileMenuSidebar" onclick="event.stopPropagation()">
                <div class="p-6 flex items-center justify-between border-b border-gray-100">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-blue-500 to-cyan-400 flex items-center justify-center shadow-lg shadow-blue-500/30">
                            <i class="fas fa-shield-alt text-white text-sm"></i>
                        </div>
                        <span class="font-bold text-gray-800">ManResponde</span>
                    </div>
                    <button onclick="toggleMobileMenu()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <nav class="p-4 space-y-2 overflow-y-auto h-[calc(100vh-80px)]">
                    <div class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-4 px-2">Menu</div>
                    
                    <a href="dashboard?view=dashboard" class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-blue-50 hover:text-blue-600 transition-all">
                        <i class="fas fa-th-large w-5 text-center"></i>
                        <span class="font-medium">Dashboard</span>
                    </a>

                    <a href="dashboard?view=map" class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-blue-50 hover:text-blue-600 transition-all">
                        <i class="fas fa-map-marked-alt w-5 text-center"></i>
                        <span class="font-medium">Live Map</span>
                    </a>

                    <a href="dashboard?view=live-support" class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-blue-50 hover:text-blue-600 transition-all">
                        <i class="fas fa-headset w-5 text-center"></i>
                        <span class="font-medium">Live Support</span>
                        <span id="liveSupportBadgeMobile" class="ml-auto bg-red-500 text-white text-xs font-bold px-2 py-0.5 rounded-full hidden">0</span>
                    </a>

                    <?php if ($isAdmin): ?>
                    <a href="dashboard?view=create-account" class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-blue-50 hover:text-blue-600 transition-all">
                        <i class="fas fa-user-plus w-5 text-center"></i>
                        <span class="font-medium">Create Account</span>
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($isAdmin || $isTanod): ?>
                    <a href="verify_users.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-blue-50 hover:text-blue-600 transition-all">
                        <i class="fas fa-user-check w-5 text-center"></i>
                        <span class="font-medium">Verify Users</span>
                        <span id="verifyUsersBadgeMobile" class="ml-auto bg-amber-500 text-white text-xs font-bold px-2 py-0.5 rounded-full hidden">0</span>
                    </a>
                    <?php endif; ?>

                    <?php if ($isAdmin || $isTanod): ?>
                    <button onclick="showExportModal(); toggleMobileMenu()" class="w-full text-left flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-blue-50 hover:text-blue-600 transition-all">
                        <i class="fas fa-download w-5 text-center"></i>
                        <span class="font-medium">Export Reports</span>
                    </button>
                    <?php endif; ?>

                    <div class="my-4 border-t border-gray-100"></div>
                    
                    <a href="logout.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-red-500 hover:bg-red-50 transition-all">
                        <i class="fas fa-sign-out-alt w-5 text-center"></i>
                        <span class="font-medium">Logout</span>
                    </a>
                </nav>
            </div>
        </div>
