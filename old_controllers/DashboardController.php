<?php
/**
 * Dashboard Controller
 * Handles dashboard view rendering and general dashboard actions
 */

require_once __DIR__ . '/../services/ReportService.php';
require_once __DIR__ . '/../services/UserService.php';
require_once __DIR__ . '/../services/StatisticsService.php';
require_once __DIR__ . '/../includes/View.php';

class DashboardController {
    private $reportService;
    private $userService;
    private $statsService;
    private $categories;
    private $userRole;
    private $userCategories;
    
    public function __construct(array $categories, string $userRole, array $userCategories = []) {
        $this->categories = $categories;
        $this->userRole = $userRole;
        $this->userCategories = $userCategories;
        
        $this->reportService = new ReportService($categories);
        $this->userService = new UserService();
        $this->statsService = new StatisticsService($categories);
    }
    
    /**
     * Show main dashboard home view
     */
    public function index(): void {
        if ($this->userRole === 'admin') {
            $this->showAdminDashboard();
        } else {
            $this->showStaffDashboard();
        }
    }
    
    /**
     * Show admin dashboard
     */
    private function showAdminDashboard(): void {
        $stats = $this->statsService->getDashboardStats();
        $pendingCount = $this->userService->getPendingUsersCount();
        
        View::share('stats', $stats);
        View::share('pendingCount', $pendingCount);
        View::share('categories', $this->categories);
        View::share('userRole', $this->userRole);
        
        echo View::render('dashboard_home', [
            'stats' => $stats,
            'pendingCount' => $pendingCount,
        ]);
    }
    
    /**
     * Show staff dashboard
     */
    private function showStaffDashboard(): void {
        View::share('categories', $this->categories);
        View::share('userCategories', $this->userCategories);
        View::share('userRole', $this->userRole);
        
        echo View::render('staff_dashboard', [
            'userCategories' => $this->userCategories,
        ]);
    }
    
    /**
     * Show analytics view
     */
    public function analytics(): void {
        $stats = $this->statsService->getDashboardStats();
        $chartData = $this->statsService->getChartData('daily', 7);
        
        View::share('categories', $this->categories);
        View::share('userRole', $this->userRole);
        
        echo View::render('analytics', [
            'stats' => $stats,
            'chartData' => $chartData,
        ]);
    }
    
    /**
     * Show interactive map view
     */
    public function map(): void {
        View::share('categories', $this->categories);
        View::share('userRole', $this->userRole);
        
        echo View::render('interactive_map');
    }
    
    /**
     * Show live support/chat view
     */
    public function liveSupport(): void {
        View::share('categories', $this->categories);
        View::share('userRole', $this->userRole);
        
        echo View::render('live_support');
    }
    
    /**
     * Show create account view (admin only)
     */
    public function createAccount(): void {
        if ($this->userRole !== 'admin') {
            View::error(403, 'Access denied');
        }
        
        View::share('categories', $this->categories);
        View::share('userRole', $this->userRole);
        
        echo View::render('create_account');
    }
    
    /**
     * Show verify users view (admin only)
     */
    public function verifyUsers(): void {
        if ($this->userRole !== 'admin') {
            View::error(403, 'Access denied');
        }
        
        View::share('categories', $this->categories);
        View::share('userRole', $this->userRole);
        
        echo View::render('verify_users');
    }
    
    /**
     * Get pending users count (AJAX)
     */
    public function getPendingCount(): void {
        $count = $this->userService->getPendingUsersCount();
        View::json(['count' => $count]);
    }
    
    /**
     * Get cache statistics (AJAX)
     */
    public function getCacheStats(): void {
        if (function_exists('cache_stats')) {
            $stats = cache_stats();
            View::json($stats);
        } else {
            View::json(['error' => 'Cache not available'], 500);
        }
    }
}
