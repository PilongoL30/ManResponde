<?php
/**
 * Report Service
 * Handles all report-related business logic and data operations
 */

class ReportService {
    private $categories;
    
    public function __construct(array $categories) {
        $this->categories = $categories;
    }
    
    /**
     * Get latest reports from a collection with caching
     */
    public function getLatestReports(string $collection, int $limit = 20, bool $useCache = true): array {
        return list_latest_reports($collection, $limit, $useCache);
    }
    
    /**
     * Get recent activity feed for dashboard
     */
    public function getRecentFeed(string $categoryFilter = 'all', string $statusFilter = 'all', string $search = '', int $limit = 15): array {
        $cacheKey = "recent_feed_" . md5($search . $categoryFilter . $statusFilter);
        
        $cachedData = cache_get($cacheKey, 30);
        if ($cachedData !== null) {
            return $cachedData;
        }
        
        $allRecent = build_recent_feed_optimized($this->categories, $categoryFilter, $statusFilter, $search, $limit);
        cache_set($cacheKey, $allRecent, 30);
        
        return $allRecent;
    }
    
    /**
     * Update report status
     */
    public function updateReportStatus(string $collection, string $docId, string $newStatus, array $additionalData = []): bool {
        try {
            $updateData = array_merge(['status' => $newStatus], $additionalData);
            
            if (function_exists('firestore_update_doc')) {
                return firestore_update_doc($collection, $docId, $updateData);
            }
            
            // REST API fallback
            $url = firestore_base_url() . '/' . rawurlencode($collection) . '/' . rawurlencode($docId);
            $body = ['fields' => firestore_encode_fields($updateData)];
            $response = firestore_rest_request('PATCH', $url . '?updateMask.fieldPaths=status', $body);
            
            return !empty($response);
        } catch (Exception $e) {
            if (DEBUG_MODE) {
                error_log("Error updating report status: " . $e->getMessage());
            }
            return false;
        }
    }
    
    /**
     * Get report by ID
     */
    public function getReportById(string $collection, string $docId): ?array {
        try {
            if (function_exists('firestore_get_doc_by_id')) {
                return firestore_get_doc_by_id($collection, $docId);
            }
            
            // REST fallback
            $url = firestore_base_url() . '/' . rawurlencode($collection) . '/' . rawurlencode($docId);
            $response = firestore_rest_request('GET', $url);
            
            if (isset($response['fields'])) {
                return firestore_decode_fields($response['fields']);
            }
            
            return null;
        } catch (Exception $e) {
            if (DEBUG_MODE) {
                error_log("Error getting report: " . $e->getMessage());
            }
            return null;
        }
    }
    
    /**
     * Count reports by collection and status
     */
    public function countReports(string $collection, ?string $status = null): int {
        $count = count_reports($collection, $status);
        return $count ?? 0;
    }
    
    /**
     * Get dashboard statistics for all categories
     */
    public function getDashboardStats(bool $forceRefresh = false): array {
        $cacheKey = 'dashboard_stats_all';
        
        if (!$forceRefresh) {
            $cached = cache_get($cacheKey, 60);
            if ($cached !== null) {
                return $cached;
            }
        }
        
        $stats = [];
        $collections = array_map(fn($meta) => $meta['collection'], $this->categories);
        
        if (!empty($collections)) {
            $countResults = get_admin_stats_counts_fallback($collections);
            
            foreach ($this->categories as $slug => $meta) {
                $col = $meta['collection'];
                $stats[$slug] = [
                    'total'    => $countResults[$col]['total'] ?? 0,
                    'approved' => $countResults[$col]['approved'] ?? 0,
                    'pending'  => $countResults[$col]['pending'] ?? 0,
                    'declined' => $countResults[$col]['declined'] ?? 0,
                    'responded' => $countResults[$col]['responded'] ?? 0,
                ];
            }
        }
        
        cache_set($cacheKey, $stats, 60);
        return $stats;
    }
    
    /**
     * Search reports across collections
     */
    public function searchReports(string $query, array $collections = [], int $limit = 50): array {
        $results = [];
        $searchCollections = empty($collections) ? array_keys($this->categories) : $collections;
        
        foreach ($searchCollections as $slug) {
            if (!isset($this->categories[$slug])) continue;
            
            $collection = $this->categories[$slug]['collection'];
            $reports = $this->getLatestReports($collection, $limit, false);
            
            foreach ($reports as $report) {
                $searchIn = strtolower(implode(' ', [
                    $report['fullName'] ?? '',
                    $report['location'] ?? '',
                    $report['purpose'] ?? '',
                    $report['contact'] ?? ''
                ]));
                
                if (stripos($searchIn, $query) !== false) {
                    $report['category'] = $slug;
                    $results[] = $report;
                }
            }
        }
        
        return array_slice($results, 0, $limit);
    }
}
