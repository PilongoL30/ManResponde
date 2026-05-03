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
$assignedBarangay = $_SESSION['assignedBarangay'] ?? ($_SESSION['assigned_barangay'] ?? '');
$staffCategories = $_SESSION['user_categories'] ?? [];

if (!is_array($staffCategories)) {
    $staffCategories = array_filter(array_map('trim', explode(',', (string)$staffCategories)));
}

function normalize_chat_category($value) {
    $v = strtolower(trim((string)$value));
    if ($v === '') return '';
    if (strpos($v, 'ambulance') !== false) return 'ambulance';
    if (strpos($v, 'fire') !== false) return 'fire';
    if (strpos($v, 'tanod') !== false) return 'tanod';
    if (strpos($v, 'barangay') !== false || strpos($v, 'brgy') !== false) return 'barangay';
    return $v;
}

function extract_chat_category(array $chat): string {
    $candidates = [
        $chat['chatCategory'] ?? '',
        $chat['category'] ?? '',
        $chat['relatedReportType'] ?? '',
        $chat['reportType'] ?? '',
        $chat['type'] ?? '',
    ];
    foreach ($candidates as $candidate) {
        $normalized = normalize_chat_category($candidate);
        if ($normalized !== '') return $normalized;
    }
    return '';
}

function category_requires_barangay(string $category): bool {
    return in_array($category, ['tanod', 'barangay'], true);
}

function is_known_chat_category(string $category): bool {
    return in_array($category, ['ambulance', 'fire', 'tanod', 'barangay'], true);
}

function staff_matches_chat_category(string $userRole, array $staffCategories, string $chatCategory): bool {
    if ($userRole === 'admin') return true;
    if ($chatCategory === '') return true;
    if (!is_known_chat_category($chatCategory)) return true;

    $normalizedStaff = [];
    foreach ($staffCategories as $cat) {
        $n = normalize_chat_category($cat);
        if ($n !== '') $normalizedStaff[] = $n;
    }

    if (in_array($chatCategory, ['tanod', 'barangay'], true)) {
        return in_array('tanod', $normalizedStaff, true) || in_array('barangay', $normalizedStaff, true);
    }

    return in_array($chatCategory, $normalizedStaff, true);
}

function staff_matches_chat_barangay(array $chat, string $assignedBarangay, string $chatCategory): bool {
    if (!category_requires_barangay($chatCategory)) {
        return true;
    }

    $assigned = strtolower(trim($assignedBarangay));
    if ($assigned === '') return false;

    $haystack = strtolower(trim(implode(' ', array_filter([
        (string)($chat['location'] ?? ''),
        (string)($chat['relatedReportLocation'] ?? ''),
        (string)($chat['barangay'] ?? ''),
        (string)($chat['userBarangay'] ?? ''),
        (string)($chat['requesterBarangay'] ?? ''),
        (string)($chat['assignedBarangay'] ?? ''),
        (string)($chat['currentBarangay'] ?? ''),
        (string)($chat['address'] ?? ''),
    ]))));

    if ($haystack === '') return false;

    return strpos($haystack, $assigned) !== false;
}

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

// Fallback: If staff categories are missing in session, fetch from profile
if ($userRole === 'staff' && empty($staffCategories)) {
    $staffProfile = get_user_details_cached($currentUserId);
    $profileCategories = $staffProfile['categories'] ?? [];
    if (!is_array($profileCategories)) {
        $profileCategories = array_filter(array_map('trim', explode(',', (string)$profileCategories)));
    }
    $staffCategories = $profileCategories;
    $_SESSION['user_categories'] = $staffCategories;
}

try {
    if ($action === 'get_chats') {
        // Fetch chats using listDocuments to ensure we see all documents 
        // (even those missing the 'status' field which runQuery would skip)
        $url = firestore_base_url() . '/' . rawurlencode('support_chats') . '?pageSize=100';
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

        // Sort in PHP (most recent first)
        usort($chats, function($a, $b) {
            $t1 = $a['lastMessageTimestamp'] ?? $a['timestamp'] ?? $a['_created'] ?? '';
            $t2 = $b['lastMessageTimestamp'] ?? $b['timestamp'] ?? $b['_created'] ?? '';
            return strcmp($t2, $t1);
        });

        $filteredChats = [];
        foreach ($chats as $chat) {
            $status = strtolower(trim((string)($chat['status'] ?? 'pending')));
            if ($status === '') $status = 'pending';
            
            // For general list, exclude ended chats older than 24 hours to keep it clean
            if ($status === 'ended') {
                $ts = $chat['lastMessageTimestamp'] ?? $chat['timestamp'] ?? $chat['_created'] ?? '';
                if ($ts && (time() - strtotime($ts)) > 86400) continue;
            }

            // Minimal filtering for staff (access control)
            if ($userRole === 'staff') {
                $acceptedBy = (string)($chat['acceptedBy'] ?? '');
                $isPending = in_array($status, ['pending', 'waiting'], true);
                $isOwnedByCurrentStaff = ($acceptedBy !== '' && $acceptedBy === $currentUserId);
                $chatCategory = extract_chat_category($chat);

                if (!$isPending && !$isOwnedByCurrentStaff) continue;
                if (!$isPending && !$isOwnedByCurrentStaff) {
                    if (!staff_matches_chat_category($userRole, $staffCategories, $chatCategory)) continue;
                    if (!staff_matches_chat_barangay($chat, $assignedBarangay, $chatCategory)) continue;
                }
            }

            $chat['id'] = $chat['_id'];
            $chat['status'] = $status;
            $chat['userName'] = $chat['userName'] ?? 'Anonymous';
            $chat['lastMessage'] = $chat['lastMessage'] ?? 'No messages yet';
            $chat['lastMessageTime'] = $chat['lastMessageTimestamp'] ?? $chat['_created'] ?? '';
            
            $filteredChats[] = $chat;
        }

        echo json_encode(['chats' => $filteredChats]);

    } elseif ($action === 'get_messages') {
        $chatId = $_GET['chat_id'] ?? '';
        if (!$chatId) throw new Exception('Missing chat ID');

        $chatDoc = firestore_get_doc_by_id('support_chats', $chatId);
        if (!$chatDoc) {
            echo json_encode(['error' => 'Chat not found']);
            exit;
        }

        if ($userRole === 'staff') {
            $status = strtolower(trim((string)($chatDoc['status'] ?? 'pending')));
            $acceptedBy = (string)($chatDoc['acceptedBy'] ?? '');
            $isPending = in_array($status, ['pending', 'waiting'], true);
            $isOwnedByCurrentStaff = ($acceptedBy !== '' && $acceptedBy === $currentUserId);
            $chatCategory = extract_chat_category($chatDoc);

            if (!$isOwnedByCurrentStaff) {
                // Do not allow opening another staff member's active/ended chat.
                if (!$isPending) {
                    echo json_encode(['error' => 'Access Denied: This chat is handled by another staff member']);
                    exit;
                }

                if (!staff_matches_chat_category($userRole, $staffCategories, $chatCategory) ||
                    !staff_matches_chat_barangay($chatDoc, $assignedBarangay, $chatCategory)) {
                    echo json_encode(['error' => 'Access Denied: Outside assigned category/jurisdiction']);
                    exit;
                }
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
        $text = $_POST['message'] ?? ($_POST['text'] ?? '');
        
        if (!$chatId || $text === '') {
            throw new Exception('Missing parameters: chatId or message');
        }

        // Staff can only send messages to chats they accepted.
        if ($userRole === 'staff') {
            $chatDoc = firestore_get_doc_by_id('support_chats', $chatId);
            $acceptedBy = (string)($chatDoc['acceptedBy'] ?? '');
            if ($acceptedBy === '' || $acceptedBy !== $currentUserId) {
                throw new Exception('Access denied: You can only message chats you accepted.');
            }
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
        $selectedCategory = normalize_chat_category($_POST['chat_category'] ?? '');
        if (!$chatId) {
            throw new Exception('Chat ID required');
        }

        // Debug logging
        error_log("Accepting chat: $chatId by user $currentUserId");

        // 1. Fetch current chat document to preserve existing data
        $currentChat = firestore_get_doc_by_id('support_chats', $chatId);
        if (!$currentChat) {
            throw new Exception('Chat not found');
        }

        // Prevent accepting a chat already accepted by another staff member.
        $alreadyAcceptedBy = (string)($currentChat['acceptedBy'] ?? '');
        $currentStatus = strtolower(trim((string)($currentChat['status'] ?? 'pending')));
        if ($alreadyAcceptedBy !== '' && $alreadyAcceptedBy !== $currentUserId && in_array($currentStatus, ['active', 'ended'], true)) {
            throw new Exception('This chat is already assigned to another staff member.');
        }

        if ($userRole === 'staff') {
            $chatCategory = $selectedCategory !== '' ? $selectedCategory : extract_chat_category($currentChat);
            if (!staff_matches_chat_category($userRole, $staffCategories, $chatCategory) ||
                !staff_matches_chat_barangay($currentChat, $assignedBarangay, $chatCategory)) {
                throw new Exception('Access denied: Chat is outside your assigned category or barangay.');
            }
        }

        $existingUserName = $currentChat['userName'] ?? '';
        $existingLocation = $currentChat['location'] ?? '';

        // 2. Fetch user details ONLY if missing or unknown
        $userName = '';
        $userLocation = '';
        
        if (empty($existingUserName) || $existingUserName === 'Unknown User' || empty($existingLocation)) {
            try {
                $userProfile = get_user_details_cached($chatId);
                if (is_array($userProfile) && !empty($userProfile)) {
                    $userName = $userProfile['fullName'] ?? $userProfile['name'] ?? $userProfile['displayName'] ?? '';
                    if (empty($userName)) {
                        $firstName = $userProfile['firstName'] ?? $userProfile['firstname'] ?? '';
                        $lastName = $userProfile['lastName'] ?? $userProfile['lastname'] ?? '';
                        if (!empty($firstName) || !empty($lastName)) $userName = trim("$firstName $lastName");
                    }
                    $userLocation = $userProfile['address'] ?? $userProfile['location'] ?? $userProfile['currentAddress'] ?? $userProfile['permanentAddress'] ?? '';
                }
            } catch (Exception $e) {}
        }

        $finalUserName = (!empty($userName) && $userName !== 'Unknown User') ? $userName : (!empty($existingUserName) ? $existingUserName : 'Unknown User');
        $finalLocation = !empty($userLocation) ? $userLocation : $existingLocation;
        $finalCategory = $selectedCategory !== '' ? $selectedCategory : extract_chat_category($currentChat);

        // Update chat status to active
        $updateData = [
            'status' => 'active',
            'acceptedBy' => $currentUserId,
            'acceptedByName' => $_SESSION['user_fullname'] ?? 'Staff',
            'acceptedAt' => new DateTime(),
            'userName' => $finalUserName,
            'location' => $finalLocation
        ];

        if ($finalCategory !== '') {
            $updateData['chatCategory'] = $finalCategory;
            if (empty($currentChat['relatedReportType'])) $updateData['relatedReportType'] = ucfirst($finalCategory);
        }
        
        $updateSuccess = false;
        if (function_exists('firestore_set_document_fast')) {
            $updateSuccess = firestore_set_document_fast('support_chats', $chatId, $updateData);
        } 
        
        if (!$updateSuccess) {
            firestore_set_document('support_chats', $chatId, $updateData);
        }
        
        // System message (done after status update)
        $msgUrl = firestore_base_url() . '/' . rawurlencode('support_chats') . '/' . rawurlencode($chatId) . '/' . rawurlencode('messages');
        try {
            $msgData = [
                'sender' => 'system', 'senderId' => 'system', 'senderName' => 'System',
                'text' => 'Chat request accepted by ' . ($_SESSION['user_fullname'] ?? 'Staff'),
                'timestamp' => new DateTime(), 'isAdmin' => true, 'isSystem' => true
            ];
            firestore_rest_request('POST', $msgUrl, ['fields' => firestore_encode_fields($msgData)]);
        } catch (Exception $e) {}
        
        echo json_encode(['success' => true]);
    } elseif ($action === 'end_chat') {
        $chatId = $_POST['chat_id'] ?? '';
        if (!$chatId) {
            throw new Exception('Chat ID required');
        }

        if ($userRole === 'staff') {
            $chatDoc = firestore_get_doc_by_id('support_chats', $chatId);
            $acceptedBy = (string)($chatDoc['acceptedBy'] ?? '');
            if ($acceptedBy === '' || $acceptedBy !== $currentUserId) {
                throw new Exception('Access denied: You can only end chats you accepted.');
            }
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
