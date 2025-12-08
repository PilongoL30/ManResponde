<?php
/**
 * ManResponde Dashboard - Refactored Architecture
 * 
 * This is the main entry point for the dashboard application.
 * Uses MVC architecture with services, controllers, routing, and views.
 * 
 * Phase 2 Implementation:
 * - Service Layer: Business logic (ReportService, UserService, StatisticsService, NotificationService)
 * - Controller Layer: Request handling (DashboardController, ReportController, UserController)
 * - View Layer: Template rendering (View helper, view files in views/)
 * - Router: URL routing and middleware (Router class with AuthMiddleware, CsrfMiddleware)
 */

// ============================================================================
// BOOTSTRAP & CONFIGURATION
// ============================================================================

// Configure session BEFORE starting it
$sessionPath = __DIR__ . '/sessions';
if (!is_dir($sessionPath)) {
    mkdir($sessionPath, 0755, true);
}
session_save_path($sessionPath);
ini_set('session.gc_probability', 1);
ini_set('session.gc_divisor', 100);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';
require_once 'db_config.php';
require_once 'includes/csrf.php';
require_once 'includes/cache.php';
require_once 'includes/helpers.php';
require_once 'includes/Router.php';
require_once 'includes/View.php';

// ============================================================================
// AUTHENTICATION CHECK
// ============================================================================

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// ============================================================================
// USER SESSION DATA
// ============================================================================

$userUid = $_SESSION['user_id'];
$userEmail = $_SESSION['user_email'] ?? '';
$userDisplayName = $_SESSION['user_fullname'] ?? 'User';
$userName = $userDisplayName; // Alias for includes compatibility
$userRole = $_SESSION['user_role'] ?? 'staff';
$isAdmin = ($userRole === 'admin');

// Fetch user profile to get categories (needed for staff access and isTanod check)
function get_user_profile_v2($uid) {
    try {
        $firestore = initialize_firestore();
        $snap = $firestore->collection('users')->document($uid)->snapshot();
        return $snap->exists() ? ($snap->data() ?? []) : [];
    } catch (Throwable $e) {
        error_log('Error fetching user profile: ' . $e->getMessage());
        return [];
    }
}

$userProfile = get_user_profile_v2($userUid);
$userCategories = $userProfile['categories'] ?? [];

// Check if user has 'tanod' category (case-insensitive)
$isTanod = false;
if (!empty($userCategories)) {
    foreach ($userCategories as $cat) {
        if (strtolower($cat) === 'tanod') {
            $isTanod = true;
            break;
        }
    }
}

// ============================================================================
// CATEGORY DEFINITIONS
// ============================================================================

$categories = [
    'tanod' => [
        'collection' => 'tanod_reports',
        'label' => 'Barangay Tanod',
        'icon' => 'shield-halved',
        'color' => 'blue',
        'canVerify' => true
    ],
    'fire' => [
        'collection' => 'fire_reports',
        'label' => 'Fire Emergency',
        'icon' => 'fire-flame-curved',
        'color' => 'red',
        'canVerify' => false
    ],
    'medical' => [
        'collection' => 'medical_reports',
        'label' => 'Medical Emergency',
        'icon' => 'kit-medical',
        'color' => 'green',
        'canVerify' => false
    ],
    'traffic' => [
        'collection' => 'traffic_reports',
        'label' => 'Traffic Incident',
        'icon' => 'car-burst',
        'color' => 'yellow',
        'canVerify' => false
    ],
    'disaster' => [
        'collection' => 'disaster_reports',
        'label' => 'Natural Disaster',
        'icon' => 'house-tsunami',
        'color' => 'purple',
        'canVerify' => false
    ],
    'crime' => [
        'collection' => 'crime_reports',
        'label' => 'Crime Incident',
        'icon' => 'handcuffs',
        'color' => 'orange',
        'canVerify' => false
    ]
];

// ============================================================================
// CSRF PROTECTION
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify_token()) {
        http_response_code(403);
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'CSRF token validation failed']);
        } else {
            echo 'CSRF token validation failed';
        }
        exit;
    }
}

// ============================================================================
// ROUTING & CONTROLLER DISPATCH
// ============================================================================

// Handle AJAX actions via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['api_action'])) {
    $action = $_POST['api_action'];
    
    // Staff: Load assigned reports data
    if (!$isAdmin && $action === 'load_staff_data') {
        header('Content-Type: application/json');
        $startTime = microtime(true);
        
        try {
            $cards = [];
            foreach ($userCategories as $slug) {
                if (!isset($categories[$slug])) continue;
                $reports = list_latest_reports($categories[$slug]['collection'], 50);
                
                // Sort reports by timestamp (newest first)
                usort($reports, function($a, $b) {
                    $timeA = $a['timestamp'] ?? '';
                    $timeB = $b['timestamp'] ?? '';
                    
                    if (is_array($timeA) && isset($timeA['_seconds'])) {
                        $secondsA = $timeA['_seconds'];
                    } elseif (is_string($timeA)) {
                        $secondsA = strtotime($timeA);
                    } else {
                        $secondsA = 0;
                    }
                    
                    if (is_array($timeB) && isset($timeB['_seconds'])) {
                        $secondsB = $timeB['_seconds'];
                    } elseif (is_string($timeB)) {
                        $secondsB = strtotime($timeB);
                    } else {
                        $secondsB = 0;
                    }
                    
                    return $secondsB - $secondsA;
                });
                
                $cards[$slug] = $reports;
            }
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'profile' => $userProfile,
                    'assigned' => $userCategories,
                    'cards' => $cards,
                ],
                'executionTime' => round((microtime(true) - $startTime) * 1000, 2) . 'ms'
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to load staff data: ' . $e->getMessage(),
                'retry' => true,
                'executionTime' => round((microtime(true) - $startTime) * 1000, 2) . 'ms'
            ]);
        }
        exit();
    }
}

// Handle AJAX actions via POST (original controller actions)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    // Import controllers
    require_once 'controllers/ReportController.php';
    require_once 'controllers/UserController.php';
    
    try {
        switch ($action) {
            // Report actions
            case 'get_reports_by_category':
                $controller = new ReportController($categories);
                $controller->getByCategory();
                break;
                
            case 'update_report_status':
                $controller = new ReportController($categories);
                $controller->updateStatus();
                break;
                
            case 'get_report':
                $controller = new ReportController($categories);
                $controller->getReportById();
                break;
                
            case 'search_reports':
                $controller = new ReportController($categories);
                $controller->search();
                break;
                
            // User actions (admin only)
            case 'verify_user':
                if (!$isAdmin) {
                    View::json(['success' => false, 'error' => 'Admin access required'], 403);
                }
                $controller = new UserController();
                $controller->verifyUser();
                break;
                
            case 'delete_user':
                if (!$isAdmin) {
                    View::json(['success' => false, 'error' => 'Admin access required'], 403);
                }
                $controller = new UserController();
                $controller->deleteUser();
                break;
                
            case 'update_user':
                if (!$isAdmin) {
                    View::json(['success' => false, 'error' => 'Admin access required'], 403);
                }
                $controller = new UserController();
                $controller->updateProfile();
                break;
                
            default:
                View::json(['success' => false, 'error' => 'Invalid action'], 400);
        }
    } catch (Exception $e) {
        View::json([
            'success' => false,
            'error' => DEBUG_MODE ? $e->getMessage() : 'An error occurred'
        ], 500);
    }
    
    exit;
}

// Handle AJAX actions via GET
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    require_once 'controllers/ReportController.php';
    require_once 'controllers/UserController.php';
    require_once 'controllers/DashboardController.php';
    
    try {
        switch ($action) {
            case 'get_recent_feed':
                $controller = new ReportController($categories);
                $controller->getRecentFeed();
                break;
                
            case 'get_report_stats':
                $controller = new ReportController($categories);
                $controller->getStats();
                break;
                
            case 'get_pending_users':
                if (!$isAdmin) {
                    View::json(['success' => false, 'error' => 'Admin access required'], 403);
                }
                $controller = new UserController();
                $controller->getPendingUsers();
                break;
                
            case 'get_verified_users':
                if (!$isAdmin) {
                    View::json(['success' => false, 'error' => 'Admin access required'], 403);
                }
                $controller = new UserController();
                $controller->getVerifiedUsers();
                break;
                
            case 'get_all_users':
                if (!$isAdmin) {
                    View::json(['success' => false, 'error' => 'Admin access required'], 403);
                }
                $controller = new UserController();
                $controller->getAllUsers();
                break;
                
            case 'get_user_stats':
                $controller = new UserController();
                $controller->getStats();
                break;
                
            case 'get_pending_count':
                $controller = new DashboardController($categories, $userRole, $userCategories);
                $controller->getPendingCount();
                break;
                
            case 'cache_stats':
                $controller = new DashboardController($categories, $userRole, $userCategories);
                $controller->getCacheStats();
                break;
                
            default:
                View::json(['success' => false, 'error' => 'Invalid action'], 400);
        }
    } catch (Exception $e) {
        View::json([
            'success' => false,
            'error' => DEBUG_MODE ? $e->getMessage() : 'An error occurred'
        ], 500);
    }
    
    exit;
}

// ============================================================================
// VIEW ROUTING
// ============================================================================

$view = $_GET['view'] ?? 'dashboard';

// Determine allowed views based on role
$allowedViews = ['dashboard', 'live-support', 'map', 'analytics'];
if ($isAdmin) {
    $allowedViews[] = 'create-account';
    $allowedViews[] = 'verify-users';
} elseif ($isTanod) {
    $allowedViews[] = 'verify-users';
}

// Validate view
if (!in_array($view, $allowedViews)) {
    $view = 'dashboard';
}

// Validate admin-only views
$adminOnlyViews = ['create-account', 'verify-users'];
if (in_array($view, $adminOnlyViews) && !$isAdmin) {
    $view = 'dashboard';
}

// Share global view data
View::share('categories', $categories);
View::share('userRole', $userRole);
View::share('userCategories', $userCategories);
View::share('isAdmin', $isAdmin);
View::share('isTanod', $isTanod);
View::share('userDisplayName', $userDisplayName);
View::share('userName', $userName);
View::share('userEmail', $userEmail);
View::share('userUid', $userUid);
View::share('view', $view);

require_once 'controllers/DashboardController.php';

?>
<!DOCTYPE html>
<html lang="en">
<?php include 'includes/header.php'; ?>
<body class="premium-gradient font-inter antialiased">
    
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="lg:ml-72">
        <?php include 'includes/topbar.php'; ?>
        
        <main class="p-4 sm:p-6 lg:p-8 pt-20 lg:pt-24 min-h-screen">
            <div class="max-w-[1600px] mx-auto">
                
                <?php
                // Controller-based view rendering
                $controller = new DashboardController($categories, $userRole, $userCategories);
                
                switch ($view) {
                    case 'analytics':
                        $controller->analytics();
                        break;
                        
                    case 'map':
                        $controller->map();
                        break;
                        
                    case 'live-support':
                        $controller->liveSupport();
                        break;
                        
                    case 'create-account':
                        $controller->createAccount();
                        break;
                        
                    case 'verify-users':
                        $controller->verifyUsers();
                        break;
                        
                    case 'dashboard':
                    default:
                        $controller->index();
                        break;
                }
                ?>
                
            </div>
        </main>
    </div>
    
    <?php include 'includes/modals_dashboard.php'; ?>
    <?php include 'includes/scripts.php'; ?>
    
    <script>
    // CSRF Token Management
    const csrfToken = '<?php echo csrf_generate_token(); ?>';
    
    function getCsrfToken() {
        return csrfToken;
    }
    
    function createFormDataWithCsrf() {
        const formData = new FormData();
        formData.append('csrf_token', csrfToken);
        return formData;
    }
    
    // Auto-inject CSRF token into all FormData instances
    const OriginalFormData = window.FormData;
    window.FormData = function() {
        const formData = new OriginalFormData(...arguments);
        if (!formData.has('csrf_token')) {
            formData.append('csrf_token', csrfToken);
        }
        return formData;
    };
    
    // Staff: Load reports data if on staff dashboard
    <?php if (!$isAdmin && $view === 'dashboard'): ?>
    (async () => {
        const cardsContainer = document.getElementById('staffReportCards');
        if (!cardsContainer) return;
    
        try {
            const formData = new FormData();
            formData.append('api_action', 'load_staff_data');
            
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Simple render for now - you can enhance this later
                let html = '';
                for (const [slug, reports] of Object.entries(result.data.cards)) {
                    const meta = window.categories[slug];
                    if (!meta) continue;
                    
                    html += `
                        <div class="glass-card p-6">
                            <h3 class="text-xl font-bold mb-4">${meta.label}</h3>
                            <div class="space-y-3">
                    `;
                    
                    const pending = reports.filter(r => (r.status || 'pending').toLowerCase() === 'pending');
                    if (pending.length > 0) {
                        pending.slice(0, 10).forEach(report => {
                            const name = report.reporterName || 'Anonymous';
                            const location = report.location || 'Unknown location';
                            html += `
                                <div class="bg-white rounded-lg p-4 border border-gray-200 hover:shadow-md transition-shadow">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <p class="font-semibold text-gray-800">${name}</p>
                                            <p class="text-sm text-gray-600">${location}</p>
                                        </div>
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                            Pending
                                        </span>
                                    </div>
                                </div>
                            `;
                        });
                    } else {
                        html += '<p class="text-gray-500 text-center py-4">No pending reports</p>';
                    }
                    
                    html += '</div></div>';
                }
                
                if (html) {
                    cardsContainer.innerHTML = html;
                } else {
                    cardsContainer.innerHTML = '<div class="text-center py-12 text-gray-500">No assigned categories or reports available.</div>';
                }
            } else {
                console.error('Failed to load staff data:', result.message);
                cardsContainer.innerHTML = `
                    <div class="text-center py-12 text-red-500">
                        <p class="text-lg font-medium">Failed to load reports</p>
                        <p class="text-sm">${result.message || 'Unknown error'}</p>
                    </div>
                `;
            }
        } catch (error) {
            console.error('Error loading staff data:', error);
            cardsContainer.innerHTML = `
                <div class="text-center py-12 text-red-500">
                    <p class="text-lg font-medium">Error loading reports</p>
                    <p class="text-sm">${error.message}</p>
                </div>
            `;
        }
    })();
    <?php endif; ?>
    </script>
    
</body>
</html>
