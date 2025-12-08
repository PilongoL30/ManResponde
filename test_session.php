<?php
// Simple session test to debug redirect loop issue

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "<h1>Session Debug Test</h1>";
echo "<pre>";

echo "Session Status: " . (session_status() === PHP_SESSION_ACTIVE ? 'ACTIVE' : 'NOT ACTIVE') . "\n";
echo "Session ID: " . session_id() . "\n";
echo "Session Save Path: " . session_save_path() . "\n\n";

echo "Current Session Data:\n";
print_r($_SESSION);

// Test setting a value
if (!isset($_SESSION['test_value'])) {
    $_SESSION['test_value'] = 'Session is working!';
    $_SESSION['test_time'] = date('Y-m-d H:i:s');
    echo "\n✅ Set test values in session\n";
} else {
    echo "\n✅ Session persisted from previous request!\n";
    echo "Test Value: " . $_SESSION['test_value'] . "\n";
    echo "Test Time: " . $_SESSION['test_time'] . "\n";
}

echo "\n\nSession Cookie Settings:\n";
echo "Name: " . session_name() . "\n";
echo "Path: " . ini_get('session.cookie_path') . "\n";
echo "Domain: " . ini_get('session.cookie_domain') . "\n";
echo "Secure: " . ini_get('session.cookie_secure') . "\n";
echo "HttpOnly: " . ini_get('session.cookie_httponly') . "\n";
echo "SameSite: " . ini_get('session.cookie_samesite') . "\n";

echo "\n\nCookies Received:\n";
print_r($_COOKIE);

echo "</pre>";

echo '<p><a href="test_session.php">Refresh to test session persistence</a></p>';
echo '<p><a href="test_session.php?clear=1">Clear session</a></p>';

if (isset($_GET['clear'])) {
    session_destroy();
    echo '<p style="color: red;">Session destroyed. <a href="test_session.php">Click here</a> to start fresh.</p>';
}
