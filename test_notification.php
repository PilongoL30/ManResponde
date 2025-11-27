<?php
require_once __DIR__ . '/fcm_config.php';

echo "<h1>🚨 Emergency Notification Test - Using Working Old Code Structure</h1>\n";

// Test notification for debugging - replace with your responder UID
$testResponderUID = 'LLi8ELjm5KMOOteCtfGAAlOoN3e2'; // Your tanod responder UID

echo "<h2>Testing Emergency Notification</h2>\n";
echo "<p>Sending test emergency alert to responder: <strong>$testResponderUID</strong></p>\n";

// Test data - simulating a tanod emergency
$testTitle = "🚔 EMERGENCY ALERT - TANOD";
$testBody = "Test User needs immediate tanod assistance at Test Location";
$testData = [
    'type' => 'tanod_emergency',
    'emergencyType' => 'tanod',
    'reportId' => 'test_report_123',
    'collection' => 'tanod_reports',
    'reporterName' => 'Test User',
    'location' => 'Test Location',
    'description' => 'Test emergency',
    'status' => 'approved',
    'priority' => 'emergency',
    'audience' => 'responder'
];

echo "<div style='background: #f0f8ff; padding: 15px; margin: 10px 0; border-left: 4px solid #007cba;'>\n";
echo "<h3>📤 Test Data Being Sent:</h3>\n";
echo "<p><strong>Title:</strong> $testTitle</p>\n";
echo "<p><strong>Body:</strong> $testBody</p>\n";
echo "<p><strong>Emergency Type:</strong> " . $testData['emergencyType'] . "</p>\n";
echo "<p><strong>Data Payload:</strong></p>\n";
echo "<pre style='background: #f9f9f9; padding: 10px; overflow-x: auto;'>" . json_encode($testData, JSON_PRETTY_PRINT) . "</pre>\n";
echo "</div>\n";

// Clear any previous logs
$debugFile = __DIR__ . '/fcm_debug.log';
if (file_exists($debugFile)) {
    file_put_contents($debugFile, ''); // Clear the log
}

echo "<h3>⚡ Sending Emergency Alert...</h3>\n";

// Send the test notification
$result = send_emergency_fcm_notification($testResponderUID, $testTitle, $testBody, $testData);

echo "<div style='padding: 15px; margin: 10px 0;'>\n";
if ($result) {
    echo "<h3 style='color: green;'>✅ Emergency notification sent successfully!</h3>\n";
    echo "<div style='background: #d4edda; padding: 15px; border-left: 4px solid #28a745; margin: 10px 0;'>\n";
    echo "<h4>🎯 What you SHOULD receive on your device:</h4>\n";
    echo "<ul>\n";
    echo "<li><strong>Title:</strong> <code>$testTitle</code></li>\n";
    echo "<li><strong>Body:</strong> <code>$testBody</code></li>\n";
    echo "<li><strong>Sound:</strong> Emergency alarm (not normal notification)</li>\n";
    echo "<li><strong>Vibration:</strong> Strong repeated pattern</li>\n";
    echo "<li><strong>Channel:</strong> emergency_alarm_channel</li>\n";
    echo "</ul>\n";
    echo "</div>\n";
    
    echo "<div style='background: #f8d7da; padding: 15px; border-left: 4px solid #dc3545; margin: 10px 0;'>\n";
    echo "<h4>❌ What you should NOT receive:</h4>\n";
    echo "<ul>\n";
    echo "<li>❌ Title: 'Report Approved ✅' (this indicates Android app issue)</li>\n";
    echo "<li>❌ Normal notification sound</li>\n";
    echo "<li>❌ Weak single vibration</li>\n";
    echo "</ul>\n";
    echo "</div>\n";
} else {
    echo "<h3 style='color: red;'>❌ Failed to send emergency notification!</h3>\n";
    echo "<p>Check the debug log below for error details.</p>\n";
}
echo "</div>\n";

echo "<h3>📋 Server Debug Log:</h3>\n";
if (file_exists($debugFile)) {
    $logContent = file_get_contents($debugFile);
    if ($logContent) {
        echo "<pre style='background: #f8f9fa; padding: 15px; border: 1px solid #dee2e6; max-height: 400px; overflow-y: auto;'>" . htmlspecialchars($logContent) . "</pre>\n";
    } else {
        echo "<p style='color: orange;'>⚠️ Debug log is empty (notification might have failed silently)</p>\n";
    }
} else {
    echo "<p style='color: orange;'>⚠️ No debug log found</p>\n";
}

echo "<div style='background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 20px 0;'>\n";
echo "<h3>🔍 Diagnosis Instructions:</h3>\n";
echo "<ol>\n";
echo "<li><strong>If you receive the CORRECT emergency alert:</strong><br>✅ Server is working perfectly! The issue was our payload structure.</li>\n";
echo "<li><strong>If you still get 'Report Approved ✅':</strong><br>❌ Android app is intercepting the data and showing wrong title. Need to check Android FirebaseMessagingService.</li>\n";
echo "<li><strong>If you get no notification:</strong><br>⚠️ Check if FCM token is valid or if the device is connected.</li>\n";
echo "</ol>\n";
echo "</div>\n";

echo "<div style='background: #e7f3ff; padding: 15px; border-left: 4px solid #007cba; margin: 20px 0;'>\n";
echo "<h3>💡 Key Changes Made:</h3>\n";
echo "<ul>\n";
echo "<li>✅ Using exact payload structure from your working old code</li>\n";
echo "<li>✅ Title/body in both 'data' section AND 'android.notification' section</li>\n";
echo "<li>✅ Channel: 'emergency_alarm_channel' (matches old code)</li>\n";
echo "<li>✅ Sound: 'default' (not custom - lets Android handle it)</li>\n";
echo "<li>✅ Removed complex settings that might confuse Android</li>\n";
echo "</ul>\n";
echo "</div>\n";

echo "<p><a href='debug_notifications.php?test=1' style='background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;'>🔍 Run Flow Debugger</a></p>\n";
?>
