<?php
// Debug script to trace exactly what happens when approving a report
require_once __DIR__ . '/fcm_config.php';

function debug_approve_report_flow($reportId, $reportCollection = 'reports') {
    echo "<h2>🔍 Debugging Report Approval Flow</h2>\n";
    echo "<p><strong>Report ID:</strong> $reportId</p>\n";
    echo "<p><strong>Collection:</strong> $reportCollection</p>\n";
    
    // Step 1: Get report details
    echo "<h3>Step 1: Fetching Report Details</h3>\n";
    $reportDetails = get_report_details($reportId, $reportCollection);
    if (!$reportDetails) {
        echo "<p style='color: red;'>❌ Report not found!</p>\n";
        return;
    }
    
    echo "<pre>" . json_encode($reportDetails, JSON_PRETTY_PRINT) . "</pre>\n";
    
    $emergencyType = $reportDetails['emergencyType'] ?? 'unknown';
    $reporterUID = $reportDetails['uid'] ?? null;
    
    echo "<p><strong>Emergency Type:</strong> $emergencyType</p>\n";
    echo "<p><strong>Reporter UID:</strong> $reporterUID</p>\n";
    
    // Step 2: Get responders for this category
    echo "<h3>Step 2: Finding Category Responders</h3>\n";
    $responders = get_responders_for_category($emergencyType);
    
    if (empty($responders)) {
        echo "<p style='color: orange;'>⚠️ No responders found for category: $emergencyType</p>\n";
        return;
    }
    
    echo "<p><strong>Found " . count($responders) . " responders for '$emergencyType':</strong></p>\n";
    foreach ($responders as $responder) {
        $uid = $responder['uid'] ?? 'No UID';
        $name = $responder['name'] ?? 'No Name';
        $categories = $responder['categories'] ?? [];
        echo "<li>$name (UID: $uid) - Categories: " . implode(', ', $categories) . "</li>\n";
    }
    
    // Step 3: Simulate notification sending
    echo "<h3>Step 3: Simulating Emergency Notification</h3>\n";
    
    $title = get_emergency_title($emergencyType);
    $body = ($reportDetails['reporterName'] ?? 'Someone') . " needs immediate $emergencyType assistance at " . ($reportDetails['location'] ?? 'unknown location');
    
    $data = [
        'type' => $emergencyType . '_emergency',
        'emergencyType' => $emergencyType,
        'reportId' => $reportId,
        'collection' => $reportCollection,
        'reporterName' => $reportDetails['reporterName'] ?? 'Unknown',
        'location' => $reportDetails['location'] ?? 'Unknown',
        'description' => $reportDetails['description'] ?? '',
        'status' => 'approved',
        'priority' => 'emergency',
        'audience' => 'responder'
    ];
    
    echo "<p><strong>Emergency Alert Details:</strong></p>\n";
    echo "<p><strong>Title:</strong> $title</p>\n";
    echo "<p><strong>Body:</strong> $body</p>\n";
    echo "<p><strong>Data Payload:</strong></p>\n";
    echo "<pre>" . json_encode($data, JSON_PRETTY_PRINT) . "</pre>\n";
    
    // Step 4: Check for blocks
    echo "<h3>Step 4: Checking Protection Blocks</h3>\n";
    foreach ($responders as $responder) {
        $responderUID = $responder['uid'];
        $responderName = $responder['name'] ?? 'Unknown';
        
        echo "<p><strong>Checking responder: $responderName ($responderUID)</strong></p>\n";
        
        // Check if responder is the reporter
        if ($responderUID === $reporterUID) {
            echo "<li style='color: orange;'>⚠️ BLOCKED: Responder is the reporter</li>\n";
            continue;
        }
        
        echo "<li style='color: green;'>✅ ALLOWED: Will receive emergency alert</li>\n";
    }
    
    echo "<h3>🎯 Expected Outcome</h3>\n";
    echo "<p>Each eligible responder should receive:</p>\n";
    echo "<ul>\n";
    echo "<li><strong>Title:</strong> $title</li>\n";
    echo "<li><strong>Body:</strong> $body</li>\n";
    echo "<li><strong>Sound:</strong> Emergency siren</li>\n";
    echo "<li><strong>Vibration:</strong> Strong pattern</li>\n";
    echo "<li><strong>NOT:</strong> 'Report Approved ✅'</li>\n";
    echo "</ul>\n";
}

function get_emergency_title($emergencyType) {
    $titles = [
        'fire' => '🔥 EMERGENCY ALERT - FIRE',
        'medical' => '🚑 EMERGENCY ALERT - MEDICAL',
        'tanod' => '🚔 EMERGENCY ALERT - TANOD',
        'accident' => '🚨 EMERGENCY ALERT - ACCIDENT',
        'rescue' => '⛑️ EMERGENCY ALERT - RESCUE'
    ];
    
    return $titles[$emergencyType] ?? '🚨 EMERGENCY ALERT';
}

function get_report_details($reportId, $collection) {
    // This would normally fetch from Firestore
    // For testing, you can manually input report details
    return [
        'emergencyType' => 'tanod',
        'reporterName' => 'Test Reporter',
        'location' => 'Test Location',
        'description' => 'Test emergency situation',
        'uid' => 'test_reporter_uid'
    ];
}

// Usage examples:
echo "<h1>🚨 Emergency Notification Debug Tool</h1>\n";

// Test with a sample report
if (isset($_GET['test'])) {
    debug_approve_report_flow('test_report_123', 'reports');
}

if (isset($_GET['reportId'])) {
    debug_approve_report_flow($_GET['reportId'], $_GET['collection'] ?? 'reports');
}

echo "<h3>Usage:</h3>\n";
echo "<ul>\n";
echo "<li><a href='?test=1'>Run Test Debug</a></li>\n";
echo "<li>Or use: <code>?reportId=YOUR_REPORT_ID&collection=reports</code></li>\n";
echo "</ul>\n";
?>
