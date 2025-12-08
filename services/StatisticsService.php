<?php
/**
 * Statistics Service
 * Handles all analytics and statistics calculations
 */

class StatisticsService {
    private $categories;
    
    public function __construct(array $categories) {
        $this->categories = $categories;
    }
    
    /**
     * Get comprehensive dashboard statistics
     */
    public function getDashboardStats(bool $forceRefresh = false): array {
        $cacheKey = 'dashboard_stats_comprehensive';
        
        if (!$forceRefresh) {
            $cached = cache_get($cacheKey, 60);
            if ($cached !== null) {
                return $cached;
            }
        }
        
        $stats = [
            'reports' => $this->getReportStats($forceRefresh),
            'users' => $this->getUserStats($forceRefresh),
            'activity' => $this->getActivityStats($forceRefresh),
            'response' => $this->getResponseStats($forceRefresh),
        ];
        
        cache_set($cacheKey, $stats, 60);
        return $stats;
    }
    
    /**
     * Get report statistics by category and status
     */
    public function getReportStats(bool $forceRefresh = false): array {
        $cacheKey = 'stats_reports';
        
        if (!$forceRefresh) {
            $cached = cache_get($cacheKey, 60);
            if ($cached !== null) {
                return $cached;
            }
        }
        
        $stats = [
            'total' => 0,
            'byCategory' => [],
            'byStatus' => [
                'pending' => 0,
                'approved' => 0,
                'responded' => 0,
                'declined' => 0,
            ],
        ];
        
        $collections = array_map(fn($meta) => $meta['collection'], $this->categories);
        
        if (!empty($collections)) {
            $countResults = get_admin_stats_counts_fallback($collections);
            
            foreach ($this->categories as $slug => $meta) {
                $col = $meta['collection'];
                $categoryStats = [
                    'total' => $countResults[$col]['total'] ?? 0,
                    'approved' => $countResults[$col]['approved'] ?? 0,
                    'pending' => $countResults[$col]['pending'] ?? 0,
                    'declined' => $countResults[$col]['declined'] ?? 0,
                    'responded' => $countResults[$col]['responded'] ?? 0,
                ];
                
                $stats['byCategory'][$slug] = $categoryStats;
                $stats['total'] += $categoryStats['total'];
                $stats['byStatus']['pending'] += $categoryStats['pending'];
                $stats['byStatus']['approved'] += $categoryStats['approved'];
                $stats['byStatus']['responded'] += $categoryStats['responded'];
                $stats['byStatus']['declined'] += $categoryStats['declined'];
            }
        }
        
        cache_set($cacheKey, $stats, 60);
        return $stats;
    }
    
    /**
     * Get user statistics
     */
    public function getUserStats(bool $forceRefresh = false): array {
        $cacheKey = 'stats_users';
        
        if (!$forceRefresh) {
            $cached = cache_get($cacheKey, 120);
            if ($cached !== null) {
                return $cached;
            }
        }
        
        $stats = [
            'total' => 0,
            'verified' => 0,
            'pending' => 0,
            'active' => 0,
        ];
        
        try {
            $auth = initialize_auth();
            $users = $auth->listUsers($defaultMaxResults = 1000);
            
            foreach ($users as $user) {
                $stats['total']++;
                
                $isVerified = ($user->customClaims['isVerified'] ?? false);
                if ($isVerified) {
                    $stats['verified']++;
                } else {
                    $stats['pending']++;
                }
                
                // Consider active if logged in within last 30 days
                if ($user->metadata->lastLoginAt) {
                    $lastLogin = $user->metadata->lastLoginAt->getTimestamp();
                    if (time() - $lastLogin < (30 * 86400)) {
                        $stats['active']++;
                    }
                }
            }
        } catch (Exception $e) {
            if (DEBUG_MODE) {
                error_log("Error fetching user stats: " . $e->getMessage());
            }
        }
        
        cache_set($cacheKey, $stats, 120);
        return $stats;
    }
    
    /**
     * Get activity statistics (reports over time)
     */
    public function getActivityStats(bool $forceRefresh = false): array {
        $cacheKey = 'stats_activity';
        
        if (!$forceRefresh) {
            $cached = cache_get($cacheKey, 300); // 5 min cache
            if ($cached !== null) {
                return $cached;
            }
        }
        
        $stats = [
            'today' => 0,
            'thisWeek' => 0,
            'thisMonth' => 0,
            'hourly' => array_fill(0, 24, 0),
        ];
        
        $now = time();
        $todayStart = strtotime('today');
        $weekStart = strtotime('monday this week');
        $monthStart = strtotime('first day of this month');
        
        foreach ($this->categories as $meta) {
            $reports = list_latest_reports($meta['collection'], 100, false);
            
            foreach ($reports as $report) {
                $timestamp = $report['timestamp'] ?? 0;
                
                // Handle different timestamp formats
                if (is_array($timestamp) && isset($timestamp['_seconds'])) {
                    $timestamp = $timestamp['_seconds'];
                } elseif (is_string($timestamp)) {
                    $converted = strtotime($timestamp);
                    $timestamp = ($converted !== false) ? $converted : 0;
                } elseif (!is_numeric($timestamp)) {
                    $timestamp = 0;
                }
                
                $timestamp = (int)$timestamp;
                
                // Skip invalid timestamps
                if ($timestamp <= 0) {
                    continue;
                }
                
                if ($timestamp >= $todayStart) {
                    $stats['today']++;
                    $hour = (int)date('G', $timestamp);
                    $stats['hourly'][$hour]++;
                }
                
                if ($timestamp >= $weekStart) {
                    $stats['thisWeek']++;
                }
                
                if ($timestamp >= $monthStart) {
                    $stats['thisMonth']++;
                }
            }
        }
        
        cache_set($cacheKey, $stats, 300);
        return $stats;
    }
    
    /**
     * Get response time statistics
     */
    public function getResponseStats(bool $forceRefresh = false): array {
        $cacheKey = 'stats_response';
        
        if (!$forceRefresh) {
            $cached = cache_get($cacheKey, 300);
            if ($cached !== null) {
                return $cached;
            }
        }
        
        $stats = [
            'averageResponseTime' => 0,
            'fastestResponse' => PHP_INT_MAX,
            'slowestResponse' => 0,
            'totalResponded' => 0,
        ];
        
        $responseTimes = [];
        
        foreach ($this->categories as $meta) {
            $reports = list_latest_reports($meta['collection'], 100, false);
            
            foreach ($reports as $report) {
                if (($report['status'] ?? '') === 'responded' && isset($report['responseTime'])) {
                    $responseTime = $report['responseTime'];
                    $responseTimes[] = $responseTime;
                    $stats['totalResponded']++;
                    
                    if ($responseTime < $stats['fastestResponse']) {
                        $stats['fastestResponse'] = $responseTime;
                    }
                    
                    if ($responseTime > $stats['slowestResponse']) {
                        $stats['slowestResponse'] = $responseTime;
                    }
                }
            }
        }
        
        if (!empty($responseTimes)) {
            $stats['averageResponseTime'] = array_sum($responseTimes) / count($responseTimes);
        }
        
        // Convert to minutes
        $stats['averageResponseTime'] = round($stats['averageResponseTime'] / 60, 1);
        $stats['fastestResponse'] = $stats['fastestResponse'] === PHP_INT_MAX ? 0 : round($stats['fastestResponse'] / 60, 1);
        $stats['slowestResponse'] = round($stats['slowestResponse'] / 60, 1);
        
        cache_set($cacheKey, $stats, 300);
        return $stats;
    }
    
    /**
     * Get chart data for analytics
     */
    public function getChartData(string $type = 'daily', int $days = 7): array {
        $cacheKey = "chart_data_{$type}_{$days}";
        
        $cached = cache_get($cacheKey, 600); // 10 min cache
        if ($cached !== null) {
            return $cached;
        }
        
        $chartData = [
            'labels' => [],
            'datasets' => []
        ];
        
        if ($type === 'daily') {
            // Generate last N days
            for ($i = $days - 1; $i >= 0; $i--) {
                $date = date('M d', strtotime("-{$i} days"));
                $chartData['labels'][] = $date;
            }
            
            // Get counts per category per day
            foreach ($this->categories as $slug => $meta) {
                $dataset = [
                    'label' => ucfirst($slug),
                    'data' => array_fill(0, $days, 0)
                ];
                
                $reports = list_latest_reports($meta['collection'], 200, false);
                
                for ($i = 0; $i < $days; $i++) {
                    $dayStart = strtotime("-{$i} days midnight");
                    $dayEnd = $dayStart + 86400;
                    
                    $count = 0;
                    foreach ($reports as $report) {
                        $timestamp = $report['timestamp'] ?? 0;
                        if ($timestamp >= $dayStart && $timestamp < $dayEnd) {
                            $count++;
                        }
                    }
                    
                    $dataset['data'][$days - 1 - $i] = $count;
                }
                
                $chartData['datasets'][] = $dataset;
            }
        }
        
        cache_set($cacheKey, $chartData, 600);
        return $chartData;
    }
}
