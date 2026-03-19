<?php
ob_start(); // Start output buffering immediately to catch any stray output

// Prevent browser caching of dashboard page to ensure latest JS/HTML
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$__reqStart = microtime(true);

require_once __DIR__.'/db_config.php';

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
$allowedViews = ['dashboard', 'live-support', 'map', 'analytics'];
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
                    'lat'          => $it['latitude'] ?? ($it['coordinates']['latitude'] ?? null),
                    'lng'          => $it['longitude'] ?? ($it['coordinates']['longitude'] ?? null),
                    'latitude'     => $it['latitude'] ?? ($it['coordinates']['latitude'] ?? null),
                    'longitude'    => $it['longitude'] ?? ($it['coordinates']['longitude'] ?? null),
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

        // Surface docs created without timestamp fields (common root-cause for "new report not showing").
        // We can't order by createTime, but IS_NULL queries keep this set small in practice.
        foreach (['timestamp', 'createdAt'] as $nullField) {
            $bodyNull = [
                'structuredQuery' => [
                    'from' => [['collectionId' => $collection]],
                    'select' => ['fields' => $selectFields],
                    'where' => [
                        'unaryFilter' => [
                            'op' => 'IS_NULL',
                            'field' => ['fieldPath' => $nullField],
                        ]
                    ],
                    'limit' => $nullLimit,
                ]
            ];

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $runQueryUrl,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($bodyNull),
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

    // Admin: Get Staff Data
    if ($isAdmin && $action === 'get_staff_data') {
        try {
            // Get all users with role 'staff' using REST API
            $url = firestore_base_url() . ':runQuery';
            $body = [
                'structuredQuery' => [
                    'from' => [['collectionId' => 'users']],
                    'where' => [
                        'fieldFilter' => [
                            'field' => ['fieldPath' => 'role'],
                            'op' => 'EQUAL',
                            'value' => firestore_encode_value('staff')
                        ]
                    ]
                ]
            ];

            $response = firestore_rest_request('POST', $url, $body);
            $staffUsers = [];

            if (isset($response[0]['document'])) {
                foreach ($response as $doc) {
                    if (isset($doc['document'])) {
                        $userId = basename($doc['document']['name']);
                        $userData = firestore_decode_fields($doc['document']['fields'] ?? []);
                        $staffUsers[$userId] = $userData;
                    }
                }
            }

            $totalStaff = 0;
            $activeStaff = 0;
            $reportsAssigned = 0;
            $staffList = [];

            if ($staffUsers) {
                foreach ($staffUsers as $userId => $userData) {
                    $totalStaff++;

                    // Check if staff is active (not disabled/removed)
                    if (isset($userData['status']) && $userData['status'] === 'approved') {
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
                        'status' => $userData['status'] ?? 'inactive',
                        'categories' => $userData['categories'] ?? [],
                        'createdAt' => $userData['createdAt'] ?? null,
                        'lastLogin' => $userData['lastLogin'] ?? null
                    ];
                }
            }

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
                
                // Use cache with tiny TTL (1 second)
                $cachedData = cache_get($cacheKey, 1);
                $cacheHit = ($cachedData !== null);
                
                if ($cachedData === null) {
                    $allRecent = empty($categories) ? [] : build_recent_feed_ultra_fast_runquery($categories, $categoryFilter, $statusFilter, $search, 10);
                    cache_set($cacheKey, $allRecent, 1);
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
        } catch (Exception $e) {
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
            $adminStats = cache_get($cacheKey, 60); // 60-second cache for better performance
            
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
                
                // Get optimized counts for all collections (using fallback to working method)
                $countResults = get_admin_stats_counts_fallback($collections);
                
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
                
                // Cache the results for 60 seconds
                cache_set($cacheKey, $adminStats, 60);
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
                $grand = ['total' => 0, 'pending' => 0, 'approved' => 0, 'declined' => 0, 'responded' => 0];

                foreach ($allowedSlugs as $slug) {
                    $col = $categories[$slug]['collection'];
                    $row = [
                        'total'     => (int)($countResults[$col]['total'] ?? 0),
                        'pending'   => (int)($countResults[$col]['pending'] ?? 0),
                        'approved'  => (int)($countResults[$col]['approved'] ?? 0),
                        'declined'  => (int)($countResults[$col]['declined'] ?? 0),
                        'responded' => (int)($countResults[$col]['responded'] ?? 0),
                    ];
                    $bySlug[$slug] = $row;
                    $grand['total'] += $row['total'];
                    $grand['pending'] += $row['pending'];
                    $grand['approved'] += $row['approved'];
                    $grand['declined'] += $row['declined'];
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
            $respondedTotal = 0;
            $totalReports = 0;

            foreach ($allowedSlugs as $slug) {
                $meta = $categories[$slug];
                $col = $meta['collection'];
                $total = (int)($countResults[$col]['total'] ?? 0);
                $responded = (int)($countResults[$col]['responded'] ?? 0);
                $totalReports += $total;
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
        
        try {
            $profile  = get_user_profile($userId);
            $assigned = array_values(array_filter(array_map('strval', $profile['categories'] ?? [])));
            
            $cards = [];
            foreach ($assigned as $slug) {
                if (!isset($categories[$slug])) continue;
                $reports = list_latest_reports($categories[$slug]['collection'], 50);
                
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

function svg_icon(string $name, string $class = 'w-6 h-6') {
    $path = [
        // ...existing icon paths...
        'sun' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25M12 18.75V21M4.219 4.219l1.591 1.591M18.19 18.19l1.591 1.59M3 12h2.25M18.75 12H21M4.219 19.781l1.591-1.591M18.19 5.81l1.591-1.591M12 8.25a3.75 3.75 0 100 7.5 3.75 3.75 0 000-7.5z" />',
        'moon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 1111 2.25a8.25 8.25 0 0010.752 12.752z" />',
        'download' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M7.5 10.5L12 15m0 0l4.5-4.5M12 15V3" />',
    ][$name] ?? ([
        // keep original mapping
        'dashboard' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />',
        'logout' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />',
        'truck' => '<path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.125-.504 1.125-1.125V14.25m-17.25 4.5v-1.875a3.375 3.375 0 003.375-3.375h1.5a1.125 1.125 0 011.125 1.125v-1.5a3.375 3.375 0 00-3.375-3.375H9.75V7.5h1.5a3.375 3.375 0 013.375 3.375v1.5a1.125 1.125 0 001.125 1.125h1.5a3.375 3.375 0 003.375-3.375V7.5a1.125 1.125 0 00-1.125-1.125H5.625" />',
        'shield-check' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.286zm0 13.036h.008v.017h-.008v-.017z" />',
        'fire' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15.362 5.214A8.252 8.252 0 0112 21 8.25 8.25 0 016.038 7.048 8.287 8.287 0 009 9.6a8.983 8.983 0 013.362-6.867 8.268 8.268 0 013 2.481z" /><path stroke-linecap="round" stroke-linejoin="round" d="M12 18a3.75 3.75 0 00.495-7.467 5.99 5.99 0 00-1.925 3.546 5.974 5.974 0 01-2.133-1.001A3.75 3.75 0 0012 18z" />',
        'home' => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h7.5" />',
        'question-mark-circle' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" />',
        'user-plus' => '<path stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.5a3 3 0 11-6 0 3 3 0 016 0zM4 18.75v-1.5a6.75 6.75 0 017.5-6.75h.5a6.75 6.75 0 016.75 6.75v1.5a6.75 6.75 0 01-6.75 6.75H9.75V21h7.5" />',
        'user-shield' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />',
        'user-check' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" /><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75" />',
        'spinner' => '<path d="M21 12a9 9 0 11-6.219-8.56" />',
        'x-mark' => '<path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />',
        'check' => '<path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />',
        'info' => '<path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.852l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />',
        'eye' => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />',
        'check-circle' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />',
        'x-circle' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />',
        'identification' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5zm6-10.125a1.875 1.875 0 11-3.75 0 1.875 1.875 0 013.75 0zm1.294 6.336a6.721 6.721 0 01-3.17.789 6.721 6.721 0 01-3.168-.789 3.376 3.376 0 016.338 0z" />',
        'user-circle' => '<path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z" />',
        'chat' => '<path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" />',
        'paper-airplane' => '<path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />',
        'map-pin' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />',
    ][$name] ?? '');
    return '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="'.$class.'">'.$path.'</svg>';
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
        $st = strtolower((string)($it['status'] ?? ''));
        $displayStatus = $it['status'] ?: 'Pending';
        $isApproved = ($st === 'approved');
        $isDeclined = ($st === 'declined');
        $isFinal = $isApproved || $isDeclined;
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ManResponde • Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php echo csrf_meta(); ?>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="responde.png">
    <link rel="icon" type="image/png" sizes="16x16" href="responde.png">
    <link rel="apple-touch-icon" href="responde.png">
    <link rel="shortcut icon" href="responde.png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Leaflet Map -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        /* ========================================
           PREMIUM DESIGN SYSTEM - PROFESSIONAL DASHBOARD
           Sophisticated Color Palette | Aurora Effects | Glassmorphism
           ======================================== */
        
        :root {
            /* === PREMIUM COLOR PALETTE === */
            --primary: #2563eb;
            --primary-light: #3b82f6;
            --primary-dark: #1d4ed8;
            --accent: #06b6d4;
            --accent-light: #0891b2;
            --success: #10b981;
            --success-light: #34d399;
            --warning: #f59e0b;
            --warning-light: #fbbf24;
            --danger: #ef4444;
            --danger-light: #f87171;
            
            /* === SOPHISTICATED NEUTRALS === */
            --white: #ffffff;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
            
            /* === PREMIUM SHADOWS === */
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --shadow-2xl: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            --shadow-inner: inset 0 2px 4px 0 rgba(0, 0, 0, 0.06);
            
            /* === GLASSMORPHISM === */
            --glass-bg: rgba(255, 255, 255, 0.75);
            --glass-border: rgba(255, 255, 255, 0.2);
            --glass-blur: blur(20px);
            
            /* === TRANSITIONS === */
            --transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-smooth: 300ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-slow: 500ms cubic-bezier(0.4, 0, 0.2, 1);
            
            /* === BORDER RADIUS === */
            --radius-sm: 0.375rem;
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
            --radius-xl: 1rem;
            --radius-2xl: 1.25rem;
            --radius-3xl: 1.5rem;
        }

        /* === DARK MODE VARIABLES (DISABLED) === */
        /* 
        html.dark {
            --white: #1e293b;
            --gray-50: #334155;
            --gray-100: #475569;
            --gray-200: #64748b;
            --gray-300: #94a3b8;
            --gray-400: #cbd5e1;
            --gray-500: #e2e8f0;
            --gray-600: #f1f5f9;
            --gray-700: #f8fafc;
            --gray-800: #ffffff;
            --gray-900: #ffffff;
            
            --glass-bg: rgba(30, 41, 59, 0.85);
            --glass-border: rgba(255, 255, 255, 0.15);
        }
        */

        /* ========================================
           FOUNDATION STYLES
           ======================================== */
        
        * {
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--gray-50);
            color: var(--gray-900);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            font-feature-settings: "cv02", "cv03", "cv04", "cv11";
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }

        /* ========================================
           STUNNING AURORA BACKGROUND
           ======================================== */
        
        .aurora-background {
            position: relative;
            min-height: 100vh;
            background: linear-gradient(135deg, 
                var(--gray-50) 0%, 
                #fafbff 25%, 
                #f0f4ff 50%, 
                #e8f2ff 75%, 
                var(--gray-100) 100%);
            overflow: hidden;
        }

        .aurora-background::before {
            content: '';
            position: fixed;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: 
                radial-gradient(ellipse 800px 600px at -10% -20%, rgba(59, 130, 246, 0.15), transparent 50%),
                radial-gradient(ellipse 600px 800px at 110% -10%, rgba(16, 185, 129, 0.12), transparent 50%),
                radial-gradient(ellipse 700px 500px at 50% 120%, rgba(139, 92, 246, 0.1), transparent 50%),
                radial-gradient(ellipse 500px 400px at 80% 80%, rgba(6, 182, 212, 0.08), transparent 50%);
            animation: aurora-drift 20s ease-in-out infinite alternate;
            z-index: -1;
        }

        .aurora-background::after {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(ellipse 400px 300px at 30% 70%, rgba(236, 72, 153, 0.08), transparent 40%),
                radial-gradient(ellipse 300px 400px at 70% 30%, rgba(245, 158, 11, 0.06), transparent 40%);
            animation: aurora-pulse 15s ease-in-out infinite alternate-reverse;
            z-index: -1;
        }

        @keyframes aurora-drift {
            0% { transform: translateX(0) translateY(0) rotate(0deg); }
            50% { transform: translateX(100px) translateY(-50px) rotate(180deg); }
            100% { transform: translateX(0) translateY(0) rotate(360deg); }
        }

        @keyframes aurora-pulse {
            0% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.8; transform: scale(1.1); }
            100% { opacity: 1; transform: scale(1); }
        }

        /* ========================================
           PREMIUM GLASSMORPHISM COMPONENTS
           ======================================== */
        
        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-2xl);
            box-shadow: var(--shadow-xl), 
                        0 0 0 1px rgba(255, 255, 255, 0.05),
                        inset 0 1px 0 rgba(255, 255, 255, 0.1);
            position: relative;
            overflow: hidden;
            transition: all var(--transition-smooth);
        }

        .glass-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, 
                transparent, 
                rgba(255, 255, 255, 0.4), 
                transparent);
        }

        .glass-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-2xl), 
                        0 0 0 1px rgba(255, 255, 255, 0.1),
                        inset 0 1px 0 rgba(255, 255, 255, 0.15);
        }

        /* ========================================
           SOPHISTICATED KPI CARDS
           ======================================== */
        
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .kpi-card {
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-xl);
            padding: 1.75rem;
            position: relative;
            overflow: hidden;
            transition: all var(--transition-smooth);
            box-shadow: var(--shadow-lg),
                        0 0 0 1px rgba(255, 255, 255, 0.05);
        }

        .kpi-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, 
                var(--primary) 0%, 
                var(--accent) 50%, 
                var(--success) 100%);
            opacity: 0.8;
        }

        .kpi-card:hover {
            transform: translateY(-4px) scale(1.02);
            box-shadow: var(--shadow-2xl),
                        0 0 0 1px rgba(255, 255, 255, 0.1),
                        0 0 50px rgba(37, 99, 235, 0.15);
        }

        .kpi-label {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--gray-500);
            margin-bottom: 0.5rem;
            display: block;
        }

        .kpi-value {
            font-size: 2.25rem;
            font-weight: 800;
            letter-spacing: -0.025em;
            color: var(--gray-900);
            line-height: 1;
            margin-bottom: 0.75rem;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .kpi-spark {
            height: 32px;
            margin-top: 0.75rem;
            opacity: 0.8;
        }

        /* ========================================
           ELEVATED STAT CARDS
           ======================================== */
        
        .stat-card {
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-xl);
            padding: 2rem;
            position: relative;
            overflow: hidden;
            transition: all var(--transition-smooth);
            box-shadow: var(--shadow-lg),
                        0 0 0 1px rgba(255, 255, 255, 0.05);
        }

        .stat-card::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: conic-gradient(from 0deg, transparent, rgba(37, 99, 235, 0.03), transparent);
            animation: stat-card-rotate 20s linear infinite;
            z-index: -1;
        }

        .stat-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-2xl),
                        0 0 0 1px rgba(255, 255, 255, 0.1),
                        0 0 60px rgba(37, 99, 235, 0.2);
        }

        @keyframes stat-card-rotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* ========================================
           PREMIUM BUTTON SYSTEM
           ======================================== */
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1.25rem;
            font-size: 0.875rem;
            font-weight: 600;
            line-height: 1;
            border-radius: var(--radius-lg);
            border: 1px solid transparent;
            cursor: pointer;
            text-decoration: none;
            transition: all var(--transition-fast);
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left var(--transition-smooth);
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-lg);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: var(--white);
            box-shadow: var(--shadow-md), 0 0 20px rgba(37, 99, 235, 0.3);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary));
            box-shadow: var(--shadow-xl), 0 0 30px rgba(37, 99, 235, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success), var(--success-light));
            color: var(--white);
            box-shadow: var(--shadow-md), 0 0 20px rgba(16, 185, 129, 0.3);
        }

        .btn-success:hover {
            background: linear-gradient(135deg, #059669, var(--success));
            box-shadow: var(--shadow-xl), 0 0 30px rgba(16, 185, 129, 0.4);
        }

        .btn-warning {
            background: linear-gradient(135deg, var(--warning), var(--warning-light));
            color: var(--white);
            box-shadow: var(--shadow-md), 0 0 20px rgba(245, 158, 11, 0.3);
        }

        .btn-warning:hover {
            background: linear-gradient(135deg, #d97706, var(--warning));
            box-shadow: var(--shadow-xl), 0 0 30px rgba(245, 158, 11, 0.4);
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--danger), var(--danger-light));
            color: var(--white);
            box-shadow: var(--shadow-md), 0 0 20px rgba(239, 68, 68, 0.3);
        }

        .btn-danger:hover {
            background: linear-gradient(135deg, #dc2626, var(--danger));
            box-shadow: var(--shadow-xl), 0 0 30px rgba(239, 68, 68, 0.4);
        }

        .btn-info {
            background: linear-gradient(135deg, var(--accent), #0891b2);
            color: var(--white);
            box-shadow: var(--shadow-md), 0 0 20px rgba(6, 182, 212, 0.3);
        }

        .btn-info:hover {
            background: linear-gradient(135deg, #0e7490, var(--accent));
            box-shadow: var(--shadow-xl), 0 0 30px rgba(6, 182, 212, 0.4);
        }

        .btn-secondary {
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
            border: 1px solid var(--glass-border);
            color: var(--gray-700);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.9);
            border-color: var(--gray-300);
        }

        /* Legacy button compatibility */
        .btn-view { background: linear-gradient(135deg, #fbbf24, #f59e0b); color: var(--white); }
        .btn-approve { background: linear-gradient(135deg, var(--success), var(--success-light)); color: var(--white); }
        .btn-decline { background: linear-gradient(135deg, var(--danger), var(--danger-light)); color: var(--white); }
        .btn-confirm { background: linear-gradient(135deg, var(--success), var(--success-light)); color: var(--white); }
        .btn-disabled { background: var(--gray-200); color: var(--gray-400); cursor: not-allowed; box-shadow: none; }

        /* ========================================
           PREMIUM INPUT SYSTEM
           ======================================== */
        
        .input, .activity-filter input, .activity-filter select,
        #createStaffForm input, #createStaffForm select,
        #createResponderForm input, #createResponderForm select,
        #vuSearch, #vuPageSize {
            width: 100%;
            padding: 0.875rem 1rem;
            font-size: 0.875rem;
            font-weight: 500;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-lg);
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
            color: var(--gray-900);
            transition: all var(--transition-fast);
            box-shadow: var(--shadow-sm), inset 0 1px 2px rgba(0, 0, 0, 0.05);
            outline: none;
        }

        .input:focus, .activity-filter input:focus, .activity-filter select:focus,
        #createStaffForm input:focus, #createStaffForm select:focus,
        #createResponderForm input:focus, #createResponderForm select:focus,
        #vuSearch:focus, #vuPageSize:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1),
                        var(--shadow-md);
            transform: translateY(-1px);
        }

        .input-group {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 0.875rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
            display: inline-flex;
            z-index: 1;
        }

        .input.with-icon {
            padding-left: 2.75rem;
        }

        .input-premium {
            background: var(--glass-bg) !important;
            backdrop-filter: var(--glass-blur) !important;
            -webkit-backdrop-filter: var(--glass-blur) !important;
            border: 1px solid var(--glass-border) !important;
            border-radius: var(--radius-xl) !important;
        }

        .input-premium:focus {
            border-color: var(--primary) !important;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.15) !important;
        }

        /* ========================================
           SOPHISTICATED TABLE STYLING
           ======================================== */
        
        .table-premium table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
            border-radius: var(--radius-xl);
            overflow: hidden;
            box-shadow: var(--shadow-lg);
        }

        .table-premium thead th {
            background: linear-gradient(135deg, 
                rgba(37, 99, 235, 0.05), 
                rgba(6, 182, 212, 0.05));
            color: var(--gray-700);
            font-weight: 700;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            position: relative;
        }

        .table-premium thead th::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            opacity: 0.6;
        }

        .table-premium tbody td {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid rgba(148, 163, 184, 0.1);
            transition: all var(--transition-fast);
        }

        .table-premium tbody tr {
            transition: all var(--transition-fast);
        }

        .table-premium tbody tr:nth-child(even) {
            background: rgba(248, 250, 252, 0.4);
        }

        .table-premium tbody tr:hover {
            background: rgba(37, 99, 235, 0.08);
            transform: scale(1.01);
        }

        /* ========================================
           ELEGANT CUSTOM CHECKBOX
           ======================================== */
        
        .custom-checkbox {
            position: relative;
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-lg);
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
            cursor: pointer;
            transition: all var(--transition-smooth);
            user-select: none;
            box-shadow: var(--shadow-sm);
        }

        .custom-checkbox:hover {
            border-color: var(--primary);
            background: rgba(37, 99, 235, 0.05);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .custom-checkbox input[type="checkbox"] {
            position: absolute;
            inset: 0;
            margin: 0;
            opacity: 0;
            cursor: pointer;
        }

        .custom-checkbox .box {
            width: 24px;
            height: 24px;
            border-radius: var(--radius-sm);
            border: 2px solid var(--gray-300);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--white);
            transition: all var(--transition-fast);
            flex-shrink: 0;
            position: relative;
            overflow: hidden;
        }

        .custom-checkbox .box::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, var(--primary), var(--accent));
            transform: translate(-50%, -50%) scale(0);
            transition: transform var(--transition-fast);
            border-radius: 50%;
        }

        .custom-checkbox .box svg {
            width: 16px;
            height: 16px;
            color: var(--white);
            opacity: 0;
            transform: scale(0.6);
            transition: all var(--transition-fast);
            z-index: 1;
        }

        .custom-checkbox input[type="checkbox"]:checked + .box {
            background: var(--primary);
            border-color: var(--primary);
            box-shadow: 0 0 20px rgba(37, 99, 235, 0.4);
        }

        .custom-checkbox input[type="checkbox"]:checked + .box::before {
            transform: translate(-50%, -50%) scale(1);
        }

        .custom-checkbox input[type="checkbox"]:checked + .box svg {
            opacity: 1;
            transform: scale(1);
        }

        .custom-checkbox .text {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray-700);
        }

        /* ========================================
           FLUID ANIMATIONS & MICRO-INTERACTIONS
           ======================================== */
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes fadeInUp {
            from { 
                opacity: 0; 
                transform: translateY(30px);
            }
            to { 
                opacity: 1; 
                transform: translateY(0);
            }
        }

        @keyframes fadeInDown {
            from { 
                opacity: 0; 
                transform: translateY(-30px);
            }
            to { 
                opacity: 1; 
                transform: translateY(0);
            }
        }

        @keyframes fadeOutDown {
            from { 
                opacity: 1; 
                transform: translateY(0);
            }
            to { 
                opacity: 0; 
                transform: translateY(20px);
            }
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(100px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes pulse {
            0%, 100% { 
                opacity: 1;
                transform: scale(1);
            }
            50% { 
                opacity: 0.8;
                transform: scale(1.05);
            }
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        @keyframes bounce {
            0%, 20%, 53%, 80%, 100% {
                animation-timing-function: cubic-bezier(0.215, 0.610, 0.355, 1.000);
                transform: translate3d(0,0,0);
            }
            40%, 43% {
                animation-timing-function: cubic-bezier(0.755, 0.050, 0.855, 0.060);
                transform: translate3d(0, -30px, 0);
            }
            70% {
                animation-timing-function: cubic-bezier(0.755, 0.050, 0.855, 0.060);
                transform: translate3d(0, -15px, 0);
            }
            90% {
                transform: translate3d(0,-4px,0);
            }
        }

        /* Animation Classes */
        .animate-fade-in {
            animation: fadeIn 0.5s ease-out forwards;
        }

        .animate-fade-in-up {
            opacity: 0;
            animation: fadeInUp 0.6s cubic-bezier(0.215, 0.610, 0.355, 1.000) forwards;
            animation-delay: var(--anim-delay, 0ms);
        }

        .animate-fade-in-down {
            opacity: 0;
            animation: fadeInDown 0.6s cubic-bezier(0.215, 0.610, 0.355, 1.000) forwards;
            animation-delay: var(--anim-delay, 0ms);
        }

        .animate-fade-out-down {
            animation: fadeOutDown 0.4s ease-in forwards;
        }

        .animate-slide-in-right {
            opacity: 0;
            animation: slideInRight 0.6s cubic-bezier(0.215, 0.610, 0.355, 1.000) forwards;
            animation-delay: var(--anim-delay, 0ms);
        }

        .animate-pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        .animate-spin {
            animation: spin 1s linear infinite;
        }

        .animate-spin-fast {
            animation: spin 0.8s linear infinite;
        }

        .animate-bounce {
            animation: bounce 1s infinite;
        }

        /* ========================================
           MODAL ENHANCEMENTS
           ======================================== */
        
        .modal-header {
            background: linear-gradient(135deg, 
                rgba(37, 99, 235, 0.05), 
                rgba(6, 182, 212, 0.03));
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
            border-bottom: 1px solid var(--glass-border);
            border-radius: var(--radius-xl) var(--radius-xl) 0 0;
        }

        .modal-content {
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-2xl);
        }

        /* ========================================
           RESPONSIVE DESIGN
           ======================================== */
        
        @media (max-width: 768px) {
            .kpi-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .kpi-card, .stat-card {
                padding: 1.25rem;
            }
            
            .btn {
                padding: 0.625rem 1rem;
                font-size: 0.8125rem;
            }
        }

        /* ========================================
           DARK MODE ENHANCEMENTS (DISABLED)
           ======================================== */
        /*
        html.dark body {
            background: linear-gradient(135deg, 
                #0f172a 0%, 
                #1e293b 25%, 
                #334155 50%, 
                #1e293b 75%, 
                #0f172a 100%);
        }

        html.dark .aurora-background {
            background: linear-gradient(135deg, 
                #0f172a 0%, 
                #1a1f2e 25%, 
                #252a3a 50%, 
                #1a1f2e 75%, 
                #0f172a 100%);
        }

        html.dark .aurora-background::before {
            background: 
                radial-gradient(ellipse 800px 600px at -10% -20%, rgba(59, 130, 246, 0.2), transparent 50%),
                radial-gradient(ellipse 600px 800px at 110% -10%, rgba(16, 185, 129, 0.15), transparent 50%),
                radial-gradient(ellipse 700px 500px at 50% 120%, rgba(139, 92, 246, 0.12), transparent 50%);
        }

        html.dark .kpi-card,
        html.dark .stat-card,
        html.dark .glass-card,
        html.dark .table-premium table,
        html.dark .custom-checkbox {
            background: rgba(15, 23, 42, 0.8);
            border-color: rgba(255, 255, 255, 0.1);
        }

        html.dark .kpi-label {
            color: var(--gray-400);
        }

        html.dark .kpi-value {
            color: var(--gray-100);
        }
        */

        /* ========================================
           UTILITY CLASSES
           ======================================== */
        
        .text-gradient {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .glow {
            box-shadow: 0 0 30px rgba(37, 99, 235, 0.3);
        }

        .glow-success {
            box-shadow: 0 0 30px rgba(16, 185, 129, 0.3);
        }

        .glow-warning {
            box-shadow: 0 0 30px rgba(245, 158, 11, 0.3);
        }

        .glow-danger {
            box-shadow: 0 0 30px rgba(239, 68, 68, 0.3);
        }

        .backdrop-blur {
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
        }

        /* ========================================
           LOADING STATES
           ======================================== */
        
        .loading-shimmer {
            background: linear-gradient(90deg, 
                rgba(255, 255, 255, 0.0) 0%, 
                rgba(255, 255, 255, 0.2) 20%, 
                rgba(255, 255, 255, 0.5) 60%, 
                rgba(255, 255, 255, 0.0) 100%);
            background-size: 200% 100%;
            animation: shimmer 2s infinite;
        }

        @keyframes shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }

        /* ========================================
           ADDITIONAL LEGACY STYLES INTEGRATION
           ======================================== */
        
        @keyframes fadeOutDown {
            from {
                opacity: 1;
                transform: translateY(0);
            }
            to {
                opacity: 0;
                transform: translateY(20px);
            }
        }

        @keyframes pulse { 
            0% { transform: scale(0.9) rotate(0deg); } 
            100% { transform: scale(1.2) rotate(20deg); } 
        }

        /* Stats progress bar */
        .progress-track { 
            height: 10px; 
            width: 100%; 
            background: rgba(148,163,184,0.28); 
            border-radius: 9999px; 
            overflow: hidden; 
        }
        
        .progress-seg { 
            height: 100%; 
            display: inline-block; 
            width: 0%; 
            transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .progress-seg.approved { 
            background: linear-gradient(90deg, #10b981, #34d399); 
        }
        
        .progress-seg.pending { 
            background: linear-gradient(90deg, #f59e0b, #fbbf24); 
        }
        
        .progress-seg.other { 
            background: linear-gradient(90deg, #94a3b8, #cbd5e1); 
        }
        
        .progress-seg.declined { 
            background: linear-gradient(90deg, #ef4444, #f87171); 
        }

        /* Recent Activity */
        .activity-filter { 
            display: grid; 
            gap: 0.5rem; 
            grid-template-columns: 1fr; 
        }
        
        @media (min-width: 768px) { 
            .activity-filter { 
                grid-template-columns: 1fr 180px 160px auto; 
            } 
        }
        
        .activity-scroll { 
            max-height: calc(100vh - 360px); 
            overflow-y: auto; 
            overscroll-behavior: contain; 
        }
        
        .activity-scroll::-webkit-scrollbar { 
            width: 10px; 
        }
        
        .activity-scroll::-webkit-scrollbar-track { 
            background: var(--gray-100); 
            border-radius: 9999px; 
        }
        
        .activity-scroll::-webkit-scrollbar-thumb { 
            background: var(--gray-300); 
            border-radius: 9999px; 
            transition: background var(--transition-fast);
        }
        
        .activity-scroll::-webkit-scrollbar-thumb:hover { 
            background: var(--gray-400); 
        }
        
        /* Video Player Styles */
        #m_video {
            background: #000;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xl);
        }
        
        #m_video::-webkit-media-controls-panel {
            background-color: rgba(0, 0, 0, 0.8);
            border-radius: 0 0 var(--radius-lg) var(--radius-lg);
        }
        
        #m_video::-webkit-media-controls-play-button,
        #m_video::-webkit-media-controls-pause-button {
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 50%;
        }
        
        .video-container {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--gray-50);
            border-radius: var(--radius-xl);
            overflow: hidden;
        }
        
        /* Brand pill for section headers */
        .brand-pill { 
            display: inline-flex; 
            align-items: center; 
            gap: 0.5rem; 
            padding: 0.375rem 0.75rem; 
            border-radius: 9999px; 
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
            border: 1px solid var(--glass-border); 
            font-weight: 700; 
            font-size: 0.75rem; 
            color: var(--gray-700);
            box-shadow: var(--shadow-sm);
            transition: all var(--transition-fast);
        }

        .brand-pill:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        /* ========================================
           PREMIUM ACTIVITY CARDS
           ======================================== */
        
        .activity-card {
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            transition: all var(--transition-smooth);
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .activity-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, 
                var(--primary) 0%, 
                var(--accent) 50%, 
                var(--success) 100%);
            opacity: 0;
            transition: opacity var(--transition-fast);
        }

        .activity-card:hover {
            transform: translateY(-4px) scale(1.02);
            box-shadow: var(--shadow-xl), 
                        0 0 40px rgba(37, 99, 235, 0.15);
        }

        .activity-card:hover::before {
            opacity: 1;
        }

        .activity-icon {
            width: 56px;
            height: 56px;
            border-radius: var(--radius-2xl);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            position: relative;
            box-shadow: var(--shadow-lg);
        }

        .activity-icon::after {
            content: '';
            position: absolute;
            top: -2px;
            right: -2px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid white;
            box-shadow: var(--shadow-sm);
        }

        .activity-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            border: 1px solid;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.9), rgba(248, 250, 252, 0.9));
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            transition: all var(--transition-fast);
        }

        .activity-status-badge .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        .activity-status-approved {
            color: var(--success);
            border-color: rgba(16, 185, 129, 0.2);
        }

        .activity-status-approved .status-dot {
            background: var(--success);
            animation: pulse 2s infinite;
        }

        .activity-status-pending {
            color: var(--warning);
            border-color: rgba(245, 158, 11, 0.2);
        }

        .activity-status-pending .status-dot {
            background: var(--warning);
            animation: pulse 2s infinite;
        }

        .activity-status-declined {
            color: var(--danger);
            border-color: rgba(239, 68, 68, 0.2);
        }

        .activity-status-declined .status-dot {
            background: var(--danger);
            animation: pulse 2s infinite;
        }

        /* Status Badges for Tables */
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            line-height: 1;
        }
        
        .status-badge-success {
            background-color: #dcfce7; /* green-100 */
            color: #166534; /* green-800 */
        }
        
        .status-badge-pending {
            background-color: #fef3c7; /* amber-100 */
            color: #92400e; /* amber-800 */
        }
        
        .status-badge-declined {
            background-color: #fee2e2; /* red-100 */
            color: #991b1b; /* red-800 */
        }

        .activity-meta {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--gray-400);
            font-size: 0.875rem;
        }

        .activity-hover-arrow {
            opacity: 0;
            transform: translateX(-10px);
            transition: all var(--transition-fast);
        }

        .activity-card:hover .activity-hover-arrow {
            opacity: 1;
            transform: translateX(0);
        }

        /* Enhanced scrollbar for activity list */
        .activity-scroll::-webkit-scrollbar {
            width: 12px;
        }

        .activity-scroll::-webkit-scrollbar-track {
            background: rgba(148, 163, 184, 0.1);
            border-radius: 6px;
        }

        .activity-scroll::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border-radius: 6px;
            border: 2px solid transparent;
            background-clip: content-box;
        }

        .activity-scroll::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary));
            background-clip: content-box;
        }
    </style>
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
                <a href="dashboard.php?view=analytics" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?php echo $isAnalyticsActive ? 'bg-sky-100 text-sky-700 font-semibold' : 'hover:bg-slate-50 text-slate-600'; ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" />
                    </svg>
                    <span>Analytics</span>
                </a>
                
                <?php $isMapActive = ($view === 'map'); ?>
                <a href="dashboard.php?view=map" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?php echo $isMapActive ? 'bg-sky-100 text-sky-700 font-semibold' : 'hover:bg-slate-50 text-slate-600'; ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                    </svg>
                    <span>Interactive Map</span>
                </a>

                <?php $isLiveSupportActive = ($view === 'live-support'); ?>
                <a href="dashboard.php?view=live-support" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?php echo $isLiveSupportActive ? 'bg-sky-100 text-sky-700 font-semibold' : 'hover:bg-slate-50'; ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                    </svg>
                    <span>Live Support</span>
                    <span id="liveSupportBadge" class="ml-auto bg-red-500 text-white text-xs font-bold px-2 py-0.5 rounded-full hidden">0</span>
                </a>

                <?php if ($isAdmin): ?>
                <?php $isCreateAccountActive = ($view === 'create-account'); ?>
                <a href="dashboard.php?view=create-account" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?php echo $isCreateAccountActive ? 'bg-sky-100 text-sky-700 font-semibold' : 'hover:bg-slate-50 text-slate-600'; ?>">
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

                        <?php $isAnalyticsActive = ($view === 'analytics'); ?>
                        <a href="dashboard.php?view=analytics" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?php echo $isAnalyticsActive ? 'bg-sky-100 text-sky-700 font-semibold' : 'hover:bg-slate-50 text-slate-600'; ?>">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" />
                            </svg>
                            <span>Analytics</span>
                        </a>
                        
                        <?php $isMapActive = ($view === 'map'); ?>
                        <a href="dashboard.php?view=map" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?php echo $isMapActive ? 'bg-sky-100 text-sky-700 font-semibold' : 'hover:bg-slate-50 text-slate-600'; ?>">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                            </svg>
                            <span>Interactive Map</span>
                        </a>

                        <?php $isLiveSupportActive = ($view === 'live-support'); ?>
                        <a href="dashboard.php?view=live-support" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?php echo $isLiveSupportActive ? 'bg-sky-100 text-sky-700 font-semibold' : 'hover:bg-slate-50 text-slate-600'; ?>">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                            </svg>
                            <span>Live Support</span>
                            <span id="liveSupportBadgeMobile" class="ml-auto bg-red-500 text-white text-xs font-bold px-2 py-0.5 rounded-full hidden">0</span>
                        </a>
                        
                        <?php if ($isAdmin): ?>
                            <?php $isCreateAccountActive = ($view === 'create-account'); ?>
                            <a href="dashboard.php?view=create-account" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?php echo $isCreateAccountActive ? 'bg-sky-100 text-sky-700 font-semibold' : 'hover:bg-slate-50 text-slate-600'; ?>">
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
                            <a href="logout" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-50">
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
                            if ($view === 'live-support') echo 'Live Support';
                            elseif ($isAdmin && $view === 'create-account') echo 'Create Account';
                            elseif ($view === 'analytics') echo 'Analytics';
                            elseif ($view === 'map') echo 'Interactive Map';
                            else echo 'Dashboard';
                        ?>
                    </h1>
                    <p class="text-slate-500 mt-1 text-base md:text-lg">
                        <?php
                            if ($view === 'live-support') {
                                echo 'Connect with residents in real-time.';
                            } elseif ($isAdmin && $view === 'create-account') {
                                echo 'Create new user accounts with flexible role assignment (Staff/Responder).';
                            } elseif ($view === 'analytics') {
                                echo 'Comprehensive statistical overview of emergency reports.';
                            } elseif ($view === 'map') {
                                echo 'Real-time visualization of emergency incidents and responder locations.';
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


                <?php if ($view === 'live-support'): ?>
                    <?php include __DIR__ . '/views/live_support.php'; ?>

                <?php elseif ($view === 'map'): ?>
                    <?php include __DIR__ . '/views/interactive_map.php'; ?>

                <?php elseif ($isAdmin): ?>
                    <?php if ($view === 'create-account'): ?>
                        <?php include __DIR__ . '/views/create_account.php'; ?>

                    <?php elseif ($view === 'analytics'): ?>
                        <?php include __DIR__ . '/views/analytics.php'; ?>

                    <?php else: // Default Admin Dashboard View ?>
                        <?php include __DIR__ . '/views/dashboard_home.php'; ?>
                    <?php endif; ?>
                
                <?php else: // Staff View ?>
                    <?php if ($view === 'analytics'): ?>
                        <?php include 'views/analytics.php'; ?>
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
    
    <?php include 'includes/modals_dashboard.php'; ?>
    
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
                                            <span><i class="fas fa-map-marker-alt mr-1"></i> Incident Location</span>
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
    
    <script>
    // Ensure theme preference is applied ASAP
    (function() {
      try {
        // Force light mode as per user request
        document.documentElement.classList.remove('dark');
        localStorage.setItem('theme', 'light');
      } catch(e) {}
    })();
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
        
            // Request notification permission on page load
            if (Notification && Notification.permission === 'default') {
                Notification.requestPermission().then(permission => {
                    console.log('Notification permission:', permission);
                });
            }
    
        const categories = <?php echo json_encode($categories); ?>;
    
        // Helper function for SVG icons in JavaScript
        function svg_icon(name, className = 'w-6 h-6') {
            const icons = {
                'dashboard': '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />',
                'truck': '<path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.125-.504 1.125-1.125V14.25m-17.25 4.5v-1.875a3.375 3.375 0 003.375-3.375h1.5a1.125 1.125 0 011.125 1.125v-1.5a3.375 3.375 0 00-3.375-3.375H9.75V7.5h1.5a3.375 3.375 0 013.375 3.375v1.5a1.125 1.125 0 001.125 1.125h1.5a3.375 3.375 0 003.375-3.375V7.5a1.125 1.125 0 00-1.125-1.125H5.625" />',
                'shield-check': '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.286zm0 13.036h.008v.017h-.008v-.017z" />',
                'fire': '<path stroke-linecap="round" stroke-linejoin="round" d="M15.362 5.214A8.252 8.252 0 0112 21 8.25 8.25 0 016.038 7.048 8.287 8.287 0 009 9.6a8.983 8.983 0 013.362-6.867 8.268 8.268 0 013 2.481z" /><path stroke-linecap="round" stroke-linejoin="round" d="M12 18a3.75 3.75 0 00.495-7.467 5.99 5.99 0 00-1.925 3.546 5.974 5.974 0 01-2.133-1.001A3.75 3.75 0 0012 18z" />',
                'home': '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h7.5" />',
                'question-mark-circle': '<path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" />',
                'user-plus': '<path stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.5a3 3 0 11-6 0 3 3 0 016 0zM4 18.75v-1.5a6.75 6.75 0 017.5-6.75h.5a6.75 6.75 0 016.75 6.75v1.5a6.75 6.75 0 01-6.75 6.75H9.75V21h7.5" />',
                'user-shield': '<path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />',
                'user-check': '<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" /><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75" />',
                'spinner': '<path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />',
                'x-mark': '<path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />',
                'identification': '<path stroke-linecap="round" stroke-linejoin="round" d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5zm6-10.125a1.875 1.875 0 11-3.75 0 1.875 1.875 0 013.75 0zm1.294 6.336a6.721 6.721 0 01-3.17.789 6.721 6.721 0 01-3.168-.789 3.376 3.376 0 016.338 0z" />',
                'user-circle': '<path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z" />',
                'eye': '<path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />',
                'check-circle': '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />',
                'x-circle': '<path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />'
            };
            const path = icons[name] || '';
            return `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="${className}">${path}</svg>`;
        }

        // Count animation function
        function animateCount(element, target) {
            const start = 0;
            const duration = 1000; // 1 second
            const startTime = performance.now();
            
            function update(currentTime) {
                const elapsed = currentTime - startTime;
                const progress = Math.min(elapsed / duration, 1);
                
                // Easing function for smooth animation
                const easeOutQuart = 1 - Math.pow(1 - progress, 4);
                const current = Math.floor(start + (target - start) * easeOutQuart);
                
                element.textContent = current;
                
                if (progress < 1) {
                    requestAnimationFrame(update);
                } else {
                    element.textContent = target;
                }
            }
            
            requestAnimationFrame(update);
        }
    
        // Helper function to normalize Firebase document data (handle field mapping for different report types)
        function normalizeFirebaseReportData(reportData) {
            // All report types have the same basic fields: contact, fullName, imageUrl, location, reporterId, status, timestamp
            // Some reports (like other_reports) might have description
            const mobileNumber = reportData.mobileNumber || reportData.contact || reportData.reporterContact || '';
            return {
                fullName: reportData.fullName || reportData.reporterName || '',
                contact: mobileNumber,
                mobileNumber: mobileNumber, // Preserve both for compatibility
                location: reportData.location || '',
                purpose: reportData.purpose || reportData.description || '', // Updated to include description
                reporterId: reportData.reporterId || '',
                imageUrl: reportData.imageUrl || '',
                status: reportData.status || 'Pending',
                priority: reportData.priority || '',
                timestamp: reportData.timestamp,
                emergencyType: reportData.emergencyType || '',
                reporterEmail: reportData.reporterEmail || ''
            };
        }

        // Helper function to format Firebase timestamp with Philippines timezone
        function formatFirebaseTimestamp(timestamp) {
            if (!timestamp) return '—';
            
            console.log('Formatting timestamp:', timestamp, 'Type:', typeof timestamp);
            
            try {
                let date;
                
                // Handle Firebase Timestamp object
                if (timestamp && typeof timestamp.toDate === 'function') {
                    date = timestamp.toDate();
                    console.log('Firebase timestamp object converted to date:', date);
                }
                // Handle Firestore timestamp object with seconds/nanoseconds
                else if (timestamp && timestamp.seconds) {
                    date = new Date(timestamp.seconds * 1000);
                    console.log('Firestore timestamp converted to date:', date);
                }
                // Handle Firebase format "August 19, 2025 at 2:28:29 AM UTC+8"
                else if (typeof timestamp === 'string' && timestamp.includes(' at ') && timestamp.includes('UTC')) {
                    // Parse: "August 19, 2025 at 2:28:29 AM UTC+8"
                    const cleanTime = timestamp.replace(' at ', ' ').replace(/\s+UTC[+-]\d+$/, '');
                    date = new Date(cleanTime);
                    console.log('Firebase format string converted to date:', cleanTime, '=>', date);
                }
                // Handle ISO string or other date strings
                else if (typeof timestamp === 'string') {
                    date = new Date(timestamp);
                    console.log('String timestamp converted to date:', date);
                }
                else {
                    date = new Date(timestamp);
                    console.log('Other timestamp converted to date:', date);
                }
                
                // Check if date is valid
                if (isNaN(date.getTime())) {
                    console.error('Invalid date created from timestamp:', timestamp);
                    return typeof timestamp === 'string' ? timestamp : '—';
                }
                
                // Convert to Philippines timezone (UTC+8) and format as "Aug 19, 2025 2:28 AM"
                const formatted = date.toLocaleDateString('en-US', {
                    month: 'short',
                    day: 'numeric', 
                    year: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit',
                    hour12: true,
                    timeZone: 'Asia/Manila'  // Philippines timezone
                });
                
                console.log('Final formatted timestamp:', formatted);
                return formatted;
            } catch (error) {
                console.error('Error formatting timestamp:', timestamp, error);
                return typeof timestamp === 'string' ? timestamp : '—';
            }
        }
    
        // --- TOAST NOTIFICATIONS ---
        function showToast(message, type = 'info') {
            const toastContainer = document.getElementById('toastContainer');
            if (!toastContainer) return;
            const toast = document.createElement('div');
            const icons = {
                success: `<?php echo svg_icon('check', 'w-6 h-6 text-emerald-500'); ?>`,
                error: `<?php echo svg_icon('x-mark', 'w-6 h-6 text-red-500'); ?>`,
                info: `<?php echo svg_icon('info', 'w-6 h-6 text-sky-500'); ?>`
            };
            const colors = {
                success: 'border-emerald-500/30 bg-emerald-50 text-emerald-800',
                error: 'border-red-500/30 bg-red-50 text-red-800',
                info: 'border-sky-500/30 bg-sky-50 text-sky-800'
            };
    
            toast.className = `relative w-full p-4 pr-12 rounded-lg shadow-lg border ${colors[type]} transform transition-all duration-300 translate-x-full opacity-0 backdrop-blur-sm bg-opacity-80`;
            toast.innerHTML = `
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0">${icons[type]}</div>
                    <p class="text-sm font-medium ">${message}</p>
                </div>
            `;
            
            toastContainer.appendChild(toast);
    
            requestAnimationFrame(() => {
                toast.classList.remove('translate-x-full', 'opacity-0');
            });
    
            setTimeout(() => {
                toast.classList.add('opacity-0', 'scale-95');
                toast.addEventListener('transitionend', () => toast.remove(), { once: true });
            }, 4000);
        }

        // Make showToast globally accessible
        window.showToast = showToast;

        // --- AUDIO UNLOCK ON FIRST USER INTERACTION ---
        // Browsers require user interaction before playing audio
        // This unlocks audio on the first click/touch/keypress
        let audioUnlocked = false;
        let audioContext = null;
        
        function unlockAudio() {
            if (audioUnlocked) return;
            
            try {
                // Create and resume AudioContext
                audioContext = new (window.AudioContext || window.webkitAudioContext)();
                
                // Create a silent buffer and play it
                const buffer = audioContext.createBuffer(1, 1, 22050);
                const source = audioContext.createBufferSource();
                source.buffer = buffer;
                source.connect(audioContext.destination);
                source.start(0);
                
                // Resume context if suspended
                if (audioContext.state === 'suspended') {
                    audioContext.resume();
                }
                
                // Also try HTML5 Audio
                const silentAudio = new Audio('data:audio/wav;base64,UklGRiQAAABXQVZFZm10IBAAAAABAAEARKwAAIhYAQACABAAZGF0YQAAAAA=');
                silentAudio.volume = 0.01;
                silentAudio.play().catch(() => {});
                
                audioUnlocked = true;
                console.log('🔊 Audio unlocked - notification sounds will now work');
                
                // Remove listeners after unlock
                document.removeEventListener('click', unlockAudio);
                document.removeEventListener('touchstart', unlockAudio);
                document.removeEventListener('keydown', unlockAudio);
            } catch (e) {
                console.log('Audio unlock attempt:', e.message);
            }
        }
        
        // Listen for first user interaction
        document.addEventListener('click', unlockAudio, { once: false });
        document.addEventListener('touchstart', unlockAudio, { once: false });
        document.addEventListener('keydown', unlockAudio, { once: false });

        // --- NOTIFICATION SOUND ---
        // Create a simple notification sound
        function playNotificationSound(soundType = 'default') {
            // Check if audio is unlocked
            if (!audioUnlocked) {
                console.log('⚠️ Audio not yet unlocked - click anywhere on page first');
            }
            
            // Special handling for emergency siren
            if (soundType === 'siren') {
                try {
                    const audio = new Audio('alarmsiren.mp3');
                    audio.volume = 1.0; // Max volume for emergency
                    audio.play().then(() => {
                        console.log('🔊 Emergency siren played');
                    }).catch(e => {
                        // Silently fall back to beep sound
                        console.log('Siren unavailable, using beep');
                        playBeepSound();
                    });
                    return;
                } catch (e) {
                    playBeepSound();
                }
                return;
            }
            
            playBeepSound();
        }
        
        function playBeepSound() {
            try {
                // Use the unlocked AudioContext if available
                const ctx = audioContext || new (window.AudioContext || window.webkitAudioContext)();
                
                if (ctx.state === 'suspended') {
                    ctx.resume();
                }
                
                // Facebook Messenger-style "pop" notification sound
                // Two quick notes: high then higher, with a nice decay
                const now = ctx.currentTime;
                
                // First note - the "pop"
                const osc1 = ctx.createOscillator();
                const gain1 = ctx.createGain();
                osc1.connect(gain1);
                gain1.connect(ctx.destination);
                osc1.frequency.setValueAtTime(830, now); // E5
                osc1.frequency.setValueAtTime(880, now + 0.08); // A5 slide up
                osc1.type = 'sine';
                gain1.gain.setValueAtTime(0, now);
                gain1.gain.linearRampToValueAtTime(0.4, now + 0.02);
                gain1.gain.exponentialRampToValueAtTime(0.01, now + 0.15);
                osc1.start(now);
                osc1.stop(now + 0.15);
                
                // Second note - the "ding" (slightly delayed)
                const osc2 = ctx.createOscillator();
                const gain2 = ctx.createGain();
                osc2.connect(gain2);
                gain2.connect(ctx.destination);
                osc2.frequency.value = 1320; // E6 - higher octave
                osc2.type = 'sine';
                gain2.gain.setValueAtTime(0, now + 0.08);
                gain2.gain.linearRampToValueAtTime(0.3, now + 0.1);
                gain2.gain.exponentialRampToValueAtTime(0.01, now + 0.35);
                osc2.start(now + 0.08);
                osc2.stop(now + 0.35);
                
                console.log('🔊 Notification sound played');
            } catch (error) {
                console.log('Notification sound failed:', error.message);
            }
        }

        // Make notification sound globally accessible
        window.playNotificationSound = playNotificationSound;
        
        // Enhanced notification with visual feedback
        function showNotificationWithSound(message, type = 'success', soundType = 'default') {
            // Play sound
            playNotificationSound(soundType);
            
            // Show toast with enhanced visual feedback
            showToast(message, type);
            
            // Add visual flash effect to document title
            const originalTitle = document.title;
            let flashCount = 0;
            const flashInterval = setInterval(() => {
                document.title = flashCount % 2 === 0 ? '🔔 NEW REPORT!' : originalTitle;
                flashCount++;
                if (flashCount >= 6) { // Flash 3 times
                    clearInterval(flashInterval);
                    document.title = originalTitle;
                }
            }, 500);
            
            // Try to show browser notification if permission is granted
            if (Notification && Notification.permission === 'granted') {
                new Notification('ManResponde Alert', {
                    body: message,
                    icon: 'responde.png',
                    badge: 'responde.png'
                });
            }
        }
        
        // Make enhanced notification globally accessible
        window.showNotificationWithSound = showNotificationWithSound;
    
        // --- API & FORM HANDLING ---
        async function handleApiFormSubmit(form, button) {
            const btnSpinner = '<?php echo svg_icon('spinner', 'w-4 h-4 animate-spin-fast'); ?>';
            const btnOriginalContent = button.innerHTML;
            
            button.innerHTML = btnSpinner;
            button.disabled = true;
    
            try {
                const formData = new FormData(form);
                // Add CSRF token
                formData.append('<?php echo CSRF_TOKEN_NAME; ?>', getCsrfToken());
                if (!formData.has('api_action')) {
                    let action = 'update_status';
                    if (form.id === 'createStaffForm') action = 'create_staff';
                    else if (form.id === 'createResponderForm') action = 'create_responder';
                    formData.append('api_action', action);
                }
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                if (!response.ok) throw new Error('Network response was not ok.');
                
                const result = await response.json();
    
                if (result.success) {
                    showToast(result.message, 'success');
                    if (form.id === 'createStaffForm' || form.id === 'createResponderForm') {
                        form.reset();
                        // Refresh admin statistics if on dashboard view after creating user
                        if (window.location.search.includes('view=dashboard') || !window.location.search.includes('view=')) {
                            refreshAdminStats();
                        }
                    }
                    return result;
                } else {
                    throw new Error(result.message || 'An unknown error occurred.');
                }
            } catch (error) {
                showToast(error.message, 'error');
                return null;
            } finally {
                if (document.body.contains(button)) {
                    button.innerHTML = btnOriginalContent;
                    button.disabled = false;
                }
            }
        }

        // Show decline confirmation dialog with clear explanation
        window.showDeclineConfirmation = function(collection, docId, reporterName, categoryType) {
            showDeclineModal(collection, docId, reporterName, categoryType);
        }

        // Global handler for status update forms (Approve/Decline)
        window.handleStatusUpdate = async function(event) {
            event.preventDefault();
            
            const form = event.currentTarget;
            const button = form.querySelector('button[type="submit"]');
            const originalText = button.innerHTML;
            const newStatus = form.querySelector('input[name="newStatus"]').value;
            const collection = form.querySelector('input[name="collection"]').value;
            const docId = form.querySelector('input[name="docId"]').value;
            
            // Show loading state
            button.innerHTML = '<?php echo svg_icon('spinner', 'w-4 h-4 animate-spin'); ?>';
            button.disabled = true;
            
            try {
                const formData = new FormData(form);
                formData.append('<?php echo CSRF_TOKEN_NAME; ?>', getCsrfToken());
                formData.append('api_action', 'update_status');
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast(result.message, 'success');
                    
                    // Get the current row
                    const row = form.closest('tr.report-row');
                    if (row) {
                        // Update the row status badge immediately
                        const badge = row.querySelector('.status-badge');
                        if (badge) {
                            badge.classList.remove('status-badge-success', 'status-badge-pending', 'status-badge-declined');
                            const st = newStatus.toLowerCase();
                            if (st === 'approved') badge.classList.add('status-badge-success');
                            else badge.classList.add('status-badge-declined');
                            badge.innerHTML = `<span class="h-2 w-2 rounded-full bg-current mr-2"></span>${newStatus}`;
                        }
                        
                        // Disable action buttons
                        row.querySelectorAll('form[onsubmit="handleStatusUpdate(event)"] button').forEach(btn => {
                            btn.disabled = true;
                            btn.classList.add('opacity-50');
                        });
                        
                        // Move report to correct section with animation
                        moveReportToSection(row, newStatus, collection);
                    }
                    
                    // Update counters immediately
                    updateStatusCounters(collection, newStatus);
                    
                    // Update notification count (remove notification for approved reports)
                    if (newStatus === 'Approved') {
                        setTimeout(() => {
                            loadNotificationCount();
                        }, 500);
                    }
                    
                } else {
                    showToast(result.message || 'Failed to update status', 'error');
                }
                
            } catch (error) {
                console.error('Error updating status:', error);
                showToast('Failed to update status - please try again', 'error');
            } finally {
                // Restore button state
                button.innerHTML = originalText;
                button.disabled = false;
            }
        };

        // Function to move report to correct section
        function moveReportToSection(row, newStatus, collection) {
                    // If in Verify Users list and approved, fade out and remove the card/item
                    if (newStatus.toLowerCase() === 'approved') {
                        // Try to find the user card/item in pending list
                        let userItem = form.closest('.vu-user-row, .vu-user-card, .vu-user-item');
                        if (!userItem) userItem = form.parentNode;
                        if (userItem) {
                            userItem.style.transition = 'opacity 0.4s, transform 0.4s';
                            userItem.style.opacity = '0';
                            userItem.style.transform = 'translateX(-20px)';
                            setTimeout(() => {
                                if (userItem.parentNode) userItem.parentNode.removeChild(userItem);
                            }, 400);
                        }
                    }
            // Get report data before removing the row
            const reportData = {
                id: row.dataset.id,
                collection: row.dataset.collection,
                fullName: row.querySelector('td:nth-child(1) .font-semibold')?.textContent || '',
                contact: row.querySelector('td:nth-child(1) .text-slate-500')?.textContent || '',
                location: row.querySelector('td:nth-child(2)')?.textContent || '',
                timestamp: row.querySelector('td:nth-child(3)')?.textContent || '',
                status: newStatus
            };
            
            // Add fade out animation
            row.style.transition = 'opacity 0.3s ease-out, transform 0.3s ease-out';
            row.style.opacity = '0';
            row.style.transform = 'translateX(-20px)';
            
            setTimeout(() => {
                // Remove the row after animation
                row.remove();
                
                // Add report to the appropriate tab (approved/declined) and update counts
                addReportToAppropriateTab(reportData);
                
                // If we're on a specific category view, refresh that category's data
                if (currentView && typeof renderStaffReports === 'function') {
                    // Refresh the specific category data after a short delay
                    setTimeout(() => {
                        loadStaffData(true); // Force refresh
                    }, 500);
                }
                
                // Show success feedback
                showToast(`Report moved to ${newStatus} section`, 'info');
            }, 300);
        }

        // Function to add report to the appropriate tab and update counts
        function addReportToAppropriateTab(reportData) {
            if (!reportData.status || !reportData.collection) return;
            
            // Find the appropriate category based on collection
            const collectionToSlug = {
                'ambulance_reports': 'ambulance',
                'fire_reports': 'fire', 
                'flood_reports': 'flood',
                'other_reports': 'other',
                'tanod_reports': 'tanod'
            };
            
            const slug = collectionToSlug[reportData.collection];
            if (!slug) return;
            
            const tabName = reportData.status.toLowerCase();
            if (!['approved', 'declined'].includes(tabName)) return;
            
            // Find the appropriate tab content panel
            const targetPanel = document.querySelector(`[data-slug="${slug}"][data-tab="${tabName}"]`);
            if (!targetPanel) return;
            
            // Find the table body in the target panel
            const tableBody = targetPanel.querySelector('tbody.divide-y');
            if (!tableBody) return;
            
            // Create new row for the report
            const newRow = document.createElement('tr');
            newRow.className = 'report-row animate-fade-in-up';
            newRow.dataset.id = reportData.id;
            newRow.dataset.collection = reportData.collection;
            newRow.style.setProperty('--anim-delay', '0ms');
            
            const statusClass = reportData.status === 'Declined' ? 'status-badge-declined' : 'status-badge-approved';
            
            newRow.innerHTML = `
                <td class="p-4 whitespace-nowrap">
                    <div class="font-semibold text-slate-800">${reportData.fullName || '—'}</div>
                    <div class="text-slate-500">${reportData.contact || '—'}</div>
                </td>
                <td class="p-4 text-slate-600 max-w-xs truncate">${reportData.location || '—'}</td>
                <td class="p-4 text-slate-600 whitespace-nowrap">${reportData.timestamp}</td>
                <td class="p-4">
                    <span class="status-badge ${statusClass}">
                        <span class="h-2 w-2 rounded-full bg-current mr-2"></span>
                        ${reportData.status}
                    </span>
                </td>
                <td class="p-4 text-right">
                    <div class="inline-flex items-center gap-2">
                        <button type="button" class="btn btn-view" title="View Details"
                            onclick="showReportModal(this)"
                            data-id="${reportData.id}" data-collection="${reportData.collection}"
                            data-fullname="${reportData.fullName}" data-contact="${reportData.mobileNumber || reportData.contact}"
                            data-location="${reportData.location}" data-status="${reportData.status}"
                            data-timestamp="${reportData.timestamp}">
                            <?php echo svg_icon('eye', 'w-4 h-4'); ?><span>View</span>
                        </button>
                        <button type="button" class="btn btn-disabled" disabled title="Report Processed">
                            <?php echo svg_icon('check-circle', 'w-4 h-4'); ?><span>Processed</span>
                        </button>
                    </div>
                </td>
            `;
            
            // Add to top of table with animation
            tableBody.insertBefore(newRow, tableBody.firstChild);
            
            // Update the tab count
            updateTabCounts(slug, reportData.status);
            
            // Trigger animation
            setTimeout(() => {
                newRow.style.opacity = '1';
                newRow.style.transform = 'translateY(0)';
            }, 50);
            
            showToast(`${reportData.status} report added to ${tabName} tab`, 'success');
        }

        // Function to update tab counts after status change
        function updateTabCounts(slug, newStatus) {
            const segmentedControl = document.querySelector(`.segmented[data-slug="${slug}"]`);
            if (!segmentedControl) return;
            
            // Find tab count elements
            const pendingTab = segmentedControl.querySelector('[data-tab="pending"] .tab-count');
            const approvedTab = segmentedControl.querySelector('[data-tab="approved"] .tab-count');
            const declinedTab = segmentedControl.querySelector('[data-tab="declined"] .tab-count');
            
            if (pendingTab && approvedTab && declinedTab) {
                // Decrease pending count
                const pendingCount = parseInt(pendingTab.textContent) || 0;
                const newPendingCount = Math.max(0, pendingCount - 1);
                pendingTab.textContent = newPendingCount;
                
                // Increase appropriate count
                if (newStatus === 'Approved') {
                    const approvedCount = parseInt(approvedTab.textContent) || 0;
                    approvedTab.textContent = approvedCount + 1;
                    
                    // Add pulse animation
                    approvedTab.classList.add('animate-pulse');
                    setTimeout(() => approvedTab.classList.remove('animate-pulse'), 1000);
                } else if (newStatus === 'Declined') {
                    const declinedCount = parseInt(declinedTab.textContent) || 0;
                    declinedTab.textContent = declinedCount + 1;
                    
                    // Add pulse animation
                    declinedTab.classList.add('animate-pulse');
                    setTimeout(() => declinedTab.classList.remove('animate-pulse'), 1000);
                }
                
                // Add pulse animation to pending (decreased)
                pendingTab.classList.add('animate-pulse');
                setTimeout(() => pendingTab.classList.remove('animate-pulse'), 1000);
                
                showToast(`Tab counts updated for ${slug}`, 'info');
            }
        }

        // Function to update status counters
        function updateStatusCounters(collection, newStatus) {
            // Find the category stats container
            const statsContainer = document.getElementById('adminStatsContainer');
            if (statsContainer) {
                // Update the counters based on collection
                const collectionMapping = {
                    'ambulance_reports': 'Ambulance',
                    'fire_reports': 'Fire',
                    'flood_reports': 'Flood',
                    'tanod_reports': 'Tanod',
                    'other_reports': 'Other'
                };
                
                const categoryName = collectionMapping[collection];
                if (categoryName) {
                    // Find the stat card for this category
                    const statCards = statsContainer.querySelectorAll('.stat-card');
                    statCards.forEach(card => {
                        const title = card.querySelector('h3, h4');
                        if (title && title.textContent.includes(categoryName)) {
                            // Find all counter elements in this card
                            const counterElements = card.querySelectorAll('[data-countup]');
                            let pendingElement = null;
                            let approvedElement = null;
                            
                            // Find pending, approved, and declined elements by their parent text
                            let declinedElement = null;
                            counterElements.forEach(el => {
                                const parent = el.closest('div');
                                const label = parent.querySelector('.text-xs');
                                if (label) {
                                    const labelText = label.textContent.toLowerCase();
                                    if (labelText.includes('pending')) {
                                        pendingElement = el;
                                    } else if (labelText.includes('approved')) {
                                        approvedElement = el;
                                    } else if (labelText.includes('declined')) {
                                        declinedElement = el;
                                    }
                                }
                            });
                            
                            if (pendingElement && approvedElement && declinedElement) {
                                // Decrease pending count
                                const pendingCount = parseInt(pendingElement.textContent) || 0;
                                const newPendingCount = Math.max(0, pendingCount - 1);
                                pendingElement.textContent = newPendingCount;
                                pendingElement.dataset.countup = newPendingCount;
                                
                                // Handle status updates
                                if (newStatus === 'Approved') {
                                    // Increase approved count
                                    const approvedCount = parseInt(approvedElement.textContent) || 0;
                                    const newApprovedCount = approvedCount + 1;
                                    approvedElement.textContent = newApprovedCount;
                                    approvedElement.dataset.countup = newApprovedCount;
                                    
                                    // Add pulse animation to approved element
                                    approvedElement.classList.add('animate-pulse');
                                    setTimeout(() => {
                                        approvedElement.classList.remove('animate-pulse');
                                    }, 1000);
                                } else if (newStatus === 'Declined') {
                                    // Increase declined count
                                    const declinedCount = parseInt(declinedElement.textContent) || 0;
                                    const newDeclinedCount = declinedCount + 1;
                                    declinedElement.textContent = newDeclinedCount;
                                    declinedElement.dataset.countup = newDeclinedCount;
                                    
                                    // Add pulse animation to declined element
                                    declinedElement.classList.add('animate-pulse');
                                    setTimeout(() => {
                                        declinedElement.classList.remove('animate-pulse');
                                    }, 1000);
                                    
                                    // Update progress bars with new declined count
                                    updateProgressBars(card, approvedElement, pendingElement, declinedElement);
                                }
                                
                                // Add pulse animation to pending element (decreased)
                                pendingElement.classList.add('animate-pulse');
                                setTimeout(() => {
                                    pendingElement.classList.remove('animate-pulse');
                                }, 1000);
                                
                                // Show success message
                                showToast(`${categoryName} counters updated`, 'success');
                            }
                        }
                    });
                }
            }
        }

        // Function to update progress bars after status change
        function updateProgressBars(card, approvedElement, pendingElement, declinedElement) {
            const approved = parseInt(approvedElement.textContent) || 0;
            const pending = parseInt(pendingElement.textContent) || 0;
            const declined = parseInt(declinedElement.textContent) || 0;
            const total = approved + pending + declined;
            
            if (total > 0) {
                const approvedPct = Math.round((approved / total) * 100);
                const pendingPct = Math.round((pending / total) * 100);
                const declinedPct = Math.round((declined / total) * 100);
                
                // Update progress bars
                const progressTrack = card.querySelector('.progress-track');
                if (progressTrack) {
                    const pendingSeg = progressTrack.querySelector('.progress-seg.pending');
                    const approvedSeg = progressTrack.querySelector('.progress-seg.approved');
                    const declinedSeg = progressTrack.querySelector('.progress-seg.declined');
                    
                    if (pendingSeg) pendingSeg.setAttribute('data-w', `${pendingPct}%`);
                    if (approvedSeg) approvedSeg.setAttribute('data-w', `${approvedPct}%`);
                    if (declinedSeg) declinedSeg.setAttribute('data-w', `${declinedPct}%`);
                    
                    // Update progress bar widths with animation
                    setTimeout(() => {
                        if (pendingSeg) pendingSeg.style.width = `${pendingPct}%`;
                        if (approvedSeg) approvedSeg.style.width = `${approvedPct}%`;
                        if (declinedSeg) declinedSeg.style.width = `${declinedPct}%`;
                    }, 100);
                }
                
                // Update percentage labels
                const percentageLabels = card.querySelectorAll('.text-xs .flex');
                if (percentageLabels.length >= 3) {
                    const pendingLabel = percentageLabels[0].querySelector('span:last-child');
                    const approvedLabel = percentageLabels[1].querySelector('span:last-child');
                    const declinedLabel = percentageLabels[2].querySelector('span:last-child');
                    
                    if (pendingLabel) pendingLabel.textContent = `${pendingPct}% Pending`;
                    if (approvedLabel) approvedLabel.textContent = `${approvedPct}% Approved`;
                    if (declinedLabel) declinedLabel.textContent = `${declinedPct}% Declined`;
                }
            }
        }
        

        
        // Original handleApiFormSubmit preserved for other forms
        window.handleApiFormSubmitOriginal = async function(form, button) {
            const result = await handleApiFormSubmit(form, button);
            if (!result || !result.success) {
                // Re-enable real-time sync if update failed
                if (typeof window.setStatusUpdateInProgress === 'function') {
                    window.setStatusUpdateInProgress(false);
                }
                return;
            }

            const docId = (form.querySelector('input[name="docId"]')?.value || '').trim();
            if (docId && newStatus) {
                updateActivityItemStatus(docId, newStatus);
            }

            // Close modal if not in a table row
            if (!row && typeof window.closeReportModal === 'function') {
                window.closeReportModal();
            }
            
            // Refresh admin statistics if on dashboard view
            if (window.location.search.includes('view=dashboard') || !window.location.search.includes('view=')) {
                refreshAdminStats();
            }
            
            // Refresh staff data if on staff view
            if (!window.location.search.includes('view=') && typeof renderStaffReports === 'function' && window.staffData) {
                // Quick update: update the specific report status in the UI first
                const docId = (form.querySelector('input[name="docId"]')?.value || '').trim();
                const collection = (form.querySelector('input[name="collection"]')?.value || '').trim();
                
                if (docId && collection) {
                    // Find the slug for this collection
                    const slug = Object.keys(categories).find(key => categories[key].collection === collection);
                    
                    if (slug) {
                        // IMMEDIATE VISUAL FEEDBACK: Update tab counts right away
                        if (typeof window.forceUpdateTabCounts === 'function') {
                            window.forceUpdateTabCounts(slug, docId, newStatus);
                        }
                        
                        // Update the report status in the current data
                        if (window.staffData && window.staffData.cards) {
                            Object.keys(window.staffData.cards).forEach(slug => {
                                const reports = window.staffData.cards[slug];
                                const reportIndex = reports.findIndex(r => r.id === docId);
                                if (reportIndex !== -1) {
                                    reports[reportIndex].status = newStatus;
                                    
                                    return;
                                }
                            });
                        }
                        
                        // Double-check tab counts are updated after a short delay
                        setTimeout(() => {
                            if (typeof window.manualUpdateTabCounts === 'function') {
                                window.manualUpdateTabCounts(slug);
                            }
                        }, 100);
                    }
                }
                
                // Also do a full refresh to ensure data consistency
                setTimeout(async () => {
                    const formData = createFormDataWithCsrf();
                    formData.append('api_action', 'load_staff_data');
                    
                    try {
                        const response = await fetch(window.location.href, {
                            method: 'POST',
                            body: formData
                        });
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            window.staffData = result.data;
                            renderStaffReports(result.data.cards, categories);
                            
                            // Update tab counts after re-render to ensure they're correct
                            if (typeof window.updateTabCounts === 'function' && docId && collection) {
                                const slug = Object.keys(categories).find(key => categories[key].collection === collection);
                                if (slug) {
                                    // Update tab counts immediately after re-render
                                    window.updateTabCounts(slug, docId, newStatus);
                                }
                            }
                        }
                    } catch (error) {
                        console.error('Error refreshing staff data:', error);
                    }
                }, 300); // Reduced delay to ensure the server has processed the update
                
                // Re-enable real-time sync after status update is complete
                setTimeout(() => {
                    if (typeof window.setStatusUpdateInProgress === 'function') {
                        window.setStatusUpdateInProgress(false);
                    }
                }, 500); // Wait 0.5 seconds after status update to re-enable sync
            }
        };
        
        // Update the existing handleUserVerification function
        window.handleUserVerification = async function(event) {
            event.preventDefault();
            const form = event.currentTarget;
            const button = form.querySelector('button[type="submit"]');
            const uid = form.querySelector('input[name="uid"]')?.value;
            
            const formData = new FormData();
            formData.append('api_action', 'verify_user');
            formData.append('uid', uid);
            formData.append('decision', form.querySelector('input[name="newStatus"]')?.value);

            const btnSpinner = '<?php echo svg_icon('spinner', 'w-4 h-4 animate-spin-fast'); ?>';
            const btnOriginalContent = button.innerHTML;
            
            button.innerHTML = btnSpinner;
            button.disabled = true;

            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                if (!response.ok) throw new Error('Network response was not ok.');
                
                const result = await response.json();

                if (result.success) {
                    showToast(result.message, 'success');
                    // For user verification view, remove the user card
                    const userCard = form.closest('.user-card') || form.closest('tr.user-row');
                    if (userCard) {
                        userCard.classList.add('animate-fade-out-down');
                        userCard.addEventListener('animationend', () => userCard.remove());
                    }
                    
                    // Refresh the pending users list to show updated counts
                    if (window.location.search.includes('view=verify-users')) {
                        // Immediate refresh to show updated list
                        setTimeout(() => {
                            loadPendingUsers(currentPage);
                        }, 100); // Faster refresh
                    }
                    
                    // Refresh admin statistics if on dashboard view
                    if (window.location.search.includes('view=dashboard') || !window.location.search.includes('view=')) {
                        refreshAdminStats();
                    }
                } else {
                    throw new Error(result.message || 'An unknown error occurred.');
                }
            } catch (error) {
                showToast(error.message, 'error');
                if (document.body.contains(button)) {
                    button.innerHTML = btnOriginalContent;
                    button.disabled = false;
                }
            }
        };

        // Attach listener for Admin 'Create Staff' form
        const createStaffForm = document.getElementById('createStaffForm');
        if (createStaffForm) {
            createStaffForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const button = createStaffForm.querySelector('button[type="submit"]');
                const result = await handleApiFormSubmit(createStaffForm, button);

                // Refresh staff data if a new staff was created
                if (result && result.refreshStaffData) {
                    console.log('New staff created, refreshing staff data...');
                    setTimeout(() => {
                        loadStaffData();
                    }, 1000); // Small delay to ensure backend is updated
                }
            });
        }

        // Attach listener for Admin 'Create Responder' form
        const createResponderForm = document.getElementById('createResponderForm');
        if (createResponderForm) {
            createResponderForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const button = createResponderForm.querySelector('button[type="submit"]');
                await handleApiFormSubmit(createResponderForm, button);
            });
        }

        // Attach listener for Admin 'Create Account' form (unified Staff/Responder)
        const createAccountForm = document.getElementById('createAccountForm');
        if (createAccountForm) {
            // Handle Tanod and Police Barangay/Outpost Selection
            const tanodCheckbox = createAccountForm.querySelector('input[name="categories[]"][value="tanod"]');
            const policeCheckbox = createAccountForm.querySelector('input[name="categories[]"][value="police"]');
            const barangaySection = document.getElementById('barangaySelection');
            const barangaySelect = document.getElementById('assignedBarangay');

            function toggleBarangaySelection() {
                const isTanod = tanodCheckbox && tanodCheckbox.checked;
                const isPolice = policeCheckbox && policeCheckbox.checked;

                if (isTanod || isPolice) {
                    barangaySection.classList.remove('hidden');
                    barangaySelect.required = true;
                } else {
                    barangaySection.classList.add('hidden');
                    barangaySelect.required = false;
                    barangaySelect.value = '';
                }
            }

            if (barangaySection && barangaySelect) {
                if (tanodCheckbox) {
                    tanodCheckbox.addEventListener('change', toggleBarangaySelection);
                }
                if (policeCheckbox) {
                    policeCheckbox.addEventListener('change', toggleBarangaySelection);
                }
            }

            createAccountForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                
                // Check if at least one role is selected
                const selectedRoles = createAccountForm.querySelectorAll('input[name="accountTypes[]"]:checked');
                const roleError = document.getElementById('roleSelectionError');
                
                if (selectedRoles.length === 0) {
                    roleError.classList.remove('hidden');
                    return;
                }
                
                roleError.classList.add('hidden');
                const button = createAccountForm.querySelector('button[type="submit"]');
                const result = await handleApiFormSubmit(createAccountForm, button);

                // Clear form and show success message
                if (result && result.success) {
                    createAccountForm.reset();
                    // Uncheck all role checkboxes
                    selectedRoles.forEach(cb => cb.checked = false);
                }
            });
        }

        // Staff Statistics and Management
        if (window.location.search.includes('view=create-account') || window.location.search.includes('view=create-staff')) {
            loadStaffData();

            // Auto-refresh staff data every 30 seconds
            setInterval(loadStaffData, 30000);
        }

        async function loadStaffData() {
            try {
                console.log('Loading staff data...');

                // Get DOM elements
                const staffList = document.getElementById('staffList');
                const staffLoading = document.getElementById('staffLoading');
                const staffEmpty = document.getElementById('staffEmpty');
                const totalStaffCount = document.getElementById('totalStaffCount');
                const activeStaffCount = document.getElementById('activeStaffCount');
                const reportsAssignedCount = document.getElementById('reportsAssignedCount');

                if (!staffList || !staffLoading) return;

                // Show loading
                staffLoading.classList.remove('hidden');
                staffEmpty.classList.add('hidden');

                // Fetch staff data
                const formData = new FormData();
                formData.append('api_action', 'get_staff_data');

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    const staffData = result.data;
                    console.log('Staff data loaded:', staffData);

                    // Update statistics
                    if (totalStaffCount) totalStaffCount.textContent = staffData.total || 0;
                    if (activeStaffCount) activeStaffCount.textContent = staffData.active || 0;
                    if (reportsAssignedCount) reportsAssignedCount.textContent = staffData.reportsAssigned || 0;

                    // Update staff list
                    updateStaffList(staffData.staff || []);
                } else {
                    console.error('Failed to load staff data:', result.message);
                    showStaffError();
                }

            } catch (error) {
                console.error('Error loading staff data:', error);
                showStaffError();
            }
        }

        function updateStaffList(staff) {
            const staffList = document.getElementById('staffList');
            const staffLoading = document.getElementById('staffLoading');
            const staffEmpty = document.getElementById('staffEmpty');

            if (!staffList) return;

            // Hide loading
            staffLoading.classList.add('hidden');

            if (staff.length === 0) {
                staffEmpty.classList.remove('hidden');
                staffList.innerHTML = '';
                return;
            }

            staffEmpty.classList.add('hidden');

            // Generate staff list HTML
            const staffHtml = staff.map(staffMember => {
                const isActive = staffMember.status === 'active';
                const categoryCount = staffMember.categories ? staffMember.categories.length : 0;

                return `
                    <div class="bg-white rounded-lg border border-slate-200 p-4 hover:shadow-md transition-shadow">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-gradient-to-br from-sky-500 to-blue-600 rounded-full flex items-center justify-center text-white font-bold">
                                    ${staffMember.name ? staffMember.name.charAt(0).toUpperCase() : '?'}
                                </div>
                                <div>
                                    <h4 class="font-semibold text-slate-800">${staffMember.name || 'Unknown'}</h4>
                                    <p class="text-sm text-slate-600">${staffMember.email || 'No email'}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-4">
                                <div class="text-right">
                                    <div class="text-sm font-medium ${isActive ? 'text-green-600' : 'text-slate-500'}">
                                        ${isActive ? 'Active' : 'Inactive'}
                                    </div>
                                    <div class="text-xs text-slate-500">
                                        ${categoryCount} categories
                                    </div>
                                </div>
                                <div class="w-2 h-2 rounded-full ${isActive ? 'bg-green-400' : 'bg-slate-400'}"></div>
                            </div>
                        </div>
                        ${categoryCount > 0 ? `
                            <div class="mt-3 flex flex-wrap gap-1">
                                ${staffMember.categories.slice(0, 3).map(cat =>
                                    `<span class="px-2 py-1 bg-slate-100 text-slate-600 rounded text-xs">${cat}</span>`
                                ).join('')}
                                ${categoryCount > 3 ? `<span class="px-2 py-1 bg-slate-100 text-slate-600 rounded text-xs">+${categoryCount - 3}</span>` : ''}
                            </div>
                        ` : ''}
                    </div>
                `;
            }).join('');

            staffList.innerHTML = staffHtml;
        }

        function showStaffError() {
            const staffLoading = document.getElementById('staffLoading');
            const staffEmpty = document.getElementById('staffEmpty');

            if (staffLoading) staffLoading.classList.add('hidden');
            if (staffEmpty) {
                staffEmpty.classList.remove('hidden');
                staffEmpty.innerHTML = `
                    <div class="text-center py-8">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12 mx-auto mb-3 text-red-400">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                        </svg>
                        <p class="text-sm text-red-600 font-medium mb-1">Failed to load staff data</p>
                        <p class="text-xs text-slate-500">Please try refreshing the page</p>
                    </div>
                `;
            }
        }

        // --- MODAL SCRIPT ---
        // Function to fetch report data directly from database
        async function fetchReportDataDirectly(collection, docId) {
            try {
                const fd = new FormData();
                fd.append('api_action', 'get_report_data');
                fd.append('collection', collection);
                fd.append('docId', docId);
                
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 3000); // 3 second timeout
                
                const res = await fetch(window.location.href, {
                    method: 'POST',
                    body: fd,
                    signal: controller.signal
                });
                
                clearTimeout(timeoutId);
                
                if (!res.ok) {
                    throw new Error(`HTTP ${res.status}: ${res.statusText}`);
                }
                
                const json = await res.json();
                
                if (!json || !json.success) {
                    throw new Error(json?.message || 'Failed to fetch report data');
                }
                
                return json.data;
            } catch (error) {
                if (error.name === 'AbortError') {
                    console.log('Database fetch timed out, keeping current purpose value');
                } else {
                    console.error('Error fetching report data directly:', error);
                }
                return null;
            }
        }
        
        window.showReportModal = function(btn) {
            const reportModal = document.getElementById('reportModal');
            const modalContent = document.getElementById('modalContent');
            const ds = btn.dataset;
            
            // Debug: Log all button data attributes
            console.log('=== Report Modal Debug ===');
            console.log('Button dataset:', ds);
            console.log('Contact value:', ds.contact);
            console.log('Collection:', ds.collection);
            console.log('Doc ID:', ds.id || ds.docid);
            console.log('Full dataset keys:', Object.keys(ds));
    
            const setText = (id, v) => {
                const element = document.getElementById(id);
                if (element) {
                    const value = v && v.trim() ? v : '—';
                    element.textContent = value;
                    console.log(`Set ${id} to: "${value}"`);
                }
            };
    
            // Handle field mapping for different report types
            setText('m_fullName', ds.fullname);
            setText('m_contact', ds.contact);
            setText('m_location', ds.location);
            setText('m_reporterId', ds.reporterid);

            // --- MAP INITIALIZATION ---
            const mapContainer = document.getElementById('m_map_container');
            const mapStatus = document.getElementById('m_map_status');
            
            // Reset map container
            if (mapContainer) {
                mapContainer.classList.add('hidden');
                if (window.reportMap) {
                    window.reportMap.remove();
                    window.reportMap = null;
                }
            }

            if (ds.location && ds.location !== '—' && ds.location.trim() !== '' && mapContainer) {
                mapContainer.classList.remove('hidden');
                if (mapStatus) mapStatus.textContent = 'Locating...';
                
                // Function to init map
                const initMap = (lat, lng, label) => {
                    setTimeout(() => {
                        if (window.reportMap) {
                            window.reportMap.remove();
                            window.reportMap = null;
                        }
                        
                        // Create map instance
                        window.reportMap = L.map('m_map').setView([lat, lng], 16);
                        
                        // Add OpenStreetMap tile layer
                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                        }).addTo(window.reportMap);
                        
                        // Add marker
                        L.marker([lat, lng]).addTo(window.reportMap)
                            .bindPopup(label)
                            .openPopup();
                            
                        // Force map redraw after modal animation to prevent gray tiles
                        setTimeout(() => {
                            window.reportMap.invalidateSize();
                        }, 300);
                        
                        if (mapStatus) mapStatus.textContent = 'Location found';
                    }, 100);
                };

                // 0. Check for direct coordinates from dataset
                if (ds.lat && ds.lng && ds.lat !== 'null' && ds.lng !== 'null' && !isNaN(parseFloat(ds.lat)) && !isNaN(parseFloat(ds.lng))) {
                     initMap(parseFloat(ds.lat), parseFloat(ds.lng), ds.location);
                } else {
                    // 1. Try to parse coordinates from string (e.g. "14.5, 121.0")
                    const coordMatch = ds.location.match(/(-?\d+\.\d+),\s*(-?\d+\.\d+)/);
                    
                    if (coordMatch) {
                        const lat = parseFloat(coordMatch[1]);
                        const lng = parseFloat(coordMatch[2]);
                        initMap(lat, lng, ds.location);
                    } else {
                        // 2. Geocode address using Nominatim
                        // Append 'Philippines' context if not present to improve accuracy
                        let queryStr = ds.location;
                        if (!queryStr.toLowerCase().includes('philippines')) {
                            queryStr += ', Philippines';
                        }
                        
                        const query = encodeURIComponent(queryStr);
                        
                        fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${query}&limit=1`)
                            .then(res => res.json())
                            .then(data => {
                                if (data && data.length > 0) {
                                    const lat = parseFloat(data[0].lat);
                                    const lng = parseFloat(data[0].lon);
                                    initMap(lat, lng, ds.location);
                                } else {
                                    if (mapStatus) mapStatus.textContent = 'Location not found on map';
                                    // Fallback to Manila
                                    initMap(14.5995, 120.9842, 'Location not found: ' + ds.location);
                                }
                            })
                            .catch(err => {
                                console.error('Geocoding error:', err);
                                if (mapStatus) mapStatus.textContent = 'Map error';
                            });
                    }
                }
            }
            
            // If contact is empty or "—", fetch from Firebase to get mobileNumber
            if (!ds.contact || ds.contact === '—' || ds.contact.trim() === '') {
                console.log('Contact is empty, attempting Firebase fallback...');
                const docId = ds.id || ds.docid || btn.getAttribute('data-id');
                if (docId && ds.collection) {
                    console.log(`Fetching contact from Firebase: ${ds.collection}/${docId}`);
                    fetchReportDataDirectly(ds.collection, docId).then(function(data) {
                        console.log('Firebase fallback response:', data);
                        if (data && data.mobileNumber) {
                            console.log('Setting contact from Firebase:', data.mobileNumber);
                            setText('m_contact', data.mobileNumber);
                        } else {
                            console.log('No mobileNumber in Firebase response');
                        }
                    }).catch(function(err) {
                        console.warn('Contact fetch fallback failed:', err);
                    });
                } else {
                    console.log('Missing docId or collection for Firebase fallback');
                }
            } else {
                console.log('Contact already has value:', ds.contact);
            }

            


            // Simple purpose extraction - same as other fields
            let purposeValue = ds.purpose || ds.Purpose || ds.description || ds.Description || btn.getAttribute('data-purpose') || btn.dataset.purpose;

            // Simple fallback - same as other fields
            if (!purposeValue || purposeValue === '—' || purposeValue === '') {
                purposeValue = '—';
            }

            // Set the purpose immediately with available data (same as other fields)
            setText('m_purpose', purposeValue || '—');
            
            
            // If tanod report or other report and purpose still empty, fetch directly from DB as a fallback
            if (ds.collection === 'tanod_reports' || ds.collection === 'other_reports') {
                const pv = (purposeValue || '').trim();
                if (!pv || pv === '—') {
                    try {
                        const docId = ds.id || ds.docid || btn.getAttribute('data-id');
                        if (docId) {
                            fetchReportDataDirectly(ds.collection, docId).then(function(data){
                                if (data) {
                                    const pur = (data.purpose && String(data.purpose).trim()) 
                                                || (data.description && String(data.description).trim()) 
                                                || '';
                                    if (pur) {
                                        setText('m_purpose', pur);
                                    }
                                }
                            }).catch(function(err){
                                console.warn('Purpose fetch fallback failed:', err);
                            });
                        }
                    } catch (e) {
                        console.warn('Purpose fetch fallback error:', e);
                    }
                }
            }
// Show Purpose field for tanod reports and other reports, hide for others
            const purposeContainer = document.getElementById('m_purpose').parentElement;
            if (ds.collection === 'tanod_reports' || ds.collection === 'other_reports') {
                purposeContainer.style.display = '';
            } else {
                purposeContainer.style.display = 'none';
            }
            
            // Handle timestamp - ensure it's displayed properly  
            let timestampDisplay = ds.timestamp;
            
            // Debug: Log the timestamp value
            console.log('Modal timestamp data:', {
                raw: ds.timestamp,
                type: typeof ds.timestamp
            });
            
            // If timestamp is empty, try to format it again if we have raw data
            if (!timestampDisplay || timestampDisplay === '—' || timestampDisplay === '') {
                // Try to get the raw timestamp and format it
                if (btn.dataset.rawtimestamp) {
                    try {
                        timestampDisplay = formatFirebaseTimestamp(btn.dataset.rawtimestamp);
                        console.log('Formatted timestamp from raw data:', timestampDisplay);
                    } catch (error) {
                        console.error('Error formatting raw timestamp:', error);
                        timestampDisplay = 'Invalid timestamp';
                    }
                } else {
                    timestampDisplay = 'No timestamp available';
                }
            }
            
            // If still no timestamp display, try to use current timestamp formatting
            if (!timestampDisplay || timestampDisplay === 'No timestamp available') {
                // Look for the timestamp in the table row
                const row = btn.closest('tr.report-row');
                if (row) {
                    const timestampCell = row.querySelector('td:nth-child(3)');
                    if (timestampCell && timestampCell.textContent.trim() !== '—' && timestampCell.textContent.trim() !== '') {
                        timestampDisplay = timestampCell.textContent.trim();
                        console.log('Got timestamp from table cell:', timestampDisplay);
                    }
                }
            }
            
            setText('m_timestamp', timestampDisplay);

            // Show tanod report details for tanod_reports on both staff and admin sides
            if (ds.collection === 'tanod_reports') {
                setText('m_contact', ds.mobileNumber || ds.mobileNumber || '—');
                setText('m_fullName', ds.fullName || ds.fullname || '—');
                setText('m_location', ds.location || ds.Location || '—');
                setText('m_purpose', ds.purpose || ds.Purpose || ds.description || ds.Description || '—');
                setText('m_reporterId', ds.reporterId || ds.reporterid || ds.ReporterId || '—');
                setText('m_status', ds.status || ds.Status || 'Pending');
                setText('m_timestamp', timestampDisplay);
            }
const meta = categories[ds.slug] || {};
            const color = meta.color || 'gray';
            document.getElementById('m_header').innerHTML = `
                <div class="w-10 h-10 rounded-lg bg-${color}-100 text-${color}-600 flex items-center justify-center flex-shrink-0">
                    <?php echo svg_icon($meta['icon'] ?? 'question-mark-circle', 'w-6, h-6'); ?>
                </div>
                <h3 class="text-lg font-bold text-slate-900">Report Details</h3>
            `;

            const statusEl = document.getElementById('m_status');
            const st = (ds.status || 'Pending').toLowerCase();
            statusEl.innerHTML = `<span class="h-2 w-2 rounded-full bg-current mr-2"></span>${ds.status || 'Pending'}`;
            statusEl.className = 'status-badge ml-2';
            if (st === 'approved') statusEl.classList.add('status-badge-success');
            else if (st === 'declined') statusEl.classList.add('status-badge-declined');
            else statusEl.classList.add('status-badge-pending');

            const imgEl = document.getElementById('m_image');
            const videoEl = document.getElementById('m_video');
            const videoSource = document.getElementById('m_video_source');
            const imgNone = document.getElementById('m_image_none');
            const link = document.getElementById('m_image_link');
            const mediaHint = document.getElementById('m_media_hint');

            if (ds.imageurl) {
                // Function to determine if URL is a video file
                const isVideo = (url) => {
                    const videoExtensions = ['.mp4', '.webm', '.ogg', '.avi', '.mov', '.wmv', '.flv', '.mkv', '.m4v', '.3gp'];
                    const urlLower = url.toLowerCase();
                    return videoExtensions.some(ext => urlLower.includes(ext));
                };
                
                // Function to get video MIME type
                const getVideoType = (url) => {
                    const urlLower = url.toLowerCase();
                    if (urlLower.includes('.mp4') || urlLower.includes('.m4v')) return 'video/mp4';
                    if (urlLower.includes('.webm')) return 'video/webm';
                    if (urlLower.includes('.ogg')) return 'video/ogg';
                    if (urlLower.includes('.avi')) return 'video/avi';
                    if (urlLower.includes('.mov')) return 'video/quicktime';
                    if (urlLower.includes('.wmv')) return 'video/x-ms-wmv';
                    if (urlLower.includes('.flv')) return 'video/x-flv';
                    if (urlLower.includes('.mkv')) return 'video/x-matroska';
                    if (urlLower.includes('.3gp')) return 'video/3gpp';
                    return 'video/mp4'; // Default fallback
                };
                
                if (isVideo(ds.imageurl)) {
                    // Show video
                    videoSource.src = ds.imageurl;
                    videoSource.type = getVideoType(ds.imageurl);
                    videoEl.load(); // Reload video element
                    videoEl.classList.remove('hidden');
                    imgEl.classList.add('hidden');
                    imgNone.classList.add('hidden');
                    link.href = ds.imageurl;
                    mediaHint.textContent = 'Click to open video in new tab or use controls to play.';
                    
                    // Add video error handling
                    videoEl.onerror = function() {
                        console.error('Failed to load video:', ds.imageurl);
                        videoEl.classList.add('hidden');
                        imgNone.classList.remove('hidden');
                        imgNone.textContent = 'Video failed to load';
                        mediaHint.textContent = 'Click link to open video in new tab.';
                    };
                    
                    // Add video load success handling
                    videoEl.onloadedmetadata = function() {
                        console.log('Video loaded successfully:', ds.imageurl);
                    };
                } else {
                    // Show image
                    imgEl.src = ds.imageurl;
                    imgEl.classList.remove('hidden');
                    videoEl.classList.add('hidden');
                    imgNone.classList.add('hidden');
                    link.href = ds.imageurl;
                    mediaHint.textContent = 'Click image to open full size.';
                    
                    // Add image error handling
                    imgEl.onerror = function() {
                        console.error('Failed to load image:', ds.imageurl);
                        imgEl.classList.add('hidden');
                        imgNone.classList.remove('hidden');
                        imgNone.textContent = 'Image failed to load';
                        mediaHint.textContent = 'Click link to open media in new tab.';
                    };
                }
            } else {
                imgEl.src = '';
                imgEl.classList.add('hidden');
                videoEl.classList.add('hidden');
                videoSource.src = '';
                imgNone.classList.remove('hidden');
                imgNone.textContent = 'No media provided';
                link.href = '#';
                mediaHint.textContent = 'No media attached to this report.';
            }
            
            const actionsContainer = document.getElementById('m_actions');
            const isFinal = st === 'approved' || st === 'declined';
            
            const approveBtnClass = isFinal ? 'btn-disabled' : 'btn-approve';
            const declineBtnClass = isFinal ? 'btn-disabled' : 'btn-decline';
            const disabledAttr = isFinal ? 'disabled' : '';
    
            actionsContainer.innerHTML = `
                <button type="button" class="btn ${approveBtnClass}" ${disabledAttr} title="Approve Report" onclick="showApproveConfirmation('${ds.collection}', '${ds.id}', '${ds.fullName}', '${ds.slug}')">
                    <?php echo svg_icon('check-circle', 'w-4 h-4'); ?><span>Approve</span>
                </button>
                <button type="button" class="btn ${declineBtnClass}" ${disabledAttr} title="Decline Report" onclick="showDeclineConfirmation('${ds.collection}', '${ds.id}', '${ds.fullName}', '${ds.slug}')">
                    <?php echo svg_icon('x-circle', 'w-4 h-4'); ?><span>Decline</span>
                </button>
            `;
            
            reportModal.classList.remove('pointer-events-none');
            reportModal.classList.add('opacity-100');
            modalContent.classList.remove('scale-95', 'opacity-0');
        };
        
        window.closeReportModal = function() {
            const reportModal = document.getElementById('reportModal');
            const modalContent = document.getElementById('modalContent');
            if (!reportModal || !modalContent) return;

            modalContent.classList.add('scale-95', 'opacity-0');
            reportModal.classList.remove('opacity-100');
            reportModal.classList.add('opacity-0');
            reportModal.addEventListener('transitionend', () => {
                reportModal.classList.add('pointer-events-none');
            }, { once: true });
        };
        
        // --- EXPORT FUNCTIONS ---
        window.showExportModal = function() {
            const exportModal = document.getElementById('exportModal');
            const modalContent = exportModal.querySelector('.relative');
            
            exportModal.classList.remove('opacity-0', 'pointer-events-none');
            modalContent.classList.remove('scale-95', 'opacity-0');
        };
        
        window.closeExportModal = function() {
            const exportModal = document.getElementById('exportModal');
            const modalContent = exportModal.querySelector('.relative');
            
            exportModal.classList.add('opacity-0', 'pointer-events-none');
            modalContent.classList.add('scale-95', 'opacity-0');
        };
        
        window.exportReports = function(format) {
            const category = document.getElementById('exportCategory').value;
            const url = `export_reports.php?format=${format}&category=${category}`;
            
            // Show loading state
            showToast('Preparing export...', 'info');
            
            // Create a temporary link to trigger download
            const link = document.createElement('a');
            link.href = url;
            link.download = '';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            // Close modal and show success message
            closeExportModal();
            setTimeout(() => {
                showToast(`Export completed! ${format.toUpperCase()} file downloaded.`, 'success');
            }, 1000);
        };
        
        // --- PROOF MODAL SCRIPT ---
        window.showProofModal = function(btn) {
            const proofModal = document.getElementById('proofModal');
            const modalContent = document.getElementById('proofModalContent');
            const ds = btn.dataset;

            document.getElementById('p_header').textContent = `Proof for ${ds.fullname}`;
            const imgEl = document.getElementById('p_image');
            const linkEl = document.getElementById('p_image_link');

            if (ds.proofurl) {
                console.log('Setting proof image URL:', ds.proofurl);
                
                // Clear any previous error messages
                const existingError = imgEl.parentNode.querySelector('.error-message');
                if (existingError) {
                    existingError.remove();
                }
                
                // Add loading state
                imgEl.style.opacity = '0.5';
                imgEl.alt = 'Loading...';
                linkEl.href = ds.proofurl;
                
                // Create a new image to test loading
                const testImg = new Image();
                testImg.onload = function() {
                    console.log('Image loaded successfully');
                    imgEl.src = ds.proofurl;
                    imgEl.alt = 'Proof of Residency';
                    imgEl.style.opacity = '1';
                };
                testImg.onerror = function() {
                    console.error('Failed to load image:', ds.proofurl);
                    imgEl.style.opacity = '1';
                    imgEl.alt = 'Failed to load image';
                    
                    // Show error message
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'error-message text-red-600 text-sm mt-2 text-center';
                    errorDiv.innerHTML = 'Failed to load image. <a href="' + ds.proofurl + '" target="_blank" class="underline text-blue-600">Click here to open directly</a>';
                    imgEl.parentNode.appendChild(errorDiv);
                };
                testImg.src = ds.proofurl;
            } else {
                console.error('No proof URL provided');
                imgEl.src = '';
                imgEl.alt = 'No image available';
                linkEl.href = '#';
            }

            proofModal.classList.remove('pointer-events-none');
            proofModal.classList.add('opacity-100');
            modalContent.classList.remove('scale-95', 'opacity-0');
        };

        window.closeProofModal = function() {
            const proofModal = document.getElementById('proofModal');
            const modalContent = document.getElementById('proofModalContent');
            if (!proofModal || !modalContent) return;

            modalContent.classList.add('scale-95', 'opacity-0');
            proofModal.classList.remove('opacity-100');
            proofModal.classList.add('opacity-0');
            proofModal.addEventListener('transitionend', () => {
                proofModal.classList.add('pointer-events-none');
            }, { once: true });
        };

        // --- ID MODAL SCRIPT FOR VERIFICATION DOCUMENTS ---
        window.showIdModal = function(btn, imageType) {
            const proofModal = document.getElementById('proofModal');
            const modalContent = document.getElementById('proofModalContent');
            const ds = btn.dataset;

            document.getElementById('p_header').textContent = `${imageType} - ${ds.fullname}`;
            const imgEl = document.getElementById('p_image');
            const linkEl = document.getElementById('p_image_link');

            if (ds.imageurl) {
                console.log('Setting ID image URL:', ds.imageurl);
                
                // Clear any previous error messages
                const existingError = imgEl.parentNode.querySelector('.error-message');
                if (existingError) {
                    existingError.remove();
                }
                
                // Add loading state
                imgEl.style.opacity = '0.5';
                imgEl.alt = 'Loading...';
                linkEl.href = ds.imageurl;
                
                // Create a new image to test loading
                const testImg = new Image();
                testImg.onload = function() {
                    console.log('ID image loaded successfully');
                    imgEl.src = ds.imageurl;
                    imgEl.alt = imageType;
                    imgEl.style.opacity = '1';
                };
                testImg.onerror = function() {
                    console.error('Failed to load ID image:', ds.imageurl);
                    imgEl.style.opacity = '1';
                    imgEl.alt = 'Failed to load image';
                    
                    // Show error message
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'error-message text-red-600 text-sm mt-2 text-center';
                    errorDiv.innerHTML = 'Failed to load image. <a href="' + ds.imageurl + '" target="_blank" class="underline text-blue-600">Click here to open directly</a>';
                    imgEl.parentNode.appendChild(errorDiv);
                };
                testImg.src = ds.imageurl;
            } else {
                console.error('No image URL provided');
                imgEl.src = '';
                imgEl.alt = 'No image available';
                linkEl.href = '#';
            }

            proofModal.classList.remove('pointer-events-none');
            proofModal.classList.add('opacity-100');
            modalContent.classList.remove('scale-95', 'opacity-0');
        };

        const segmentedStyle = document.createElement('style');
        segmentedStyle.innerHTML = `
            .segmented { display: inline-flex; align-items: center; gap: 4px; padding: 4px; border-radius: 9999px; background: rgba(226,232,240,0.7); border: 1px solid rgba(203,213,225,0.8); box-shadow: inset 0 1px 0 rgba(255,255,255,0.35); backdrop-filter: saturate(1.2); }
            .seg-btn { appearance: none; border: 0; background: transparent; color: #475569; font-weight: 700; font-size: 0.875rem; line-height: 1; padding: 0.5rem 0.75rem; border-radius: 9999px; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; transition: color .2s, transform .15s, background-color .2s, box-shadow .2s; will-change: transform; }
            .seg-btn:hover { color: #0c4a6e; transform: translateY(-1px); }
            .seg-btn:active { transform: translateY(0); }
            .seg-btn.active { background: #0284c7; color: #ffffff; box-shadow: 0 6px 14px rgba(2,132,199,0.25), inset 0 1px 0 rgba(255,255,255,.18); }
            .seg-btn .tab-count { min-width: 22px; height: 20px; padding: 0 6px; border-radius: 9999px; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800; letter-spacing: .2px; background: rgba(100,116,139,0.18); color: #475569; transition: background-color .2s, color .2s, transform .2s; }
            .seg-btn:hover .tab-count { transform: translateY(-1px); }
            .seg-btn.active .tab-count { background: rgba(255,255,255,0.28); color: #ffffff; }
            .panel-content { display: none; }
            .panel-content.active { display: block; }
        `;
        document.head.appendChild(segmentedStyle);

        (function() {
            const easeOutCubic = t => 1 - Math.pow(1 - t, 3);
            document.querySelectorAll('[data-countup]').forEach(el => {
                const target = Number(el.getAttribute('data-countup')) || 0;
                const dur = 900;
                const start = performance.now();
                function step(now) {
                    const p = Math.min(1, (now - start) / dur);
                    el.textContent = Math.round(target * easeOutCubic(p)).toLocaleString();
                    if (p < 1) requestAnimationFrame(step);
                }
                requestAnimationFrame(step);
            });
        })();

        (function() {
            document.querySelectorAll('.progress-seg').forEach(seg => {
                const w = seg.getAttribute('data-w') || '0%';
                seg.style.transition = 'width 900ms cubic-bezier(0.16, 1, 0.3, 1)';
                requestAnimationFrame(() => { seg.style.width = w; });
            });
        })();
        
        (function() {
            document.querySelectorAll('.segmented').forEach(group => {
                group.addEventListener('click', e => {
                    const btn = e.target.closest('.seg-btn');
                    if (!btn) return;
                    const tab = btn.dataset.tab;
                    const container = group.closest('.report-category-group');
                    if (!container) return;
                    group.querySelectorAll('.seg-btn').forEach(b => b.classList.toggle('active', b === btn));
                    container.querySelectorAll('.panel-content').forEach(p => {
                        p.classList.toggle('active', p.dataset.tab === tab);
                    });
                });
            });
        })();

        // Initialize global refresh function placeholder
        window.refreshRecentActivity = function() { 
            if (typeof loadRecentPage === 'function') {
                loadRecentPage(currentPage);
            } else {
                console.log('Recent activity refresh not initialized yet'); 
            }
        };

        (function() {
            const list = document.getElementById('activityList');
            if (!list) return;

            const pageSizeEl = document.getElementById('activityPageSize');
            const rangeEl   = document.getElementById('activityRange');
            const prevBtn   = document.getElementById('activityPrev');
            const nextBtn   = document.getElementById('activityNext');

            let total = 0;
            let currentPage = 1;
            let pageSize = pageSizeEl ? parseInt(pageSizeEl.value || '20', 10) : 20;

            // Enhanced loading with retry mechanism and better error handling
            async function loadRecentPage(page = 1, retryCount = 0, forceRefresh = false) {
                const maxRetries = 3;
                const retryDelay = 1000 * Math.pow(2, retryCount); // Exponential backoff
                
                // Show loading state with better feedback only on first load or explicit page change
                // Don't show full loading spinner on background refreshes
                const isBackgroundRefresh = window.isBackgroundRefresh === true;
                window.isBackgroundRefresh = false; // Reset flag

                if (retryCount === 0 && !isBackgroundRefresh && !forceRefresh) {
                    list.innerHTML = `
                        <div class="text-center py-16">
                            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-gradient-to-br from-blue-100 to-purple-100 flex items-center justify-center">
                                <svg class="w-8 h-8 text-blue-500 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                            </div>
                            <p class="text-lg font-semibold text-gray-600">Loading Recent Activity</p>
                            <p class="text-sm text-gray-400 mt-1">Fetching latest emergency reports...</p>
                        </div>
                    `;
                }
                
                try {
                    const searchEl = document.getElementById('activitySearch');
                    const categoryEl = document.getElementById('activityCategory');
                    const statusEl = document.getElementById('activityStatus');
                    
                    const fd = new FormData();
                    fd.append('api_action', 'recent_feed');
                    fd.append('page', String(page));
                    fd.append('pageSize', String(pageSize));
                    fd.append('search', searchEl ? searchEl.value.trim() : '');
                    fd.append('category', categoryEl ? categoryEl.value : 'all');
                    fd.append('status', statusEl ? statusEl.value : 'all');
                    
                    if (forceRefresh) {
                        fd.append('force_refresh', 'true');
                    }
                    
                    // Add timestamp to prevent browser caching
                    fd.append('_t', Date.now());
                    
                    const res = await fetch(window.location.href, {
                        method: 'POST',
                        body: fd
                    });
                    
                    if (!res.ok) {
                        throw new Error(`HTTP ${res.status}: ${res.statusText}`);
                    }
                    
                    const json = await res.json();
                    
                    if (!json || !json.success) {
                        throw new Error(json?.message || 'Failed to load recent activity');
                    }
                    
                    total = Number(json.total || 0);
                    currentPage = Number(json.page || page);
                    const data = Array.isArray(json.data) ? json.data : [];
                    
                    // Initialize server signature from the response if available
                    if (json.signature && page === 1) {
                        window.lastServerSignature = json.signature;
                    }
                    
                    // Store recent feed data globally for modal fallback
                    window.recentFeedData = data;
                    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
                    
                    // Debug: log approver fields for first approved item
                    const approvedItem = data.find(r => r.status?.toLowerCase() === 'approved');
                    if (approvedItem) {
                        console.log('Approved item data:', {
                            id: approvedItem.id,
                            status: approvedItem.status,
                            approvedBy: approvedItem.approvedBy,
                            approvedByName: approvedItem.approvedByName,
                            approvedAt: approvedItem.approvedAt,
                            updatedBy: approvedItem.updatedBy,
                            updatedAt: approvedItem.updatedAt
                        });
                    }
                    
                    const html = data.length > 0 ? data.map((row, index) => {
                        const st = String(row.status || 'Pending').toLowerCase();
                        const getStatusConfig = (status) => {
                            switch(status) {
                                case 'approved':
                                    return {
                                        bgColor: 'from-green-500 to-emerald-600',
                                        textColor: 'text-green-700',
                                        dotColor: 'bg-green-500',
                                        borderColor: 'border-green-200',
                                        label: 'Approved'
                                    };
                                case 'declined':
                                    return {
                                        bgColor: 'from-red-500 to-rose-600',
                                        textColor: 'text-red-700',
                                        dotColor: 'bg-red-500',
                                        borderColor: 'border-red-200',
                                        label: 'Declined'
                                    };
                                case 'responded':
                                    return {
                                        bgColor: 'from-blue-500 to-cyan-600',
                                        textColor: 'text-blue-700',
                                        dotColor: 'bg-blue-500',
                                        borderColor: 'border-blue-200',
                                        label: 'Responded'
                                    };
                                case 'resolved':
                                    return {
                                        bgColor: 'from-gray-500 to-slate-600',
                                        textColor: 'text-gray-700',
                                        dotColor: 'bg-gray-500',
                                        borderColor: 'border-gray-200',
                                        label: 'Resolved'
                                    };
                                default:
                                    return {
                                        bgColor: 'from-yellow-500 to-amber-600',
                                        textColor: 'text-yellow-700',
                                        dotColor: 'bg-yellow-500',
                                        borderColor: 'border-yellow-200',
                                        label: 'Pending'
                                    };
                            }
                        };
                        
                        const statusConfig = getStatusConfig(st);
                        
                        // Build approver/action info - check multiple possible fields
                        let actionInfo = '';
                        // For approved: show approver name, with multiple fallbacks
                        if (st === 'approved') {
                            const approverName = row.approvedByName || row.updatedBy || '';
                            const approveTime = row.approvedAt || row.updatedAt || '';
                            if (approverName) {
                                actionInfo = `<div class="flex items-center gap-1.5 mt-2 text-xs text-green-600">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <span>Approved by <strong>${esc(approverName)}</strong>${approveTime ? ' • ' + esc(approveTime) : ''}</span>
                                </div>`;
                            }
                        } else if (st === 'declined') {
                            const declinerName = row.declinedByName || row.updatedBy || '';
                            const declineTime = row.declinedAt || row.updatedAt || '';
                            if (declinerName) {
                                actionInfo = `<div class="flex items-center gap-1.5 mt-2 text-xs text-red-600">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <span>Declined by <strong>${esc(declinerName)}</strong>${declineTime ? ' • ' + esc(declineTime) : ''}</span>
                                </div>`;
                            }
                        } else if (st === 'responded') {
                            const responderName = row.respondedByName || row.respondedBy || '';
                            const respondTime = row.respondedAt || '';
                            if (responderName) {
                                actionInfo = `<div class="flex items-center gap-1.5 mt-2 text-xs text-blue-600">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                    </svg>
                                    <span>Responded by <strong>${esc(responderName)}</strong>${respondTime ? ' • ' + esc(respondTime) : ''}</span>
                                </div>`;
                            }
                        }
                        
                        return `
                        <li
                            onclick="showReportModal(this)" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();showReportModal(this);}"
                            role="button" tabindex="0"
                            data-slug="${esc(row.slug)}" data-id="${esc(row.id)}" data-collection="${esc(row.collection)}"
                            data-fullname="${esc(row.fullName)}" data-contact="${esc(row.mobileNumber || row.contact)}"
                            data-location="${esc(row.location)}" data-purpose="${esc(row.purpose)}"
                            data-reporterid="${esc(row.reporterId)}" data-imageurl="${esc(row.imageUrl)}"
                            data-status="${esc(st)}" data-timestamp="${esc(row.tsDisplay)}"
                            data-lat="${esc(row.lat)}" data-lng="${esc(row.lng)}"
                            data-approvedbyname="${esc(row.approvedByName || '')}" data-approvedat="${esc(row.approvedAt || '')}"
                            data-declinedbyname="${esc(row.declinedByName || '')}" data-declinedat="${esc(row.declinedAt || '')}"
                            data-respondedbyname="${esc(row.respondedByName || '')}" data-respondedat="${esc(row.respondedAt || '')}"
                            class="glass-card p-5 cursor-pointer animate-fade-in-up group hover:scale-[1.02] transition-all duration-300"
                            style="--anim-delay: ${index * 50}ms"
                        >
                            <div class="flex items-start gap-4">
                                <div class="relative flex-shrink-0">
                                    <div class="w-14 h-14 rounded-2xl bg-gradient-to-br ${statusConfig.bgColor} flex items-center justify-center text-white shadow-lg">
                                        ${row.iconSvg}
                                    </div>
                                    <div class="absolute -top-1 -right-1 w-5 h-5 ${statusConfig.dotColor} rounded-full border-2 border-white shadow-sm animate-pulse"></div>
                                </div>
                                
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-start justify-between gap-3 mb-2">
                                        <div>
                                            <h4 class="text-base font-bold text-gray-800 mb-1">${esc(row.label)}</h4>
                                            <p class="text-sm font-semibold text-gray-600">${esc(row.fullName || 'Unknown')}</p>
                                        </div>
                                        <div class="text-right flex-shrink-0">
                                            <span class="text-xs text-gray-500 font-medium">${esc(row.tsDisplay)}</span>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center gap-2 mb-3">
                                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        </svg>
                                        <span class="text-sm text-gray-500 truncate">${esc(row.location || 'No location specified')}</span>
                                    </div>
                                    
                                    <div class="flex items-center justify-between">
                                        <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-semibold ${statusConfig.textColor} bg-gradient-to-r from-white to-gray-50 border ${statusConfig.borderColor} shadow-sm">
                                            <span class="w-2 h-2 rounded-full ${statusConfig.dotColor} animate-pulse"></span>
                                            ${statusConfig.label}
                                        </span>
                                        
                                        <div class="opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                            </svg>
                                        </div>
                                    </div>
                                    ${actionInfo}
                                </div>
                            </div>
                        </li>`;
                    }).join('') : `
                        <div class="text-center py-16">
                            <div class="w-20 h-20 mx-auto mb-6 rounded-full bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center">
                                <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                                </svg>
                            </div>
                            <p class="text-xl font-semibold text-gray-600 mb-2">No Recent Activity</p>
                            <p class="text-gray-400">No emergency reports found matching your criteria</p>
                        </div>
                    `;

                    list.innerHTML = html;
                    const totalPages = Math.max(1, Math.ceil(total / pageSize));
                    if (rangeEl) {
                        const start = total ? ((currentPage - 1) * pageSize + 1) : 0;
                        const end = total ? Math.min(currentPage * pageSize, total) : 0;
                        rangeEl.textContent = `Showing ${start}-${end} of ${total}`;
                    }
                    if (prevBtn) prevBtn.disabled = currentPage <= 1;
                    if (nextBtn) nextBtn.disabled = currentPage >= totalPages;
                    
                    // Update activity count display with performance info
                    const countEl = document.getElementById('activityCount');
                    if (countEl) {
                        const filters = json.filters || {};
                        const hasFilters = filters.search || filters.category !== 'all' || filters.status !== 'all';
                        const perfInfo = json.executionTime ? ` (${json.executionTime})` : '';
                        countEl.textContent = hasFilters ? `${total} filtered results${perfInfo}` : `Last ${Math.min(total, 50)} updates${perfInfo}`;
                    }
                    
                } catch (error) {
                    console.error("Failed to load recent page:", error);
                    
                    if (retryCount < maxRetries && (error.message.includes('timeout') || error.message.includes('fetch'))) {
                        // Retry with exponential backoff
                        setTimeout(() => {
                            loadRecentPage(page, retryCount + 1);
                        }, retryDelay);
                        
                        list.innerHTML = `
                            <div class="text-center py-16">
                                <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-gradient-to-br from-yellow-100 to-orange-100 flex items-center justify-center">
                                    <svg class="w-8 h-8 text-yellow-500 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                    </svg>
                                </div>
                                <p class="text-lg font-semibold text-gray-600">Retrying Connection</p>
                                <p class="text-sm text-gray-400 mt-1">Attempt ${retryCount + 1} of ${maxRetries}</p>
                            </div>
                        `;
                    } else {
                        // Show error with retry button
                        list.innerHTML = `
                            <div class="text-center py-16">
                                <div class="w-20 h-20 mx-auto mb-6 rounded-full bg-gradient-to-br from-red-100 to-pink-100 flex items-center justify-center">
                                    <svg class="w-10 h-10 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.464 0L4.35 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                    </svg>
                                </div>
                                <p class="text-xl font-semibold text-red-600 mb-2">Connection Failed</p>
                                <p class="text-gray-500 mb-6 max-w-sm mx-auto">Unable to load recent activity: ${error.message}</p>
                                <button onclick="loadRecentPage(${page})" class="btn btn-primary glow">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                    </svg>
                                    Try Again
                                </button>
                            </div>
                        `;
                    }
                }
            }

            // Enhanced event listeners with real-time filtering
            if (pageSizeEl) pageSizeEl.addEventListener('change', () => {
                pageSize = parseInt(pageSizeEl.value || '20', 10) || 20;
                loadRecentPage(1);
            });
            
            if (prevBtn) prevBtn.addEventListener('click', () => currentPage > 1 && loadRecentPage(currentPage - 1));
            if (nextBtn) nextBtn.addEventListener('click', () => {
                const totalPages = Math.max(1, Math.ceil(total / pageSize));
                if (currentPage < totalPages) loadRecentPage(currentPage + 1);
            });

            // Real-time search functionality
            const searchEl = document.getElementById('activitySearch');
            const categoryEl = document.getElementById('activityCategory');
            const statusEl = document.getElementById('activityStatus');
            const resetEl = document.getElementById('activityReset');
            
            // Debounce function for search input
            function debounce(func, wait) {
                let timeout;
                return function executedFunction(...args) {
                    const later = () => {
                        clearTimeout(timeout);
                        func(...args);
                    };
                    clearTimeout(timeout);
                    timeout = setTimeout(later, wait);
                };
            }
            
            // Real-time search with debouncing
            if (searchEl) {
                searchEl.addEventListener('input', debounce(() => {
                    currentPage = 1; // Reset to first page when searching
                    loadRecentPage(1);
                }, 300));
            }
            
            // Instant category filtering
            if (categoryEl) {
                categoryEl.addEventListener('change', () => {
                    currentPage = 1;
                    loadRecentPage(1);
                });
            }
            
            // Instant status filtering
            if (statusEl) {
                statusEl.addEventListener('change', () => {
                    currentPage = 1;
                    loadRecentPage(1);
                });
            }
            
            // Reset filters
            if (resetEl) {
                resetEl.addEventListener('click', () => {
                    if (searchEl) searchEl.value = '';
                    if (categoryEl) categoryEl.value = 'all';
                    if (statusEl) statusEl.value = 'all';
                    currentPage = 1;
                    loadRecentPage(1);
                });
            }

            // Initial load with cache warming
            loadRecentPage(1);
            
            // Pre-warm cache for common filters
            setTimeout(() => {
                const warmCache = async () => {
                    try {
                        const fd = new FormData();
                        fd.append('api_action', 'recent_feed');
                        fd.append('page', '1');
                        fd.append('pageSize', '10');
                        fd.append('category', 'all');
                        fd.append('status', 'all');
                        await fetch(window.location.href, { method: 'POST', body: fd });
                    } catch (e) {
                        // Silent fail for cache warming
                    }
                };
                warmCache();
            }, 2000);

            // Expose loadRecentPage to global scope
            window.loadRecentPage = loadRecentPage;
            
            // Override global refresh function
            window.refreshRecentActivity = function() {
                window.isBackgroundRefresh = true; // Use background refresh to avoid spinner
                // Check if force refresh was requested (e.g., from head-check signature change)
                const shouldForceRefresh = window.forceRecentFeedRefresh === true;
                window.forceRecentFeedRefresh = false;
                console.log('[RecentActivity] Refreshing feed, forceRefresh:', shouldForceRefresh);
                loadRecentPage(currentPage, 0, shouldForceRefresh);
            };
            
            console.log('[RecentActivity] Inline JS initialized');

            // Check for updates every 2 seconds (Faster polling for realtime feel)
            async function checkForUpdates() {
                // Only poll if tab is visible to save resources
                if (document.hidden) return;
                
                try {
                    const categoryEl = document.getElementById('activityCategory');
                    const statusEl = document.getElementById('activityStatus');
                    
                    const fd = new FormData();
                    fd.append('api_action', 'check_recent_updates');
                    fd.append('category', categoryEl ? categoryEl.value : 'all');
                    fd.append('status', statusEl ? statusEl.value : 'all');
                    
                    const res = await fetch(window.location.href, { method: 'POST', body: fd });
                    const json = await res.json();
                    
                    if (json.success) {
                        // If we have a new signature that is different from our last known one
                        // This ensures we only reload the full list when there is actually new data
                        // We use a simple MD5-like comparison (server sends MD5, we can't easily MD5 on client without lib)
                        // So we rely on the server sending a signature, and we just check if it changed from what we last saw
                        // Wait, we can't compare server MD5 with client string.
                        // Let's just store the server signature!
                        
                        if (json.signature && json.signature !== window.lastServerSignature) {
                            console.log('New activity detected (signature change), refreshing feed...');
                            window.lastServerSignature = json.signature; // Update immediately to prevent double refresh
                            window.isBackgroundRefresh = true;
                            // Force refresh to bypass cache
                            loadRecentPage(currentPage, 0, true);
                        }
                    }
                } catch (e) {
                    // Silent fail for background checks
                    // console.error('Failed to check for updates:', e);
                }
            }

            setInterval(checkForUpdates, 2000);
        })();

        // KPI Helper Functions for Overview Section
        function getKpiAggregatesFromStats(stats) {
            let totalPending = 0, totalApproved = 0, totalDeclined = 0, totalResponded = 0, grandTotal = 0;
            
            Object.values(stats).forEach(stat => {
                totalPending += parseInt(stat.pending || 0);
                totalApproved += parseInt(stat.approved || 0);
                totalDeclined += parseInt(stat.declined || 0);
                totalResponded += parseInt(stat.responded || 0);
                grandTotal += parseInt(stat.total || 0);
            });
            
            return {
                pending: totalPending,
                approved: totalApproved,
                declined: totalDeclined,
                responded: totalResponded,
                total: grandTotal
            };
        }
        
        function pushKpiHistory(aggregates) {
            try {
                const history = JSON.parse(localStorage.getItem('kpiHistory') || '[]');
                const now = Date.now();
                
                // Add current values with timestamp
                history.push({
                    timestamp: now,
                    ...aggregates
                });
                
                // Keep only last 50 entries (about 24 hours of data if updated every 30 minutes)
                if (history.length > 50) {
                    history.splice(0, history.length - 50);
                }
                
                localStorage.setItem('kpiHistory', JSON.stringify(history));
            } catch (e) {
                console.warn('Failed to save KPI history:', e);
            }
        }
        
        function drawSparkline(element, values, strokeColor = '#0284c7') {
            if (!values || values.length < 2) {
                element.innerHTML = '<div class="text-xs text-slate-400">Insufficient data</div>';
                return;
            }
            
            const width = 60;
            const height = 20;
            const max = Math.max(...values, 1);
            const min = Math.min(...values);
            const range = max - min || 1;
            
            const points = values.map((value, index) => {
                const x = (index / (values.length - 1)) * width;
                const y = height - ((value - min) / range) * height;
                return `${x},${y}`;
            }).join(' ');
            
            element.innerHTML = `
                <svg width="${width}" height="${height}" class="opacity-70">
                    <polyline
                        fill="none"
                        stroke="${strokeColor}"
                        stroke-width="1.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        points="${points}"
                    />
                </svg>
            `;
        }
        
        function renderTopKpis(aggregates) {
            const container = document.getElementById('topKpiContainer');
            if (!container) return;
            
            try {
                const history = JSON.parse(localStorage.getItem('kpiHistory') || '[]');
                const kpis = [
                    { key: 'pending', label: 'Pending', value: aggregates.pending, color: 'amber' },
                    { key: 'approved', label: 'Approved', value: aggregates.approved, color: 'emerald' },
                    { key: 'responded', label: 'Responded', value: aggregates.responded, color: 'cyan' },
                    { key: 'declined', label: 'Declined', value: aggregates.declined, color: 'rose' },
                    { key: 'total', label: 'Total', value: aggregates.total, color: 'slate' }
                ];
                
                container.innerHTML = kpis.map(kpi => {
                    const historicalValues = history.map(h => h[kpi.key] || 0);
                    const sparklineId = `sparkline-${kpi.key}`;
                    
                    return `
                        <div class="kpi-card">
                            <div class="kpi-label">${kpi.label}</div>
                            <div class="kpi-value text-${kpi.color}-600" data-countup="${kpi.value}">${kpi.value}</div>
                            <div id="${sparklineId}" class="kpi-sparkline"></div>
                        </div>
                    `;
                }).join('');
                
                // Draw sparklines after DOM update
                setTimeout(() => {
                    kpis.forEach(kpi => {
                        const element = document.getElementById(`sparkline-${kpi.key}`);
                        if (element) {
                            const historicalValues = history.map(h => h[kpi.key] || 0);
                            const colors = {
                                'amber': '#f59e0b',
                                'emerald': '#10b981',
                                'cyan': '#06b6d4',
                                'rose': '#f43f5e',
                                'slate': '#64748b'
                            };
                            drawSparkline(element, historicalValues, colors[kpi.color]);
                        }
                    });
                }, 50);
                
                // Trigger count-up animations
                setTimeout(() => {
                    container.querySelectorAll('[data-countup]').forEach(el => {
                        const target = parseInt(el.dataset.countup) || 0;
                        animateCount(el, target);
                    });
                }, 100);
                
            } catch (e) {
                console.error('Error rendering KPIs:', e);
                container.innerHTML = kpis.map(kpi => `
                    <div class="kpi-card">
                        <div class="kpi-label">${kpi.label}</div>
                        <div class="kpi-value text-${kpi.color}-600">${kpi.value}</div>
                    </div>
                `).join('');
            }
        }

        // Function to refresh admin statistics
        window.refreshAdminStats = async function() {
            const container = document.getElementById('adminStatsContainer');
            if (!container) return;
            
            // Show brief loading state
            const originalContent = container.innerHTML;
            container.innerHTML = `
                <div class="col-span-full text-center py-6 text-slate-500">
                    <div class="inline-flex items-center gap-2">
                        ${svg_icon('spinner', 'w-4 h-4 animate-spin')}
                        Refreshing statistics...
                    </div>
                </div>
            `;
            
            try {
                const formData = createFormDataWithCsrf();
                formData.append('api_action', 'load_admin_stats');
                formData.append('force_refresh', 'true'); // Force fresh data
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    const container = document.getElementById('adminStatsContainer');
                    if (container) {
                        const categories = <?php echo json_encode($categories); ?>;
                        const stats = result.data;
                        
                        // Clear loading state
                        container.innerHTML = '';
                        
                        // Render stats cards
                        Object.entries(categories).forEach(([slug, meta]) => {
                            const stat = stats[slug] || { total: 0, approved: 0, pending: 0, declined: 0, responded: 0 };
                            const total = Math.max(0, parseInt(stat.total) || 0);
                            const approved = Math.max(0, parseInt(stat.approved) || 0);
                            const pending = Math.max(0, parseInt(stat.pending) || 0);
                            const declined = Math.max(0, parseInt(stat.declined) || 0);
                            const responded = Math.max(0, parseInt(stat.responded) || 0);
                            
                            const approvedPct = total > 0 ? Math.round((approved / total) * 100) : 0;
                            const pendingPct = total > 0 ? Math.round((pending / total) * 100) : 0;
                            const declinedPct = total > 0 ? Math.round((declined / total) * 100) : 0;
                            const respondedPct = total > 0 ? Math.round((responded / total) * 100) : 0;
                            
                            const card = document.createElement('div');
                            card.className = 'stat-card p-5';
                            card.innerHTML = `
                                <div class="flex items-center gap-4 mb-4">
                                    <div class="w-12 h-12 rounded-xl bg-${meta.color}-100 text-${meta.color}-600 flex items-center justify-center">
                                        ${svg_icon(meta.icon, 'w-7 h-7')}
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-bold text-slate-800">${meta.label}</h3>
                                        <p class="text-xs text-slate-500">Overview</p>
                                    </div>
                                </div>
                                <div class="grid grid-cols-4 gap-2 text-center mb-4">
                                    <div>
                                        <div class="text-2xl font-extrabold text-amber-600 tracking-tighter">
                                            <span data-countup="${pending}" data-status="pending">${pending}</span>
                                        </div>
                                        <div class="text-[10px] text-slate-500 uppercase tracking-wider font-medium">Pend</div>
                                    </div>
                                    <div>
                                        <div class="text-2xl font-extrabold text-emerald-600 tracking-tighter">
                                            <span data-countup="${approved}" data-status="approved">${approved}</span>
                                        </div>
                                        <div class="text-[10px] text-slate-500 uppercase tracking-wider font-medium">Appr</div>
                                    </div>
                                    <div>
                                        <div class="text-2xl font-extrabold text-cyan-600 tracking-tighter">
                                            <span data-countup="${responded}" data-status="responded">${responded}</span>
                                        </div>
                                        <div class="text-[10px] text-slate-500 uppercase tracking-wider font-medium">Resp</div>
                                    </div>
                                    <div>
                                        <div class="text-2xl font-extrabold text-red-600 tracking-tighter">
                                            <span data-countup="${declined}" data-status="declined">${declined}</span>
                                        </div>
                                        <div class="text-[10px] text-slate-500 uppercase tracking-wider font-medium">Decl</div>
                                    </div>
                                </div>
                                <div class="space-y-2">
                                    <div class="progress-track">
                                        <span class="progress-seg pending" data-w="${pendingPct}%"></span>
                                        <span class="progress-seg approved" data-w="${approvedPct}%"></span>
                                        <span class="progress-seg responded" style="background-color: #06b6d4;" data-w="${respondedPct}%"></span>
                                        <span class="progress-seg declined" data-w="${declinedPct}%"></span>
                                    </div>
                                    <div class="grid grid-cols-2 gap-1 text-[10px] text-slate-500">
                                        <div class="flex items-center gap-1">
                                            <span class="inline-block w-2 h-2 rounded-full bg-amber-500"></span>
                                            <span>${pendingPct}% Pend</span>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <span class="inline-block w-2 h-2 rounded-full bg-emerald-500"></span>
                                            <span>${approvedPct}% Appr</span>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <span class="inline-block w-2 h-2 rounded-full bg-cyan-500"></span>
                                            <span>${respondedPct}% Resp</span>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <span class="inline-block w-2 h-2 rounded-full bg-red-500"></span>
                                            <span>${declinedPct}% Decl</span>
                                        </div>
                                    </div>
                                </div>
                            `;
                            
                            container.appendChild(card);
                        });
                        
                        // Update Overview KPIs with aggregated data
                        const aggregates = getKpiAggregatesFromStats(stats);
                        renderTopKpis(aggregates);
                        pushKpiHistory(aggregates);
                        
                        // Trigger countup animations
                        setTimeout(() => {
                            document.querySelectorAll('[data-countup]').forEach(el => {
                                const target = parseInt(el.dataset.countup) || 0;
                                animateCount(el, target);
                            });
                        }, 100);
                        
                        console.log('Admin stats refreshed successfully:', result.executionTime);
                    }
                } else {
                    console.error('Failed to refresh admin stats:', result.message);
                    showToast('Failed to refresh statistics: ' + result.message, 'error');
                    // Restore original content on error
                    container.innerHTML = originalContent;
                }
            } catch (error) {
                console.error('Error refreshing admin stats:', error);
                showToast('Error refreshing statistics: ' + error.message, 'error');
                // Restore original content on error
                container.innerHTML = originalContent;
            }
        };

        // Initial load of admin statistics and Overview KPIs
        setTimeout(() => {
            refreshAdminStats();
        }, 1000);

        // Quick Action: Clear All Cache
        window.clearAllCache = async function() {
            try {
                showToast('Clearing cache...', 'info');
                
                const formData = new FormData();
                formData.append('api_action', 'clear_cache');
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast('Cache cleared successfully', 'success');
                    // Refresh stats after clearing cache
                    setTimeout(() => refreshAdminStats(), 500);
                } else {
                    showToast('Failed to clear cache: ' + result.message, 'error');
                }
            } catch (error) {
                showToast('Error clearing cache: ' + error.message, 'error');
            }
        };



        // Quick Action: Test Notifications (Debug triple notifications)
        window.testNotifications = async function() {
            try {
                const collection = prompt('Enter collection name (e.g., flood_reports):');
                const docId = prompt('Enter document ID:');
                
                if (!collection || !docId) {
                    showToast('Collection and Document ID are required', 'error');
                    return;
                }
                
                showToast('Testing notification flow...', 'info');
                
                const formData = new FormData();
                formData.append('api_action', 'test_notifications');
                formData.append('collection', collection);
                formData.append('docId', docId);
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    console.log('Notification Test Results:', result.results);
                    showToast('✅ Test completed! Check browser console and server logs for details.', 'success');
                    
                    // Show key results in a more readable format
                    const results = result.results;
                    let summary = `Test Results for ${results.collection}/${results.docId}:\n`;
                    summary += `Reporter ID: ${results.reporterId}\n`;
                    summary += `Emergency Type: ${results.emergency_type}\n`;
                    summary += `Total Responders: ${results.total_responders}\n`;
                    summary += `Reporter is Responder: ${results.reporter_is_responder ? 'YES' : 'NO'}\n`;
                    summary += `User Notification: ${results.user_notification ? 'SUCCESS' : 'FAILED'}\n`;
                    summary += `Responder Notification: ${results.responder_notification ? 'SUCCESS' : 'FAILED'}`;
                    
                    alert(summary);
                } else {
                    showToast('❌ Test failed: ' + result.message, 'error');
                }
            } catch (error) {
                showToast('❌ Test error: ' + error.message, 'error');
            }
        };

        // Quick Action: View Pending Reports
        window.viewPendingReports = function() {
            // Find any pending tab and click it
            const pendingTabs = document.querySelectorAll('[data-tab="pending"]');
            if (pendingTabs.length > 0) {
                pendingTabs[0].click();
                pendingTabs[0].scrollIntoView({ behavior: 'smooth' });
                showToast('Switched to Pending reports view', 'success');
            } else {
                showToast('No pending reports tabs found', 'warning');
            }
        };

        // Admin: Load dashboard statistics asynchronously
        <?php if ($isAdmin && $view === 'analytics'): ?>
        (async () => {
            try {
                // Show loading states for both sections
                const statsContainer = document.getElementById('adminStatsContainer');
                const recentContainer = document.getElementById('recentActivityList');
                
                if (statsContainer) {
                    statsContainer.innerHTML = `
                        <div class="col-span-full text-center py-6 text-slate-500">
                            <div class="inline-flex items-center gap-2">
                                ${svg_icon('spinner', 'w-4 h-4 animate-spin')}
                                <span class="text-sm">Loading statistics...</span>
                            </div>
                        </div>
                    `;
                }
                
                // Prepare both requests in parallel
                const statsFormData = new FormData();
                statsFormData.append('api_action', 'load_admin_stats');
                
                const recentFormData = new FormData();
                recentFormData.append('api_action', 'recent_feed');
                recentFormData.append('page', '1');
                recentFormData.append('pageSize', '10');
                recentFormData.append('search', '');
                recentFormData.append('category', 'all');
                recentFormData.append('status', 'all');
                
                // Execute both requests simultaneously
                const [statsResponse, recentResponse] = await Promise.all([
                    fetch(window.location.href, {
                    method: 'POST',
                        body: statsFormData
                    }),
                    fetch(window.location.href, {
                        method: 'POST',
                        body: recentFormData
                    })
                ]);
                
                // Process stats response
                const statsResult = await statsResponse.json();
                if (statsResult.success) {
                    const container = document.getElementById('adminStatsContainer');
                    if (container) {
                        const categories = <?php echo json_encode($categories); ?>;
                        const stats = statsResult.data;
                        
                        // Clear loading state
                        container.innerHTML = '';
                        
                        // Render stats cards
                        Object.entries(categories).forEach(([slug, meta]) => {
                            const stat = stats[slug] || { total: 0, approved: 0, pending: 0, declined: 0 };
                            const total = Math.max(0, parseInt(stat.total) || 0);
                            const approved = Math.max(0, parseInt(stat.approved) || 0);
                            const pending = Math.max(0, parseInt(stat.pending) || 0);
                            const declined = Math.max(0, parseInt(stat.declined) || 0);
                            
                            const approvedPct = total > 0 ? Math.round((approved / total) * 100) : 0;
                            const pendingPct = total > 0 ? Math.round((pending / total) * 100) : 0;
                            const declinedPct = total > 0 ? Math.round((declined / total) * 100) : 0;
                            
                            const card = document.createElement('div');
                            card.className = 'stat-card p-5';
                            card.innerHTML = `
                                <div class="flex items-center gap-4 mb-4">
                                    <div class="w-12 h-12 rounded-xl bg-${meta.color}-100 text-${meta.color}-600 flex items-center justify-center">
                                        ${svg_icon(meta.icon, 'w-7 h-7')}
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-bold text-slate-800">${meta.label}</h3>
                                        <p class="text-xs text-slate-500">Overview</p>
                                    </div>
                                </div>
                                <div class="grid grid-cols-3 gap-3 text-center mb-4">
                                    <div>
                                        <div class="text-4xl font-extrabold text-amber-600 tracking-tighter">
                                            <span data-countup="${pending}" data-status="pending">${pending}</span>
                                        </div>
                                        <div class="text-xs text-slate-500 uppercase tracking-wider font-medium">Pending</div>
                                    </div>
                                    <div>
                                        <div class="text-4xl font-extrabold text-emerald-600 tracking-tighter">
                                            <span data-countup="${approved}" data-status="approved">${approved}</span>
                                        </div>
                                        <div class="text-xs text-slate-500 uppercase tracking-wider font-medium">Approved</div>
                                    </div>
                                    <div>
                                        <div class="text-4xl font-extrabold text-red-600 tracking-tighter">
                                            <span data-countup="${declined}" data-status="declined">${declined}</span>
                                        </div>
                                        <div class="text-xs text-slate-500 uppercase tracking-wider font-medium">Declined</div>
                                    </div>
                                </div>
                                <div class="space-y-2">
                                    <div class="progress-track">
                                        <span class="progress-seg pending" data-w="${pendingPct}%"></span>
                                        <span class="progress-seg approved" data-w="${approvedPct}%"></span>
                                        <span class="progress-seg declined" data-w="${declinedPct}%"></span>
                                    </div>
                                    <div class="flex items-center justify-between text-xs text-slate-500">
                                        <div class="flex items-center gap-2">
                                            <span class="inline-block w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                                            <span>${pendingPct}% Pending</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="inline-block w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                                            <span>${approvedPct}% Approved</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="inline-block w-2.5 h-2.5 rounded-full bg-red-500"></span>
                                            <span>${declinedPct}% Declined</span>
                                        </div>
                                    </div>
                                </div>
                            `;
                            
                            container.appendChild(card);
                        });
                        
                        // Trigger countup animations
                        setTimeout(() => {
                            document.querySelectorAll('[data-countup]').forEach(el => {
                                const target = parseInt(el.dataset.countup) || 0;
                                animateCount(el, target);
                            });
                        }, 100);
                        
                        console.log('Admin stats loaded successfully:', statsResult.executionTime);
                    }
                } else {
                    console.error('Failed to load admin stats:', statsResult.message);
                    showToast('Failed to load statistics: ' + statsResult.message, 'error');
                    
                    // Show error state in container
                    const container = document.getElementById('adminStatsContainer');
                    if (container) {
                        container.innerHTML = `
                            <div class="col-span-full text-center py-8 text-red-500">
                                <div class="inline-flex items-center gap-3">
                                    ${svg_icon('x-mark', 'w-5 h-5')}
                                    <div>
                                        <div class="text-sm font-medium">Failed to load statistics</div>
                                        <div class="text-xs text-red-400">Click to retry</div>
                                    </div>
                                </div>
                            </div>
                        `;
                        container.onclick = () => {
                            container.onclick = null;
                            window.location.reload();
                        };
                        container.style.cursor = 'pointer';
                    }
                }
                
                // Process recent activity response in parallel
                const recentResult = await recentResponse.json();
                if (recentResult.success && recentContainer) {
                    // Update recent activity list immediately
                    if (typeof loadRecentPage === 'function') {
                        // Manually trigger the recent activity update with the cached data
                        displayRecentItems(recentResult.data);
                        console.log('Recent activity loaded successfully:', recentResult.executionTime);
                    }
                } else if (recentResult && !recentResult.success) {
                    console.error('Failed to load recent activity:', recentResult.message);
                }
                
                // Prefetch data for next load (background refresh)
                setTimeout(() => {
                    if (document.visibilityState === 'visible') {
                        Promise.all([
                            fetch(window.location.href, {
                                method: 'POST',
                                body: (() => {
                                    const fd = new FormData();
                                    fd.append('api_action', 'load_admin_stats');
                                    return fd;
                                })()
                            }),
                            fetch(window.location.href, {
                                method: 'POST',
                                body: (() => {
                                    const fd = new FormData();
                                    fd.append('api_action', 'recent_feed');
                                    fd.append('page', '1');
                                    fd.append('pageSize', '10');
                                    fd.append('search', '');
                                    fd.append('category', 'all');
                                    fd.append('status', 'all');
                                    return fd;
                                })()
                            })
                        ]).then(() => {
                            console.log('Admin dashboard data prefetched for next load');
                        }).catch(() => {}); // Silent fail for prefetch
                    }
                }, 30000); // Prefetch after 30 seconds
                
            } catch (error) {
                console.error('Error loading admin stats:', error);
                let errorMessage = 'Error loading statistics: ' + error.message;
                if (error.name === 'AbortError') {
                    errorMessage = 'Statistics loading timed out. Please try again.';
                }
                showToast(errorMessage, 'error');
                
                // Show error state in container
                const container = document.getElementById('adminStatsContainer');
                if (container) {
                    container.innerHTML = `
                        <div class="col-span-full text-center py-8 text-red-500">
                            <div class="inline-flex items-center gap-3">
                                ${svg_icon('x-mark', 'w-5 h-5')}
                                <div>
                                    <div class="text-sm font-medium">Failed to load statistics</div>
                                    <div class="text-xs text-red-400">Click to retry</div>
                                </div>
                            </div>
                        </div>
                    `;
                    container.onclick = () => {
                        container.onclick = null;
                        window.location.reload();
                    };
                    container.style.cursor = 'pointer';
                }
            }
        })();
        <?php endif; ?>

        window.updateActivityItemStatus = function(id, newStatus) {
            const li = document.querySelector(`#activityList li[data-id="${id}"]`);
            if (!li) return;

            const st = (newStatus || 'Pending').toLowerCase();
            li.dataset.status = st;

            const badge = li.querySelector('.status-badge');
            if (badge) {
                badge.className = 'mt-2 inline-flex status-badge';
                if (st === 'approved') badge.classList.add('status-badge-success');
                else if (st === 'declined') badge.classList.add('status-badge-declined');
                else badge.classList.add('status-badge-pending');
                badge.innerHTML = `<span class="h-2 w-2 rounded-full bg-current mr-2"></span>${newStatus}`;
            }
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                window.closeReportModal();
                window.closeProofModal();
            }
        });

        // User Verification JavaScript (only run on verify-users view)
        if (window.location.search.includes('view=verify-users')) {
            (function() {
                const vuList = document.getElementById('vuList');
                const vuLoading = document.getElementById('vuLoading');
                const vuEmpty = document.getElementById('vuEmpty');
                const vuRange = document.getElementById('vuRange');
                const vuPrev = document.getElementById('vuPrev');
                const vuNext = document.getElementById('vuNext');
                const vuPageSize = document.getElementById('vuPageSize');
                const vuSearch = document.getElementById('vuSearch');
                const vuRefresh = document.getElementById('vuRefresh');


                let currentPage = 1;
                let pageSize = 20;
                let searchTerm = '';
                let totalUsers = 0;
                let lastCheckTime = new Date().toISOString();
                let realTimeSyncInterval = null;
                let isRealTimeUpdating = false;

                // Start real-time sync for pending users - Fresh session every 2 seconds
                function startRealTimeSync() {
                    if (realTimeSyncInterval) {
                        clearInterval(realTimeSyncInterval);
                    }
                    
                    console.log('🚀 Starting fresh session real-time sync for pending users...');
                    
                    // Check for new users every 2 seconds with fresh session
                    realTimeSyncInterval = setInterval(async () => {
                        if (isRealTimeUpdating || searchTerm.trim() !== '') {
                            return; // Skip if already updating or if search is active
                        }
                        
                        try {
                            isRealTimeUpdating = true;
                            
                            // 🔄 Reset session every 2 seconds for fresh detection
                            console.log('🔄 Resetting session for fresh user detection...');
                            const resetFormData = new FormData();
                            resetFormData.append('api_action', 'reset_user_session');
                            
                            await fetch(window.location.href, {
                                method: 'POST',
                                body: resetFormData
                            });
                            
                            // Silent background check for new users with fresh session
                            const formData = new FormData();
                            formData.append('api_action', 'get_new_pending_users');
                            formData.append('last_check', lastCheckTime);
                            
                            const response = await fetch(window.location.href, {
                                method: 'POST',
                                body: formData
                            });
                            
                            // Check if response is valid JSON
                            const responseText = await response.text();
                            let result;
                            try {
                                result = JSON.parse(responseText);
                            } catch (parseError) {
                                console.error('Invalid JSON response:', responseText);
                                return;
                            }
                            
                            console.log('Real-time check result:', result);
                            
                            if (result.success && result.hasNew) {
                                console.log(`🆕 ${result.count} new pending users detected, silently updating list...`);
                                console.log('New users:', result.newUsers);
                                
                                // Update last check time
                                lastCheckTime = result.timestamp || new Date().toISOString();
                                
                                // Silently add new users to the TOP of the list
                                if (result.newUsers && result.newUsers.length > 0) {
                                    addNewUsersToList(result.newUsers);
                                    
                                    // Show subtle notification without disrupting user experience
                                    showNotificationWithSound(`🆕 ${result.count} new user registration(s) received!`, 'success');
                                }
                            } else if (result.success) {
                                console.log(`✅ No new users (${result.totalPending} total pending)`);
                            }
                            
                        } catch (error) {
                            console.error('Error in fresh session real-time sync:', error);
                        } finally {
                            isRealTimeUpdating = false;
                        }
                    }, 2000); // Check every 2 seconds with fresh session
                }

                // Stop real-time sync
                function stopRealTimeSync() {
                    if (realTimeSyncInterval) {
                        clearInterval(realTimeSyncInterval);
                        realTimeSyncInterval = null;
                    }
                }

                // Debug database function
                async function debugDatabase() {
                    console.log('🔍 Manual debug: Checking database...');
                    try {
                        const formData = new FormData();
                        formData.append('api_action', 'debug_pending_users');
                        
                        const response = await fetch(window.location.href, {
                            method: 'POST',
                            body: formData
                        });
                        
                        if (response.ok) {
                            const result = await response.json();
                            console.log('🔍 Manual debug result:', result);
                            
                            if (result.success) {
                                if (result.allPendingUsers && result.allPendingUsers.length > 0) {
                                    console.log(`🔍 Found ${result.allPendingUsers.length} pending users in database:`, result.allPendingUsers);
                                    alert(`Found ${result.allPendingUsers.length} pending users in database. Check console for details.`);
                                } else {
                                    console.log('🔍 No pending users found in database');
                                    alert('No pending users found in database. This explains why real-time is not working!');
                                }
                            } else {
                                console.error('Debug failed:', result.message);
                                alert('Debug failed: ' + result.message);
                            }
                        }
                    } catch (error) {
                        console.error('Debug error:', error);
                        alert('Debug error: ' + error.message);
                    }
                }

                // Add new users to the list silently and smoothly - NO visible refresh
                function addNewUsersToList(newUsers) {
                    if (!newUsers || newUsers.length === 0) return;
                    
                    console.log('Silently adding new users to list:', newUsers);
                    
                    // Hide empty message if it's showing
                    if (vuEmpty) vuEmpty.classList.add('hidden');
                    
                    // Generate HTML for new users
                    const newUsersHtml = newUsers.map((user, index) => {
                        const uid = user.id || '';
                        const fullName = escapeHtml(user.fullName || '—');
                        const email = escapeHtml(user.email || '—');
                        const contact = escapeHtml(user.mobileNumber || user.contact || '—');
                        const currentAddress = escapeHtml(user.currentAddress || '');
                        const permanentAddress = escapeHtml(user.permanentAddress || '');
                        const address = currentAddress || permanentAddress || escapeHtml(user.address || '—');
                        const birthdate = escapeHtml(user.birthdate || '—');
                        
                        // Handle multiple ID images
                        const frontIdUrl = user.frontIdImageUrl || '';
                        const backIdUrl = user.backIdImageUrl || '';
                        const selfieUrl = user.selfieImageUrl || '';
                        
                        // Fallback to old proof path for backward compatibility
                        const proofPath = user.proofOfResidencyPath || '';
                        const proofUrl = proofPath ? `proof_proxy.php?path=${encodeURIComponent(proofPath)}&user=${encodeURIComponent(uid)}` : '';

                        return `
                            <div class="user-card bg-gradient-to-r from-green-50 to-emerald-50 border-2 border-green-200 rounded-xl p-6 animate-fade-in-up shadow-lg" data-uid="${uid}" style="--anim-delay: ${index * 50}ms">
                                <div class="flex flex-col gap-4">
                                    <!-- NEW USER Badge -->
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <span class="px-3 py-1 text-xs font-bold rounded-full bg-green-500 text-white animate-pulse">NEW USER</span>
                                            <span class="text-xs text-green-600 font-medium">Just registered!</span>
                                        </div>
                                    </div>
                                    
                                    <!-- User Info Header -->
                                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                                        <div class="flex-1">
                                            <div class="flex items-center gap-3 mb-2">
                                                <h4 class="font-semibold text-slate-800 text-lg">${fullName}</h4>
                                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-amber-100 text-amber-800">PENDING</span>
                                            </div>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
                                                <div>
                                                    <span class="text-slate-500">Email:</span> <span class="text-slate-800">${email}</span>
                                                </div>
                                                <div>
                                                    <span class="text-slate-500">Mobile:</span> <span class="text-slate-800">${contact}</span>
                                                </div>
                                                <div>
                                                    <span class="text-slate-500">Birthdate:</span> <span class="text-slate-800">${birthdate}</span>
                                                </div>
                                            </div>
                                            <div class="mt-2">
                                                <span class="text-slate-500 text-sm">Address:</span> 
                                                <span class="text-slate-800 text-sm" title="${address}">${address.length > 80 ? address.substring(0, 80) + '...' : address}</span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- ID Documents Section -->
                                    ${(frontIdUrl || backIdUrl || selfieUrl || proofUrl) ? `
                                        <div class="border-t border-green-200 pt-4">
                                            <h5 class="text-sm font-medium text-slate-700 mb-3">Verification Documents</h5>
                                            <div class="flex flex-wrap gap-2">
                                                ${frontIdUrl ? `
                                                    <button type="button" class="btn btn-view text-xs" title="View Front ID"
                                                            onclick="showIdModal(this, 'Front ID')"
                                                            data-fullname="${escapeHtml(user.fullName || '')}"
                                                            data-imageurl="${escapeHtml(frontIdUrl)}"
                                                            data-imagetype="Front ID">
                                                        <?php echo svg_icon('identification', 'w-3 h-3'); ?><span>Front ID</span>
                                                    </button>
                                                ` : ''}
                                                ${backIdUrl ? `
                                                    <button type="button" class="btn btn-view text-xs" title="View Back ID"
                                                            onclick="showIdModal(this, 'Back ID')"
                                                            data-fullname="${escapeHtml(user.fullName || '')}"
                                                            data-imageurl="${escapeHtml(backIdUrl)}"
                                                            data-imagetype="Back ID">
                                                        <?php echo svg_icon('identification', 'w-3 h-3'); ?><span>Back ID</span>
                                                    </button>
                                                ` : ''}
                                                ${selfieUrl ? `
                                                    <button type="button" class="btn btn-view text-xs" title="View Selfie"
                                                            onclick="showIdModal(this, 'Selfie')"
                                                            data-fullname="${escapeHtml(user.fullName || '')}"
                                                            data-imageurl="${escapeHtml(selfieUrl)}"
                                                            data-imagetype="Selfie">
                                                        <?php echo svg_icon('user-circle', 'w-3 h-3'); ?><span>Selfie</span>
                                                    </button>
                                                ` : ''}
                                                ${proofUrl ? `
                                                    <button type="button" class="btn btn-view text-xs" title="View Proof of Residency"
                                                            onclick="showProofModal(this)"
                                                            data-fullname="${escapeHtml(user.fullName || '')}"
                                                            data-proofurl="${escapeHtml(proofUrl)}">
                                                        <?php echo svg_icon('home', 'w-3 h-3'); ?><span>Proof</span>
                                                    </button>
                                                ` : ''}
                                            </div>
                                        </div>
                                    ` : ''}
                                    
                                    <!-- Action Buttons -->
                                    <div class="border-t border-green-200 pt-4">
                                        <div class="flex justify-end gap-2">
                                            <form class="inline-flex" onsubmit="handleUserVerification(event)">
                                                <input type="hidden" name="uid" value="${uid}">
                                                <input type="hidden" name="newStatus" value="approved">
                                                <button type="submit" class="btn btn-approve" title="Approve Registration">
                                                    <?php echo svg_icon('check-circle', 'w-4 h-4'); ?><span>Approve</span>
                                                </button>
                                            </form>
                                            <form class="inline-flex" onsubmit="handleUserVerification(event)">
                                                <input type="hidden" name="uid" value="${uid}">
                                                <input type="hidden" name="newStatus" value="rejected">
                                                <button type="submit" class="btn btn-decline" title="Reject Registration">
                                                    <?php echo svg_icon('x-circle', 'w-4 h-4'); ?><span>Reject</span>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    }).join('');
                    
                    // Silently add new users to the TOP of the list - NO visible refresh
                    if (vuList && newUsersHtml) {
                        // Create temporary container for new users
                        const tempDiv = document.createElement('div');
                        tempDiv.innerHTML = newUsersHtml;
                        
                        // Insert each new user at the top with smooth animation
                        const newUserElements = Array.from(tempDiv.children);
                        newUserElements.reverse().forEach((userElement, index) => {
                            vuList.insertBefore(userElement, vuList.firstChild);
                            
                            // Silent glow effect that fades after 5 seconds
                            setTimeout(() => {
                                userElement.classList.remove('from-green-50', 'to-emerald-50', 'border-green-200');
                                userElement.classList.add('bg-white', 'border-slate-200');
                                userElement.querySelector('.animate-pulse')?.classList.remove('animate-pulse');
                            }, 5000); // Remove highlight after 5 seconds
                        });
                        
                        // Update total users count silently
                        totalUsers += newUsers.length;
                        updatePagination();
                    }
                }

                async function loadPendingUsers(page = 1, retryCount = 0) {
                    if (!vuList) return;
                    
                    const maxRetries = 2; // Reduced retries for faster response
                    const retryDelay = 500 * Math.pow(2, retryCount); // Faster retry delays

                    // Show loading state only on first load or manual refresh
                    if (retryCount === 0) {
                        // Only show loading spinner if this is a manual refresh or first load
                        if (vuList.children.length === 0 || vuList.querySelector('.user-card') === null) {
                            vuLoading.style.display = 'block';
                            vuEmpty.classList.add('hidden');
                            vuList.innerHTML = '<div class="text-center py-10 text-slate-500 text-sm"><div class="inline-flex items-center gap-2"><?php echo svg_icon('spinner', 'w-5 h-5 animate-spin'); ?> Loading users...</div></div>';
                        }
                    }

                    try {
                        const formData = new FormData();
                        formData.append('api_action', 'list_pending_users');
                        formData.append('page', String(page));
                        formData.append('pageSize', String(pageSize));
                        formData.append('search', searchTerm);

                        console.log('DEBUG: Sending request to list_pending_users with:', {
                            page: page,
                            pageSize: pageSize,
                            search: searchTerm
                        });

                        const controller = new AbortController();
                        const timeoutId = setTimeout(() => controller.abort(), 10000); // 10 second timeout for faster response

                        const response = await fetch(window.location.href, {
                            method: 'POST',
                            body: formData,
                            signal: controller.signal
                        });

                        clearTimeout(timeoutId);

                        if (!response.ok) {
                            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                        }

                        const result = await response.json();
                        console.log('DEBUG: Received response:', result);

                        if (result.success) {
                            currentPage = result.page;
                            totalUsers = result.total;
                            const users = result.data || [];

                            console.log('DEBUG: Processing users:', users.length, 'users found');
                            renderUserList(users);
                            updatePagination();
                            
                            // Update last check time for real-time sync
                            lastCheckTime = new Date().toISOString();
                            
                            // Show execution time if available
                            if (result.executionTime) {
                                console.log(`User verification loaded in ${result.executionTime}`);
                            }
                        } else {
                            console.error('DEBUG: API returned error:', result.message);
                            throw new Error(result.message || 'Failed to load users');
                        }
                    } catch (error) {
                        console.error('Error loading pending users:', error);
                        
                        const isTimeout = error.name === 'AbortError' || error.message.includes('timeout');
                        const isNetworkError = error.message.includes('fetch') || error.message.includes('Network');
                        
                        if (retryCount < maxRetries && (isTimeout || isNetworkError)) {
                            // Retry with exponential backoff
                            setTimeout(() => {
                                loadPendingUsers(page, retryCount + 1);
                            }, retryDelay);
                            
                            vuList.innerHTML = `<div class="text-center py-10 text-slate-500 text-sm">
                                <div class="inline-flex items-center gap-2 mb-2">
                                    <?php echo svg_icon('spinner', 'w-5 h-5 animate-spin'); ?>
                                    Retrying... (${retryCount + 1}/${maxRetries})
                                </div>
                                <div class="text-xs text-slate-400">
                                    ${isTimeout ? 'Request timed out' : 'Network error occurred'}
                                </div>
                            </div>`;
                        } else {
                            // Show error with retry button and more helpful message
                            let errorMsg = 'Failed to load users. ';
                            if (isTimeout) {
                                errorMsg += 'Request timed out. Please try again.';
                            } else if (isNetworkError) {
                                errorMsg += 'Network connection issue. Please check your internet connection and try again.';
                            } else {
                                errorMsg += error.message || 'Unknown error occurred.';
                            }
                            
                            vuList.innerHTML = `<div class="text-center py-10">
                                <div class="text-red-500 mb-3 text-sm">
                                    <?php echo svg_icon('x-mark', 'w-6 h-6 mx-auto mb-2'); ?>
                                    ${errorMsg}
                                </div>
                                <div class="space-y-2">
                                <button onclick="loadPendingUsers(${page})" class="btn btn-primary text-sm">
                                    Try Again
                                </button>
                                    <button onclick="loadPendingUsers(1)" class="btn btn-view text-sm">
                                        Reset to First Page
                                    </button>
                                </div>
                            </div>`;
                        }
                    }
                }

                function renderUserList(users) {
                    if (users.length === 0) {
                        vuList.innerHTML = '';
                        vuEmpty.classList.remove('hidden');
                                vuEmpty.innerHTML = searchTerm ? 'No users found for your search.' : `
                            <div class="text-center">
                                <div class="text-slate-500 mb-3">No pending user registrations. ✨</div>
                                <button type="button" onclick="debugDatabase()" class="px-3 py-1 text-xs bg-blue-100 text-blue-700 rounded hover:bg-blue-200">
                                    🔍 Debug Database
                                </button>
                            </div>
                        `;
                        return;
                    }

                    vuEmpty.classList.add('hidden');

                    const html = users.map((user, index) => {
                        const uid = user.id || '';
                        const fullName = escapeHtml(user.fullName || '—');
                        const firstName = escapeHtml(user.firstName || '');
                        const lastName = escapeHtml(user.lastName || '');
                        const middleName = escapeHtml(user.middleName || '');
                        const email = escapeHtml(user.email || '—');
                        const contact = escapeHtml(user.mobileNumber || user.contact || '—');
                        const currentAddress = escapeHtml(user.currentAddress || '');
                        const permanentAddress = escapeHtml(user.permanentAddress || '');
                        const address = currentAddress || permanentAddress || escapeHtml(user.address || '—');
                        const birthdate = escapeHtml(user.birthdate || '—');
                        const gender = escapeHtml(user.gender || '—');
                        const accountStatus = escapeHtml(user.accountStatus || 'pending');
                        
                        // Handle multiple ID images
                        const frontIdUrl = user.frontIdImageUrl || '';
                        const backIdUrl = user.backIdImageUrl || '';
                        const selfieUrl = user.selfieImageUrl || '';
                        
                        // Fallback to old proof path for backward compatibility
                        const proofPath = user.proofOfResidencyPath || '';
                        const proofUrl = proofPath ? `proof_proxy.php?path=${encodeURIComponent(proofPath)}&user=${encodeURIComponent(uid)}` : '';

                        const animDelay = `style="--anim-delay: ${index * 50}ms"`;

                        return `
                            <div class="user-card bg-white rounded-xl border border-slate-200 p-6 animate-fade-in-up" ${animDelay} data-uid="${uid}">
                                <div class="flex flex-col gap-4">
                                    <!-- User Info Header -->
                                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                                        <div class="flex-1">
                                            <div class="flex items-center gap-3 mb-2">
                                                <h4 class="font-semibold text-slate-800 text-lg">${fullName}</h4>
                                                <span class="px-2 py-1 text-xs font-medium rounded-full ${accountStatus === 'pending' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600'}">${accountStatus.toUpperCase()}</span>
                                            </div>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
                                                <div>
                                                    <span class="text-slate-500">Email:</span> <span class="text-slate-800">${email}</span>
                                                </div>
                                                <div>
                                                    <span class="text-slate-500">Mobile:</span> <span class="text-slate-800">${contact}</span>
                                                </div>
                                                <div>
                                                    <span class="text-slate-500">Birthdate:</span> <span class="text-slate-800">${birthdate}</span>
                                                </div>
                                            </div>
                                            <div class="mt-2">
                                                <span class="text-slate-500 text-sm">Address:</span> 
                                                <span class="text-slate-800 text-sm" title="${address}">${address.length > 80 ? address.substring(0, 80) + '...' : address}</span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- ID Documents Section -->
                                    ${(frontIdUrl || backIdUrl || selfieUrl || proofUrl) ? `
                                        <div class="border-t border-slate-200 pt-4">
                                            <h5 class="text-sm font-medium text-slate-700 mb-3">Verification Documents</h5>
                                            <div class="flex flex-wrap gap-2">
                                                ${frontIdUrl ? `
                                                    <button type="button" class="btn btn-view text-xs" title="View Front ID"
                                                            onclick="showIdModal(this, 'Front ID')"
                                                            data-fullname="${escapeHtml(user.fullName || '')}"
                                                            data-imageurl="${escapeHtml(frontIdUrl)}"
                                                            data-imagetype="Front ID">
                                                        <?php echo svg_icon('identification', 'w-3 h-3'); ?><span>Front ID</span>
                                                    </button>
                                                ` : ''}
                                                ${backIdUrl ? `
                                                    <button type="button" class="btn btn-view text-xs" title="View Back ID"
                                                            onclick="showIdModal(this, 'Back ID')"
                                                            data-fullname="${escapeHtml(user.fullName || '')}"
                                                            data-imageurl="${escapeHtml(backIdUrl)}"
                                                            data-imagetype="Back ID">
                                                        <?php echo svg_icon('identification', 'w-3 h-3'); ?><span>Back ID</span>
                                                    </button>
                                                ` : ''}
                                                ${selfieUrl ? `
                                                    <button type="button" class="btn btn-view text-xs" title="View Selfie"
                                                            onclick="showIdModal(this, 'Selfie')"
                                                            data-fullname="${escapeHtml(user.fullName || '')}"
                                                            data-imageurl="${escapeHtml(selfieUrl)}"
                                                            data-imagetype="Selfie">
                                                        <?php echo svg_icon('user-circle', 'w-3 h-3'); ?><span>Selfie</span>
                                                    </button>
                                                ` : ''}
                                                ${proofUrl ? `
                                                    <button type="button" class="btn btn-view text-xs" title="View Proof of Residency"
                                                            onclick="showProofModal(this)"
                                                            data-fullname="${escapeHtml(user.fullName || '')}"
                                                            data-proofurl="${escapeHtml(proofUrl)}">
                                                        <?php echo svg_icon('home', 'w-3 h-3'); ?><span>Proof</span>
                                                    </button>
                                                ` : ''}
                                            </div>
                                        </div>
                                    ` : ''}
                                    
                                    <!-- Action Buttons -->
                                    <div class="border-t border-slate-200 pt-4">
                                        <div class="flex justify-end gap-2">
                                            <form class="inline-flex" onsubmit="handleUserVerification(event)">
                                                <input type="hidden" name="uid" value="${uid}">
                                                <input type="hidden" name="newStatus" value="approved">
                                                <button type="submit" class="btn btn-approve" title="Approve Registration">
                                                    <?php echo svg_icon('check-circle', 'w-4 h-4'); ?><span>Approve</span>
                                                </button>
                                            </form>
                                            <form class="inline-flex" onsubmit="handleUserVerification(event)">
                                                <input type="hidden" name="uid" value="${uid}">
                                                <input type="hidden" name="newStatus" value="rejected">
                                                <button type="submit" class="btn btn-decline" title="Reject Registration">
                                                    <?php echo svg_icon('x-circle', 'w-4 h-4'); ?><span>Reject</span>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    }).join('');

                    vuList.innerHTML = html;
                }

                function updatePagination() {
                    const totalPages = Math.max(1, Math.ceil(totalUsers / pageSize));
                    const start = totalUsers ? ((currentPage - 1) * pageSize + 1) : 0;
                    const end = totalUsers ? Math.min(currentPage * pageSize, totalUsers) : 0;

                    if (vuRange) vuRange.textContent = `Showing ${start}-${end} of ${totalUsers}`;
                    if (vuPrev) vuPrev.disabled = currentPage <= 1;
                    if (vuNext) vuNext.disabled = currentPage >= totalPages;
                }

                function escapeHtml(text) {
                    const div = document.createElement('div');
                    div.textContent = text;
                    return div.innerHTML;
                }

                function getStorageUrl(path) {
                    if (!path) return '';
                    const projectId = 'ibantayv2';
                    const encodedPath = encodeURIComponent(path);
                    // Use the correct Firebase Storage bucket format (.firebasestorage.app instead of .appspot.com)
                    return `https://firebasestorage.googleapis.com/v0/b/${projectId}.firebasestorage.app/o/${encodedPath}?alt=media`;
                }

                // Event listeners
                if (vuPageSize) {
                    vuPageSize.addEventListener('change', () => {
                        pageSize = parseInt(vuPageSize.value || '20', 10);
                        loadPendingUsers(1);
                    });
                }

                if (vuSearch) {
                    vuSearch.addEventListener('input', debounce(() => {
                        const newSearchTerm = vuSearch.value.trim();
                        const wasSearching = searchTerm.trim() !== '';
                        searchTerm = newSearchTerm;
                        
                        // If we were searching and now we're not, restart real-time updates
                        if (wasSearching && searchTerm.trim() === '') {
                            startRealTimeSync();
                        }
                        // If we started searching, stop real-time updates
                        else if (!wasSearching && searchTerm.trim() !== '') {
                            stopRealTimeSync();
                        }
                        
                        loadPendingUsers(1);
                    }, 300));
                }









                if (vuPrev) {
                    vuPrev.addEventListener('click', () => {
                        if (currentPage > 1) loadPendingUsers(currentPage - 1);
                    });
                }

                if (vuNext) {
                    vuNext.addEventListener('click', () => {
                        const totalPages = Math.max(1, Math.ceil(totalUsers / pageSize));
                        if (currentPage < totalPages) loadPendingUsers(currentPage + 1);
                    });
                }

                // Debounce function
                function debounce(func, wait) {
                    let timeout;
                    return function executedFunction(...args) {
                        const later = () => {
                            clearTimeout(timeout);
                            func(...args);
                        };
                        clearTimeout(timeout);
                        timeout = setTimeout(later, wait);
                    };
                }

                // Initial load
                loadPendingUsers(1);
                
                // Reset session and force initial check
                setTimeout(async () => {
                    console.log('🔄 Resetting session and checking for users...');
                    try {
                        // First reset the session to get a fresh start
                        const resetFormData = new FormData();
                        resetFormData.append('api_action', 'reset_user_session');
                        
                        await fetch(window.location.href, {
                            method: 'POST',
                            body: resetFormData
                        });
                        
                        // Debug: Check what users exist in database
                        console.log('🔍 Debug: Checking database for users...');
                        const debugFormData = new FormData();
                        debugFormData.append('api_action', 'debug_pending_users');
                        
                        const debugResponse = await fetch(window.location.href, {
                            method: 'POST',
                            body: debugFormData
                        });
                        
                        if (debugResponse.ok) {
                            const debugResult = await debugResponse.json();
                            console.log('🔍 Database debug result:', debugResult);
                            
                            if (debugResult.success && debugResult.allPendingUsers && debugResult.allPendingUsers.length > 0) {
                                console.log(`🔍 Found ${debugResult.allPendingUsers.length} pending users in database:`, debugResult.allPendingUsers);
                            } else {
                                console.log('🔍 No pending users found in database. This might be the issue!');
                            }
                        }
                        
                        // Then check for any users
                        const formData = new FormData();
                        formData.append('api_action', 'get_new_pending_users');
                        
                        const response = await fetch(window.location.href, {
                            method: 'POST',
                            body: formData
                        });
                        
                        if (response.ok) {
                            const result = await response.json();
                            console.log('Initial check result:', result);
                            
                            if (result.success && result.hasNew && result.newUsers && result.newUsers.length > 0) {
                                console.log(`🆕 ${result.newUsers.length} users found on initial check!`);
                                addNewUsersToList(result.newUsers);
                                lastCheckTime = result.timestamp || new Date().toISOString();
                                showNotificationWithSound(`🆕 ${result.newUsers.length} pending user(s) found!`, 'info');
                            }
                        }
                    } catch (error) {
                        console.error('Initial check error:', error);
                    }
                }, 500); // Check after 500ms
                
                // Start real-time sync for pending users
                startRealTimeSync();
                
                // Add real-time indicator - Fresh session updates every 2 seconds
                const realTimeIndicator = document.createElement('div');
                realTimeIndicator.id = 'realTimeIndicator';
                realTimeIndicator.className = 'fixed bottom-4 right-4 bg-green-500 text-white px-3 py-1 rounded-full text-xs font-medium shadow-lg z-50 flex items-center gap-2 opacity-80';
                realTimeIndicator.innerHTML = `
                    <div class="w-2 h-2 bg-white rounded-full animate-pulse"></div>
                    <span>Fresh Session Updates (2s)</span>
                `;
                document.body.appendChild(realTimeIndicator);
                
                // Clean up on page unload
                window.addEventListener('beforeunload', () => {
                    stopRealTimeSync();
                });
            })();
        }
        
        // Staff: Load assigned reports data via AJAX for better performance
        <?php if (!$isAdmin): ?>
        (async () => {
            const cardsContainer = document.getElementById('staffReportCards');
            if (!cardsContainer) return;
        
            try {
                // Load staff data
                const formData = new FormData();
                formData.append('api_action', 'load_staff_data');
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    window.staffData = result.data;
                    renderStaffReports(result.data.cards, categories);
                } else {
                    console.error('Failed to load staff data:', result.message);
                    showToast('Failed to load reports data: ' + result.message, 'error');
                    cardsContainer.innerHTML = `<div class="text-center py-12 text-red-500">
                        <div class="inline-flex items-center gap-3">
                            ${svg_icon('x-mark', 'w-5 h-5')}
                            <div>
                                <div class="text-lg font-medium">Failed to load reports</div>
                                <div class="text-sm text-red-400">${result.message}</div>
                            </div>
                        </div>
                    </div>`;
                }
            } catch (error) {
                console.error('Error loading staff data:', error);
                showToast('Error loading reports data: ' + error.message, 'error');
                cardsContainer.innerHTML = `<div class="text-center py-12 text-red-500">
                    <div class="inline-flex items-center gap-3">
                        ${svg_icon('x-mark', 'w-5 h-5')}
                        <div>
                            <div class="text-lg font-medium">Error loading reports</div>
                            <div class="text-sm text-red-400">${error.message}</div>
                        </div>
                    </div>
                </div>`;
            }
        })();
        
        // Smart real-time sync function for staff reports
        function startRealTimeSync() {
            let lastCheckTime = new Date().toISOString();
            let isUpdating = false; // Prevent multiple simultaneous updates
            let isStatusUpdateInProgress = false; // Track if status update is happening
            
            // Check for new reports every 2 seconds (more reasonable)
            setInterval(async () => {
                // Skip if already updating or if status update is in progress
                if (isUpdating || isStatusUpdateInProgress) return;
                
                try {
                    isUpdating = true;
                    
                    // Quick check for new reports
                    const formData = new FormData();
                    formData.append('api_action', 'check_new_reports');
                    formData.append('last_check', lastCheckTime);
                    
                    const response = await fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    });
                    
                    // Check if response is valid JSON
                    const responseText = await response.text();
                    let result;
                    try {
                        result = JSON.parse(responseText);
                    } catch (parseError) {
                        console.error('Invalid JSON response:', responseText);
                        return;
                    }
                    
                    if (result.success && result.hasNew) {
                        console.log('🆕 New reports detected, updating dashboard...');
                        
                        // Update last check time
                        lastCheckTime = result.timestamp || new Date().toISOString();
                        
                        // Reload full staff data
                        const fullDataForm = new FormData();
                        fullDataForm.append('api_action', 'load_staff_data');
                        
                        const fullResponse = await fetch(window.location.href, {
                            method: 'POST',
                            body: fullDataForm
                        });
                        
                        // Check if full response is valid JSON
                        const fullResponseText = await fullResponse.text();
                        let fullResult;
                        try {
                            fullResult = JSON.parse(fullResponseText);
                        } catch (parseError) {
                            console.error('Invalid JSON in full response:', fullResponseText);
                            return;
                        }
                        
                        if (fullResult.success) {
                            window.staffData = fullResult.data;
                            renderStaffReports(fullResult.data.cards, categories);
                            
                            // Also refresh emergency alerts
                            if (typeof loadEmergencyAlerts === 'function') {
                                loadEmergencyAlerts();
                            }
                            
                            // Update all tab counts after refresh to ensure accuracy
                            if (fullResult.data.cards) {
                                Object.keys(fullResult.data.cards).forEach(slug => {
                                    if (typeof window.manualUpdateTabCounts === 'function') {
                                        window.manualUpdateTabCounts(slug);
                                    }
                                });
                            }
                            
                            // Determine sound type based on new reports
                            let soundType = 'default';
                            if (result.data && Array.isArray(result.data)) {
                                const hasEmergency = result.data.some(item => {
                                    const cat = (item.category || '').toLowerCase();
                                    return cat === 'ambulance' || cat === 'fire';
                                });
                                if (hasEmergency) {
                                    soundType = 'siren';
                                }
                            }

                            // Show notification with sound and visual effects
                            showNotificationWithSound('🆕 New reports received!', 'success', soundType);
                        }
                    }
                } catch (error) {
                    console.error('Error in real-time sync:', error);
                } finally {
                    isUpdating = false;
                }
            }, 2000); // Check every 2 seconds (more reasonable)
            
            // Expose the status update flag for handleStatusUpdate to use
            window.setStatusUpdateInProgress = function(inProgress) {
                isStatusUpdateInProgress = inProgress;
            };
        }
        
        // Start real-time sync
        startRealTimeSync();
        
        // Also start emergency alerts specific sync (less frequent for stability)
        setInterval(async () => {
            try {
                if (typeof loadEmergencyAlerts === 'function') {
                    loadEmergencyAlerts();
                }
            } catch (error) {
                console.error('Error in emergency alerts sync:', error);
            }
        }, 5000); // Check emergency alerts every 5 seconds for stability
        
        // Debug function to test tab count updates
        window.testTabCountUpdate = function() {
            console.log('🧪 Testing tab count update...');
            if (window.staffData && window.staffData.cards && window.staffData.cards.ambulance) {
                const reports = window.staffData.cards.ambulance;
                if (reports.length > 0) {
                    const firstReport = reports[0];
                    console.log('📋 Testing with report:', firstReport);
                    window.forceUpdateTabCounts('ambulance', firstReport.id, 'Approved');
                } else {
                    console.log('❌ No reports available for testing');
                }
            } else {
                console.log('❌ No staff data available for testing');
            }
        };

        // Manual function to update tab counts for any slug
        window.manualUpdateTabCounts = function(slug) {
            console.log('🔧 Manual tab count update for slug:', slug);
            if (window.staffData && window.staffData.cards && window.staffData.cards[slug]) {
                const reports = window.staffData.cards[slug];
                console.log('📊 Current reports:', reports);
                
                // Recalculate counts
                const urgentReports = reports.filter(r => (r.priority || '').toUpperCase() === 'HIGH' && (r.status || 'pending').toLowerCase() === 'pending');
                const pendingItems = reports.filter(r => (r.status || 'pending').toLowerCase() === 'pending' && (r.priority || '').toUpperCase() !== 'HIGH');
                const approvedItems = reports.filter(r => (r.status || 'pending').toLowerCase() === 'approved');
                const declinedItems = reports.filter(r => (r.status || 'pending').toLowerCase() === 'declined');
                
                console.log('📊 Calculated counts:', {
                    pending: pendingItems.length + urgentReports.length,
                    approved: approvedItems.length,
                    declined: declinedItems.length
                });
                
                // Find and update the tab counts
                const segmentedControl = document.querySelector(`.segmented[data-slug="${slug}"]`);
                if (segmentedControl) {
                    const pendingTab = segmentedControl.querySelector('.seg-btn[data-tab="pending"] .tab-count');
                    const approvedTab = segmentedControl.querySelector('.seg-btn[data-tab="approved"] .tab-count');
                    const declinedTab = segmentedControl.querySelector('.seg-btn[data-tab="declined"] .tab-count');
                    
                    if (pendingTab) {
                        pendingTab.textContent = pendingItems.length + urgentReports.length;
                        console.log('✅ Manual updated pending count:', pendingTab.textContent);
                    }
                    if (approvedTab) {
                        approvedTab.textContent = approvedItems.length;
                        console.log('✅ Manual updated approved count:', approvedTab.textContent);
                    }
                    if (declinedTab) {
                        declinedTab.textContent = declinedItems.length;
                        console.log('✅ Manual updated declined count:', declinedTab.textContent);
                    }
                } else {
                    console.log('❌ Could not find segmented control for manual update');
                }
            } else {
                console.log('❌ No staff data available for manual update');
            }
        };

        // Function to refresh all tab counts
        window.refreshAllTabCounts = function() {
            console.log('🔄 Refreshing all tab counts...');
            if (window.staffData && window.staffData.cards) {
                Object.keys(window.staffData.cards).forEach(slug => {
                    if (typeof window.manualUpdateTabCounts === 'function') {
                        window.manualUpdateTabCounts(slug);
                    }
                });
            }
        };

        // Add manual refresh function
        window.refreshStaffReports = async function() {
                try {
                    const formData = createFormDataWithCsrf();
                    formData.append('api_action', 'load_staff_data');                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    window.staffData = result.data;
                    renderStaffReports(result.data.cards, categories);
                    
                    // Also refresh emergency alerts
                    if (typeof loadEmergencyAlerts === 'function') {
                        loadEmergencyAlerts();
                    }
                    
                    showToast('Reports refreshed successfully', 'success');
                } else {
                    showToast('Failed to refresh reports: ' + result.message, 'error');
                }
            } catch (error) {
                console.error('Error refreshing reports:', error);
                showToast('Error refreshing reports: ' + error.message, 'error');
            }
        };
        
        // Add immediate refresh function for instant updates
        window.immediateRefresh = async function() {
            try {
                console.log('🔄 Immediate refresh triggered...');
                
                // Force immediate refresh without checking last update time
                const formData = new FormData();
                formData.append('api_action', 'load_staff_data');
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    window.staffData = result.data;
                    renderStaffReports(result.data.cards, categories);
                    
                    // Also refresh emergency alerts immediately
                    if (typeof loadEmergencyAlerts === 'function') {
                        loadEmergencyAlerts();
                    }
                    
                    console.log('✅ Immediate refresh completed');
                } else {
                    console.error('❌ Immediate refresh failed:', result.message);
                }
            } catch (error) {
                console.error('❌ Error in immediate refresh:', error);
            }
        };
        
        // Add ultra-fast refresh function (can be called from report submission)
        window.ultraFastRefresh = async function() {
            try {
                console.log('⚡ Ultra-fast refresh triggered...');
                
                // Immediate refresh with minimal delay
                const formData = new FormData();
                formData.append('api_action', 'load_staff_data');
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    window.staffData = result.data;
                    renderStaffReports(result.data.cards, categories);
                    
                    // Also refresh emergency alerts immediately
                    if (typeof loadEmergencyAlerts === 'function') {
                        loadEmergencyAlerts();
                    }
                    
                    console.log('⚡ Ultra-fast refresh completed');
                } else {
                    console.error('❌ Ultra-fast refresh failed:', result.message);
                }
            } catch (error) {
                console.error('❌ Error in ultra-fast refresh:', error);
            }
        };
        
        // New function to render the staff report cards
        function renderStaffReports(cards, categories) {
            const cardsContainer = document.getElementById('staffReportCards');
            if (!cardsContainer) return;
            
            // --- STAFF PAGINATION STATE ---
            if (!window.staffPagination) {
                window.staffPagination = {
                    page: 1,
                    pageSize: 20,
                    slug: null
                };
            }
            let html = '';
            let animDelayCounter = 200;
            for (const [slug, reports] of Object.entries(cards)) {
                const meta = categories[slug];
                if (!meta) continue;
                window.staffPagination.slug = slug;
                // Filter reports by status
                const pendingItems = reports.filter(r => (r.status || 'pending').toLowerCase() === 'pending');
                const approvedItems = reports.filter(r => (r.status || 'pending').toLowerCase() === 'approved');
                const declinedItems = reports.filter(r => (r.status || 'pending').toLowerCase() === 'declined');
                const respondedItems = reports.filter(r => (r.status || 'pending').toLowerCase() === 'responded');
                // Emergency alerts section removed
                let emergencySection = '';
                // --- PAGINATION LOGIC ---
                const page = window.staffPagination.page;
                const pageSize = window.staffPagination.pageSize;
                const total = pendingItems.length;
                const startIdx = (page - 1) * pageSize;
                const endIdx = Math.min(startIdx + pageSize, total);
                const paginatedPending = pendingItems.slice(startIdx, endIdx);
                html += `
                <div class="report-category-group bg-white rounded-2xl shadow-lg shadow-sky-500/5 border border-slate-200/60 overflow-hidden animate-fade-in-up" style="--anim-delay: ${animDelayCounter}ms;">
                    <div class="p-4 border-b border-slate-200/80 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <div class="w-11 h-11 rounded-lg bg-${meta.color}-100 text-${meta.color}-600 flex items-center justify-center flex-shrink-0">
                                <?php echo svg_icon($meta['icon'], 'w-6 h-6'); ?>
                            </div>
                            <h3 class="text-lg font-bold text-slate-800">${meta.label} Reports</h3>
                        </div>
                        <div class="segmented" data-slug="${slug}">
                            <button type="button" class="seg-btn active" data-tab="pending" onclick="switchTab('${slug}', 'pending')">
                                <span class="seg-label">Pending</span>
                                <span class="tab-count">${pendingItems.length}</span>
                            </button>
                            <button type="button" class="seg-btn" data-tab="approved" onclick="switchTab('${slug}', 'approved')">
                                <span class="seg-label">Approved</span>
                                <span class="tab-count">${approvedItems.length}</span>
                            </button>
                            <button type="button" class="seg-btn" data-tab="declined" onclick="switchTab('${slug}', 'declined')">
                                <span class="seg-label">Declined</span>
                                <span class="tab-count">${declinedItems.length}</span>
                            </button>
                            <button type="button" class="seg-btn" data-tab="responded" onclick="switchTab('${slug}', 'responded')">
                                <span class="seg-label">Responded</span>
                                <span class="tab-count">${respondedItems.length}</span>
                            </button>
                        </div>
                    </div>
                    <div class="panel-content active" data-slug="${slug}" data-tab="pending">
                        ${emergencySection}
                        ${renderReportsTable(paginatedPending, meta.collection, categories)}
                        <div class="flex items-center justify-between mt-4">
                            <div class="text-sm text-slate-600 font-semibold bg-slate-100 px-4 py-2 rounded-lg shadow-sm">
                                <span class="inline-block mr-2 text-blue-600 font-bold">Showing</span>
                                <span class="inline-block">${total === 0 ? 0 : startIdx + 1}-${endIdx}</span>
                                <span class="inline-block mx-2">of</span>
                                <span class="inline-block font-bold">${total}</span>
                            </div>
                            <div class="flex items-center gap-2 bg-slate-50 px-3 py-2 rounded-lg shadow-sm">
                                <label for="staffPageSize" class="mr-2 text-sm font-medium text-slate-700">Rows:</label>
                                <select id="staffPageSize" class="border border-blue-300 rounded-lg px-2 py-1 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all">
                                    <option value="10" ${pageSize === 10 ? 'selected' : ''}>10</option>
                                    <option value="20" ${pageSize === 20 ? 'selected' : ''}>20</option>
                                    <option value="50" ${pageSize === 50 ? 'selected' : ''}>50</option>
                                </select>
                                <button id="staffPrev" class="ml-4 px-3 py-1 border border-blue-300 rounded-lg text-sm font-semibold text-blue-700 bg-white hover:bg-blue-50 transition-all ${page === 1 ? 'opacity-50 cursor-not-allowed' : ''}">Prev</button>
                                <button id="staffNext" class="px-3 py-1 border border-blue-300 rounded-lg text-sm font-semibold text-blue-700 bg-white hover:bg-blue-50 transition-all ${endIdx >= total ? 'opacity-50 cursor-not-allowed' : ''}">Next</button>
                            </div>
                        </div>
                    </div>
                    <div class="panel-content" data-slug="${slug}" data-tab="approved">
                        ${(() => {
                            const page = window.staffPagination.page;
                            const pageSize = window.staffPagination.pageSize;
                            const total = approvedItems.length;
                            const startIdx = (page - 1) * pageSize;
                            const endIdx = Math.min(startIdx + pageSize, total);
                            const paginatedApproved = approvedItems.slice(startIdx, endIdx);
                            return `
                                ${renderReportsTable(paginatedApproved, meta.collection, categories)}
                                <div class='flex items-center justify-between mt-4'>
                                    <div class='text-sm text-slate-600 font-semibold bg-slate-100 px-4 py-2 rounded-lg shadow-sm'>
                                        <span class='inline-block mr-2 text-blue-600 font-bold'>Showing</span>
                                        <span class='inline-block'>${total === 0 ? 0 : startIdx + 1}-${endIdx}</span>
                                        <span class='inline-block mx-2'>of</span>
                                        <span class='inline-block font-bold'>${total}</span>
                                    </div>
                                    <div class='flex items-center gap-2 bg-slate-50 px-3 py-2 rounded-lg shadow-sm'>
                                        <label for='staffPageSize' class='mr-2 text-sm font-medium text-slate-700'>Rows:</label>
                                        <select id='staffPageSize' class='border border-blue-300 rounded-lg px-2 py-1 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all'>
                                            <option value='10' ${pageSize === 10 ? 'selected' : ''}>10</option>
                                            <option value='20' ${pageSize === 20 ? 'selected' : ''}>20</option>
                                            <option value='50' ${pageSize === 50 ? 'selected' : ''}>50</option>
                                        </select>
                                        <button id='staffPrev' class='ml-4 px-3 py-1 border border-blue-300 rounded-lg text-sm font-semibold text-blue-700 bg-white hover:bg-blue-50 transition-all ${page === 1 ? 'opacity-50 cursor-not-allowed' : ''}'>Prev</button>
                                        <button id='staffNext' class='px-3 py-1 border border-blue-300 rounded-lg text-sm font-semibold text-blue-700 bg-white hover:bg-blue-50 transition-all ${endIdx >= total ? 'opacity-50 cursor-not-allowed' : ''}'>Next</button>
                                    </div>
                                </div>
                            `;
                        })()}
                    </div>
                    <div class="panel-content" data-slug="${slug}" data-tab="declined">
                        ${(() => {
                            const page = window.staffPagination.page;
                            const pageSize = window.staffPagination.pageSize;
                            const total = declinedItems.length;
                            const startIdx = (page - 1) * pageSize;
                            const endIdx = Math.min(startIdx + pageSize, total);
                            const paginatedDeclined = declinedItems.slice(startIdx, endIdx);
                            return `
                                ${renderReportsTable(paginatedDeclined, meta.collection, categories)}
                                <div class='flex items-center justify-between mt-4'>
                                    <div class='text-sm text-slate-600 font-semibold bg-slate-100 px-4 py-2 rounded-lg shadow-sm'>
                                        <span class='inline-block mr-2 text-blue-600 font-bold'>Showing</span>
                                        <span class='inline-block'>${total === 0 ? 0 : startIdx + 1}-${endIdx}</span>
                                        <span class='inline-block mx-2'>of</span>
                                        <span class='inline-block font-bold'>${total}</span>
                                    </div>
                                    <div class='flex items-center gap-2 bg-slate-50 px-3 py-2 rounded-lg shadow-sm'>
                                        <label for='staffPageSize' class='mr-2 text-sm font-medium text-slate-700'>Rows:</label>
                                        <select id='staffPageSize' class='border border-blue-300 rounded-lg px-2 py-1 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all'>
                                            <option value='10' ${pageSize === 10 ? 'selected' : ''}>10</option>
                                            <option value='20' ${pageSize === 20 ? 'selected' : ''}>20</option>
                                            <option value='50' ${pageSize === 50 ? 'selected' : ''}>50</option>
                                        </select>
                                        <button id='staffPrev' class='ml-4 px-3 py-1 border border-blue-300 rounded-lg text-sm font-semibold text-blue-700 bg-white hover:bg-blue-50 transition-all ${page === 1 ? 'opacity-50 cursor-not-allowed' : ''}'>Prev</button>
                                        <button id='staffNext' class='px-3 py-1 border border-blue-300 rounded-lg text-sm font-semibold text-blue-700 bg-white hover:bg-blue-50 transition-all ${endIdx >= total ? 'opacity-50 cursor-not-allowed' : ''}'>Next</button>
                                    </div>
                                </div>
                            `;
                        })()}
                    </div>
                    <div class="panel-content" data-slug="${slug}" data-tab="responded">
                        ${(() => {
                            const page = window.staffPagination.page;
                            const pageSize = window.staffPagination.pageSize;
                            const total = respondedItems.length;
                            const startIdx = (page - 1) * pageSize;
                            const endIdx = Math.min(startIdx + pageSize, total);
                            const paginatedResponded = respondedItems.slice(startIdx, endIdx);
                            return `
                                ${renderReportsTable(paginatedResponded, meta.collection, categories)}
                                <div class='flex items-center justify-between mt-4'>
                                    <div class='text-sm text-slate-600 font-semibold bg-slate-100 px-4 py-2 rounded-lg shadow-sm'>
                                        <span class='inline-block mr-2 text-blue-600 font-bold'>Showing</span>
                                        <span class='inline-block'>${total === 0 ? 0 : startIdx + 1}-${endIdx}</span>
                                        <span class='inline-block mx-2'>of</span>
                                        <span class='inline-block font-bold'>${total}</span>
                                    </div>
                                    <div class='flex items-center gap-2 bg-slate-50 px-3 py-2 rounded-lg shadow-sm'>
                                        <label for='staffPageSize' class='mr-2 text-sm font-medium text-slate-700'>Rows:</label>
                                        <select id='staffPageSize' class='border border-blue-300 rounded-lg px-2 py-1 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all'>
                                            <option value='10' ${pageSize === 10 ? 'selected' : ''}>10</option>
                                            <option value='20' ${pageSize === 20 ? 'selected' : ''}>20</option>
                                            <option value='50' ${pageSize === 50 ? 'selected' : ''}>50</option>
                                        </select>
                                        <button id='staffPrev' class='ml-4 px-3 py-1 border border-blue-300 rounded-lg text-sm font-semibold text-blue-700 bg-white hover:bg-blue-50 transition-all ${page === 1 ? 'opacity-50 cursor-not-allowed' : ''}'>Prev</button>
                                        <button id='staffNext' class='px-3 py-1 border border-blue-300 rounded-lg text-sm font-semibold text-blue-700 bg-white hover:bg-blue-50 transition-all ${endIdx >= total ? 'opacity-50 cursor-not-allowed' : ''}'>Next</button>
                                    </div>
                                </div>
                            `;
                        })()}
                    </div>
                </div>`;
                animDelayCounter += 100;
            }
            cardsContainer.innerHTML = html;
            // --- PAGINATION EVENTS ---
            setTimeout(() => {
                const pageSizeEl = document.getElementById('staffPageSize');
                const prevBtn = document.getElementById('staffPrev');
                const nextBtn = document.getElementById('staffNext');
                if (pageSizeEl) {
                    pageSizeEl.addEventListener('change', function() {
                        window.staffPagination.pageSize = parseInt(this.value, 10);
                        window.staffPagination.page = 1;
                        renderStaffReports(cards, categories);
                    });
                }
                if (prevBtn) {
                    prevBtn.addEventListener('click', function() {
                        if (window.staffPagination.page > 1) {
                            window.staffPagination.page--;
                            renderStaffReports(cards, categories);
                        }
                    });
                }
                if (nextBtn) {
                    nextBtn.addEventListener('click', function() {
                        const total = cards[window.staffPagination.slug].filter(r => (r.status || 'pending').toLowerCase() === 'pending').length;
                        const endIdx = window.staffPagination.page * window.staffPagination.pageSize;
                        if (endIdx < total) {
                            window.staffPagination.page++;
                            renderStaffReports(cards, categories);
                        }
                    });
                }
            }, 10);
            // Update all tab counts after rendering to ensure they're accurate
            Object.keys(cards).forEach(slug => {
                if (typeof window.manualUpdateTabCounts === 'function') {
                    setTimeout(() => {
                        window.manualUpdateTabCounts(slug);
                    }, 50);
                }
            });
        }

        // Function to update tab counts in real-time
        window.updateTabCounts = function(slug, docId, newStatus) {
            console.log('🔄 Updating tab counts for:', { slug, docId, newStatus });
            
            if (!window.staffData || !window.staffData.cards || !window.staffData.cards[slug]) {
                console.log('❌ No staff data available');
                return;
            }
            
            const reports = window.staffData.cards[slug];
            
            // Find the report and update its status
            const reportIndex = reports.findIndex(r => r.id === docId);
            if (reportIndex !== -1) {
                reports[reportIndex].status = newStatus;
                console.log('✅ Updated report status:', reports[reportIndex]);
                
                // Recalculate counts - no more urgent separation
                const pendingItems = reports.filter(r => (r.status || 'pending').toLowerCase() === 'pending');
                const approvedItems = reports.filter(r => (r.status || 'pending').toLowerCase() === 'approved');
                const declinedItems = reports.filter(r => (r.status || 'pending').toLowerCase() === 'declined');
                
                console.log('📊 New counts:', {
                    pending: pendingItems.length,
                    approved: approvedItems.length,
                    declined: declinedItems.length
                });
                
                // Update tab counts - try multiple selectors to find the elements
                let segmentedControl = document.querySelector(`[data-slug="${slug}"]`);
                if (!segmentedControl) {
                    // Try alternative selector
                    segmentedControl = document.querySelector(`.segmented[data-slug="${slug}"]`);
                }
                if (!segmentedControl) {
                    // Try finding by class and data attribute
                    segmentedControl = document.querySelector(`.segmented-control[data-slug="${slug}"]`);
                }
                
                if (segmentedControl) {
                    console.log('✅ Found segmented control:', segmentedControl);
                    
                    // Try multiple selectors for each tab
                    let pendingTab = segmentedControl.querySelector('[data-tab="pending"] .tab-count');
                    if (!pendingTab) pendingTab = segmentedControl.querySelector('.seg-btn[data-tab="pending"] .tab-count');
                    
                    let approvedTab = segmentedControl.querySelector('[data-tab="approved"] .tab-count');
                    if (!approvedTab) approvedTab = segmentedControl.querySelector('.seg-btn[data-tab="approved"] .tab-count');
                    
                    let declinedTab = segmentedControl.querySelector('[data-tab="declined"] .tab-count');
                    if (!declinedTab) declinedTab = segmentedControl.querySelector('.seg-btn[data-tab="declined"] .tab-count');
                    
                    console.log('🔍 Found tab elements:', { pendingTab, approvedTab, declinedTab });
                    
                    if (pendingTab) {
                        pendingTab.textContent = pendingItems.length;
                        console.log('✅ Updated pending count:', pendingTab.textContent);
                    } else {
                        console.log('❌ Could not find pending tab');
                    }
                    if (approvedTab) {
                        approvedTab.textContent = approvedItems.length;
                        console.log('✅ Updated approved count:', approvedTab.textContent);
                    } else {
                        console.log('❌ Could not find approved tab');
                    }
                    if (declinedTab) {
                        declinedTab.textContent = declinedItems.length;
                        console.log('✅ Updated declined count:', declinedTab.textContent);
                    } else {
                        console.log('❌ Could not find declined tab');
                    }
                } else {
                    console.log('❌ Could not find segmented control for slug:', slug);
                    console.log('🔍 Available segmented controls:', document.querySelectorAll('[data-slug]'));
                }
            } else {
                console.log('❌ Could not find report with ID:', docId);
            }
        };

        // Function to force update tab counts immediately (for immediate visual feedback)
        window.forceUpdateTabCounts = function(slug, docId, newStatus) {
            console.log('⚡ Force updating tab counts for:', { slug, docId, newStatus });
            
            // Update the report status in memory first
            if (window.staffData && window.staffData.cards && window.staffData.cards[slug]) {
                const reports = window.staffData.cards[slug];
                const reportIndex = reports.findIndex(r => r.id === docId);
                if (reportIndex !== -1) {
                    reports[reportIndex].status = newStatus;
                    console.log('✅ Updated report in memory:', reports[reportIndex]);
                } else {
                    console.log('❌ Report not found in memory:', docId);
                }
            } else {
                console.log('❌ No staff data available for slug:', slug);
            }
            
            // Then update the tab counts
            window.updateTabCounts(slug, docId, newStatus);
        };

        // Function to switch tabs
        window.switchTab = function(slug, tabName) {
            // Remove active class from all buttons in this segmented control
            const segmentedControl = document.querySelector(`[data-slug="${slug}"]`);
            if (segmentedControl) {
                segmentedControl.querySelectorAll('.seg-btn').forEach(btn => {
                    btn.classList.remove('active');
                });
                
                // Add active class to clicked button
                const activeButton = segmentedControl.querySelector(`[data-tab="${tabName}"]`);
                if (activeButton) {
                    activeButton.classList.add('active');
                }
            }
            
            // Hide all panel contents for this slug
            document.querySelectorAll(`[data-slug="${slug}"].panel-content`).forEach(panel => {
                panel.classList.remove('active');
            });
            
            // Show the selected panel
            const selectedPanel = document.querySelector(`[data-slug="${slug}"][data-tab="${tabName}"]`);
            if (selectedPanel) {
                selectedPanel.classList.add('active');
            }
        }

        // Function to load emergency alerts - ULTRA FAST VERSION
        window.loadEmergencyAlerts = async function() {
            const emergencyContainer = document.getElementById('emergencyAlertsContainer');
            if (!emergencyContainer) return;

            try {
                const emergencyFormData = new FormData();
                emergencyFormData.append('api_action', 'check_urgent');
                
                const emergencyResponse = await fetch(window.location.href, {
                    method: 'POST',
                    body: emergencyFormData,
                    signal: AbortSignal.timeout(5000) // Increased to 5-second timeout
                });
                
                const emergencyResult = await emergencyResponse.json();
                
                if (emergencyResult.success) {
                    renderEmergencyAlerts(emergencyResult.data);
                    console.log('⚡ Emergency alerts loaded:', emergencyResult.executionTime);
                } else {
                    console.error('Failed to load emergency alerts:', emergencyResult.message);
                    // Show error state with retry option
                    emergencyContainer.innerHTML = `
                        <div class="text-center py-3 text-red-400">
                            <div class="inline-flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                </svg>
                                <span class="text-xs">Unable to load alerts</span>
                            </div>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Error loading emergency alerts:', error);
                // Show timeout or network error
                emergencyContainer.innerHTML = `
                    <div class="text-center py-3 text-amber-500">
                        <span class="text-xs">Loading alerts...</span>
                    </div>
                `;
            }
        }
        
        // Cache emergency alerts for instant subsequent loads
        let emergencyAlertsCache = null;
        let emergencyAlertsCacheTime = 0;
        
        window.loadEmergencyAlertsCached = async function() {
            const emergencyContainer = document.getElementById('emergencyAlertsContainer');
            if (!emergencyContainer) return;
            
            const now = Date.now();
            const CACHE_DURATION = 30000; // 30 seconds
            
            // Use cache if available and fresh
            if (emergencyAlertsCache && (now - emergencyAlertsCacheTime) < CACHE_DURATION) {
                renderEmergencyAlerts(emergencyAlertsCache);
                console.log('⚡ Emergency alerts loaded from cache');
                return;
            }
            
            // Load fresh data
            try {
                const emergencyFormData = new FormData();
                emergencyFormData.append('api_action', 'check_urgent');
                
                const emergencyResponse = await fetch(window.location.href, {
                    method: 'POST',
                    body: emergencyFormData,
                    signal: AbortSignal.timeout(5000) // Increased to 5-second timeout
                });
                
                const emergencyResult = await emergencyResponse.json();
                
                if (emergencyResult.success) {
                    // Update cache
                    emergencyAlertsCache = emergencyResult.data;
                    emergencyAlertsCacheTime = now;
                    
                    // Debug logging to verify filtering
                    if (emergencyResult.filteredFor) {
                        console.log('🔍 Emergency alerts filtered for categories:', emergencyResult.filteredFor);
                    }
                    if (emergencyResult.data && emergencyResult.data.length > 0) {
                        const collections = [...new Set(emergencyResult.data.map(r => r.collection))];
                        console.log('📋 Emergency alerts from collections:', collections);
                    }
                    
                    renderEmergencyAlerts(emergencyResult.data);
                    console.log('⚡ Emergency alerts loaded and cached:', emergencyResult.executionTime);
                } else {
                    console.error('Failed to load emergency alerts:', emergencyResult.message);
                    // Show error state
                    emergencyContainer.innerHTML = `
                        <div class="text-center py-3 text-red-400">
                            <div class="inline-flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                </svg>
                                <span class="text-xs">Unable to load alerts</span>
                            </div>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Error loading emergency alerts:', error);
                // Use stale cache if available
                if (emergencyAlertsCache) {
                    renderEmergencyAlerts(emergencyAlertsCache);
                    console.log('⚡ Using stale emergency alerts cache');
                } else {
                    // Show error state if no cache available
                    emergencyContainer.innerHTML = `
                        <div class="text-center py-3 text-red-400">
                            <div class="inline-flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                </svg>
                                <span class="text-xs">Unable to load alerts</span>
                            </div>
                        </div>
                    `;
                }
            }
        }
        
        // Replace the original function with the cached version
        window.loadEmergencyAlerts = window.loadEmergencyAlertsCached;

        // Function to render emergency alerts
        function renderEmergencyAlerts(urgentReports) {
            const emergencyContainer = document.getElementById('emergencyAlertsContainer');
            if (!emergencyContainer) return;

            if (!urgentReports || urgentReports.length === 0) {
                emergencyContainer.innerHTML = `
                    <div class="text-center py-4 text-green-600">
                        <div class="inline-flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <div>
                                <div class="text-sm font-medium">All Clear!</div>
                                <div class="text-xs text-green-500">No high priority emergencies.</div>
                            </div>
                        </div>
                    </div>
                `;
                return;
            }

            // Deduplicate reports by ID to prevent duplicates
            const uniqueReports = [];
            const seenIds = new Set();
            
            urgentReports.forEach(report => {
                const reportId = report.id || report._id;
                if (reportId && !seenIds.has(reportId)) {
                    seenIds.add(reportId);
                    uniqueReports.push(report);
                }
            });

            let alertsHtml = '';
            uniqueReports.forEach((report, index) => {
                const animDelay = `style="--anim-delay: ${index * 100}ms"`;
                
                // Debug: Log report data to see what we're working with
                console.log('🚨 Emergency Alert Report:', {
                    id: report.id,
                    _id: report._id,
                    collection: report.collection,
                    fullName: report.fullName,
                    reporterName: report.reporterName,
                    status: report.status,
                    priority: report.priority,
                    timestamp: report.timestamp
                });
                
                // Fix timestamp parsing using Philippines timezone
                let timestamp = 'Unknown time';
                if (report.timestamp) {
                    timestamp = formatFirebaseTimestamp(report.timestamp);
                }
                
                alertsHtml += `
                    <div class="bg-white/80 backdrop-blur-sm rounded-lg border-2 border-red-200 p-3 mb-3 animate-fade-in-up" ${animDelay}>
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-lg">🚨</span>
                                    <h5 class="text-sm font-bold text-red-800">${report.fullName || report.reporterName || 'Unknown Reporter'}</h5>
                                    <span class="px-2 py-0.5 bg-red-100 text-red-800 text-xs font-bold rounded-full">HIGH PRIORITY</span>
                                </div>
                                <p class="text-xs text-red-700 mb-1"><strong>Location:</strong> ${report.location || 'No location specified'}</p>
                                <p class="text-xs text-red-700 mb-1"><strong>Contact:</strong> ${report.contact || report.reporterContact || 'No contact'}</p>
                                <p class="text-xs text-red-600"><strong>Reported:</strong> ${timestamp}</p>
                            </div>
                            <div class="flex flex-col gap-1">
                                <button type="button" class="btn btn-view text-xs px-2 py-1" title="View Details"
                                    onclick="showReportModal(this)"
                                    data-slug="ambulance" data-id="${report.id || report._id || ''}" data-collection="ambulance_reports"
                                    data-fullname="${report.fullName || report.reporterName || ''}" 
                                    data-contact="${report.mobileNumber || report.contact || report.reporterContact || ''}"
                                    data-location="${report.location || ''}" 
                                    data-purpose=""
                                    data-status="${report.status || 'Pending'}" 
                                    data-timestamp="${formatFirebaseTimestamp(report.timestamp)}"
                                    data-rawtimestamp="${report.timestamp}"
                                    data-reporterid="${report.reporterId || ''}" 
                                    data-imageurl="${report.imageUrl || ''}">
                                    <?php echo svg_icon('eye', 'w-3 h-3'); ?><span>View</span>
                                </button>
                                <button type="button" class="btn btn-approve text-xs px-2 py-1" title="Approve Report" onclick="showApproveConfirmation('ambulance_reports', '${report.id || report._id || ''}', '${report.fullName || report.reporterName || ''}', 'ambulance')">
                                    <?php echo svg_icon('check-circle', 'w-3 h-3'); ?><span>Approve</span>
                                </button>
                                <button type="button" class="btn btn-decline text-xs px-2 py-1" title="Decline Report" onclick="showDeclineConfirmation('ambulance_reports', '${report.id || report._id || ''}', '${report.fullName || report.reporterName || ''}', 'ambulance')">
                                    <?php echo svg_icon('x-circle', 'w-3 h-3'); ?><span>Decline</span>
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            });

            emergencyContainer.innerHTML = alertsHtml;
        }

        // New function to render the report table HTML
        function renderReportsTable(reports, collection, categories) {
            if (reports.length === 0) {
                return ``;
            }

            const slug = Object.keys(categories).find(key => categories[key].collection === collection);

            let tableRows = '';
            reports.forEach((it, i) => {
                const st = (it.status || 'Pending').toLowerCase();
                const displayStatus = it.status || 'Pending';
                const isApproved = (st === 'approved');
                const isDeclined = (st === 'declined');
                const isFinal = isApproved || isDeclined;
                const tDisplay = it.tsDisplay || formatFirebaseTimestamp(it.timestamp);
                const imgUrl = it.imageUrl || '';
                
                // Normalize the data for consistent field mapping
                const normalizedData = normalizeFirebaseReportData(it);
                
                let statusClass = 'status-badge-pending';
                if (isApproved) statusClass = 'status-badge-success';
                if (isDeclined) statusClass = 'status-badge-declined';

                const animDelay = `style="--anim-delay: ${i * 50}ms"`;

                tableRows += `
                    <tr class='report-row animate-fade-in-up' ${animDelay} data-id='${it.id}' data-collection='${collection}'>
                        <td class="p-4 whitespace-nowrap">
                            <div class="font-semibold text-slate-800">${normalizedData.fullName || '—'}</div>
                            <div class="text-slate-500">${normalizedData.contact || '—'}</div>
                        </td>
                        <td class="p-4 text-slate-600 max-w-xs truncate">${normalizedData.location || '—'}</td>
                        <td class="p-4 text-slate-600 whitespace-nowrap">${tDisplay || formatFirebaseTimestamp(it.timestamp)}</td>
                        <td class="p-4">
                            <span class="status-badge ${statusClass}">
                                <span class="h-2 w-2 rounded-full bg-current mr-2"></span>
                                ${displayStatus}
                            </span>
                        </td>
                        <td class="p-4 text-right">
                            <div class="inline-flex items-center gap-2">
                                <button type="button" class="btn btn-view" title="View Details"
                                    onclick="showReportModal(this)"
                                    data-slug="${slug}" data-id="${it.id}" data-collection="${collection}"
                                    data-fullname="${normalizedData.fullName}" data-contact="${normalizedData.mobileNumber || normalizedData.contact}"
                                    data-location="${normalizedData.location}" data-purpose="${normalizedData.purpose}"
                                    data-status="${displayStatus}" data-timestamp="${tDisplay}"
                                    data-rawtimestamp="${JSON.stringify(it.timestamp).replace(/"/g, '&quot;')}"
                                    data-reporterid="${normalizedData.reporterId}" data-imageurl="${imgUrl}">
                                    <?php echo svg_icon('eye', 'w-4 h-4'); ?><span>View</span>
                                </button>
                                <button type="button" class="btn ${isFinal ? 'btn-disabled' : 'btn-approve'}" ${isFinal ? 'disabled' : ''} title="Approve Report" onclick="showApproveConfirmation('${collection}', '${it.id}', '${normalizedData.fullName}', '${slug}')">
                                    <?php echo svg_icon('check-circle', 'w-4 h-4'); ?><span>Approve</span>
                                </button>
                                <button type="button" class="btn ${isFinal ? 'btn-disabled' : 'btn-decline'}" ${isFinal ? 'disabled' : ''} title="Decline Report" onclick="showDeclineConfirmation('${collection}', '${it.id}', '${normalizedData.fullName}', '${slug}')">
                                    <?php echo svg_icon('x-circle', 'w-4 h-4'); ?><span>Decline</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            });

            return `
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr>
                                <th class="p-4 text-left font-semibold text-slate-500 uppercase tracking-wider">Reporter Details</th>
                                <th class="p-4 text-left font-semibold text-slate-500 uppercase tracking-wider">Location</th>
                                <th class="p-4 text-left font-semibold text-slate-500 uppercase tracking-wider">Timestamp</th>
                                <th class="p-4 text-left font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                                <th class="p-4 text-right font-semibold text-slate-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/50">
                            ${tableRows}
                        </tbody>
                    </table>
                </div>
            `;
        }

        <?php endif; ?>
    });
    </script>

    <script type="module">
    import { initializeApp } from "https://www.gstatic.com/firebasejs/10.12.5/firebase-app.js";
    import { getFirestore, doc, onSnapshot, collection, query, where, orderBy, limit } from "https://www.gstatic.com/firebasejs/10.12.5/firebase-firestore.js";

    if (window.FIREBASE_CLIENT_CONFIG && window.FIREBASE_CLIENT_CONFIG.projectId) {
        const app = initializeApp(window.FIREBASE_CLIENT_CONFIG);
        const db = getFirestore(app);
        
        // ============ LISTEN FOR NEW REPORTS (Realtime) ============
        const reportCollections = ['ambulance_reports', 'fire_reports', 'police_reports', 'tanod_reports', 'flood_reports', 'other_reports'];
        let isInitialLoad = true;
        
        // Wait a moment for initial page load before enabling notifications
        setTimeout(() => { isInitialLoad = false; }, 3000);
        
        reportCollections.forEach(collName => {
            try {
                const q = query(
                    collection(db, collName),
                    orderBy('timestamp', 'desc'),
                    limit(1)
                );
                
                onSnapshot(q, (snapshot) => {
                    snapshot.docChanges().forEach(change => {
                        if (change.type === 'added' && !isInitialLoad) {
                            const data = change.doc.data();
                            const st = String(data?.status || '').toLowerCase();
                            
                            // Only notify for pending reports
                            if (st && st !== 'pending') return;
                            
                            // Only notify if report is recent (last 5 minutes)
                            const reportTime = data.timestamp?.seconds ? (data.timestamp.seconds * 1000) : Date.now();
                            if (Date.now() - reportTime > 5 * 60 * 1000) return;
                            
                            console.log('[Firebase Realtime] 🆕 New report detected in:', collName);
                            
                            // Show notification
                            if (typeof window.showNotificationWithSound === 'function') {
                                window.showNotificationWithSound(`New ${collName.replace('_reports', '')} report received!`, 'info', 'siren');
                            }
                            
                            // Refresh the activity feed with a small delay to ensure DOM is ready
                            setTimeout(() => {
                                console.log('[Firebase Realtime] 🔄 Refreshing activity feed...');
                                if (typeof window.loadRecentPage === 'function') {
                                    window.forceRecentFeedRefresh = true;
                                    window.loadRecentPage(1);
                                    console.log('[Firebase Realtime] ✅ Called loadRecentPage(1)');
                                } else if (typeof window.refreshRecentActivity === 'function') {
                                    window.forceRecentFeedRefresh = true;
                                    window.refreshRecentActivity();
                                    console.log('[Firebase Realtime] ✅ Called refreshRecentActivity()');
                                } else {
                                    console.warn('[Firebase Realtime] ⚠️ No refresh function available, reloading activityList...');
                                    // Fallback: manually reload via AJAX
                                    const list = document.getElementById('activityList');
                                    if (list) {
                                        const fd = new FormData();
                                        fd.append('recent_feed', '1');
                                        fd.append('page', '1');
                                        fd.append('pageSize', '10');
                                        fd.append('category', 'all');
                                        fd.append('status', 'all');
                                        fd.append('force_refresh', 'true');
                                        fetch(window.location.href, { method: 'POST', body: fd })
                                            .then(r => r.json())
                                            .then(data => {
                                                if (data.html) {
                                                    list.innerHTML = data.html;
                                                    console.log('[Firebase Realtime] ✅ Activity list refreshed via fallback');
                                                }
                                            })
                                            .catch(e => console.error('[Firebase Realtime] Fallback refresh failed:', e));
                                    }
                                }
                            }, 100);
                        }
                    });
                }, (error) => {
                    console.log(`[Firebase Realtime] Listener error for ${collName}:`, error.message);
                });
                
                console.log('[Firebase Realtime] ✅ Listening to:', collName);
            } catch (e) {
                console.error(`[Firebase Realtime] Failed to setup listener for ${collName}:`, e);
            }
        });
        
        console.log('[Firebase Realtime] ✅ All report listeners initialized');
        
        // ============ WATCH EXISTING ITEMS FOR STATUS CHANGES ============
        const list = document.getElementById('activityList');
        if (list) {
            const attached = new Set();
            const watch = (li) => {
                const coll = li.dataset.collection;
                const id = li.dataset.id;
                if (!coll || !id) return;
                const key = `${coll}/${id}`;
                if (attached.has(key)) return;
                attached.add(key);
                try {
                    onSnapshot(doc(db, coll, id), (snap) => {
                        const data = snap.data();
                        if (!data || typeof window.updateActivityItemStatus !== 'function') return;
                        const status = typeof data.status === 'string' ? data.status : 'Pending';
                        window.updateActivityItemStatus(id, status);
                    });
                } catch(e) { console.error(`Failed to watch document ${key}:`, e); }
            };
            Array.from(list.querySelectorAll('li[data-id]')).forEach(watch);
            const mo = new MutationObserver((mutations) => {
                mutations.forEach(mutation => {
                    if (mutation.addedNodes.length) {
                        Array.from(list.querySelectorAll('li[data-id]')).forEach(watch);
                    }
                });
            });
            mo.observe(list, { childList: true });
        }
    }

    // Notification System
    const notificationBell = document.getElementById('notificationBell');
    const notificationDropdown = document.getElementById('notificationDropdown');
    const notificationBadge = document.getElementById('notificationBadge');
    const notificationList = document.getElementById('notificationList');
    const markAllRead = document.getElementById('markAllRead');

    if (notificationBell) {
        // Toggle notification dropdown
        notificationBell.addEventListener('click', (e) => {
            e.stopPropagation();
            notificationDropdown.classList.toggle('hidden');
            if (!notificationDropdown.classList.contains('hidden')) {
                loadNotifications();
            }
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!notificationBell.contains(e.target) && !notificationDropdown.contains(e.target)) {
                notificationDropdown.classList.add('hidden');
            }
        });

        // Load notification count on page load
        loadNotificationCount();
        
        // Refresh notification count every 30 seconds
        setInterval(loadNotificationCount, 30000);
        
        // Real-time notification updates
        if (window.FIREBASE_CLIENT_CONFIG && window.FIREBASE_CLIENT_CONFIG.projectId) {
            setupRealtimeNotifications();
        }
    }

    // Setup real-time notifications
    function setupRealtimeNotifications() {
        const app = initializeApp(window.FIREBASE_CLIENT_CONFIG);
        const db = getFirestore(app);
        
        // Define report collections to watch
        const reportCollections = [
            'ambulance_reports',
            'fire_reports', 
            'flood_reports',
            'other_reports',
            'tanod_reports'
        ];
        
        // Watch each collection for new documents
        reportCollections.forEach(colName => {
            try {
                // Use collection query to listen for new documents
                const q = query(
                    collection(db, colName),
                    where('status', '==', 'Pending'),
                    orderBy('timestamp', 'desc'),
                    limit(1)
                );
                
                const unsubscribe = onSnapshot(q, (snapshot) => {
                    snapshot.docChanges().forEach((change) => {
                        if (change.type === 'added') {
                            const data = change.doc.data();
                            // Check if this is a recent document (within last 5 minutes)
                            const timestamp = data.timestamp;
                            const now = new Date();
                            let docTime = new Date();
                            
                            // Properly handle Firebase timestamp
                            try {
                                if (timestamp && typeof timestamp.toDate === 'function') {
                                    docTime = timestamp.toDate();
                                } else if (timestamp && timestamp.seconds) {
                                    docTime = new Date(timestamp.seconds * 1000);
                                } else if (timestamp) {
                                    docTime = new Date(timestamp);
                                }
                            } catch (error) {
                                console.warn('Error parsing timestamp:', timestamp, error);
                            }
                            
                            const timeDiff = now - docTime;
                            
                            // Only create notification for documents created in the last 5 minutes
                            if (timeDiff < 5 * 60 * 1000) {
                                createNotificationForNewReport(colName, change.doc.id, data);
                            }
                        }
                    });
                });
                
                // Store unsubscribe function for cleanup
                if (!window.notificationUnsubscribers) {
                    window.notificationUnsubscribers = [];
                }
                window.notificationUnsubscribers.push(unsubscribe);
                
            } catch (error) {
                console.error(`Error setting up real-time listener for ${colName}:`, error);
            }
        });
    }

    // Create notification for new report
    function createNotificationForNewReport(collection, reportId, reportData) {
        const formData = new FormData();
        formData.append('api_action', 'create_notification_for_report');
        formData.append('collection', collection);
        formData.append('reportId', reportId);
        
        // Normalize the report data before sending
        const normalizedData = normalizeFirebaseReportData(reportData);
        formData.append('reportData', JSON.stringify(normalizedData));
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update notification count and list
                loadNotificationCount();
                if (!notificationDropdown.classList.contains('hidden')) {
                    loadNotifications();
                }
                
                // Show toast notification
                showToast(`New ${getCollectionLabel(collection)} report received!`, 'info');
            }
        })
        .catch(error => console.error('Error creating notification:', error));
    }

    // Get collection label
    function getCollectionLabel(collection) {
        const labels = {
            'ambulance_reports': '🚑 Ambulance',
            'fire_reports': '🔥 Fire',
            'flood_reports': '🌊 Flood',
            'other_reports': '📋 Other',
            'tanod_reports': '👮 Tanod'
        };
        return labels[collection] || '📋 Report';
    }

    function loadNotificationCount() {
        const formData = new FormData();
        formData.append('api_action', 'get_notification_count');
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const count = data.count;
                if (count > 0) {
                    notificationBadge.textContent = count;
                    notificationBadge.classList.remove('hidden');
                    // Add pulsing animation for urgent notifications
                    notificationBell.classList.add('animate-pulse');
                } else {
                    notificationBadge.classList.add('hidden');
                    notificationBell.classList.remove('animate-pulse');
                }
            }
        })
        .catch(error => console.error('Error loading notification count:', error));
    }

    function loadNotifications() {
        const formData = new FormData();
        formData.append('api_action', 'get_notifications');
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayNotifications(data.notifications);
            }
        })
        .catch(error => console.error('Error loading notifications:', error));
    }

    function displayNotifications(notifications) {
        if (notifications.length === 0) {
            notificationList.innerHTML = '<div class="p-4 text-center text-slate-500">No new notifications</div>';
            return;
        }

        const html = notifications.map(notification => {
            // Get status from notification data
            const status = notification.data?.status || 'Pending';
            let statusClass = 'bg-red-100 text-red-600';
            let statusIcon = '🚨';
            
            if (status === 'Approved') {
                statusClass = 'bg-green-100 text-green-600';
                statusIcon = '✅';
            } else if (status === 'Declined') {
                statusClass = 'bg-orange-100 text-orange-600';
                statusIcon = '❌';
            }
            
            return `
                <div class="p-4 border-b border-slate-100 hover:bg-slate-50 transition-colors cursor-pointer" 
                     onclick="markNotificationRead('${notification._id}')">
                    <div class="flex items-start gap-3">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 ${statusClass} rounded-full flex items-center justify-center">
                                <span class="text-sm">${statusIcon}</span>
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <p class="text-sm font-semibold text-slate-800">${notification.title}</p>
                                <span class="text-xs px-2 py-1 rounded-full ${statusClass}">${status}</span>
                            </div>
                            <p class="text-xs text-slate-600 mt-1">${notification.message}</p>
                            <p class="text-xs text-slate-400 mt-2">${formatNotificationTime(notification.timestamp)}</p>
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        notificationList.innerHTML = html;
    }

    function markNotificationRead(notificationId) {
        const formData = new FormData();
        formData.append('api_action', 'mark_notification_read');
        formData.append('notification_id', notificationId);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadNotificationCount();
                loadNotifications();
            }
        })
        .catch(error => console.error('Error marking notification as read:', error));
    }

    function formatNotificationTime(timestamp) {
        const now = new Date();
        const time = new Date(timestamp);
        const diff = now - time;
        
        if (diff < 60000) return 'Just now';
        if (diff < 3600000) return Math.floor(diff / 60000) + ' minutes ago';
        if (diff < 86400000) return Math.floor(diff / 3600000) + ' hours ago';
        return Math.floor(diff / 86400000) + ' days ago';
    }

    if (markAllRead) {
        markAllRead.addEventListener('click', () => {
            // Mark all notifications as read
            const notifications = notificationList.querySelectorAll('[onclick*="markNotificationRead"]');
            notifications.forEach(notification => {
                const notificationId = notification.getAttribute('onclick').match(/'([^']+)'/)[1];
                markNotificationRead(notificationId);
            });
        });
    }

    // Mobile menu functionality
    document.addEventListener('DOMContentLoaded', function() {
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const mobileMenuOverlay = document.getElementById('mobileMenuOverlay');
        
        if (mobileMenuBtn && mobileMenuOverlay) {
            // Toggle mobile menu
            mobileMenuBtn.addEventListener('click', function() {
                mobileMenuOverlay.classList.toggle('hidden');
            });
            
            // Close mobile menu when clicking overlay
            mobileMenuOverlay.addEventListener('click', function(e) {
                if (e.target === mobileMenuOverlay) {
                    mobileMenuOverlay.classList.add('hidden');
                }
            });
            
            // Close mobile menu when clicking any navigation link
            const mobileNavLinks = mobileMenuOverlay.querySelectorAll('a');
            mobileNavLinks.forEach(link => {
                link.addEventListener('click', function() {
                    mobileMenuOverlay.classList.add('hidden');
                });
            });
            
            // Handle escape key to close menu
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && !mobileMenuOverlay.classList.contains('hidden')) {
                    mobileMenuOverlay.classList.add('hidden');
                }
            });
        }
        
        // Global function to close mobile sidebar
        window.closeMobileSidebar = function() {
            const mobileMenuOverlay = document.getElementById('mobileMenuOverlay');
            if (mobileMenuOverlay) {
                mobileMenuOverlay.classList.add('hidden');
            }
        };
    });

// Global variables for tracking modal state
        let pendingApproveAction = null;
        let pendingDeclineAction = null;

        // Approve modal functions
        function showApproveModal(collection, docId, reporterName, categoryType) {
            console.log('showApproveModal called:', { collection, docId, reporterName, categoryType });
            
            // Close the report modal first
            const reportModal = document.getElementById('reportModal');
            if (reportModal) {
                reportModal.classList.remove('opacity-100');
                reportModal.classList.add('opacity-0', 'pointer-events-none');
            }
            
            const modal = document.getElementById('approveModal');
            const content = modal.querySelector('.relative');
            const detailsDiv = document.getElementById('approve-report-details');

            if (!modal) {
                console.error('Approve modal not found!');
                return;
            }

            // Store the action details for later execution
            pendingApproveAction = {
                collection: collection,
                docId: docId,
                reporterName: reporterName,
                categoryType: categoryType
            };

            // Populate report details
            detailsDiv.innerHTML = `
                <p><strong>Reporter:</strong> ${reporterName}</p>
                <p><strong>Type:</strong> ${categoryType}</p>
                <p><strong>ID:</strong> ${docId}</p>
            `;

            // Show modal
            modal.classList.remove('opacity-0', 'pointer-events-none');
            setTimeout(() => {
                content.classList.remove('opacity-0', 'scale-95');
            }, 50);
            console.log('Approve modal should now be visible');
        }

        function closeApproveModal() {
            console.log('closeApproveModal called');
            const modal = document.getElementById('approveModal');
            const content = modal.querySelector('.relative');

            content.classList.add('opacity-0', 'scale-95');
            modal.classList.add('opacity-0');

            setTimeout(() => {
                modal.classList.add('pointer-events-none');
                pendingApproveAction = null;
            }, 300);
        }

        // Function to update report status (used by confirmation modals)
        async function updateReportStatus(collection, docId, newStatus, declineReason = null) {
            console.log('🚀 updateReportStatus called:', { collection, docId, newStatus, declineReason });
            console.log('🔍 Starting status update process...');
            
            // Show loading toast
            const actionType = newStatus === 'Approved' ? 'Approving' : 'Declining';
            showToast(`${actionType} report...`, 'info');
            
            try {
                console.log('📝 Creating FormData...');
                const formData = new FormData();
                formData.append('api_action', 'update_status');
                formData.append('collection', collection);
                formData.append('docId', docId);
                formData.append('newStatus', newStatus);
                
                // Add decline reason if provided
                if (declineReason && newStatus === 'Declined') {
                    formData.append('declineReason', declineReason);
                    console.log('📝 Added decline reason to request:', declineReason);
                }
                
                console.log('📤 Sending request with data:', {
                    api_action: 'update_status',
                    collection: collection,
                    docId: docId,
                    newStatus: newStatus,
                    declineReason: declineReason || 'N/A'
                });
                console.log('🌐 Request URL:', window.location.href);
                
                console.log('⏰ Making fetch request...');
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                console.log('📨 Response received:', response.status, response.statusText);
                console.log('📨 Response headers:', response.headers);
                
                if (!response.ok) {
                    console.error('❌ Response not OK:', response.status, response.statusText);
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                console.log('📝 Parsing JSON response...');
                const result = await response.json();
                console.log('📋 Response data:', result);
                
                if (result.success) {
                    console.log('✅ Status update successful!');
                    
                    // Clear timeout
                    if (window.currentTimeoutId) {
                        clearTimeout(window.currentTimeoutId);
                        window.currentTimeoutId = null;
                        console.log('⏰ Timeout cleared');
                    }
                    
                    // Restore external buttons if they exist
                    if (window.currentExternalApproveBtn && window.originalExternalApproveText) {
                        console.log('🔄 Restoring external approve button...');
                        window.currentExternalApproveBtn.innerHTML = window.originalExternalApproveText;
                        window.currentExternalApproveBtn.disabled = false;
                        window.currentExternalApproveBtn.classList.remove('opacity-75');
                        console.log('✅ External approve button restored');
                        
                        // Clean up references
                        window.currentExternalApproveBtn = null;
                        window.originalExternalApproveText = null;
                    }
                    
                    if (window.currentExternalDeclineBtn && window.originalExternalDeclineText) {
                        console.log('🔄 Restoring external decline button...');
                        window.currentExternalDeclineBtn.innerHTML = window.originalExternalDeclineText;
                        window.currentExternalDeclineBtn.disabled = false;
                        window.currentExternalDeclineBtn.classList.remove('opacity-75');
                        console.log('✅ External decline button restored');
                        
                        // Clean up references
                        window.currentExternalDeclineBtn = null;
                        window.originalExternalDeclineText = null;
                    }
                    
                    // Show success message
                    console.log('🎉 Showing success toast and modal...');
                    showToast(`Report ${newStatus.toLowerCase()} successfully!`, 'success');
                    
                    // Show success modal
                    showSuccessModal(newStatus, docId);
                    
                    // Update the UI immediately
                    let reportRows = Array.from(document.querySelectorAll(`tr.report-row[data-id="${docId}"]`));
                    
                    // Fallback: Try to find row via the button that triggered the action
                    if (reportRows.length === 0) {
                        console.log('⚠️ No rows found by data-id, trying button reference...');
                        let btn = null;
                        if (newStatus === 'Approved') btn = window.currentExternalApproveBtn;
                        else if (newStatus === 'Declined') btn = window.currentExternalDeclineBtn;
                        
                        if (btn) {
                            const row = btn.closest('tr.report-row') || btn.closest('tr');
                            if (row) {
                                console.log('🎯 Found row via button reference');
                                reportRows.push(row);
                            }
                        }
                    }
                    
                    console.log('🎯 Total report rows to update:', reportRows.length);
                    reportRows.forEach(row => {
                        // Update status badge
                        const badge = row.querySelector('.status-badge');
                        if (badge) {
                            badge.classList.remove('status-badge-success', 'status-badge-pending', 'status-badge-declined');
                            const st = newStatus.toLowerCase();
                            if (st === 'approved') badge.classList.add('status-badge-success');
                            else badge.classList.add('status-badge-declined');
                            badge.innerHTML = `<span class="h-2 w-2 rounded-full bg-current mr-2"></span>${newStatus}`;
                        }
                        
                        // Disable action buttons
                        row.querySelectorAll('button[onclick*="showApproveConfirmation"], button[onclick*="showDeclineConfirmation"]').forEach(btn => {
                            btn.disabled = true;
                            btn.classList.add('opacity-50', 'btn-disabled');
                        });
                        
                        // Move report to correct section with animation
                        setTimeout(() => {
                            row.style.transition = 'opacity 0.3s ease-out, transform 0.3s ease-out';
                            row.style.opacity = '0';
                            row.style.transform = 'translateX(-20px)';
                            
                            setTimeout(() => {
                                row.remove();
                            }, 300);
                        }, 2000); // Longer delay to see the success feedback
                    });

                    // Update Recent Activity items immediately (Real-time UI update)
                    const recentItems = document.querySelectorAll(`#activityList li[data-id="${docId}"]`);
                    console.log('🎯 Updating recent activity items:', recentItems.length);
                    recentItems.forEach(item => {
                        // Update data attribute
                        item.dataset.status = newStatus.toLowerCase();
                        
                        // Update icon background
                        const iconBg = item.querySelector('.w-14.h-14');
                        if (iconBg) {
                            // Remove old gradient classes
                            iconBg.classList.remove('from-yellow-500', 'to-amber-600', 'from-green-500', 'to-emerald-600', 'from-red-500', 'to-rose-600');
                            
                            // Add new gradient classes
                            if (newStatus === 'Approved') {
                                iconBg.classList.add('from-green-500', 'to-emerald-600');
                            } else if (newStatus === 'Declined') {
                                iconBg.classList.add('from-red-500', 'to-rose-600');
                            } else {
                                iconBg.classList.add('from-yellow-500', 'to-amber-600');
                            }
                        }
                        
                        // Update status dot
                        const statusDot = item.querySelector('.absolute.-top-1.-right-1');
                        if (statusDot) {
                            statusDot.classList.remove('bg-yellow-500', 'bg-green-500', 'bg-red-500');
                            statusDot.classList.add(newStatus === 'Approved' ? 'bg-green-500' : (newStatus === 'Declined' ? 'bg-red-500' : 'bg-yellow-500'));
                        }
                        
                        // Update status badge
                        const statusBadge = item.querySelector('.inline-flex.items-center.gap-2');
                        if (statusBadge) {
                            // Update text color
                            statusBadge.classList.remove('text-yellow-700', 'text-green-700', 'text-red-700');
                            statusBadge.classList.add(newStatus === 'Approved' ? 'text-green-700' : (newStatus === 'Declined' ? 'text-red-700' : 'text-yellow-700'));
                                
                            // Update border color
                            statusBadge.classList.remove('border-yellow-200', 'border-green-200', 'border-red-200');
                            statusBadge.classList.add(newStatus === 'Approved' ? 'border-green-200' : (newStatus === 'Declined' ? 'border-red-200' : 'border-yellow-200'));
                                
                            // Update inner dot
                            const innerDot = statusBadge.querySelector('.w-2.h-2');
                            if (innerDot) {
                                innerDot.classList.remove('bg-yellow-500', 'bg-green-500', 'bg-red-500');
                                innerDot.classList.add(newStatus === 'Approved' ? 'bg-green-500' : (newStatus === 'Declined' ? 'bg-red-500' : 'bg-yellow-500'));
                            }
                            
                            // Update text content
                            // Find the text node (it's usually the last child after the dot span)
                            let textUpdated = false;
                            statusBadge.childNodes.forEach(node => {
                                if (node.nodeType === 3 && node.textContent.trim().length > 0) {
                                    node.textContent = ' ' + newStatus;
                                    textUpdated = true;
                                }
                            });
                            
                            if (!textUpdated) {
                                statusBadge.appendChild(document.createTextNode(' ' + newStatus));
                            }
                        }
                    });
                    
                    // Update tab counts in real-time
                    updateTabCountsRealtime(collection, docId, newStatus);
                    
                    // Refresh Recent Activity list to reflect changes
                    if (typeof window.refreshRecentActivity === 'function') {
                        console.log('🔄 Refreshing recent activity list...');
                        window.refreshRecentActivity();
                    }
                    
                    // Update counters and refresh data
                    if (typeof updateStatusCounters === 'function') {
                        updateStatusCounters(collection, newStatus);
                    }
                    
                    if (typeof loadStaffData === 'function') {
                        setTimeout(() => {
                            loadStaffData(true); // Force refresh
                        }, 1000);
                    }
                    
                } else {
                    console.error('❌ Server returned error:', result.message);
                    
                    // Clear timeout
                    if (window.currentTimeoutId) {
                        clearTimeout(window.currentTimeoutId);
                        window.currentTimeoutId = null;
                    }
                    
                    // Restore external buttons on error
                    if (window.currentExternalApproveBtn && window.originalExternalApproveText) {
                        window.currentExternalApproveBtn.innerHTML = window.originalExternalApproveText;
                        window.currentExternalApproveBtn.disabled = false;
                        window.currentExternalApproveBtn.classList.remove('opacity-75');
                        console.log('✅ External approve button restored after error');
                        
                        // Clean up references
                        window.currentExternalApproveBtn = null;
                        window.originalExternalApproveText = null;
                    }
                    
                    if (window.currentExternalDeclineBtn && window.originalExternalDeclineText) {
                        window.currentExternalDeclineBtn.innerHTML = window.originalExternalDeclineText;
                        window.currentExternalDeclineBtn.disabled = false;
                        window.currentExternalDeclineBtn.classList.remove('opacity-75');
                        console.log('✅ External decline button restored after error');
                        
                        // Clean up references
                        window.currentExternalDeclineBtn = null;
                        window.originalExternalDeclineText = null;
                    }
                    
                    showToast(result.message || 'Failed to update status', 'error');
                }
                
            } catch (error) {
                console.error('💥 Error updating status:', error);
                console.error('💥 Error details:', error.stack);
                
                // Clear timeout
                if (window.currentTimeoutId) {
                    clearTimeout(window.currentTimeoutId);
                    window.currentTimeoutId = null;
                }
                
                // Restore external buttons on error
                if (window.currentExternalApproveBtn && window.originalExternalApproveText) {
                    window.currentExternalApproveBtn.innerHTML = window.originalExternalApproveText;
                    window.currentExternalApproveBtn.disabled = false;
                    window.currentExternalApproveBtn.classList.remove('opacity-75');
                    console.log('✅ External approve button restored after network error');
                    
                    // Clean up references
                    window.currentExternalApproveBtn = null;
                    window.originalExternalApproveText = null;
                }
                
                if (window.currentExternalDeclineBtn && window.originalExternalDeclineText) {
                    window.currentExternalDeclineBtn.innerHTML = window.originalExternalDeclineText;
                    window.currentExternalDeclineBtn.disabled = false;
                    window.currentExternalDeclineBtn.classList.remove('opacity-75');
                    console.log('✅ External decline button restored after network error');
                    
                    // Clean up references
                    window.currentExternalDeclineBtn = null;
                    window.originalExternalDeclineText = null;
                }
                
                showToast('Failed to update status - please try again', 'error');
            }
        }

        // Function to show success modal after approval/decline
        function showSuccessModal(status, docId) {
            const isApproved = status === 'Approved';
            const color = isApproved ? 'green' : 'red';
            const icon = isApproved ? 'check-circle' : 'x-circle';
            const title = isApproved ? 'Report Approved!' : 'Report Declined!';
            const message = isApproved 
                ? 'Emergency responders have been notified and will respond shortly.'
                : 'The reporter has been notified and can resubmit if needed.';

            // Create success modal
            const successModal = document.createElement('div');
            successModal.id = 'successModal';
            successModal.className = 'fixed inset-0 z-[60] flex items-center justify-center p-4';
            successModal.innerHTML = `
                <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm"></div>
                <div class="relative max-w-md w-full bg-white rounded-2xl shadow-xl overflow-hidden animate-fade-in-up">
                    <div class="p-6 text-center">
                        <div class="w-16 h-16 bg-${color}-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <?php echo svg_icon('${icon}', 'w-8 h-8 text-${color}-600'); ?>
                        </div>
                        <h3 class="text-xl font-bold text-${color}-800 mb-2">${title}</h3>
                        <p class="text-gray-600 mb-6">${message}</p>
                        <div class="text-xs text-gray-500 mb-4">Report ID: ${docId}</div>
                        <button onclick="closeSuccessModal()" class="btn btn-primary w-full" style="background-color: ${isApproved ? '#10b981' : '#dc2626'};">
                            Continue
                        </button>
                    </div>
                </div>
            `;

            document.body.appendChild(successModal);

            // Auto-close after 5 seconds
            setTimeout(() => {
                closeSuccessModal();
            }, 5000);
        }

        // Function to close success modal
        window.closeSuccessModal = function() {
            const modal = document.getElementById('successModal');
            if (modal) {
                modal.style.opacity = '0';
                setTimeout(() => {
                    modal.remove();
                }, 300);
            }
        }

        // Function to update tab counts in real-time
        function updateTabCountsRealtime(collection, docId, newStatus) {
            console.log('Updating tab counts in real-time:', { collection, docId, newStatus });
            
            // Find the appropriate slug based on collection
            const collectionToSlug = {
                'ambulance_reports': 'ambulance',
                'fire_reports': 'fire', 
                'flood_reports': 'flood',
                'tanod_reports': 'tanod',
                'other_reports': 'other'
            };
            
            const slug = collectionToSlug[collection];
            if (!slug) {
                console.log('No slug found for collection:', collection);
                return;
            }
            
            // Find the segmented control for this category
            const segmentedControl = document.querySelector(`.segmented[data-slug="${slug}"]`);
            if (!segmentedControl) {
                console.log('No segmented control found for slug:', slug);
                return;
            }
            
            // Get current counts
            const pendingTab = segmentedControl.querySelector('[data-tab="pending"] .tab-count');
            const approvedTab = segmentedControl.querySelector('[data-tab="approved"] .tab-count');
            const declinedTab = segmentedControl.querySelector('[data-tab="declined"] .tab-count');
            
            if (pendingTab && approvedTab && declinedTab) {
                // Decrease pending count
                const pendingCount = parseInt(pendingTab.textContent) || 0;
                const newPendingCount = Math.max(0, pendingCount - 1);
                pendingTab.textContent = newPendingCount;
                
                // Increase appropriate count based on new status
                if (newStatus === 'Approved') {
                    const approvedCount = parseInt(approvedTab.textContent) || 0;
                    approvedTab.textContent = approvedCount + 1;
                    
                    // Add pulse animation
                    approvedTab.classList.add('animate-pulse');
                    setTimeout(() => approvedTab.classList.remove('animate-pulse'), 1000);
                } else if (newStatus === 'Declined') {
                    const declinedCount = parseInt(declinedTab.textContent) || 0;
                    declinedTab.textContent = declinedCount + 1;
                    
                    // Add pulse animation
                    declinedTab.classList.add('animate-pulse');
                    setTimeout(() => declinedTab.classList.remove('animate-pulse'), 1000);
                }
                
                // Add pulse animation to pending (decreased)
                pendingTab.classList.add('animate-pulse');
                setTimeout(() => pendingTab.classList.remove('animate-pulse'), 1000);
                
                console.log('✅ Tab counts updated successfully:', {
                    pending: newPendingCount,
                    approved: approvedTab.textContent,
                    declined: declinedTab.textContent
                });
            } else {
                console.log('❌ Could not find tab count elements');
            }
        }

        function confirmApprove() {
            console.log('🚀 confirmApprove called, pendingApproveAction:', pendingApproveAction);
            if (!pendingApproveAction) {
                console.error('❌ No pending approve action found!');
                return;
            }

            // Find and update the external approve button to loading state
            const docId = pendingApproveAction.docId;
            console.log('🔍 Looking for approve button with docId:', docId);
            
            // Try multiple selectors to find the button
            let externalApproveBtn = document.querySelector(`button[onclick*="showApproveConfirmation"][onclick*="${docId}"]`);
            if (!externalApproveBtn) {
                // Try alternative selector
                externalApproveBtn = document.querySelector(`button[onclick*="showApproveConfirmation("][onclick*="'${docId}'"]`);
            }
            if (!externalApproveBtn) {
                // Try finding by data attribute if it exists
                externalApproveBtn = document.querySelector(`button[data-doc-id="${docId}"][onclick*="showApproveConfirmation"]`);
            }
            if (!externalApproveBtn) {
                // Debug: List all approve buttons to see their onclick attributes
                const allApproveButtons = document.querySelectorAll('button[onclick*="showApproveConfirmation"]');
                console.log('🔍 All approve buttons found:', allApproveButtons.length);
                allApproveButtons.forEach((btn, index) => {
                    console.log(`Button ${index}:`, btn.onclick?.toString() || btn.getAttribute('onclick'));
                });
            }
            
            console.log('🔍 External approve button found:', externalApproveBtn);
            
            let originalExternalText = '';
            if (externalApproveBtn) {
                originalExternalText = externalApproveBtn.innerHTML;
                externalApproveBtn.innerHTML = '<?php echo svg_icon('spinner', 'w-4 h-4 animate-spin'); ?> Approving...';
                externalApproveBtn.disabled = true;
                externalApproveBtn.classList.add('opacity-75');
                console.log('✅ External button set to loading state');
            } else {
                console.warn('⚠️ External approve button not found - will continue without loading state');
            }

            // Show loading on the modal confirm button
            const confirmBtn = document.querySelector('#approveModal button[onclick="confirmApprove()"]');
            console.log('🔍 Confirm button found:', confirmBtn);
            if (confirmBtn) {
                const originalText = confirmBtn.innerHTML;
                confirmBtn.innerHTML = '<?php echo svg_icon('spinner', 'w-4 h-4 animate-spin'); ?> Approving...';
                confirmBtn.disabled = true;
                
                // Restore button after action completes
                setTimeout(() => {
                    if (confirmBtn && document.body.contains(confirmBtn)) {
                        confirmBtn.innerHTML = originalText;
                        confirmBtn.disabled = false;
                    }
                }, 3000);
            }

            // Close the modal first
            console.log('🔒 Closing approve modal...');
            closeApproveModal();

            // Call the status update directly
            console.log('📡 Calling updateReportStatus with:', {
                collection: pendingApproveAction.collection,
                docId: pendingApproveAction.docId,
                status: 'Approved'
            });
            
            // Store external button reference for restoration after completion
            window.currentExternalApproveBtn = externalApproveBtn;
            window.originalExternalApproveText = originalExternalText;
            
            // Add a timeout to restore button if API call takes too long
            const timeoutId = setTimeout(() => {
                console.warn('⚠️ API call timeout - restoring button');
                if (window.currentExternalApproveBtn && window.originalExternalApproveText) {
                    window.currentExternalApproveBtn.innerHTML = window.originalExternalApproveText;
                    window.currentExternalApproveBtn.disabled = false;
                    window.currentExternalApproveBtn.classList.remove('opacity-75');
                    window.currentExternalApproveBtn = null;
                    window.originalExternalApproveText = null;
                }
                showToast('Request timeout - please try again', 'error');
            }, 30000); // 30 second timeout
            
            // Store timeout ID for cleanup
            window.currentTimeoutId = timeoutId;
            
            updateReportStatus(pendingApproveAction.collection, pendingApproveAction.docId, 'Approved');
        }

        // Decline modal functions
        function showDeclineModal(collection, docId, reporterName, categoryType) {
            console.log('showDeclineModal called:', { collection, docId, reporterName, categoryType });
            
            // Close the report modal first
            const reportModal = document.getElementById('reportModal');
            if (reportModal) {
                reportModal.classList.remove('opacity-100');
                reportModal.classList.add('opacity-0', 'pointer-events-none');
            }
            
            const modal = document.getElementById('declineModal');
            const content = modal.querySelector('.relative');
            const detailsDiv = document.getElementById('decline-report-details');

            if (!modal) {
                console.error('Decline modal not found!');
                return;
            }

            // Store the action details for later execution
            pendingDeclineAction = {
                collection: collection,
                docId: docId,
                reporterName: reporterName,
                categoryType: categoryType
            };

            // Populate report details
            detailsDiv.innerHTML = `
                <p><strong>Reporter:</strong> ${reporterName}</p>
                <p><strong>Type:</strong> ${categoryType}</p>
                <p><strong>ID:</strong> ${docId}</p>
            `;

            // Clear and reset the decline reason textarea
            const reasonTextarea = document.getElementById('declineReason');
            if (reasonTextarea) {
                reasonTextarea.value = '';
                reasonTextarea.classList.remove('border-red-500', 'ring-2', 'ring-red-500');
                
                // Update character counter
                const charCount = document.getElementById('reasonCharCount');
                if (charCount) charCount.textContent = '0/500';
                
                // Add character counter event listener
                reasonTextarea.addEventListener('input', function() {
                    const count = this.value.length;
                    const maxLength = 500;
                    if (charCount) {
                        charCount.textContent = `${count}/${maxLength}`;
                        if (count > maxLength * 0.9) {
                            charCount.classList.add('text-red-500');
                        } else {
                            charCount.classList.remove('text-red-500');
                        }
                    }
                });
            }

            // Show modal
            modal.classList.remove('opacity-0', 'pointer-events-none');
            setTimeout(() => {
                content.classList.remove('opacity-0', 'scale-95');
            }, 50);
            console.log('Decline modal should now be visible');
        }

        function closeDeclineModal() {
            console.log('closeDeclineModal called');
            const modal = document.getElementById('declineModal');
            const content = modal.querySelector('.relative');

            content.classList.add('opacity-0', 'scale-95');
            modal.classList.add('opacity-0');

            setTimeout(() => {
                modal.classList.add('pointer-events-none');
                pendingDeclineAction = null;
                
                // Clear the decline reason textarea when modal closes
                const reasonTextarea = document.getElementById('declineReason');
                if (reasonTextarea) {
                    reasonTextarea.value = '';
                    reasonTextarea.classList.remove('border-red-500', 'ring-2', 'ring-red-500');
                }
            }, 300);
        }

        function confirmDecline() {
            console.log('🚀 confirmDecline called, pendingDeclineAction:', pendingDeclineAction);
            if (!pendingDeclineAction) {
                console.error('❌ No pending decline action found!');
                return;
            }

            // Get and validate the decline reason
            const reasonTextarea = document.getElementById('declineReason');
            if (!reasonTextarea) {
                console.error('❌ Decline reason textarea not found!');
                showToast('Error: Could not find decline reason field', 'error');
                return;
            }

            const declineReason = reasonTextarea.value.trim();
            if (!declineReason) {
                // Show validation error
                reasonTextarea.classList.add('border-red-500', 'ring-2', 'ring-red-500');
                reasonTextarea.focus();
                showToast('Please provide a reason for declining this report', 'error');
                return;
            }
            
            if (declineReason.length < 10) {
                // Ensure reason is meaningful
                reasonTextarea.classList.add('border-red-500', 'ring-2', 'ring-red-500');
                reasonTextarea.focus();
                showToast('Please provide a more detailed reason (at least 10 characters)', 'error');
                return;
            }

            // Remove validation styling if present
            reasonTextarea.classList.remove('border-red-500', 'ring-2', 'ring-red-500');

            console.log('📝 Decline reason captured:', declineReason);

            // Find and update the external decline button to loading state
            const docId = pendingDeclineAction.docId;
            console.log('🔍 Looking for decline button with docId:', docId);
            
            // Try multiple selectors to find the button
            let externalDeclineBtn = document.querySelector(`button[onclick*="showDeclineConfirmation"][onclick*="${docId}"]`);
            if (!externalDeclineBtn) {
                // Try alternative selector
                externalDeclineBtn = document.querySelector(`button[onclick*="showDeclineConfirmation("][onclick*="'${docId}'"]`);
            }
            if (!externalDeclineBtn) {
                // Try finding by data attribute if it exists
                externalDeclineBtn = document.querySelector(`button[data-doc-id="${docId}"][onclick*="showDeclineConfirmation"]`);
            }
            if (!externalDeclineBtn) {
                // Debug: List all decline buttons to see their onclick attributes
                const allDeclineButtons = document.querySelectorAll('button[onclick*="showDeclineConfirmation"]');
                console.log('🔍 All decline buttons found:', allDeclineButtons.length);
                allDeclineButtons.forEach((btn, index) => {
                    console.log(`Button ${index}:`, btn.onclick?.toString() || btn.getAttribute('onclick'));
                });
            }
            
            console.log('🔍 External decline button found:', externalDeclineBtn);
            
            let originalExternalText = '';
            if (externalDeclineBtn) {
                originalExternalText = externalDeclineBtn.innerHTML;
                externalDeclineBtn.innerHTML = '<?php echo svg_icon('spinner', 'w-4 h-4 animate-spin'); ?> Declining...';
                externalDeclineBtn.disabled = true;
                externalDeclineBtn.classList.add('opacity-75');
                console.log('✅ External decline button set to loading state');
            } else {
                console.warn('⚠️ External decline button not found - will continue without loading state');
            }

            // Show loading on the modal confirm button
            const confirmBtn = document.querySelector('#declineModal button[onclick="confirmDecline()"]');
            if (confirmBtn) {
                const originalText = confirmBtn.innerHTML;
                confirmBtn.innerHTML = '<?php echo svg_icon('spinner', 'w-4 h-4 animate-spin'); ?> Declining...';
                confirmBtn.disabled = true;
                
                // Restore button after action completes
                setTimeout(() => {
                    if (confirmBtn && document.body.contains(confirmBtn)) {
                        confirmBtn.innerHTML = originalText;
                        confirmBtn.disabled = false;
                    }
                }, 3000);
            }

            // Close the modal first
            closeDeclineModal();

            // Store external button reference for restoration after completion
            window.currentExternalDeclineBtn = externalDeclineBtn;
            window.originalExternalDeclineText = originalExternalText;
            
            // Add a timeout to restore button if API call takes too long
            const timeoutId = setTimeout(() => {
                console.warn('⚠️ API call timeout - restoring decline button');
                if (window.currentExternalDeclineBtn && window.originalExternalDeclineText) {
                    window.currentExternalDeclineBtn.innerHTML = window.originalExternalDeclineText;
                    window.currentExternalDeclineBtn.disabled = false;
                    window.currentExternalDeclineBtn.classList.remove('opacity-75');
                    window.currentExternalDeclineBtn = null;
                    window.originalExternalDeclineText = null;
                }
                showToast('Request timeout - please try again', 'error');
            }, 30000); // 30 second timeout
            
            // Store timeout ID for cleanup
            window.currentTimeoutId = timeoutId;

            // Call the status update directly with the decline reason
            updateReportStatus(pendingDeclineAction.collection, pendingDeclineAction.docId, 'Declined', declineReason);
        }

        // Function to refresh tab counts periodically for real-time updates
        function startRealTimeTabCountUpdates() {
            // Update tab counts every 10 seconds
            setInterval(async () => {
                try {
                    // Get fresh data from server
                    const formData = new FormData();
                    formData.append('api_action', 'get_tab_counts');
                    
                    const response = await fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    });
                    
                    if (response.ok) {
                        const result = await response.json();
                        if (result.success && result.data) {
                            // Update each category's tab counts
                            Object.keys(result.data).forEach(slug => {
                                const counts = result.data[slug];
                                updateTabCountsFromServer(slug, counts);
                            });
                        }
                    }
                } catch (error) {
                    console.log('Tab count refresh error (silent):', error);
                }
            }, 10000); // Every 10 seconds
        }

        // Function to update tab counts from server data
        function updateTabCountsFromServer(slug, counts) {
            const segmentedControl = document.querySelector(`.segmented[data-slug="${slug}"]`);
            if (!segmentedControl) return;
            
            const pendingTab = segmentedControl.querySelector('[data-tab="pending"] .tab-count');
            const approvedTab = segmentedControl.querySelector('[data-tab="approved"] .tab-count');
            const declinedTab = segmentedControl.querySelector('[data-tab="declined"] .tab-count');
            
            if (pendingTab && approvedTab && declinedTab) {
                // Only update if counts have changed to avoid unnecessary animations
                const currentPending = parseInt(pendingTab.textContent) || 0;
                const currentApproved = parseInt(approvedTab.textContent) || 0;
                const currentDeclined = parseInt(declinedTab.textContent) || 0;
                
                if (currentPending !== counts.pending) {
                    pendingTab.textContent = counts.pending;
                    pendingTab.classList.add('animate-pulse');
                    setTimeout(() => pendingTab.classList.remove('animate-pulse'), 1000);
                }
                
                if (currentApproved !== counts.approved) {
                    approvedTab.textContent = counts.approved;
                    approvedTab.classList.add('animate-pulse');
                    setTimeout(() => approvedTab.classList.remove('animate-pulse'), 1000);
                }
                
                if (currentDeclined !== counts.declined) {
                    declinedTab.textContent = counts.declined;
                    declinedTab.classList.add('animate-pulse');
                    setTimeout(() => declinedTab.classList.remove('animate-pulse'), 1000);
                }
            }
        }

        // Start real-time updates when page loads
        document.addEventListener('DOMContentLoaded', () => {
            // Delay to ensure all elements are loaded
            setTimeout(() => {
                startRealTimeTabCountUpdates();
            }, 2000);
        });

        // Live Support Chat Logic
        document.addEventListener('DOMContentLoaded', function() {
            const chatListSidebar = document.getElementById('chatListSidebar');
            const chatArea = document.getElementById('chatArea');
            const backToChatListBtn = document.getElementById('backToChatList');
            const chatList = document.getElementById('chatList');
            const messagesArea = document.getElementById('messagesArea');
            const messageForm = document.getElementById('messageForm');
            const messageInput = document.getElementById('messageInput');
            const chatSearch = document.getElementById('chatSearch');
            
            let currentChatId = null;
            let currentChatStatus = null;
            let chatPollInterval = null;
            let messagePollInterval = null;
            let latestChats = [];
            let lastFetchedMessages = [];
            let pendingMessages = [];

            // Mobile UI: Show Chat List
            function showChatList() {
                if (window.innerWidth < 768) {
                    chatListSidebar.classList.remove('-translate-x-full', 'hidden');
                    chatArea.classList.add('hidden');
                }
            }

            // Mobile UI: Show Chat Area
            function showChatArea() {
                if (window.innerWidth < 768) {
                    chatListSidebar.classList.add('hidden');
                    chatArea.classList.remove('hidden');
                }
            }

            if (backToChatListBtn) {
                backToChatListBtn.addEventListener('click', showChatList);
            }

            // Fetch Chats
            let isFetchingChats = false;
            const recentlyAcceptedChats = new Set();

            async function fetchChats() {
                if (isFetchingChats) return;
                isFetchingChats = true;
                try {
                    // Add timestamp to prevent caching
                    const response = await fetch('api/support_chat.php?action=get_chats&t=' + new Date().getTime());
                    const result = await response.json();
                    
                    if (Array.isArray(result.chats)) {
                        const normalizedChats = result.chats
                            .map(chat => {
                                const chatId = chat.id || chat._id || chat.userId || chat.user_id || chat.uid || '';
                                const lastMessageTime = chat.lastMessageTimestamp || chat.lastMessageTime || chat.timestamp || chat._created || null;
                                
                                // Override status if recently accepted
                                let status = chat.status;
                                if (recentlyAcceptedChats.has(chatId)) {
                                    status = 'active';
                                }

                                return {
                                    ...chat,
                                    id: chatId,
                                    status: status,
                                    lastMessageTime
                                };
                            })
                            .filter(chat => chat.id);

                        if (normalizedChats.length !== result.chats.length) {
                            console.warn('Some chats were missing IDs and were skipped.');
                        }

                        latestChats = normalizedChats;
                        renderChatList(normalizedChats);
                        return normalizedChats;
                    }
                } catch (error) {
                    console.error('Error fetching chats:', error);
                } finally {
                    isFetchingChats = false;
                }
                return latestChats;
            }

            // Render Chat List
            function renderChatList(chats) {
                chatList.innerHTML = '';
                if (chats.length === 0) {
                    chatList.innerHTML = '<div class="text-center py-8 text-slate-500 text-sm">No active chats or pending requests</div>';
                    return;
                }

                const pendingChats = chats.filter(c => !c.status || c.status === 'pending' || c.status === 'waiting');
                const activeChats = chats.filter(c => c.status === 'active');
                const endedChats = chats.filter(c => c.status === 'ended');

                if (pendingChats.length > 0) {
                    const pendingHeader = document.createElement('div');
                    pendingHeader.className = 'px-3 py-2 text-xs font-bold text-slate-500 uppercase tracking-wider';
                    pendingHeader.textContent = 'Pending Requests';
                    chatList.appendChild(pendingHeader);

                    pendingChats.forEach(chat => renderChatItem(chat));
                }

                if (activeChats.length > 0) {
                    const activeHeader = document.createElement('div');
                    activeHeader.className = 'px-3 py-2 text-xs font-bold text-slate-500 uppercase tracking-wider mt-2';
                    activeHeader.textContent = 'Active Conversations';
                    chatList.appendChild(activeHeader);

                    activeChats.forEach(chat => renderChatItem(chat));
                }

                if (endedChats.length > 0) {
                    const endedHeader = document.createElement('div');
                    endedHeader.className = 'px-3 py-2 text-xs font-bold text-slate-500 uppercase tracking-wider mt-2';
                    endedHeader.textContent = 'Past Conversations';
                    chatList.appendChild(endedHeader);

                    endedChats.forEach(chat => renderChatItem(chat));
                }
            }

            function renderChatItem(chat) {
                const chatId = chat.id || chat._id || chat.userId || chat.user_id || chat.uid || '';
                if (!chatId) {
                    console.warn('Skipping chat with no identifier', chat);
                    return;
                }

                const div = document.createElement('div');
                div.className = `p-3 rounded-xl cursor-pointer hover:bg-slate-100 transition-colors mb-1 ${currentChatId === chatId ? 'bg-sky-50 border-l-4 border-sky-500' : ''}`;
                div.onclick = () => loadChat({ ...chat, id: chatId });
                
                const lastMessage = chat.lastMessage ? (chat.lastMessage.length > 30 ? chat.lastMessage.substring(0, 30) + '...' : chat.lastMessage) : 'No messages yet';
                const time = chat.lastMessageTime ? new Date(chat.lastMessageTime).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : '';
                const isPending = !chat.status || chat.status === 'pending' || chat.status === 'waiting';
                const isEnded = chat.status === 'ended';
                
                const chatName = chat.userName || 'Unknown User';
                const chatInitials = chatName.substring(0, 2).toUpperCase();
                
                let avatarClass = 'bg-sky-100 text-sky-600';
                if (isPending) avatarClass = 'bg-amber-100 text-amber-600';
                if (isEnded) avatarClass = 'bg-slate-200 text-slate-500';

                div.innerHTML = `
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full ${avatarClass} flex items-center justify-center font-bold text-sm relative">
                            ${chatInitials}
                            ${isPending ? '<span class="absolute -top-1 -right-1 w-3 h-3 bg-amber-500 rounded-full border-2 border-white"></span>' : ''}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex justify-between items-start">
                                <h4 class="font-bold text-slate-800 text-sm truncate ${isEnded ? 'text-slate-500' : ''}">${chatName}</h4>
                                <span class="text-xs text-slate-400 whitespace-nowrap ml-2">${time}</span>
                            </div>
                            <p class="text-xs text-slate-500 truncate mt-0.5 ${isPending ? 'font-semibold text-slate-700' : ''}">${lastMessage}</p>
                            ${chat.unreadCount > 0 ? `<span class="inline-flex items-center justify-center px-2 py-0.5 mt-1 text-xs font-bold leading-none text-white bg-red-500 rounded-full">${chat.unreadCount}</span>` : ''}
                        </div>
                    </div>
                `;
                chatList.appendChild(div);
            }

            // Load Chat
            async function loadChat(chat) {
                const resolvedChatId = chat.id || chat._id || chat.userId || chat.user_id || chat.uid || '';
                if (!resolvedChatId) {
                    console.error('Cannot load chat without an identifier', chat);
                    return;
                }

                // Clear previous chat data if switching chats
                if (currentChatId !== resolvedChatId) {
                    pendingMessages = [];
                    lastFetchedMessages = [];
                    messagesArea.innerHTML = ''; // Clear visual area immediately
                }

                currentChatId = resolvedChatId;
                currentChatStatus = chat.status || 'pending';
                showChatArea();
                
                // Update Header
                const chatName = chat.userName || 'Unknown User';
                document.getElementById('chatUserName').textContent = chatName;
                document.getElementById('chatUserInitials').textContent = chatName.substring(0, 2).toUpperCase();
                document.getElementById('chatHeader').classList.remove('hidden');
                
                const statusEl = document.getElementById('chatUserStatus');
                const endChatBtn = document.getElementById('endChatBtn');
                
                if (currentChatStatus === 'pending' || currentChatStatus === 'waiting') {
                    statusEl.innerHTML = '<span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span> Pending Request';
                    if(endChatBtn) endChatBtn.classList.add('hidden');
                } else if (currentChatStatus === 'ended') {
                    statusEl.innerHTML = '<span class="w-2 h-2 rounded-full bg-slate-400"></span> Ended';
                    if(endChatBtn) endChatBtn.classList.add('hidden');
                } else {
                    statusEl.innerHTML = '<span class="w-2 h-2 rounded-full bg-emerald-500"></span> Active';
                    if(endChatBtn) endChatBtn.classList.remove('hidden');
                }

                // Handle Input Area based on status
                const inputArea = document.getElementById('messageInputArea');
                if (currentChatStatus === 'pending' || currentChatStatus === 'waiting') {
                    inputArea.classList.add('hidden');
                    // Show Accept Button in messages area
                    messagesArea.innerHTML = `
                        <div class="h-full flex flex-col items-center justify-center p-6 text-center">
                            <div class="w-16 h-16 bg-amber-100 rounded-full flex items-center justify-center mb-4">
                                <svg class="w-8 h-8 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <h3 class="text-lg font-bold text-slate-800 mb-2">New Chat Request</h3>
                            <p class="text-slate-500 mb-6 max-w-xs">This user is requesting live support. Accept the request to start messaging.</p>
                            ${chat.relatedReportId ? `<div class="mb-6 p-3 bg-slate-50 rounded-lg text-sm text-left w-full max-w-xs border border-slate-200">
                                <p class="font-bold text-slate-700 mb-1">Related Report:</p>
                                <p class="text-slate-600">Type: ${chat.relatedReportType || 'Report'}</p>
                                <p class="text-slate-600 text-xs mt-1">ID: ${chat.relatedReportId}</p>
                            </div>` : ''}
                            <button onclick="acceptChat('${resolvedChatId}', this)" class="bg-sky-600 hover:bg-sky-700 text-white px-6 py-3 rounded-xl font-bold shadow-lg shadow-sky-500/20 transition-all hover:scale-105 flex items-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Accept Chat Request
                            </button>
                        </div>
                    `;
                } else if (currentChatStatus === 'ended') {
                    inputArea.classList.add('hidden');
                    messagesArea.innerHTML = `
                        <div class="h-full flex flex-col items-center justify-center text-slate-400">
                            <p class="text-sm">This chat has ended.</p>
                        </div>
                    `;
                    // Load Messages (read-only)
                    await fetchMessages();
                } else {
                    inputArea.classList.remove('hidden');
                    messagesArea.innerHTML = `
                        <div class="h-full flex flex-col items-center justify-center text-slate-400">
                            <p class="text-sm">Loading conversation...</p>
                        </div>
                    `;
                    // Load Messages
                    await fetchMessages();
                    // Start polling messages
                    if (window.messagePollTimeout) clearTimeout(window.messagePollTimeout);
                    fetchMessages(); // Initial fetch, will trigger subsequent polls
                }
            }

            // End Chat Functions
            window.confirmEndChat = function() {
                showEndChatModal();
            };

            window.confirmEndChatAction = function() {
                console.log('confirmEndChatAction called');
                closeEndChatModal();
                if (typeof endChat === 'function') {
                    endChat();
                } else {
                    alert('Error: endChat function not found! Please refresh the page.');
                }
            };

            async function endChat() {
                console.log('endChat called, currentChatId:', currentChatId);
                if (!currentChatId) {
                    alert('Error: No active chat selected. Please refresh the page.');
                    return;
                }

                try {
                    const formData = new FormData();
                    formData.append('action', 'end_chat');
                    formData.append('chat_id', currentChatId);

                    const response = await fetch('api/support_chat.php', {
                        method: 'POST',
                        body: formData
                    });

                    const text = await response.text();
                    console.log('End chat response:', text); // Debug log
                    
                    let result;
                    try {
                        result = JSON.parse(text);
                    } catch (e) {
                        console.error('Invalid JSON response:', text);
                        // Show the actual text response in the alert for debugging
                        throw new Error('Server returned invalid response: ' + text.substring(0, 100));
                    }

                    if (result.success) {
                        // Update local status
                        currentChatStatus = 'ended';
                        
                        // Update UI
                        const statusEl = document.getElementById('chatUserStatus');
                        if (statusEl) statusEl.innerHTML = '<span class="w-2 h-2 rounded-full bg-slate-400"></span> Ended';
                        
                        const endChatBtn = document.getElementById('endChatBtn');
                        if(endChatBtn) endChatBtn.classList.add('hidden');
                        
                        const inputArea = document.getElementById('messageInputArea');
                        if (inputArea) inputArea.classList.add('hidden');
                        
                        // Refresh messages to show system message
                        await fetchMessages();
                        
                        // Refresh chat list to update status there too
                        if (typeof fetchChats === 'function') fetchChats();
                    } else {
                        alert('Failed to end chat: ' + (result.error || 'Unknown error'));
                    }
                } catch (error) {
                    console.error('Error ending chat:', error);
                    alert('An error occurred while ending the chat: ' + error.message);
                }
            }

            // Accept Chat Function (Global)
            window.acceptChat = async function(chatId, triggerBtn = null) {
                if (!chatId) {
                    alert('Chat ID is missing. Please refresh and try again.');
                    return;
                }

                const btn = triggerBtn || document.querySelector(`button[onclick*="${chatId}"]`) || document.querySelector('button[onclick^="acceptChat"]');
                let originalContent = '';
                
                try {
                    const formData = new FormData();
                    formData.append('chat_id', chatId);
                    
                    if(btn) {
                        // Store original content to restore on error
                        originalContent = btn.innerHTML;
                        btn.dataset.originalContent = originalContent;
                        btn.innerHTML = '<svg class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg> Accepting...';
                        btn.disabled = true;
                    }

                    // Add timeout to fetch
                    const controller = new AbortController();
                    const timeoutId = setTimeout(() => controller.abort(), 30000); // Increased to 30s timeout

                    const response = await fetch('api/support_chat.php?action=accept_chat', {
                        method: 'POST',
                        body: formData,
                        signal: controller.signal
                    });
                    
                    clearTimeout(timeoutId);
                    
                    // Check if response is ok
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    const text = await response.text();
                    let result;
                    try {
                        result = JSON.parse(text);
                    } catch (e) {
                        console.error('Invalid JSON response:', text);
                        throw new Error('Server returned invalid response');
                    }
                    
                    if (result.success) {
                        const chatName = document.getElementById('chatUserName').textContent || 'Resident';
                        
                        // Add to recently accepted set to prevent race conditions
                        recentlyAcceptedChats.add(chatId);
                        setTimeout(() => recentlyAcceptedChats.delete(chatId), 15000); // Keep for 15 seconds

                        // Optimistically update UI immediately
                        const optimisticChat = { 
                            id: chatId, 
                            userName: chatName, 
                            status: 'active',
                            lastMessageTime: new Date()
                        };
                        
                        // Update local cache if exists
                        if (Array.isArray(latestChats)) {
                            const existingIndex = latestChats.findIndex(c => c.id === chatId);
                            if (existingIndex !== -1) {
                                latestChats[existingIndex] = { ...latestChats[existingIndex], ...optimisticChat };
                            }
                        }

                        // Force load chat with active status
                        await loadChat(optimisticChat);

                        if (btn) {
                            btn.innerHTML = 'Chat Accepted';
                            btn.disabled = true;
                        }
                        
                        // Fetch latest data in background
                        fetchChats();
                    } else {
                        throw new Error(result.error || 'Unknown error');
                    }
                } catch (error) {
                    console.error('Error accepting chat:', error);
                    
                    // Handle AbortError or Timeout specifically
                    if (error.name === 'AbortError' || error.message.includes('aborted')) {
                        console.log('Request timed out or aborted, checking if chat was accepted anyway...');
                        // Check if the chat status actually changed
                        try {
                            const chats = await fetchChats();
                            const updatedChat = Array.isArray(chats) ? chats.find(c => c.id === chatId) : null;
                            
                            if (updatedChat && updatedChat.status === 'active') {
                                console.log('Chat was accepted despite timeout');
                                await loadChat(updatedChat);
                                if (btn) {
                                    btn.innerHTML = 'Chat Accepted';
                                    btn.disabled = true;
                                }
                                return; // Exit successfully
                            }
                        } catch (checkError) {
                            console.error('Failed to verify chat status:', checkError);
                        }
                        alert('Request timed out. Please check your internet connection and try again.');
                    } else {
                        alert('Failed to accept chat: ' + error.message);
                    }

                    // Restore button if failed
                    if(btn && originalContent && !btn.disabled) { // Only restore if we didn't succeed above
                        btn.innerHTML = originalContent;
                        btn.disabled = false;
                    }
                }
            };

            // Fetch Messages
            async function fetchMessages() {
                if (!currentChatId || (currentChatStatus === 'pending' || currentChatStatus === 'waiting')) return;
                
                try {
                    const response = await fetch(`api/support_chat.php?action=get_messages&chat_id=${currentChatId}`);
                    const result = await response.json();
                    
                    if (result.messages) {
                        lastFetchedMessages = result.messages;
                        renderMessages(lastFetchedMessages);
                    } else if (result.error) {
                        console.error('API Error:', result.error);
                        // Only show error if we don't have messages yet
                        if (messagesArea.innerHTML.includes('Loading conversation...')) {
                            messagesArea.innerHTML = `
                                <div class="h-full flex flex-col items-center justify-center text-red-400">
                                    <svg class="w-8 h-8 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    <p class="text-sm">${result.error}</p>
                                </div>
                            `;
                        }
                    }
                } catch (error) {
                    console.error('Error fetching messages:', error);
                    // Don't show error UI for transient network errors during polling, just log it
                } finally {
                    // Schedule next poll if still active
                    if (currentChatId && currentChatStatus === 'active') {
                        window.messagePollTimeout = setTimeout(fetchMessages, 1000);
                    }
                }
            }

            // Render Messages
            function renderMessages(messages) {
                messagesArea.innerHTML = '';
                
                // Combine fetched messages with pending messages
                const allMessages = [...messages, ...pendingMessages];

                if (allMessages.length === 0) {
                    messagesArea.innerHTML = `
                        <div class="h-full flex flex-col items-center justify-center text-slate-400">
                            <p class="text-sm">No messages yet. Start the conversation!</p>
                        </div>
                    `;
                    return;
                }

                let lastDate = null;
                
                allMessages.forEach(msg => {
                    // Handle timestamp: Firestore timestamp or ISO string or JS Date
                    let dateObj;
                    if (msg.timestamp instanceof Date) {
                        dateObj = msg.timestamp;
                    } else if (msg.timestamp && msg.timestamp.seconds) {
                        dateObj = new Date(msg.timestamp.seconds * 1000);
                    } else {
                        dateObj = new Date(msg.timestamp);
                    }
                    
                    const date = dateObj.toLocaleDateString();
                    if (date !== lastDate) {
                        const dateDiv = document.createElement('div');
                        dateDiv.className = 'flex justify-center my-4';
                        dateDiv.innerHTML = `<span class="bg-slate-100 text-slate-500 text-xs px-3 py-1 rounded-full">${date}</span>`;
                        messagesArea.appendChild(dateDiv);
                        lastDate = date;
                    }

                    const isMe = msg.senderId === '<?php echo $_SESSION['user_id'] ?? ''; ?>';
                    const isSystem = msg.isSystem === true;
                    const isPending = msg.isPending === true;
                    
                    const div = document.createElement('div');
                    
                    if (isSystem) {
                        div.className = 'flex justify-center mb-4';
                        div.innerHTML = `<span class="text-xs text-slate-400 italic bg-slate-50 px-3 py-1 rounded-full">${msg.text}</span>`;
                    } else {
                        div.className = `flex ${isMe ? 'justify-end' : 'justify-start'} mb-4 ${isPending ? 'opacity-70' : ''}`;
                        div.innerHTML = `
                            <div class="max-w-[75%] ${isMe ? 'bg-sky-600 text-white rounded-l-2xl rounded-tr-2xl' : 'bg-white border border-slate-200 text-slate-800 rounded-r-2xl rounded-tl-2xl'} p-3 shadow-sm relative group">
                                <p class="text-sm leading-relaxed">${msg.text || msg.message}</p>
                                <span class="text-[10px] ${isMe ? 'text-sky-100' : 'text-slate-400'} block text-right mt-1 opacity-70 flex items-center justify-end gap-1">
                                    ${dateObj.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}
                                    ${isPending ? '<svg class="w-3 h-3 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>' : ''}
                                </span>
                            </div>
                        `;
                    }
                    messagesArea.appendChild(div);
                });
                
                messagesArea.scrollTop = messagesArea.scrollHeight;
            }

            // Send Message
            if (messageForm) {
                messageForm.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const message = messageInput.value.trim();
                    if (!message || !currentChatId) return;
                    
                    // Optimistic Update
                    const tempId = 'temp-' + Date.now();
                    const optimisticMsg = {
                        text: message,
                        senderId: '<?php echo $_SESSION['user_id'] ?? ''; ?>',
                        timestamp: new Date(),
                        isPending: true,
                        id: tempId
                    };
                    
                    pendingMessages.push(optimisticMsg);
                    messageInput.value = '';
                    renderMessages(lastFetchedMessages); // Re-render with pending message

                    try {
                        const formData = new FormData();
                        formData.append('chat_id', currentChatId);
                        formData.append('text', message);
                        
                        const response = await fetch('api/support_chat.php?action=send_message', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const result = await response.json();
                        if (result.success) {
                            // Remove from pending
                            pendingMessages = pendingMessages.filter(m => m.id !== tempId);
                            fetchMessages();
                            fetchChats(); // Update last message in list
                        } else {
                            console.error('Failed to send message');
                            pendingMessages = pendingMessages.filter(m => m.id !== tempId);
                            renderMessages(lastFetchedMessages);
                            alert('Failed to send message');
                        }
                    } catch (error) {
                        console.error('Error sending message:', error);
                        pendingMessages = pendingMessages.filter(m => m.id !== tempId);
                        renderMessages(lastFetchedMessages);
                        alert('Error sending message');
                    }
                });
                
                // Enter to send
                messageInput.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        messageForm.dispatchEvent(new Event('submit'));
                    }
                });
            }

            // Initial Load
            if (document.getElementById('chatList')) {
                fetchChats();
                chatPollInterval = setInterval(fetchChats, 5000);
            }
        });

        // Updated confirmation functions to use modals instead of browser confirm
        window.showApproveConfirmation = function(collection, docId, reporterName, categoryType) {
            console.log('showApproveConfirmation called:', { collection, docId, reporterName, categoryType });
            showApproveModal(collection, docId, reporterName, categoryType);
        }

        window.showDeclineConfirmation = function(collection, docId, reporterName, categoryType) {
            console.log('showDeclineConfirmation called:', { collection, docId, reporterName, categoryType });
            showDeclineModal(collection, docId, reporterName, categoryType);
        }

        // Make modal functions globally accessible
        window.showApproveModal = showApproveModal;
        window.closeApproveModal = closeApproveModal;
        window.confirmApprove = confirmApprove;
        window.showDeclineModal = showDeclineModal;
        window.closeDeclineModal = closeDeclineModal;
        window.confirmDecline = confirmDecline;
        window.updateReportStatus = updateReportStatus;
    </script>
    <script>
        // Live Support Badge Logic
        async function updateLiveSupportBadge() {
            try {
                const response = await fetch('api/support_chat.php?action=get_chats');
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const result = await response.json();
                
                if (Array.isArray(result.chats)) {
                    // Count pending chats
                    const pendingCount = result.chats.filter(c => !c.status || c.status === 'pending' || c.status === 'waiting').length;
                    
                    const badgeDesktop = document.getElementById('liveSupportBadge');
                    const badgeMobile = document.getElementById('liveSupportBadgeMobile');
                    
                    if (pendingCount > 0) {
                        if (badgeDesktop) {
                            badgeDesktop.textContent = pendingCount;
                            badgeDesktop.classList.remove('hidden');
                            badgeDesktop.style.display = 'inline-flex'; // Force display
                        }
                        if (badgeMobile) {
                            badgeMobile.textContent = pendingCount;
                            badgeMobile.classList.remove('hidden');
                            badgeMobile.style.display = 'inline-flex'; // Force display
                        }
                    } else {
                        if (badgeDesktop) {
                            badgeDesktop.classList.add('hidden');
                            badgeDesktop.style.display = 'none'; // Force hide
                        }
                        if (badgeMobile) {
                            badgeMobile.classList.add('hidden');
                            badgeMobile.style.display = 'none'; // Force hide
                        }
                    }
                }
            } catch (error) {
                console.error('Error updating live support badge:', error);
            }
        }

        // Start polling for badge updates
        (function() {
            const startPolling = () => {
                updateLiveSupportBadge();
                setInterval(updateLiveSupportBadge, 5000); // Poll every 5 seconds
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', startPolling);
            } else {
                startPolling();
            }
        })();
    </script>
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
                                <h3 class="text-lg font-bold leading-6 text-slate-900" id="modal-title">End Chat Session</h3>
                                <div class="mt-2">
                                    <p class="text-sm text-slate-500">Are you sure you want to end this chat session? This action cannot be undone and the user will be notified.</p>
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

    <script>
        // End Chat Modal Functions
        function showEndChatModal() {
            const modal = document.getElementById('endChatModal');
            const backdrop = document.getElementById('endChatModalBackdrop');
            const panel = document.getElementById('endChatModalPanel');
            
            if (modal && backdrop && panel) {
                modal.classList.remove('hidden');
                // Trigger reflow
                void modal.offsetWidth;
                
                // Animate in
                backdrop.classList.remove('opacity-0');
                panel.classList.remove('opacity-0', 'translate-y-4', 'sm:translate-y-0', 'sm:scale-95');
                panel.classList.add('opacity-100', 'translate-y-0', 'sm:scale-100');
            }
        }

        function closeEndChatModal() {
            const modal = document.getElementById('endChatModal');
            const backdrop = document.getElementById('endChatModalBackdrop');
            const panel = document.getElementById('endChatModalPanel');
            
            if (modal && backdrop && panel) {
                // Animate out
                backdrop.classList.add('opacity-0');
                panel.classList.remove('opacity-100', 'translate-y-0', 'sm:scale-100');
                panel.classList.add('opacity-0', 'translate-y-4', 'sm:translate-y-0', 'sm:scale-95');
                
                setTimeout(() => {
                    modal.classList.add('hidden');
                }, 300);
            }
        }
    </script>
    <script>
        // Verify Users Badge Logic
        async function updateVerifyUsersBadge() {
            try {
                const response = await fetch('api/get_pending_users_count.php');
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const result = await response.json();
                
                const count = result.count || 0;
                
                const badgeDesktop = document.getElementById('verifyUsersBadge');
                const badgeMobile = document.getElementById('verifyUsersBadgeMobile');
                
                if (count > 0) {
                    if (badgeDesktop) {
                        badgeDesktop.textContent = count;
                        badgeDesktop.classList.remove('hidden');
                        badgeDesktop.style.display = 'inline-flex';
                    }
                    if (badgeMobile) {
                        badgeMobile.textContent = count;
                        badgeMobile.classList.remove('hidden');
                        badgeMobile.style.display = 'inline-flex';
                    }
                } else {
                    if (badgeDesktop) {
                        badgeDesktop.classList.add('hidden');
                        badgeDesktop.style.display = 'none';
                    }
                    if (badgeMobile) {
                        badgeMobile.classList.add('hidden');
                        badgeMobile.style.display = 'none';
                    }
                }
            } catch (error) {
                console.error('Error updating verify users badge:', error);
            }
        }

        // Start polling for verify users badge updates
        (function() {
            const startPolling = () => {
                updateVerifyUsersBadge();
                setInterval(updateVerifyUsersBadge, 10000); // Poll every 10 seconds
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', startPolling);
            } else {
                startPolling();
            }
        })();
    </script>
    
    <!-- Dashboard Core JS for realtime polling -->
    <script src="assets/js/dashboard-core.js?v=<?php echo time(); ?>"></script>
    <?php if (($_GET['view'] ?? 'dashboard') === 'map'): ?>
    <script src="assets/js/map-dashboard.js?v=<?php echo filemtime(__DIR__ . '/assets/js/map-dashboard.js'); ?>"></script>
    <?php endif; ?>
    <?php if (($_GET['view'] ?? 'dashboard') === 'analytics'): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="assets/js/analytics.js?v=<?php echo filemtime(__DIR__ . '/assets/js/analytics.js'); ?>"></script>
    <?php endif; ?>
</body>
</html>

<?php
if (defined('DEBUG_MODE') && DEBUG_MODE) {
    $ms = round((microtime(true) - ($__reqStart ?? microtime(true))) * 1000, 2);
    $v = $_GET['view'] ?? 'dashboard';
    error_log('[perf] dashboard.php end view=' . $v . ' ms=' . $ms . ' sid=' . session_id());
}
?>