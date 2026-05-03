<?php
ob_start();

// Prevent browser caching to ensure latest analytics data
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/includes/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CSRF Protection for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $skipCsrf = ['firebase_webhook', 'external_api'];
    $action = $_POST['api_action'] ?? $_GET['action'] ?? '';

    if (!in_array($action, $skipCsrf, true)) {
        if (!csrf_verify_token()) {
            http_response_code(403);
            if (!empty($_POST['api_action']) || isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'CSRF token validation failed. Please refresh the page and try again.',
                    'action' => $action
                ]);
                exit;
            }
            die('CSRF token validation failed. Please go back and try again.');
        }
    }
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$userId   = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? 'staff';
$userName = $_SESSION['user_fullname'] ?? 'User';
$isAdmin  = ($userRole === 'admin');

$userCategories = $_SESSION['user_categories'] ?? [];
if (!is_array($userCategories)) $userCategories = [];

$userProfile = ['categories' => $userCategories, 'assignedBarangay' => ($_SESSION['assignedBarangay'] ?? '')];
if (empty($userCategories)) {
    $userProfile = get_user_profile($userId);
    $userCategories = $userProfile['categories'] ?? [];
    if (is_array($userCategories)) {
        $_SESSION['user_categories'] = $userCategories;
    }
}

$isTanod = false;
if (!empty($userCategories)) {
    foreach ($userCategories as $cat) {
        if (strtolower((string)$cat) === 'tanod') {
            $isTanod = true;
            break;
        }
    }
}

$view = 'analytics';

function get_user_profile(string $uid): array {
    if (session_status() !== PHP_SESSION_NONE) {
        $k = '__user_profile_' . $uid;
        $kt = $k . '_time';
        if (isset($_SESSION[$k], $_SESSION[$kt]) && (time() - (int)$_SESSION[$kt]) < 300) {
            return is_array($_SESSION[$k]) ? $_SESSION[$k] : [];
        }
    }

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
        } catch (Throwable $e) {
        }
    }

    return [];
}

// Category configuration (shared with dashboard)
$categories = [
    'ambulance' => ['label' => 'Ambulance', 'collection' => 'ambulance_reports', 'icon' => 'truck', 'color' => 'blue'],
    'police'    => ['label' => 'Police',    'collection' => 'police_reports',    'icon' => 'user-shield', 'color' => 'slate'],
    'tanod'     => ['label' => 'Tanod',     'collection' => 'tanod_reports',     'icon' => 'shield-check', 'color' => 'sky'],
    'fire'      => ['label' => 'Fire',      'collection' => 'fire_reports',      'icon' => 'fire', 'color' => 'red'],
    'flood'     => ['label' => 'Flood',     'collection' => 'flood_reports',     'icon' => 'home', 'color' => 'indigo'],
    'other'     => ['label' => 'Other',     'collection' => 'other_reports',     'icon' => 'question-mark-circle', 'color' => 'gray'],
];

// AJAX: Analytics charts + metrics (cached, minimal data)
$action = $_POST['api_action'] ?? ($_GET['action'] ?? '');
if ($action === 'get_analytics_data') {
    $startTime = microtime(true);
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');

    try {
        $range = strtolower(trim((string)($_POST['range'] ?? 'week')));
        $rangeAliases = ['today' => 'day'];
        if (isset($rangeAliases[$range])) {
            $range = $rangeAliases[$range];
        }
        $allowedRanges = ['day', 'week', 'month', 'year', 'all'];
        if (!in_array($range, $allowedRanges, true)) {
            $range = 'week';
        }

        if ($isAdmin) {
            $allowedSlugs = array_keys($categories);
        } else {
            $allowedSlugs = array_values(array_filter(array_map('strval', $_SESSION['user_categories'] ?? [])));
            $allowedSlugs = array_values(array_filter($allowedSlugs, fn($s) => isset($categories[$s])));

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
            $cached = cache_get($cacheKey, 300); // 5 minute cache (was 120s)
            if (is_array($cached)) {
                $cached['executionTime'] = round((microtime(true) - $startTime) * 1000, 2) . 'ms';
                $cached['cached'] = true;
                echo json_encode($cached);
                exit();
            }
        }

        $collections = [];
        foreach ($allowedSlugs as $slug) {
            $collections[] = $categories[$slug]['collection'];
        }

        // Calculate time range for filtering
        $startTimeStr = null;
        $endTimeStr = null;
        if ($range !== 'all') {
            $now = time();
            $start = $now;
            if ($range === 'day') {
                $start = strtotime('today midnight');
            } elseif ($range === 'week') {
                $start = strtotime('-7 days midnight');
            } elseif ($range === 'month') {
                $start = strtotime('-30 days midnight');
            } elseif ($range === 'year') {
                $start = strtotime('-1 year midnight');
            }
            $startTimeStr = date('Y-m-d\T00:00:00\Z', $start);
            // $endTimeStr is usually now, but we can set it to end of today
            $endTimeStr = date('Y-m-d\T23:59:59\Z', $now);
        }

        if (empty($collections)) {
            $countResults = [];
        } else {
            $countResults = function_exists('get_admin_stats_counts_fast')
                ? get_admin_stats_counts_fast($collections, $startTimeStr, $endTimeStr)
                : get_admin_stats_counts_fallback($collections, $startTimeStr, $endTimeStr);

            $hasAnyTotal = false;
            foreach ($collections as $colCheck) {
                if ((int)($countResults[$colCheck]['total'] ?? 0) > 0) {
                    $hasAnyTotal = true;
                    break;
                }
            }
            if (!$hasAnyTotal) {
                // If fast counts return nothing, try fallback one more time without filters if range is 'all'
                if ($range === 'all') {
                    $countResults = get_admin_stats_counts_fallback($collections);
                }
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

        $sampleLimit = ($range === 'month') ? 100 : (($range === 'year' || $range === 'all') ? 200 : 50);
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
                // Use runQuery to support filtering by timestamp
                $url = $base . ':runQuery';
                
                $filters = [];
                if ($startTimeStr) {
                    $filters[] = [
                        'fieldFilter' => [
                            'field' => ['fieldPath' => 'timestamp'],
                            'op' => 'GREATER_THAN_OR_EQUAL',
                            'value' => ['timestampValue' => $startTimeStr]
                        ]
                    ];
                }
                if ($endTimeStr) {
                    $filters[] = [
                        'fieldFilter' => [
                            'field' => ['fieldPath' => 'timestamp'],
                            'op' => 'LESS_THAN_OR_EQUAL',
                            'value' => ['timestampValue' => $endTimeStr]
                        ]
                    ];
                }

                $structuredQuery = [
                    'from' => [['collectionId' => $col]],
                    'limit' => (int)$sampleLimit,
                    'orderBy' => [['field' => ['fieldPath' => 'timestamp'], 'direction' => 'DESCENDING']]
                ];

                if (count($filters) === 1) {
                    $structuredQuery['where'] = $filters[0];
                } elseif (count($filters) > 1) {
                    $structuredQuery['where'] = [
                        'compositeFilter' => [
                            'op' => 'AND',
                            'filters' => $filters
                        ]
                    ];
                }

                $body = ['structuredQuery' => $structuredQuery];
                
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $url,
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
                // runQuery returns a list of objects, each containing a 'document' key
                $results = is_array($json) ? $json : [];

                foreach ($results as $result) {
                    if (!isset($result['document'])) continue;
                    $doc = $result['document'];
                    
                    $kRaw = $parseDateKey($doc['createTime'] ?? null);
                    $k = null;
                    if ($kRaw !== null) {
                        $k = ($bucketMode === 'month') ? substr($kRaw, 0, 7) : $kRaw;
                    }
                    if ($k !== null && isset($trendCounts[$k])) {
                        $trendCounts[$k]++;
                    }

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
        }
        $trendData = array_values($trendCounts);

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

        cache_set($cacheKey, $payload, 300);
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

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ManResponde • Analytics</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php echo csrf_meta(); ?>

    <link rel="icon" type="image/png" sizes="32x32" href="responde.png">
    <link rel="icon" type="image/png" sizes="16x16" href="responde.png">
    <link rel="apple-touch-icon" href="responde.png">
    <link rel="shortcut icon" href="responde.png">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/custom.css?v=<?php echo filemtime(__DIR__ . '/assets/css/custom.css'); ?>">
    <script src="assets/js/common-modals.js?v=<?php echo filemtime(__DIR__ . '/assets/js/common-modals.js'); ?>"></script>
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
                <a href="verify_users.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?php echo $isVerifyUsersActive ? 'bg-sky-100 text-sky-700 font-semibold' : 'hover:bg-slate-50 text-slate-600'; ?>">
                    <?php echo svg_icon('user-check', 'w-5 h-5'); ?>
                    <span>Verify Users</span>
                    <span id="verifyUsersBadge" class="ml-auto bg-amber-500 text-white text-xs font-bold px-2 py-0.5 rounded-full hidden">0</span>
                </a>
                <?php endif; ?>

                <?php if ($isAdmin): ?>
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
                <div class="w-10"></div>
            </div>
        </div>

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
                <?php include __DIR__ . '/includes/analytics_view.php'; ?>
            </div>
        </main>
    </div>

    <?php if ($isAdmin) include __DIR__ . '/includes/modals_dashboard.php'; ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const mobileMenuOverlay = document.getElementById('mobileMenuOverlay');
            
            if (mobileMenuBtn && mobileMenuOverlay) {
                mobileMenuBtn.addEventListener('click', function() {
                    mobileMenuOverlay.classList.toggle('hidden');
                });
                
                mobileMenuOverlay.addEventListener('click', function(e) {
                    if (e.target === mobileMenuOverlay) {
                        mobileMenuOverlay.classList.add('hidden');
                    }
                });
                
                const mobileNavLinks = mobileMenuOverlay.querySelectorAll('a');
                mobileNavLinks.forEach(link => {
                    link.addEventListener('click', function() {
                        mobileMenuOverlay.classList.add('hidden');
                    });
                });
                
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && !mobileMenuOverlay.classList.contains('hidden')) {
                        mobileMenuOverlay.classList.add('hidden');
                    }
                });
            }
            
            window.closeMobileSidebar = function() {
                const mobileMenuOverlay = document.getElementById('mobileMenuOverlay');
                if (mobileMenuOverlay) {
                    mobileMenuOverlay.classList.add('hidden');
                }
            };
        });

        window.showToast = window.showToast || function(message, type = 'info') {
            const toast = document.createElement('div');
            const colors = {
                success: 'bg-emerald-600',
                error: 'bg-rose-600',
                info: 'bg-slate-800'
            };
            toast.className = `fixed bottom-6 right-6 z-50 text-white px-4 py-3 rounded-lg shadow-lg text-sm ${colors[type] || colors.info}`;
            toast.textContent = message;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        };
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="assets/js/analytics.js?v=<?php echo filemtime(__DIR__ . '/assets/js/analytics.js'); ?>"></script>
</body>
</html>
