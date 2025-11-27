<?php
/**
 * Debug FCM Notification Content
 * This shows exactly what data is being sent to FCM to help diagnose mobile app issues
 */

require_once __DIR__.'/db_config.php';
require_once __DIR__.'/fcm_config.php';

// Test emergency notification content
$testCollection = 'tanod_reports';
$testDocId = 'test_doc_123';

echo "<h2>🔍 FCM Notification Debug</h2>\n";

// Test 1: Show what send_emergency_notification_directly sends
echo "<h3>1. Direct Emergency Notification Content</h3>\n";

if (function_exists('send_emergency_notification_directly')) {
    // Capture the FCM payload by temporarily modifying the function
    echo "<p>Testing direct emergency notification...</p>\n";
    
    // This will show in error logs what is being sent
    $result = send_emergency_notification_directly($testCollection, $testDocId);
    echo "<p>Result: " . ($result ? 'SUCCESS' : 'FAILED') . "</p>\n";
    echo "<p><strong>Check error logs for detailed FCM payload data</strong></p>\n";
} else {
    echo "<p>❌ send_emergency_notification_directly function not available</p>\n";
}

// Test 2: Show the exact data structure being sent
echo "<h3>2. Expected FCM Payload Structure</h3>\n";

$expectedTitle = "🚔 EMERGENCY ALERT - TANOD";
$expectedBody = "Test User needs immediate tanod assistance at Test Location";
$expectedData = [
    'type' => 'tanod_emergency',
    'emergencyType' => 'tanod',
    'reportId' => $testDocId,
    'collection' => $testCollection,
    'reporterName' => 'Test User',
    'location' => 'Test Location',
    'description' => 'Test emergency',
    'status' => 'approved',
    'priority' => 'emergency',
    'audience' => 'responder'
];

echo "<div style='background: #f0f0f0; padding: 10px; margin: 10px; border: 1px solid #ccc;'>\n";
echo "<strong>Title:</strong> " . htmlspecialchars($expectedTitle) . "<br>\n";
echo "<strong>Body:</strong> " . htmlspecialchars($expectedBody) . "<br>\n";
echo "<strong>Data:</strong><br>\n";
echo "<pre>" . json_encode($expectedData, JSON_PRETTY_PRINT) . "</pre>\n";
echo "</div>\n";

// Test 3: Compare with working test_notification.php
echo "<h3>3. Comparison with Working Test</h3>\n";
echo "<p>✅ <a href='test_notification.php' target='_blank'>test_notification.php</a> works correctly</p>\n";
echo "<p>❌ Real dashboard approval shows 'Report Approved ✅' instead</p>\n";

echo "<h4>Possible Mobile App Issues:</h4>\n";
echo "<ul>\n";
echo "<li><strong>FCM Data Handling:</strong> App might ignore title/body from FCM payload</li>\n";
echo "<li><strong>Notification Display:</strong> App might use default 'Report Approved' text</li>\n";
echo "<li><strong>FirebaseMessagingService:</strong> Bug in onMessageReceived() method</li>\n";
echo "<li><strong>Notification Channel:</strong> Wrong notification channel being used</li>\n";
echo "<li><strong>Cache Issue:</strong> App cached old notification behavior</li>\n";
echo "</ul>\n";

echo "<h4>Next Steps:</h4>\n";
echo "<ol>\n";
echo "<li>Check mobile app's FirebaseMessagingService.onMessageReceived() method</li>\n";
echo "<li>Verify the app uses FCM payload title/body instead of hardcoded strings</li>\n";
echo "<li>Clear mobile app cache/data and test again</li>\n";
echo "<li>Check if app has multiple notification listeners</li>\n";
echo "</ol>\n";

// Test 4: Show recent FCM calls
echo "<h3>4. Recent FCM Function Calls</h3>\n";
global $fcm_function_calls;
if (!empty($fcm_function_calls)) {
    echo "<ul>\n";
    foreach ($fcm_function_calls as $call) {
        echo "<li>" . htmlspecialchars($call) . "</li>\n";
    }
    echo "</ul>\n";
} else {
    echo "<p>No FCM function calls recorded in this session</p>\n";
}

?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
pre { background: #f8f8f8; padding: 10px; border: 1px solid #ddd; }
h2 { color: #333; border-bottom: 2px solid #007cba; }
h3 { color: #555; border-bottom: 1px solid #ccc; }
.success { color: green; }
.error { color: red; }
</style>
