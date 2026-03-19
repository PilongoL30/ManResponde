<?php
require_once dirname(__DIR__) . '/db_config.php';
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'unauthorized']);
    exit;
}

$userRole = $_SESSION['user_role'] ?? 'staff';
$isAdmin  = ($userRole === 'admin');
if (!$isAdmin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'forbidden']);
    exit;
}

$collections = [
    'ambulance_reports',
    'police_reports',
    'tanod_reports',
    'fire_reports',
    'flood_reports',
    'other_reports',
];

function _epoch_from_any($t): int {
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
}

try {
    // No file-based caching for head-check - always query Firestore for real-time detection
    $start = microtime(true);
    $token = firestore_rest_token();
    $runQueryUrl = firestore_base_url() . ':runQuery';

    $nullLimit = 25;

    $mh = curl_multi_init();
    $chs = [];

    foreach ($collections as $col) {
        foreach (['timestamp', 'createdAt'] as $field) {
            $body = [
                'structuredQuery' => [
                    'from' => [['collectionId' => $col]],
                    'select' => [
                        'fields' => [
                            ['fieldPath' => 'timestamp'],
                            ['fieldPath' => 'createdAt'],
                            ['fieldPath' => 'status'],
                        ]
                    ],
                    'orderBy' => [
                        ['field' => ['fieldPath' => $field], 'direction' => 'DESCENDING']
                    ],
                    'limit' => 1,
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
                CURLOPT_TIMEOUT => 8,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_SSL_VERIFYPEER => SSL_VERIFY,
                CURLOPT_SSL_VERIFYHOST => SSL_VERIFY ? 2 : 0,
            ]);
            curl_multi_add_handle($mh, $ch);
            $chs[] = ['collection' => $col, 'type' => $field, 'ch' => $ch];
        }

        // If a new report is created without timestamp fields, the ordered queries above
        // won't detect it. This lightweight IS_NULL query helps surface those docs so we
        // can use createTime and opportunistically backfill fields.
        foreach (['timestamp', 'createdAt'] as $nullField) {
            $bodyNull = [
                'structuredQuery' => [
                    'from' => [['collectionId' => $col]],
                    'select' => [
                        'fields' => [
                            ['fieldPath' => 'timestamp'],
                            ['fieldPath' => 'createdAt'],
                            ['fieldPath' => 'status'],
                        ]
                    ],
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
                CURLOPT_TIMEOUT => 8,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_SSL_VERIFYPEER => SSL_VERIFY,
                CURLOPT_SSL_VERIFYHOST => SSL_VERIFY ? 2 : 0,
            ]);
            curl_multi_add_handle($mh, $ch);
            $chs[] = ['collection' => $col, 'type' => 'null:' . $nullField, 'ch' => $ch];
        }
    }

    $running = null;
    do {
        curl_multi_exec($mh, $running);
        curl_multi_select($mh, 0.2);
    } while ($running > 0);

    $best = [
        'collection' => null,
        'id' => null,
        'epoch' => 0,
        'status' => null,
    ];

    $byCollection = [];

    foreach ($collections as $col) {
        $byCollection[$col] = ['id' => null, 'epoch' => 0, 'status' => null];
    }

    $backfills = [];

    foreach ($chs as $entry) {
        $col = $entry['collection'];
        $ch = $entry['ch'];

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
            $id = basename($doc['name']);
            $fields = isset($doc['fields']) ? firestore_decode_fields($doc['fields']) : [];
            if (!is_array($fields)) $fields = [];

            $ts = $fields['timestamp'] ?? ($fields['createdAt'] ?? ($doc['createTime'] ?? null));
            $epoch = _epoch_from_any($ts);
            if ($epoch <= 0 && isset($doc['createTime'])) {
                $epoch = _epoch_from_any($doc['createTime']);
            }

            if ($epoch > ($byCollection[$col]['epoch'] ?? 0)) {
                $byCollection[$col] = [
                    'id' => $id,
                    'epoch' => $epoch,
                    'status' => $fields['status'] ?? null,
                ];
            }

            // Opportunistic backfill: if a report doc is missing timestamp fields but has createTime,
            // set timestamp/createdAt so ordered queries can reliably detect it next time.
            if (isset($doc['createTime'])) {
                $missingTs = !isset($fields['timestamp']) || $fields['timestamp'] === null || $fields['timestamp'] === '';
                $missingCreated = !isset($fields['createdAt']) || $fields['createdAt'] === null || $fields['createdAt'] === '';
                if ($missingTs || $missingCreated) {
                    $backfills[] = [
                        'collection' => $col,
                        'id' => $id,
                        'createTime' => (string)$doc['createTime'],
                        'epoch' => _epoch_from_any($doc['createTime']),
                    ];
                }
            }
        }
    }

    foreach ($byCollection as $col => $head) {
        if (($head['epoch'] ?? 0) > ($best['epoch'] ?? 0)) {
            $best = [
                'collection' => $col,
                'id' => $head['id'],
                'epoch' => $head['epoch'],
                'status' => $head['status'],
            ];
        }
    }

    curl_multi_close($mh);

    if (!empty($backfills)) {
        usort($backfills, function($a, $b) {
            return ((int)($b['epoch'] ?? 0)) <=> ((int)($a['epoch'] ?? 0));
        });
        $backfills = array_slice($backfills, 0, 4);
        foreach ($backfills as $bf) {
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

    $sig = ($best['collection'] ?? '') . ':' . ($best['id'] ?? '') . ':' . (string)($best['epoch'] ?? 0);

    $payload = [
        'success' => true,
        'signature' => $sig,
        'latest' => $best,
        'byCollection' => $byCollection,
        'serverNow' => date('c'),
        'executionMs' => round((microtime(true) - $start) * 1000, 2),
    ];

    // No caching - always return fresh data for real-time detection
    $payload['cached'] = false;
    echo json_encode($payload);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'server', 'message' => $e->getMessage()]);
}
