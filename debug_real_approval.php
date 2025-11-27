<?php
// Real-time debug tool for tracking report approval
require_once __DIR__ . '/dashboard.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['debug_approve'])) {
    $collection = $_POST['collection'] ?? 'tanod_reports';
    $docId = $_POST['docId'] ?? '';
    
    if ($docId) {
        echo "<h1>🔍 DEBUGGING REAL REPORT APPROVAL</h1>\n";
        echo "<p><strong>Collection:</strong> $collection</p>\n";
        echo "<p><strong>Report ID:</strong> $docId</p>\n";
        
        // Clear debug log
        $debugFile = __DIR__ . '/fcm_debug.log';
        file_put_contents($debugFile, "=== DEBUG REAL APPROVAL START ===\n");
        
        echo "<h3>📋 Before Approval:</h3>\n";
        
        // Simulate the approval process with full debugging
        error_log("🔍 DEBUG: Starting real report approval simulation");
        error_log("🔍 DEBUG: About to call send_fcm_notification_for_approved_report($collection, $docId)");
        
        $result = send_fcm_notification_for_approved_report($collection, $docId, false);
        
        echo "<h3>📋 Approval Result:</h3>\n";
        echo "<p><strong>Result:</strong> " . ($result ? '✅ Success' : '❌ Failed') . "</p>\n";
        
        echo "<h3>📋 Debug Log:</h3>\n";
        if (file_exists($debugFile)) {
            echo "<pre style='background: #f8f9fa; padding: 15px; border: 1px solid #dee2e6; max-height: 400px; overflow-y: auto;'>" . htmlspecialchars(file_get_contents($debugFile)) . "</pre>\n";
        }
        
        echo "<h3>🎯 What Should Happen:</h3>\n";
        echo "<ul>\n";
        echo "<li>✅ Tanod responders should get: <strong>'🚔 EMERGENCY ALERT - TANOD'</strong></li>\n";
        echo "<li>✅ Emergency siren and strong vibration</li>\n";
        echo "<li>❌ NO ONE should get: <strong>'Report Approved ✅'</strong></li>\n";
        echo "</ul>\n";
        
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Debug Real Report Approval</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .form-group { margin: 15px 0; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .btn { background: #007cba; color: white; padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        .btn:hover { background: #005a87; }
        .warning { background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 20px 0; }
        .info { background: #e7f3ff; padding: 15px; border-left: 4px solid #007cba; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Debug Real Report Approval</h1>
        
        <div class="warning">
            <strong>⚠️ Warning:</strong> This will send real FCM notifications to responders.
            Use this only to debug the "Report Approved ✅" issue.
        </div>
        
        <div class="info">
            <strong>💡 Purpose:</strong> This tool will approve a real report and show you exactly what FCM notifications are sent.
            Compare what the server sends vs. what appears on your responder device.
        </div>
        
        <form method="POST">
            <div class="form-group">
                <label for="collection">Collection:</label>
                <select name="collection" id="collection" required>
                    <option value="tanod_reports">Tanod Reports</option>
                    <option value="fire_reports">Fire Reports</option>
                    <option value="ambulance_reports">Ambulance Reports</option>
                    <option value="flood_reports">Flood Reports</option>
                    <option value="other_reports">Other Reports</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="docId">Report ID (from your database):</label>
                <input type="text" name="docId" id="docId" placeholder="e.g., 1XTzZbE7V4N6J8F2L9M3" required>
                <small>Get this from your Firestore console or dashboard</small>
            </div>
            
            <button type="submit" name="debug_approve" class="btn">🔍 Debug Real Approval</button>
        </form>
        
        <h3>🎯 Expected Results:</h3>
        <ul>
            <li><strong>Server should send:</strong> "🚔 EMERGENCY ALERT - TANOD" (for tanod reports)</li>
            <li><strong>Your device should receive:</strong> Emergency alert with siren/vibration</li>
            <li><strong>Your device should NOT receive:</strong> "Report Approved ✅"</li>
        </ul>
        
        <h3>📋 How to Use:</h3>
        <ol>
            <li>Go to your Firestore console</li>
            <li>Find a pending report in tanod_reports collection</li>
            <li>Copy the document ID</li>
            <li>Enter it above and click "Debug Real Approval"</li>
            <li>Check what notification appears on your responder device</li>
        </ol>
        
        <p><a href="test_notification.php" style="background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;">🧪 Test Notification</a></p>
    </div>
</body>
</html>
