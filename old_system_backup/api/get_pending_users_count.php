<?php
require_once __DIR__ . '/../db_config.php';
header('Content-Type: application/json');

try {
    session_start();
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['count' => 0, 'error' => 'Unauthorized']);
        exit;
    }

    $userRole = $_SESSION['user_role'] ?? 'staff';
    $isAdmin = ($userRole === 'admin');
    // Get assigned barangay and normalize it
    $assignedBarangay = strtolower(trim($_SESSION['assignedBarangay'] ?? ''));

    // If Admin, count all pending users globally (existing logic)
    if ($isAdmin) {
        $count = firestore_count('users', 'pending');
        if ($count === 0) {
            $count = firestore_count('users', 'Pending');
        }
        echo json_encode(['count' => $count]);
        exit;
    }

    // If Staff/Tanod, we need to filter by barangay
    if (empty($assignedBarangay)) {
        echo json_encode(['count' => 0]);
        exit;
    }

    // Helper function to fetch and count with client-side filtering (server-side PHP)
    function count_filtered_users($statusValue, $assignedBarangay) {
        $url = firestore_base_url() . ':runQuery';
        $body = [
            'structuredQuery' => [
                'from' => [['collectionId' => 'users']],
                'where' => [
                    'fieldFilter' => [
                        'field' => ['fieldPath' => 'status'],
                        'op'    => 'EQUAL',
                        'value' => ['stringValue' => $statusValue],
                    ],
                ],
                // Select only necessary fields to reduce bandwidth
                'select' => [
                    'fields' => [
                        ['fieldPath' => 'currentBarangay'],
                        ['fieldPath' => 'barangay'],
                        ['fieldPath' => 'Barangay'],
                        ['fieldPath' => 'currentAddress'],
                        ['fieldPath' => 'address'],
                    ]
                ],
                'limit' => 500, // Match frontend limit
            ],
        ];

        try {
            $response = firestore_rest_request('POST', $url, $body);
        } catch (Exception $e) {
            return 0;
        }
        
        $localCount = 0;
        if (is_array($response)) {
             foreach ($response as $row) {
                 if (!isset($row['document'])) continue;
                 $fields = $row['document']['fields'] ?? [];
                 
                 // Extract fields (handling multiple possible keys)
                 $uBarangay = '';
                 if (isset($fields['currentBarangay']['stringValue'])) $uBarangay = $fields['currentBarangay']['stringValue'];
                 elseif (isset($fields['barangay']['stringValue'])) $uBarangay = $fields['barangay']['stringValue'];
                 elseif (isset($fields['Barangay']['stringValue'])) $uBarangay = $fields['Barangay']['stringValue'];
                 
                 $uAddress = '';
                 if (isset($fields['currentAddress']['stringValue'])) $uAddress = $fields['currentAddress']['stringValue'];
                 elseif (isset($fields['address']['stringValue'])) $uAddress = $fields['address']['stringValue'];
                 
                 // Normalize
                 $uBarangay = strtolower(trim($uBarangay));
                 $uAddress = strtolower(trim($uAddress));
                 
                 // Filter logic matching verify_users.php
                 if ($uBarangay === $assignedBarangay || (!empty($uAddress) && strpos($uAddress, $assignedBarangay) !== false)) {
                     $localCount++;
                 }
             }
        }
        return $localCount;
    }

    // Count 'pending' (lowercase)
    $totalCount = count_filtered_users('pending', $assignedBarangay);
    
    // If 0, try 'Pending' (Title case) - just in case mixed data exists
    if ($totalCount === 0) {
        $totalCount = count_filtered_users('Pending', $assignedBarangay);
    }

    echo json_encode(['count' => $totalCount]);

} catch (Exception $e) {
    error_log("Error counting pending users: " . $e->getMessage());
    echo json_encode(['count' => 0, 'error' => $e->getMessage()]);
}
?>