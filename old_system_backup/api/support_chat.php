<?php
// Ensure no output before JSON
ob_start();

require_once __DIR__ . '/../db_config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clean buffer before sending headers
if (ob_get_length()) ob_clean();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? ''; // Support POST action too
$currentUserId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? 'staff';
$assignedBarangay = $_SESSION['assigned_barangay'] ?? ''; // Get assigned barangay
$assignedBarangay = $_SESSION['assignedBarangay'] ?? '';

// Helper to get user details
function get_user_details_cached($uid) {
    // Simple in-memory cache for this request
    static $cache = [];
    if (isset($cache[$uid])) return $cache[$uid];
    
    $user = firestore_get_doc_by_id('users', $uid);
    $cache[$uid] = $user;
    return $user;
}

// Fallback: If assignedBarangay is missing in session for staff, fetch it
if ($userRole === 'staff' && empty($assignedBarangay)) {
    $staffProfile = get_user_details_cached($currentUserId);
    $assignedBarangay = $staffProfile['assignedBarangay'] ?? '';
    $_SESSION['assignedBarangay'] = $assignedBarangay;
}

try {
    if ($action === 'get_chats') {
        // Fetch active chats using listDocuments to avoid index/ordering issues
        // Reduced pageSize to 20 for performance
        $url = firestore_base_url() . '/' . rawurlencode('support_chats') . '?pageSize=20';
        $res = firestore_rest_request('GET', $url);
        
        $chats = [];
        if (isset($res['documents'])) {
            foreach ($res['documents'] as $doc) {
                $data = firestore_decode_fields($doc['fields'] ?? []);
                $data['_id'] = basename($doc['name']);
                $data['_created'] = $doc['createTime'] ?? null;
                $chats[] = $data;
            }
        }
        
        // Sort manually by timestamp or _created
        usort($chats, function($a, $b) {
            $t1 = $a['timestamp'] ?? $a['_created'] ?? '';
            $t2 = $b['timestamp'] ?? $b['_created'] ?? '';
            // Handle DateTime objects if present (though decode usually gives strings/timestamps)
            if (is_object($t1) && method_exists($t1, 'format')) $t1 = $t1->format('c');
            if (is_object($t2) && method_exists($t2, 'format')) $t2 = $t2->format('c');
            return strcmp($t2, $t1); // Descending
        });

        $filteredChats = [];

        foreach ($chats as $chat) {
            $chatUserId = $chat['_id']; // The document ID is the userId
            
            // Use data directly from the chat document
            // We DO NOT fetch user profile here to avoid N+1 performance issues
            $userName = $chat['userName'] ?? 'Unknown User';
            $userLocation = $chat['location'] ?? '';
            $userBarangay = ''; 
            
            if (empty($userBarangay) && !empty($userLocation)) {
                $userBarangay = $userLocation; // Assume location string contains barangay
            }

            // Filtering Logic
            if ($userRole === 'staff' && !empty($assignedBarangay)) {
                $hasAccess = false;
                
                // 1. Check User Location
                // Allow if location matches OR if location is unknown (empty) so staff can triage
                if (empty($userLocation) && empty($userBarangay)) {
                    $hasAccess = true;
                } elseif (stripos($userLocation, $assignedBarangay) !== false || stripos($userBarangay, $assignedBarangay) !== false) {
                    $hasAccess = true;
                }

                // Allow access if location is unknown and status is pending/waiting (so someone can accept it)
                if (!$hasAccess && empty($userLocation) && empty($userBarangay) && 
                    (!isset($chat['status']) || $chat['status'] === 'pending' || $chat['status'] === 'waiting')) {
                    $hasAccess = true;
                }
                
                // 2. Check Related Report Location (if not yet matched)
                // OPTIMIZATION: Only check if we have the data in the chat document. 
                // Do NOT fetch the report document here.
                if (!$hasAccess && !empty($chat['relatedReportLocation'])) {
                     if (stripos($chat['relatedReportLocation'], $assignedBarangay) !== false) {
                        $hasAccess = true;
                    }
                }

                // 3. Always allow chats already accepted by this staff member
                if (!$hasAccess && isset($chat['acceptedBy']) && $chat['acceptedBy'] === $currentUserId) {
                    $hasAccess = true;
                }

                if (!$hasAccess) {
                    continue; // Skip this chat if it doesn't match the staff's barangay
                }
            }

            $chat['userName'] = $userName;
            $chat['location'] = $userLocation;
            if (empty($chat['id'])) {
                $chat['id'] = $chat['_id'] ?? $chat['userId'] ?? $chat['user_id'] ?? '';
            }
            $filteredChats[] = $chat;
        }

        echo json_encode(['chats' => $filteredChats]);

    } elseif ($action === 'get_messages') {
        $chatId = $_GET['chat_id'] ?? '';
        if (!$chatId) throw new Exception('Missing chat ID');

        if ($userRole === 'staff' && !empty($assignedBarangay)) {
             $hasAccess = false;
             
             // Optimization: Fetch chat document ONCE to check access
             $chatDoc = firestore_get_doc_by_id('support_chats', $chatId);
             
             if ($chatDoc) {
                 // 1. Check if accepted by this user (Fastest check)
                 if (isset($chatDoc['acceptedBy']) && $chatDoc['acceptedBy'] === $currentUserId) {
                     $hasAccess = true;
                 }
                 
                 // 2. Check User Location in Chat Doc
                 if (!$hasAccess) {
                     $userLocation = $chatDoc['location'] ?? '';
                     if (empty($userLocation) || stripos($userLocation, $assignedBarangay) !== false) {
                         $hasAccess = true;
                     }
                 }

                 // 3. Check Related Report Location (Only if needed)
                 if (!$hasAccess && !empty($chatDoc['relatedReportLocation'])) {
                     if (stripos($chatDoc['relatedReportLocation'], $assignedBarangay) !== false) {
                         $hasAccess = true;
                     }
                 }
             }

             if (!$hasAccess) {
                 echo json_encode(['error' => 'Access Denied: Outside jurisdiction']);
                 exit;
             }
        }

        // Fetch messages subcollection
        // We need a way to query subcollections. 
        // firestore_query_latest queries a root collection usually.
        // We need to construct the path: support_chats/{chatId}/messages
        
        $url = firestore_base_url() . '/' . rawurlencode('support_chats') . '/' . rawurlencode($chatId) . '/' . rawurlencode('messages');
        // We want to order by timestamp asc usually for chat, but firestore REST list is default ordered.
        // Let's use runQuery to sort.
        
        $queryUrl = firestore_base_url() . ':runQuery';
        $body = [
            'structuredQuery' => [
                'from' => [['collectionId' => 'messages', 'allDescendants' => false]], // Query within the specific document context? No, REST API is tricky for subcollections with runQuery unless we specify parent.
            ]
        ];
        
        // Actually, for subcollections, we should use the parent in the URL for runQuery?
        // Or just use listDocuments with orderBy?
        // Let's try listDocuments (GET) first, it's simpler.
        // Limit to 100 messages to prevent slow loading of long conversations
        $listUrl = $url . '?orderBy=timestamp&pageSize=100';
        $res = firestore_rest_request('GET', $listUrl);
        
        $messages = [];
        if (isset($res['documents'])) {
            foreach ($res['documents'] as $doc) {
                $data = firestore_decode_fields($doc['fields'] ?? []);
                $data['_id'] = basename($doc['name']);
                $messages[] = $data;
            }
        }
        
        echo json_encode(['messages' => $messages]);

    } elseif ($action === 'send_message') {
        $chatId = $_POST['chat_id'] ?? '';
        $text = $_POST['text'] ?? '';
        
        if (!$chatId || !$text) {
            throw new Exception('Missing parameters');
        }

        // 1. Add message to subcollection
        $msgData = [
            'sender' => 'admin', // Or 'staff', maybe use actual name or ID
            'senderId' => $currentUserId,
            'senderName' => $_SESSION['user_fullname'] ?? 'Support',
            'text' => $text,
            'timestamp' => new DateTime(), // Will be converted to timestampValue
            'isAdmin' => true // Flag to distinguish from user messages
        ];
        
        $msgUrl = firestore_base_url() . '/' . rawurlencode('support_chats') . '/' . rawurlencode($chatId) . '/' . rawurlencode('messages');
        $res = firestore_rest_request('POST', $msgUrl, ['fields' => firestore_encode_fields($msgData)]);
        
        // 2. Update parent chat document
        $updateData = [
            'lastMessage' => $text,
            'lastMessageTimestamp' => new DateTime(),
            'status' => 'active', // Ensure it's active
            'unreadCount' => 0 // Reset unread count for admin? Or increment for user? 
                               // Usually if admin sends, user has unread. 
                               // But here we just update last message.
        ];
        
        // Use fast update for parent document to improve performance
        if (function_exists('firestore_set_document_fast')) {
            firestore_set_document_fast('support_chats', $chatId, $updateData);
        } else {
            firestore_set_document('support_chats', $chatId, $updateData);
        }
        
        echo json_encode(['success' => true, 'data' => $res]);

    } elseif ($action === 'accept_chat') {
        $chatId = $_POST['chat_id'] ?? '';
        if (!$chatId) {
            throw new Exception('Chat ID required');
        }

        // Debug logging
        error_log("Accepting chat: $chatId by user $currentUserId");

        // 1. Fetch current chat document to preserve existing data
        $currentChat = firestore_get_doc_by_id('support_chats', $chatId);
        $existingUserName = $currentChat['userName'] ?? '';
        $existingLocation = $currentChat['location'] ?? '';

        // 2. Fetch user details to ensure they are in the chat document
        $userName = '';
        $userLocation = '';
        
        try {
            // The chat ID is the user ID in this system
            $userProfile = get_user_details_cached($chatId);
            
            if (is_array($userProfile) && !empty($userProfile)) {
                // Try multiple fields for name
                $userName = $userProfile['fullName'] ?? $userProfile['name'] ?? $userProfile['displayName'] ?? '';
                
                // Try constructing from parts
                if (empty($userName)) {
                    $firstName = $userProfile['firstName'] ?? $userProfile['firstname'] ?? '';
                    $lastName = $userProfile['lastName'] ?? $userProfile['lastname'] ?? '';
                    if (!empty($firstName) || !empty($lastName)) {
                        $userName = trim("$firstName $lastName");
                    }
                }

                // Try multiple fields for location/address
                $userLocation = $userProfile['address'] ?? $userProfile['location'] ?? $userProfile['currentAddress'] ?? $userProfile['permanentAddress'] ?? '';
            }
        } catch (Exception $e) {
            error_log("Error fetching user profile for chat $chatId: " . $e->getMessage());
        }

        // Fallback logic:
        // 1. Use fetched profile name if available
        // 2. Use existing chat name if available and not "Unknown User"
        // 3. Default to "Unknown User"
        
        $finalUserName = 'Unknown User';
        if (!empty($userName) && $userName !== 'Unknown User') {
            $finalUserName = $userName;
        } elseif (!empty($existingUserName) && $existingUserName !== 'Unknown User') {
            $finalUserName = $existingUserName;
        }

        $finalLocation = !empty($userLocation) ? $userLocation : $existingLocation;

        // Update chat status to active AND backfill user info
        $updateData = [
            'status' => 'active',
            'acceptedBy' => $currentUserId,
            'acceptedByName' => $_SESSION['user_fullname'] ?? 'Staff',
            'acceptedAt' => new DateTime(),
            'userName' => $finalUserName,
            'location' => $finalLocation
        ];
        
        $updateSuccess = false;

        // Use fast update if available to speed up response
        if (function_exists('firestore_set_document_fast')) {
            try {
                $updateSuccess = firestore_set_document_fast('support_chats', $chatId, $updateData);
                error_log("Fast update result for $chatId: " . ($updateSuccess ? 'Success' : 'Failed'));
            } catch (Exception $e) {
                error_log("Fast update exception for $chatId: " . $e->getMessage());
            }
        } 
        
        // Fallback to standard update if fast update failed or not available
        if (!$updateSuccess) {
            error_log("Falling back to standard update for $chatId");
            try {
                firestore_set_document('support_chats', $chatId, $updateData);
                $updateSuccess = true;
            } catch (Exception $e) {
                error_log("Standard update exception for $chatId: " . $e->getMessage());
                throw $e; // Re-throw to be caught by main catch block
            }
        }
        
        // Send a system message indicating the chat has been accepted
        // We do this AFTER the status update to ensure the chat is active
        $msgData = [
            'sender' => 'system',
            'senderId' => 'system',
            'senderName' => 'System',
            'text' => 'Chat request accepted by ' . ($_SESSION['user_fullname'] ?? 'Staff'),
            'timestamp' => new DateTime(),
            'isAdmin' => true,
            'isSystem' => true
        ];
        
        $msgUrl = firestore_base_url() . '/' . rawurlencode('support_chats') . '/' . rawurlencode($chatId) . '/' . rawurlencode('messages');
        
        // Use a shorter timeout for the system message to avoid blocking
        try {
            firestore_rest_request('POST', $msgUrl, ['fields' => firestore_encode_fields($msgData)]);
        } catch (Exception $e) {
            // Ignore message send failure, as long as status is updated
            error_log("Failed to send system message for chat acceptance: " . $e->getMessage());
        }
        
        echo json_encode(['success' => true]);
    } elseif ($action === 'end_chat') {
        $chatId = $_POST['chat_id'] ?? '';
        if (!$chatId) {
            throw new Exception('Chat ID required');
        }

        error_log("Ending chat $chatId by $currentUserId");

        // Update chat status to ended
        $updateData = [
            'status' => 'ended',
            'endedBy' => $currentUserId,
            'endedByName' => $_SESSION['user_fullname'] ?? 'Staff',
            'endedAt' => new DateTime()
        ];
        
        // Use standard update - simple and reliable
        try {
            firestore_set_document('support_chats', $chatId, $updateData);
        } catch (Exception $e) {
            error_log("Failed to update chat status to ended: " . $e->getMessage());
            throw new Exception("Failed to update chat status: " . $e->getMessage());
        }
        
        // Send a system message
        $msgData = [
            'sender' => 'system',
            'senderId' => 'system',
            'senderName' => 'System',
            'text' => 'Chat ended by ' . ($_SESSION['user_fullname'] ?? 'Staff'),
            'timestamp' => new DateTime(),
            'isAdmin' => true,
            'isSystem' => true
        ];
        
        $msgUrl = firestore_base_url() . '/' . rawurlencode('support_chats') . '/' . rawurlencode($chatId) . '/' . rawurlencode('messages');
        try {
            firestore_rest_request('POST', $msgUrl, ['fields' => firestore_encode_fields($msgData)]);
        } catch (Exception $e) {
            error_log("Failed to send system message for chat end: " . $e->getMessage());
            // Don't fail the whole request if just the message fails
        }
        
        // Clear buffer and output JSON immediately
        if (ob_get_length()) ob_clean();
        echo json_encode(['success' => true]);
        exit;

    }

} catch (Throwable $e) {
    // Ensure clean JSON output even on error
    if (ob_get_length()) ob_clean();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
