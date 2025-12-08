<?php
// Test dashboard variables
session_start();

echo "<h1>Dashboard Variables Test</h1>";
echo "<pre>";

echo "Session Variables:\n";
print_r($_SESSION);

echo "\n\nRequired Variables for Dashboard:\n";
echo "user_id: " . ($_SESSION['user_id'] ?? 'NOT SET') . "\n";
echo "user_role: " . ($_SESSION['user_role'] ?? 'NOT SET') . "\n";
echo "user_fullname: " . ($_SESSION['user_fullname'] ?? 'NOT SET') . "\n";
echo "user_email: " . ($_SESSION['user_email'] ?? 'NOT SET') . "\n";

// Simulate what dashboard.php does
require_once __DIR__ . '/db_config.php';

function get_user_profile($uid) {
    try {
        $firestore = initialize_firestore();
        $snap = $firestore->collection('users')->document($uid)->snapshot();
        return $snap->exists() ? ($snap->data() ?? []) : [];
    } catch (Throwable $e) {
        echo "\n❌ Error getting profile: " . $e->getMessage() . "\n";
    }
    return [];
}

if (isset($_SESSION['user_id'])) {
    echo "\n\n User Profile from Firestore:\n";
    $profile = get_user_profile($_SESSION['user_id']);
    print_r($profile);
    
    $categories = $profile['categories'] ?? [];
    echo "\n\nUser Categories: ";
    print_r($categories);
}

echo "</pre>";
