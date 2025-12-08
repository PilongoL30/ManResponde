<?php
/**
 * Phase 1 Implementation Validator
 * Tests all Phase 1 security and performance enhancements
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/cache.php';
require_once __DIR__ . '/includes/csrf.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: text/html; charset=UTF-8');

function test_result($name, $passed, $message = '') {
    $icon = $passed ? '✅' : '❌';
    $class = $passed ? 'success' : 'error';
    echo "<div class='test-item {$class}'>";
    echo "<span class='icon'>{$icon}</span>";
    echo "<span class='name'>{$name}</span>";
    if ($message) {
        echo "<span class='message'>{$message}</span>";
    }
    echo "</div>\n";
    return $passed;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Phase 1 Implementation Validator</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .section {
            margin-bottom: 30px;
        }
        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #444;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e0e0e0;
        }
        .test-item {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            margin-bottom: 8px;
            border-radius: 6px;
            background: #f8f9fa;
        }
        .test-item.success {
            background: #d4edda;
            border-left: 4px solid #28a745;
        }
        .test-item.error {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
        }
        .test-item .icon {
            font-size: 20px;
            margin-right: 12px;
        }
        .test-item .name {
            flex: 1;
            font-weight: 500;
            color: #333;
        }
        .test-item .message {
            font-size: 12px;
            color: #666;
            margin-left: 10px;
        }
        .summary {
            margin-top: 30px;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 8px;
            text-align: center;
        }
        .summary h2 {
            font-size: 24px;
            margin-bottom: 10px;
        }
        .summary .stats {
            font-size: 16px;
            opacity: 0.9;
        }
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .info-box strong {
            color: #1976D2;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Phase 1 Implementation Validator</h1>
        <div class="subtitle">Testing Security & Performance Enhancements</div>

        <?php
        $total_tests = 0;
        $passed_tests = 0;
        
        // === Configuration Tests ===
        echo '<div class="section">';
        echo '<div class="section-title">📋 Configuration</div>';
        
        $total_tests++;
        if (test_result('config.php loaded', defined('APP_ENV'), 'Environment: ' . (defined('APP_ENV') ? APP_ENV : 'undefined'))) {
            $passed_tests++;
        }
        
        $total_tests++;
        if (test_result('Cache enabled', defined('CACHE_ENABLED') && CACHE_ENABLED, 'TTL: ' . (defined('CACHE_DEFAULT_TTL') ? CACHE_DEFAULT_TTL . 's' : 'N/A'))) {
            $passed_tests++;
        }
        
        $total_tests++;
        if (test_result('SSL configuration', defined('SSL_VERIFY'), SSL_VERIFY ? 'Enabled (Production)' : 'Disabled (Development)')) {
            $passed_tests++;
        }
        
        $total_tests++;
        if (test_result('CSRF token name defined', defined('CSRF_TOKEN_NAME'), CSRF_TOKEN_NAME ?? 'N/A')) {
            $passed_tests++;
        }
        
        echo '</div>';
        
        // === Directory Tests ===
        echo '<div class="section">';
        echo '<div class="section-title">📁 Directories</div>';
        
        $dirs = ['cache', 'logs', 'sessions'];
        foreach ($dirs as $dir) {
            $total_tests++;
            $path = __DIR__ . '/' . $dir;
            $exists = is_dir($path);
            $writable = $exists && is_writable($path);
            if (test_result("/{$dir} directory", $exists && $writable, $writable ? 'Writable' : ($exists ? 'Not writable' : 'Missing'))) {
                $passed_tests++;
            }
        }
        
        echo '</div>';
        
        // === CSRF Tests ===
        echo '<div class="section">';
        echo '<div class="section-title">🔒 CSRF Protection</div>';
        
        $total_tests++;
        if (test_result('csrf.php loaded', function_exists('csrf_generate_token'))) {
            $passed_tests++;
        }
        
        $total_tests++;
        $token = csrf_generate_token();
        if (test_result('CSRF token generation', !empty($token), substr($token, 0, 20) . '...')) {
            $passed_tests++;
        }
        
        $total_tests++;
        if (test_result('CSRF token validation', csrf_verify_token($token), 'Token matches')) {
            $passed_tests++;
        }
        
        $total_tests++;
        if (test_result('CSRF field helper', !empty(csrf_field()), 'HTML input generated')) {
            $passed_tests++;
        }
        
        $total_tests++;
        if (test_result('CSRF meta helper', !empty(csrf_meta()), 'Meta tag generated')) {
            $passed_tests++;
        }
        
        echo '</div>';
        
        // === Cache Tests ===
        echo '<div class="section">';
        echo '<div class="section-title">⚡ Caching System</div>';
        
        $total_tests++;
        if (test_result('cache.php loaded', function_exists('cache_get'))) {
            $passed_tests++;
        }
        
        $total_tests++;
        $test_value = ['test' => 'data', 'timestamp' => time()];
        $cache_set = cache_set('test_key', $test_value, 60);
        if (test_result('Cache set operation', $cache_set, 'Value stored')) {
            $passed_tests++;
        }
        
        $total_tests++;
        $cached = cache_get('test_key', 60);
        if (test_result('Cache get operation', $cached !== null && $cached['test'] === 'data', 'Value retrieved')) {
            $passed_tests++;
        }
        
        $total_tests++;
        cache_delete('test_key');
        $deleted = cache_get('test_key', 60);
        if (test_result('Cache delete operation', $deleted === null, 'Value removed')) {
            $passed_tests++;
        }
        
        $total_tests++;
        $stats = cache_stats();
        if (test_result('Cache statistics', is_array($stats) && isset($stats['total']), $stats['total'] . ' files, ' . $stats['size_mb'] . ' MB')) {
            $passed_tests++;
        }
        
        echo '</div>';
        
        // === File Tests ===
        echo '<div class="section">';
        echo '<div class="section-title">📄 Required Files</div>';
        
        $files = [
            'config.php' => 'Configuration file',
            'includes/csrf.php' => 'CSRF protection',
            'includes/cache.php' => 'Cache system',
            'db_config.php' => 'Database config',
            '.env.example' => 'Environment example'
        ];
        
        foreach ($files as $file => $desc) {
            $total_tests++;
            $exists = file_exists(__DIR__ . '/' . $file);
            if (test_result($file, $exists, $desc)) {
                $passed_tests++;
            }
        }
        
        echo '</div>';
        
        // === Security Tests ===
        echo '<div class="section">';
        echo '<div class="section-title">🛡️ Security</div>';
        
        $total_tests++;
        $debug_disabled = IS_PRODUCTION ? !DEBUG_MODE : true;
        if (test_result('Debug mode', $debug_disabled, IS_PRODUCTION ? 'Disabled in production' : 'Enabled in development')) {
            $passed_tests++;
        }
        
        $total_tests++;
        $errors_hidden = IS_PRODUCTION ? !DISPLAY_ERRORS : true;
        if (test_result('Error display', $errors_hidden, IS_PRODUCTION ? 'Hidden in production' : 'Shown in development')) {
            $passed_tests++;
        }
        
        $total_tests++;
        $ssl_enabled = IS_PRODUCTION ? SSL_VERIFY : true;
        if (test_result('SSL verification', $ssl_enabled, IS_PRODUCTION ? 'Enabled' : 'Development mode')) {
            $passed_tests++;
        }
        
        echo '</div>';
        
        // === Summary ===
        $percentage = round(($passed_tests / $total_tests) * 100);
        $status = $percentage === 100 ? '🎉 All Tests Passed!' : ($percentage >= 80 ? '⚠️ Most Tests Passed' : '❌ Some Tests Failed');
        ?>
        
        <div class="summary">
            <h2><?php echo $status; ?></h2>
            <div class="stats">
                <?php echo $passed_tests; ?> / <?php echo $total_tests; ?> tests passed (<?php echo $percentage; ?>%)
            </div>
        </div>
        
        <?php if ($percentage < 100): ?>
        <div class="info-box">
            <strong>⚠️ Action Required:</strong> Some tests failed. Please review the errors above and:
            <ul style="margin: 10px 0 0 20px;">
                <li>Ensure all required files exist</li>
                <li>Check directory permissions (755 for directories)</li>
                <li>Verify PHP version (7.4+ recommended)</li>
                <li>Review PHASE1_IMPLEMENTATION.md for setup instructions</li>
            </ul>
        </div>
        <?php else: ?>
        <div class="info-box">
            <strong>✅ Success!</strong> Phase 1 implementation is complete and all systems are operational.
            <br><br>
            <strong>Next Steps:</strong>
            <ul style="margin: 10px 0 0 20px;">
                <li>Set APP_ENV=production for live deployment</li>
                <li>Test CSRF protection on login form</li>
                <li>Monitor cache performance in dashboard</li>
                <li>Review PHASE1_IMPLEMENTATION.md for usage guide</li>
            </ul>
        </div>
        <?php endif; ?>
        
        <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 6px; font-size: 12px; color: #666;">
            <strong>Environment:</strong> <?php echo APP_ENV; ?><br>
            <strong>PHP Version:</strong> <?php echo PHP_VERSION; ?><br>
            <strong>Timestamp:</strong> <?php echo date('Y-m-d H:i:s'); ?>
        </div>
    </div>
</body>
</html>
