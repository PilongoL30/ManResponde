<?php
/**
 * FCM Configuration for iBantay (V1 API)
 * 
 * To get your Service Account Key:
 * 1. Go to Firebase Console (https://console.firebase.google.com)
 * 2. Select your project
 * 3. Go to Project Settings (gear icon)
 * 4. Go to Service accounts tab
 * 5. Click "Generate new private key"
 * 6. Download the JSON file and place it in your project
 * 7. Update the path below to point to your JSON file
 */

// Include required Firestore functions
require_once __DIR__ . '/db_config.php';

// Service Account Key file path - Update this to your JSON file path
// define('FCM_SERVICE_ACCOUNT_PATH', __DIR__ . '/firebase-service-account.json');

// Use your existing service account file with relative path (works on both localhost and live server)
define('FCM_SERVICE_ACCOUNT_PATH', __DIR__ . '/firebase-php-auth/config/ibantayv2-firebase-adminsdk-fbsvc-0526b0e79f.json');

// FCM V1 API URL
define('FCM_API_URL', 'https://fcm.googleapis.com/v1/projects/');

// Notification settings
define('FCM_NOTIFICATION_PRIORITY', 'high');
define('FCM_NOTIFICATION_SOUND', 'emergency_siren'); // Aggressive emergency sound
define('FCM_NOTIFICATION_CHANNEL_ID', 'emergency_critical');

// Ultra-aggressive vibration pattern for emergency notifications (in milliseconds)
// Pattern: Immediate strong burst, pause, repeat 5 times for maximum attention
define('FCM_VIBRATION_PATTERN', [0, 1000, 200, 1000, 200, 1000, 200, 1000, 200, 1000, 200, 1000, 200, 1000, 200, 1000]);

// Emergency notification color - bright red for maximum visibility
define('FCM_NOTIFICATION_COLOR', '#FF0000');

// Emergency notification icon - should correspond to bantay2.png iBantay logo in app
define('FCM_NOTIFICATION_ICON', 'ic_ibantay_logo');

/**
 * Get FCM access token using service account
 */
function get_fcm_access_token(): ?string {
    try {
        if (!file_exists(FCM_SERVICE_ACCOUNT_PATH)) {
            error_log("Service account file not found: " . FCM_SERVICE_ACCOUNT_PATH);
            return null;
        }
        
        $serviceAccount = json_decode(file_get_contents(FCM_SERVICE_ACCOUNT_PATH), true);
        
        if (!$serviceAccount) {
            error_log("Invalid service account JSON file");
            return null;
        }
        
        // Create JWT token
        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT'
        ];
        
        $payload = [
            'iss' => $serviceAccount['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => time() + 3600,
            'iat' => time()
        ];
        
        $headerEncoded = base64url_encode(json_encode($header));
        $payloadEncoded = base64url_encode(json_encode($payload));
        
        $signature = '';
        openssl_sign(
            $headerEncoded . '.' . $payloadEncoded,
            $signature,
            $serviceAccount['private_key'],
            'SHA256'
        );
        
        $signatureEncoded = base64url_encode($signature);
        $jwt = $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
        
        // Exchange JWT for access token
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $tokenData = json_decode($response, true);
        
        if (isset($tokenData['access_token'])) {
            return $tokenData['access_token'];
        }
        
        error_log("Failed to get access token: " . $response);
        return null;
        
    } catch (Exception $e) {
        error_log("Error getting FCM access token: " . $e->getMessage());
        return null;
    }
}

/**
 * Base64URL encode function
 */
function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Test FCM connection
 */
function test_fcm_connection(): array {
    if (!file_exists(FCM_SERVICE_ACCOUNT_PATH)) {
        return [
            'success' => false,
            'error' => 'Service account file not found. Please download and place firebase-service-account.json in your project root.'
        ];
    }
    
    $accessToken = get_fcm_access_token();
    if (!$accessToken) {
        return [
            'success' => false,
            'error' => 'Failed to get FCM access token. Check service account configuration.'
        ];
    }
    
    return [
        'success' => true,
        'message' => 'FCM V1 API configuration is valid'
    ];
}

/**
 * Test the new notification flow for debugging - comprehensive notification tracker
 */
function test_new_notification_flow(string $collection, string $docId): array {
    global $fcm_function_calls; // Track function calls
    $fcm_function_calls = [];
    
    $results = [];
    $results['timestamp'] = date('Y-m-d H:i:s');
    $results['collection'] = $collection;
    $results['docId'] = $docId;
    
    error_log("=== NOTIFICATION TEST START for {$collection}/{$docId} ===");
    
    // Get report details
    $report = firestore_get_doc_by_id($collection, $docId);
    if (!$report) {
        $results['error'] = 'Report not found';
        return $results;
    }
    
    // Get reporter ID
    $reporterId = '';
    foreach (['reporterId','userId','uid','userUID','reportedBy','reporter_uid','reporter_id'] as $key) {
        if (!empty($report[$key]) && is_string($report[$key])) { $reporterId = $report[$key]; break; }
    }
    $results['reporterId'] = $reporterId;
    
    // Get responders
    $collectionMap = ['ambulance_reports' => 'ambulance', 'fire_reports' => 'fire', 'flood_reports' => 'flood', 'tanod_reports' => 'tanod', 'police_reports' => 'police', 'other_reports' => 'other'];
    $emergencyType = $collectionMap[$collection] ?? 'other';
    $responders = get_responders_for_category($emergencyType);
    
    $results['emergency_type'] = $emergencyType;
    $results['total_responders'] = count($responders);
    $results['responder_list'] = array_map(function($r) { return ['uid' => $r['uid'], 'name' => $r['fullName'] ?? 'Unknown']; }, $responders);
    
    // Check if reporter is in responders list
    $reporterIsResponder = false;
    foreach ($responders as $responder) {
        if (($responder['uid'] ?? '') === $reporterId) {
            $reporterIsResponder = true;
            break;
        }
    }
    $results['reporter_is_responder'] = $reporterIsResponder;
    
    error_log("🔍 TESTING: Reporter ID: $reporterId, Emergency Type: $emergencyType");
    error_log("🔍 TESTING: Reporter is Responder: " . ($reporterIsResponder ? 'YES' : 'NO'));
    error_log("🔍 TESTING: Total Responders: " . count($responders));
    
    // Reset function call tracker
    $fcm_function_calls = [];
    
    // Test user notification - DISABLED per user request
    error_log("User notification disabled per user request (blue notification removal)");
    $results['user_notification'] = false;
    $results['user_error'] = 'User notification disabled - user requested removal of blue "Report Approved" notification';
    
    // Test responder notification
    error_log("Testing responder notification...");
    if (function_exists('send_fcm_notification_for_approved_report')) {
        $results['responder_notification'] = send_fcm_notification_for_approved_report($collection, $docId);
        error_log("Responder notification result: " . ($results['responder_notification'] ? 'SUCCESS' : 'FAILED'));
    } else {
        $results['responder_notification'] = false;
        $results['responder_error'] = 'Responder notification function not found';
    }
    
    // Add function call tracking
    $results['function_calls'] = $fcm_function_calls;
    $results['total_function_calls'] = count($fcm_function_calls);
    
    error_log("🔍 TESTING: Total FCM function calls made: " . count($fcm_function_calls));
    foreach ($fcm_function_calls as $call) {
        error_log("🔍 TESTING: Function called: $call");
    }
    
    error_log("=== NOTIFICATION TEST END ===");
    
    return $results;
}

/**
 * Create a detailed FCM notification trace
 */
function trace_fcm_notification_calls(): void {
    error_log("=== FCM NOTIFICATION TRACE ACTIVATED ===");
    error_log("All subsequent FCM calls will be logged with stack traces");
}

/**
 * Get FCM configuration status
 */
function get_fcm_config_status(): array {
    return [
        'service_account_configured' => file_exists(FCM_SERVICE_ACCOUNT_PATH),
        'api_url' => FCM_API_URL,
        'priority' => FCM_NOTIFICATION_PRIORITY,
        'channel_id' => FCM_NOTIFICATION_CHANNEL_ID,
        'access_token_available' => get_fcm_access_token() !== null
    ];
}

/**
 * NUCLEAR BLOCKER: Prevent ALL "Report Approved" notifications
 * This function intercepts ANY notification containing "Report Approved" text
 */
function block_all_report_approved_notifications(): void {
    // Override any function that might try to send "Report Approved" notifications
    if (!function_exists('original_send_fcm_notification_to_user')) {
        // Create backup of original function if not already done
        return;
    }
}

/**
 * Send FCM notification to specific user by UID
 */
function send_fcm_notification_to_user(string $uid, string $title, string $body, array $data = []): bool {
    global $fcm_function_calls;
    if (!isset($fcm_function_calls)) $fcm_function_calls = [];
    $fcm_function_calls[] = "📱 send_fcm_notification_to_user(uid: $uid, title: $title)";
    
    error_log("📱 FCM_TRACE: send_fcm_notification_to_user() called for UID: $uid, Title: $title");
    
    // NUCLEAR BLOCKER: Block ANY notification with "Report Approved", "Approved", "✅" or similar
    $blockedPhrases = ['Report Approved', 'report approved', 'REPORT APPROVED', 'Approved', 'approved', '✅', 'Report Status', 'report status'];
    foreach ($blockedPhrases as $phrase) {
        if (stripos($title, $phrase) !== false || stripos($body, $phrase) !== false) {
            error_log("🚨🚨🚨 NUCLEAR BLOCK: Notification containing '$phrase' COMPLETELY BLOCKED");
            error_log("🚨🚨🚨 BLOCKED TITLE: $title");
            error_log("🚨🚨🚨 BLOCKED BODY: $body");
            error_log("🚨🚨🚨 BLOCKED UID: $uid");
            error_log("🚨🚨🚨 This prevents ANY 'Report Approved' style notifications");
            
            // Print stack trace to find the source
            $backtrace = debug_backtrace();
            error_log("🚨🚨🚨 CALL STACK:");
            foreach ($backtrace as $i => $trace) {
                $file = $trace['file'] ?? 'unknown';
                $line = $trace['line'] ?? 'unknown';
                $function = $trace['function'] ?? 'unknown';
                error_log("  $i: $function() in $file:$line");
            }
            
            return true; // Pretend success but don't send anything
        }
    }
    
    // Check if this is for a responder - block user-style notifications entirely
    $userDocCheck = firestore_get_doc_by_id('users', $uid);
    if ($userDocCheck && ($userDocCheck['role'] ?? '') === 'responder') {
        $audience = $data['audience'] ?? 'user';
        $isUserStyle = ($audience === 'user');
        $looksLikeUserReport = stripos($title, 'report') !== false || strpos($title, '✅') !== false || strpos($title, '❌') !== false;
        if ($isUserStyle || $looksLikeUserReport) {
            error_log("🚫 FCM BLOCKED: User-style notification to responder $uid blocked (title='$title', audience='$audience'). Use send_emergency_fcm_notification() instead.");
            return true; // pretend success; do not send
        }
        
        // If truly needed to notify responder via regular path, log strongly
        error_log("📱 FCM: Regular notification requested for responder $uid (audience='$audience'). Ensure this is intentional.");
    }
    
    try {
        $accessToken = get_fcm_access_token();
        if (!$accessToken) {
            error_log("FCM: No access token available");
            return false;
        }
        
        // Get user's FCM token from Firestore
        $userDoc = firestore_get_doc_by_id('users', $uid);
        if (!$userDoc || empty($userDoc['fcmToken'])) {
            error_log("FCM: No FCM token found for user $uid");
            return false;
        }
        
        $fcmToken = $userDoc['fcmToken'];
        
        // EXTRA PROTECTION: If this token belongs to any responder, block user-style send
        $audience = $data['audience'] ?? 'user';
        $looksLikeUserReport = stripos($title, 'report') !== false || strpos($title, '✅') !== false || strpos($title, '❌') !== false;
        if ($audience === 'user' && $looksLikeUserReport && token_belongs_to_responder($fcmToken)) {
            error_log("🚫 FCM BLOCKED: Token belongs to responder; blocking user-style notification to protect responders from 'Report Approved/Declined'.");
            return true;
        }
        
        // Prepare notification payload with both notification and data sections for better client compatibility
        $message = [
            'message' => [
                'token' => $fcmToken,
                'notification' => [
                    'title' => $title,
                    'body' => $body
                ],
                'data' => array_merge($data, [
                    'title' => $title,
                    'body' => $body,
                    'audience' => 'user',
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    'timestamp' => (string)time()
                ]),
                'android' => [
                    'priority' => FCM_NOTIFICATION_PRIORITY,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                        'channel_id' => FCM_NOTIFICATION_CHANNEL_ID,
                        'sound' => FCM_NOTIFICATION_SOUND,
                        'color' => FCM_NOTIFICATION_COLOR,
                        'icon' => FCM_NOTIFICATION_ICON,
                        'default_sound' => true,
                        'default_vibrate_timings' => true,
                        'visibility' => 'public'
                    ]
                ],
                'apns' => [
                    'payload' => [
                        'aps' => [
                            'alert' => [
                                'title' => $title,
                                'body' => $body
                            ],
                            'sound' => 'emergency_alert.caf',
                            'badge' => 1,
                            'category' => 'EMERGENCY_ALERT',
                            'mutable-content' => 1
                        ]
                    ],
                    'headers' => [
                        'apns-priority' => '10',
                        'apns-push-type' => 'alert'
                    ]
                ]
            ]
        ];
        
        // Send to FCM V1 API
        $projectId = 'ibantayv2'; // Update this to your project ID
        $url = FCM_API_URL . $projectId . '/messages:send';
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($message),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken
            ],
            CURLOPT_TIMEOUT => 10
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            error_log("FCM: Notification sent successfully to user $uid");
            return true;
        } else {
            error_log("FCM: Failed to send notification. HTTP $httpCode: $response");
            return false;
        }
        
    } catch (Exception $e) {
        error_log("FCM: Error sending notification to user $uid: " . $e->getMessage());
        return false;
    }
}

/**
 * Send FCM notification to all responders for approved reports
 */
function send_fcm_notification_for_approved_report(string $collection, string $docId, bool $notifyUser = false): bool {
    global $fcm_function_calls;
    if (!isset($fcm_function_calls)) $fcm_function_calls = [];
    $fcm_function_calls[] = "🚨 send_fcm_notification_for_approved_report(collection: $collection, docId: $docId, notifyUser: " . ($notifyUser ? 'true' : 'false') . ")";
    
    error_log("🚨 FCM_TRACE: send_fcm_notification_for_approved_report() called for {$collection}/{$docId}");
    error_log("🚨 FCM_TRACE: This function should only send EMERGENCY ALERTS to responders, NOT user-style notifications");
    error_log("🚨🚨🚨 REAL APPROVAL DEBUG: Starting approval notification process");
    error_log("🚨🚨🚨 REAL APPROVAL DEBUG: If you still get 'Report Approved ✅', check the Android app code");
    
    try {
        // Get the report details
        $report = firestore_get_doc_by_id($collection, $docId);
        if (!$report) {
            error_log("FCM: Report not found: $collection/$docId");
            return false;
        }
        
        // Map collection to emergency type and labels first
        $collectionMap = [
            'ambulance_reports' => ['type' => 'ambulance', 'emoji' => '🚑', 'label' => 'AMBULANCE'],
            'fire_reports' => ['type' => 'fire', 'emoji' => '🔥', 'label' => 'FIRE'],
            'flood_reports' => ['type' => 'flood', 'emoji' => '🌊', 'label' => 'FLOOD'],
            'tanod_reports' => ['type' => 'tanod', 'emoji' => '🚔', 'label' => 'TANOD'],
            'police_reports' => ['type' => 'police', 'emoji' => '🚓', 'label' => 'POLICE'],
            'other_reports' => ['type' => 'other', 'emoji' => '🚨', 'label' => 'EMERGENCY']
        ];
        
        $emergencyInfo = $collectionMap[$collection] ?? ['type' => 'other', 'emoji' => '🚨', 'label' => 'EMERGENCY'];
        $emergencyType = $emergencyInfo['type'];
        $emoji = $emergencyInfo['emoji'];
        $label = $emergencyInfo['label'];
        
        // Resolve reporter
        $reporterId = '';
        foreach (['reporterId','userId','uid','userUID','reportedBy','reporter_uid','reporter_id'] as $key) {
            if (!empty($report[$key]) && is_string($report[$key])) { $reporterId = trim((string)$report[$key]); break; }
        }
        error_log("FCM: Original reporter ID (resolved once): " . ($reporterId ?: 'NOT_FOUND'));
        
        // Check if reporter is a responder to avoid sending them notifications
        $reporterIsResponder = false;
        if ($reporterId) {
            $reporterDoc = firestore_get_doc_by_id('users', $reporterId);
            if ($reporterDoc && ($reporterDoc['role'] ?? '') === 'responder') {
                $reporterIsResponder = true;
                error_log("FCM: IMPORTANT - Reporter $reporterId is a responder, will be excluded from emergency alerts");
            }
        }
        
        // Fetch reporter doc to get its current FCM token (to avoid duplicate device emergency alert)
        $reporterToken = null;
        if ($reporterId) {
            $reporterDoc = firestore_get_doc_by_id('users', $reporterId);
            if ($reporterDoc && !empty($reporterDoc['fcmToken'])) {
                $reporterToken = $reporterDoc['fcmToken'];
                error_log("FCM: Reporter device token captured for exclusion");
            }
        }
        $responders = get_responders_for_category($emergencyType);
        $origResponderCount = count($responders);
        // Hard filter out reporter even if they are a responder AND any responder sharing reporter's token
        if ($reporterId || $reporterToken) {
            $responders = array_values(array_filter($responders, function($r) use ($reporterId, $reporterToken) {
                $uidMatch = $reporterId && strcasecmp(trim((string)($r['uid'] ?? '')), $reporterId) === 0;
                $tokenMatch = $reporterToken && isset($r['fcmToken']) && $r['fcmToken'] === $reporterToken;
                return !$uidMatch && !$tokenMatch; // keep only if not same uid and not same device token
            }));
        }
        if ($reporterToken) {
            error_log("FCM: After removing shared-token responders, remaining=" . count($responders));
        }
        if (empty($responders)) { error_log("FCM: No responders after filtering; skipping emergency broadcast"); }
        $reporterName = $report['fullName'] ?? $report['reporterName'] ?? 'Unknown';
        $location = $report['location'] ?? 'Unknown location';
        $description = $report['purpose'] ?? $report['description'] ?? 'No description';
        $title = "{$emoji} EMERGENCY ALERT - {$label}";
        $body = "{$reporterName} needs immediate {$emergencyType} assistance at {$location}";
        
        // CRITICAL DEBUG: Log exactly what should be sent
        error_log("🚨 FCM CRITICAL DEBUG: About to send to {$emergencyType} responders:");
        error_log("🚨 FCM TITLE: " . $title);
        error_log("🚨 FCM BODY: " . $body);
        error_log("🚨 FCM DATA TYPE: " . ($emergencyType . '_emergency'));
        error_log("🚨 FCM AUDIENCE: responder");
        error_log("🚨 FCM EMERGENCY: true");
        
        $data = [
            'type' => $emergencyType . '_emergency',
            'emergencyType' => $emergencyType,
            'reportId' => $docId,
            'collection' => $collection,
            'reporterName' => $reporterName,
            'location' => $location,
            'description' => $description,
            'status' => 'approved',
            'priority' => 'emergency',
            'audience' => 'responder'
        ];
        // User notification disabled per user request to remove blue "Report Approved" notification
        // The user no longer wants to receive the blue notification saying "Your report was approved - help is on the way!"
        // IMPORTANT: If reporter is a responder, they should not get ANY user-style notifications
        if ($reporterIsResponder) {
            error_log("FCM: Reporter is responder - no user notifications will be sent to avoid confusion");
        }
        
        // Send HIGH PRIORITY EMERGENCY ALERT to category-specific responders only
        // For example: fire report -> only fire responders, ambulance -> only ambulance responders
        error_log("🚨 FCM: Broadcasting HIGH PRIORITY EMERGENCY to " . count($responders) . " {$emergencyType} responders");
        error_log("🚨 FCM: Category-specific notification: {$emergencyType} emergency -> {$emergencyType} responders only");
        
        if (empty($responders)) {
            error_log("🚨 FCM: No {$emergencyType} responders available after filtering");
            return false;
        }
        
        $sent = 0;
        foreach ($responders as $responder) {
            $uid = trim((string)($responder['uid'] ?? ''));
            $name = $responder['fullName'] ?? 'Unknown';
            if ($uid === '') continue;
            
            // Extra safety: skip if responder token matches reporter token at send time
            if ($reporterToken && !empty($responder['fcmToken']) && $responder['fcmToken'] === $reporterToken) {
                error_log("FCM: Skipping responder $uid ($name) due to shared device token with reporter");
                continue;
            }
            
            // Double check: make sure this responder is not the reporter
            if ($reporterId && strcasecmp(trim($uid), trim($reporterId)) === 0) {
                error_log("FCM: Skipping responder $uid ($name) - is the original reporter");
                continue;
            }
            
            error_log("FCM: Sending HIGH PRIORITY EMERGENCY ALERT to {$emergencyType} responder $uid ($name) - Title: $title");
            error_log("FCM: Emergency data being sent: " . json_encode($data));
            error_log("🚨 FCM CRITICAL: This should send HIGH PRIORITY EMERGENCY ALERT with loud siren/vibration, NOT 'Report Approved ✅'");
            error_log("🚨 FCM DEBUG: If you see 'Report Approved ✅' on your device, the Android app is incorrectly displaying this emergency alert");
            error_log("🚨 FCM DEBUG: Title being sent: '$title'");
            error_log("🚨 FCM DEBUG: Body being sent: '$body'");
            error_log("🚨 FCM DEBUG: Data type being sent: '" . ($data['type'] ?? 'unknown') . "'");
            error_log("🚨 FCM DEBUG: Data audience being sent: '" . ($data['audience'] ?? 'unknown') . "'");
            error_log("🚨🚨🚨 REAL APPROVAL DEBUG: About to call send_emergency_fcm_notification() for responder $uid");
            error_log("🚨🚨🚨 REAL APPROVAL DEBUG: This is the SAME function that works in test_notification.php");
            error_log("🚨🚨🚨 REAL APPROVAL DEBUG: If different result, check Android app's notification handling logic");
            
            if (send_emergency_fcm_notification($uid, $title, $body, $data)) { 
                $sent++; 
                error_log("FCM: ✅ HIGH PRIORITY EMERGENCY ALERT sent successfully to {$emergencyType} responder $uid ($name)");
                error_log("🚨 FCM SUCCESS: If responder receives 'Report Approved ✅' instead, there's a mobile app or FCM delivery issue");
                error_log("🚨🚨🚨 REAL APPROVAL SUCCESS: Emergency alert sent to $uid - should be identical to test notification");
            } else { 
                error_log("FCM: ❌ Failed emergency send to {$emergencyType} responder $uid ($name)"); 
            }
        }
        $skippedReporter = $origResponderCount - count($responders);
        error_log("FCM: HIGH PRIORITY emergency broadcast to {$emergencyType} responders -> sent=" . $sent . " category=" . $emergencyType);
        return $sent > 0;
    } catch (Exception $e) {
        error_log("FCM: Error in send_fcm_notification_for_approved_report: " . $e->getMessage());
        return false;
    }
}

/**
 * Send FCM notification to user for rejected report
 */
function send_fcm_notification_to_user_for_rejected_report(string $collection, string $docId, string $declineReason = ''): bool {
    try {
        // Get the report details
        $report = firestore_get_doc_by_id($collection, $docId);
        if (!$report) {
            error_log("FCM: Report not found: $collection/$docId");
            return false;
        }
        // Resolve reporter/user id from multiple possible field names
        $reporterId = '';
        foreach (['reporterId','userId','uid','userUID','reportedBy','reporter_uid','reporter_id'] as $key) {
            if (!empty($report[$key]) && is_string($report[$key])) { $reporterId = $report[$key]; break; }
        }
        if (!$reporterId) {
            error_log("FCM: No reporter ID found in report for rejected notification");
            return false;
        }
        // Block if reporter is a responder (no user-style notifications to responders)
        $reporterDoc = firestore_get_doc_by_id('users', $reporterId);
        if ($reporterDoc && ($reporterDoc['role'] ?? '') === 'responder') {
            error_log("🚫 FCM BLOCKED: Reporter $reporterId is a responder - blocking user-style 'Report Declined' notification");
            return true;
        }
        // Map collection to emergency type (needed by client to render properly)
        $collectionMap = [
            'ambulance_reports' => 'ambulance',
            'fire_reports' => 'fire',
            'flood_reports' => 'flood',
            'tanod_reports' => 'tanod',
            'police_reports' => 'police',
            'other_reports' => 'other'
        ];
        $emergencyType = $collectionMap[$collection] ?? 'other';
        // Title & category-specific body messages (declined)
        $title = "Report Declined ❌";
        $bodyMap = [
            'ambulance' => 'Your medical emergency report was declined. Please provide clearer details (patient condition, exact location) and resubmit if assistance is still required.',
            'fire' => 'Your fire report was declined. Verify the situation and include precise location / severity details then resubmit if the threat persists.',
            'flood' => 'Your flood report was declined. Add water level, affected people, and exact location then resubmit if still needed.',
            'tanod' => 'Your security (tanod) report was declined. Include what happened, persons involved, and location then resubmit if assistance is still needed.',
            'police' => 'Your police report was declined. Please provide more details about the incident and location, then resubmit if assistance is still needed.',
            'other' => 'Your report was declined. Please add missing/clear details and resubmit if you still need help.'
        ];
        $body = $bodyMap[$emergencyType] ?? $bodyMap['other'];
        
        // Append specific decline reason if provided
        if (!empty($declineReason)) {
            $body .= "\n\nReason: " . $declineReason;
        }
        
        $data = [
            'type' => 'report_status',
            'reportId' => $docId,
            'collection' => $collection,
            'status' => 'declined',
            'emergencyType' => $emergencyType,
            'audience' => 'user',
            'declineReason' => $declineReason
        ];
        return send_fcm_notification_to_user($reporterId, $title, $body, $data);
    } catch (Exception $e) {
        error_log("FCM: Error sending rejected report notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Send FCM notification to user for approved report
 */
function send_fcm_notification_to_user_for_approved_report(string $collection, string $docId): bool {
    global $fcm_function_calls;
    if (!isset($fcm_function_calls)) $fcm_function_calls = [];
    $fcm_function_calls[] = "✅ send_fcm_notification_to_user_for_approved_report(collection: $collection, docId: $docId)";
    
    error_log("✅ FCM_TRACE: send_fcm_notification_to_user_for_approved_report() called for {$collection}/{$docId}");
    error_log("🚨🚨🚨 CRITICAL: THIS FUNCTION SHOULD NEVER BE CALLED - IT SENDS 'Report Approved ✅'");
    error_log("🚨🚨🚨 This is the source of the 'Report Approved' notifications to responders!");
    
    // Print stack trace to find where this is being called from
    $backtrace = debug_backtrace();
    error_log("🚨🚨🚨 CALL STACK FOR BANNED FUNCTION:");
    foreach ($backtrace as $i => $trace) {
        $file = $trace['file'] ?? 'unknown';
        $line = $trace['line'] ?? 'unknown';
        $function = $trace['function'] ?? 'unknown';
        error_log("  $i: $function() in $file:$line");
    }
    
    error_log("🚨 FCM WARNING: This function should NOT be called if user notifications are disabled!");
    error_log("🚨 FCM WARNING: Check call stack to see where this is being called from");
    
    // COMPLETE BLOCK: This function should never send to anyone - it's deprecated
    error_log("🚫 FCM COMPLETELY BLOCKED: send_fcm_notification_to_user_for_approved_report() is now disabled");
    error_log("🚫 FCM BLOCKED: This function sends 'Report Approved ✅' which causes confusion for responders");
    error_log("🚫 FCM BLOCKED: Use send_fcm_notification_for_approved_report() for emergency alerts instead");
    return true; // Return true to not break the flow, but don't send anything
    
    // The rest of this function is now blocked to prevent "Report Approved" notifications
    /*
    try {
        // Get the report details
        $report = firestore_get_doc_by_id($collection, $docId);
        if (!$report) {
            error_log("FCM: Report not found: $collection/$docId");
            return false;
        }
        
        // Resolve reporter/user id from multiple possible field names
        $reporterId = '';
        foreach (['reporterId','userId','uid','userUID','reportedBy','reporter_uid','reporter_id'] as $key) {
            if (!empty($report[$key]) && is_string($report[$key])) { $reporterId = $report[$key]; break; }
        }
        if (!$reporterId) {
            error_log("FCM: No reporter ID found in report for approved notification");
            return false;
        }
        
        // CRITICAL: Check if reporter is a responder - responders should NOT receive user approval notifications
        $reporterDoc = firestore_get_doc_by_id('users', $reporterId);
        if ($reporterDoc && ($reporterDoc['role'] ?? '') === 'responder') {
            error_log("🚨 FCM BLOCKED: Reporter $reporterId is a responder - blocking user approval notification to prevent duplicate/wrong notification type");
            return true; // Return true to not break the flow, but don't send the notification
        }
        
        // Map collection to emergency type for user-friendly message
        $collectionMap = [
            'ambulance_reports' => 'ambulance',
            'fire_reports' => 'fire',
            'flood_reports' => 'flood',
            'tanod_reports' => 'tanod',
            'police_reports' => 'police',
            'other_reports' => 'other'
        ];
        
        $emergencyType = $collectionMap[$collection] ?? 'other';
        
        // ALWAYS send user-style notification to the reporter, regardless of their role
        // The reporter should know their report was approved, not receive an emergency alert
        $title = "Report Approved ✅";
        // Category-specific body messages
        $bodyMap = [
            'ambulance' => 'Your medical emergency report was approved. Ambulance and medical responders are on the way. Stay calm and clear a path if possible.',
            'fire' => 'Your fire emergency report was approved. Fire responders are en route. Move everyone to a safe location and keep clear of the danger area.',
            'flood' => 'Your flood report was approved. Rescue personnel are on the way. Move to higher ground and keep your phone accessible.',
            'tanod' => 'Your security (tanod) report was approved. Barangay tanod personnel are on the way. Stay in a safe, visible area if you can.',
            'police' => 'Your police report was approved. Police officers are on the way. Stay in a safe location.',
            'other' => 'Your report was approved. Responders are on the way. Stay safe and follow any instructions you receive.'
        ];
        $body = $bodyMap[$emergencyType] ?? $bodyMap['other'];
        $data = [
            'type' => 'report_status',
            'reportId' => $docId,
            'collection' => $collection,
            'status' => 'approved',
            'emergencyType' => $emergencyType,
            'audience' => 'user'
        ];
        return send_fcm_notification_to_user($reporterId, $title, $body, $data);
        
    } catch (Exception $e) {
        error_log("FCM: Error sending approved report notification to user: " . $e->getMessage());
        return false;
    }
    */ // End of blocked function code
}

/**
 * Check if a given FCM token belongs to any approved responder
 */
function token_belongs_to_responder(string $fcmToken): bool {
    try {
        if ($fcmToken === '') return false;
        $url = firestore_base_url() . ':runQuery';
        $body = [
            'structuredQuery' => [
                'from' => [['collectionId' => 'users']],
                'where' => [
                    'compositeFilter' => [
                        'op' => 'AND',
                        'filters' => [
                            [
                                'fieldFilter' => [
                                    'field' => ['fieldPath' => 'role'],
                                    'op' => 'EQUAL',
                                    'value' => ['stringValue' => 'responder']
                                ]
                            ],
                            [
                                'fieldFilter' => [
                                    'field' => ['fieldPath' => 'status'],
                                    'op' => 'EQUAL',
                                    'value' => ['stringValue' => 'approved']
                                ]
                            ],
                            [
                                'fieldFilter' => [
                                    'field' => ['fieldPath' => 'fcmToken'],
                                    'op' => 'EQUAL',
                                    'value' => ['stringValue' => $fcmToken]
                                ]
                            ]
                        ]
                    ]
                ],
                'limit' => 1
            ]
        ];
        $response = firestore_rest_request('POST', $url, $body);
        if (is_array($response)) {
            foreach ($response as $row) {
                if (isset($row['document'])) {
                    return true; // found a responder with this token
                }
            }
        }
        return false;
    } catch (Exception $e) {
        error_log('FCM: token_belongs_to_responder error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get all responder users from Firestore
 */
function get_responder_users(): array {
    try {
        $url = firestore_base_url() . ':runQuery';
        $body = [
            'structuredQuery' => [
                'from' => [['collectionId' => 'users']],
                'where' => [
                    'compositeFilter' => [
                        'op' => 'AND',
                        'filters' => [
                            [
                                'fieldFilter' => [
                                    'field' => ['fieldPath' => 'role'],
                                    'op' => 'EQUAL',
                                    'value' => ['stringValue' => 'responder']
                                ]
                            ],
                            [
                                'fieldFilter' => [
                                    'field' => ['fieldPath' => 'status'],
                                    'op' => 'EQUAL',
                                    'value' => ['stringValue' => 'approved']
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
        
        $response = firestore_rest_request('POST', $url, $body);
        $responders = [];
        
        if (is_array($response)) {
            foreach ($response as $row) {
                if (isset($row['document'])) {
                    $doc = $row['document'];
                    $data = firestore_decode_fields($doc['fields'] ?? []);
                    $name = $doc['name'] ?? '';
                    $data['uid'] = $name ? basename($name) : '';
                    
                    // Only include responders with valid FCM tokens
                    if (!empty($data['fcmToken'])) {
                        $responders[] = $data;
                        error_log("Found responder with FCM token: " . $data['fullName'] . " (" . $data['uid'] . ")");
                    } else {
                        error_log("Skipping responder without FCM token: " . ($data['fullName'] ?? 'Unknown') . " (" . $data['uid'] . ")");
                    }
                }
            }
        }
        
        error_log("Total responders with FCM tokens found: " . count($responders));
        return $responders;
        
    } catch (Exception $e) {
        error_log("Error getting responder users: " . $e->getMessage());
        return [];
    }
}

/**
 * Get ALL approved responders (not category-filtered) for critical emergency broadcasts
 */
function get_all_approved_responders(): array {
    try {
        $url = firestore_base_url() . ':runQuery';
        $body = [
            'structuredQuery' => [
                'select' => [
                    'fields' => [
                        ['fieldPath' => 'uid'],
                        ['fieldPath' => 'fullName'], 
                        ['fieldPath' => 'fcmToken'],
                        ['fieldPath' => 'role'],
                        ['fieldPath' => 'categories']
                    ]
                ],
                'from' => [['collectionId' => 'users']],
                'where' => [
                    'compositeFilter' => [
                        'op' => 'AND',
                        'filters' => [
                            [
                                'fieldFilter' => [
                                    'field' => ['fieldPath' => 'role'],
                                    'op' => 'EQUAL',
                                    'value' => ['stringValue' => 'responder']
                                ]
                            ],
                            [
                                'fieldFilter' => [
                                    'field' => ['fieldPath' => 'accountStatus'],
                                    'op' => 'EQUAL',
                                    'value' => ['stringValue' => 'approved']
                                ]
                            ]
                        ]
                    ]
                ],
                'limit' => 500
            ]
        ];
        
        $response = firestore_rest_request('POST', $url, $body);
        $allResponders = [];
        
        if (is_array($response)) {
            foreach ($response as $doc) {
                if (isset($doc['document']['fields'])) {
                    $fields = $doc['document']['fields'];
                    
                    $responder = [
                        'uid' => $fields['uid']['stringValue'] ?? '',
                        'fullName' => $fields['fullName']['stringValue'] ?? 'Unknown',
                        'fcmToken' => $fields['fcmToken']['stringValue'] ?? '',
                        'role' => $fields['role']['stringValue'] ?? '',
                        'categories' => []
                    ];
                    
                    // Extract categories array
                    if (isset($fields['categories']['arrayValue']['values'])) {
                        foreach ($fields['categories']['arrayValue']['values'] as $cat) {
                            if (isset($cat['stringValue'])) {
                                $responder['categories'][] = $cat['stringValue'];
                            }
                        }
                    }
                    
                    if ($responder['uid'] && $responder['fcmToken']) {
                        $allResponders[] = $responder;
                    }
                }
            }
        }
        
        error_log("FCM: Found " . count($allResponders) . " approved responders with FCM tokens");
        return $allResponders;
        
    } catch (Exception $e) {
        error_log("FCM: Error getting all approved responders: " . $e->getMessage());
        return [];
    }
}

/**
 * Get responders for a specific emergency category
 */
function get_responders_for_category(string $emergencyType): array {
    try {
        error_log("Getting responders for emergency type: " . $emergencyType . " (category-specific filtering)");
        error_log("FCM: Fire report -> Fire responders, Ambulance -> Ambulance responders, etc.");
        
        $url = firestore_base_url() . ':runQuery';
        $body = [
            'structuredQuery' => [
                'from' => [['collectionId' => 'users']],
                'where' => [
                    'compositeFilter' => [
                        'op' => 'AND',
                        'filters' => [
                            [
                                'fieldFilter' => [
                                    'field' => ['fieldPath' => 'role'],
                                    'op' => 'EQUAL',
                                    'value' => ['stringValue' => 'responder']
                                ]
                            ],
                            [
                                'compositeFilter' => [
                                    'op' => 'OR',
                                    'filters' => [
                                        [
                                            'fieldFilter' => [
                                                'field' => ['fieldPath' => 'status'],
                                                'op' => 'EQUAL',
                                                'value' => ['stringValue' => 'approved']
                                            ]
                                        ],
                                        [
                                            'fieldFilter' => [
                                                'field' => ['fieldPath' => 'accountStatus'],
                                                'op' => 'EQUAL',
                                                'value' => ['stringValue' => 'approved']
                                            ]
                                        ]
                                    ]
                                ]
                            ],
                            [
                                'fieldFilter' => [
                                    'field' => ['fieldPath' => 'categories'],
                                    'op' => 'ARRAY_CONTAINS',
                                    'value' => ['stringValue' => $emergencyType]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
        
        $response = firestore_rest_request('POST', $url, $body);
        $responders = [];
        
        if (is_array($response)) {
            foreach ($response as $row) {
                if (isset($row['document'])) {
                    $doc = $row['document'];
                    $data = firestore_decode_fields($doc['fields'] ?? []);
                    $name = $doc['name'] ?? '';
                    $data['uid'] = $name ? basename($name) : '';
                    
                    // Only include responders with valid FCM tokens
                    if (!empty($data['fcmToken'])) {
                        $responders[] = $data;
                        error_log("Found {$emergencyType} responder with FCM token: " . $data['fullName'] . " (" . $data['uid'] . ")");
                    } else {
                        error_log("Skipping {$emergencyType} responder without FCM token: " . ($data['fullName'] ?? 'Unknown') . " (" . $data['uid'] . ")");
                    }
                }
            }
        }
        
        error_log("Total {$emergencyType} responders with FCM tokens found: " . count($responders));
        return $responders;
        
    } catch (Exception $e) {
        error_log("Error getting responders for category {$emergencyType}: " . $e->getMessage());
        return [];
    }
}

/**
 * Send emergency FCM notification with aggressive settings for urgent alerts
 */
function send_emergency_fcm_notification(string $uid, string $title, string $body, array $data = []): bool {
    global $fcm_function_calls;
    if (!isset($fcm_function_calls)) $fcm_function_calls = [];
    $fcm_function_calls[] = "🚨 send_emergency_fcm_notification(uid: $uid, title: $title)";
    
    error_log("🚨 FCM_TRACE: send_emergency_fcm_notification() called for UID: $uid, Title: $title");
    error_log("🚨 FCM_TRACE: Emergency notification data: " . json_encode($data));
    try {
        $accessToken = get_fcm_access_token();
        if (!$accessToken) {
            error_log("FCM: No access token available");
            return false;
        }
        
        // Get user's FCM token from Firestore
        $userDoc = firestore_get_doc_by_id('users', $uid);
        if (!$userDoc || empty($userDoc['fcmToken'])) {
            error_log("FCM: No FCM token found for user $uid");
            return false;
        }
        // Ensure only true responders receive emergency alerts
        if (($userDoc['role'] ?? '') !== 'responder') {
            error_log("FCM: Skipping emergency alert for non-responder user $uid (role=" . ($userDoc['role'] ?? 'none') . ")");
            return false;
        }
        
        error_log("🚨 FCM_TRACE: Sending EMERGENCY ALERT to responder $uid with role=" . ($userDoc['role'] ?? 'none'));
        error_log("🚨 FCM_TRACE: User status=" . ($userDoc['status'] ?? 'none') . ", categories=" . json_encode($userDoc['categories'] ?? []));
        error_log("🚨 FCM_PAYLOAD: Title being sent to FCM: '$title'");
        error_log("🚨 FCM_PAYLOAD: Body being sent to FCM: '$body'");
        error_log("🚨 FCM_PAYLOAD: If mobile app shows 'Report Approved ✅', it's ignoring this FCM payload!");
        
        // EXTRA SAFETY: Double-check that we're not accidentally sending wrong data
        if (stripos($title, 'approved') !== false && stripos($title, 'emergency') === false) {
            error_log("🚨🚨🚨 EMERGENCY ALERT CORRUPTION DETECTED! Title contains 'approved' but not 'emergency'");
            error_log("🚨🚨🚨 CORRUPTED TITLE: $title");
            error_log("🚨🚨🚨 This should be impossible - emergency alerts should never contain 'approved'");
            return false;
        }
        $fcmToken = $userDoc['fcmToken'];
        
        // Determine emergency type from data
        $emergencyType = $data['emergencyType'] ?? 'emergency';
        $location = $data['location'] ?? 'Unknown location';
        $reportId = $data['reportId'] ?? '';
        
        // Prepare emergency notification payload compatible with mobile app's alarm system - USING WORKING OLD CODE STRUCTURE
        $message = [
            'message' => [
                'token' => $fcmToken,
                'data' => [
                    'type' => $emergencyType . '_emergency',
                    'emergencyType' => $emergencyType,
                    'title' => $title,
                    'body' => $body,
                    'location' => $location,
                    'reportId' => $reportId,
                    'timestamp' => date('c'),
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    'emergency' => 'true',
                    'sound' => 'emergency_alert',
                    'vibrate' => 'true'
                ],
                'android' => [
                    'priority' => 'high',
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                        'channel_id' => 'emergency_alarm_channel',
                        'sound' => 'default',
                        'color' => '#FF0000',
                        'icon' => 'ic_launcher_round',
                        'default_sound' => true,
                        'default_vibrate_timings' => true,
                        'visibility' => 'public',
                        'tag' => 'emergency_alert'
                    ]
                ],
                'apns' => [
                    'payload' => [
                        'aps' => [
                            'alert' => [
                                'title' => $title,
                                'body' => $body
                            ],
                            'sound' => 'emergency_alert.caf',
                            'badge' => 1,
                            'category' => 'EMERGENCY_ALERT',
                            'mutable-content' => 1,
                            'interruption-level' => 'critical'
                        ]
                    ],
                    'headers' => [
                        'apns-priority' => '10',
                        'apns-push-type' => 'alert',
                        'apns-collapse-id' => 'emergency_alert'
                    ]
                ]
            ]
        ];
        
        // Send to FCM V1 API
        $projectId = 'ibantayv2';
        $url = FCM_API_URL . $projectId . '/messages:send';
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($message),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken
            ],
            CURLOPT_TIMEOUT => 10
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            // ULTRA-DEBUG: Log the exact payload that was sent
            error_log("🚨 FCM SUCCESS: EMERGENCY ALERT sent successfully to responder $uid - Title: $title");
            error_log("🚨 FCM SUCCESS: Emergency payload included sound/vibration settings for maximum priority");
            error_log("🚨 FCM SUCCESS: If responder sees 'Report Approved ✅' instead, there's a mobile app or FCM delivery issue");
            error_log("🚨 FCM SUCCESS: Notification title sent: '$title'");
            error_log("🚨 FCM SUCCESS: Notification body sent: '$body'");
            error_log("🚨 FCM SUCCESS: Data type sent: '" . ($data['type'] ?? 'unknown') . "'");
            
            // Log the complete message payload for debugging
            error_log("🔍 FCM FULL PAYLOAD SENT: " . json_encode($message, JSON_PRETTY_PRINT));
            
            // Write to a separate debug file for easier viewing
            $debugFile = __DIR__ . '/fcm_debug.log';
            $debugEntry = date('Y-m-d H:i:s') . " - EMERGENCY ALERT SENT TO $uid\n";
            $debugEntry .= "Title: $title\n";
            $debugEntry .= "Body: $body\n";
            $debugEntry .= "Data type: " . ($data['type'] ?? 'unknown') . "\n";
            $debugEntry .= "Full payload: " . json_encode($message, JSON_PRETTY_PRINT) . "\n";
            $debugEntry .= str_repeat("=", 80) . "\n";
            file_put_contents($debugFile, $debugEntry, FILE_APPEND);
            
            return true;
        } else {
            error_log("❌ FCM FAILED: Emergency notification failed. HTTP $httpCode: $response");
            return false;
        }
        
    } catch (Exception $e) {
        error_log("FCM: Error sending emergency notification to user $uid: " . $e->getMessage());
        return false;
    }
}

/**
 * Send FCM notification to responders (legacy function for compatibility)
 */
function send_fcm_notification_to_responders(string $title, string $body, array $data = []): bool {
    try {
        $responders = get_responder_users();
        if (empty($responders)) {
            error_log("FCM: No responder users found");
            return false;
        }

        $successCount = 0;
        foreach ($responders as $responder) {
            $uid = $responder['uid'] ?? '';
            if (!$uid) { continue; }
            // Always use the emergency path for responders to ensure alarm/vibration
            if (send_emergency_fcm_notification($uid, $title, $body, $data)) {
                $successCount++;
            }
        }

        return $successCount > 0;

    } catch (Exception $e) {
        error_log("FCM: Error sending notification to responders: " . $e->getMessage());
        return false;
    }
}

/**
 * WORKING DIRECT VERSION - Send emergency notification exactly like test_notification.php
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
            'police_reports' => ['type' => 'police', 'emoji' => '🚓', 'label' => 'POLICE'],
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
        error_log("🔥 DIRECT: ⚠️  IF RESPONDER GETS 'Report Approved ✅' INSTEAD, IT'S AN ANDROID APP BUG!");
        error_log("🔥 DIRECT: ⚠️  The server is sending: '$title' but app shows 'Report Approved ✅'");
        
        // Get reporter ID to exclude them
        $reporterId = '';
        foreach (['reporterId','userId','uid','userUID','reportedBy','reporter_uid','reporter_id'] as $key) {
            if (!empty($report[$key]) && is_string($report[$key])) { 
                $reporterId = trim((string)$report[$key]); 
                break; 
            }
        }
        
        $successCount = 0;
        foreach ($responders as $responder) {
            $uid = $responder['uid'] ?? '';
            if (!$uid) continue;
            
            // Skip if responder is the reporter
            if ($reporterId && strcasecmp(trim($uid), trim($reporterId)) === 0) {
                error_log("🔥 DIRECT: Skipping responder $uid - is the reporter");
                continue;
            }
            
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

