<?php
session_start();

// Check if user is already logged in
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    // User is logged in, redirect to dashboard
    header('Location: dashboard');
    exit();
}

// User is not logged in, redirect to login page
header('Location: login');
exit();
?>
