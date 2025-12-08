<?php
/**
 * View Helper Class
 * Provides template rendering and view utilities
 */

class View {
    private static $layoutPath = __DIR__ . '/../views/layouts/';
    private static $viewPath = __DIR__ . '/../views/';
    private static $data = [];
    
    /**
     * Render a view file with data
     */
    public static function render(string $view, array $data = []): string {
        $viewFile = self::$viewPath . $view . '.php';
        
        if (!file_exists($viewFile)) {
            throw new Exception("View file not found: {$viewFile}");
        }
        
        // Extract data to variables
        extract(array_merge(self::$data, $data));
        
        // Start output buffering
        ob_start();
        
        // Include the view file
        include $viewFile;
        
        // Return the buffered content
        return ob_get_clean();
    }
    
    /**
     * Render a view within a layout
     */
    public static function renderWithLayout(string $view, string $layout = 'dashboard', array $data = []): string {
        $layoutFile = self::$layoutPath . $layout . '.php';
        
        if (!file_exists($layoutFile)) {
            throw new Exception("Layout file not found: {$layoutFile}");
        }
        
        // Render the view content first
        $content = self::render($view, $data);
        
        // Extract data and content for layout
        extract(array_merge(self::$data, $data, ['content' => $content]));
        
        // Start output buffering
        ob_start();
        
        // Include the layout file
        include $layoutFile;
        
        // Return the buffered content
        return ob_get_clean();
    }
    
    /**
     * Set global data available to all views
     */
    public static function share(string $key, $value): void {
        self::$data[$key] = $value;
    }
    
    /**
     * Include a partial view
     */
    public static function partial(string $partial, array $data = []): void {
        extract($data);
        include self::$viewPath . 'partials/' . $partial . '.php';
    }
    
    /**
     * Escape HTML output
     */
    public static function e($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Generate URL for asset
     */
    public static function asset(string $path): string {
        return 'assets/' . ltrim($path, '/');
    }
    
    /**
     * Check if current view matches
     */
    public static function isActive(string $view): bool {
        $currentView = $_GET['view'] ?? 'dashboard';
        return $currentView === $view;
    }
    
    /**
     * Generate dashboard URL
     */
    public static function url(string $view, array $params = []): string {
        $url = 'dashboard.php?view=' . urlencode($view);
        
        foreach ($params as $key => $value) {
            $url .= '&' . urlencode($key) . '=' . urlencode($value);
        }
        
        return $url;
    }
    
    /**
     * Render JSON response
     */
    public static function json($data, int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
    
    /**
     * Render error page
     */
    public static function error(int $code = 500, string $message = 'An error occurred'): void {
        http_response_code($code);
        echo self::render('errors/' . $code, ['message' => $message]);
        exit;
    }
}

/**
 * Template Helper Functions
 */

/**
 * Render a view
 */
function view(string $view, array $data = []): string {
    return View::render($view, $data);
}

/**
 * Escape and echo value
 */
function e($value): void {
    echo View::e($value);
}

/**
 * Include a partial
 */
function partial(string $partial, array $data = []): void {
    View::partial($partial, $data);
}

/**
 * Generate asset URL
 */
function asset(string $path): string {
    return View::asset($path);
}

/**
 * Check if view is active
 */
function is_active(string $view): bool {
    return View::isActive($view);
}

/**
 * Generate dashboard URL
 */
function dashboard_url(string $view, array $params = []): string {
    return View::url($view, $params);
}
