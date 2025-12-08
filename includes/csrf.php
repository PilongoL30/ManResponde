<?php
/**
 * CSRF Protection System
 * Protects forms and AJAX requests from Cross-Site Request Forgery attacks
 */

/**
 * Generate a new CSRF token
 */
function csrf_generate_token(): string {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $token = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
    $_SESSION[CSRF_TOKEN_NAME] = $token;
    $_SESSION[CSRF_TOKEN_NAME . '_time'] = time();
    
    return $token;
}

/**
 * Get the current CSRF token (generate if not exists)
 */
function csrf_get_token(): string {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION[CSRF_TOKEN_NAME]) || !csrf_is_token_valid_time()) {
        return csrf_generate_token();
    }
    
    return $_SESSION[CSRF_TOKEN_NAME];
}

/**
 * Check if token is still within valid time window (1 hour)
 */
function csrf_is_token_valid_time(): bool {
    if (!isset($_SESSION[CSRF_TOKEN_NAME . '_time'])) {
        return false;
    }
    
    $tokenAge = time() - $_SESSION[CSRF_TOKEN_NAME . '_time'];
    return $tokenAge < 3600; // 1 hour
}

/**
 * Verify CSRF token from request
 */
function csrf_verify_token(?string $token = null): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Get token from parameter or from POST/header
    if ($token === null) {
        $token = $_POST[CSRF_TOKEN_NAME] ?? 
                 $_SERVER['HTTP_X_CSRF_TOKEN'] ?? 
                 null;
    }
    
    if (!$token) {
        return false;
    }
    
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        return false;
    }
    
    // Check token age
    if (!csrf_is_token_valid_time()) {
        return false;
    }
    
    // Timing-safe comparison
    return hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

/**
 * Generate hidden input field for forms
 */
function csrf_field(): string {
    $token = csrf_get_token();
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Get token as meta tag for AJAX requests
 */
function csrf_meta(): string {
    $token = csrf_get_token();
    return '<meta name="csrf-token" content="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Middleware to protect POST requests
 */
function csrf_protect_request(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrf_verify_token()) {
            http_response_code(403);
            die(json_encode([
                'success' => false,
                'error' => 'CSRF token validation failed. Please refresh the page and try again.'
            ]));
        }
    }
}
