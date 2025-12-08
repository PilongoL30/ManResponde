<?php
/**
 * ManResponde Configuration
 * Central configuration for environment-specific settings
 */

// Environment: 'development' or 'production'
define('APP_ENV', getenv('APP_ENV') ?: 'production');
define('IS_DEVELOPMENT', APP_ENV === 'development');
define('IS_PRODUCTION', APP_ENV === 'production');

// Debug Settings
define('DEBUG_MODE', IS_DEVELOPMENT);
define('DISPLAY_ERRORS', IS_DEVELOPMENT);
define('LOG_ERRORS', true);
define('ERROR_LOG_PATH', __DIR__ . '/logs/error.log');

// Security Settings
define('SSL_VERIFY', IS_PRODUCTION); // Enable SSL verification in production
define('CSRF_TOKEN_NAME', '_csrf_token');
define('CSRF_TOKEN_LENGTH', 32);

// Cache Settings
define('CACHE_ENABLED', true);
define('CACHE_DIR', __DIR__ . '/cache');
define('CACHE_DEFAULT_TTL', 300); // 5 minutes

// Session Settings
define('SESSION_NAME', 'manresponde_session');
define('SESSION_LIFETIME', 3600); // 1 hour

// Firebase Settings
define('FIRESTORE_USE_REST', true); // Force REST for Windows compatibility

// Performance Settings
define('DEFAULT_PAGE_SIZE', 20);
define('MAX_PAGE_SIZE', 100);

// Configure error reporting based on environment
if (IS_PRODUCTION) {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
    ini_set('display_errors', '0');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

// Ensure required directories exist
$requiredDirs = [
    __DIR__ . '/logs',
    __DIR__ . '/cache',
    __DIR__ . '/sessions'
];

foreach ($requiredDirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Set custom error log
if (LOG_ERRORS) {
    ini_set('log_errors', '1');
    ini_set('error_log', ERROR_LOG_PATH);
}
