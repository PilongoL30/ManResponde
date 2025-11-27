<?php
// Only start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__.'/db_config.php';
require_once __DIR__.'/fcm_config.php';

/**
 * Notification System for iBantay
 * Handles urgent/high priority report notifications for staff
 * AND FCM notifications for responders
 */

// Notification types
define('NOTIFICATION_TYPE_URGENT', 'urgent_report');
define('NOTIFICATION_TYPE_HIGH_PRIORITY', 'high_priority');
define('NOTIFICATION_TYPE_EMERGENCY', 'emergency_alert');

// FCM configuration is now in fcm_config.php

/**
 * Create a notification for staff members
 */
function create_staff_notification(string $type, string $title, string $message, array $data = []): bool {
    try {
        $notification = [
            'fields' => [
                'type' => ['stringValue' => $type],
                'title' => ['stringValue' => $title],
                'message' => ['stringValue' => $message],
                'data' => ['mapValue' => ['fields' => firestore_encode_fields($data)]],
                'timestamp' => ['stringValue' => date('Y-m-d H:i:s')],
                'read' => ['booleanValue' => false],
                'recipients' => ['stringValue' => 'staff'],
                'priority' => ['stringValue' => 'high']
            ]
        ];
        
        $url = firestore_base_url() . '/notifications';
        $response = firestore_rest_request('POST', $url, $notification);
        
        return !empty($response);
    } catch (Exception $e) {
        error_log("Error creating notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Check for urgent reports and create notifications (ULTRA FAST VERSION)
 */
function check_urgent_reports(array $userCategories = []): array {
    // Check cache first for aggressive performance
    $cacheKey = 'urgent_reports_' . md5(implode(',', $userCategories));
    $cachedReports = cache_get($cacheKey, 30); // 30-second cache for real-time experience
    
    if ($cachedReports !== null) {
        return $cachedReports;
    }
    
    $urgentReports = [];
    
    // Define all report collections to check
    $reportCollections = [
        'ambulance_reports',
        'fire_reports', 
        'flood_reports',
        'other_reports',
        'tanod_reports'
    ];
    
    // Map collection names to category slugs
    $collectionToCategory = [
        'ambulance_reports' => 'ambulance',
        'fire_reports' => 'fire',
        'flood_reports' => 'flood',
        'other_reports' => 'other',
        'tanod_reports' => 'tanod'
    ];
    
    // Filter collections based on user categories
    if (!empty($userCategories)) {
        $allowedCollections = [];
        foreach ($reportCollections as $collection) {
            $category = $collectionToCategory[$collection] ?? '';
            if (in_array($category, $userCategories)) {
                $allowedCollections[] = $collection;
            }
        }
        $reportCollections = $allowedCollections;
        
        // Debug logging to verify filtering
        error_log("✅ URGENT REPORTS FILTERED: User categories: " . implode(', ', $userCategories));
        error_log("✅ CHECKING ONLY COLLECTIONS: " . implode(', ', $reportCollections));
    } else {
        error_log("⚠️ NO USER CATEGORIES - checking all collections (this should not happen for staff)");
    }
    
    try {
        // Use parallel requests for maximum speed
        $urgentReports = check_urgent_reports_parallel($reportCollections);
        
        // If parallel approach fails or returns empty, try fallback
        if (empty($urgentReports)) {
            error_log("Parallel urgent reports returned empty, trying fallback");
            $urgentReports = check_urgent_reports_sequential($reportCollections);
        }
        
        // Cache the results
        cache_set($cacheKey, $urgentReports);
        
        return $urgentReports;
        
    } catch (Exception $e) {
        error_log("Error checking urgent reports: " . $e->getMessage());
        // Try fallback method
        try {
            $urgentReports = check_urgent_reports_sequential($reportCollections);
            cache_set($cacheKey, $urgentReports);
            return $urgentReports;
        } catch (Exception $fallbackE) {
            error_log("Fallback urgent reports also failed: " . $fallbackE->getMessage());
            return [];
        }
    }
}

/**
 * Parallel urgent reports checker using multi-curl
 */
function check_urgent_reports_parallel(array $collections): array {
    $urgentReports = [];
    
    if (empty($collections)) {
        return $urgentReports;
    }
    
    // Prepare parallel requests
    $multiHandle = curl_multi_init();
    $curlHandles = [];
    $requestMap = [];
    
    foreach ($collections as $collection) {
        try {
            $url = firestore_base_url() . ':runQuery';
            $body = [
                'structuredQuery' => [
                    'from' => [['collectionId' => $collection]],
                    'where' => [
                        'compositeFilter' => [
                            'op' => 'AND',
                            'filters' => [
                                [
                                    'fieldFilter' => [
                                        'field' => ['fieldPath' => 'status'],
                                        'op' => 'EQUAL',
                                        'value' => ['stringValue' => 'Pending']
                                    ]
                                ],
                                [
                                    'fieldFilter' => [
                                        'field' => ['fieldPath' => 'priority'],
                                        'op' => 'EQUAL',
                                        'value' => ['stringValue' => 'HIGH']
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'orderBy' => [[
                        'field' => ['fieldPath' => 'timestamp'],
                        'direction' => 'DESCENDING',
                    ]],
                    'limit' => 20
                ]
            ];
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($body),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . get_firebase_access_token()
                ],
                CURLOPT_TIMEOUT => 2, // Very aggressive timeout
                CURLOPT_CONNECTTIMEOUT => 1
            ]);
            
            curl_multi_add_handle($multiHandle, $ch);
            $curlHandles[] = $ch;
            $requestMap[] = $collection;
            
        } catch (Exception $e) {
            error_log("Error preparing request for {$collection}: " . $e->getMessage());
        }
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
        $collection = $requestMap[$idx];
        
        try {
            $data = json_decode($response, true);
            if (is_array($data)) {
                foreach ($data as $row) {
                    if (!isset($row['document'])) continue;
                    
                    $doc = $row['document'];
                    $docData = firestore_decode_fields($doc['fields'] ?? []);
                    $name = $doc['name'] ?? '';
                    $docId = $name ? basename($name) : '';
                    
                    $docData['_id'] = $docId;
                    $docData['id'] = $docId;
                    $docData['_created'] = $doc['createTime'] ?? null;
                    $docData['collection'] = $collection;
                    
                    $urgentReports[] = $docData;
                }
            }
        } catch (Exception $e) {
            error_log("Error processing urgent reports for {$collection}: " . $e->getMessage());
        }
        
        curl_multi_remove_handle($multiHandle, $ch);
        curl_close($ch);
    }
    
    curl_multi_close($multiHandle);
    
    // Sort by timestamp (newest first)
    usort($urgentReports, function($a, $b) {
        return strcmp((string)($b['timestamp'] ?? ''), (string)($a['timestamp'] ?? ''));
    });
    
    return $urgentReports;
}

/**
 * FALLBACK: Original sequential check for urgent reports
 */
function check_urgent_reports_sequential(array $reportCollections): array {
    $urgentReports = [];
    
    try {
        foreach ($reportCollections as $collection) {
            $url = firestore_base_url() . ':runQuery';
            $body = [
                'structuredQuery' => [
                    'from' => [['collectionId' => $collection]],
                    'where' => [
                        'fieldFilter' => [
                            'field' => ['fieldPath' => 'status'],
                            'op' => 'EQUAL',
                            'value' => ['stringValue' => 'Pending']
                        ]
                    ],
                    'limit' => 50
                ]
            ];
            
            $response = firestore_rest_request('POST', $url, $body);
            
            if (is_array($response)) {
                foreach ($response as $row) {
                    if (isset($row['document'])) {
                        $doc = $row['document'];
                        $data = firestore_decode_fields($doc['fields'] ?? []);
                        $name = $doc['name'] ?? '';
                        $docId = $name ? basename($name) : '';
                        $data['_id'] = $docId;
                        $data['id'] = $docId;
                        $data['_created'] = $doc['createTime'] ?? null;
                        $data['collection'] = $collection;
                        
                        // Include ALL PENDING reports from allowed collections
                        $urgentReports[] = $data;
                    }
                }
            }
        }
        
        // Sort by timestamp in PHP (newest first)
        usort($urgentReports, function($a, $b) {
            return strcmp((string)($b['timestamp'] ?? ''), (string)($a['timestamp'] ?? ''));
        });
        
    } catch (Exception $e) {
        error_log("Error in sequential urgent reports check: " . $e->getMessage());
    }
    
    return $urgentReports;
}

/**
 * Original check_urgent_reports function preserved for compatibility
 */
function check_urgent_reports_original(array $userCategories = []): array {
    $urgentReports = [];
    
    // Define all report collections to check
    $reportCollections = [
        'ambulance_reports',
        'fire_reports', 
        'flood_reports',
        'other_reports',
        'tanod_reports'
    ];
    
    // Map collection names to category slugs
    $collectionToCategory = [
        'ambulance_reports' => 'ambulance',
        'fire_reports' => 'fire',
        'flood_reports' => 'flood',
        'other_reports' => 'other',
        'tanod_reports' => 'tanod'
    ];
    
    // Filter collections based on user categories
    if (!empty($userCategories)) {
        $allowedCollections = [];
        foreach ($reportCollections as $collection) {
            $category = $collectionToCategory[$collection] ?? '';
            if (in_array($category, $userCategories)) {
                $allowedCollections[] = $collection;
            }
        }
        $reportCollections = $allowedCollections;
    }
    
    try {
        foreach ($reportCollections as $collection) {
            $url = firestore_base_url() . ':runQuery';
            $body = [
                'structuredQuery' => [
                    'from' => [['collectionId' => $collection]],
                    'where' => [
                        'fieldFilter' => [
                            'field' => ['fieldPath' => 'status'],
                            'op' => 'EQUAL',
                            'value' => ['stringValue' => 'Pending']
                        ]
                    ],
                    'limit' => 50
                ]
            ];
            
            $response = firestore_rest_request('POST', $url, $body);
            
            if (is_array($response)) {
                foreach ($response as $row) {
                    if (isset($row['document'])) {
                        $doc = $row['document'];
                        $data = firestore_decode_fields($doc['fields'] ?? []);
                        $name = $doc['name'] ?? '';
                        $docId = $name ? basename($name) : '';
                        $data['_id'] = $docId;
                        $data['id'] = $docId; // Also set 'id' for JavaScript compatibility
                        $data['_created'] = $doc['createTime'] ?? null;
                        $data['collection'] = $collection; // Add collection name for reference
                        
                        // Include ALL PENDING reports from allowed collections
                        $urgentReports[] = $data;
                    }
                }
            }
        }
        
        // Sort by timestamp in PHP (newest first)
        usort($urgentReports, function($a, $b) {
            $timeA = $a['timestamp'] ?? '';
            $timeB = $b['timestamp'] ?? '';
            
            $secondsA = is_array($timeA) && isset($timeA['_seconds']) ? $timeA['_seconds'] : strtotime($timeA);
            $secondsB = is_array($timeB) && isset($timeB['_seconds']) ? $timeB['_seconds'] : strtotime($timeB);
            
            return $secondsB - $secondsA; // Descending order
        });
        
        return $urgentReports;
    } catch (Exception $e) {
        error_log("Error checking urgent reports: " . $e->getMessage());
        return [];
    }
}

/**
 * Check if notification already exists for a report
 */
function notification_exists_for_report(string $reportId): bool {
    try {
        $url = firestore_base_url() . ':runQuery';
        $body = [
            'structuredQuery' => [
                'from' => [['collectionId' => 'notifications']],
                'where' => [
                    'fieldFilter' => [
                        'field' => ['fieldPath' => 'data.reportId'],
                        'op' => 'EQUAL',
                        'value' => ['stringValue' => $reportId]
                    ]
                ],
                'limit' => 1
            ]
        ];
        
        $response = firestore_rest_request('POST', $url, $body);
        
        return is_array($response) && count($response) > 0;
    } catch (Exception $e) {
        error_log("Error checking if notification exists: " . $e->getMessage());
        return false;
    }
}

/**
 * Check for new urgent reports and create notifications
 */
function check_and_create_notifications(array $userCategories = []): void {
    try {
        $urgentReports = check_urgent_reports($userCategories);
        
        foreach ($urgentReports as $report) {
            $reportId = $report['_id'] ?? '';
            $reporterName = $report['fullName'] ?? $report['reporterName'] ?? 'Unknown';
            $location = $report['location'] ?? 'Unknown location';
            $description = $report['purpose'] ?? $report['description'] ?? 'No description';
            $status = $report['status'] ?? 'Pending';
            $collection = $report['collection'] ?? 'unknown';
            
            // SAFEGUARD: Only create notifications for pending reports
            if (strtolower($status) !== 'pending') {
                error_log("Skipping notification creation for non-pending report: {$reportId} (status: {$status})");
                continue;
            }
            
            // Skip if notification already exists for this report
            if (notification_exists_for_report($reportId)) {
                continue;
            }
            
            // Create appropriate title based on collection type
            $collectionLabels = [
                'ambulance_reports' => '🚑 Ambulance',
                'fire_reports' => '🔥 Fire',
                'flood_reports' => '🌊 Flood',
                'other_reports' => '📋 Other',
                'tanod_reports' => '👮 Tanod'
            ];
            
            $collectionLabel = $collectionLabels[$collection] ?? '📋 Report';
            
            // Create notification for this pending report
            $title = "{$collectionLabel} - {$reporterName}";
            $message = "Pending report from {$reporterName} at {$location}. {$description}";
            
            $notificationData = [
                'reportId' => $reportId,
                'reporterName' => $reporterName,
                'location' => $location,
                'description' => $description,
                'timestamp' => $report['timestamp'] ?? null,
                'status' => $status,
                'collection' => $collection
            ];
            
            create_staff_notification(
                NOTIFICATION_TYPE_URGENT,
                $title,
                $message,
                $notificationData
            );
            
            error_log("Created staff notification for pending report: {$reportId}");
        }
    } catch (Exception $e) {
        error_log("Error creating notifications: " . $e->getMessage());
    }
}

/**
 * Get unread notifications for staff
 */
function get_staff_notifications(int $limit = 20, array $userCategories = []): array {
    try {
        $url = firestore_base_url() . ':runQuery';
        $body = [
            'structuredQuery' => [
                'from' => [['collectionId' => 'notifications']],
                'where' => [
                    'fieldFilter' => [
                        'field' => ['fieldPath' => 'read'],
                        'op' => 'EQUAL',
                        'value' => ['booleanValue' => false]
                    ]
                ],
                'limit' => $limit
            ]
        ];
        
        $response = firestore_rest_request('POST', $url, $body);
        $notifications = [];
        
        // Map collection names to category slugs
        $collectionToCategory = [
            'ambulance_reports' => 'ambulance',
            'fire_reports' => 'fire',
            'flood_reports' => 'flood',
            'other_reports' => 'other',
            'tanod_reports' => 'tanod'
        ];
        
        if (is_array($response)) {
            foreach ($response as $row) {
                if (isset($row['document'])) {
                    $doc = $row['document'];
                    $data = firestore_decode_fields($doc['fields'] ?? []);
                    $name = $doc['name'] ?? '';
                    $data['_id'] = $name ? basename($name) : '';
                    
                    // Filter notifications based on user categories
                    if (!empty($userCategories)) {
                        $notificationCollection = $data['data']['collection'] ?? '';
                        $category = $collectionToCategory[$notificationCollection] ?? '';
                        
                        // Only include notifications for user's assigned categories
                        if (!in_array($category, $userCategories)) {
                            continue;
                        }
                    }
                    
                    $notifications[] = $data;
                }
            }
            
            // Sort by timestamp in PHP (newest first)
            usort($notifications, function($a, $b) {
                $timeA = $a['timestamp'] ?? '';
                $timeB = $b['timestamp'] ?? '';
                
                $secondsA = is_string($timeA) ? strtotime($timeA) : 0;
                $secondsB = is_string($timeB) ? strtotime($timeB) : 0;
                
                return $secondsB - $secondsA; // Descending order
            });
        }
        
        return $notifications;
    } catch (Exception $e) {
        error_log("Error getting notifications: " . $e->getMessage());
        return [];
    }
}

/**
 * Mark notification as read
 */
function mark_notification_read(string $notificationId): bool {
    try {
        $url = firestore_base_url() . "/notifications/{$notificationId}";
        $updateData = [
            'fields' => [
                'read' => ['booleanValue' => true],
                'readAt' => ['stringValue' => date('Y-m-d H:i:s')]
            ]
        ];
        
        $response = firestore_rest_request('PATCH', $url, $updateData);
        return !empty($response);
    } catch (Exception $e) {
        error_log("Error marking notification as read: " . $e->getMessage());
        return false;
    }
}

/**
 * Remove notification when report is approved
 */
function remove_notification_for_approved_report(string $reportId): bool {
    try {
        // Find notification for this report
        $url = firestore_base_url() . ':runQuery';
        $body = [
            'structuredQuery' => [
                'from' => [['collectionId' => 'notifications']],
                'where' => [
                    'fieldFilter' => [
                        'field' => ['fieldPath' => 'data.reportId'],
                        'op' => 'EQUAL',
                        'value' => ['stringValue' => $reportId]
                    ]
                ],
                'limit' => 10
            ]
        ];
        
        $response = firestore_rest_request('POST', $url, $body);
        
        if (is_array($response)) {
            foreach ($response as $row) {
                if (isset($row['document'])) {
                    $doc = $row['document'];
                    $name = $doc['name'] ?? '';
                    $notificationId = $name ? basename($name) : '';
                    
                    if ($notificationId) {
                        // Delete the notification
                        $deleteUrl = firestore_base_url() . "/notifications/{$notificationId}";
                        firestore_rest_request('DELETE', $deleteUrl);
                    }
                }
            }
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Error removing notification for approved report: " . $e->getMessage());
        return false;
    }
}

/**
 * Update notification status when report status changes
 */
function update_notification_for_report_status(string $reportId, string $newStatus, string $collection = 'ambulance_reports'): bool {
    try {
        // Find notification for this report
        $url = firestore_base_url() . ':runQuery';
        $body = [
            'structuredQuery' => [
                'from' => [['collectionId' => 'notifications']],
                'where' => [
                    'fieldFilter' => [
                        'field' => ['fieldPath' => 'data.reportId'],
                        'op' => 'EQUAL',
                        'value' => ['stringValue' => $reportId]
                    ]
                ],
                'limit' => 10
            ]
        ];
        
        $response = firestore_rest_request('POST', $url, $body);
        
        if (is_array($response)) {
            foreach ($response as $row) {
                if (isset($row['document'])) {
                    $doc = $row['document'];
                    $name = $doc['name'] ?? '';
                    $notificationId = $name ? basename($name) : '';
                    
                    if ($notificationId) {
                        if ($newStatus === 'Approved') {
                            // Remove notification for approved reports
                            $deleteUrl = firestore_base_url() . "/notifications/{$notificationId}";
                            firestore_rest_request('DELETE', $deleteUrl);
                        } else {
                            // Update notification status for declined reports
                            $updateUrl = firestore_base_url() . "/notifications/{$notificationId}";
                            $updateData = [
                                'fields' => [
                                    'data.status' => ['stringValue' => $newStatus]
                                ]
                            ];
                            firestore_rest_request('PATCH', $updateUrl, $updateData);
                        }
                    }
                }
            }
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Error updating notification for report status: " . $e->getMessage());
        return false;
    }
}

/**
 * Process urgent reports and create notifications
 */
function process_urgent_reports(): void {
    $urgentReports = check_urgent_reports();
    
    foreach ($urgentReports as $report) {
        $title = "🚨 URGENT: " . ($report['emergencyType'] ?? 'Medical Emergency');
        $message = "Emergency alert from " . ($report['reporterName'] ?? 'Unknown') . 
                   " at " . ($report['location'] ?? 'Unknown location') . 
                   ". Description: " . ($report['description'] ?? 'No description provided');
        
        $data = [
            'reportId' => $report['_id'],
            'reportType' => 'ambulance',
            'priority' => $report['priority'] ?? 'HIGH',
            'contact' => $report['reporterContact'] ?? '',
            'location' => $report['location'] ?? ''
        ];
        
        create_staff_notification(
            NOTIFICATION_TYPE_URGENT,
            $title,
            $message,
            $data
        );
    }
}

/**
 * Get notification count for staff
 */
function get_notification_count(array $userCategories = []): int {
    $notifications = get_staff_notifications(100, $userCategories);
    return count($notifications);
}

/**
 * Debug function to get all notifications (for troubleshooting)
 */
function debug_all_notifications(): array {
    try {
        $url = firestore_base_url() . ':runQuery';
        $body = [
            'structuredQuery' => [
                'from' => [['collectionId' => 'notifications']],
                'limit' => 100
            ]
        ];
        
        $response = firestore_rest_request('POST', $url, $body);
        $notifications = [];
        
        if (is_array($response)) {
            foreach ($response as $row) {
                if (isset($row['document'])) {
                    $doc = $row['document'];
                    $data = firestore_decode_fields($doc['fields'] ?? []);
                    $name = $doc['name'] ?? '';
                    $data['_id'] = $name ? basename($name) : '';
                    $notifications[] = $data;
                }
            }
        }
        
        return $notifications;
    } catch (Exception $e) {
        error_log("Error getting all notifications: " . $e->getMessage());
        return [];
    }
}

/**
 * Clean up orphaned notifications (remove notifications for non-existent reports)
 */
function cleanup_orphaned_notifications(): int {
    try {
        $allNotifications = debug_all_notifications();
        $cleanedCount = 0;
        
        foreach ($allNotifications as $notification) {
            $reportId = $notification['data']['reportId'] ?? '';
            $notificationId = $notification['_id'] ?? '';
            
            if ($reportId && $notificationId) {
                // Check if the report still exists
                $report = firestore_get_doc_by_id('ambulance_reports', $reportId);
                
                if (!$report) {
                    // Report doesn't exist, remove the notification
                    $deleteUrl = firestore_base_url() . "/notifications/{$notificationId}";
                    firestore_rest_request('DELETE', $deleteUrl);
                    $cleanedCount++;
                }
            }
        }
        
        return $cleanedCount;
    } catch (Exception $e) {
        error_log("Error cleaning up orphaned notifications: " . $e->getMessage());
        return 0;
    }
}

/**
 * Clean up corrupted notifications (remove notifications with missing data)
 */
function cleanup_corrupted_notifications(): int {
    try {
        $allNotifications = debug_all_notifications();
        $cleanedCount = 0;
        
        foreach ($allNotifications as $notification) {
            $notificationId = $notification['_id'] ?? '';
            $title = $notification['title'] ?? '';
            $reportId = $notification['data']['reportId'] ?? '';
            
            // Remove notifications with missing essential data
            if (empty($title) || empty($reportId) || $title === 'N/A' || $reportId === 'N/A') {
                if ($notificationId) {
                    $deleteUrl = firestore_base_url() . "/notifications/{$notificationId}";
                    firestore_rest_request('DELETE', $deleteUrl);
                    $cleanedCount++;
                }
            }
        }
        
        return $cleanedCount;
    } catch (Exception $e) {
        error_log("Error cleaning up corrupted notifications: " . $e->getMessage());
        return 0;
    }
}

/**
 * Format notification timestamp
 */
function format_notification_time($timestamp): string {
    if (is_array($timestamp) && isset($timestamp['_seconds'])) {
        $time = $timestamp['_seconds'];
    } elseif (is_string($timestamp)) {
        $time = strtotime($timestamp);
    } else {
        return 'Unknown time';
    }
    
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } else {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    }
}

/**
 * Send FCM notification to responders when a report is approved
 */
function send_fcm_notification_to_responders_legacy(string $emergencyType, string $location, string $description, string $reportId): bool {
    // DEPRECATED: ensure responders get EMERGENCY alert with siren/vibration
    error_log("⚠️ DEPRECATED: send_fcm_notification_to_responders_legacy called. Using emergency path with siren/vibration.");

    $key = strtolower($emergencyType);
    $map = [
        'ambulance' => ['emoji' => '🚑', 'label' => 'AMBULANCE'],
        'fire' => ['emoji' => '🔥', 'label' => 'FIRE'],
        'flood' => ['emoji' => '🌊', 'label' => 'FLOOD'],
        'tanod' => ['emoji' => '🚔', 'label' => 'TANOD'],
        'other' => ['emoji' => '🚨', 'label' => 'EMERGENCY']
    ];
    $emoji = $map[$key]['emoji'] ?? '🚨';
    $label = $map[$key]['label'] ?? 'EMERGENCY';
    $title = "{$emoji} EMERGENCY ALERT - {$label}";
    $body  = $description ?: 'Emergency requires immediate attention';

    // Try to identify and exclude original reporter and shared token
    $collectionMap = [
        'ambulance' => 'ambulance_reports',
        'fire' => 'fire_reports',
        'flood' => 'flood_reports',
        'tanod' => 'tanod_reports',
        'other' => 'other_reports'
    ];
    $collection = $collectionMap[$key] ?? null;
    $reporterId = null; $reporterToken = null;
    if ($collection) {
        $report = firestore_get_doc_by_id($collection, $reportId);
        if ($report) {
            foreach (['reporterId','userId','uid','userUID','reportedBy','reporter_uid','reporter_id'] as $rk) {
                if (!empty($report[$rk]) && is_string($report[$rk])) { $reporterId = trim((string)$report[$rk]); break; }
            }
            if ($reporterId) {
                $reporterDoc = firestore_get_doc_by_id('users', $reporterId);
                if ($reporterDoc && !empty($reporterDoc['fcmToken'])) {
                    $reporterToken = $reporterDoc['fcmToken'];
                }
            }
        }
    }

    $data = [
        'type' => $key . '_emergency',
        'emergencyType' => $key,
        'reportId' => $reportId,
        'location' => $location,
        'description' => $description ?: '',
        'status' => 'approved',
        'priority' => 'emergency',
        'audience' => 'responder'
    ];

    // Fetch category-specific responders and send via emergency path
    // For example: fire emergency -> only fire responders, ambulance -> only ambulance responders
    $responders = get_responders_for_emergency_type($key);
    error_log("Legacy emergency: Broadcasting to " . count($responders) . " {$key} responders (category-specific)");
    $sent = 0;
    foreach ($responders as $responder) {
        $uid = $responder['uid'] ?? '';
        if (!$uid) continue;
        if ($reporterId && strcasecmp($uid, $reporterId) === 0) continue; // exclude reporter
        if ($reporterToken && !empty($responder['fcmToken']) && $responder['fcmToken'] === $reporterToken) continue; // exclude shared device
        if (send_emergency_fcm_notification($uid, $title, $body, $data)) { $sent++; }
    }
    error_log("Legacy emergency broadcast summary -> sent={$sent} to {$key} responders only");
    return $sent > 0;
}

/**
 * Get responders for a specific emergency type
 */
function get_responders_for_emergency_type(string $emergencyType): array {
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
                            ],
                            [
                                'fieldFilter' => [
                                    'field' => ['fieldPath' => 'categories'],
                                    'op' => 'ARRAY_CONTAINS',
                                    'value' => ['stringValue' => strtolower($emergencyType)]
                                ]
                            ]
                        ]
                    ]
                ],
                'limit' => 100
            ]
        ];
        
        $response = firestore_rest_request('POST', $url, $body);
        $responders = [];
        
        if (is_array($response)) {
            foreach ($response as $row) {
                if (isset($row['document'])) {
                    $doc = $row['document'];
                    $fields = firestore_decode_fields($doc['fields'] ?? []);
                    
                    if (!empty($fields['fcmToken'])) {
                        $responders[] = [
                            'uid' => basename($doc['name']),
                            'fcmToken' => $fields['fcmToken'],
                            'fullName' => $fields['fullName'] ?? 'Unknown',
                            'categories' => $fields['categories'] ?? []
                        ];
                    }
                }
            }
        }
        
        return $responders;
        
    } catch (Exception $e) {
        error_log("Error getting responders: " . $e->getMessage());
        return [];
    }
}

/**
 * Send FCM notification to user when their report is rejected/declined (Legacy version - deprecated)
 * @deprecated Use send_fcm_notification_to_user_for_rejected_report from fcm_config.php instead
 */
function send_fcm_notification_to_user_for_rejected_report_legacy(string $collection, string $reportId): bool {
    // DEPRECATED: use the fcm_config implementation (includes responder-role blocks)
    error_log("⚠️ DEPRECATED: send_fcm_notification_to_user_for_rejected_report_legacy called. Redirecting to fcm_config version.");
    return send_fcm_notification_to_user_for_rejected_report($collection, $reportId, '');
}

/**
 * Get user's FCM token from Firestore
 */
function get_user_fcm_token(string $userId): ?string {
    try {
        $user = firestore_get_doc_by_id('users', $userId);
        
        if ($user && isset($user['fcmToken'])) {
            return $user['fcmToken'];
        }
        
        return null;
        
    } catch (Exception $e) {
        error_log("Error getting user FCM token: " . $e->getMessage());
        return null;
    }
}

/**
 * Send FCM notification to user device (for non-emergency notifications)
 */
function send_fcm_to_device_user(string $fcmToken, array $notificationData): bool {
    try {
        // Get access token
        $accessToken = get_fcm_access_token();
        if (!$accessToken) {
            error_log("Failed to get FCM access token");
            return false;
        }
        
        // Get project ID from service account
        $serviceAccount = json_decode(file_get_contents(FCM_SERVICE_ACCOUNT_PATH), true);
        $projectId = $serviceAccount['project_id'] ?? null;
        
        if (!$projectId) {
            error_log("Project ID not found in service account");
            return false;
        }
        
        $url = FCM_API_URL . $projectId . '/messages:send';
        
        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ];
        
        $payload = [
            'message' => [
                'token' => $fcmToken,
                'notification' => [
                    'title' => $notificationData['title'],
                    'body' => $notificationData['body']
                ],
                'data' => $notificationData,
                'android' => [
                    'priority' => 'normal',
                    'notification' => [
                        'sound' => 'default',
                        'priority' => 'normal',
                        'vibrate_timings' => [0, 500],
                        'notification_priority' => 'PRIORITY_DEFAULT',
                        'channel_id' => 'user_notifications',
                        'default_sound' => true,
                        'default_vibrate_timings' => false,
                        'visibility' => 'private',
                        'importance' => 'default',
                        'icon' => 'ic_ibantay_logo',
                        'color' => '#2196F3',
                        'tag' => 'user_notification',
                        'auto_cancel' => true
                    ]
                ]
            ]
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            if (isset($result['success']) && $result['success'] > 0) {
                error_log("User FCM notification sent successfully to token: " . substr($fcmToken, 0, 20) . "...");
                return true;
            } else {
                error_log("User FCM notification failed: " . ($result['results'][0]['error'] ?? 'Unknown error'));
                return false;
            }
        } else {
            error_log("User FCM HTTP error: " . $httpCode . " - " . $response);
            return false;
        }
        
    } catch (Exception $e) {
        error_log("Error sending user FCM notification: " . $e->getMessage());
        return false;
    }
}

// AJAX handlers
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    switch ($action) {
        case 'get_notifications':
            $notifications = get_staff_notifications();
            echo json_encode(['success' => true, 'notifications' => $notifications]);
            exit;
            
        case 'mark_read':
            $notificationId = $_POST['notification_id'] ?? '';
            if ($notificationId) {
                $success = mark_notification_read($notificationId);
                echo json_encode(['success' => $success]);
            } else {
                echo json_encode(['success' => false, 'error' => 'No notification ID provided']);
            }
            exit;
            
        case 'get_count':
            $count = get_notification_count();
            echo json_encode(['success' => true, 'count' => $count]);
            exit;
            
        case 'process_urgent':
            process_urgent_reports();
            echo json_encode(['success' => true, 'message' => 'Urgent reports processed']);
            exit;
            
        case 'check_urgent':
            $urgentReports = check_urgent_reports();
            echo json_encode(['success' => true, 'reports' => $urgentReports]);
            exit;
    }
}
?>
