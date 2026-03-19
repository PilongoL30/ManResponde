<?php
/**
 * Debug script to check Recent Activity data directly
 * Access: http://localhost/ManResponde/debug_recent.php
 */

require_once __DIR__.'/db_config.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check login
if (!isset($_SESSION['user']) && !isset($_SESSION['user_id'])) {
    die("Please login first");
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

echo "<html><head><title>Debug Recent Activity - REALTIME</title>";
echo "<meta http-equiv='refresh' content='3'>"; // Auto-refresh every 3 seconds
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
    .card { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    pre { background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 12px; }
    .success { color: #22c55e; }
    .error { color: #ef4444; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #eee; font-size: 13px; }
    th { background: #f8fafc; }
    .recent { background: #ecfdf5; font-weight: bold; }
    .pulse { animation: pulse 1s infinite; }
    @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
    .live-badge { display: inline-flex; align-items: center; gap: 6px; background: #22c55e; color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px; }
    .live-dot { width: 8px; height: 8px; background: white; border-radius: 50%; animation: pulse 1s infinite; }
</style>";
echo "</head><body>";

echo "<h1>🔍 Debug Recent Activity <span class='live-badge'><span class='live-dot'></span> LIVE (auto-refresh 3s)</span></h1>";
echo "<p><strong>Server Time:</strong> " . date('Y-m-d H:i:s T') . " (epoch: " . time() . ")</p>";

// CORRECT categories matching your Firestore structure
$categories = [
    'ambulance' => ['collection' => 'ambulance_reports', 'label' => 'Ambulance', 'icon' => 'truck', 'color' => 'blue'],
    'police'    => ['collection' => 'police_reports', 'label' => 'Police', 'icon' => 'shield', 'color' => 'slate'],
    'tanod'     => ['collection' => 'tanod_reports', 'label' => 'Tanod', 'icon' => 'shield-check', 'color' => 'sky'],
    'fire'      => ['collection' => 'fire_reports', 'label' => 'Fire', 'icon' => 'fire', 'color' => 'red'],
    'flood'     => ['collection' => 'flood_reports', 'label' => 'Flood', 'icon' => 'home', 'color' => 'indigo'],
    'other'     => ['collection' => 'other_reports', 'label' => 'Other', 'icon' => 'question', 'color' => 'gray'],
];

$token = firestore_rest_token();
$runQueryUrl = firestore_base_url() . ':runQuery';

$toEpoch = function($t): int {
    if ($t === null) return 0;
    if (is_array($t)) {
        if (isset($t['_seconds'])) return (int)$t['_seconds'];
        if (isset($t['seconds'])) return (int)$t['seconds'];
        return 0;
    }
    if (is_int($t) || is_float($t)) return (int)$t;
    if (is_string($t) && $t !== '') {
        $s = strtotime($t);
        return $s === false ? 0 : (int)$s;
    }
    return 0;
};

$allReports = [];

foreach ($categories as $slug => $meta) {
    $collection = $meta['collection'];
    
    $body = [
        'structuredQuery' => [
            'from' => [['collectionId' => $collection]],
            'orderBy' => [[
                'field' => ['fieldPath' => 'timestamp'],
                'direction' => 'DESCENDING',
            ]],
            'limit' => 5,
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
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => SSL_VERIFY,
        CURLOPT_SSL_VERIFYHOST => SSL_VERIFY ? 2 : 0,
    ]);
    
    $raw = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http < 200 || $http >= 300) continue;
    
    $rows = json_decode($raw, true);
    if (!is_array($rows)) continue;
    
    foreach ($rows as $row) {
        if (!isset($row['document'])) continue;
        $doc = $row['document'];
        $docId = basename($doc['name']);
        $fields = isset($doc['fields']) ? firestore_decode_fields($doc['fields']) : [];
        
        $rawTs = $fields['timestamp'] ?? null;
        $epoch = $toEpoch($rawTs);
        
        if ($epoch <= 0 && isset($doc['createTime'])) {
            $epoch = $toEpoch($doc['createTime']);
        }
        
        $allReports[] = [
            'collection' => $collection,
            'slug' => $slug,
            'label' => $meta['label'],
            'id' => $docId,
            'name' => $fields['fullName'] ?? $fields['reporterName'] ?? '',
            'status' => $fields['status'] ?? 'Pending',
            'epoch' => $epoch,
        ];
    }
}

// Sort all reports by epoch descending
usort($allReports, fn($a, $b) => $b['epoch'] <=> $a['epoch']);

echo "<div class='card'>";
echo "<h2>🔥 Combined Newest Reports (All Collections - Sorted by Epoch)</h2>";
echo "<p><em>This list auto-refreshes every 3 seconds. Create a new report and watch it appear at #1!</em></p>";
echo "<table>";
echo "<tr><th>#</th><th>Collection</th><th>ID</th><th>Name</th><th>Status</th><th>Epoch</th><th>Time</th></tr>";
$top15 = array_slice($allReports, 0, 15);
foreach ($top15 as $i => $r) {
    $ago = time() - $r['epoch'];
    $agoStr = $ago < 60 ? "{$ago}s ago" : ($ago < 3600 ? round($ago/60) . "m ago" : round($ago/3600, 1) . "h ago");
    $rowClass = ($i === 0) ? 'recent' : '';
    $isNew = $ago < 10 ? ' 🆕' : ''; // Mark as new if less than 10 seconds old
    echo "<tr class='{$rowClass}'>";
    echo "<td>" . ($i+1) . "{$isNew}</td>";
    echo "<td>{$r['label']}</td>";
    echo "<td><code>" . htmlspecialchars(substr($r['id'], 0, 15)) . "</code></td>";
    echo "<td>" . htmlspecialchars($r['name']) . "</td>";
    echo "<td>{$r['status']}</td>";
    echo "<td>{$r['epoch']}</td>";
    echo "<td>" . date('H:i:s', $r['epoch']) . " ({$agoStr})</td>";
    echo "</tr>";
}
echo "</table>";
echo "</div>";

// Show the signature that recent_head.php would return
$newestEpoch = $top15[0]['epoch'] ?? 0;
$newestId = $top15[0]['id'] ?? '';
$newestCollection = $top15[0]['collection'] ?? '';
$signature = $newestCollection . ':' . $newestId . ':' . $newestEpoch;

echo "<div class='card'>";
echo "<h2>🔑 Current Signature (for polling)</h2>";
echo "<p><strong>Newest:</strong> {$newestCollection} / " . substr($newestId, 0, 15) . "</p>";
echo "<p><strong>Epoch:</strong> {$newestEpoch} (" . date('Y-m-d H:i:s', $newestEpoch) . ")</p>";
echo "<p><strong>Signature:</strong> <code>" . htmlspecialchars($signature) . "</code></p>";
echo "<p><em>When this signature changes, the dashboard will refresh the Recent Activity list.</em></p>";
echo "</div>";

echo "<div class='card'>";
echo "<h2>📋 Instructions</h2>";
echo "<ol>";
echo "<li><strong>Keep this page open</strong> - it auto-refreshes every 3 seconds</li>";
echo "<li><strong>Create a new report</strong> from your mobile app</li>";
echo "<li><strong>Watch the table</strong> - your new report should appear at #1 with 🆕 badge</li>";
echo "<li>If it works here, it should work on the dashboard too!</li>";
echo "</ol>";
echo "</div>";

echo "</body></html>";
