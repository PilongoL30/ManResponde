<?php
ob_start(); // Start output buffering immediately to catch any stray output

// Prevent browser caching of dashboard page to ensure latest JS/HTML
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$__reqStart = microtime(true);

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/includes/performance.php';
require_once __DIR__ . '/includes/helpers.php';

// Session is already started in db_config.php via config.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Defer notification/FCM systems; load only for actions that need them.

// CSRF Protection for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Skip CSRF for specific actions that come from external sources
    $skipCsrf = ['firebase_webhook', 'external_api'];
    $action = $_POST['api_action'] ?? $_GET['action'] ?? '';
    
    if (!in_array($action, $skipCsrf)) {
        if (!csrf_verify_token()) {
            http_response_code(403);
            
            // Log CSRF failure for debugging
            if (DEBUG_MODE) {
                error_log("CSRF validation failed for action: {$action}, token provided: " . (isset($_POST[CSRF_TOKEN_NAME]) ? 'yes' : 'no'));
            }
            
            if (!empty($_POST['api_action']) || isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                // AJAX request
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'CSRF token validation failed. Please refresh the page and try again.',
                    'action' => $action
                ]);
                exit;
            } else {
                // Regular form submission
                die('CSRF token validation failed. Please go back and try again.');
            }
        }
    }
}

if (defined('DEBUG_MODE') && DEBUG_MODE) {
    $v = $_GET['view'] ?? 'dashboard';
    $a = $_POST['api_action'] ?? ($_GET['action'] ?? '');
    error_log('[perf] dashboard.php start view=' . $v . ' action=' . $a . ' sid=' . session_id());
}

// Using Kreait Firebase SDK exceptions
use Kreait\Firebase\Exception\Auth\UserNotFound;
use Kreait\Firebase\Exception\Auth\EmailExists;

// --- HELPER FUNCTIONS ---
/**
 * Lists the latest reports from a specific Firestore collection.
 *
 * @param string $collection The name of the Firestore collection.
 * @param int $limit The maximum number of documents to retrieve.
 * @param bool $useCache Whether to use caching (default: true).
 * @return array An array of report documents.
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
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
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
 * Fetches documents from a Firestore collection using a basic REST query.
 *
 * @param string $collection The collection ID.
 * @param int $pageSize The number of documents to return.
 * @return array
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
 * Fetches a user's profile document by their UID.
 *
 * @param string $uid The user ID.
 * @return array The user's profile data.
 */
function get_user_profile(string $uid): array {
    // Session-level cache (fastest)
    if (session_status() !== PHP_SESSION_NONE) {
        $k = '__user_profile_' . $uid;
        $kt = $k . '_time';
        if (isset($_SESSION[$k], $_SESSION[$kt]) && (time() - (int)$_SESSION[$kt]) < 300) {
            return is_array($_SESSION[$k]) ? $_SESSION[$k] : [];
        }
    }

    // File cache (fast across requests)
    $cacheKey = 'user_profile_' . $uid;
    $cached = cache_get($cacheKey, 300);
    if (is_array($cached)) {
        if (session_status() !== PHP_SESSION_NONE) {
            $_SESSION[$k] = $cached;
            $_SESSION[$kt] = time();
        }
        return $cached;
    }

    if (function_exists('firestore_get_doc_by_id')) {
        try {
            $data = firestore_get_doc_by_id('users', $uid) ?? [];
            if (is_array($data)) {
                cache_set($cacheKey, $data, 300);
                if (session_status() !== PHP_SESSION_NONE) {
                    $_SESSION[$k] = $data;
                    $_SESSION[$kt] = time();
                }
            }
            return $data;
        } catch (Throwable $e) {}
    }
    global $firestore;
    if ($firestore) {
        try {
            $snap = $firestore->collection('users')->document($uid)->snapshot();
            $data = $snap->exists() ? ($snap->data() ?? []) : [];
            if (is_array($data)) {
                cache_set($cacheKey, $data, 300);
                if (session_status() !== PHP_SESSION_NONE) {
                    $_SESSION[$k] = $data;
                    $_SESSION[$kt] = time();
                }
            }
            return $data;
        } catch (Throwable $e) {}
    }
    return [];
}

/**
 * Get user's full name by UID. Uses caching to avoid repeated lookups.
 */
function get_user_name_by_id(string $uid): string {
    if (empty($uid)) return '';
    
    // Check static cache first (within same request)
    static $nameCache = [];
    if (isset($nameCache[$uid])) {
        return $nameCache[$uid];
    }
    
    // Try to get from user profile
    $profile = get_user_profile($uid);
    $name = $profile['fullName'] ?? $profile['name'] ?? $profile['displayName'] ?? '';
    
    // Cache the result
    $nameCache[$uid] = $name;
    
    return $name;
}

/**
 * Format ISO timestamp to human-readable format.
 * Example: "Dec 14, 2025, 10:30 PM"
 */
function fmt_action_time($ts): string {
    if (empty($ts)) return '';
    
    try {
        // Handle ISO 8601 format
        if (is_string($ts)) {
            $dt = new DateTime($ts);
        } elseif (is_array($ts) && isset($ts['_seconds'])) {
            $dt = new DateTime('@' . $ts['_seconds']);
        } elseif (is_array($ts) && isset($ts['seconds'])) {
            $dt = new DateTime('@' . $ts['seconds']);
        } else {
            return '';
        }
        
        // Set timezone to Asia/Manila (Philippines)
        $dt->setTimezone(new DateTimeZone('Asia/Manila'));
        
        // Format: "Dec 14, 2025, 10:30 PM"
        return $dt->format('M j, Y, g:i A');
    } catch (Throwable $e) {
        return '';
    }
}

// --- SESSION & ROLE MANAGEMENT ---
if (!isset($_SESSION['user_id'])) {
                header('Location: login.php');
    exit();
}

$userId   = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? 'staff';
$userName = $_SESSION['user_fullname'] ?? 'User';
$isAdmin  = ($userRole === 'admin');

// Fetch user profile/categories (prefer session to keep navigation fast)
$userCategories = $_SESSION['user_categories'] ?? [];
if (!is_array($userCategories)) $userCategories = [];

// Only fetch profile if we don't have categories in session
$userProfile = ['categories' => $userCategories, 'assignedBarangay' => ($_SESSION['assignedBarangay'] ?? '')];
if (empty($userCategories)) {
    $userProfile = get_user_profile($userId);
    $userCategories = $userProfile['categories'] ?? [];
    if (is_array($userCategories)) {
        $_SESSION['user_categories'] = $userCategories;
    }
}
// Check if user has 'tanod' category (case-insensitive check)
$isTanod = false;
if (!empty($userCategories)) {
    foreach ($userCategories as $cat) {
        if (strtolower($cat) === 'tanod') {
            $isTanod = true;
            break;
        }
    }
}

// Determine current view
$view = $_GET['view'] ?? 'dashboard';

// Define allowed views based on role
$allowedViews = ['dashboard', 'analytics'];
if ($isAdmin) {
    $allowedViews[] = 'create-account';
    $allowedViews[] = 'verify-users';
} elseif ($isTanod) {
    $allowedViews[] = 'verify-users';
}

// Validate view
if (!in_array($view, $allowedViews)) {
    $view = 'dashboard';
}

if ($view === 'analytics') {
    header('Location: analytics.php');
    exit();
}

// --- CATEGORY CONFIGURATION ---
$categories = [
    'ambulance' => ['label' => 'Ambulance', 'collection' => 'ambulance_reports', 'icon' => 'truck', 'color' => 'blue'],
    'police'    => ['label' => 'Police',    'collection' => 'police_reports',    'icon' => 'user-shield', 'color' => 'slate'],
    'tanod'     => ['label' => 'Tanod',     'collection' => 'tanod_reports',     'icon' => 'shield-check', 'color' => 'sky'],
    'fire'      => ['label' => 'Fire',      'collection' => 'fire_reports',      'icon' => 'fire', 'color' => 'red'],
    'flood'     => ['label' => 'Flood',     'collection' => 'flood_reports',     'icon' => 'home', 'color' => 'indigo'],
    'other'     => ['label' => 'Other',     'collection' => 'other_reports',     'icon' => 'question-mark-circle', 'color' => 'gray'],
];

// --- LAZY FIRESTORE INITIALIZATION (optimized for performance) ---
$firestore = null;
// Don't initialize Firebase on every page load - only when needed via AJAX
// This significantly improves page load times

// --- PRE-LOAD RECENT FEED (server-side, eliminates loading spinner) ---
$__preloadedFeed = null;
if ($isAdmin && $view === 'dashboard') {
    try {
        $cacheKey = 'recent_feed_' . md5('' . 'all' . 'all'); // matches AJAX handler: md5($search . $category . $status)
        $__preloadedFeed = cache_get($cacheKey, 15);
        if ($__preloadedFeed === null) {
            $__preloadedFeed = empty($categories) ? [] : build_recent_feed_ultra_fast_runquery($categories, 'all', 'all', '', 10);
            cache_set($cacheKey, $__preloadedFeed, 15);
        }
    } catch (Throwable $e) {
        $__preloadedFeed = null;
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log('[perf] preload recent feed failed: ' . $e->getMessage());
        }
    }
}



// Build the recent activity feed (server-side utility)
function build_recent_feed(array $categories): array {
    $recentFeed = [];
    foreach ($categories as $slug => $meta) {
        $items = list_latest_reports($meta['collection'], 5);
        foreach ($items as $it) {
            $recentFeed[] = [
                'slug'       => $slug,
                'label'      => $meta['label'],
                'icon'       => $meta['icon'],
                'color'      => $meta['color'],
                'id'         => $it['id'] ?? '',
                'fullName'   => $it['fullName'] ?? $it['reporterName'] ?? '',
                'contact'    => $it['contact'] ?? $it['reporterContact'] ?? '',
                'location'   => $it['location'] ?? '',
                'purpose'    => $it['purpose'] ?? $it['description'] ?? '',
                'reporterId' => $it['reporterId'] ?? '',
                'imageUrl'   => $it['imageUrl'] ?? '',
                'status'     => $it['status'] ?? 'Pending',
                'priority'   => $it['priority'] ?? '',
                'timestamp'  => $it['timestamp'] ?? ($it['_created'] ?? ''),
            ];
        }
    }
    // Sort by priority first (urgent reports first), then by timestamp (newest first)
    usort($recentFeed, function($a, $b) {
        $aUrgent = ($a['priority'] ?? '') === 'HIGH';
        $bUrgent = ($b['priority'] ?? '') === 'HIGH';
        
        if ($aUrgent && !$bUrgent) return -1;
        if (!$aUrgent && $bUrgent) return 1;
        
        // If both have same priority, sort by timestamp (newest first)
        return strcmp((string)($b['timestamp'] ?? ''), (string)($a['timestamp'] ?? ''));
    });
    return array_slice($recentFeed, 0, 10);
}

// Build the full recent activity feed (for pagination)
function build_recent_feed_all(array $categories, int $perCategoryLimit = 200): array {
    $recent = [];
    foreach ($categories as $slug => $meta) {
        $items = list_latest_reports($meta['collection'], $perCategoryLimit);
        foreach ($items as $it) {
            $ts = $it['timestamp'] ?? ($it['_created'] ?? '');
            $recent[] = [
                'slug'       => $slug,
                'label'      => $meta['label'],
                'icon'       => $meta['icon'],
                'iconSvg'    => svg_icon($meta['icon'], 'w-5 h-5'),
                'color'      => $meta['color'],
                'id'         => $it['id'] ?? '',
                'fullName'   => $it['fullName'] ?? $it['reporterName'] ?? '',
                'contact'    => $it['contact'] ?? $it['reporterContact'] ?? '',
                'location'   => $it['location'] ?? '',
                'purpose'    => $it['purpose'] ?? $it['description'] ?? '',
                'reporterId' => $it['reporterId'] ?? '',
                'imageUrl'   => $it['imageUrl'] ?? '',
                'status'     => $it['status'] ?? 'Pending',
                'priority'   => $it['priority'] ?? '',
                'timestamp'  => $ts,
                'tsDisplay'  => fmt_ts($ts),
                'collection' => $meta['collection'],
            ];
        }
    }
    // Sort by priority first (urgent reports first), then by timestamp (newest first)
    usort($recent, function($a, $b) {
        $aUrgent = ($a['priority'] ?? '') === 'HIGH';
        $bUrgent = ($b['priority'] ?? '') === 'HIGH';
        
        if ($aUrgent && !$bUrgent) return -1;
        if (!$aUrgent && $bUrgent) return 1;
        
        // If both have same priority, sort by timestamp (newest first)
        return strcmp((string)($b['timestamp'] ?? ''), (string)($a['timestamp'] ?? ''));
    });
    return $recent;
}

// Optimized recent feed builder with smart filtering
function build_recent_feed_optimized(array $categories, string $categoryFilter, string $statusFilter, string $search, int $perCategoryLimit = 10): array {
    $recent = [];
    
    // Determine which categories to fetch based on filter
    $categoriesToFetch = [];
    if ($categoryFilter === 'all') {
        $categoriesToFetch = $categories;
    } else {
        $categoriesToFetch = isset($categories[$categoryFilter]) ? [$categoryFilter => $categories[$categoryFilter]] : [];
    }
    
    // If no categories match, return empty
    if (empty($categoriesToFetch)) {
        return [];
    }
    
    foreach ($categoriesToFetch as $slug => $meta) {
        try {
            // Use REST API directly for better performance
            $items = get_recent_reports_optimized($meta['collection'], $perCategoryLimit, $statusFilter, $search);
            
            foreach ($items as $it) {
                $ts = $it['timestamp'] ?? ($it['createdAt'] ?? ($it['_created'] ?? ''));
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
                    // Provide both legacy (lat/lng) and UI-expected (latitude/longitude) fields
                    'lat'          => ($it['latitude'] ?: ($it['lat'] ?: ($it['coordinates']['latitude'] ?: ($it['coordinates']['lat'] ?: null)))),
                    'lng'          => ($it['longitude'] ?: ($it['lng'] ?: ($it['coordinates']['longitude'] ?: ($it['coordinates']['lng'] ?: null)))),
                    'latitude'     => ($it['latitude'] ?: ($it['lat'] ?: ($it['coordinates']['latitude'] ?: ($it['coordinates']['lat'] ?: null)))),
                    'longitude'    => ($it['longitude'] ?: ($it['lng'] ?: ($it['coordinates']['longitude'] ?: ($it['coordinates']['lng'] ?: null)))),
                    'timestamp'    => $ts,
                    'tsDisplay'    => fmt_ts($ts),
                    'collection'   => $meta['collection'],
                ];
            }
        } catch (Exception $e) {
            // Log error but continue with other categories
            error_log("Error fetching from collection {$meta['collection']}: " . $e->getMessage());
        }
    }
    
    // Sort by priority first (urgent reports first), then by timestamp (newest first)
    usort($recent, function($a, $b) {
        $aUrgent = ($a['priority'] ?? '') === 'HIGH';
        $bUrgent = ($b['priority'] ?? '') === 'HIGH';
        
        if ($aUrgent && !$bUrgent) return -1;
        if (!$aUrgent && $bUrgent) return 1;
        
        // If both have same priority, sort by time (newest first)
        $ta = $a['timestamp'] ?? ($a['createdAt'] ?? '');
        $tb = $b['timestamp'] ?? ($b['createdAt'] ?? '');

        $toEpoch = function($t): int {
            if (is_array($t)) {
                if (isset($t['_seconds']) && is_numeric($t['_seconds'])) return (int)$t['_seconds'];
                if (isset($t['seconds']) && is_numeric($t['seconds'])) return (int)$t['seconds'];
                return 0;
            }
            if (is_int($t)) return $t;
            if (is_float($t)) return (int)$t;
            if (is_string($t)) {
                $s = strtotime($t);
                return $s === false ? 0 : (int)$s;
            }
            return 0;
        };

        return $toEpoch($tb) <=> $toEpoch($ta);
    });
    
    return $recent;
}

// Ultra-fast recent feed builder using parallel requests
function build_recent_feed_ultra_fast(array $categories, string $categoryFilter, string $statusFilter, string $search, int $perCategoryLimit = 15): array {
    $categoriesToFetch = [];
    if ($categoryFilter === 'all') {
        $categoriesToFetch = $categories;
    } else {
        $categoriesToFetch = isset($categories[$categoryFilter]) ? [$categoryFilter => $categories[$categoryFilter]] : [];
    }
    
    if (empty($categoriesToFetch)) {
        return [];
    }
    
    // Use parallel curl for all category requests
    $multiHandle = curl_multi_init();
    $curlHandles = [];
    $requestMap = [];
    
    foreach ($categoriesToFetch as $slug => $meta) {
        $url = firestore_base_url() . ':runQuery';
        $body = [
            'structuredQuery' => [
                'from' => [['collectionId' => $meta['collection']]],
                'orderBy' => [[
                    'field' => ['fieldPath' => 'timestamp'],
                    'direction' => 'DESCENDING',
                ]],
                'limit' => $perCategoryLimit,
            ]
        ];
        
        // Add status filter if specified
        if ($statusFilter !== 'all') {
            $body['structuredQuery']['where'] = [
                'fieldFilter' => [
                    'field' => ['fieldPath' => 'status'],
                    'op' => 'EQUAL',
                    'value' => ['stringValue' => ucfirst($statusFilter)]
                ]
            ];
        }
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . get_firebase_access_token()
            ],
            CURLOPT_TIMEOUT => 3 // Very short timeout for speed
        ]);
        
        curl_multi_add_handle($multiHandle, $ch);
        $curlHandles[] = $ch;
        $requestMap[] = ['slug' => $slug, 'meta' => $meta];
    }
    
    // Execute all requests in parallel
    $running = null;
    do {
        curl_multi_exec($multiHandle, $running);
        curl_multi_select($multiHandle);
    } while ($running > 0);
    
    // Process all results
    $recent = [];
    foreach ($curlHandles as $idx => $ch) {
        $response = curl_multi_getcontent($ch);
        $slug = $requestMap[$idx]['slug'];
        $meta = $requestMap[$idx]['meta'];
        
        try {
            $data = json_decode($response, true);
            if (is_array($data)) {
                foreach ($data as $row) {
                    if (!isset($row['document'])) continue;
                    
                    $doc = $row['document'];
                    $docData = firestore_decode_fields($doc['fields'] ?? []);
                    $name = $doc['name'] ?? '';
                    $docId = $name ? basename($name) : '';
                    
                    // Apply search filter if specified
                    if (!empty($search)) {
                        $searchText = strtolower($search);
                        $fullName = strtolower($docData['fullName'] ?? $docData['reporterName'] ?? '');
                        $location = strtolower($docData['location'] ?? '');
                        $purpose = strtolower($docData['purpose'] ?? $docData['description'] ?? '');
                        
                        if (strpos($fullName, $searchText) === false && 
                            strpos($location, $searchText) === false && 
                            strpos($purpose, $searchText) === false) {
                            continue;
                        }
                    }
                    
                    $ts = $docData['timestamp'] ?? ($docData['_created'] ?? '');
                    $recent[] = [
                        'slug' => $slug,
                        'label' => $meta['label'],
                        'icon' => $meta['icon'],
                        'iconSvg' => svg_icon($meta['icon'], 'w-5 h-5'),
                        'color' => $meta['color'],
                        'id' => $docId,
                        'fullName' => $docData['fullName'] ?? $docData['reporterName'] ?? '',
                        'contact' => $docData['contact'] ?? $docData['reporterContact'] ?? '',
                        'mobileNumber' => $docData['mobileNumber'] ?? $docData['contact'] ?? $docData['reporterContact'] ?? '',
                        'location' => $docData['location'] ?? '',
                        'purpose' => $docData['purpose'] ?? $docData['description'] ?? '',
                        'reporterId' => $docData['reporterId'] ?? '',
                        'imageUrl' => $docData['imageUrl'] ?? '',
                        'status' => $docData['status'] ?? 'Pending',
                        'priority' => $docData['priority'] ?? '',
                        'timestamp' => $ts,
                        'tsDisplay' => fmt_ts($ts),
                        'updatedBy' => $docData['updatedBy'] ?? '',
                        'updatedAt' => $docData['updatedAt'] ?? '',
                        'collection' => $meta['collection'],
                    ];
                }
            }
        } catch (Exception $e) {
            error_log("Error processing recent feed for {$slug}: " . $e->getMessage());
        }
        
        curl_multi_remove_handle($multiHandle, $ch);
        curl_close($ch);
    }
    
    curl_multi_close($multiHandle);
    
    // Sort by priority and timestamp
    usort($recent, function($a, $b) {
        $aUrgent = ($a['priority'] ?? '') === 'HIGH';
        $bUrgent = ($b['priority'] ?? '') === 'HIGH';
        
        if ($aUrgent && !$bUrgent) return -1;
        if (!$aUrgent && $bUrgent) return 1;
        
        return strcmp((string)($b['timestamp'] ?? ''), (string)($a['timestamp'] ?? ''));
    });
    
    return $recent;
}

// Ultra-fast recent feed builder using parallel LIST documents (createTime-based).
// This avoids timestamp index issues and is much faster under load.
function build_recent_feed_ultra_fast_listdocs(array $categories, string $categoryFilter, string $statusFilter, string $search, int $perCategoryLimit = 15): array {
    $categoriesToFetch = [];
    if ($categoryFilter === 'all') {
        $categoriesToFetch = $categories;
    } else {
        $categoriesToFetch = isset($categories[$categoryFilter]) ? [$categoryFilter => $categories[$categoryFilter]] : [];
    }
    if (empty($categoriesToFetch)) return [];

    $searchNeedle = trim(strtolower((string)$search));
    $statusNeedle = strtolower(trim((string)$statusFilter));

    // Keep list small; this endpoint is called frequently.
    $pageSize = (int)min(max($perCategoryLimit * 5, 40), 80);

    $token = firestore_rest_token();
    $base = firestore_base_url();

    $toEpoch = function($t): int {
        if (is_array($t)) {
            if (isset($t['_seconds']) && is_numeric($t['_seconds'])) return (int)$t['_seconds'];
            if (isset($t['seconds']) && is_numeric($t['seconds'])) return (int)$t['seconds'];
            return 0;
        }
        if (is_int($t)) return $t;
        if (is_float($t)) return (int)$t;
        if (is_string($t) && $t !== '') {
            $s = strtotime($t);
            return $s === false ? 0 : (int)$s;
        }
        return 0;
    };

    $mh = curl_multi_init();
    $handles = [];
    $map = [];

    foreach ($categoriesToFetch as $slug => $meta) {
        $collection = $meta['collection'];
        $url = $base . '/' . rawurlencode($collection) . '?pageSize=' . $pageSize;
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_SSL_VERIFYPEER => SSL_VERIFY,
            CURLOPT_SSL_VERIFYHOST => SSL_VERIFY ? 2 : 0,
        ]);
        curl_multi_add_handle($mh, $ch);
        $handles[] = $ch;
        $map[(int)$ch] = ['slug' => $slug, 'meta' => $meta, 'collection' => $collection];
    }

    $running = null;
    do {
        curl_multi_exec($mh, $running);
        curl_multi_select($mh, 0.2);
    } while ($running > 0);

    $recent = [];
    foreach ($handles as $ch) {
        $info = $map[(int)$ch] ?? null;
        $raw = curl_multi_getcontent($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
        if (!$info || $http < 200 || $http >= 300) {
            continue;
        }

        $json = json_decode($raw ?: 'null', true);
        $docs = is_array($json) ? ($json['documents'] ?? []) : [];
        if (!is_array($docs)) continue;

        foreach ($docs as $doc) {
            if (!isset($doc['name'])) continue;
            $docId = basename($doc['name']);
            $fields = isset($doc['fields']) ? firestore_decode_fields($doc['fields']) : [];
            if (!is_array($fields)) $fields = [];

            // Local status filter (case-insensitive)
            if ($statusNeedle !== 'all') {
                $st = strtolower(trim((string)($fields['status'] ?? '')));
                if ($st !== $statusNeedle) continue;
            }

            // Local search filter
            if ($searchNeedle !== '') {
                $searchableText = strtolower(
                    ($fields['fullName'] ?? $fields['reporterName'] ?? '') . ' ' .
                    ($fields['location'] ?? '') . ' ' .
                    ($fields['purpose'] ?? $fields['description'] ?? '') . ' ' .
                    ($fields['contact'] ?? $fields['reporterContact'] ?? '')
                );
                if (strpos($searchableText, $searchNeedle) === false) continue;
            }

            $ts = $fields['timestamp'] ?? ($fields['createdAt'] ?? ($doc['createTime'] ?? null));
            
            // Resolve approver/decliner/responder names from user IDs
            $approvedById = $fields['approvedBy'] ?? $fields['updatedBy'] ?? '';
            $declinedById = $fields['declinedBy'] ?? '';
            $respondedById = $fields['respondedBy'] ?? '';
            
            // Get names - prefer stored name, fallback to lookup by ID
            $approvedByName = $fields['approvedByName'] ?? '';
            if (empty($approvedByName) && !empty($approvedById)) {
                $approvedByName = get_user_name_by_id($approvedById);
            }
            
            $declinedByName = $fields['declinedByName'] ?? '';
            if (empty($declinedByName) && !empty($declinedById)) {
                $declinedByName = get_user_name_by_id($declinedById);
            }
            
            $respondedByName = $fields['respondedByName'] ?? '';
            if (empty($respondedByName) && !empty($respondedById)) {
                $respondedByName = get_user_name_by_id($respondedById);
            }
            
            // For approved status, also check updatedBy as fallback
            $status = strtolower($fields['status'] ?? '');
            if ($status === 'approved' && empty($approvedByName)) {
                $updatedById = $fields['updatedBy'] ?? '';
                if (!empty($updatedById)) {
                    $approvedByName = get_user_name_by_id($updatedById);
                }
            }
            if ($status === 'declined' && empty($declinedByName)) {
                $updatedById = $fields['updatedBy'] ?? '';
                if (!empty($updatedById)) {
                    $declinedByName = get_user_name_by_id($updatedById);
                }
            }
            
            $recent[] = [
                'slug'         => $info['slug'],
                'label'        => $info['meta']['label'],
                'icon'         => $info['meta']['icon'],
                'iconSvg'      => svg_icon($info['meta']['icon'], 'w-5 h-5'),
                'color'        => $info['meta']['color'],
                'id'           => $docId,
                'fullName'     => $fields['fullName'] ?? $fields['reporterName'] ?? '',
                'contact'      => $fields['contact'] ?? $fields['reporterContact'] ?? '',
                'mobileNumber' => $fields['mobileNumber'] ?? $fields['contact'] ?? $fields['reporterContact'] ?? '',
                'location'     => $fields['location'] ?? '',
                'purpose'      => $fields['purpose'] ?? $fields['description'] ?? '',
                'reporterId'   => $fields['reporterId'] ?? ($fields['uid'] ?? ''),
                'imageUrl'     => $fields['imageUrl'] ?? '',
                'status'       => $fields['status'] ?? 'Pending',
                'priority'     => $fields['priority'] ?? '',
                'lat'          => $fields['latitude'] ?? ($fields['coordinates']['latitude'] ?? null),
                'lng'          => $fields['longitude'] ?? ($fields['coordinates']['longitude'] ?? null),
                'latitude'     => $fields['latitude'] ?? ($fields['coordinates']['latitude'] ?? null),
                'longitude'    => $fields['longitude'] ?? ($fields['coordinates']['longitude'] ?? null),
                'timestamp'    => $ts,
                'tsDisplay'    => fmt_ts($ts),
                'updatedBy'    => $fields['updatedBy'] ?? '',
                'updatedAt'    => fmt_action_time($fields['updatedAt'] ?? ''),
                'approvedBy'   => $approvedById,
                'approvedByName' => $approvedByName,
                'approvedAt'   => fmt_action_time($fields['approvedAt'] ?? $fields['updatedAt'] ?? ''),
                'declinedBy'   => $declinedById,
                'declinedByName' => $declinedByName,
                'declinedAt'   => fmt_action_time($fields['declinedAt'] ?? $fields['updatedAt'] ?? ''),
                'respondedBy'  => $respondedById,
                'respondedByName' => $respondedByName,
                'respondedAt'  => fmt_action_time($fields['respondedAt'] ?? ''),
                '_created'     => $doc['createTime'] ?? null,
                'collection'   => $info['collection'],
            ];
        }
    }

    curl_multi_close($mh);

    // Sort by priority then time (newest first)
    usort($recent, function($a, $b) use ($toEpoch) {
        $aUrgent = ($a['priority'] ?? '') === 'HIGH';
        $bUrgent = ($b['priority'] ?? '') === 'HIGH';
        if ($aUrgent && !$bUrgent) return -1;
        if (!$aUrgent && $bUrgent) return 1;

        $ta = $a['timestamp'] ?? ($a['_created'] ?? '');
        $tb = $b['timestamp'] ?? ($b['_created'] ?? '');
        return $toEpoch($tb) <=> $toEpoch($ta);
    });

    // Keep payload bounded
    $max = min(max($perCategoryLimit * 8, 60), 180);
    if (count($recent) > $max) {
        $recent = array_slice($recent, 0, $max);
    }

    return $recent;
}

// Ultra-fast recent feed builder using parallel RunQuery (ORDERED).
// This is reliable for "newest" because list-documents is not ordered.
function build_recent_feed_ultra_fast_runquery(array $categories, string $categoryFilter, string $statusFilter, string $search, int $perCategoryLimit = 10): array {
    if (!function_exists('curl_multi_init') || !function_exists('curl_init')) {
        return build_recent_feed_optimized($categories, $categoryFilter, $statusFilter, $search, $perCategoryLimit);
    }
    if (!function_exists('firestore_rest_token') || !function_exists('firestore_base_url')) {
        return build_recent_feed_optimized($categories, $categoryFilter, $statusFilter, $search, $perCategoryLimit);
    }
    $categoriesToFetch = [];
    if ($categoryFilter === 'all') {
        $categoriesToFetch = $categories;
    } else {
        $categoriesToFetch = isset($categories[$categoryFilter]) ? [$categoryFilter => $categories[$categoryFilter]] : [];
    }
    if (empty($categoriesToFetch)) return [];

    $searchNeedle = trim(strtolower((string)$search));
    $statusNeedle = strtolower(trim((string)$statusFilter));

    $toEpoch = function($t): int {
        if (is_array($t)) {
            if (isset($t['_seconds']) && is_numeric($t['_seconds'])) return (int)$t['_seconds'];
            if (isset($t['seconds']) && is_numeric($t['seconds'])) return (int)$t['seconds'];
            return 0;
        }
        if (is_int($t)) return $t;
        if (is_float($t)) return (int)$t;
        if (is_string($t) && $t !== '') {
            $s = strtotime($t);
            return $s === false ? 0 : (int)$s;
        }
        return 0;
    };

    // Pull a slightly larger window so docs missing timestamp fields (which sort last)
    // still have a chance to be included and backfilled.
    $pageSize = (int)min(max($perCategoryLimit * 4, 35), 80);
    $nullLimit = (int)min(max($perCategoryLimit * 2, 20), 60);

    $token = firestore_rest_token();
    $runQueryUrl = firestore_base_url() . ':runQuery';

    $selectFields = [
        ['fieldPath' => 'fullName'],
        ['fieldPath' => 'reporterName'],
        ['fieldPath' => 'contact'],
        ['fieldPath' => 'reporterContact'],
        ['fieldPath' => 'mobileNumber'],
        ['fieldPath' => 'location'],
        ['fieldPath' => 'purpose'],
        ['fieldPath' => 'description'],
        ['fieldPath' => 'status'],
        ['fieldPath' => 'priority'],
        ['fieldPath' => 'latitude'],
        ['fieldPath' => 'longitude'],
        ['fieldPath' => 'coordinates'],
        ['fieldPath' => 'reporterId'],
        ['fieldPath' => 'uid'],
        ['fieldPath' => 'imageUrl'],
        ['fieldPath' => 'timestamp'],
        ['fieldPath' => 'createdAt'],
        ['fieldPath' => 'updatedBy'],
        ['fieldPath' => 'updatedAt'],
        ['fieldPath' => 'approvedBy'],
        ['fieldPath' => 'approvedByName'],
        ['fieldPath' => 'approvedAt'],
        ['fieldPath' => 'declinedBy'],
        ['fieldPath' => 'declinedByName'],
        ['fieldPath' => 'declinedAt'],
        ['fieldPath' => 'respondedBy'],
        ['fieldPath' => 'respondedByName'],
        ['fieldPath' => 'respondedAt'],
    ];

    $mh = curl_multi_init();
    $handles = [];

    foreach ($categoriesToFetch as $slug => $meta) {
        $collection = $meta['collection'];
        foreach (['timestamp', 'createdAt'] as $orderField) {
            $body = [
                'structuredQuery' => [
                    'from' => [['collectionId' => $collection]],
                    'select' => ['fields' => $selectFields],
                    'orderBy' => [[
                        'field' => ['fieldPath' => $orderField],
                        'direction' => 'DESCENDING',
                    ]],
                    'limit' => $pageSize,
                ]
            ];
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $runQueryUrl,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($body),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $token,
                    'Accept: application/json',
                    'Content-Type: application/json',
                ],
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_SSL_VERIFYPEER => SSL_VERIFY,
                CURLOPT_SSL_VERIFYHOST => SSL_VERIFY ? 2 : 0,
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[] = ['ch' => $ch, 'slug' => $slug, 'meta' => $meta, 'collection' => $collection];
        }

        // NOTE: Null-field queries removed for performance (saved 12 HTTP requests).
        // Documents without timestamp fields are rare and will appear on next sync.
    }

    $running = null;
    do {
        curl_multi_exec($mh, $running);
        curl_multi_select($mh, 0.2);
    } while ($running > 0);

    $recentByKey = [];
    $backfills = [];

    foreach ($handles as $h) {
        $ch = $h['ch'];
        $raw = curl_multi_getcontent($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
        if ($http < 200 || $http >= 300) continue;

        $rows = json_decode($raw ?: 'null', true);
        if (!is_array($rows)) continue;

        foreach ($rows as $row) {
            if (!isset($row['document'])) continue;
            $doc = $row['document'];
            if (!isset($doc['name'])) continue;
            $docId = basename($doc['name']);

            $fields = isset($doc['fields']) ? firestore_decode_fields($doc['fields']) : [];
            if (!is_array($fields)) $fields = [];

            // Local status filter (case-insensitive)
            if ($statusNeedle !== 'all') {
                $st = strtolower(trim((string)($fields['status'] ?? '')));
                if ($st !== $statusNeedle) continue;
            }

            // Local search filter
            if ($searchNeedle !== '') {
                $searchableText = strtolower(
                    ($fields['fullName'] ?? $fields['reporterName'] ?? '') . ' ' .
                    ($fields['location'] ?? '') . ' ' .
                    ($fields['purpose'] ?? $fields['description'] ?? '') . ' ' .
                    ($fields['contact'] ?? $fields['reporterContact'] ?? '')
                );
                if (strpos($searchableText, $searchNeedle) === false) continue;
            }

            $ts = $fields['timestamp'] ?? ($fields['createdAt'] ?? ($doc['createTime'] ?? null));
            $epoch = $toEpoch($ts);
            if ($epoch <= 0 && isset($doc['createTime'])) {
                $epoch = $toEpoch($doc['createTime']);
            }

            // Collect candidates for backfilling missing timestamp fields using createTime.
            if (isset($doc['createTime'])) {
                $missingTs = !isset($fields['timestamp']) || $fields['timestamp'] === null || $fields['timestamp'] === '';
                $missingCreated = !isset($fields['createdAt']) || $fields['createdAt'] === null || $fields['createdAt'] === '';
                if ($missingTs || $missingCreated) {
                    $backfills[] = [
                        'collection' => $h['collection'],
                        'id' => $docId,
                        'createTime' => (string)$doc['createTime'],
                        'epoch' => $toEpoch($doc['createTime']),
                    ];
                }
            }

            $key = $h['collection'] . ':' . $docId;
            $existingEpoch = (int)($recentByKey[$key]['_epoch'] ?? 0);
            if ($epoch <= $existingEpoch) {
                continue;
            }

            // Resolve approver/decliner/responder names from user IDs
            $approvedById = $fields['approvedBy'] ?? $fields['updatedBy'] ?? '';
            $declinedById = $fields['declinedBy'] ?? '';
            $respondedById = $fields['respondedBy'] ?? '';
            
            // Get names - prefer stored name, fallback to lookup by ID
            $approvedByName = $fields['approvedByName'] ?? '';
            if (empty($approvedByName) && !empty($approvedById)) {
                $approvedByName = get_user_name_by_id($approvedById);
            }
            
            $declinedByName = $fields['declinedByName'] ?? '';
            if (empty($declinedByName) && !empty($declinedById)) {
                $declinedByName = get_user_name_by_id($declinedById);
            }
            
            $respondedByName = $fields['respondedByName'] ?? '';
            if (empty($respondedByName) && !empty($respondedById)) {
                $respondedByName = get_user_name_by_id($respondedById);
            }
            
            // For approved status, also check updatedBy as fallback
            $status = strtolower($fields['status'] ?? '');
            if ($status === 'approved' && empty($approvedByName)) {
                $updatedById = $fields['updatedBy'] ?? '';
                if (!empty($updatedById)) {
                    $approvedByName = get_user_name_by_id($updatedById);
                }
            }
            if ($status === 'declined' && empty($declinedByName)) {
                $updatedById = $fields['updatedBy'] ?? '';
                if (!empty($updatedById)) {
                    $declinedByName = get_user_name_by_id($updatedById);
                }
            }

            $recentByKey[$key] = [
                'slug'         => $h['slug'],
                'label'        => $h['meta']['label'],
                'icon'         => $h['meta']['icon'],
                'iconSvg'      => svg_icon($h['meta']['icon'], 'w-5 h-5'),
                'color'        => $h['meta']['color'],
                'id'           => $docId,
                'fullName'     => $fields['fullName'] ?? $fields['reporterName'] ?? '',
                'contact'      => $fields['contact'] ?? $fields['reporterContact'] ?? '',
                'mobileNumber' => $fields['mobileNumber'] ?? $fields['contact'] ?? $fields['reporterContact'] ?? '',
                'location'     => $fields['location'] ?? '',
                'purpose'      => $fields['purpose'] ?? $fields['description'] ?? '',
                'reporterId'   => $fields['reporterId'] ?? ($fields['uid'] ?? ''),
                'imageUrl'     => $fields['imageUrl'] ?? '',
                'status'       => $fields['status'] ?? 'Pending',
                'priority'     => $fields['priority'] ?? '',
                'lat'          => $fields['latitude'] ?? ($fields['coordinates']['latitude'] ?? null),
                'lng'          => $fields['longitude'] ?? ($fields['coordinates']['longitude'] ?? null),
                'latitude'     => $fields['latitude'] ?? ($fields['coordinates']['latitude'] ?? null),
                'longitude'    => $fields['longitude'] ?? ($fields['coordinates']['longitude'] ?? null),
                'timestamp'    => $ts,
                'tsDisplay'    => fmt_ts($ts),
                'updatedBy'    => $fields['updatedBy'] ?? '',
                'updatedAt'    => fmt_action_time($fields['updatedAt'] ?? ''),
                'approvedBy'   => $approvedById,
                'approvedByName' => $approvedByName,
                'approvedAt'   => fmt_action_time($fields['approvedAt'] ?? $fields['updatedAt'] ?? ''),
                'declinedBy'   => $declinedById,
                'declinedByName' => $declinedByName,
                'declinedAt'   => fmt_action_time($fields['declinedAt'] ?? $fields['updatedAt'] ?? ''),
                'respondedBy'  => $respondedById,
                'respondedByName' => $respondedByName,
                'respondedAt'  => fmt_action_time($fields['respondedAt'] ?? ''),
                '_created'     => $doc['createTime'] ?? null,
                '_epoch'       => $epoch,
                'collection'   => $h['collection'],
            ];
        }
    }

    curl_multi_close($mh);

    // Backfill only a handful of the newest missing-timestamp docs per request.
    // This helps make ordered queries reliable without turning the feed into a repair job.
    if (!empty($backfills)) {
        usort($backfills, function($a, $b) {
            return ((int)($b['epoch'] ?? 0)) <=> ((int)($a['epoch'] ?? 0));
        });
        $backfills = array_slice($backfills, 0, 6);

        $seenBackfill = [];
        foreach ($backfills as $bf) {
            $k = ($bf['collection'] ?? '') . ':' . ($bf['id'] ?? '');
            if (isset($seenBackfill[$k])) continue;
            $seenBackfill[$k] = true;
            try {
                $dt = new DateTimeImmutable($bf['createTime']);
                firestore_set_document($bf['collection'], $bf['id'], [
                    'timestamp' => $dt,
                    'createdAt' => $dt,
                ]);
            } catch (Throwable $e) {
                // ignore backfill failures
            }
        }
    }

    $recent = array_values($recentByKey);

    usort($recent, function($a, $b) {
        $aUrgent = ($a['priority'] ?? '') === 'HIGH';
        $bUrgent = ($b['priority'] ?? '') === 'HIGH';
        if ($aUrgent && !$bUrgent) return -1;
        if (!$aUrgent && $bUrgent) return 1;
        return ((int)($b['_epoch'] ?? 0)) <=> ((int)($a['_epoch'] ?? 0));
    });

    $max = min(max($perCategoryLimit * 8, 60), 180);
    if (count($recent) > $max) {
        $recent = array_slice($recent, 0, $max);
    }

    // Remove internal sort key
    foreach ($recent as &$it) {
        unset($it['_epoch']);
    }
    unset($it);

    return $recent;
}

// Optimized function to get recent reports with filtering
function get_recent_reports_optimized(string $collection, int $limit, string $statusFilter, string $search): array {
    try {
        $url = firestore_base_url() . ':runQuery';
        // Fetch a larger window then sort locally so we don't miss new reports
        // when some documents have missing/inconsistent timestamp fields.
        $fetchLimit = min(max($limit * 6, 40), 120);

        $bodyTs = [
            'structuredQuery' => [
                'from' => [['collectionId' => $collection]],
                'orderBy' => [[
                    'field' => ['fieldPath' => 'timestamp'],
                    'direction' => 'DESCENDING'
                ]],
                'limit' => $fetchLimit
            ]
        ];

        $bodyCreated = [
            'structuredQuery' => [
                'from' => [['collectionId' => $collection]],
                'orderBy' => [[
                    'field' => ['fieldPath' => 'createdAt'],
                    'direction' => 'DESCENDING'
                ]],
                'limit' => $fetchLimit
            ]
        ];

        $statusNeedle = strtolower(trim((string)$statusFilter));

        // Run both ordered queries (timestamp + createdAt) and merge.
        // If either fails (index/field issues), we still keep the other.
        $respTs = [];
        $respCreated = [];
        try { $respTs = firestore_rest_request('POST', $url, $bodyTs); }
        catch (Exception $e) { error_log("Recent query (timestamp) failed for {$collection}: " . $e->getMessage()); }
        try { $respCreated = firestore_rest_request('POST', $url, $bodyCreated); }
        catch (Exception $e) { error_log("Recent query (createdAt) failed for {$collection}: " . $e->getMessage()); }

        // If both fail, fallback to an unordered limited fetch.
        if (empty($respTs) && empty($respCreated)) {
            $fallbackBody = ['structuredQuery' => ['from' => [['collectionId' => $collection]], 'limit' => $fetchLimit]];
            $respTs = firestore_rest_request('POST', $url, $fallbackBody);
        }

        $items = [];
        $seen = [];

        $consume = function(array $response) use (&$items, &$seen, $collection, $search, $limit, $statusNeedle) {
            if (!is_array($response)) return;
            foreach ($response as $row) {
                if (!isset($row['document'])) continue;
                $doc = $row['document'];
                $itemData = firestore_decode_fields($doc['fields'] ?? []);
                $itemData['id'] = basename($doc['name'] ?? '');
                $itemData['_created'] = $doc['createTime'] ?? null;

                if (!$itemData['id'] || isset($seen[$itemData['id']])) continue;
                $seen[$itemData['id']] = true;

                // Apply search filter if specified
                if ($search) {
                    $searchableText = strtolower(
                        ($itemData['fullName'] ?? $itemData['reporterName'] ?? '') . ' ' .
                        ($itemData['location'] ?? '') . ' ' .
                        ($itemData['purpose'] ?? $itemData['description'] ?? '') . ' ' .
                        ($itemData['contact'] ?? $itemData['reporterContact'] ?? '')
                    );
                    if (strpos($searchableText, $search) === false) {
                        continue;
                    }
                }

                // Apply status filter locally (case-insensitive) to avoid missing docs
                // where status values vary in casing (e.g., "pending" vs "Pending").
                if ($statusNeedle !== 'all') {
                    $st = strtolower(trim((string)($itemData['status'] ?? '')));
                    if ($st !== $statusNeedle) {
                        continue;
                    }
                }

                // Debug: Log purpose field for tanod reports
                if ($collection === 'tanod_reports') {
                    error_log("Tanod report purpose debug (optimized) - ID: {$itemData['id']}, Purpose: '" . ($itemData['purpose'] ?? '') . "', Raw description: " . ($itemData['description'] ?? 'NULL'));
                }

                $items[] = $itemData;
                if (count($items) >= ($limit * 3)) {
                    // keep a cap; we'll sort/slice later
                    return;
                }
            }
        };

        $consume($respTs);
        $consume($respCreated);

        // Extra robustness: merge in a small list-documents sample (createTime-based).
        // This helps surface newly created reports that are missing/invalid timestamp fields,
        // which otherwise sort last in the ordered queries and may not appear in the window.
        try {
            $rawDocs = rest_list_documents($collection, min(max($fetchLimit, 60), 200));
            foreach ($rawDocs as $doc) {
                if (!isset($doc['name'])) continue;
                $id = basename($doc['name']);
                if (!$id || isset($seen[$id])) continue;

                $fields = isset($doc['fields']) && function_exists('firestore_decode_fields')
                    ? firestore_decode_fields($doc['fields'])
                    : [];

                $itemData = is_array($fields) ? $fields : [];
                $itemData['id'] = $id;
                $itemData['_created'] = $doc['createTime'] ?? null;

                // Apply search filter if specified
                if ($search) {
                    $searchableText = strtolower(
                        ($itemData['fullName'] ?? $itemData['reporterName'] ?? '') . ' ' .
                        ($itemData['location'] ?? '') . ' ' .
                        ($itemData['purpose'] ?? $itemData['description'] ?? '') . ' ' .
                        ($itemData['contact'] ?? $itemData['reporterContact'] ?? '')
                    );
                    if (strpos($searchableText, $search) === false) {
                        continue;
                    }
                }

                // Apply status filter if specified
                if ($statusFilter !== 'all') {
                    $st = strtolower((string)($itemData['status'] ?? ''));
                    if ($st !== strtolower($statusFilter)) {
                        continue;
                    }
                }

                $seen[$id] = true;
                $items[] = $itemData;

                if (count($items) >= ($limit * 4)) {
                    break;
                }
            }
        } catch (Throwable $e) {
            // ignore list-documents fallback errors
        }
        
        // Ensure newest-first ordering even when using fallback / mixed timestamp formats.
        // Prefer explicit timestamp; fallback to createdAt; then Firestore createTime.
        usort($items, function($a, $b) {
            $ta = $a['timestamp'] ?? ($a['createdAt'] ?? ($a['_created'] ?? ''));
            $tb = $b['timestamp'] ?? ($b['createdAt'] ?? ($b['_created'] ?? ''));

            $toEpoch = function($t): int {
                if (is_array($t)) {
                    if (isset($t['_seconds']) && is_numeric($t['_seconds'])) return (int)$t['_seconds'];
                    if (isset($t['seconds']) && is_numeric($t['seconds'])) return (int)$t['seconds'];
                    return 0;
                }
                if (is_int($t)) return $t;
                if (is_float($t)) return (int)$t;
                if (is_string($t)) {
                    $s = strtotime($t);
                    return $s === false ? 0 : (int)$s;
                }
                return 0;
            };

            return $toEpoch($tb) <=> $toEpoch($ta);
        });

        if (count($items) > $limit) {
            $items = array_slice($items, 0, $limit);
        }
        
    return $items;
    } catch (Exception $e) {
        error_log("Error in get_recent_reports_optimized: " . $e->getMessage());
        return [];
    }
}


# --- HELPER FUNCTIONS (Backend Logic) ---
# Note: Backend logic is preserved from the original file.

function update_report_status(string $collection, string $docId, string $newStatus, string $userId, string $userName = ''): bool {
    $newStatus = in_array($newStatus, ['Approved','Declined'], true) ? $newStatus : 'Pending';
    $now = date('c');
    $payload = [
        'status'    => $newStatus,
        'updatedAt' => $now,
        'updatedBy' => $userId,
    ];
    
    // Add approver/decliner info based on status
    if ($newStatus === 'Approved') {
        $payload['approvedBy'] = $userId;
        $payload['approvedByName'] = $userName ?: ($_SESSION['user_fullname'] ?? 'Admin');
        $payload['approvedAt'] = $now;
    } elseif ($newStatus === 'Declined') {
        $payload['declinedBy'] = $userId;
        $payload['declinedByName'] = $userName ?: ($_SESSION['user_fullname'] ?? 'Admin');
        $payload['declinedAt'] = $now;
    }
    
    error_log("Attempting to update status: {$collection}/{$docId} to {$newStatus} by user {$userId}");
    
    $updateSuccess = false;
    
    // Try the fast method first
    if (function_exists('firestore_set_document_fast')) {
        try { 
            $result = firestore_set_document_fast($collection, $docId, $payload);
            if ($result) {
                error_log("Fast Firestore update successful: {$collection}/{$docId}");
                $updateSuccess = true;
            } else {
                error_log("Fast Firestore update returned false: {$collection}/{$docId}");
            }
        } catch (Throwable $e) {
            error_log("Fast Firestore update error: " . $e->getMessage());
        }
    }
    
    // Fallback to original REST API method if fast method failed
    if (!$updateSuccess && function_exists('firestore_set_document')) {
        try { 
            $result = firestore_set_document($collection, $docId, $payload);
            if ($result) {
                error_log("REST API update successful: {$collection}/{$docId}");
                $updateSuccess = true;
            } else {
                error_log("REST API update returned false: {$collection}/{$docId}");
            }
        } catch (Throwable $e) {
            error_log("Firestore REST API error: " . $e->getMessage());
        }
    }
    
    // Final fallback to Firebase SDK if other methods failed
    if (!$updateSuccess) {
        global $firestore;
        if ($firestore) {
            try { 
                $firestore->collection($collection)->document($docId)->set($payload, ['merge' => true]); 
                error_log("Firebase SDK update successful: {$collection}/{$docId}");
                $updateSuccess = true;
            } catch (Throwable $e) {
                error_log("Firebase SDK error: " . $e->getMessage());
            }
        }
    }
    
    // Send FCM notifications only once after successful update
    if ($updateSuccess) {
        // DISABLED: Notifications are handled by other code path to avoid duplicates
        error_log("Notifications handled by alternative code path for {$collection}/{$docId}");
        return true;
    }
    
    error_log("All update methods failed for document: {$collection}/{$docId}");
    return false;
}

// New function to list pending users
function list_pending_users(int $limit = 200): array {
    global $firestore;
    if (!$firestore) {
        throw new Exception("Firestore is not initialized.");
    }
    
    // Check for both 'accountStatus' and 'status' fields for backward compatibility
    try {
        // First try with 'accountStatus' field
        $query = $firestore->collection('users')->where('accountStatus', '==', 'pending')->limit($limit);
        $documents = $query->documents();
        $users = [];
        foreach ($documents as $doc) {
            if ($doc->exists()) {
                $userData = $doc->data();
                $userData['id'] = $doc->id(); // Add the document ID
                $users[] = $userData;
            }
        }
        
        // If no users found with 'accountStatus', try with 'status' field
        if (empty($users)) {
            $query = $firestore->collection('users')->where('status', '==', 'pending')->limit($limit);
            $documents = $query->documents();
            foreach ($documents as $doc) {
                if ($doc->exists()) {
                    $userData = $doc->data();
                    $userData['id'] = $doc->id(); // Add the document ID
                    // Map 'status' to 'accountStatus' for consistency
                    $userData['accountStatus'] = $userData['status'] ?? 'pending';
                    $users[] = $userData;
                }
            }
        }
        
        return $users;
    } catch (Throwable $e) {
        // Fallback to 'status' field if 'accountStatus' query fails
        try {
            $query = $firestore->collection('users')->where('status', '==', 'pending')->limit($limit);
            $documents = $query->documents();
            $users = [];
            foreach ($documents as $doc) {
                if ($doc->exists()) {
                    $userData = $doc->data();
                    $userData['id'] = $doc->id(); // Add the document ID
                    // Map 'status' to 'accountStatus' for consistency
                    $userData['accountStatus'] = $userData['status'] ?? 'pending';
                    $users[] = $userData;
                }
            }
            return $users;
        } catch (Throwable $e2) {
            return [];
        }
    }
}

// New function to update user status
function update_user_status(string $uid, string $newStatus): bool {
    $newStatus = in_array($newStatus, ['approved', 'rejected'], true) ? $newStatus : 'pending';
    $payload = [
        'accountStatus' => $newStatus,
        'status' => $newStatus, // Update both fields for backward compatibility
        'statusUpdatedAt' => date('c'),
        'isVerified' => ($newStatus === 'approved') // Update isVerified based on status
    ];
    if (function_exists('firestore_set_document')) {
        try { firestore_set_document('users', $uid, $payload, true); return true; } catch (Throwable $e) {}
    }
    global $firestore;
    if ($firestore) {
        try { $firestore->collection('users')->document($uid)->set($payload, ['merge' => true]); return true; } catch (Throwable $e) {}
    }
    return false;
}

/* =========================
   PENDING USERS HELPERS
   ========================= */
// If not yet defined, add a proper paginated fetcher (Firestore)
if (!function_exists('list_pending_users_paginated')) {
    function list_pending_users_paginated(int $limit = 20, int $offset = 0): array {
        global $firestore;
        if (!$firestore) return [];
        try {
            // Check for both 'accountStatus' and 'status' fields for backward compatibility
            $out = [];
            
            // First try with 'accountStatus' field
            try {
                $q = $firestore->collection('users')
                    ->where('accountStatus', '==', 'pending')
                    ->orderBy('fullName')
                    ->offset($offset)
                    ->limit($limit);
                $docs = $q->documents();
                foreach ($docs as $doc) {
                    if (!$doc->exists()) continue;
                    $d = $doc->data();
                    $out[] = [
                        'id'     => $doc->id(),
                        'fullName' => $d['fullName'] ?? '',
                        'firstName' => $d['firstName'] ?? '',
                        'lastName' => $d['lastName'] ?? '',
                        'middleName' => $d['middleName'] ?? '',
                        'email' => $d['email'] ?? '',
                        'mobileNumber' => $d['mobileNumber'] ?? '',
                        'contact' => $d['contact'] ?? '', // Fallback for old data
                        'currentAddress' => $d['currentAddress'] ?? '',
                        'permanentAddress' => $d['permanentAddress'] ?? '',
                        'address' => $d['address'] ?? '', // Fallback for old data
                        'birthdate' => $d['birthdate'] ?? '',
                        'gender' => $d['gender'] ?? '',
                        'accountStatus' => $d['accountStatus'] ?? 'pending',
                        'frontIdImageUrl' => $d['frontIdImageUrl'] ?? '',
                        'backIdImageUrl' => $d['backIdImageUrl'] ?? '',
                        'selfieImageUrl' => $d['selfieImageUrl'] ?? '',
                        'proofOfResidencyPath' => $d['proofOfResidencyPath'] ?? '', // Fallback for old data
                    ];
                }
            } catch (Throwable $e1) {
                // If 'accountStatus' fails, try with 'status' field
                try {
                    $q = $firestore->collection('users')
                        ->where('status', '==', 'pending')
                        ->orderBy('fullName')
                        ->offset($offset)
                        ->limit($limit);
                    $docs = $q->documents();
                    foreach ($docs as $doc) {
                        if (!$doc->exists()) continue;
                        $d = $doc->data();
                        $out[] = [
                            'id'     => $doc->id(),
                            'fullName' => $d['fullName'] ?? '',
                            'firstName' => $d['firstName'] ?? '',
                            'lastName' => $d['lastName'] ?? '',
                            'middleName' => $d['middleName'] ?? '',
                            'email' => $d['email'] ?? '',
                            'mobileNumber' => $d['mobileNumber'] ?? '',
                            'contact' => $d['contact'] ?? '', // Fallback for old data
                            'currentAddress' => $d['currentAddress'] ?? '',
                            'permanentAddress' => $d['permanentAddress'] ?? '',
                            'address' => $d['address'] ?? '', // Fallback for old data
                            'birthdate' => $d['birthdate'] ?? '',
                            'gender' => $d['gender'] ?? '',
                            'accountStatus' => $d['status'] ?? 'pending', // Map 'status' to 'accountStatus'
                            'frontIdImageUrl' => $d['frontIdImageUrl'] ?? '',
                            'backIdImageUrl' => $d['backIdImageUrl'] ?? '',
                            'selfieImageUrl' => $d['selfieImageUrl'] ?? '',
                            'proofOfResidencyPath' => $d['proofOfResidencyPath'] ?? '', // Fallback for old data
                        ];
                    }
                } catch (Throwable $e2) {
                    return [];
                }
            }
            
            return $out;
        } catch (Throwable $e) {
            return [];
        }
    }
}

// --- AJAX/API REQUEST HANDLING ---
if (isset($_POST['api_action'])) {
    header('Content-Type: application/json');
    $action = $_POST['api_action'];
    $response = ['success' => false, 'message' => 'Invalid action.'];

    // Admin: Create Staff
    if ($isAdmin && $action === 'create_staff') {
        $lastName = $_POST['lastName'] ?? '';
        $firstName = $_POST['firstName'] ?? '';
        $middleName = $_POST['middleName'] ?? '';
        $email = $_POST['email'] ?? '';
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $categories = $_POST['categories'] ?? [];
        
        // Construct full name in the format: Last Name, First Name Middle Name
        $fullName = trim($lastName);
        if (!empty($firstName)) {
            $fullName .= ', ' . trim($firstName);
            if (!empty($middleName)) {
                $fullName .= ' ' . trim($middleName);
            }
        }
        
        if (empty($lastName) || empty($firstName) || empty($email) || empty($username) || empty($password)) {
             $response['message'] = 'Staff creation failed. Last name, first name, email, username, and password are required.';
        } else {
            try {
                // Initialize Firebase Auth
                $auth = initialize_auth();
                
                // Create user in Firebase Auth
                $userProperties = [
                    'email' => $email,
                    'password' => $password,
                    'displayName' => $fullName,
                    'emailVerified' => true
                ];
                
                $userRecord = $auth->createUser($userProperties);
                $uid = $userRecord->uid;
                
                // Create user document in Firestore
                $userData = [
                    'uid' => $uid,
                    'fullName' => $fullName,
                    'lastName' => $lastName,
                    'firstName' => $firstName,
                    'middleName' => $middleName,
                    'email' => $email,
                    'username' => $username,
                    'role' => 'staff',
                    'status' => 'approved',
                    'categories' => $categories,
                    'createdAt' => date('Y-m-d H:i:s'),
                    'createdBy' => $userId,
                    'lastLogin' => null
                ];
                
                // Add to Firestore users collection
                firestore_set_document('users', $uid, $userData);
                
                $response = ['success' => true, 'message' => "Staff account for {$fullName} created successfully."];
                // Invalidate cached dashboard data
                unset($_SESSION['__cache']['admin_stats'], $_SESSION['__cache']['recent_feed']);

                // Trigger staff data refresh on the frontend
                $response['refreshStaffData'] = true;
                
            } catch (EmailExists $e) {
                error_log("Staff creation error - Email already exists: " . $e->getMessage());
                $response['message'] = 'Staff creation failed: Email address is already registered.';
            } catch (Exception $e) {
                error_log("Staff creation error: " . $e->getMessage());
                $response['message'] = 'Staff creation failed: ' . $e->getMessage();
            }
        }
    }

    // Admin: Get Staff and Responder Data
    if ($isAdmin && $action === 'get_staff_data') {
        try {
            $url = firestore_base_url() . ':runQuery';
            $fetchUsersByRole = function(string $role) use ($url): array {
                $body = [
                    'structuredQuery' => [
                        'from' => [['collectionId' => 'users']],
                        'where' => [
                            'fieldFilter' => [
                                'field' => ['fieldPath' => 'role'],
                                'op' => 'EQUAL',
                                'value' => firestore_encode_value($role)
                            ]
                        ]
                    ]
                ];

                $response = firestore_rest_request('POST', $url, $body);
                $users = [];

                if (isset($response[0]['document'])) {
                    foreach ($response as $doc) {
                        if (!isset($doc['document'])) continue;
                        $docName = $doc['document']['name'] ?? '';
                        if ($docName === '') continue;
                        $docId = basename($docName);
                        $docData = firestore_decode_fields($doc['document']['fields'] ?? []);
                        $users[$docId] = $docData;
                    }
                }

                return $users;
            };

            $accountUsers = $fetchUsersByRole('staff');
            foreach ($fetchUsersByRole('responder') as $docId => $docData) {
                $accountUsers[$docId] = $docData;
            }

            $totalStaff = 0;
            $activeStaff = 0;
            $reportsAssigned = 0;
            $staffList = [];

            if ($accountUsers) {
                foreach ($accountUsers as $userId => $userData) {
                    $totalStaff++;

                    // Check if staff is active (not disabled/removed)
                    $normalizedStatus = strtolower((string)($userData['status'] ?? ''));
                    if ($normalizedStatus === 'approved' || $normalizedStatus === 'active') {
                        $activeStaff++;
                    }

                    // Count assigned categories
                    if (isset($userData['categories']) && is_array($userData['categories'])) {
                        $reportsAssigned += count($userData['categories']);
                    }

                    // Add to staff list
                    $staffList[] = [
                        'id' => $userId,
                        'name' => $userData['fullName'] ?? 'Unknown',
                        'email' => $userData['email'] ?? '',
                        'username' => $userData['username'] ?? '',
                        'role' => strtolower((string)($userData['role'] ?? 'staff')),
                        'status' => $userData['status'] ?? 'inactive',
                        'categories' => $userData['categories'] ?? [],
                        'createdAt' => $userData['createdAt'] ?? null,
                        'lastLogin' => $userData['lastLogin'] ?? null
                    ];
                }
            }

            usort($staffList, static function(array $left, array $right): int {
                return strcasecmp((string)($left['name'] ?? ''), (string)($right['name'] ?? ''));
            });

            $response = [
                'success' => true,
                'data' => [
                    'total' => $totalStaff,
                    'active' => $activeStaff,
                    'reportsAssigned' => $reportsAssigned,
                    'staff' => $staffList
                ]
            ];

        } catch (Exception $e) {
            error_log("Get staff data error: " . $e->getMessage());
            $response = [
                'success' => false,
                'message' => 'Failed to load staff data: ' . $e->getMessage()
            ];
        }
    }

    // Admin: Create Responder
    if ($isAdmin && $action === 'create_responder') {
        $lastName = trim($_POST['lastName'] ?? '');
        $firstName = trim($_POST['firstName'] ?? '');
        $middleName = trim($_POST['middleName'] ?? '');
        $email = $_POST['email'] ?? '';
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $categories = $_POST['categories'] ?? [];
        
        // Construct full name in "Last, First, Middle" format
        $fullName = $lastName;
        if (!empty($firstName)) {
            $fullName .= ', ' . $firstName;
        }
        if (!empty($middleName)) {
            $fullName .= ', ' . $middleName;
        }
        
        if (empty($lastName) || empty($firstName) || empty($email) || empty($username) || empty($password)) {
             $response['message'] = 'Responder creation failed. Last name, first name, email, username and password are required.';
        } else {
            try {
                // Initialize Firebase Auth
                $auth = initialize_auth();
                
                // Create user in Firebase Auth
                $userProperties = [
                    'email' => $email,
                    'password' => $password,
                    'displayName' => $fullName,
                    'emailVerified' => true
                ];
                
                $userRecord = $auth->createUser($userProperties);
                $uid = $userRecord->uid;
                
                // Create user document in Firestore
                $userData = [
                    'uid' => $uid,
                    'fullName' => $fullName,  // "Last, First, Middle" format
                    'lastName' => $lastName,
                    'firstName' => $firstName,
                    'middleName' => $middleName,
                    'email' => $email,
                    'username' => $username,
                    'role' => 'responder',
                    'status' => 'approved',
                    'categories' => $categories,
                    'createdAt' => date('Y-m-d H:i:s'),
                    'createdBy' => $userId,
                    'lastLogin' => null
                ];
                
                // Add to Firestore users collection
                firestore_set_document('users', $uid, $userData);
                
                $response = ['success' => true, 'message' => "Responder account for {$fullName} created successfully."];
                // Invalidate cached dashboard data
                unset($_SESSION['__cache']['admin_stats'], $_SESSION['__cache']['recent_feed']);
                
            } catch (EmailExists $e) {
                error_log("Responder creation error - Email already exists: " . $e->getMessage());
                $response['message'] = 'Responder creation failed: Email address is already registered.';
            } catch (Exception $e) {
                error_log("Responder creation error: " . $e->getMessage());
                $response['message'] = 'Responder creation failed: ' . $e->getMessage();
            }
        }
    }

    // Admin: Create Account (Multi-Role Support - Unified Staff/Responder Creation)
    if ($isAdmin && $action === 'create_account') {
        $lastName = trim($_POST['lastName'] ?? '');
        $firstName = trim($_POST['firstName'] ?? '');
        $middleName = trim($_POST['middleName'] ?? '');
        $email = $_POST['email'] ?? '';
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $accountTypes = $_POST['accountTypes'] ?? [];
        $categories = $_POST['categories'] ?? [];
        $assignedBarangay = $_POST['assignedBarangay'] ?? null;
        $plateNumber = trim($_POST['plateNumber'] ?? '');
        $vehicleType = trim($_POST['vehicleType'] ?? '');
        $vehicleTypeOther = trim($_POST['vehicleTypeOther'] ?? '');
        
        // Construct full name in "Last, First Middle" format
        $fullName = $lastName;
        if (!empty($firstName)) {
            $fullName .= ', ' . $firstName;
            if (!empty($middleName)) {
                $fullName .= ' ' . $middleName;
            }
        }
        
        if (empty($lastName) || empty($firstName) || empty($email) || empty($username) || empty($password)) {
             $response['message'] = 'Account creation failed. Last name, first name, email, username and password are required.';
        } elseif (empty($accountTypes)) {
            $response['message'] = 'Account creation failed. Please select at least one account type (Staff or Responder).';
        } elseif ((in_array('tanod', $categories) || in_array('police', $categories)) && empty($assignedBarangay)) {
            $response['message'] = 'Account creation failed. Assigned Barangay/Outpost is required for Tanod and Police categories.';
        } elseif (in_array('responder', $accountTypes) && (empty($plateNumber) || empty($vehicleType))) {
            $response['message'] = 'Account creation failed. Plate number and vehicle type are required for responder accounts.';
        } elseif (in_array('responder', $accountTypes) && $vehicleType === 'Others' && empty($vehicleTypeOther)) {
            $response['message'] = 'Account creation failed. Please type the custom vehicle type when Others is selected.';
        } else {
            try {
                // Initialize Firebase Auth
                $auth = initialize_auth();
                
                // Create user in Firebase Auth
                $userProperties = [
                    'email' => $email,
                    'password' => $password,
                    'displayName' => $fullName,
                    'emailVerified' => true
                ];
                
                $userRecord = $auth->createUser($userProperties);
                $uid = $userRecord->uid;
                
                // Determine primary role (if multiple selected, prefer staff over responder)
                $primaryRole = in_array('staff', $accountTypes) ? 'staff' : 'responder';
                
                // Create user document in Firestore with multi-role support
                $resolvedVehicleType = ($vehicleType === 'Others') ? $vehicleTypeOther : $vehicleType;

                $userData = [
                    'uid' => $uid,
                    'fullName' => $fullName,
                    'lastName' => $lastName,
                    'firstName' => $firstName,
                    'middleName' => $middleName,
                    'email' => $email,
                    'username' => $username,
                    'role' => $primaryRole,
                    'roles' => $accountTypes, // Store all selected roles
                    'status' => 'approved',
                    'categories' => $categories,
                    'assignedBarangay' => $assignedBarangay,
                    'plateNumber' => in_array('responder', $accountTypes) ? $plateNumber : null,
                    'vehicleType' => in_array('responder', $accountTypes) ? $resolvedVehicleType : null,
                    'vehicleTypeChoice' => in_array('responder', $accountTypes) ? $vehicleType : null,
                    'vehicleTypeOther' => in_array('responder', $accountTypes) && $vehicleType === 'Others' ? $vehicleTypeOther : null,
                    'createdAt' => date('Y-m-d H:i:s'),
                    'createdBy' => $userId,
                    'lastLogin' => null
                ];
                
                // Add to Firestore users collection
                firestore_set_document('users', $uid, $userData);
                
                $roleText = implode(' and ', array_map('ucfirst', $accountTypes));
                $response = ['success' => true, 'message' => "{$roleText} account for {$fullName} created successfully."];
                
                // Invalidate cached dashboard data
                unset($_SESSION['__cache']['admin_stats'], $_SESSION['__cache']['recent_feed']);

                // Trigger staff data refresh on the frontend
                $response['refreshStaffData'] = true;
                
            } catch (EmailExists $e) {
                error_log("Account creation error - Email already exists: " . $e->getMessage());
                $response['message'] = 'Account creation failed: Email address is already registered.';
            } catch (Exception $e) {
                error_log("Account creation error: " . $e->getMessage());
                $response['message'] = 'Account creation failed: ' . $e->getMessage();
            }
        }
    }

    // Admin: Update User Status (Approve/Reject Registration)
    if ($isAdmin && $action === 'update_user_status') {
        $uid = $_POST['uid'] ?? '';
        $newStatus = $_POST['newStatus'] ?? '';

        if (empty($uid) || !in_array($newStatus, ['approved', 'rejected'], true)) {
            $response['message'] = 'Invalid user update request.';
        } else {
            $ok = update_user_status($uid, $newStatus);
            if ($ok) {
                $response = ['success' => true, 'message' => "User registration has been ".ucfirst($newStatus)."."];
                // Notify the user about the registration decision via FCM
                if (function_exists('send_fcm_notification_to_user')) {
                    $title = ($newStatus === 'approved') ? 'Account Approved' : 'Account Rejected';
                    $body  = ($newStatus === 'approved')
                        ? 'Your registration has been approved. You can now log in.'
                        : 'Your registration was rejected. Please give a complete and corrected information.';
                    $data  = ['type' => 'registration_status', 'status' => $newStatus];
                    try { send_fcm_notification_to_user($uid, $title, $body, $data); } catch (Throwable $e) { error_log('FCM notify (registration) failed: '.$e->getMessage()); }
                }
                // Invalidate cached dashboard data when user status changes
                unset($_SESSION['__cache']['admin_stats'], $_SESSION['__cache']['recent_feed']);
            } else {
                $response['message'] = 'A server error occurred while updating the user status.';
            }
        }
    }

    // Admin: Test Notification Flow (for debugging triple notifications)
    if ($isAdmin && $action === 'test_notifications') {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');
        
        $collection = trim($_POST['collection'] ?? '');
        $docId = trim($_POST['docId'] ?? '');
        
        if (empty($collection) || empty($docId)) {
            echo json_encode([
                'success' => false,
                'message' => 'Collection and docId are required for testing'
            ]);
            exit();
        }
        
        try {
            if (function_exists('test_new_notification_flow')) {
                $testResults = test_new_notification_flow($collection, $docId);
                echo json_encode([
                    'success' => true,
                    'message' => 'Notification test completed',
                    'results' => $testResults
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Test function not available'
                ]);
            }
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Test failed: ' . $e->getMessage()
            ]);
        }
        exit();
    }

    // Admin: Clear All Cache
    if ($isAdmin && $action === 'clear_cache') {
        try {
            // Clear file-based cache
            $cleared = cache_clear();
            
            // Also cleanup expired cache files
            $expired = cache_cleanup_expired();
            
            // Get cache stats
            $stats = cache_stats();
            
            $response = [
                'success' => true, 
                'message' => "Cache cleared successfully. Removed {$cleared} files ({$expired} expired).",
                'stats' => $stats
            ];
        } catch (Exception $e) {
            error_log("Error clearing cache: " . $e->getMessage());
            $response = [
                'success' => false,
                'message' => 'Failed to clear cache: ' . $e->getMessage()
            ];
        }
        
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }



    // Staff: Update Report Status
    if ($action === 'update_status') {
        // Ensure notification + FCM helpers are available for both Approved and Declined flows
        require_once __DIR__ . '/notification_system.php';
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');
        
        $collection = trim($_POST['collection'] ?? '');
        $docId      = trim($_POST['docId'] ?? '');
        $newStatus  = trim($_POST['newStatus'] ?? '');
        $declineReason = trim($_POST['declineReason'] ?? ''); // Capture decline reason

        $profile  = get_user_profile($userId);
        $assigned = $isAdmin ? array_keys($categories) : array_values(array_filter(array_map('strval', $profile['categories'] ?? [])));
        $allowedCollections = array_map(fn($s) => $categories[$s]['collection'] ?? null, $assigned);

        if (!$isAdmin && !in_array($collection, array_filter($allowedCollections), true)) {
            echo json_encode(['success' => false, 'message' => 'Error: You are not permitted to modify this category.']);
        } elseif ($docId === '' || !in_array($newStatus, ['Approved','Declined'], true)) {
            echo json_encode(['success' => false, 'message' => 'Invalid update request.']);
        } elseif ($newStatus === 'Declined' && empty($declineReason)) {
            echo json_encode(['success' => false, 'message' => 'Decline reason is required when declining a report.']);
        } else {
            error_log("Attempting to update status: {$collection}/{$docId} to {$newStatus} by user {$userId}" . 
                     ($declineReason ? " with reason: {$declineReason}" : ""));
            
            // Get current user's name for action attribution
            $actionByName = $_SESSION['user_fullname'] ?? '';
            
            // If session doesn't have fullname, try to get it from the user profile
            if (empty($actionByName) && !empty($userId)) {
                $staffProfile = get_user_profile($userId);
                $actionByName = $staffProfile['fullName'] ?? $staffProfile['name'] ?? '';
                // Cache it in session for future use
                if (!empty($actionByName)) {
                    $_SESSION['user_fullname'] = $actionByName;
                }
            }
            
            // Final fallback
            if (empty($actionByName)) {
                $actionByName = 'Admin';
            }
            
            error_log("Action by name resolved to: {$actionByName}");
            
            $now = date('c');
            
            // Try direct Firestore update first
            $payload = [
                'status'    => $newStatus,
                'updatedAt' => $now,
                'updatedBy' => $userId,
            ];
            
            // Add approver info when approved
            if ($newStatus === 'Approved') {
                $payload['approvedBy'] = $userId;
                $payload['approvedByName'] = $actionByName;
                $payload['approvedAt'] = $now;
            }
            
            // Add decline reason and decliner info to the document if provided
            if ($newStatus === 'Declined') {
                $payload['declinedBy'] = $userId;
                $payload['declinedByName'] = $actionByName;
                $payload['declinedAt'] = $now;
                if (!empty($declineReason)) {
                    $payload['declineReason'] = $declineReason;
                }
            }
            
            $updateSuccess = false;
            
                // Test Firestore connection first
    try {
        $testToken = firestore_rest_token();
        error_log("Firestore token obtained: " . substr($testToken, 0, 20) . "...");
        
        // Test if we can read a document to verify connection
        $testUrl = firestore_base_url() . '/documents/' . $collection . '/' . $docId;
        error_log("Testing Firestore connection with URL: " . $testUrl);
        
    } catch (Exception $e) {
        error_log("Firestore token error: " . $e->getMessage());
    }
            
            // Try fast method
            if (function_exists('firestore_set_document_fast')) {
                error_log("Trying fast update method...");
                $updateSuccess = firestore_set_document_fast($collection, $docId, $payload);
                if ($updateSuccess) {
                    error_log("Fast update successful: {$collection}/{$docId}");
                } else {
                    error_log("Fast update failed: {$collection}/{$docId}");
                }
            }
            
            // Try regular method if fast failed
            if (!$updateSuccess && function_exists('firestore_set_document')) {
                error_log("Trying regular update method...");
                try {
                    $updateSuccess = firestore_set_document($collection, $docId, $payload);
                    if ($updateSuccess) {
                        error_log("Regular update successful: {$collection}/{$docId}");
                    } else {
                        error_log("Regular update failed: {$collection}/{$docId}");
                    }
                } catch (Exception $e) {
                    error_log("Regular update exception: " . $e->getMessage());
                    $updateSuccess = false;
                }
            }
            
            // Try SDK method if both failed
            if (!$updateSuccess) {
                error_log("Trying SDK update method...");
                global $firestore;
                if ($firestore) {
                    try {
                        $firestore->collection($collection)->document($docId)->set($payload, ['merge' => true]);
                        $updateSuccess = true;
                        error_log("SDK update successful: {$collection}/{$docId}");
                    } catch (Exception $e) {
                        error_log("SDK update failed: " . $e->getMessage());
                    }
                } else {
                    error_log("Firestore SDK not available");
                }
            }
            
            if ($updateSuccess) {
                error_log("Status update successful: {$collection}/{$docId} to {$newStatus}");
                
                // Send appropriate notifications based on status
                if ($newStatus === 'Approved') {
                    if (function_exists('send_emergency_notification_directly') || function_exists('send_fcm_notification_for_approved_report')) {
                        error_log("Sending approved notifications for {$collection}/{$docId}");

                        // Reporter "approved" notifications are intentionally disabled (per earlier request)
                        $userNotificationResult = true;

                        if (function_exists('send_emergency_notification_directly')) {
                            $responderNotificationResult = send_emergency_notification_directly($collection, $docId);
                        } else {
                            $responderNotificationResult = send_fcm_notification_for_approved_report($collection, $docId, false);
                        }

                        $notificationResult = $userNotificationResult || $responderNotificationResult;
                    } else {
                        error_log("FCM approved-notification functions not available");
                    }
                } elseif ($newStatus === 'Declined') {
                    if (function_exists('send_fcm_notification_to_user_for_rejected_report')) {
                        error_log("Sending declined notification for {$collection}/{$docId}" . ($declineReason ? " with reason: {$declineReason}" : ""));
                        $notificationResult = send_fcm_notification_to_user_for_rejected_report($collection, $docId, $declineReason);
                        error_log("Declined notification result: " . ($notificationResult ? 'success' : 'failed'));
                    } else {
                        error_log("Decline notification function not available");
                    }
                }
                
                // Update staff notifications when report status changes
                if (function_exists('update_notification_for_report_status')) {
                    error_log("Updating staff notifications for {$collection}/{$docId} status: {$newStatus}");
                    update_notification_for_report_status($docId, $newStatus, $collection);
                } else {
                    error_log("update_notification_for_report_status function not available");
                }
                
                // Update staff notifications when report status changes
                if (function_exists('update_notification_for_report_status')) {
                    error_log("Updating staff notifications for {$collection}/{$docId} status: {$newStatus}");
                    update_notification_for_report_status($docId, $newStatus, $collection);
                } else {
                    error_log("update_notification_for_report_status function not available");
                }
                
                $successMessage = ($newStatus === 'Declined') 
                    ? "Report has been DECLINED" . (!empty($declineReason) ? " with custom reason" : "") . ". The reporter has been notified" . (!empty($declineReason) ? " with your specific feedback" : " with instructions to resubmit with better details") . "."
                    : "Report status successfully updated to {$newStatus}.";
                
                echo json_encode(['success' => true, 'message' => $successMessage]);
                // Invalidate cached dashboard data
                unset($_SESSION['__cache']['admin_stats'], $_SESSION['__cache']['recent_feed']);
                
                // Also clear all specific recent_feed_* cache keys
                if (isset($_SESSION['__cache']) && is_array($_SESSION['__cache'])) {
                    foreach (array_keys($_SESSION['__cache']) as $key) {
                        if (strpos($key, 'recent_feed_') === 0) {
                            unset($_SESSION['__cache'][$key]);
                        }
                    }
                }
            } else {
                error_log("All update methods failed: {$collection}/{$docId} to {$newStatus}");
                echo json_encode(['success' => false, 'message' => 'Failed to update report status in database. Please check error logs.']);
            }
        }
        exit();
    }



    // Admin: Check for new pending users (real-time sync) - Enhanced detection
    if ($isAdmin && $action === 'get_new_pending_users') {
        header('Content-Type: application/json');
        
        try {
            $newUsers = [];
            $allCurrentUsers = [];
            
            // Initialize session tracking if not exists
            if (!isset($_SESSION['known_pending_users'])) {
                $_SESSION['known_pending_users'] = [];
            }
            
            global $firestore;
            if ($firestore) {
                // Get ALL current pending users with multiple field checks
                $queries = [
                    // Query with 'accountStatus' field
                    $firestore->collection('users')->where('accountStatus', '==', 'pending'),
                    // Query with 'status' field  
                    $firestore->collection('users')->where('status', '==', 'pending'),
                    // Query with 'Status' field (capitalized)
                    $firestore->collection('users')->where('Status', '==', 'pending'),
                    // Query with 'AccountStatus' field (capitalized)
                    $firestore->collection('users')->where('AccountStatus', '==', 'pending')
                ];
                
                $seenIds = [];
                foreach ($queries as $query) {
                    try {
                        $docs = $query->documents();
                        foreach ($docs as $doc) {
                            if ($doc->exists()) {
                                $userData = $doc->data();
                                $docId = $doc->id();
                                
                                // Skip if we've already seen this user in this check
                                if (isset($seenIds[$docId])) continue;
                                $seenIds[$docId] = true;
                                
                                $userData['id'] = $docId;
                                // Normalize fields for consistency - check multiple possible field names
                                $userData['accountStatus'] = $userData['accountStatus'] ?? $userData['status'] ?? $userData['Status'] ?? $userData['AccountStatus'] ?? 'pending';
                                
                                $allCurrentUsers[$docId] = $userData;
                                
                                // Check if this is a NEW user (not in previous session)
                                if (!isset($_SESSION['known_pending_users'][$docId])) {
                                    $newUsers[] = $userData;
                                    error_log("NEW USER DETECTED: " . $docId . " - " . ($userData['fullName'] ?? 'Unknown'));
                                }
                            }
                        }
                    } catch (Throwable $e) {
                        error_log("Query error: " . $e->getMessage());
                        continue;
                    }
                }
                
                // Update session with ALL current users
                $_SESSION['known_pending_users'] = $allCurrentUsers;
            }
            
            error_log("Real-time check: " . count($allCurrentUsers) . " total users, " . count($newUsers) . " new users");
            
            echo json_encode([
                'success' => true,
                'hasNew' => !empty($newUsers),
                'newUsers' => array_values($newUsers),
                'count' => count($newUsers),
                'totalPending' => count($allCurrentUsers),
                'sessionCount' => count($_SESSION['known_pending_users'] ?? []),
                'timestamp' => date('Y-m-d H:i:s'),
                'debug' => [
                    'currentUserIds' => array_keys($allCurrentUsers),
                    'sessionUserIds' => array_keys($_SESSION['known_pending_users'] ?? [])
                ]
            ]);
        } catch (Exception $e) {
            error_log('Error in get_new_pending_users: ' . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'Failed to check for new users: ' . $e->getMessage()
            ]);
        } catch (Error $e) {
            error_log('Fatal error in get_new_pending_users: ' . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'System error occurred while checking for new users'
            ]);
        }
        exit();
    }

    // Admin: Reset user session for fresh start
    if ($isAdmin && $action === 'reset_user_session') {
        // Clean output buffer to ensure valid JSON
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');
        unset($_SESSION['known_pending_users']);
        error_log("User session reset for fresh real-time detection");
        echo json_encode(['success' => true, 'message' => 'Session reset']);
        exit();
    }

    // AJAX: Check for new pending users (real-time sync)
    if ($isAdmin && $action === 'check_new_pending_users') {
        try {
            $lastCheck = $_POST['last_check'] ?? '';
            $hasNew = false;
            $timestamp = date('c');
            
            // Always check for new users by comparing current count with stored count
            global $firestore;
            $currentPendingCount = 0;
            
            if ($firestore) {
                try {
                    // Get current count of pending users using both fields
                    $query1 = $firestore->collection('users')->where('accountStatus', '==', 'pending');
                    $docs1 = $query1->documents();
                    $count1 = $docs1->size();
                    
                    $query2 = $firestore->collection('users')->where('status', '==', 'pending');
                    $docs2 = $query2->documents();
                    $count2 = $docs2->documents()->size();
                    
                    $currentPendingCount = max($count1, $count2);
                } catch (Throwable $e) {
                    // If direct query fails, try alternative approach
                    try {
                        $currentPendingCount = count(list_pending_users(1000)); // Get all pending users
                    } catch (Throwable $e2) {
                        $currentPendingCount = 0;
                    }
                }
            }
            
            // Store the count in session for comparison
            if (!isset($_SESSION['last_pending_count'])) {
                $_SESSION['last_pending_count'] = $currentPendingCount;
                $hasNew = false; // First time, no new users
            } else {
                $lastCount = $_SESSION['last_pending_count'];
                if ($currentPendingCount > $lastCount) {
                    $hasNew = true;
                    $_SESSION['last_pending_count'] = $currentPendingCount;
                }
            }
            
            echo json_encode([
                'success' => true,
                'hasNew' => $hasNew,
                'timestamp' => $timestamp,
                'currentCount' => $currentPendingCount,
                'lastCount' => $_SESSION['last_pending_count'] ?? 0
            ]);
        } catch (Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to check for new users: ' . $e->getMessage(),
                'hasNew' => false
            ]);
        }
        exit();
    }

    // AJAX: List pending users for verification view with optimized performance and error handling
    if ($isAdmin && $action === 'list_pending_users') {
        $startTime = microtime(true);
        
        try {
            $page     = max(1, (int)($_POST['page'] ?? 1));
            $pageSize = max(5, min(50, (int)($_POST['pageSize'] ?? 20)));
            $search   = trim(strtolower($_POST['search'] ?? ''));
            $offset   = ($page - 1) * $pageSize;
    
            // Create cache key for this request
            $cacheKey = "pending_users_" . md5($search . $page . $pageSize);
            $cachedResult = cache_get($cacheKey, 120); // 2-minute cache
            
            if ($cachedResult !== null) {
                echo json_encode($cachedResult);
                exit();
            }
    
            $users = [];
            $total = 0;
    
            // Use REST API approach which is more reliable
            if ($search) {
                // Search functionality using REST API
                $searchResults = search_pending_users_rest($search, $pageSize, $offset);
                $users = $searchResults['users'];
                $total = $searchResults['total'];
                
                // Check for errors in search results
                if (isset($searchResults['error'])) {
                    throw new Exception('Search failed: ' . $searchResults['error']);
                }
            } else {
                // Simple paginated query without search
                    $restResults = get_pending_users_rest($pageSize, $offset);
                    $users = $restResults['users'];
                    $total = $restResults['total'];
                
                // Check for errors in rest results
                if (isset($restResults['error'])) {
                    throw new Exception('Query failed: ' . $restResults['error']);
                }
            }
            
            $result = [
                'success' => true,
                'data' => $users,
                'total' => $total,
                'page' => $page,
                'pageSize' => $pageSize,
                'hasMore' => ($offset + count($users)) < $total,
                'executionTime' => round((microtime(true) - $startTime) * 1000, 2) . 'ms',
                'cached' => false
            ];
            
            // Cache successful results
            cache_set($cacheKey, $result);
            
            echo json_encode($result);
        } catch (Throwable $e) {
            $errorMessage = $e->getMessage();
            
            echo json_encode([
                'success' => false,
                'message' => 'Failed to load pending users: ' . $errorMessage,
                'retry' => true,
                'executionTime' => round((microtime(true) - $startTime) * 1000, 2) . 'ms'
            ]);
        }
        exit();
    }
    
    // DEBUG: Test specific user lookup
    if ($isAdmin && $action === 'debug_user') {
        // Clean output buffer to ensure valid JSON
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');

        $userId = $_POST['userId'] ?? '';
        if (empty($userId)) {
            echo json_encode(['success' => false, 'message' => 'User ID required']);
            exit();
        }
        
        try {
            global $firestore;
            if ($firestore) {
                $userDoc = $firestore->collection('users')->document($userId)->snapshot();
                if ($userDoc->exists()) {
                    $userData = $userDoc->data();
                    echo json_encode([
                        'success' => true,
                        'message' => 'User found in Firestore',
                        'data' => $userData,
                        'accountStatus' => $userData['accountStatus'] ?? 'not set',
                        'hasAccountStatus' => isset($userData['accountStatus'])
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'User not found in Firestore']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Firestore not initialized']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit();
    }

    // AJAX: Recent feed for admin dashboard with optimized caching and filtering
    if ($isAdmin && $action === 'recent_feed') {
        // Disable error display to prevent JSON corruption
        ini_set('display_errors', 0);
        error_reporting(0);
        
        $startTime = microtime(true);
        
        // Clean output buffer to ensure valid JSON
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        
        $page = max(1, (int)($_POST['page'] ?? 1));
        $pageSize = max(5, min(50, (int)($_POST['pageSize'] ?? 20)));
        $search = trim(strtolower($_POST['search'] ?? ''));
        $categoryFilter = trim($_POST['category'] ?? 'all');
        $statusFilter = trim($_POST['status'] ?? 'all');
        
        try {
            // Check if force refresh is requested
            $forceRefresh = isset($_POST['force_refresh']) && $_POST['force_refresh'] === 'true';
            $debugClient = isset($_POST['debug']) && $_POST['debug'] === 'true';
            
            // For realtime: when force_refresh is true, always go directly to Firestore
            if ($forceRefresh) {
                // Parallel ORDERED runQuery fetch (reliable newest-first)
                $allRecent = empty($categories) ? [] : build_recent_feed_ultra_fast_runquery($categories, $categoryFilter, $statusFilter, $search, 10);
                $cacheHit = false;
            } else {
                // Create cache key based on filters
                $cacheKey = "recent_feed_" . md5($search . $categoryFilter . $statusFilter);
                
                // Use cache with 15 second TTL (was 1s — too aggressive)
                $cachedData = cache_get($cacheKey, 15);
                $cacheHit = ($cachedData !== null);
                
                if ($cachedData === null) {
                    $allRecent = empty($categories) ? [] : build_recent_feed_ultra_fast_runquery($categories, $categoryFilter, $statusFilter, $search, 10);
                    cache_set($cacheKey, $allRecent, 15);
                } else {
                    $allRecent = $cachedData;
                }
            }
            
            $total = count($allRecent);
            $offset = ($page - 1) * $pageSize;
            $paginatedRecent = array_slice($allRecent, $offset, $pageSize);
            
            // Final buffer clean before output
            if (ob_get_length()) ob_clean();
            
            echo json_encode([
                'success' => true,
                'data' => $paginatedRecent,
                'total' => $total,
                'page' => $page,
                'pageSize' => $pageSize,
                'hasMore' => ($offset + $pageSize) < $total,
                'meta' => [
                    'serverNow' => date('c'),
                    'cache' => [
                        'hit' => $cacheHit,
                        'forceRefresh' => $forceRefresh,
                    ],
                ],
                'filters' => [
                    'search' => $search,
                    'category' => $categoryFilter,
                    'status' => $statusFilter
                ],
                'executionTime' => round((microtime(true) - $startTime) * 1000, 2) . 'ms'
            ]);
        } catch (Throwable $e) {
            // Final buffer clean before error output
            if (ob_get_length()) ob_clean();
            
            error_log("Recent activity error: " . $e->getMessage() . " Stack: " . $e->getTraceAsString());
            echo json_encode([
                'success' => false,
                'message' => 'Failed to load recent activity: ' . $e->getMessage(),
                'retry' => true,
                'executionTime' => round((microtime(true) - $startTime) * 1000, 2) . 'ms'
            ]);
        }
        exit();
    }

    // AJAX: Load admin dashboard stats (optimized for performance)
    if ($isAdmin && $action === 'load_admin_stats') {
        $startTime = microtime(true);
        
        // Clean output buffer to ensure valid JSON
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');
        
        try {
            // Check cache first with aggressive caching for admin dashboard
            $cacheKey = 'admin_stats_' . $userRole;
            $adminStats = cache_get($cacheKey, 20); // Short cache for fast UX with near-realtime feel
            
            // Check if we need to force refresh (cache busting)
            $forceRefresh = isset($_POST['force_refresh']) && $_POST['force_refresh'] === 'true';
            
            if ($adminStats === null || $forceRefresh) {
                if ($forceRefresh) {
                    // Clear cache if forcing refresh
                    cache_delete($cacheKey);
                    $adminStats = null;
                }
                
                $adminStats = [];
                
                // Get all collection names
                $collections = array_map(fn($meta) => $meta['collection'], $categories);
                
                // Safety check
                if (empty($collections)) {
                    throw new Exception("No collections found");
                }
                
                // Get optimized counts for all collections (prefer fast path when available)
                $countResults = function_exists('get_admin_stats_counts_fast')
                    ? get_admin_stats_counts_fast($collections)
                    : get_admin_stats_counts_fallback($collections);
                
                // Map results back to category slugs
                foreach ($categories as $slug => $meta) {
                    $col = $meta['collection'];
                    $adminStats[$slug] = [
                        'total'    => $countResults[$col]['total'] ?? 0,
                        'approved' => $countResults[$col]['approved'] ?? 0,
                        'pending'  => $countResults[$col]['pending'] ?? 0,
                        'declined' => $countResults[$col]['declined'] ?? 0,
                        'responded' => $countResults[$col]['responded'] ?? 0,
                    ];
                }
                
                // Cache the results briefly for fast page loads and navigation
                cache_set($cacheKey, $adminStats, 20);
            }
            
            echo json_encode([
                'success' => true,
                'data' => $adminStats,
                'executionTime' => round((microtime(true) - $startTime) * 1000, 2) . 'ms'
            ]);
        } catch (Exception $e) {
            error_log("Admin stats error: " . $e->getMessage() . " Stack: " . $e->getTraceAsString());
            echo json_encode([
                'success' => false,
                'message' => 'Failed to load admin stats: ' . $e->getMessage(),
                'retry' => true,
                'executionTime' => round((microtime(true) - $startTime) * 1000, 2) . 'ms'
            ]);
        }
        exit();
    }

    // AJAX: Load analytics counts (fast, cached) for both admin and staff
    if ($action === 'load_analytics_counts') {
        $startTime = microtime(true);

        // Clean output buffer to ensure valid JSON
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');
        header('Cache-Control: no-cache');

        try {
            // Determine which category slugs are allowed for this user
            $allowedSlugs = [];
            if ($isAdmin) {
                $allowedSlugs = array_keys($categories);
            } else {
                $profile = get_user_profile($userId);
                $assigned = array_values(array_filter(array_map('strval', $profile['categories'] ?? [])));
                foreach ($assigned as $slug) {
                    if (isset($categories[$slug])) $allowedSlugs[] = $slug;
                }
            }

            $allowedSlugs = array_values(array_unique($allowedSlugs));
            sort($allowedSlugs);

            $cacheKey = 'analytics_counts_' . ($isAdmin ? 'admin' : 'staff') . '_' . md5(implode(',', $allowedSlugs));
            $forceRefresh = isset($_POST['force_refresh']) && $_POST['force_refresh'] === 'true';
            
            // Always try to get cached data first for immediate response
            $cachedPayload = cache_get($cacheKey, 300); // 5 minute cache
            
            // If we have cache and not forcing refresh, return immediately
            if (is_array($cachedPayload) && !$forceRefresh) {
                $cachedPayload['executionTime'] = round((microtime(true) - $startTime) * 1000, 2) . 'ms';
                $cachedPayload['cached'] = true;
                echo json_encode($cachedPayload);
                exit();
            }
            
            // Fetch fresh data
            $payload = null;
            if (!is_array($cachedPayload) || $forceRefresh) {
                $collections = [];
                foreach ($allowedSlugs as $slug) {
                    $collections[] = $categories[$slug]['collection'];
                }

                if (empty($collections)) {
                    $countResults = [];
                } else {
                    // Prefer parallel aggregation counts when available.
                    $countResults = function_exists('get_admin_stats_counts_fast')
                        ? get_admin_stats_counts_fast($collections)
                        : get_admin_stats_counts_fallback($collections);
                }

                $bySlug = [];
                $grand = ['total' => 0, 'pending' => 0, 'approved' => 0, 'declined' => 0, 'responding' => 0, 'responded' => 0];

                foreach ($allowedSlugs as $slug) {
                    $col = $categories[$slug]['collection'];
                    $row = [
                        'total'     => (int)($countResults[$col]['total'] ?? 0),
                        'pending'   => (int)($countResults[$col]['pending'] ?? 0),
                        'approved'  => (int)($countResults[$col]['approved'] ?? 0),
                        'declined'  => (int)($countResults[$col]['declined'] ?? 0),
                        'responding'=> (int)($countResults[$col]['responding'] ?? 0),
                        'responded' => (int)($countResults[$col]['responded'] ?? 0),
                    ];
                    $bySlug[$slug] = $row;
                    $grand['total'] += $row['total'];
                    $grand['pending'] += $row['pending'];
                    $grand['approved'] += $row['approved'];
                    $grand['declined'] += $row['declined'];
                    $grand['responding'] += $row['responding'];
                    $grand['responded'] += $row['responded'];
                }

                $payload = [
                    'success' => true,
                    'grand' => $grand,
                    'bySlug' => $bySlug,
                ];

                cache_set($cacheKey, $payload, 120);
            }

            $payload['executionTime'] = round((microtime(true) - $startTime) * 1000, 2) . 'ms';
            echo json_encode($payload);
        } catch (Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to load analytics counts: ' . $e->getMessage(),
                'retry' => true,
                'executionTime' => round((microtime(true) - $startTime) * 1000, 2) . 'ms'
            ]);
        }
        exit();
    }

    // AJAX: Analytics charts + metrics (cached, minimal data)
    if ($action === 'get_analytics_data') {
        $startTime = microtime(true);
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');

        try {
            $range = strtolower(trim((string)($_POST['range'] ?? 'week')));
            $rangeAliases = [
                'today' => 'day',
            ];
            if (isset($rangeAliases[$range])) {
                $range = $rangeAliases[$range];
            }
            $allowedRanges = ['day', 'week', 'month', 'year', 'all'];
            if (!in_array($range, $allowedRanges, true)) {
                $range = 'week';
            }

            // Determine allowed slugs for current user
            if ($isAdmin) {
                $allowedSlugs = array_keys($categories);
            } else {
                $allowedSlugs = array_values(array_filter(array_map('strval', $_SESSION['user_categories'] ?? [])));
                $allowedSlugs = array_values(array_filter($allowedSlugs, fn($s) => isset($categories[$s])));

                // If session categories are stale/missing, fall back to profile lookup.
                if (empty($allowedSlugs)) {
                    $profile = get_user_profile($userId);
                    $allowedSlugs = array_values(array_filter(array_map('strval', $profile['categories'] ?? [])));
                    $allowedSlugs = array_values(array_filter($allowedSlugs, fn($s) => isset($categories[$s])));
                    if (!empty($allowedSlugs)) {
                        $_SESSION['user_categories'] = $allowedSlugs;
                    }
                }
            }
            $allowedSlugs = array_values(array_unique($allowedSlugs));
            sort($allowedSlugs);

            $cacheKey = 'analytics_data_' . $range . '_' . ($isAdmin ? 'admin' : 'staff') . '_' . md5(implode(',', $allowedSlugs));
            $forceRefresh = isset($_POST['force_refresh']) && $_POST['force_refresh'] === 'true';
            if (!$forceRefresh) {
                $cached = cache_get($cacheKey, 120);
                if (is_array($cached)) {
                    $cached['executionTime'] = round((microtime(true) - $startTime) * 1000, 2) . 'ms';
                    $cached['cached'] = true;
                    echo json_encode($cached);
                    exit();
                }
            }

            // Category totals via existing fast counts
            $collections = [];
            foreach ($allowedSlugs as $slug) {
                $collections[] = $categories[$slug]['collection'];
            }
            if (empty($collections)) {
                $countResults = [];
            } else {
                // Prefer parallel aggregation counts when available.
                $countResults = function_exists('get_admin_stats_counts_fast')
                    ? get_admin_stats_counts_fast($collections)
                    : get_admin_stats_counts_fallback($collections);

                // Guard: if fast path returns all-zero totals, retry with fallback parser.
                $hasAnyTotal = false;
                foreach ($collections as $colCheck) {
                    if ((int)($countResults[$colCheck]['total'] ?? 0) > 0) {
                        $hasAnyTotal = true;
                        break;
                    }
                }
                if (!$hasAnyTotal) {
                    $countResults = get_admin_stats_counts_fallback($collections);
                }
            }

            $categoryLabels = [];
            $categoryData = [];
            $respondingTotal = 0;
            $respondedTotal = 0;
            $totalReports = 0;

            foreach ($allowedSlugs as $slug) {
                $meta = $categories[$slug];
                $col = $meta['collection'];
                $total = (int)($countResults[$col]['total'] ?? 0);
                $responding = (int)($countResults[$col]['responding'] ?? 0);
                $responded = (int)($countResults[$col]['responded'] ?? 0);
                $totalReports += $total;
                $respondingTotal += $responding;
                $respondedTotal += $responded;
                $categoryLabels[] = $meta['label'];
                $categoryData[] = $total;
            }

            // Trend data (fast heuristic): daily buckets for short ranges, monthly for year/all.
            $bucketMode = ($range === 'year' || $range === 'all') ? 'month' : 'day';
            $trendLabels = [];
            $trendCounts = [];
            if ($bucketMode === 'month') {
                for ($i = 11; $i >= 0; $i--) {
                    $label = date('M Y', strtotime("first day of -$i month"));
                    $key = date('Y-m', strtotime("first day of -$i month"));
                    $trendLabels[] = $label;
                    $trendCounts[$key] = 0;
                }
            } else {
                $days = ($range === 'day') ? 1 : (($range === 'month') ? 30 : 7);
                for ($i = $days - 1; $i >= 0; $i--) {
                    $label = date('M j', strtotime("-$i day"));
                    $key = date('Y-m-d', strtotime("-$i day"));
                    $trendLabels[] = $label;
                    $trendCounts[$key] = 0;
                }
            }

            $parseDateKey = function($ts): ?string {
                if (is_array($ts) && isset($ts['_seconds'])) {
                    return date('Y-m-d', (int)$ts['_seconds']);
                }
                if ($ts instanceof \Google\Cloud\Core\Timestamp) {
                    try { return $ts->get()->format('Y-m-d'); } catch (Throwable $e) { return null; }
                }
                if (is_string($ts) && $ts !== '') {
                    $t = strtotime($ts);
                    if ($t !== false) return date('Y-m-d', $t);
                }
                return null;
            };

            // Parallel list-documents sampling for trend/response metrics
            $sampleLimit = ($range === 'month') ? 40 : (($range === 'year' || $range === 'all') ? 32 : 20);
            $responseAgg = [];
            foreach ($allowedSlugs as $slugKey) {
                $responseAgg[$slugKey] = ['sum' => 0.0, 'count' => 0];
            }

            try {
                $token = firestore_rest_token();
                $base  = firestore_base_url();
                $mh = curl_multi_init();
                $handles = [];
                foreach ($allowedSlugs as $slug) {
                    $col = $categories[$slug]['collection'];
                    $url = $base . '/' . rawurlencode($col) . '?pageSize=' . (int)$sampleLimit;
                    $ch = curl_init();
                    curl_setopt_array($ch, [
                        CURLOPT_URL => $url,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_HTTPHEADER => [
                            'Authorization: Bearer ' . $token,
                            'Accept: application/json',
                        ],
                        CURLOPT_TIMEOUT => 10,
                        CURLOPT_CONNECTTIMEOUT => 4,
                        CURLOPT_SSL_VERIFYPEER => SSL_VERIFY,
                        CURLOPT_SSL_VERIFYHOST => SSL_VERIFY ? 2 : 0,
                    ]);
                    curl_multi_add_handle($mh, $ch);
                    $handles[] = ['ch' => $ch, 'slug' => $slug];
                }

                $running = null;
                do {
                    curl_multi_exec($mh, $running);
                    curl_multi_select($mh, 0.2);
                } while ($running > 0);

                foreach ($handles as $entry) {
                    $ch = $entry['ch'];
                    $slug = $entry['slug'];
                    $raw  = curl_multi_getcontent($ch);
                    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_multi_remove_handle($mh, $ch);
                    curl_close($ch);
                    if ($http < 200 || $http >= 300) continue;

                    $json = json_decode($raw ?: 'null', true);
                    $docs = is_array($json) ? ($json['documents'] ?? []) : [];
                    if (!is_array($docs)) continue;

                    foreach ($docs as $doc) {
                        // createTime exists for every doc and is enough for trend buckets.
                        $kRaw = $parseDateKey($doc['createTime'] ?? null);
                        $k = null;
                        if ($kRaw !== null) {
                            $k = ($bucketMode === 'month') ? substr($kRaw, 0, 7) : $kRaw;
                        }
                        if ($k !== null && isset($trendCounts[$k])) {
                            $trendCounts[$k]++;
                        }

                        // Compute response-time sample from timestamp/createdAt -> respondedAt.
                        $fields = isset($doc['fields']) && is_array($doc['fields'])
                            ? firestore_decode_fields($doc['fields'])
                            : [];
                        if (!is_array($fields)) {
                            $fields = [];
                        }

                        $respondedAt = $fields['respondedAt'] ?? null;
                        $reportedAt = $fields['timestamp'] ?? ($fields['createdAt'] ?? ($doc['createTime'] ?? null));

                        $respondedEpoch = is_string($respondedAt) ? strtotime($respondedAt) : false;
                        $reportedEpoch = false;
                        if (is_string($reportedAt)) {
                            $reportedEpoch = strtotime($reportedAt);
                        } elseif (is_array($reportedAt) && isset($reportedAt['_seconds'])) {
                            $reportedEpoch = (int)$reportedAt['_seconds'];
                        }

                        if ($respondedEpoch !== false && $reportedEpoch !== false && $respondedEpoch > $reportedEpoch) {
                            $minutes = ($respondedEpoch - $reportedEpoch) / 60;
                            if ($minutes >= 0 && $minutes <= 1440 && isset($responseAgg[$slug])) {
                                $responseAgg[$slug]['sum'] += $minutes;
                                $responseAgg[$slug]['count']++;
                            }
                        }
                    }
                }

                curl_multi_close($mh);
            } catch (Throwable $e) {
                // If sampling fails, keep zeros; counts/metrics still work.
            }
            $trendData = array_values($trendCounts);

            // Response time chart from sampled responded documents.
            $responseTimeLabels = [];
            $responseTimeData = [];
            $avgResponseMinutes = [];
            foreach ($allowedSlugs as $slug) {
                $responseTimeLabels[] = $categories[$slug]['label'];
                $count = (int)($responseAgg[$slug]['count'] ?? 0);
                $sum = (float)($responseAgg[$slug]['sum'] ?? 0.0);
                $avg = $count > 0 ? round($sum / $count, 1) : 0;
                $responseTimeData[] = $avg;
                if ($avg > 0) {
                    $avgResponseMinutes[] = $avg;
                }
            }

            $responseRate = $totalReports > 0 ? round(($respondedTotal / $totalReports) * 100, 1) : 0;
            $avgResponseTime = !empty($avgResponseMinutes)
                ? round(array_sum($avgResponseMinutes) / count($avgResponseMinutes), 1)
                : 0;
            $activeResponders = 0;
            foreach ($allowedSlugs as $slug) {
                if ((int)($countResults[$categories[$slug]['collection']]['responded'] ?? 0) > 0) {
                    $activeResponders++;
                }
            }

            // Lightweight trend percentages for cards
            $points = count($trendData);
            $half = (int)floor($points / 2);
            $currentPeriod = $half > 0 ? array_slice($trendData, -$half) : $trendData;
            $prevPeriod = $half > 0 ? array_slice($trendData, 0, $half) : [];
            $currentTotal = array_sum($currentPeriod);
            $prevTotal = array_sum($prevPeriod);
            $totalReportsTrend = ($prevTotal > 0)
                ? round((($currentTotal - $prevTotal) / $prevTotal) * 100, 1)
                : ($currentTotal > 0 ? 100.0 : 0.0);

            $payload = [
                'success' => true,
                'data' => [
                    'categoryLabels' => $categoryLabels,
                    'categoryData' => $categoryData,
                    'trendLabels' => $trendLabels,
                    'trendData' => $trendData,
                    'responseTimeLabels' => $responseTimeLabels,
                    'responseTimeData' => $responseTimeData,
                    'metrics' => [
                        'totalReports' => $totalReports,
                        'totalReportsTrend' => $totalReportsTrend,
                        'responseRate' => $responseRate,
                        'responseRateTrend' => $totalReportsTrend,
                        'avgResponseTime' => $avgResponseTime,
                        'responseTimeTrend' => $avgResponseTime > 0 ? -round(min($avgResponseTime / 60, 100), 1) : 0,
                        'activeResponders' => $activeResponders,
                    ]
                ]
            ];

            cache_set($cacheKey, $payload, 120);
            $payload['executionTime'] = round((microtime(true) - $startTime) * 1000, 2) . 'ms';
            $payload['cached'] = false;
            echo json_encode($payload);
        } catch (Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to load analytics data: ' . $e->getMessage(),
                'retry' => true,
                'executionTime' => round((microtime(true) - $startTime) * 1000, 2) . 'ms'
            ]);
        }
        exit();
    }

    // Debug: Test Firestore connection
    if ($isAdmin && $action === 'test_connection') {
        $testResult = test_firestore_connection();
        echo json_encode($testResult);
        exit();
    }

    // Lightweight approve/reject (accept both userId & uid)
    if ($isAdmin && $action === 'verify_user') {
        require_once __DIR__ . '/notification_system.php';
        $uid = trim($_POST['userId'] ?? ($_POST['uid'] ?? ''));
        $decision = strtolower(trim($_POST['decision'] ?? ''));
        if (!$uid || !in_array($decision, ['approved','rejected'], true)) {
            echo json_encode(['success'=>false,'message'=>'Invalid request']); exit();
        }
        $ok = update_user_status($uid, $decision);
        if ($ok) {
            // Notify the user about the registration decision
            if (function_exists('send_fcm_notification_to_user')) {
                $title = ($decision === 'approved') ? 'Account Approved' : 'Account Rejected';
                $body  = ($decision === 'approved')
                    ? 'Your registration has been approved. You can now log in.'
                    : 'Your registration was rejected. Please contact support for assistance.';
                $data  = ['type' => 'registration_status', 'status' => $decision];
                try { send_fcm_notification_to_user($uid, $title, $body, $data); } catch (Throwable $e) { error_log('FCM notify (verify_user) failed: '.$e->getMessage()); }
            }
            // Invalidate cached dashboard data when user status changes
            unset($_SESSION['__cache']['admin_stats'], $_SESSION['__cache']['recent_feed']);
            echo json_encode(['success'=>true,'message'=>"User {$decision}"]);
        } else {
            echo json_encode(['success'=>false,'message'=>'Update failed']);
        }
        exit();
    }

    // Staff: Load assigned reports data (optimized for performance)
    if (!$isAdmin && $action === 'load_staff_data') {
        $startTime = microtime(true);
        $forceRefresh = isset($_POST['force_refresh']) && $_POST['force_refresh'] === 'true';
        
        try {
            $profile  = get_user_profile($userId);
            $assigned = array_values(array_filter(array_map('strval', $profile['categories'] ?? [])));
            
            $cards = [];
            foreach ($assigned as $slug) {
                if (!isset($categories[$slug])) continue;
                $collection = $categories[$slug]['collection'];
                if ($forceRefresh) {
                    cache_delete("reports_{$collection}_50");
                }
                $reports = list_latest_reports($collection, 50, !$forceRefresh);
                
                // Sort reports by timestamp (newest first)
                usort($reports, function($a, $b) {
                    $timeA = $a['timestamp'] ?? '';
                    $timeB = $b['timestamp'] ?? '';
                    
                    $secondsA = is_array($timeA) && isset($timeA['_seconds']) ? $timeA['_seconds'] : strtotime($timeA);
                    $secondsB = is_array($timeB) && isset($timeB['_seconds']) ? $timeB['_seconds'] : strtotime($timeB);
                    
                    return $secondsB - $secondsA;
                });
                
                $cards[$slug] = $reports;
            }
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'profile' => $profile,
                    'assigned' => $assigned,
                    'cards' => $cards,
                ],
                'meta' => [
                    'forceRefresh' => $forceRefresh,
                ],
                'executionTime' => round((microtime(true) - $startTime) * 1000, 2) . 'ms'
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to load staff data: ' . $e->getMessage(),
                'retry' => true,
                'executionTime' => round((microtime(true) - $startTime) * 1000, 2) . 'ms'
            ]);
        }
        exit();
    }

    // Staff: Check for urgent reports
    if (!$isAdmin && $action === 'check_urgent') {
        $startTime = microtime(true);
        
        try {
            require_once __DIR__ . '/notification_system.php';
            // Get user profile to know assigned categories FIRST
            $userProfile = get_user_profile($userId);
            $userCategories = $userProfile['categories'] ?? [];
            
            // FIXED: Pass user categories to only get reports for their assigned categories
            $urgentReports = check_urgent_reports($userCategories);
            
            // Create notifications for urgent reports
            check_and_create_notifications($userCategories);
            
            echo json_encode([
                'success' => true,
                'data' => $urgentReports,
                'executionTime' => round((microtime(true) - $startTime) * 1000, 2) . 'ms',
                'filteredFor' => $userCategories // Debug info
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to check urgent reports: ' . $e->getMessage(),
                'retry' => true,
                'executionTime' => round((microtime(true) - $startTime) * 1000, 2) . 'ms'
            ]);
        }
        exit();
    }

    // Staff: Get notification count
    if (!$isAdmin && $action === 'get_notification_count') {
        try {
            require_once __DIR__ . '/notification_system.php';
            // Get user profile to know assigned categories
            $userProfile = get_user_profile($userId);
            $userCategories = $userProfile['categories'] ?? [];
            
            $notifications = get_staff_notifications(50, $userCategories);
            $count = count($notifications);
            
            echo json_encode([
                'success' => true,
                'count' => $count
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'count' => 0,
                'message' => 'Failed to get notification count: ' . $e->getMessage()
            ]);
        }
        exit();
    }

    // Staff: Get notifications
    if (!$isAdmin && $action === 'get_notifications') {
        try {
            require_once __DIR__ . '/notification_system.php';
            // Get user profile to know assigned categories
            $userProfile = get_user_profile($userId);
            $userCategories = $userProfile['categories'] ?? [];
            
            $notifications = get_staff_notifications(20, $userCategories);
            
            echo json_encode([
                'success' => true,
                'notifications' => $notifications
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'notifications' => [],
                'message' => 'Failed to get notifications: ' . $e->getMessage()
            ]);
        }
        exit();
    }

    // Staff: Mark notification as read
    if (!$isAdmin && $action === 'mark_notification_read') {
        $notificationId = $_POST['notification_id'] ?? '';
        
        if (empty($notificationId)) {
            echo json_encode([
                'success' => false,
                'message' => 'Notification ID is required'
            ]);
            exit();
        }
        
        try {
            require_once __DIR__ . '/notification_system.php';
            $success = mark_notification_read($notificationId);
            
            echo json_encode([
                'success' => $success,
                'message' => $success ? 'Notification marked as read' : 'Failed to mark notification as read'
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to mark notification as read: ' . $e->getMessage()
            ]);
        }
        exit();
    }

    // Staff: Create notifications for urgent reports
    if (!$isAdmin && $action === 'create_notifications') {
        try {
            require_once __DIR__ . '/notification_system.php';
            // Get user profile to know assigned categories
            $userProfile = get_user_profile($userId);
            $userCategories = $userProfile['categories'] ?? [];
            
            check_and_create_notifications($userCategories);
            
            echo json_encode([
                'success' => true,
                'message' => 'Notifications created successfully'
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to create notifications: ' . $e->getMessage()
            ]);
        }
        exit();
    }

    // Staff: Cleanup notifications (debug and fix count issues)
    if (!$isAdmin && $action === 'cleanup_notifications') {
        try {
            require_once __DIR__ . '/notification_system.php';
            $cleanedCount = cleanup_orphaned_notifications();
            
            echo json_encode([
                'success' => true,
                'message' => "Cleaned up {$cleanedCount} orphaned notifications",
                'cleanedCount' => $cleanedCount
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to cleanup notifications: ' . $e->getMessage()
            ]);
        }
        exit();
    }

    // Staff: Cleanup corrupted notifications (fix count issues)
    if (!$isAdmin && $action === 'cleanup_corrupted_notifications') {
        try {
            $cleanedCount = cleanup_corrupted_notifications();
            
            echo json_encode([
                'success' => true,
                'message' => "Cleaned up {$cleanedCount} corrupted notifications",
                'cleanedCount' => $cleanedCount
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to cleanup corrupted notifications: ' . $e->getMessage()
            ]);
        }
        exit();
    }

    // Staff: Debug notifications (get all notifications for troubleshooting)
    if (!$isAdmin && $action === 'debug_notifications') {
        try {
            // Get user profile to know assigned categories
            $userProfile = get_user_profile($userId);
            $userCategories = $userProfile['categories'] ?? [];
            
            $allNotifications = debug_all_notifications();
            $unreadNotifications = get_staff_notifications(100, $userCategories);
            
            echo json_encode([
                'success' => true,
                'allCount' => count($allNotifications),
                'unreadCount' => count($unreadNotifications),
                'allNotifications' => $allNotifications,
                'unreadNotifications' => $unreadNotifications
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to debug notifications: ' . $e->getMessage()
            ]);
        }
        exit();
    }

    // Staff: Create notification for new report (real-time)
    if (!$isAdmin && $action === 'create_notification_for_report') {
        try {
            $collection = $_POST['collection'] ?? '';
            $reportId = $_POST['reportId'] ?? '';
            $reportData = json_decode($_POST['reportData'] ?? '{}', true);
            
            if (empty($collection) || empty($reportId) || empty($reportData)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Missing required data'
                ]);
                exit();
            }
            
            // Get user profile to know assigned categories
            $userProfile = get_user_profile($userId);
            $userCategories = $userProfile['categories'] ?? [];
            
            // Check if user has access to this collection
            $collectionToCategory = [
                'ambulance_reports' => 'ambulance',
                'fire_reports' => 'fire',
                'flood_reports' => 'flood',
                'other_reports' => 'other',
                'tanod_reports' => 'tanod'
            ];
            
            $category = $collectionToCategory[$collection] ?? '';
            if (!empty($userCategories) && !in_array($category, $userCategories)) {
                // User doesn't have access to this category
                echo json_encode([
                    'success' => true,
                    'message' => 'Notification not created - user not assigned to this category'
                ]);
                exit();
            }
            
            // Create notification for this new report
            $reporterName = $reportData['fullName'] ?? $reportData['reporterName'] ?? 'Unknown';
            $location = $reportData['location'] ?? 'Unknown location';
            $description = $reportData['purpose'] ?? $reportData['description'] ?? 'No description';
            
            // Create appropriate title based on collection type
            $collectionLabels = [
                'ambulance_reports' => '🚑 Ambulance',
                'fire_reports' => '🔥 Fire',
                'flood_reports' => '🌊 Flood',
                'other_reports' => '📋 Other',
                'tanod_reports' => '👮 Tanod'
            ];
            
            $collectionLabel = $collectionLabels[$collection] ?? '📋 Report';
            
            // Create notification for this new report
            $title = "{$collectionLabel} - {$reporterName}";
            $message = "New pending report from {$reporterName} at {$location}. {$description}";
            
            $notificationData = [
                'reportId' => $reportId,
                'reporterName' => $reporterName,
                'location' => $location,
                'description' => $description,
                'timestamp' => $reportData['timestamp'] ?? null,
                'status' => 'Pending',
                'collection' => $collection
            ];
            
            $success = create_staff_notification(
                NOTIFICATION_TYPE_URGENT,
                $title,
                $message,
                $notificationData
            );
            
            echo json_encode([
                'success' => $success,
                'message' => $success ? 'Notification created successfully' : 'Failed to create notification'
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to create notification: ' . $e->getMessage()
            ]);
        }
        exit();
    }

    // AJAX: Get specific report data (for modal fallback)
    if ($action === 'get_report_data') {
        header('Content-Type: application/json');
        
        $collection = $_POST['collection'] ?? '';
        $docId = $_POST['docId'] ?? '';
        
        if (empty($collection) || empty($docId)) {
            echo json_encode([
                'success' => false,
                'message' => 'Collection and docId are required'
            ]);
            exit();
        }
        
        try {
            // Get the report data directly from Firestore
            if (function_exists('firestore_get_doc_by_id')) {
                $reportData = firestore_get_doc_by_id($collection, $docId);
                if ($reportData) {
                    echo json_encode([
                        'success' => true,
                        'data' => $reportData
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Report not found'
                    ]);
                }
            } else {
                // Fallback to REST API
                $url = firestore_base_url() . '/documents/' . rawurlencode($collection) . '/' . rawurlencode($docId);
                $response = firestore_rest_request('GET', $url);
                
                if (isset($response['fields'])) {
                    $reportData = firestore_decode_fields($response['fields']);
                    echo json_encode([
                        'success' => true,
                        'data' => $reportData
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Report not found'
                    ]);
                }
            }
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to fetch report data: ' . $e->getMessage()
            ]);
        }
        exit();
    }

    // Check for new reports (real-time sync) - For both Staff and Admin
    if ($action === 'check_new_reports') {
        // Ensure we return JSON
        header('Content-Type: application/json');
        $lastCheckTime = $_POST['last_check'] ?? '';
        
        try {
            $newReports = [];
            $userCategories = [];
            
            if ($isAdmin) {
                // Admin checks all categories
                $userCategories = array_keys($categories);
            } else {
                // Staff checks assigned categories
                $userProfile = get_user_profile($_SESSION['user_id']);
                if ($userProfile && !empty($userProfile['categories'])) {
                    $userCategories = $userProfile['categories'];
                }
            }
            
            if (empty($userCategories)) {
                echo json_encode([
                    'success' => true,
                    'hasNew' => false,
                    'data' => []
                ]);
                exit();
            }
            
            foreach ($userCategories as $category) {
                // Use the global categories array instead of undefined function
                if (!isset($categories[$category])) continue;
                
                $categoryMeta = $categories[$category];
                $collection = $categoryMeta['collection'];
                
                // Get reports from the last 10 minutes (for real-time checking)
                $url = firestore_base_url() . ':runQuery';
                $body = [
                    'structuredQuery' => [
                        'from' => [['collectionId' => $collection]],
                        'orderBy' => [
                            ['field' => ['fieldPath' => 'timestamp'], 'direction' => 'DESCENDING']
                        ],
                        'limit' => 20
                    ]
                ];
                
                $response = firestore_rest_request('POST', $url, $body);
                
                if (is_array($response)) {
                    foreach ($response as $row) {
                        if (isset($row['document'])) {
                            $doc = $row['document'];
                            $data = firestore_decode_fields($doc['fields'] ?? []);
                            $name = $doc['name'] ?? '';
                            $data['id'] = $name ? basename($name) : '';
                            $data['_created'] = $doc['createTime'] ?? null;
                            
                            // Check if this report is newer than last check
                            $reportTime = $data['timestamp'] ?? '';
                            if ($reportTime) {
                                $reportSeconds = is_array($reportTime) && isset($reportTime['_seconds']) ? $reportTime['_seconds'] : strtotime($reportTime);
                                $lastCheckSeconds = $lastCheckTime ? strtotime($lastCheckTime) : 0;
                                
                                if ($reportSeconds > $lastCheckSeconds) {
                                    $newReports[] = [
                                        'category' => $category,
                                        'report' => $data
                                    ];
                                }
                            }
                        }
                    }
                }
            }
            
            echo json_encode([
                'success' => true,
                'hasNew' => !empty($newReports),
                'data' => $newReports,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            error_log('Error in check_new_reports: ' . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'Failed to check for new reports: ' . $e->getMessage()
            ]);
        } catch (Error $e) {
            error_log('Fatal error in check_new_reports: ' . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'System error occurred while checking for new reports'
            ]);
        }
        exit();
    }


    echo json_encode($response);
    exit();
}


# --- DATA FETCHING FOR VIEW (OPTIMIZED) ---
$adminStats = [];
$pendingUsers = [];
$page_error_message = null; // Variable to hold any page-level error messages

// OPTIMIZATION: Defer all heavy data loading to AJAX for faster page loads
// This ensures pages load quickly and data is fetched asynchronously


$cards = [];
// Only pull staff profile and cards for staff users
if (!$isAdmin) {
    $profile  = get_user_profile($userId);
    $assigned = array_values(array_filter(array_map('strval', $profile['categories'] ?? [])));
    // OPTIMIZATION: Don't load heavy data during initial page render
    // The cards will be loaded via AJAX after the page loads
    $cards = []; // Empty initially, will be populated via AJAX
} else {
    // For admin, keep assigned list to all categories (used for labels elsewhere)
    $assigned = array_keys($categories);
}
// --- VIEW HELPER FUNCTIONS ---
function fmt_ts($ts): string {
    if ($ts instanceof \Google\Cloud\Core\Timestamp) {
        try { 
            // Convert to Philippines timezone (UTC+8)
            $timestamp = $ts->get();
            if ($timestamp instanceof DateTime) {
                return $timestamp->setTimezone(new DateTimeZone('Asia/Manila'))->format('M j, Y g:i A');
            }
            return '';
        } catch (Throwable $e) { return ''; }
    }
    if (is_array($ts)) {
        // Support Firestore timestamp-like arrays (e.g. ['_seconds'=>..., '_nanoseconds'=>...])
        $sec = null;
        if (isset($ts['_seconds']) && is_numeric($ts['_seconds'])) $sec = (int)$ts['_seconds'];
        elseif (isset($ts['seconds']) && is_numeric($ts['seconds'])) $sec = (int)$ts['seconds'];
        if ($sec !== null) {
            try {
                $dt = (new DateTimeImmutable('@' . $sec))->setTimezone(new DateTimeZone('Asia/Manila'));
                return $dt->format('M j, Y g:i A');
            } catch (Throwable $e) {
                return '';
            }
        }
    }
    if (is_string($ts)) {
        try { 
            // Parse the timestamp and convert to Philippines timezone
            $dateTime = new DateTimeImmutable($ts);
            return $dateTime->setTimezone(new DateTimeZone('Asia/Manila'))->format('M j, Y g:i A'); 
        } catch (Throwable $e) { return htmlspecialchars($ts); }
    }
    return '';
}


function render_report_table(array $list, string $collection, array $categories) {
    if (empty($list)) {
        echo '<div class="text-center py-16 animate-fade-in-up"><p class="text-slate-500">No reports in this list. ✨</p></div>';
        return;
    }

    $slug = '';
    foreach ($categories as $catSlug => $meta) {
        if ($meta['collection'] === $collection) {
            $slug = $catSlug;
            break;
        }
    }

    echo '<div class="overflow-x-auto table-premium"><table class="min-w-full text-sm">';
    echo '<thead><tr>
            <th class="p-4 text-left font-semibold text-slate-500 uppercase tracking-wider">Reporter Details</th>
            <th class="p-4 text-left font-semibold text-slate-500 uppercase tracking-wider">Location</th>
            <th class="p-4 text-left font-semibold text-slate-500 uppercase tracking-wider">Timestamp</th>
            <th class="p-4 text-left font-semibold text-slate-500 uppercase tracking-wider">Status</th>
            <th class="p-4 text-right font-semibold text-slate-500 uppercase tracking-wider">Actions</th>
            </tr></thead><tbody class="divide-y divide-slate-200/50">';
    foreach ($list as $i => $it) {
        $stRaw = strtolower((string)($it['status'] ?? ''));
        $st = ($stRaw === 'faile') ? 'failed' : $stRaw;
        $displayStatus = ($st === 'failed') ? 'Failed' : ($it['status'] ?: 'Pending');
        $isApproved = ($st === 'approved');
        $isDeclined = ($st === 'declined' || $st === 'failed');
        $isResponded = ($st === 'responded');
        $isFinal = $isApproved || $isDeclined || $isResponded;
        $tDisplay = fmt_ts($it['timestamp']);
        $imgUrl = $it['imageUrl'] ?? '';
        
        $statusClass = 'status-badge-pending';
        if ($isApproved) $statusClass = 'status-badge-success';
        if ($isDeclined) $statusClass = 'status-badge-declined';

        // Check if this is an urgent report
        $isUrgent = ($it['priority'] ?? '') === 'HIGH';
        $urgentClass = $isUrgent ? 'bg-red-50/50 border-l-4 border-l-red-500' : '';
        $urgentIcon = $isUrgent ? '🚨 ' : '';

        $animDelay = 'style="--anim-delay: '.($i * 50).'ms"';

        echo "<tr class='report-row animate-fade-in-up {$urgentClass}' {$animDelay} data-id='".htmlspecialchars($it['id'])."' data-collection='".htmlspecialchars($collection)."'>";
        echo '<td class="p-4 whitespace-nowrap"><div class="font-semibold text-slate-800">'.$urgentIcon.htmlspecialchars($it['fullName'] ?: '—').'</div><div class="text-slate-500">'.htmlspecialchars(($it['mobileNumber'] ?? $it['contact']) ?: '—').'</div>'.($isUrgent ? '<div class="text-xs text-red-600 font-medium mt-1">⚡ HIGH PRIORITY</div>' : '').'</td>';
        echo '<td class="p-4 text-slate-600 max-w-xs truncate">'.htmlspecialchars($it['location'] ?: '—').'</td>';
        echo '<td class="p-4 text-slate-600 whitespace-nowrap">'.$tDisplay.'</td>';
        echo '<td class="p-4"><span class="status-badge '.$statusClass.'"><span class="h-2 w-2 rounded-full bg-current mr-2"></span>'.htmlspecialchars($displayStatus).'</span>'.($isUrgent ? '<div class="text-xs text-red-600 font-medium mt-1">🚨 URGENT</div>' : '').'</td>';
        
        echo '<td class="p-4 text-right"><div class="inline-flex items-center gap-2">';
        echo '<button type="button" class="btn btn-view" title="View Details"
                    onclick="showReportModal(this)"
                    data-slug="'.htmlspecialchars($slug).'"
                    data-id="'.htmlspecialchars($it['id']).'"
                    data-collection="'.htmlspecialchars($collection).'"
                    data-fullname="'.htmlspecialchars($it['fullName'] ?? '').'" data-contact="'.htmlspecialchars($it['mobileNumber'] ?? $it['contact'] ?? '').'"
                    data-location="'.htmlspecialchars($it['location'] ?? '').'"'.
                    (($collection === 'tanod_reports' || $collection === 'other_reports') ? ' data-purpose="'.htmlspecialchars($it['purpose'] ?? $it['description'] ?? '', ENT_QUOTES).'"' : ' data-purpose=""').
                    ' data-status="'.htmlspecialchars($displayStatus).'" data-timestamp="'.htmlspecialchars($tDisplay).'"'.
                    ' data-reporterid="'.htmlspecialchars($it['reporterId'] ?? '').'" data-imageurl="'.htmlspecialchars($imgUrl).'">
                    '.svg_icon('eye', 'w-4 h-4').'<span>View</span>
                  </button>';
        $approveBtnClass = $isFinal ? 'btn-disabled' : 'btn-approve';
        echo '<button type="button" class="btn '.$approveBtnClass.'" '.($isFinal ? 'disabled' : '').' title="Approve Report" onclick="showApproveConfirmation(\''.htmlspecialchars($collection).'\', \''.htmlspecialchars($it['id']).'\', \''.htmlspecialchars($it['fullName']).'\', \''.htmlspecialchars($slug).'\')" '.($isFinal ? '' : '').'>
                '.svg_icon('check-circle', 'w-4 h-4').'<span>Approve</span>
              </button>';
        $declineBtnClass = $isFinal ? 'btn-disabled' : 'btn-decline';
        echo '<button type="button" class="btn '.$declineBtnClass.'" '.($isFinal ? 'disabled' : '').' title="Decline Report" onclick="showDeclineConfirmation(\''.htmlspecialchars($collection).'\', \''.htmlspecialchars($it['id']).'\', \''.htmlspecialchars($it['fullName']).'\', \''.htmlspecialchars($slug).'\')" '.($isFinal ? '' : '').'>
                '.svg_icon('x-circle', 'w-4 h-4').'<span>Decline</span>
              </button>';
        echo '</div></td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}

function render_user_verification_table(array $users) {
    if (empty($users)) {
        echo '<div class="text-center py-16"><p class="text-slate-500">No pending user registrations. ✨</p></div>';
        return;
    }

    echo '<div class="overflow-x-auto table-premium"><table class="min-w-full text-sm">';
    echo '<thead><tr>
            <th class="p-4 text-left font-semibold text-slate-500 uppercase tracking-wider">User Details</th>
            <th class="p-4 text-left font-semibold text-slate-500 uppercase tracking-wider">Contact</th>
            <th class="p-4 text-left font-semibold text-slate-500 uppercase tracking-wider">Info</th>
            <th class="p-4 text-right font-semibold text-slate-500 uppercase tracking-wider">Actions</th>
            </tr></thead><tbody class="divide-y divide-slate-200/50">';
    
    foreach ($users as $i => $user) {
        $uid = $user['id'] ?? '';
        if (!$uid) continue; // Skip if no ID
        
        $fullName = htmlspecialchars($user['fullName'] ?? '—');
        $username = htmlspecialchars($user['username'] ?? '—');
        $email = htmlspecialchars($user['email'] ?? '—');
        // Updated: prefer mobileNumber then legacy contact; add leading 0 if 10-digit PH mobile missing it
        $contactRaw = $user['mobileNumber'] ?? $user['contact'] ?? '';
        if ($contactRaw && strlen($contactRaw) === 10 && $contactRaw[0] === '9') {
            $contactRaw = '0' . $contactRaw; // normalize to 11-digit format
        }
        $contact = htmlspecialchars($contactRaw ?: '—');
        $address = htmlspecialchars($user['address'] ?? '—');
        $birthdate = htmlspecialchars($user['birthdate'] ?? '—');
        $proofPath = $user['proofOfResidencyPath'] ?? '';
        $proofUrl = $proofPath ? 'proof_proxy.php?path=' . urlencode($proofPath) . '&user=' . urlencode($uid) : '';

        $animDelay = 'style="--anim-delay: '.($i * 50).'ms"';

        echo "<tr class='user-row animate-fade-in-up' {$animDelay} data-uid='{$uid}'>";
        echo "<td class='p-4 whitespace-nowrap'>
                <div class='font-semibold text-slate-800'>{$fullName}</div>
                <div class='text-slate-500 font-mono text-xs'>@{$username}</div>
              </td>";
        echo "<td class='p-4 whitespace-nowrap'>
                <div class='text-slate-800'>{$contact}</div>
                <div class='text-slate-500 text-xs'>{$email}</div>
              </td>";
        echo "<td class='p-4 max-w-sm'>
                <div class='text-slate-800 truncate' title='{$address}'>{$address}</div>
                <div class='text-slate-500 text-xs'>Born: {$birthdate}</div>
              </td>";
        
        echo '<td class="p-4 text-right"><div class="inline-flex items-center gap-2">';
        
        if ($proofUrl) {
            echo '<button type="button" class="btn btn-view" title="View Proof of Residency"
                    onclick="showProofModal(this)"
                    data-fullname="'.htmlspecialchars($user['fullName'] ?? '').'"
                    data-proofurl="'.htmlspecialchars($proofUrl).'">
                    '.svg_icon('eye', 'w-4 h-4').'<span>View Proof</span>
                  </button>';
        }

        echo '<form class="inline-flex" onsubmit="handleUserVerification(event)">
                <input type="hidden" name="uid" value="'.htmlspecialchars($uid).'">
                <input type="hidden" name="newStatus" value="approved">
                <button type="submit" class="btn btn-approve" title="Approve Registration">
                    '.svg_icon('check-circle', 'w-4 h-4').'<span>Approve</span>
                </button>
              </form>';
        
        echo '<form class="inline-flex" onsubmit="handleUserVerification(event)">
                <input type="hidden" name="uid" value="'.htmlspecialchars($uid).'">
                <input type="hidden" name="newStatus" value="rejected">
                <button type="submit" class="btn btn-decline" title="Reject Registration">
                    '.svg_icon('x-circle', 'w-4 h-4').'<span>Reject</span>
                </button>
              </form>';

        echo '</div></td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}



// REST API functions for getting pending users
function get_pending_users_rest(int $pageSize, int $offset): array {
    try {
        $url = firestore_base_url() . ':runQuery';
        // Try both 'accountStatus' and 'status' fields for backward compatibility
        $body = [
            'structuredQuery' => [
                'from' => [['collectionId' => 'users']],
                'where' => [
                    'compositeFilter' => [
                        'op' => 'OR',
                        'filters' => [
                            [
                                'fieldFilter' => [
                                    'field' => ['fieldPath' => 'accountStatus'],
                                    'op' => 'EQUAL',
                                    'value' => firestore_encode_value('pending')
                                ]
                            ],
                            [
                                'fieldFilter' => [
                                    'field' => ['fieldPath' => 'status'],
                                    'op' => 'EQUAL',
                                    'value' => firestore_encode_value('pending')
                                ]
                            ]
                        ]
                    ]
                ],
                'limit' => $pageSize + $offset // Get enough documents to handle offset
            ]
        ];
        
        $response = firestore_rest_request('POST', $url, $body);
        $allUsers = [];
        
        if (is_array($response)) {
            foreach ($response as $row) {
                if (!isset($row['document'])) continue;
                $doc = $row['document'];
                $userData = firestore_decode_fields($doc['fields'] ?? []);
                $userData['id'] = basename($doc['name'] ?? '');
                $allUsers[] = $userData;
            }
        }
        
        // Sort by fullName and apply offset and limit manually
        usort($allUsers, function($a, $b) {
            return strcasecmp($a['fullName'] ?? '', $b['fullName'] ?? '');
        });
        $users = array_slice($allUsers, $offset, $pageSize);
        
        // Get total count - using 'accountStatus' instead of 'status'
        $total = firestore_count('users', 'accountStatus', 'pending');
        
        return ['users' => $users, 'total' => $total];
    } catch (Exception $e) {
        error_log("Error in get_pending_users_rest: " . $e->getMessage());
        return ['users' => [], 'total' => 0, 'error' => $e->getMessage()];
    }
}



// Search pending users using REST API
function search_pending_users_rest(string $search, int $pageSize, int $offset): array {
    try {
        // Get all pending users (since Firestore doesn't support full-text search)
        $url = firestore_base_url() . ':runQuery';
        // Try both 'accountStatus' and 'status' fields for backward compatibility
        $body = [
            'structuredQuery' => [
                'from' => [['collectionId' => 'users']],
                'where' => [
                    'compositeFilter' => [
                        'op' => 'OR',
                        'filters' => [
                            [
                                'fieldFilter' => [
                                    'field' => ['fieldPath' => 'accountStatus'],
                                    'op' => 'EQUAL',
                                    'value' => firestore_encode_value('pending')
                                ]
                            ],
                            [
                                'fieldFilter' => [
                                    'field' => ['fieldPath' => 'status'],
                                    'op' => 'EQUAL',
                                    'value' => firestore_encode_value('pending')
                                ]
                            ]
                        ]
                    ]
                ],
                'limit' => 1000 // Get more for search filtering
            ]
        ];
        
        $response = firestore_rest_request('POST', $url, $body);
        $allUsers = [];
        
        if (is_array($response)) {
            foreach ($response as $row) {
                if (!isset($row['document'])) continue;
                $doc = $row['document'];
                $userData = firestore_decode_fields($doc['fields'] ?? []);
                $userData['id'] = basename($doc['name'] ?? '');
                $allUsers[] = $userData;
            }
        }
        
        // Filter users based on search term - updated to include new fields
        $filteredUsers = array_filter($allUsers, function($user) use ($search) {
            $searchableText = strtolower(
                ($user['fullName'] ?? '') . ' ' .
                ($user['firstName'] ?? '') . ' ' .
                ($user['lastName'] ?? '') . ' ' .
                ($user['middleName'] ?? '') . ' ' .
                ($user['email'] ?? '') . ' ' .
                ($user['mobileNumber'] ?? '') . ' ' .
                ($user['contact'] ?? '') // Fallback for old data
            );
            return strpos($searchableText, $search) !== false;
        });
        
        // Convert to indexed array, sort, and apply pagination
        $filteredUsers = array_values($filteredUsers);
        usort($filteredUsers, function($a, $b) {
            return strcasecmp($a['fullName'] ?? '', $b['fullName'] ?? '');
        });
        $total = count($filteredUsers);
        $users = array_slice($filteredUsers, $offset, $pageSize);
        
        return ['users' => $users, 'total' => $total];
    } catch (Exception $e) {
        error_log("Error in search_pending_users_rest: " . $e->getMessage());
        return ['users' => [], 'total' => 0, 'error' => $e->getMessage()];
    }
}

// --- RENDER VIEW ---
include __DIR__ . '/views/dashboard_layout.php';
