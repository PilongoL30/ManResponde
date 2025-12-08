<?php
/**
 * Router Class
 * Handles URL routing and middleware for dashboard views
 */

class Router {
    private $routes = [];
    private $middleware = [];
    private $currentRoute = null;
    
    /**
     * Register a GET route
     */
    public function get(string $path, $handler, array $middleware = []): void {
        $this->addRoute('GET', $path, $handler, $middleware);
    }
    
    /**
     * Register a POST route
     */
    public function post(string $path, $handler, array $middleware = []): void {
        $this->addRoute('POST', $path, $handler, $middleware);
    }
    
    /**
     * Register a route for any method
     */
    public function any(string $path, $handler, array $middleware = []): void {
        $this->addRoute('*', $path, $handler, $middleware);
    }
    
    /**
     * Add a route to the routing table
     */
    private function addRoute(string $method, string $path, $handler, array $middleware = []): void {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
            'middleware' => $middleware
        ];
    }
    
    /**
     * Register global middleware
     */
    public function use($middleware): void {
        $this->middleware[] = $middleware;
    }
    
    /**
     * Dispatch the current request
     */
    public function dispatch(): mixed {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = $_GET['view'] ?? 'home';
        
        // Find matching route
        foreach ($this->routes as $route) {
            if ($this->matchRoute($route, $method, $path)) {
                $this->currentRoute = $route;
                return $this->executeRoute($route);
            }
        }
        
        // 404 - Route not found
        http_response_code(404);
        return $this->render404();
    }
    
    /**
     * Check if route matches current request
     */
    private function matchRoute(array $route, string $method, string $path): bool {
        // Check method
        if ($route['method'] !== '*' && $route['method'] !== $method) {
            return false;
        }
        
        // Check path (exact match for now, can be extended with regex)
        return $route['path'] === $path;
    }
    
    /**
     * Execute a matched route with middleware
     */
    private function executeRoute(array $route): mixed {
        // Execute global middleware
        foreach ($this->middleware as $middleware) {
            $result = $this->executeMiddleware($middleware);
            if ($result !== true) {
                return $result; // Middleware blocked the request
            }
        }
        
        // Execute route-specific middleware
        foreach ($route['middleware'] as $middleware) {
            $result = $this->executeMiddleware($middleware);
            if ($result !== true) {
                return $result;
            }
        }
        
        // Execute the route handler
        $handler = $route['handler'];
        
        if (is_callable($handler)) {
            return call_user_func($handler);
        }
        
        if (is_array($handler) && count($handler) === 2) {
            // Controller@method format
            [$controller, $method] = $handler;
            
            if (is_string($controller)) {
                $controller = new $controller();
            }
            
            if (method_exists($controller, $method)) {
                return call_user_func([$controller, $method]);
            }
        }
        
        throw new Exception("Invalid route handler");
    }
    
    /**
     * Execute middleware
     */
    private function executeMiddleware($middleware): mixed {
        if (is_callable($middleware)) {
            return call_user_func($middleware);
        }
        
        if (is_string($middleware) && class_exists($middleware)) {
            $instance = new $middleware();
            if (method_exists($instance, 'handle')) {
                return $instance->handle();
            }
        }
        
        return true;
    }
    
    /**
     * Render 404 page
     */
    private function render404(): string {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>404 - Page Not Found</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center">
        <div class="text-center">
            <h1 class="text-6xl font-bold text-gray-800 mb-4">404</h1>
            <p class="text-xl text-gray-600 mb-8">Page not found</p>
            <a href="dashboard.php" class="bg-blue-500 text-white px-6 py-3 rounded-lg hover:bg-blue-600">
                Go to Dashboard
            </a>
        </div>
    </div>
</body>
</html>
HTML;
    }
    
    /**
     * Get current route info
     */
    public function getCurrentRoute(): ?array {
        return $this->currentRoute;
    }
    
    /**
     * Generate URL for a route
     */
    public function url(string $view, array $params = []): string {
        $url = 'dashboard.php?view=' . urlencode($view);
        
        foreach ($params as $key => $value) {
            $url .= '&' . urlencode($key) . '=' . urlencode($value);
        }
        
        return $url;
    }
    
    /**
     * Redirect to a route
     */
    public function redirect(string $view, array $params = []): void {
        header('Location: ' . $this->url($view, $params));
        exit;
    }
}

/**
 * Authentication Middleware
 */
class AuthMiddleware {
    public function handle(): bool {
        if (!isset($_SESSION['user_uid'])) {
            header('Location: login.php');
            exit;
        }
        return true;
    }
}

/**
 * Admin Role Middleware
 */
class AdminMiddleware {
    public function handle(): bool {
        if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Admin access required']);
            exit;
        }
        return true;
    }
}

/**
 * CSRF Protection Middleware
 */
class CsrfMiddleware {
    public function handle(): bool {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!function_exists('csrf_verify_token')) {
                require_once __DIR__ . '/csrf.php';
            }
            
            if (!csrf_verify_token()) {
                http_response_code(403);
                echo json_encode(['error' => 'CSRF token validation failed']);
                exit;
            }
        }
        return true;
    }
}

/**
 * JSON Response Middleware
 */
class JsonMiddleware {
    public function handle(): bool {
        header('Content-Type: application/json');
        return true;
    }
}
