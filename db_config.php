<?php
// db_config.php

// Load configuration
require_once __DIR__ . '/config.php';

// Load cache and CSRF systems
require_once __DIR__ . '/includes/cache.php';
require_once __DIR__ . '/includes/csrf.php';

// Configure custom session path to avoid permission issues (BEFORE session starts)
if (session_status() === PHP_SESSION_NONE) {
    $sessionPath = __DIR__ . '/sessions';
    if (!is_dir($sessionPath)) {
        mkdir($sessionPath, 0755, true);
    }
    session_save_path($sessionPath);
    ini_set('session.gc_probability', 1);
    ini_set('session.gc_divisor', 100);
}

// Force REST transport for Google Cloud PHP to avoid gRPC instability on Windows
if (FIRESTORE_USE_REST) {
    putenv('GOOGLE_CLOUD_PHP_USE_REST=true');
    $_ENV['GOOGLE_CLOUD_PHP_USE_REST'] = 'true';
}

// Composer autoload for Firebase SDK
$firebaseAutoloadPath = __DIR__ . '/firebase-php-auth/vendor/autoload.php';
if (!is_file($firebaseAutoloadPath)) {
    throw new RuntimeException('Missing Firebase dependency: ' . $firebaseAutoloadPath);
}
require $firebaseAutoloadPath;

// Hosting safety fallback: some deployments have incomplete/stale Composer autoload metadata.
// Ensure the converter class required by newer Kreait releases is available.
if (!class_exists('Kreait\\Firebase\\Valinor\\Converter\\SnakeCaseToCamelCaseConverter')) {
    $converterPath = __DIR__ . '/firebase-php-auth/vendor/kreait/firebase-php/src/Firebase/Valinor/Converter/SnakeCaseToCamelCaseConverter.php';
    if (is_file($converterPath)) {
        require_once $converterPath;
    }
}

use Kreait\Firebase\Factory;
use Kreait\Firebase\Auth;
use Kreait\Firebase\Http\HttpClientOptions;
use Google\Auth\Credentials\ServiceAccountCredentials;

/**
 * Initialize Firebase Factory (Auth/Firestore creators).
 * @return \Kreait\Firebase\Factory
 */
function initialize_firebase() {
    // SSL verification based on environment
    $sslVerify = SSL_VERIFY;
    
    // In development on Windows, you may need to disable SSL verification
    // OR set 'verify' to path of cacert.pem file
    if (!$sslVerify && IS_DEVELOPMENT) {
        // Log warning in development when SSL verification is disabled
        if (DEBUG_MODE) {
            error_log('WARNING: SSL verification is disabled in development mode');
        }
    }
    
    $httpOptions = HttpClientOptions::default()
        ->withGuzzleConfigOptions([
            'connect_timeout' => 10,
            'read_timeout'    => 30,
            'timeout'         => 60,
            'verify'          => $sslVerify,
        ]);

    return (new Factory)
        ->withServiceAccount(service_account_path())
        ->withHttpClientOptions($httpOptions)
        ->withFirestoreClientConfig([
            'transport' => 'rest',
            'transportConfig' => [
                'rest' => [
                    'restOptions' => [
                        'verify'          => $sslVerify,
                        'connect_timeout' => 10,
                        'timeout'         => 60,
                    ],
                ],
            ],
        ]);
}

/**
 * Firebase Authentication instance.
 * @return \Kreait\Firebase\Auth
 */
function initialize_auth() {
    return initialize_firebase()->createAuth();
}

/**
 * Firestore client (if you need SDK; dashboard uses REST helpers below).
 */
function initialize_firestore() {
    return initialize_firebase()->createFirestore()->database();
}

/**
 * Firebase Storage client.
 * @return \Kreait\Firebase\Storage
 */
function initialize_storage() {
    return initialize_firebase()->createStorage();
}

/**
 * Get the correct Firebase Storage bucket.
 */
function get_storage_bucket() {
    $storage = initialize_storage();
    $projectId = firebase_project_id();
    $bucketName = $projectId . '.firebasestorage.app';
    
    return $storage->getBucket($bucketName);
}

/**
 * Path to service account JSON.
 */
function service_account_path(): string {
    $envPath = getenv('FIREBASE_SERVICE_ACCOUNT_PATH');
    if (is_string($envPath) && trim($envPath) !== '') {
        return trim($envPath);
    }

    $googleAppCreds = getenv('GOOGLE_APPLICATION_CREDENTIALS');
    if (is_string($googleAppCreds) && trim($googleAppCreds) !== '') {
        return trim($googleAppCreds);
    }

    return __DIR__.'/firebase-php-auth/config/ibantayv2-firebase-adminsdk-fbsvc-0526b0e79f.json';
}

/**
 * Read service account JSON config (cached).
 */
function service_account_config(): array {
    static $cfg = null;
    if ($cfg === null) {
        $envJson = getenv('FIREBASE_SERVICE_ACCOUNT_JSON');
        if (is_string($envJson) && trim($envJson) !== '') {
            $cfg = json_decode($envJson, true) ?: [];
        } else {
            $path = service_account_path();
            if (!is_file($path)) {
                throw new Exception('Service account JSON not found at: '.$path);
            }
            $cfg = json_decode(file_get_contents($path), true) ?: [];
        }

        if (!is_array($cfg) || empty($cfg)) {
            throw new Exception('Service account JSON is invalid or empty.');
        }

        if (isset($cfg['private_key']) && is_string($cfg['private_key'])) {
            if (strpos($cfg['private_key'], '\\n') !== false) {
                $cfg['private_key'] = str_replace('\\n', "\n", $cfg['private_key']);
            }
            $cfg['private_key'] = trim($cfg['private_key']);
        }

        $required = ['project_id', 'client_email', 'private_key'];
        foreach ($required as $requiredKey) {
            if (empty($cfg[$requiredKey]) || !is_string($cfg[$requiredKey])) {
                throw new Exception('Service account JSON missing required field: '.$requiredKey);
            }
        }

        if (!openssl_pkey_get_private($cfg['private_key'])) {
            throw new Exception('Service account private_key is invalid or malformed.');
        }
    }
    return $cfg;
}

/**
 * Firebase project id.
 */
function firebase_project_id(): string {
    $cfg = service_account_config();
    return $cfg['project_id'] ?? '';
}

/**
 * OAuth2 token for Firestore REST.
 */
function firestore_rest_token(): string {
    static $token = null, $exp = 0;
    
    // Check static cache first
    if ($token && time() < ($exp - 60)) return $token;

    // Check session cache
    if (isset($_SESSION['__firestore_token']) && isset($_SESSION['__firestore_token_exp'])) {
        if (time() < ($_SESSION['__firestore_token_exp'] - 60)) {
            $token = $_SESSION['__firestore_token'];
            $exp = $_SESSION['__firestore_token_exp'];
            return $token;
        }
    }

    $scopes = ['https://www.googleapis.com/auth/datastore'];
    $creds  = new ServiceAccountCredentials($scopes, service_account_config());
    try {
        $resp = $creds->fetchAuthToken();
    } catch (Throwable $e) {
        $message = $e->getMessage();
        if (stripos($message, 'invalid_grant') !== false || stripos($message, 'Invalid JWT Signature') !== false) {
            error_log('Firestore token error: invalid service account signature. Check/rotate Firebase service account JSON key.');
            throw new Exception('Firebase service account key is invalid or revoked. Please replace JSON credentials and try again.');
        }
        throw $e;
    }
    $token  = $resp['access_token'] ?? '';
    $exp    = (int)($resp['expires_at'] ?? (time()+300));
    
    if (!$token) throw new Exception('Failed to get Firestore access token.');
    
    // Update caches
    $_SESSION['__firestore_token'] = $token;
    $_SESSION['__firestore_token_exp'] = $exp;
    
    return $token;
}

/**
 * Firestore REST base URL.
 */
function firestore_base_url(): string {
    return 'https://firestore.googleapis.com/v1/projects/'
        .rawurlencode(firebase_project_id())
        .'/databases/(default)/documents';
}

/**
 * Low-level REST request to Firestore (cURL).
 */
function firestore_rest_request(string $method, string $url, ?array $body = null): array {
    $ch = curl_init();
    $headers = [
        'Authorization: Bearer '.firestore_rest_token(),
        'Accept: application/json',
    ];
    if ($body !== null) $headers[] = 'Content-Type: application/json';

    $opts = [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => SSL_VERIFY,
        CURLOPT_SSL_VERIFYHOST => SSL_VERIFY ? 2 : 0,
    ];
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($body);
    }
    curl_setopt_array($ch, $opts);
    $respBody = curl_exec($ch);
    $errno    = curl_errno($ch);
    $err      = curl_error($ch);
    $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno) throw new Exception('Firestore REST request failed: '.$err);
    $json = json_decode($respBody ?: 'null', true);
    if ($status >= 400) {
        $msg = is_array($json) && isset($json['error']['message']) ? $json['error']['message'] : 'HTTP '.$status;
        error_log("Firestore REST error details - Status: $status, URL: $url, Body: " . json_encode($body));
        throw new Exception('Firestore REST error: '.$msg);
    }
    return is_array($json) ? $json : [];
}

/**
 * Encode PHP value to Firestore Value format.
 */
function firestore_encode_value($value): array {
    if ($value === null) return ['nullValue' => null];
    if (is_string($value)) return ['stringValue' => $value];
    if (is_bool($value))   return ['booleanValue' => $value];
    if (is_int($value))    return ['integerValue' => (string)$value];
    if (is_float($value))  return ['doubleValue' => $value];

    if ($value instanceof DateTimeInterface) {
        return ['timestampValue' => $value->format(DateTimeInterface::RFC3339_EXTENDED)];
    }

    if (is_array($value)) {
        $isAssoc = array_keys($value) !== range(0, count($value)-1);
        if ($isAssoc) {
            $fields = [];
            foreach ($value as $k => $v) $fields[$k] = firestore_encode_value($v);
            return ['mapValue' => ['fields' => $fields]];
        }
        return ['arrayValue' => ['values' => array_map('firestore_encode_value', $value)]];
    }

    // Allow object with ->timestampValue
    if (is_object($value) && isset($value->timestampValue)) {
        return ['timestampValue' => (string)$value->timestampValue];
    }

    return ['stringValue' => (string)$value];
}

/**
 * Encode assoc array to Firestore fields map.
 */
function firestore_encode_fields(array $data): array {
    $out = [];
    foreach ($data as $k => $v) $out[$k] = firestore_encode_value($v);
    return $out;
}

/**
 * Decode Firestore Value to PHP.
 */
function firestore_decode_value(array $v) {
    if (array_key_exists('nullValue', $v))      return null;
    if (array_key_exists('stringValue', $v))    return $v['stringValue'];
    if (array_key_exists('booleanValue', $v))   return (bool)$v['booleanValue'];
    if (array_key_exists('integerValue', $v))   return (int)$v['integerValue'];
    if (array_key_exists('doubleValue', $v))    return (float)$v['doubleValue'];
    if (array_key_exists('timestampValue', $v)) return $v['timestampValue'];
    if (array_key_exists('mapValue', $v)) {
        $f = $v['mapValue']['fields'] ?? [];
        return firestore_decode_fields($f);
    }
    if (array_key_exists('arrayValue', $v)) {
        $vals = $v['arrayValue']['values'] ?? [];
        return array_map('firestore_decode_value', $vals);
    }
    return null;
}

/**
 * Decode Firestore fields map to PHP assoc array.
 */
function firestore_decode_fields(array $fields): array {
    $out = [];
    foreach ($fields as $k => $v) $out[$k] = firestore_decode_value($v);
    return $out;
}

/**
 * Merge (PATCH) a document (behaves like set(..., {merge:true})).
 */
function firestore_set_document(string $collection, string $docId, array $data): array {
    $base = firestore_base_url().'/'.rawurlencode($collection).'/'.rawurlencode($docId);
    $mask = implode('&', array_map(fn($k) => 'updateMask.fieldPaths='.rawurlencode($k), array_keys($data)));
    $url  = $base.($mask ? ('?'.$mask) : '');
    $body = ['fields' => firestore_encode_fields($data)];
    return firestore_rest_request('PATCH', $url, $body);
}

/**
 * Fetch a single document by collection and document ID.
 */
function firestore_get_doc_by_id(string $collection, string $docId): ?array {
    $url  = firestore_base_url().'/'.rawurlencode($collection).'/'.rawurlencode($docId);
    try {
        $res  = firestore_rest_request('GET', $url, null);
        if (!isset($res['fields'])) return null;
        $data = firestore_decode_fields($res['fields']);
        $name = $res['name'] ?? '';
        $data['_id'] = $name ? basename($name) : $docId;
        return $data;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Delete a single document by collection and document ID.
 */
function firestore_delete_document(string $collection, string $docId): bool {
    $url = firestore_base_url().'/'.rawurlencode($collection).'/'.rawurlencode($docId);
    try {
        $res = firestore_rest_request('DELETE', $url, null);
        return !empty($res);
    } catch (Throwable $e) {
        error_log("Error deleting document {$collection}/{$docId}: " . $e->getMessage());
        return false;
    }
}

/**
 * RunQuery: first doc where field == value.
 */
function firestore_query_one_by_field(string $collection, string $field, $value): ?array {
    $url  = firestore_base_url().':runQuery';
    $body = [
        'structuredQuery' => [
            'from' => [['collectionId' => $collection]],
            'where' => [
                'fieldFilter' => [
                    'field' => ['fieldPath' => $field],
                    'op'    => 'EQUAL',
                    'value' => firestore_encode_value($value),
                ],
            ],
            'limit' => 1,
        ],
    ];
    try {
        $rows = firestore_rest_request('POST', $url, $body);
        $rows = is_array($rows) && array_is_list($rows) ? $rows : [$rows];
        foreach ($rows as $row) {
            if (!isset($row['document'])) continue;
            $doc  = $row['document'];
            $data = firestore_decode_fields($doc['fields'] ?? []);
            $name = $doc['name'] ?? '';
            $data['_id'] = $name ? basename($name) : '';
            return $data;
        }
    } catch (Throwable $e) {}
    return null;
}

/**
 * RunQuery: latest docs ordered by 'timestamp' DESC.
 * Returns decoded docs with _id and _created (document createTime).
 */
function firestore_query_latest(string $collection, int $limit = 10): array {
    $url  = firestore_base_url().':runQuery';
    $body = [
        'structuredQuery' => [
            'from'    => [['collectionId' => $collection]],
            'orderBy' => [[
                'field'     => ['fieldPath' => 'timestamp'],
                'direction' => 'DESCENDING',
            ]],
            'limit' => $limit,
        ],
    ];
    $out = [];
    try {
        $rows = firestore_rest_request('POST', $url, $body);
        $rows = is_array($rows) && array_is_list($rows) ? $rows : [$rows];
        foreach ($rows as $row) {
            if (!isset($row['document'])) continue;
            $doc   = $row['document'];
            $data  = firestore_decode_fields($doc['fields'] ?? []);
            $name  = $doc['name'] ?? '';
            $data['_id']      = $name ? basename($name) : '';
            $data['_created'] = $doc['createTime'] ?? null;
            $out[] = $data;
        }
    } catch (Throwable $e) {}
    return $out;
}

/**
 * Aggregation count (optionally filtered by status).
 */
function firestore_count(string $collection, ?string $statusEquals = null): int {
    $url  = 'https://firestore.googleapis.com/v1/projects/'.rawurlencode(firebase_project_id()).'/databases/(default)/documents:runAggregationQuery';
    $structuredQuery = ['from' => [['collectionId' => $collection]]];
    if ($statusEquals !== null) {
        $structuredQuery['where'] = [
            'fieldFilter' => [
                'field' => ['fieldPath' => 'status'],
                'op'    => 'EQUAL',
                'value' => firestore_encode_value($statusEquals),
            ],
        ];
    }
    $body = [
        'structuredAggregationQuery' => [
            'aggregations'    => [['alias' => 'c', 'count' => new stdClass()]],
            'structuredQuery' => $structuredQuery,
        ],
    ];
    try {
        $rows = firestore_rest_request('POST', $url, $body);
        $rows = is_array($rows) && array_is_list($rows) ? $rows : [$rows];
        foreach ($rows as $row) {
            $v = $row['result']['aggregateFields']['c']['integerValue'] ?? null;
            if ($v !== null) return (int)$v;
        }
    } catch (Throwable $e) {}
    return 0;
}

/**
 * Count reports in a collection (optionally filtered by status).
 */
function count_reports(string $collection, ?string $status = null): ?int {
    if (function_exists('firestore_count')) {
        try { 
            return firestore_count($collection, $status); 
        } catch (Throwable $e) { 
            error_log("Error counting reports in {$collection}: " . $e->getMessage());
            return null; 
        }
    }
    return null;
}

/**
 * Optimized batch count for admin statistics.
 * This function is specifically designed for the admin dashboard stats.
 */
function get_admin_stats_counts(array $collections): array {
    $results = [];
    
    // Process each collection with all required status counts
    foreach ($collections as $collection) {
        $results[$collection] = [
            'total' => firestore_count($collection, null),
            'approved' => firestore_count($collection, 'Approved'),
            'pending' => firestore_count($collection, 'Pending'),
        ];
    }
    
    return $results;
}

/**
 * Super fast admin statistics using Firestore aggregation queries.
 * Uses parallel requests and aggregation for maximum performance.
 */
function get_admin_stats_counts_fast(array $collections): array {
    $results = [];
    $requests = [];
    
    // Prepare all requests for parallel execution
    foreach ($collections as $collection) {
        // Use aggregation API for much faster counting
        $requests[] = [
            'collection' => $collection,
            'total_url' => firestore_base_url() . ':runAggregationQuery',
            'total_body' => [
                'structuredAggregationQuery' => [
                    'structuredQuery' => [
                        'from' => [['collectionId' => $collection]]
                    ],
                    'aggregations' => [
                        'total_count' => ['count' => (object)[]]
                    ]
                ]
            ],
            'approved_url' => firestore_base_url() . ':runAggregationQuery',
            'approved_body' => [
                'structuredAggregationQuery' => [
                    'structuredQuery' => [
                        'from' => [['collectionId' => $collection]],
                        'where' => [
                            'fieldFilter' => [
                                'field' => ['fieldPath' => 'status'],
                                'op' => 'EQUAL',
                                'value' => ['stringValue' => 'Approved']
                            ]
                        ]
                    ],
                    'aggregations' => [
                        'approved_count' => ['count' => (object)[]]
                    ]
                ]
            ],
            'pending_url' => firestore_base_url() . ':runAggregationQuery',
            'pending_body' => [
                'structuredAggregationQuery' => [
                    'structuredQuery' => [
                        'from' => [['collectionId' => $collection]],
                        'where' => [
                            'fieldFilter' => [
                                'field' => ['fieldPath' => 'status'],
                                'op' => 'EQUAL',
                                'value' => ['stringValue' => 'Pending']
                            ]
                        ]
                    ],
                    'aggregations' => [
                        'pending_count' => ['count' => (object)[]]
                    ]
                ]
            ],
            'declined_url' => firestore_base_url() . ':runAggregationQuery',
            'declined_body' => [
                'structuredAggregationQuery' => [
                    'structuredQuery' => [
                        'from' => [['collectionId' => $collection]],
                        'where' => [
                            'fieldFilter' => [
                                'field' => ['fieldPath' => 'status'],
                                'op' => 'EQUAL',
                                'value' => ['stringValue' => 'Declined']
                            ]
                        ]
                    ],
                    'aggregations' => [
                        'declined_count' => ['count' => (object)[]]
                    ]
                ]
            ],
            'responding_url' => firestore_base_url() . ':runAggregationQuery',
            'responding_body' => [
                'structuredAggregationQuery' => [
                    'structuredQuery' => [
                        'from' => [['collectionId' => $collection]],
                        'where' => [
                            'fieldFilter' => [
                                'field' => ['fieldPath' => 'status'],
                                'op' => 'EQUAL',
                                'value' => ['stringValue' => 'Responding']
                            ]
                        ]
                    ],
                    'aggregations' => [
                        'responding_count' => ['count' => (object)[]]
                    ]
                ]
            ],
            'responded_url' => firestore_base_url() . ':runAggregationQuery',
            'responded_body' => [
                'structuredAggregationQuery' => [
                    'structuredQuery' => [
                        'from' => [['collectionId' => $collection]],
                        'where' => [
                            'fieldFilter' => [
                                'field' => ['fieldPath' => 'status'],
                                'op' => 'EQUAL',
                                'value' => ['stringValue' => 'Responded']
                            ]
                        ]
                    ],
                    'aggregations' => [
                        'responded_count' => ['count' => (object)[]]
                    ]
                ]
            ]
        ];
    }
    
    // Execute all requests in parallel using multi-curl
    $multiHandle = curl_multi_init();
    $curlHandles = [];
    $requestMap = [];
    
    foreach ($requests as $idx => $request) {
        $collection = $request['collection'];
        
        // Total count request
        $ch1 = curl_init();
        curl_setopt_array($ch1, [
            CURLOPT_URL => $request['total_url'],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($request['total_body']),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . firestore_rest_token()
            ],
            CURLOPT_TIMEOUT => 3,
            CURLOPT_CONNECTTIMEOUT => 2
        ]);
        curl_multi_add_handle($multiHandle, $ch1);
        $curlHandles[] = $ch1;
        $requestMap[] = ['collection' => $collection, 'type' => 'total'];
        
        // Approved count request
        $ch2 = curl_init();
        curl_setopt_array($ch2, [
            CURLOPT_URL => $request['approved_url'],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($request['approved_body']),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . firestore_rest_token()
            ],
            CURLOPT_TIMEOUT => 3,
            CURLOPT_CONNECTTIMEOUT => 2
        ]);
        curl_multi_add_handle($multiHandle, $ch2);
        $curlHandles[] = $ch2;
        $requestMap[] = ['collection' => $collection, 'type' => 'approved'];
        
        // Pending count request
        $ch3 = curl_init();
        curl_setopt_array($ch3, [
            CURLOPT_URL => $request['pending_url'],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($request['pending_body']),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . firestore_rest_token()
            ],
            CURLOPT_TIMEOUT => 3,
            CURLOPT_CONNECTTIMEOUT => 2
        ]);
        curl_multi_add_handle($multiHandle, $ch3);
        $curlHandles[] = $ch3;
        $requestMap[] = ['collection' => $collection, 'type' => 'pending'];

        // Declined count request
        $ch4 = curl_init();
        curl_setopt_array($ch4, [
            CURLOPT_URL => $request['declined_url'],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($request['declined_body']),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . firestore_rest_token()
            ],
            CURLOPT_TIMEOUT => 3,
            CURLOPT_CONNECTTIMEOUT => 2
        ]);
        curl_multi_add_handle($multiHandle, $ch4);
        $curlHandles[] = $ch4;
        $requestMap[] = ['collection' => $collection, 'type' => 'declined'];

        // Responded count request
        $ch5 = curl_init();
        curl_setopt_array($ch5, [
            CURLOPT_URL => $request['responded_url'],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($request['responded_body']),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . firestore_rest_token()
            ],
            CURLOPT_TIMEOUT => 3,
            CURLOPT_CONNECTTIMEOUT => 2
        ]);
        curl_multi_add_handle($multiHandle, $ch5);
        $curlHandles[] = $ch5;
        $requestMap[] = ['collection' => $collection, 'type' => 'responded'];
        
        // Responding count request
        $ch6 = curl_init();
        curl_setopt_array($ch6, [
            CURLOPT_URL => $request['responding_url'],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($request['responding_body']),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . firestore_rest_token()
            ],
            CURLOPT_TIMEOUT => 3,
            CURLOPT_CONNECTTIMEOUT => 2
        ]);
        curl_multi_add_handle($multiHandle, $ch6);
        $curlHandles[] = $ch6;
        $requestMap[] = ['collection' => $collection, 'type' => 'responding'];
    }
    
    // Execute all requests in parallel
    $running = null;
    do {
        curl_multi_exec($multiHandle, $running);
        curl_multi_select($multiHandle);
    } while ($running > 0);
    
    // Process results
    foreach ($curlHandles as $idx => $ch) {
        $response = curl_multi_getcontent($ch);
        $collection = $requestMap[$idx]['collection'];
        $type = $requestMap[$idx]['type'];
        
        if (!isset($results[$collection])) {
            $results[$collection] = ['total' => 0, 'approved' => 0, 'pending' => 0, 'declined' => 0, 'responding' => 0, 'responded' => 0];
        }
        
        try {
            $data = json_decode($response, true);

            // Firestore runAggregationQuery often returns an array of result rows.
            // Normalize both array and object response shapes.
            $aggregateFields = null;
            if (is_array($data) && isset($data[0]['result']['aggregateFields'])) {
                $aggregateFields = $data[0]['result']['aggregateFields'];
            } elseif (is_array($data) && isset($data['result']['aggregateFields'])) {
                $aggregateFields = $data['result']['aggregateFields'];
            }

            if (is_array($aggregateFields)) {
                $count = $aggregateFields[$type . '_count']['integerValue'] ?? null;
                if ($count === null) {
                    foreach ($aggregateFields as $fieldValue) {
                        if (isset($fieldValue['integerValue'])) {
                            $count = $fieldValue['integerValue'];
                            break;
                        }
                    }
                }
                if ($count === null) {
                    $count = 0;
                }
                $results[$collection][$type] = (int)$count;
            }
        } catch (Exception $e) {
            error_log("Error processing aggregation result for {$collection}.{$type}: " . $e->getMessage());
        }
        
        curl_multi_remove_handle($multiHandle, $ch);
        curl_close($ch);
    }
    
    curl_multi_close($multiHandle);
    
    return $results;
}

/**
 * Fallback fast count using optimized document fetching.
 * Only used if aggregation queries fail.
 */
function get_admin_stats_counts_fallback(array $collections): array {
    $results = [];
    
    foreach ($collections as $collection) {
        try {
            // Get only status field to minimize data transfer
            $url = firestore_base_url() . ':runQuery';
            $body = [
                'structuredQuery' => [
                    'from' => [['collectionId' => $collection]],
                    'select' => [
                        'fields' => [['fieldPath' => 'status']]
                    ],
                    'limit' => 500, // Reduced limit for faster response
                ]
            ];
            
            $response = firestore_rest_request('POST', $url, $body);
            $total = 0;
            $approved = 0;
            $pending = 0;
            $declined = 0;
            $responding = 0;
            $responded = 0;

            if (is_array($response)) {
                foreach ($response as $row) {
                    if (isset($row['document']['fields']['status'])) {
                        $total++;
                        $status = $row['document']['fields']['status']['stringValue'] ?? '';
                        // Case-insensitive check
                        $statusLower = strtolower($status);

                        if ($statusLower === 'approved') {
                            $approved++;
                        } elseif ($statusLower === 'pending') {
                            $pending++;
                        } elseif ($statusLower === 'declined') {
                            $declined++;
                        } elseif ($statusLower === 'responding') {
                            $responding++;
                        } elseif ($statusLower === 'responded') {
                            $responded++;
                        }
                    }
                }
            }

            $results[$collection] = [
                'total' => $total,
                'approved' => $approved,
                'pending' => $pending,
                'declined' => $declined,
                'responding' => $responding,
                'responded' => $responded,
            ];

        } catch (Exception $e) {
            // Fallback to individual counts if the batch approach fails
            $results[$collection] = [
                'total' => firestore_count($collection, null),
                'approved' => firestore_count($collection, 'Approved'),
                'pending' => firestore_count($collection, 'Pending'),
                'declined' => firestore_count($collection, 'Declined'),
                'responding' => firestore_count($collection, 'Responding'),
                'responded' => firestore_count($collection, 'Responded'),
            ];
        }
    }
    
    return $results;
}

/**
 * Get Firebase access token for API calls.
 * Simplified version that falls back to original auth method.
 */
function get_firebase_access_token(): string {
    try {
        if (function_exists('firestore_rest_token')) {
            return firestore_rest_token();
        }
        return '';
    } catch (Exception $e) {
        error_log("Error getting firebase access token: " . $e->getMessage());
        return '';
    }
}

/**
 * Ultra-fast Firestore document update with aggressive optimization.
 * Used for real-time status updates requiring <0.5 second response.
 */
function firestore_set_document_fast(string $collection, string $docId, array $data): bool {
    try {
        // Fix: Remove extra '/documents' segment as firestore_base_url() already includes it
        $url = firestore_base_url() . '/' . rawurlencode($collection) . '/' . rawurlencode($docId);
        
        // Convert data to Firestore format
        $firestoreData = [];
        foreach ($data as $key => $value) {
            $firestoreData[$key] = firestore_encode_value($value);
        }
        
        $body = [
            'fields' => $firestoreData
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url . '?updateMask.fieldPaths=' . implode('&updateMask.fieldPaths=', array_keys($data)),
            CURLOPT_CUSTOMREQUEST => 'PATCH',
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . firestore_rest_token()
            ],
            CURLOPT_TIMEOUT => 10, // Increased timeout
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            error_log("Fast Firestore update cURL error: $curlError");
            return false;
        }
        
        if ($httpCode >= 200 && $httpCode < 300) {
            error_log("Fast Firestore update successful: HTTP $httpCode");
            return true;
        } else {
            error_log("Fast Firestore update failed with HTTP $httpCode: $response");
            return false;
        }
        
    } catch (Exception $e) {
        error_log("Error in firestore_set_document_fast: " . $e->getMessage());
        return false;
    }
}

/**
 * Async notification update that doesn't block the main request.
 */
function update_notification_for_report_status_async(string $docId, string $newStatus, string $collection): void {
    // Use a background process or queue for notification updates
    // For now, we'll do it synchronously but with a very short timeout
    try {
        // Check if notification system is available
        if (file_exists(__DIR__ . '/notification_system.php')) {
            require_once __DIR__ . '/notification_system.php';
            if (function_exists('update_notification_for_report_status')) {
                update_notification_for_report_status($docId, $newStatus, $collection);
            }
        }
    } catch (Exception $e) {
        // Silently fail notifications to not block status updates
        error_log("Async notification update failed: " . $e->getMessage());
    }
}

/**
 * Optimized admin stats with smart fallback strategy.
 * Tries aggregation API first, falls back to optimized counting.
 */
function get_admin_stats_counts_optimized(array $collections): array {
    try {
        // Try the new aggregation-based approach first
        $results = get_admin_stats_counts_fast($collections);
        
        // Validate results - if any collection has 0 for all counts, it might be an API issue
        $hasValidData = false;
        foreach ($results as $collection => $counts) {
            if ($counts['total'] > 0 || $counts['approved'] > 0 || $counts['pending'] > 0) {
                $hasValidData = true;
                break;
            }
        }
        
        if ($hasValidData) {
            return $results;
        }
    } catch (Exception $e) {
        error_log("Aggregation API failed, falling back to optimized approach: " . $e->getMessage());
    }
    
    // Fallback to the optimized document-based approach
    return get_admin_stats_counts_fallback($collections);
}

/**
 * Generate Firebase Storage URL for proof images.
 */
function get_storage_url(string $path): string {
    if (empty($path)) return '';
    $projectId = 'ibantayv2'; // Match the Firebase project
    $encodedPath = rawurlencode($path);
    // Use the correct Firebase Storage bucket format (.firebasestorage.app instead of .appspot.com)
    return "https://firebasestorage.googleapis.com/v0/b/{$projectId}.firebasestorage.app/o/{$encodedPath}?alt=media";
}

/**
 * Test Firestore connection and basic functionality.
 */
function test_firestore_connection(): array {
    $startTime = microtime(true);
    $results = [];
    
    try {
        // Test 1: Authentication
        $token = firestore_rest_token();
        if (!empty($token)) {
            $results['authentication'] = ['status' => 'success', 'message' => 'Authentication successful'];
        } else {
            $results['authentication'] = ['status' => 'error', 'message' => 'Authentication failed - empty token'];
        }
    } catch (Exception $e) {
        $results['authentication'] = ['status' => 'error', 'message' => 'Authentication failed: ' . $e->getMessage()];
    }
    
    try {
        // Test 2: Basic query
        $url = firestore_base_url() . ':runQuery';
        $body = [
            'structuredQuery' => [
                'from' => [['collectionId' => 'users']],
                'limit' => 1
            ]
        ];
        
        $response = firestore_rest_request('POST', $url, $body);
        $results['basic_query'] = ['status' => 'success', 'message' => 'Basic query successful'];
    } catch (Exception $e) {
        $results['basic_query'] = ['status' => 'error', 'message' => 'Basic query failed: ' . $e->getMessage()];
    }
    
    try {
        // Test 3: Pending users count
        $count = firestore_count('users', 'pending');
        $results['pending_count'] = ['status' => 'success', 'message' => "Pending users count: {$count}"];
    } catch (Exception $e) {
        $results['pending_count'] = ['status' => 'error', 'message' => 'Count failed: ' . $e->getMessage()];
    }
    
    $executionTime = round((microtime(true) - $startTime) * 1000, 2);
    $overallSuccess = !array_filter($results, fn($r) => $r['status'] === 'error');
    
    return [
        'success' => $overallSuccess,
        'results' => $results,
        'executionTime' => $executionTime . 'ms'
    ];
}
