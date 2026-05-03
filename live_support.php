<?php
ob_start();

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/includes/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $skipCsrf = ['firebase_webhook', 'external_api'];
    $action = $_POST['api_action'] ?? $_GET['action'] ?? '';

    if (!in_array($action, $skipCsrf, true)) {
        if (!csrf_verify_token()) {
            http_response_code(403);
            if (!empty($_POST['api_action']) || isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'CSRF token validation failed. Please refresh the page and try again.',
                    'action' => $action
                ]);
                exit;
            }
            die('CSRF token validation failed. Please go back and try again.');
        }
    }
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$userId   = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? 'staff';
$userName = $_SESSION['user_fullname'] ?? 'User';
$isAdmin  = ($userRole === 'admin');

$userCategories = $_SESSION['user_categories'] ?? [];
if (!is_array($userCategories)) $userCategories = [];

$userProfile = ['categories' => $userCategories, 'assignedBarangay' => ($_SESSION['assignedBarangay'] ?? '')];
if (empty($userCategories)) {
    $userProfile = get_user_profile($userId);
    $userCategories = $userProfile['categories'] ?? [];
    if (is_array($userCategories)) {
        $_SESSION['user_categories'] = $userCategories;
    }
}

$isTanod = false;
if (!empty($userCategories)) {
    foreach ($userCategories as $cat) {
        if (strtolower((string)$cat) === 'tanod') {
            $isTanod = true;
            break;
        }
    }
}

$view = 'live-support';

function get_user_profile(string $uid): array {
    if (session_status() !== PHP_SESSION_NONE) {
        $k = '__user_profile_' . $uid;
        $kt = $k . '_time';
        if (isset($_SESSION[$k], $_SESSION[$kt]) && (time() - (int)$_SESSION[$kt]) < 300) {
            return is_array($_SESSION[$k]) ? $_SESSION[$k] : [];
        }
    }

    $cacheKey = 'user_profile_' . $uid;
    $cached = cache_get($cacheKey, 300);
    if (is_array($cached)) {
        if (session_status() !== PHP_SESSION_NONE) {
            $_SESSION[$k] = $cached;
            $_SESSION[$kt] = time();
        }
        return $cached;
    }

    if (function_exists('firestore_get_doc_by_id')) {
        try {
            $data = firestore_get_doc_by_id('users', $uid) ?? [];
            if (is_array($data)) {
                cache_set($cacheKey, $data, 300);
                if (session_status() !== PHP_SESSION_NONE) {
                    $_SESSION[$k] = $data;
                    $_SESSION[$kt] = time();
                }
            }
            return $data;
        } catch (Throwable $e) {
        }
    }

    return [];
}

$categories = [
    'ambulance' => ['label' => 'Ambulance', 'collection' => 'ambulance_reports', 'icon' => 'truck', 'color' => 'blue'],
    'police'    => ['label' => 'Police',    'collection' => 'police_reports',    'icon' => 'user-shield', 'color' => 'slate'],
    'tanod'     => ['label' => 'Tanod',     'collection' => 'tanod_reports',     'icon' => 'shield-check', 'color' => 'sky'],
    'fire'      => ['label' => 'Fire',      'collection' => 'fire_reports',      'icon' => 'fire', 'color' => 'red'],
    'flood'     => ['label' => 'Flood',     'collection' => 'flood_reports',     'icon' => 'home', 'color' => 'indigo'],
    'other'     => ['label' => 'Other',     'collection' => 'other_reports',     'icon' => 'question-mark-circle', 'color' => 'gray'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ManResponde • Live Support</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php echo csrf_meta(); ?>

    <link rel="icon" type="image/png" sizes="32x32" href="responde.png">
    <link rel="icon" type="image/png" sizes="16x16" href="responde.png">
    <link rel="apple-touch-icon" href="responde.png">
    <link rel="shortcut icon" href="responde.png">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/custom.css?v=<?php echo filemtime(__DIR__ . '/assets/css/custom.css'); ?>">
    <script src="assets/js/common-modals.js?v=<?php echo filemtime(__DIR__ . '/assets/js/common-modals.js'); ?>"></script>
</head>
<body class="antialiased">
    <div class="flex h-screen bg-slate-100">
        <aside class="hidden md:flex w-64 flex-shrink-0 bg-white text-slate-600 flex-col p-4 border-r border-slate-200">
            <div class="h-16 flex items-center justify-center px-2">
                <img src="responde.png" alt="ManResponde Logo" class="h-10 w-auto object-contain sm:h-12 md:h-14 lg:h-10" onerror="this.style.display='none'">
            </div>
            <nav class="flex-1 px-2 py-4 space-y-1.5">
                <?php $isDashActive = ($view === 'dashboard'); ?>
                <a href="dashboard.php?view=dashboard" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?php echo $isDashActive ? 'bg-sky-100 text-sky-700 font-semibold' : 'hover:bg-slate-50'; ?>">
                    <?php echo svg_icon('dashboard', 'w-5 h-5'); ?>
                    <span>Dashboard</span>
                </a>

                <?php $isAnalyticsActive = ($view === 'analytics'); ?>
                <a href="analytics.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?php echo $isAnalyticsActive ? 'bg-sky-100 text-sky-700 font-semibold' : 'hover:bg-slate-50 text-slate-600'; ?>">
                    <?php echo svg_icon('chart-pie', 'w-5 h-5'); ?>
                    <span>Analytics</span>
                </a>

                <?php $isMapActive = ($view === 'map'); ?>
                <a href="interactive_map.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?php echo $isMapActive ? 'bg-sky-100 text-sky-700 font-semibold' : 'hover:bg-slate-50 text-slate-600'; ?>">
                    <?php echo svg_icon('map', 'w-5 h-5'); ?>
                    <span>Interactive Map</span>
                </a>

                <?php $isLiveSupportActive = ($view === 'live-support'); ?>
                <a href="live_support.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?php echo $isLiveSupportActive ? 'bg-sky-100 text-sky-700 font-semibold' : 'hover:bg-slate-50'; ?>">
                    <?php echo svg_icon('chat', 'w-5 h-5'); ?>
                    <span>Live Support</span>
                    <span id="liveSupportBadge" class="ml-auto bg-red-500 text-white text-xs font-bold px-2 py-0.5 rounded-full hidden">0</span>
                </a>

                <?php if ($isAdmin): ?>
                <?php $isCreateAccountActive = ($view === 'create-account'); ?>
                <a href="create_account.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?php echo $isCreateAccountActive ? 'bg-sky-100 text-sky-700 font-semibold' : 'hover:bg-slate-50 text-slate-600'; ?>">
                    <?php echo svg_icon('user-plus', 'w-5 h-5'); ?>
                    <span>Create Account</span>
                </a>
                <?php endif; ?>

                <?php if ($isAdmin || $isTanod): ?>
                <?php $isVerifyUsersActive = ($view === 'verify-users'); ?>
                <a href="verify_users.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?php echo $isVerifyUsersActive ? 'bg-sky-100 text-sky-700 font-semibold' : 'hover:bg-slate-50 text-slate-600'; ?>">
                    <?php echo svg_icon('user-check', 'w-5 h-5'); ?>
                    <span>Verify Users</span>
                    <span id="verifyUsersBadge" class="ml-auto bg-amber-500 text-white text-xs font-bold px-2 py-0.5 rounded-full hidden">0</span>
                </a>
                <?php endif; ?>

                <?php if ($isAdmin): ?>
                <button onclick="showExportModal()" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-50 text-slate-600 w-full text-left">
                    <?php echo svg_icon('download', 'w-5 h-5'); ?>
                    <span>Export Reports</span>
                </button>
                <?php endif; ?>
            </nav>
            <div class="p-2 border-t border-slate-200/70 pt-4">
                 <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-full bg-sky-500 flex items-center justify-center font-bold text-white ring-2 ring-sky-200">
                        <?php echo strtoupper(substr($userName, 0, 1)); ?>
                    </div>
                    <div>
                        <p class="font-semibold text-slate-800 text-sm"><?php echo htmlspecialchars($userName); ?></p>
                        <p class="text-xs text-slate-500"><?php echo htmlspecialchars(ucfirst($userRole)); ?></p>
                    </div>
                </div>
                <a href="logout.php" class="flex items-center justify-center gap-2 rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200 w-full px-4 py-2.5 text-sm font-semibold">
                    <?php echo svg_icon('logout', 'w-5 h-5'); ?>
                    <span>Logout</span>
                </a>
            </div>
        </aside>

        <div class="md:hidden fixed top-0 left-0 right-0 z-50 bg-white border-b border-slate-200 px-4 py-3">
            <div class="flex items-center justify-between">
                <button id="mobileMenuBtn" class="p-2 rounded-lg text-slate-600 hover:bg-slate-100">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
                <div class="flex items-center justify-center flex-1">
                    <img src="responde.png" alt="ManResponde Logo" class="h-8 w-auto object-contain" onerror="this.style.display='none'">
                </div>
                <div class="w-10"></div>
            </div>
        </div>

        <div id="mobileMenuOverlay" class="md:hidden fixed inset-0 z-40 bg-black bg-opacity-50 hidden">
            <div class="fixed inset-y-0 left-0 w-64 bg-white shadow-xl">
                <div class="flex flex-col h-full">
                    <div class="h-16 flex items-center justify-center px-4 border-b border-slate-200">
                        <img src="responde.png" alt="ManResponde Logo" class="h-10 w-auto object-contain" onerror="this.style.display='none'">
                    </div>
                    <nav class="flex-1 px-4 py-4 space-y-1.5 overflow-y-auto">
                        <?php $isDashActive = ($view === 'dashboard'); ?>
                        <a href="dashboard.php?view=dashboard" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?php echo $isDashActive ? 'bg-sky-100 text-sky-700 font-semibold' : 'hover:bg-slate-50 text-slate-600'; ?>">
                            <?php echo svg_icon('dashboard', 'w-5 h-5'); ?>
                            <span>Dashboard</span>
                        </a>

                        <?php $isAnalyticsActive = ($view === 'analytics'); ?>
                        <a href="analytics.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?php echo $isAnalyticsActive ? 'bg-sky-100 text-sky-700 font-semibold' : 'hover:bg-slate-50 text-slate-600'; ?>">
                            <?php echo svg_icon('chart-pie', 'w-5 h-5'); ?>
                            <span>Analytics</span>
                        </a>

                        <?php $isMapActive = ($view === 'map'); ?>
                        <a href="interactive_map.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?php echo $isMapActive ? 'bg-sky-100 text-sky-700 font-semibold' : 'hover:bg-slate-50 text-slate-600'; ?>">
                            <?php echo svg_icon('map', 'w-5 h-5'); ?>
                            <span>Interactive Map</span>
                        </a>

                        <?php $isLiveSupportActive = ($view === 'live-support'); ?>
                        <a href="live_support.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?php echo $isLiveSupportActive ? 'bg-sky-100 text-sky-700 font-semibold' : 'hover:bg-slate-50 text-slate-600'; ?>">
                            <?php echo svg_icon('chat', 'w-5 h-5'); ?>
                            <span>Live Support</span>
                            <span id="liveSupportBadgeMobile" class="ml-auto bg-red-500 text-white text-xs font-bold px-2 py-0.5 rounded-full hidden">0</span>
                        </a>

                        <?php if ($isAdmin): ?>
                            <?php $isCreateAccountActive = ($view === 'create-account'); ?>
                            <a href="create_account.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?php echo $isCreateAccountActive ? 'bg-sky-100 text-sky-700 font-semibold' : 'hover:bg-slate-50 text-slate-600'; ?>">
                                <?php echo svg_icon('user-plus', 'w-5 h-5'); ?>
                                <span>Create Account</span>
                            </a>
                        <?php endif; ?>

                        <?php if ($isAdmin || $isTanod): ?>
                            <?php $isVerifyUsersActive = ($view === 'verify-users'); ?>
                            <a href="verify_users.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?php echo $isVerifyUsersActive ? 'bg-sky-100 text-sky-700 font-semibold' : 'hover:bg-slate-50 text-slate-600'; ?>">
                                <?php echo svg_icon('user-check', 'w-5 h-5'); ?>
                                <span>Verify Users</span>
                                <span id="verifyUsersBadgeMobile" class="ml-auto bg-amber-500 text-white text-xs font-bold px-2 py-0.5 rounded-full hidden">0</span>
                            </a>
                        <?php endif; ?>

                        <?php if ($isAdmin): ?>
                            <button onclick="showExportModal(); closeMobileSidebar();" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-50 text-slate-600 w-full text-left">
                                <?php echo svg_icon('download', 'w-5 h-5'); ?>
                                <span>Export Reports</span>
                            </button>
                        <?php endif; ?>

                        <div class="border-t border-slate-200 pt-4 mt-4">
                            <a href="logout.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-50">
                                <?php echo svg_icon('logout', 'w-5 h-5'); ?>
                                <span>Logout</span>
                            </a>
                        </div>
                    </nav>
                </div>
            </div>
        </div>

        <main class="flex-1 overflow-y-auto aurora-background">
            <div class="pt-20 md:pt-6 pb-6 px-4 sm:px-6 md:px-8 lg:px-10 animate-fade-in relative z-10">
                <header class="mb-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-3xl md:text-4xl font-extrabold text-slate-900 tracking-tighter">Live Support</h1>
                            <p class="text-slate-500 mt-1 text-base md:text-lg">Connect with residents in real-time.</p>
                        </div>
                    </div>
                </header>

                <?php include __DIR__ . '/views/live_support.php'; ?>
            </div>
        </main>
    </div>

    <?php if ($isAdmin) include __DIR__ . '/includes/modals_dashboard.php'; ?>

    <script>
        window.dashboardConfig = {
            isAdmin: <?php echo $isAdmin ? 'true' : 'false'; ?>,
            userRole: '<?php echo htmlspecialchars($userRole); ?>',
            userId: '<?php echo htmlspecialchars((string)$userId); ?>',
            view: '<?php echo htmlspecialchars($view); ?>',
            userCategories: <?php echo json_encode(array_values($userCategories ?? [])); ?>,
            userBarangay: <?php echo json_encode((string)($userProfile['assignedBarangay'] ?? ($_SESSION['assignedBarangay'] ?? ''))); ?>
        };

        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const mobileMenuOverlay = document.getElementById('mobileMenuOverlay');

            if (mobileMenuBtn && mobileMenuOverlay) {
                mobileMenuBtn.addEventListener('click', function() {
                    mobileMenuOverlay.classList.toggle('hidden');
                });

                mobileMenuOverlay.addEventListener('click', function(e) {
                    if (e.target === mobileMenuOverlay) {
                        mobileMenuOverlay.classList.add('hidden');
                    }
                });

                const mobileNavLinks = mobileMenuOverlay.querySelectorAll('a');
                mobileNavLinks.forEach(link => {
                    link.addEventListener('click', function() {
                        mobileMenuOverlay.classList.add('hidden');
                    });
                });

                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && !mobileMenuOverlay.classList.contains('hidden')) {
                        mobileMenuOverlay.classList.add('hidden');
                    }
                });
            }

            window.closeMobileSidebar = function() {
                const mobileMenuOverlay = document.getElementById('mobileMenuOverlay');
                if (mobileMenuOverlay) {
                    mobileMenuOverlay.classList.add('hidden');
                }
            };
        });
    </script>
    <script src="assets/js/common-modals.js?v=<?php echo filemtime(__DIR__ . '/assets/js/common-modals.js'); ?>"></script>
    <script src="assets/js/chat-system.js?v=<?php echo filemtime(__DIR__ . '/assets/js/chat-system.js'); ?>"></script>
    <?php if ($isAdmin) include __DIR__ . '/includes/modals_dashboard.php'; ?>
</body>
</html>
