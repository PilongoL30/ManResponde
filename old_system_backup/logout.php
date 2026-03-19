<?php
// logout.php

require_once __DIR__ . '/db_config.php';

// Start the session.
session_start();

// Unset all of the session variables.
$_SESSION = array();

// Destroy the session.
session_destroy();

// Redirect to the login page after logging out.
header('Location: login');
exit();
?>
