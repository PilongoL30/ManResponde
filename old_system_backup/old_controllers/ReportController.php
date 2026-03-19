<?php
/**
 * Report Controller
 * Handles all report-related actions (CRUD operations)
 */

require_once __DIR__ . '/../services/ReportService.php';
require_once __DIR__ . '/../services/NotificationService.php';
require_once __DIR__ . '/../includes/View.php';

class ReportController {
    private $reportService;
    private $notificationService;
    private $categories;
    
    public function __construct(array $categories) {
        $this->categories = $categories;
        $this->reportService = new ReportService($categories);
        $this->notificationService = new NotificationService();
    }
    
    /**
     * Get recent reports feed (AJAX)
     */
    public function getRecentFeed(): void {
        $category = $_GET['category'] ?? 'all';
        $status = $_GET['status'] ?? 'all';
        $search = $_GET['search'] ?? '';
        $limit = (int)($_GET['limit'] ?? 15);
        
        try {
            $reports = $this->reportService->getRecentFeed($category, $status, $search, $limit);
            View::json([
                'success' => true,
                'reports' => $reports,
                'count' => count($reports)
            ]);
        } catch (Exception $e) {
            View::json([
                'success' => false,
                'error' => DEBUG_MODE ? $e->getMessage() : 'Failed to load reports'
            ], 500);
        }
    }
    
    /**
     * Get reports by category (AJAX)
     */
    public function getByCategory(): void {
        $category = $_POST['category'] ?? null;
        $limit = (int)($_POST['limit'] ?? 20);
        
        if (!$category || !isset($this->categories[$category])) {
            View::json(['success' => false, 'error' => 'Invalid category'], 400);
            return;
        }
        
        try {
            $collection = $this->categories[$category]['collection'];
            $reports = $this->reportService->getLatestReports($collection, $limit);
            
            View::json([
                'success' => true,
                'reports' => $reports,
                'count' => count($reports)
            ]);
        } catch (Exception $e) {
            View::json([
                'success' => false,
                'error' => DEBUG_MODE ? $e->getMessage() : 'Failed to load reports'
            ], 500);
        }
    }
    
    /**
     * Update report status (AJAX)
     */
    public function updateStatus(): void {
        $collection = $_POST['collection'] ?? null;
        $docId = $_POST['docId'] ?? null;
        $newStatus = $_POST['newStatus'] ?? null;
        
        if (!$collection || !$docId || !$newStatus) {
            View::json(['success' => false, 'error' => 'Missing required parameters'], 400);
            return;
        }
        
        try {
            $success = $this->reportService->updateReportStatus($collection, $docId, $newStatus);
            
            if ($success) {
                // Send notification if FCM token provided
                if (!empty($_POST['fcmToken'])) {
                    $category = $_POST['category'] ?? 'report';
                    $oldStatus = $_POST['oldStatus'] ?? 'pending';
                    
                    $this->notificationService->notifyReportStatusChange(
                        $docId,
                        $category,
                        $oldStatus,
                        $newStatus,
                        $_POST['fcmToken']
                    );
                }
                
                View::json(['success' => true, 'message' => 'Status updated successfully']);
            } else {
                View::json(['success' => false, 'error' => 'Failed to update status'], 500);
            }
        } catch (Exception $e) {
            View::json([
                'success' => false,
                'error' => DEBUG_MODE ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }
    
    /**
     * Get report details by ID (AJAX)
     */
    public function getReportById(): void {
        $collection = $_GET['collection'] ?? null;
        $docId = $_GET['docId'] ?? null;
        
        if (!$collection || !$docId) {
            View::json(['success' => false, 'error' => 'Missing parameters'], 400);
            return;
        }
        
        try {
            $report = $this->reportService->getReportById($collection, $docId);
            
            if ($report) {
                View::json(['success' => true, 'report' => $report]);
            } else {
                View::json(['success' => false, 'error' => 'Report not found'], 404);
            }
        } catch (Exception $e) {
            View::json([
                'success' => false,
                'error' => DEBUG_MODE ? $e->getMessage() : 'Failed to load report'
            ], 500);
        }
    }
    
    /**
     * Get report statistics (AJAX)
     */
    public function getStats(): void {
        try {
            $stats = $this->reportService->getDashboardStats();
            View::json(['success' => true, 'stats' => $stats]);
        } catch (Exception $e) {
            View::json([
                'success' => false,
                'error' => DEBUG_MODE ? $e->getMessage() : 'Failed to load statistics'
            ], 500);
        }
    }
    
    /**
     * Search reports (AJAX)
     */
    public function search(): void {
        $query = $_GET['q'] ?? $_POST['q'] ?? '';
        $categories = $_GET['categories'] ?? $_POST['categories'] ?? [];
        $limit = (int)($_GET['limit'] ?? $_POST['limit'] ?? 50);
        
        if (empty($query)) {
            View::json(['success' => false, 'error' => 'Search query required'], 400);
            return;
        }
        
        try {
            $results = $this->reportService->searchReports($query, $categories, $limit);
            View::json([
                'success' => true,
                'results' => $results,
                'count' => count($results)
            ]);
        } catch (Exception $e) {
            View::json([
                'success' => false,
                'error' => DEBUG_MODE ? $e->getMessage() : 'Search failed'
            ], 500);
        }
    }
}
