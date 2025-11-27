<?php
// Direct integration of working test code into real approval
require_once __DIR__ . '/fcm_config.php';

/**
 * WORKING VERSION - Send emergency notification directly like test_notification.php
 * This bypasses the complex send_fcm_notification_for_approved_report() function
 */
function send_emergency_notification_directly(string $collection, string $docId): bool {
    try {
        error_log("🔥 DIRECT: Starting direct emergency notification like test_notification.php");
        
        // Get the report details
        $report = firestore_get_doc_by_id($collection, $docId);
        if (!$report) {
            error_log("🔥 DIRECT: Report not found: $collection/$docId");
            return false;
        }
        
        // Map collection to emergency type and labels - SAME AS WORKING TEST
        $collectionMap = [
            'ambulance_reports' => ['type' => 'ambulance', 'emoji' => '🚑', 'label' => 'AMBULANCE'],
            'fire_reports' => ['type' => 'fire', 'emoji' => '🔥', 'label' => 'FIRE'],
            'flood_reports' => ['type' => 'flood', 'emoji' => '🌊', 'label' => 'FLOOD'],
            'tanod_reports' => ['type' => 'tanod', 'emoji' => '🚔', 'label' => 'TANOD'],
            'other_reports' => ['type' => 'other', 'emoji' => '🚨', 'label' => 'EMERGENCY']
        ];
        
        $emergencyInfo = $collectionMap[$collection] ?? ['type' => 'other', 'emoji' => '🚨', 'label' => 'EMERGENCY'];
        $emergencyType = $emergencyInfo['type'];
        $emoji = $emergencyInfo['emoji'];
        $label = $emergencyInfo['label'];
        
        // Get responders - SAME AS TEST
        $responders = get_responders_for_category($emergencyType);
        if (empty($responders)) {
            error_log("🔥 DIRECT: No responders found for emergency type: {$emergencyType}");
            return false;
        }
        
        // Prepare notification content - EXACT SAME AS TEST
        $reporterName = $report['fullName'] ?? $report['reporterName'] ?? 'Unknown';
        $location = $report['location'] ?? 'Unknown location';
        
        $title = "{$emoji} EMERGENCY ALERT - {$label}";
        $body = "{$reporterName} needs immediate {$emergencyType} assistance at {$location}";
        
        // EXACT SAME DATA STRUCTURE AS WORKING TEST
        $data = [
            'type' => $emergencyType . '_emergency',
            'emergencyType' => $emergencyType,
            'reportId' => $docId,
            'collection' => $collection,
            'reporterName' => $reporterName,
            'location' => $location,
            'description' => $report['purpose'] ?? $report['description'] ?? 'Test emergency',
            'status' => 'approved',
            'priority' => 'emergency',
            'audience' => 'responder'
        ];
        
        error_log("🔥 DIRECT: About to send SAME notification as working test:");
        error_log("🔥 DIRECT: Title: $title");
        error_log("🔥 DIRECT: Body: $body");
        error_log("🔥 DIRECT: Data: " . json_encode($data));
        
        $successCount = 0;
        foreach ($responders as $responder) {
            $uid = $responder['uid'] ?? '';
            if (!$uid) continue;
            
            error_log("🔥 DIRECT: Sending to responder $uid using EXACT SAME CODE as test_notification.php");
            
            // USE THE EXACT SAME FUNCTION CALL AS THE WORKING TEST
            if (send_emergency_fcm_notification($uid, $title, $body, $data)) {
                $successCount++;
                error_log("🔥 DIRECT: ✅ SUCCESS - Emergency alert sent to $uid (SAME AS TEST)");
            } else {
                error_log("🔥 DIRECT: ❌ FAILED - Emergency alert failed for $uid");
            }
        }
        
        error_log("🔥 DIRECT: Completed direct notification - sent to $successCount responders");
        return $successCount > 0;
        
    } catch (Exception $e) {
        error_log("🔥 DIRECT: Error in direct emergency notification: " . $e->getMessage());
        return false;
    }
}

// Test this function
if (isset($_GET['test_direct'])) {
    $collection = $_GET['collection'] ?? 'tanod_reports';
    $docId = $_GET['docId'] ?? 'test_123';
    
    echo "<h1>🔥 Testing Direct Integration</h1>";
    echo "<p>Collection: $collection</p>";
    echo "<p>Doc ID: $docId</p>";
    
    $result = send_emergency_notification_directly($collection, $docId);
    
    echo "<h3>Result: " . ($result ? "✅ Success" : "❌ Failed") . "</h3>";
    
    if (file_exists(__DIR__ . '/fcm_debug.log')) {
        echo "<h3>Debug Log:</h3>";
        echo "<pre>" . htmlspecialchars(file_get_contents(__DIR__ . '/fcm_debug.log')) . "</pre>";
    }
}
?>

<h1>🔥 Direct Integration Test</h1>

<p><strong>Purpose:</strong> This uses the EXACT SAME code as the working test_notification.php but for real reports.</p>

<form>
    <p>
        <label>Collection:</label>
        <select name="collection">
            <option value="tanod_reports">Tanod Reports</option>
            <option value="fire_reports">Fire Reports</option>
            <option value="ambulance_reports">Ambulance Reports</option>
        </select>
    </p>
    <p>
        <label>Report ID:</label>
        <input type="text" name="docId" placeholder="Enter real report ID" required>
    </p>
    <button type="submit" name="test_direct">🔥 Test Direct Integration</button>
</form>

<h3>💡 Theory:</h3>
<ul>
<li>✅ test_notification.php works because it calls <code>send_emergency_fcm_notification()</code> directly</li>
<li>❌ Real approval fails because <code>send_fcm_notification_for_approved_report()</code> does something different</li>
<li>🔥 This test uses the EXACT same code as the working test but for real reports</li>
</ul>
