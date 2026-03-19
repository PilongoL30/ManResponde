<?php
require_once __DIR__ . '/db_config.php';
session_start();

// Check if user is already logged in
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    // User is logged in, redirect to dashboard
    header('Location: dashboard.php');
    exit();
}

// User is not logged in, show the public landing page
require __DIR__ . '/home.php';
// header('Location: login'); // previous behavior
// exit();
?>
