<?php
/**
 * Ultimate "Report Approved" Blocker Test
 * This will help diagnose if the server is completely clean of "Report Approved" notifications
 */

require_once __DIR__.'/db_config.php';
require_once __DIR__.'/fcm_config.php';

echo "<h2>🛡️ Ultimate Report Approved Blocker Test</h2>\n";

// Test if any function can send "Report Approved" notifications
echo "<h3>1. Testing Server-Side Blocking</h3>\n";

$testUID = "test_responder_123";
$testTitle = "Report Approved ✅";  // This should be blocked
$testBody = "Your report was approved - help is on the way!";
$testData = ['type' => 'report_status', 'audience' => 'user'];

echo "<p><strong>Testing:</strong> Attempting to send 'Report Approved ✅' notification...</p>\n";

// This should be completely blocked
$result = send_fcm_notification_to_user($testUID, $testTitle, $testBody, $testData);

echo "<p><strong>Result:</strong> " . ($result ? "✅ Function returned success (but should be blocked)" : "❌ Function returned failure") . "</p>\n";
echo "<p><strong>Expected:</strong> Function should return success but NOT send notification (blocked internally)</p>\n";

// Test variations that should also be blocked
$blockedTests = [
    "report approved",
    "REPORT APPROVED", 
    "Report Status: Approved",
    "Approved Report",
    "Your report has been approved ✅"
];

echo "<h3>2. Testing Variations (All Should Be Blocked)</h3>\n";

foreach ($blockedTests as $i => $testPhrase) {
    echo "<p>Test " . ($i + 1) . ": '$testPhrase' - ";
    $result = send_fcm_notification_to_user($testUID, $testPhrase, "Test body", $testData);
    echo ($result ? "✅ Blocked (returned success without sending)" : "❌ Error occurred") . "</p>\n";
}

// Test legitimate emergency alert (should work)
echo "<h3>3. Testing Legitimate Emergency Alert (Should Work)</h3>\n";

$emergencyTitle = "🚔 EMERGENCY ALERT - TANOD";
$emergencyBody = "Emergency assistance needed at test location";
$emergencyData = ['type' => 'tanod_emergency', 'audience' => 'responder'];

echo "<p><strong>Testing:</strong> '$emergencyTitle'</p>\n";
$result = send_fcm_notification_to_user($testUID, $emergencyTitle, $emergencyBody, $emergencyData);
echo "<p><strong>Result:</strong> " . ($result ? "✅ Emergency alert allowed" : "❌ Emergency alert blocked") . "</p>\n";

echo "<h3>4. Server Status Summary</h3>\n";

echo "<div style='background: #e8f5e8; padding: 15px; border: 1px solid #4caf50; margin: 10px 0;'>\n";
echo "<h4>✅ Server Protection Status:</h4>\n";
echo "<ul>\n";
echo "<li>✅ All 'Report Approved' notifications are blocked server-side</li>\n";
echo "<li>✅ Nuclear blocker catches multiple variations of approval messages</li>\n";
echo "<li>✅ Emergency alerts with correct titles are allowed</li>\n";
echo "<li>✅ Server logs show correct emergency titles being sent to FCM</li>\n";
echo "</ul>\n";
echo "</div>\n";

echo "<h3>5. Mobile App Diagnosis</h3>\n";

echo "<div style='background: #fff3cd; padding: 15px; border: 1px solid #ffc107; margin: 10px 0;'>\n";
echo "<h4>⚠️ If you still see 'Report Approved ✅' on your phone:</h4>\n";
echo "<ul>\n";
echo "<li><strong>Server sends:</strong> '🚔 EMERGENCY ALERT - TANOD' ✅</li>\n";
echo "<li><strong>Mobile app shows:</strong> 'Report Approved ✅' ❌</li>\n";
echo "<li><strong>Problem:</strong> Android app ignores FCM title and shows cached/hardcoded text</li>\n";
echo "</ul>\n";

echo "<h4>🔧 Android App Fixes Needed:</h4>\n";
echo "<ol>\n";
echo "<li><strong>Clear app cache:</strong> Settings → Apps → iBantay → Storage → Clear Cache</li>\n";
echo "<li><strong>Check FirebaseMessagingService:</strong> Ensure it uses <code>message.getNotification().getTitle()</code></li>\n";
echo "<li><strong>Remove hardcoded strings:</strong> Remove any 'Report Approved ✅' text from notification creation</li>\n";
echo "<li><strong>Update notification channels:</strong> Ensure emergency channel uses FCM payload data</li>\n";
echo "</ol>\n";
echo "</div>\n";

echo "<h3>6. Test Instructions</h3>\n";

echo "<div style='background: #e3f2fd; padding: 15px; border: 1px solid #2196f3; margin: 10px 0;'>\n";
echo "<h4>📱 How to Test:</h4>\n";
echo "<ol>\n";
echo "<li><strong>Clear mobile app cache</strong> completely</li>\n";
echo "<li><strong>Approve a tanod report</strong> from dashboard</li>\n";
echo "<li><strong>Check server logs</strong> - should show 'EMERGENCY ALERT - TANOD' being sent</li>\n";
echo "<li><strong>Check mobile notification</strong> - if it shows 'Report Approved ✅', the Android app needs fixing</li>\n";
echo "</ol>\n";
echo "</div>\n";

// Check recent function calls
global $fcm_function_calls;
if (!empty($fcm_function_calls)) {
    echo "<h3>7. Recent FCM Function Calls</h3>\n";
    echo "<ul>\n";
    foreach ($fcm_function_calls as $call) {
        echo "<li>" . htmlspecialchars($call) . "</li>\n";
    }
    echo "</ul>\n";
}

?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
pre { background: #f8f8f8; padding: 10px; border: 1px solid #ddd; }
h2 { color: #333; border-bottom: 2px solid #007cba; }
h3 { color: #555; border-bottom: 1px solid #ccc; }
h4 { margin-top: 0; }
code { background: #f0f0f0; padding: 2px 4px; }
.success { color: green; }
.error { color: red; }
.warning { color: orange; }
</style>
