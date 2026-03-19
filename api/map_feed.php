<?php
require_once dirname(__DIR__) . '/db_config.php';
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, max-age=2');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$userId   = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? 'staff';
$isAdmin  = ($userRole === 'admin');

function get_user_profile_min_map(string $uid): array {
    if (function_exists('firestore_get_doc_by_id')) {
        try { return firestore_get_doc_by_id('users', $uid) ?? []; } catch (Throwable $e) {}
    }
    return [];
}

$categories = [
    'ambulance' => ['label' => 'Ambulance', 'collection' => 'ambulance_reports'],
    'police'    => ['label' => 'Police',    'collection' => 'police_reports'],
    'tanod'     => ['label' => 'Tanod',     'collection' => 'tanod_reports'],
    'fire'      => ['label' => 'Fire',      'collection' => 'fire_reports'],
    'flood'     => ['label' => 'Flood',     'collection' => 'flood_reports'],
    'other'     => ['label' => 'Other',     'collection' => 'other_reports'],
];

$limit = (int)($_GET['limit'] ?? 40);
$limit = max(10, min(150, $limit));

$allowedSlugs = [];
if ($isAdmin) {
    $allowedSlugs = array_keys($categories);
} else {
    $assignedSlugs = $_SESSION['user_categories'] ?? null;
    if (!is_array($assignedSlugs) || empty($assignedSlugs)) {
        $profile = get_user_profile_min_map($userId);
        $assignedSlugs = array_values(array_filter(array_map('strval', $profile['categories'] ?? [])));
        if (!empty($assignedSlugs)) {
            $_SESSION['user_categories'] = $assignedSlugs;
        }
    }
    foreach ((array)$assignedSlugs as $slug) {
        if (isset($categories[$slug])) $allowedSlugs[] = $slug;
    }
}
$allowedSlugs = array_values(array_unique($allowedSlugs));
sort($allowedSlugs);

try {
    $cacheKey = 'api_map_feed_' . ($isAdmin ? 'admin' : 'staff') . '_' . md5(implode(',', $allowedSlugs)) . '_' . $limit;
    $cached = cache_get($cacheKey, 3);
    if (is_array($cached)) {
        echo json_encode(['items' => $cached, 'cached' => true]);
        exit;
    }

    if (empty($allowedSlugs)) {
        echo json_encode(['items' => [], 'cached' => false]);
        exit;
    }

    $token = firestore_rest_token();
    $base  = firestore_base_url();

    $mh = curl_multi_init();
    $handles = [];
    $map = [];

    foreach ($allowedSlugs as $slug) {
        $collection = $categories[$slug]['collection'];
        $url = $base . '/' . rawurlencode($collection) . '?pageSize=' . $limit;

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
        $map[(int)$ch] = ['slug' => $slug, 'collection' => $collection];
    }

    $running = null;
    do {
        curl_multi_exec($mh, $running);
        curl_multi_select($mh, 0.2);
    } while ($running > 0);

    $items = [];
    foreach ($handles as $ch) {
        $info = $map[(int)$ch] ?? null;
        $raw  = curl_multi_getcontent($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);

        if (!$info || $http < 200 || $http >= 300) continue;

        $json = json_decode($raw ?: 'null', true);
        $docs = is_array($json) ? ($json['documents'] ?? []) : [];
        if (!is_array($docs)) continue;

        foreach ($docs as $doc) {
            if (!isset($doc['name'])) continue;
            $id = basename($doc['name']);
            $fields = isset($doc['fields']) && function_exists('firestore_decode_fields')
                ? firestore_decode_fields($doc['fields'])
                : [];
            if (!is_array($fields)) $fields = [];

            $items[] = [
                'id' => $id,
                'category' => $info['slug'],
                'fullName' => $fields['fullName'] ?? $fields['reporterName'] ?? '',
                'contact' => $fields['contact'] ?? $fields['reporterContact'] ?? '',
                'location' => $fields['location'] ?? '',
                'purpose' => $fields['purpose'] ?? $fields['description'] ?? '',
                'status' => $fields['status'] ?? '',
                'imageUrl' => $fields['imageUrl'] ?? '',
                'timestamp' => $fields['timestamp'] ?? ($fields['createdAt'] ?? ($doc['createTime'] ?? null)),
                '_created' => $doc['createTime'] ?? null,
                'latitude' => $fields['latitude'] ?? null,
                'longitude' => $fields['longitude'] ?? null,
                'coordinates' => $fields['coordinates'] ?? null,
            ];
        }
    }

    curl_multi_close($mh);

    cache_set($cacheKey, $items, 3);
    echo json_encode(['items' => $items, 'cached' => false]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'server', 'message' => $e->getMessage()]);
}
