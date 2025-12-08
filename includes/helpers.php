<?php
/**
 * Helper Functions
 * Common utility functions used across the application
 */

// Prevent direct access
if (!defined('DEFAULT_PAGE_SIZE')) {
    define('DEFAULT_PAGE_SIZE', 50);
}

/**
 * List latest reports from a collection with caching
 */
function list_latest_reports(string $collection, int $limit = 20, bool $useCache = true): array {
    // Enforce maximum limit
    $limit = min($limit, DEFAULT_PAGE_SIZE);
    
    // Try cache first
    if ($useCache) {
        $cacheKey = "reports_{$collection}_{$limit}";
        $cached = cache_get($cacheKey, 30); // 30-second cache
        if ($cached !== null) {
            return $cached;
        }
    }
    
    $items = [];
    if (function_exists('firestore_query_latest')) {
        try {
            $docs = firestore_query_latest($collection, $limit);
            foreach ($docs as $d) {
                $item = [
                    'id'         => $d['_id'] ?? '',
                    'fullName'   => $d['fullName'] ?? $d['reporterName'] ?? $d['name'] ?? '',
                    'contact'    => $d['contact'] ?? $d['reporterContact'] ?? $d['phone'] ?? '',
                    'location'   => $d['location'] ?? $d['address'] ?? '',
                    'purpose'    => $d['purpose'] ?? $d['description'] ?? '',
                    'status'     => $d['status'] ?? '',
                    'priority'   => $d['priority'] ?? '',
                    'imageUrl'   => $d['imageUrl'] ?? '',
                    'timestamp'  => $d['timestamp'] ?? $d['createdAt'] ?? null,
                    'reporterId' => $d['reporterId'] ?? $d['uid'] ?? '',
                    '_created'   => $d['_created'] ?? null,
                ];
                $items[] = $item;
            }
        } catch (Throwable $e) {
            if (DEBUG_MODE) {
                error_log("Error in firestore_query_latest for {$collection}: " . $e->getMessage());
            }
        }
    }
    // Fallback to REST API if the specific function doesn't exist
    if (count($items) < $limit) {
        // Reduced from 200 to limit * 2 for better performance
        $fetchLimit = min($limit * 2, 50);
        $raw = rest_list_documents($collection, $fetchLimit);
        foreach ($raw as $doc) {
            if (!isset($doc['name'])) continue;
            $parts = explode('/', $doc['name']);
            $id = end($parts);
            $fields = isset($doc['fields']) && function_exists('firestore_decode_fields')
                ? firestore_decode_fields($doc['fields'])
                : [];
            $item = [
                'id'         => $id,
                'fullName'   => $fields['fullName'] ?? '',
                'contact'    => $fields['contact'] ?? '',
                'location'   => $fields['location'] ?? '',
                'purpose'    => $fields['purpose'] ?? $fields['description'] ?? '',
                'status'     => $fields['status'] ?? '',
                'priority'   => $fields['priority'] ?? '',
                'imageUrl'   => $fields['imageUrl'] ?? '',
                'timestamp'  => $fields['timestamp'] ?? ($doc['createTime'] ?? null),
                'reporterId' => $fields['reporterId'] ?? '',
                '_created'   => $doc['createTime'] ?? null,
            ];
            $items[] = $item;
        }
        usort($items, function($a, $b) {
            $ta = $a['timestamp'] ?? $a['_created'] ?? '';
            $tb = $b['timestamp'] ?? $b['_created'] ?? '';
            return strcmp((string)$tb, (string)$ta);
        });
        $seen = [];
        $dedup = [];
        foreach ($items as $it) {
            if (isset($seen[$it['id']])) continue;
            $seen[$it['id']] = true;
            $dedup[] = $it;
            if (count($dedup) >= $limit) break;
        }
        $items = $dedup;
    }
    
    // Cache the results before returning
    if ($useCache && !empty($items)) {
        $cacheKey = "reports_{$collection}_{$limit}";
        cache_set($cacheKey, $items, 30); // 30-second cache
    }
    
    return $items;
}

/**
 * Fetches documents from a Firestore collection using a basic REST query
 */
function rest_list_documents(string $collection, int $pageSize = 200): array {
    if (!function_exists('firestore_rest_request') || !function_exists('firestore_base_url')) return [];
    $url = firestore_base_url().'/'.rawurlencode($collection).'?pageSize='.$pageSize;
    try {
        $res = firestore_rest_request('GET', $url);
        $docs = $res['documents'] ?? [];
        return is_array($docs) ? $docs : [];
    } catch (Throwable $e) { return []; }
}

/**
 * Build recent feed (simple version for admin dashboard)
 */
function build_recent_feed(array $categories, int $limit = 10): array {
    $recent = [];
    
    foreach ($categories as $slug => $meta) {
        $reports = list_latest_reports($meta['collection'], $limit, true);
        foreach ($reports as $report) {
            $recent[] = array_merge($report, ['categorySlug' => $slug]);
        }
    }
    
    // Sort by timestamp descending
    usort($recent, function($a, $b) {
        $timeA = $a['timestamp'] ?? '';
        $timeB = $b['timestamp'] ?? '';
        
        // Handle different timestamp formats
        if (is_array($timeA) && isset($timeA['_seconds'])) {
            $secondsA = $timeA['_seconds'];
        } elseif (is_string($timeA)) {
            $secondsA = strtotime($timeA);
        } else {
            $secondsA = 0;
        }
        
        if (is_array($timeB) && isset($timeB['_seconds'])) {
            $secondsB = $timeB['_seconds'];
        } elseif (is_string($timeB)) {
            $secondsB = strtotime($timeB);
        } else {
            $secondsB = 0;
        }
        
        return $secondsB - $secondsA;
    });
    
    return array_slice($recent, 0, $limit);
}

/**
 * Build recent feed optimized with filtering
 */
function build_recent_feed_optimized(array $categories, string $categoryFilter, string $statusFilter, string $search, int $perCategoryLimit = 10): array {
    $recent = [];
    
    // Determine which categories to fetch
    $categoriesToFetch = [];
    if ($categoryFilter === 'all') {
        $categoriesToFetch = $categories;
    } else {
        $categoriesToFetch = isset($categories[$categoryFilter]) ? [$categoryFilter => $categories[$categoryFilter]] : [];
    }
    
    if (empty($categoriesToFetch)) {
        return [];
    }
    
    foreach ($categoriesToFetch as $slug => $meta) {
        try {
            $items = get_recent_reports_optimized($meta['collection'], $perCategoryLimit, $statusFilter, $search);
            
            foreach ($items as $it) {
                $ts = $it['timestamp'] ?? ($it['_created'] ?? '');
                $recent[] = [
                    'slug'         => $slug,
                    'label'        => $meta['label'],
                    'icon'         => $meta['icon'],
                    'iconSvg'      => svg_icon($meta['icon'], 'w-5 h-5'),
                    'color'        => $meta['color'],
                    'id'           => $it['id'] ?? '',
                    'fullName'     => $it['fullName'] ?? $it['reporterName'] ?? '',
                    'contact'      => $it['contact'] ?? $it['reporterContact'] ?? '',
                    'mobileNumber' => $it['mobileNumber'] ?? $it['contact'] ?? $it['reporterContact'] ?? '',
                    'location'     => $it['location'] ?? '',
                    'purpose'      => $it['purpose'] ?? $it['description'] ?? '',
                    'reporterId'   => $it['reporterId'] ?? '',
                    'imageUrl'     => $it['imageUrl'] ?? '',
                    'status'       => $it['status'] ?? 'Pending',
                    'priority'     => $it['priority'] ?? '',
                    'lat'          => $it['latitude'] ?? ($it['coordinates']['latitude'] ?? null),
                    'lng'          => $it['longitude'] ?? ($it['coordinates']['longitude'] ?? null),
                    'timestamp'    => $ts,
                    'tsDisplay'    => fmt_ts($ts),
                    'collection'   => $meta['collection'],
                ];
            }
        } catch (Exception $e) {
            error_log("Error fetching from collection {$meta['collection']}: " . $e->getMessage());
        }
    }
    
    // Sort by priority first, then timestamp
    usort($recent, function($a, $b) {
        $aUrgent = ($a['priority'] ?? '') === 'HIGH';
        $bUrgent = ($b['priority'] ?? '') === 'HIGH';
        
        if ($aUrgent && !$bUrgent) return -1;
        if (!$aUrgent && $bUrgent) return 1;
        
        return strcmp((string)($b['timestamp'] ?? ''), (string)($a['timestamp'] ?? ''));
    });
    
    return $recent;
}

/**
 * Optimized function to get recent reports with filtering
 */
function get_recent_reports_optimized(string $collection, int $limit, string $statusFilter, string $search): array {
    try {
        $url = firestore_base_url() . ':runQuery';
        $body = [
            'structuredQuery' => [
                'from' => [['collectionId' => $collection]],
                'limit' => $limit * 2
            ]
        ];
        
        if ($statusFilter !== 'all') {
            $body['structuredQuery']['where'] = [
                'fieldFilter' => [
                    'field' => ['fieldPath' => 'status'],
                    'op' => 'EQUAL',
                    'value' => firestore_encode_value(ucfirst($statusFilter))
                ]
            ];
        }
        
        $response = firestore_rest_request('POST', $url, $body);
        $items = [];
        
        if (is_array($response)) {
            foreach ($response as $row) {
                if (!isset($row['document'])) continue;
                $doc = $row['document'];
                $itemData = firestore_decode_fields($doc['fields'] ?? []);
                $itemData['id'] = basename($doc['name'] ?? '');
                $itemData['_created'] = $doc['createTime'] ?? null;
                
                // Apply search filter
                if ($search) {
                    $searchableText = strtolower(
                        ($itemData['fullName'] ?? $itemData['reporterName'] ?? '') . ' ' .
                        ($itemData['location'] ?? '') . ' ' .
                        ($itemData['purpose'] ?? $itemData['description'] ?? '') . ' ' .
                        ($itemData['contact'] ?? $itemData['reporterContact'] ?? '')
                    );
                    if (strpos($searchableText, strtolower($search)) === false) {
                        continue;
                    }
                }
                
                $items[] = $itemData;
                
                if (count($items) >= $limit) {
                    break;
                }
            }
        }
        
        return $items;
    } catch (Exception $e) {
        error_log("Error in get_recent_reports_optimized: " . $e->getMessage());
        return [];
    }
}

/**
 * Format timestamp for display
 */
function fmt_ts($ts): string {
    if ($ts instanceof \Google\Cloud\Core\Timestamp) {
        try {
            $timestamp = $ts->get();
            if ($timestamp instanceof DateTime) {
                return $timestamp->setTimezone(new DateTimeZone('Asia/Manila'))->format('M j, Y g:i A');
            }
            return '';
        } catch (Throwable $e) { return ''; }
    }
    if (is_string($ts)) {
        try {
            $dateTime = new DateTimeImmutable($ts);
            return $dateTime->setTimezone(new DateTimeZone('Asia/Manila'))->format('M j, Y g:i A');
        } catch (Throwable $e) { return htmlspecialchars($ts); }
    }
    return '';
}

/**
 * Generate SVG icons
 */
function svg_icon(string $name, string $class = 'w-6 h-6') {
    $icons = [
        'shield-halved' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.286zm0 13.036h.008v.017h-.008v-.017z" />',
        'fire-flame-curved' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15.362 5.214A8.252 8.252 0 0112 21 8.25 8.25 0 016.038 7.048 8.287 8.287 0 009 9.6a8.983 8.983 0 013.362-6.867 8.268 8.268 0 013 2.481z" /><path stroke-linecap="round" stroke-linejoin="round" d="M12 18a3.75 3.75 0 00.495-7.467 5.99 5.99 0 00-1.925 3.546 5.974 5.974 0 01-2.133-1.001A3.75 3.75 0 0012 18z" />',
        'kit-medical' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />',
        'car-burst' => '<path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.125-.504 1.125-1.125V14.25m-17.25 4.5v-1.875a3.375 3.375 0 003.375-3.375h1.5a1.125 1.125 0 011.125 1.125v-1.5a3.375 3.375 0 00-3.375-3.375H9.75V7.5h1.5a3.375 3.375 0 013.375 3.375v1.5a1.125 1.125 0 001.125 1.125h1.5a3.375 3.375 0 003.375-3.375V7.5a1.125 1.125 0 00-1.125-1.125H5.625" />',
        'house-tsunami' => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h7.5" />',
        'handcuffs' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />',
        'spinner' => '<path d="M21 12a9 9 0 11-6.219-8.56" />',
    ];
    
    $path = $icons[$name] ?? $icons['spinner'];
    return '<svg class="'.$class.'" fill="none" stroke="currentColor" viewBox="0 0 24 24">'.$path.'</svg>';
}
