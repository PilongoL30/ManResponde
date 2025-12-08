<?php
require_once dirname(__DIR__) . '/db_config.php';
session_start();
header('Content-Type: application/json; charset=utf-8');
// Strong no-cache headers

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$userId   = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? 'staff';
$isAdmin  = ($userRole === 'admin');

function get_user_profile_min(string $uid): array {
    if (function_exists('firestore_get_doc_by_id')) {
        try { return firestore_get_doc_by_id('users', $uid) ?? []; } catch (Throwable $e) {}
    }
    return [];
}

function rest_list_documents_min(string $collection, int $pageSize = 200): array {
    if (!function_exists('firestore_rest_request') || !function_exists('firestore_base_url')) return [];
    $url = firestore_base_url().'/'.rawurlencode($collection).'?pageSize='.$pageSize;
    try {
        $res = firestore_rest_request('GET', $url);
        $docs = $res['documents'] ?? [];
        return is_array($docs) ? $docs : [];
    } catch (Throwable $e) { return []; }
}

function latest_reports_min(string $collection, int $limit = 200): array {
    $items = [];
    if (function_exists('firestore_query_latest')) {
        try {
            $docs = firestore_query_latest($collection, $limit);
            foreach ($docs as $d) {
                $items[] = [
                    'id'        => $d['_id'] ?? '',
                    'fullName'  => $d['fullName'] ?? '',
                    'contact'   => $d['contact'] ?? '',
                    'location'  => $d['location'] ?? '',
                    'purpose'   => $d['purpose'] ?? '',
                    'status'    => $d['status'] ?? '',
                    'imageUrl'  => $d['imageUrl'] ?? '',
                    'timestamp' => $d['timestamp'] ?? null,
                    'reporterId'=> $d['reporterId'] ?? '',
                    '_created'  => $d['_created'] ?? null,
                    'latitude'  => $d['latitude'] ?? null,
                    'longitude' => $d['longitude'] ?? null,
                    'coordinates' => $d['coordinates'] ?? null,
                ];
            }
        } catch (Throwable $e) {}
    }
    if (count($items) < $limit) {
        $raw = rest_list_documents_min($collection, 200);
        foreach ($raw as $doc) {
            if (!isset($doc['name'])) continue;
            $parts  = explode('/', $doc['name']);
            $id     = end($parts);
            $fields = isset($doc['fields']) && function_exists('firestore_decode_fields')
                ? firestore_decode_fields($doc['fields'])
                : [];
            $items[] = [
                'id'        => $id,
                'fullName'  => $fields['fullName'] ?? '',
                'contact'   => $fields['contact'] ?? '',
                'location'  => $fields['location'] ?? '',
                'purpose'   => $fields['purpose'] ?? '',
                'status'    => $fields['status'] ?? '',
                'imageUrl'  => $fields['imageUrl'] ?? '',
                'timestamp' => $fields['timestamp'] ?? ($doc['createTime'] ?? null),
                'reporterId'=> $fields['reporterId'] ?? '',
                '_created'  => $doc['createTime'] ?? null,
                'latitude'  => $fields['latitude'] ?? null,
                'longitude' => $fields['longitude'] ?? null,
                'coordinates' => $fields['coordinates'] ?? null,
            ];
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
    return $items;
}

$categories = [
    'ambulance' => ['label' => 'Ambulance', 'collection' => 'ambulance_reports'],
    'police'    => ['label' => 'Police',    'collection' => 'police_reports'],
    'tanod'     => ['label' => 'Tanod',     'collection' => 'tanod_reports'],
    'fire'      => ['label' => 'Fire',      'collection' => 'fire_reports'],
    'flood'     => ['label' => 'Flood',     'collection' => 'flood_reports'],
    'other'     => ['label' => 'Other',     'collection' => 'other_reports'],
];

$collection = trim($_GET['collection'] ?? '');
if ($collection === '') {
    http_response_code(400);
    echo json_encode(['error' => 'missing collection']);
    exit;
}

$allowed = [];
if ($isAdmin) {
    foreach ($categories as $meta) $allowed[] = $meta['collection'];
} else {
    $profile = get_user_profile_min($userId);
    $assignedSlugs = array_values(array_filter(array_map('strval', $profile['categories'] ?? [])));
    foreach ($assignedSlugs as $slug) {
        if (isset($categories[$slug]['collection'])) $allowed[] = $categories[$slug]['collection'];
    }
}

if (!in_array($collection, $allowed, true)) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

try {
    $items = latest_reports_min($collection, 200);
    echo json_encode(['items' => $items]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'server', 'message' => $e->getMessage()]);
}