<?php
// logout.php

require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
	$sessionPath = __DIR__ . '/sessions';
	if (!is_dir($sessionPath)) {
		mkdir($sessionPath, 0755, true);
	}
	session_save_path($sessionPath);
	ini_set('session.gc_probability', 1);
	ini_set('session.gc_divisor', 100);
}

if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
	$params = session_get_cookie_params();
	setcookie(session_name(), '', time() - 42000,
		$params['path'], $params['domain'],
		(bool)$params['secure'], (bool)$params['httponly']
	);
}

session_destroy();

header('Location: login.php');
exit();
?>
