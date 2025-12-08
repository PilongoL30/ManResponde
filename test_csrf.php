<?php
/**
 * CSRF Fix Test
 * Quick test to verify CSRF protection is working correctly
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/csrf.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate a fresh token
$token = csrf_generate_token();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CSRF Test - ManResponde</title>
    <?php echo csrf_meta(); ?>
    <style>
        body {
            font-family: sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .test-box {
            background: white;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .success { border-left: 4px solid #28a745; }
        .error { border-left: 4px solid #dc3545; }
        .info { border-left: 4px solid #17a2b8; }
        button {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            margin: 5px;
        }
        button:hover { background: #0056b3; }
        pre {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
        }
        code {
            background: #e9ecef;
            padding: 2px 6px;
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <h1>🔒 CSRF Protection Test</h1>
    
    <div class="test-box info">
        <h3>Current CSRF Token:</h3>
        <code id="currentToken"><?php echo $token; ?></code>
        <p><small>Token is stored in session and meta tag</small></p>
    </div>

    <div class="test-box">
        <h3>Test 1: Automatic FormData CSRF Injection</h3>
        <p>This test verifies that FormData automatically includes CSRF token</p>
        <button onclick="testAutoCSRF()">Run Test</button>
        <div id="test1Result"></div>
    </div>

    <div class="test-box">
        <h3>Test 2: Manual CSRF Helper</h3>
        <p>Test the <code>createFormDataWithCsrf()</code> helper function</p>
        <button onclick="testManualCSRF()">Run Test</button>
        <div id="test2Result"></div>
    </div>

    <div class="test-box">
        <h3>Test 3: Missing CSRF Token</h3>
        <p>This should FAIL - testing that requests without CSRF are blocked</p>
        <button onclick="testNoCSRF()">Run Test (Should Fail)</button>
        <div id="test3Result"></div>
    </div>

    <div class="test-box">
        <h3>Test 4: Invalid CSRF Token</h3>
        <p>This should FAIL - testing that requests with invalid CSRF are blocked</p>
        <button onclick="testInvalidCSRF()">Run Test (Should Fail)</button>
        <div id="test4Result"></div>
    </div>

    <script>
        // CSRF Token Helpers (same as in dashboard)
        function getCsrfToken() {
            const meta = document.querySelector('meta[name="csrf-token"]');
            return meta ? meta.getAttribute('content') : '';
        }
        
        function createFormDataWithCsrf() {
            const formData = new FormData();
            formData.append('<?php echo CSRF_TOKEN_NAME; ?>', getCsrfToken());
            return formData;
        }
        
        // Auto-inject CSRF (same as dashboard)
        const originalFormData = window.FormData;
        window.FormData = function(form) {
            const formData = new originalFormData(form);
            const csrfToken = getCsrfToken();
            if (csrfToken && !formData.has('<?php echo CSRF_TOKEN_NAME; ?>')) {
                formData.append('<?php echo CSRF_TOKEN_NAME; ?>', csrfToken);
            }
            return formData;
        };

        // Test Functions
        async function testAutoCSRF() {
            const result = document.getElementById('test1Result');
            result.innerHTML = '<p>Testing...</p>';
            
            try {
                const formData = new FormData(); // Should auto-inject CSRF
                formData.append('api_action', 'test_action');
                formData.append('data', 'test');
                
                // Check if CSRF was added
                const hasCsrf = formData.has('<?php echo CSRF_TOKEN_NAME; ?>');
                const csrfValue = formData.get('<?php echo CSRF_TOKEN_NAME; ?>');
                
                if (hasCsrf && csrfValue) {
                    result.innerHTML = `
                        <div class="success">
                            <p>✅ SUCCESS: CSRF token automatically added!</p>
                            <pre>Token: ${csrfValue.substring(0, 20)}...</pre>
                        </div>
                    `;
                } else {
                    result.innerHTML = `
                        <div class="error">
                            <p>❌ FAILED: CSRF token was NOT added automatically</p>
                        </div>
                    `;
                }
            } catch (error) {
                result.innerHTML = `<div class="error"><p>❌ Error: ${error.message}</p></div>`;
            }
        }

        async function testManualCSRF() {
            const result = document.getElementById('test2Result');
            result.innerHTML = '<p>Testing...</p>';
            
            try {
                const formData = createFormDataWithCsrf();
                formData.append('api_action', 'test_action');
                
                const hasCsrf = formData.has('<?php echo CSRF_TOKEN_NAME; ?>');
                const csrfValue = formData.get('<?php echo CSRF_TOKEN_NAME; ?>');
                
                if (hasCsrf && csrfValue) {
                    result.innerHTML = `
                        <div class="success">
                            <p>✅ SUCCESS: createFormDataWithCsrf() works!</p>
                            <pre>Token: ${csrfValue.substring(0, 20)}...</pre>
                        </div>
                    `;
                } else {
                    result.innerHTML = `
                        <div class="error">
                            <p>❌ FAILED: Helper function didn't add CSRF</p>
                        </div>
                    `;
                }
            } catch (error) {
                result.innerHTML = `<div class="error"><p>❌ Error: ${error.message}</p></div>`;
            }
        }

        async function testNoCSRF() {
            const result = document.getElementById('test3Result');
            result.innerHTML = '<p>Testing...</p>';
            
            try {
                // Create FormData without CSRF (bypassing auto-injection)
                const formData = new originalFormData();
                formData.append('api_action', 'recent_feed');
                
                const response = await fetch('dashboard.php', {
                    method: 'POST',
                    body: formData
                });
                
                if (response.status === 403) {
                    result.innerHTML = `
                        <div class="success">
                            <p>✅ SUCCESS: Request blocked (HTTP 403) - CSRF protection working!</p>
                        </div>
                    `;
                } else {
                    const data = await response.json();
                    result.innerHTML = `
                        <div class="error">
                            <p>❌ FAILED: Request was allowed without CSRF token</p>
                            <pre>${JSON.stringify(data, null, 2)}</pre>
                        </div>
                    `;
                }
            } catch (error) {
                result.innerHTML = `<div class="error"><p>❌ Error: ${error.message}</p></div>`;
            }
        }

        async function testInvalidCSRF() {
            const result = document.getElementById('test4Result');
            result.innerHTML = '<p>Testing...</p>';
            
            try {
                const formData = new originalFormData();
                formData.append('<?php echo CSRF_TOKEN_NAME; ?>', 'invalid_token_12345');
                formData.append('api_action', 'recent_feed');
                
                const response = await fetch('dashboard.php', {
                    method: 'POST',
                    body: formData
                });
                
                if (response.status === 403) {
                    result.innerHTML = `
                        <div class="success">
                            <p>✅ SUCCESS: Invalid token blocked (HTTP 403)</p>
                        </div>
                    `;
                } else {
                    const data = await response.json();
                    result.innerHTML = `
                        <div class="error">
                            <p>❌ FAILED: Invalid token was accepted</p>
                            <pre>${JSON.stringify(data, null, 2)}</pre>
                        </div>
                    `;
                }
            } catch (error) {
                result.innerHTML = `<div class="error"><p>❌ Error: ${error.message}</p></div>`;
            }
        }

        // Update token display every second
        setInterval(() => {
            document.getElementById('currentToken').textContent = getCsrfToken();
        }, 1000);
    </script>

    <div class="test-box info" style="margin-top: 30px;">
        <h3>Quick Actions:</h3>
        <button onclick="window.location.href='dashboard.php'">Go to Dashboard</button>
        <button onclick="window.location.href='test_phase1.php'">Run Phase 1 Tests</button>
        <button onclick="location.reload()">Refresh Page</button>
    </div>

    <div class="test-box" style="font-size: 12px; color: #666; margin-top: 20px;">
        <p><strong>How to Fix if Tests Fail:</strong></p>
        <ol>
            <li>Ensure <code>config.php</code>, <code>includes/csrf.php</code> are loaded</li>
            <li>Check browser console for JavaScript errors</li>
            <li>Verify session is working (check <code>/sessions</code> directory)</li>
            <li>Clear browser cache and cookies</li>
            <li>Check <code>logs/error.log</code> for PHP errors</li>
        </ol>
    </div>
</body>
</html>
