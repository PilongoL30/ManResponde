<?php
// verify_users.php
// Standalone page for real-time user verification

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

ob_start(); // Start output buffering

// Include required configuration files
require_once __DIR__ . '/db_config.php';
session_start();
require_once __DIR__ . '/fcm_config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$userRole = $_SESSION['user_role'] ?? 'staff';
$isAdmin = ($userRole === 'admin');
$assignedBarangay = $_SESSION['assignedBarangay'] ?? '';

// Fetch user profile to check categories
$userProfile = firestore_get_doc_by_id('users', $_SESSION['user_id']);
$categories = $userProfile['categories'] ?? [];
// Handle case where categories might be a string or array
if (is_string($categories)) {
    $categories = [$categories];
} elseif (!is_array($categories)) {
    $categories = [];
}
// Convert all categories to lowercase for case-insensitive comparison
$categories = array_map('strtolower', $categories);
$isTanod = in_array('tanod', $categories);

// Allow admin or tanod to access
if (!$isAdmin && !$isTanod) {
    header('Location: dashboard.php');
    exit();
}

// Handle AJAX requests for sending notifications
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'send_approval_notification') {
        $userId = $_POST['userId'] ?? '';
        
        if (empty($userId)) {
            echo json_encode(['success' => false, 'error' => 'User ID is required']);
            exit;
        }
        
        $success = sendApprovalNotification($userId);
        echo json_encode(['success' => $success]);
        exit;
    }
}

/**
 * Send approval notification to user
 */
function sendApprovalNotification($userId) {
    try {
        $title = "Account Approved! 🎉";
        $body = "Congratulations! Your ManResponde account has been approved. You can now access all features.";
        $data = [
            'type' => 'account_approved',
            'userId' => $userId,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // Send FCM notification
        $success = send_fcm_notification_to_user($userId, $title, $body, $data);
        
        if ($success) {
            error_log("✅ Approval notification sent successfully to user: $userId");
            return true;
        } else {
            error_log("❌ Failed to send approval notification to user: $userId");
            return false;
        }
    } catch (Exception $e) {
        error_log("❌ Error sending approval notification: " . $e->getMessage());
        return false;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>User Registration Verification</title>
  <link rel="icon" href="responde.png" type="image/png">
  <!-- Tailwind CSS for styling -->
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    /* Custom animations and styles */
    @keyframes fade-in-up {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    @keyframes shimmer {
      0% { background-position: -1000px 0; }
      100% { background-position: 1000px 0; }
    }
    
    .animate-fade-in-up {
      animation: fade-in-up 0.6s ease-out forwards;
      animation-delay: var(--anim-delay, 0ms);
    }
    
    /* Premium card effects */
    .user-card {
      position: relative;
      overflow: hidden;
      transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
      background: white;
    }
    
    .user-card:hover {
      transform: translateY(-8px);
      box-shadow: 0 20px 50px -12px rgba(0, 0, 0, 0.15);
    }
    
    .user-card::after {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, #10b981, #34d399);
      opacity: 0;
      transition: opacity 0.4s ease;
    }
    
    .user-card:hover::after {
      opacity: 1;
    }
    
    /* Document preview styling */
    .doc-preview {
      position: relative;
      overflow: hidden;
      border-radius: 12px;
      transition: all 0.3s ease;
      cursor: pointer;
      aspect-ratio: 16/10;
      background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    }
    
    .doc-preview:hover {
      transform: translateY(-4px);
      box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15);
    }
    
    .doc-preview img {
      transition: transform 0.4s ease;
      object-fit: cover;
      width: 100%;
      height: 100%;
    }
    
    .doc-preview:hover img {
      transform: scale(1.08);
    }
    
    .doc-preview-overlay {
      position: absolute;
      inset: 0;
      background: linear-gradient(to top, rgba(0,0,0,0.4), transparent);
      display: flex;
      align-items: flex-end;
      padding: 12px;
      opacity: 0;
      transition: opacity 0.3s ease;
    }
    
    .doc-preview:hover .doc-preview-overlay {
      opacity: 1;
    }
    
    /* Button effects */
    .btn-approve, .btn-reject {
      position: relative;
      overflow: hidden;
      transition: all 0.3s ease;
      font-weight: 600;
      letter-spacing: 0.025em;
    }
    
    .btn-approve:active, .btn-reject:active {
      transform: scale(0.97);
    }
    
    .btn-approve:hover {
      box-shadow: 0 12px 28px -6px rgba(16, 185, 129, 0.4);
    }
    
    /* Loading skeleton */
    .skeleton {
      background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
      background-size: 200% 100%;
      animation: shimmer 1.5s infinite;
    }
    
    /* Badge styling */
    .status-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 14px;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 600;
      letter-spacing: 0.025em;
      text-transform: uppercase;
    }
    
    .status-badge-pending {
      background: linear-gradient(135deg, #fef3c7, #fde68a);
      color: #92400e;
      box-shadow: 0 2px 8px -2px rgba(251, 191, 36, 0.3);
    }
    
    /* Info grid styling */
    .info-grid-item {
      position: relative;
      padding-left: 12px;
    }
    
    .info-grid-item::before {
      content: '';
      position: absolute;
      left: 0;
      top: 50%;
      transform: translateY(-50%);
      width: 3px;
      height: 60%;
      background: linear-gradient(to bottom, #10b981, #34d399);
      border-radius: 99px;
    }
  </style>
</head>
<body class="antialiased bg-slate-50 font-sans text-slate-800">
  <!-- Layout with sidebar and content -->
  <div class="flex h-screen bg-slate-100">
    <!-- Sidebar (desktop only) -->
    <aside class="hidden md:flex w-64 flex-shrink-0 bg-white text-slate-600 flex-col p-4 border-r border-slate-200">
      <div class="h-16 flex items-center justify-center px-2">
        <img src="responde.png" alt="ManResponde Logo" class="h-10 w-auto object-contain sm:h-12 md:h-14 lg:h-10" onerror="this.style.display='none'">
      </div>
      <nav class="flex-1 px-2 py-4 space-y-1.5">
        <a href="dashboard.php?view=dashboard" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-50 text-slate-600">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
          </svg>
          <span>Dashboard</span>
        </a>

        <a href="dashboard.php?view=analytics" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-50 text-slate-600">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" />
          </svg>
          <span>Analytics</span>
        </a>

        <a href="dashboard.php?view=map" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-50 text-slate-600">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
          </svg>
          <span>Interactive Map</span>
        </a>

        <a href="dashboard.php?view=live-support" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-50 text-slate-600">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" />
          </svg>
          <span>Live Support</span>
          <span id="liveSupportBadge" class="ml-auto bg-red-500 text-white text-xs font-bold px-2 py-0.5 rounded-full hidden">0</span>
        </a>

        <?php if ($isAdmin): ?>
        <a href="dashboard.php?view=create-account" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-50 text-slate-600">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.766z" />
          </svg>
          <span>Create Account</span>
        </a>
        <?php endif; ?>
        
        <?php if ($isAdmin || $isTanod): ?>
        <a href="verify_users.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-sky-100 text-sky-700 font-semibold">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <span>Verify Users</span>
          <span id="verifyUsersBadge" class="ml-auto bg-amber-500 text-white text-xs font-bold px-2 py-0.5 rounded-full hidden">0</span>
        </a>
        <?php endif; ?>
        
        <?php if ($isAdmin): ?>
        <button onclick="showExportModal()" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-50 text-slate-600 w-full text-left">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
          </svg>
          <span>Export Reports</span>
        </button>
        <?php endif; ?>
      </nav>
      <div class="p-2 border-t border-slate-200/70 pt-4">
        <div class="flex items-center gap-3 mb-4">
          <div class="w-10 h-10 rounded-full bg-sky-500 flex items-center justify-center font-bold text-white ring-2 ring-sky-200">
            <?php echo strtoupper(substr($_SESSION['user_fullname'] ?? 'U', 0, 1)); ?>
          </div>
          <div>
            <p class="font-semibold text-slate-800 text-sm"><?php echo htmlspecialchars($_SESSION['user_fullname'] ?? 'User'); ?></p>
            <p class="text-xs text-slate-500"><?php echo htmlspecialchars(ucfirst($userRole)); ?></p>
          </div>
        </div>
        <a href="logout.php" class="flex items-center justify-center gap-2 rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200 w-full px-4 py-2.5 text-sm font-semibold">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
          </svg>
          <span>Logout</span>
        </a>
      </div>
    </aside>

    <!-- Mobile Header (single) -->
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

    <!-- Mobile Menu Overlay (single) -->
    <div id="mobileMenuOverlay" class="md:hidden fixed inset-0 z-40 bg-black bg-opacity-50 hidden">
      <div class="fixed inset-y-0 left-0 w-64 bg-white shadow-xl">
        <div class="flex flex-col h-full">
          <div class="h-16 flex items-center justify-center px-4 border-b border-slate-200">
            <img src="responde.png" alt="ManResponde Logo" class="h-10 w-auto object-contain" onerror="this.style.display='none'">
          </div>
          <nav class="flex-1 px-4 py-4 space-y-1.5 overflow-y-auto">
            <a href="dashboard.php?view=dashboard" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-50 text-slate-600">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
              </svg>
              <span>Dashboard</span>
            </a>

            <a href="dashboard.php?view=analytics" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-50 text-slate-600">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" />
              </svg>
              <span>Analytics</span>
            </a>

            <a href="dashboard.php?view=map" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-50 text-slate-600">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
              </svg>
              <span>Interactive Map</span>
            </a>

            <a href="dashboard.php?view=live-support" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-50 text-slate-600">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" />
              </svg>
              <span>Live Support</span>
              <span id="liveSupportBadgeMobile" class="ml-auto bg-red-500 text-white text-xs font-bold px-2 py-0.5 rounded-full hidden">0</span>
            </a>
            
            <?php if ($isAdmin): ?>
            <a href="dashboard.php?view=create-account" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-50 text-slate-600">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.766z" />
              </svg>
              <span>Create Account</span>
            </a>
            <?php endif; ?>
            
            <?php if ($isAdmin || $isTanod): ?>
            <a href="verify_users.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-sky-100 text-sky-700 font-semibold">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <span>Verify Users</span>
              <span id="verifyUsersBadgeMobile" class="ml-auto bg-amber-500 text-white text-xs font-bold px-2 py-0.5 rounded-full hidden">0</span>
            </a>
            <?php endif; ?>
            
            <?php if ($isAdmin): ?>
            <button onclick="showExportModal(); closeMobileSidebar();" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-50 text-slate-600 w-full text-left">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
              </svg>
              <span>Export Reports</span>
            </button>
            <?php endif; ?>
            
            <div class="border-t border-slate-200 pt-4 mt-4">
              <a href="logout.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-50">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                </svg>
                <span>Logout</span>
              </a>
            </div>
          </nav>
        </div>
      </div>
    </div>

    <!-- Main content -->
    <main class="flex-1 overflow-y-auto bg-slate-50">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 md:py-10">
        <!-- Premium Header Section -->
        <section class="mb-10">
          <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-6 md:p-8">
            <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
              <div>
                <h1 class="text-3xl font-bold text-slate-900">User Verification Hub</h1>
                <p class="text-slate-500 mt-1">Review and manage pending user registrations.</p>
              </div>
              <!-- Premium Stats -->
              <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 w-full md:w-auto">
                <div class="bg-slate-50 rounded-lg p-4 text-center">
                  <p class="text-sm font-medium text-slate-500">Detection</p>
                  <p class="text-lg font-bold text-slate-800 mt-1">Real-time</p>
                </div>
                <div class="bg-slate-50 rounded-lg p-4 text-center">
                  <p class="text-sm font-medium text-slate-500">Pending</p>
                  <p class="text-lg font-bold text-slate-800 mt-1" id="pendingCount">0</p>
                </div>
                <div class="bg-slate-50 rounded-lg p-4 text-center col-span-2 sm:col-span-1">
                  <p class="text-sm font-medium text-slate-500">Today's Activity</p>
                  <p class="text-lg font-bold text-slate-800 mt-1" id="activityCount">0</p>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- User List Section -->
        <section>
          <!-- Loading State -->
          <div id="vuLoading" class="text-center py-20">
            <div class="w-10 h-10 border-4 border-slate-300 border-t-slate-800 rounded-full animate-spin mx-auto"></div>
            <p class="mt-4 text-slate-500">Loading pending users...</p>
          </div>

          <!-- Empty State -->
          <div id="vuEmpty" class="hidden text-center py-20 bg-white rounded-2xl border border-slate-200/80 shadow-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-16 w-16 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <h3 class="mt-4 text-xl font-semibold text-slate-800">All Caught Up!</h3>
            <p class="mt-2 text-slate-500">There are no pending users to verify at this time.</p>
          </div>

          <!-- User Cards Grid -->
          <div id="vuList" class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-8">
            <!-- User cards will be injected here by JavaScript -->
          </div>
        </section>
      </div>
    </main>
  </div>

  <!-- Proof of Residency Modal -->
  <div id="proofModal" class="fixed inset-0 z-50 hidden" style="display: none;">
    <div class="absolute inset-0 bg-black/70" onclick="closeProofModal()"></div>
    <div class="relative flex items-center justify-center min-h-screen p-4">
      <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden border border-slate-200/60 flex flex-col">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200">
          <h3 id="proofModalTitle" class="text-lg font-semibold text-slate-800">Verification Document</h3>
          <button id="closeProofModal" class="p-2 rounded-full hover:bg-slate-100 text-slate-500">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        
        <!-- Modal Content -->
        <div id="proofContent" class="p-6 flex-1 overflow-y-auto text-center">
          <!-- Loading state for modal -->
          <div class="w-8 h-8 border-2 border-sky-500 border-t-transparent rounded-full animate-spin mx-auto"></div>
        </div>

        <!-- Modal Footer -->
        <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 text-right">
          <a id="proofImageLink" href="#" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-lg transition-colors">
            Open in New Tab
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
              <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-4.5 0V6.75a.75.75 0 01.75-.75h3.75a.75.75 0 01.75.75v3.75m-4.5 0h4.5" />
            </svg>
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- Define viewProof function globally for immediate access -->
  <script>
    // viewProof function is defined later in the file

    // Simple close function for the modal
    window.closeProofModal = function() {
      const proofModal = document.getElementById('proofModal');
      if (proofModal) {
        proofModal.classList.add('hidden');
        proofModal.style.display = 'none';
      }
    };

    // Add keyboard support for closing modal
    document.addEventListener('keydown', function(event) {
      if (event.key === 'Escape') {
        const proofModal = document.getElementById('proofModal');
        if (proofModal && !proofModal.classList.contains('hidden')) {
          closeProofModal();
        }
      }
    });

    // Helper function to show Firebase Storage rules fix
    window.showStorageRulesHelp = function() {
      const helpText = `
🔥 FIREBASE STORAGE RULES FIX

Your current rules require authentication to read proof_of_residency images,
but verify_users.php needs to access them without authentication.

1. Go to Firebase Console: https://console.firebase.google.com/
2. Select your project: ibantayv2
3. Click "Storage" in the left sidebar
4. Click "Rules" tab
5. Replace current rules with:

rules_version = '2';
service firebase.storage {
  match /b/{bucket}/o {

    // Allow public read access for proof of residency images (for verification)
    match /proof_of_residency/{imageId} {
      allow write; // Allows uploads without authentication
      allow read: if true; // Allow anyone to read (for admin verification)
    }

    // Secure all other paths to only allow access for authenticated users
    match /{allPaths=**} {
      allow read, write: if request.auth != null;
    }
  }
}

6. Click "Publish" to save
7. Wait 5-10 minutes for rules to propagate
8. Try viewing the proof image again

✅ This only allows public read access to proof_of_residency images,
keeping other files secure.
      `;

      console.log(helpText);

      // Copy rules to clipboard
      const rulesText = `rules_version = '2';
service firebase.storage {
  match /b/{bucket}/o {

    // Allow public read access to registration images (for admin verification)
    match /registration_images/{allPaths=**} {
      allow read: if true; // Allow anyone to read (for admin verification)
      allow write: if request.auth != null; // Only authenticated users can upload
    }

    // Allow public read access for proof of residency images (for verification)
    match /proof_of_residency/{allPaths=**} {
      allow read: if true; // Allow anyone to read (for admin verification)
      allow write: if request.auth != null; // Only authenticated users can upload
    }

    // Secure all other paths to only allow access for authenticated users
    match /{allPaths=**} {
      allow read, write: if request.auth != null;
    }
  }
}`;

      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(rulesText).then(() => {
          alert('✅ Firebase Storage rules copied to clipboard!\n\n📋 Paste them into Firebase Console → Storage → Rules');
        });
      } else {
        alert('📋 Firebase Storage rules copied to console!\n\n📝 Copy them from the browser console and paste into Firebase Console → Storage → Rules');
      }
    };
  </script>



  <!-- Premium Toast notification -->
  <div id="vuToast" class="fixed bottom-8 left-1/2 -translate-x-1/2 px-6 py-4 rounded-2xl text-white text-sm shadow-2xl hidden backdrop-blur-xl border border-white/20 z-50">
    <div class="flex items-center gap-3">
      <div class="w-2 h-2 rounded-full bg-current"></div>
      <span class="font-medium"></span>
    </div>
  </div>

  <!-- Mobile menu functionality -->
  <script>
    // Mobile menu toggle (guard for desktop)
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const mobileMenuOverlay = document.getElementById('mobileMenuOverlay');
    if (mobileMenuBtn && mobileMenuOverlay) {
      mobileMenuBtn.addEventListener('click', () => {
        mobileMenuOverlay.classList.toggle('hidden');
      });
      mobileMenuOverlay.addEventListener('click', (e) => {
        if (e.target === mobileMenuOverlay) {
          mobileMenuOverlay.classList.add('hidden');
        }
      });
    }
    
    // Close mobile sidebar function
    function closeMobileSidebar() {
      if (mobileMenuOverlay) {
        mobileMenuOverlay.classList.add('hidden');
      }
    }
    
    // Show export modal function (redirects to export page)
    function showExportModal() {
      window.location.href = 'export_reports.php';
    }
  </script>
  


  <!-- Proof Modal functionality -->
  <script>
    // Firebase Storage Configuration
    // IMPORTANT: Update the storageBucket below to match your actual Firebase Storage bucket
    const FIREBASE_CONFIG = {
      projectId: 'ibantayv2',
      storageBucket: 'ibantayv2.firebasestorage.app', // Correct bucket from gs://ibantayv2.firebasestorage.app
      // 
      // To find your bucket name:
      // 1. Go to Firebase Console > Storage
      // 2. Look at the bucket name in the URL or storage rules
      // 3. Common formats: projectId.appspot.com or projectId.firebasestorage.app
      // 4. Check your Firebase project settings for the exact bucket name
      //
      // Example paths in Firestore:
      // - "proof_of_residency/ac08b6be-83c7-4405-8850-a6864ad2eaf5"
      // - "proof_of_residency/user123_document.jpg"
      // - "proof_of_residency/video_proof.mp4"
    };
    
    // Alternative bucket configurations to try
    const ALTERNATIVE_BUCKETS = [
      'ibantayv2.firebasestorage.app', // Primary - correct bucket
      'ibantayv2.appspot.com',         // Fallback
      'ibantayv2.firebaseapp.com',     // Fallback
      'ibantayv2.storage.googleapis.com' // Fallback
    ];

    const proofModal = document.getElementById('proofModal');
    const closeProofModal = document.getElementById('closeProofModal');
    const proofContent = document.getElementById('proofContent');

    // Close modal when clicking close button
    closeProofModal.addEventListener('click', () => {
      proofModal.classList.add('hidden');
      proofModal.style.display = 'none';
    });

    // Close modal when clicking outside
    proofModal.addEventListener('click', (e) => {
      if (e.target === proofModal) {
        proofModal.classList.add('hidden');
        proofModal.style.display = 'none';
      }
    });

    // Close modal with Escape key
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && !proofModal.classList.contains('hidden')) {
        proofModal.classList.add('hidden');
        proofModal.style.display = 'none';
      }
    });

    // Function to test Firebase Storage connection
    async function testFirebaseStorage(bucket, path) {
      const testUrl = `https://firebasestorage.googleapis.com/v0/b/${bucket}/o/${encodeURIComponent(path)}?alt=media`;
      try {
        const response = await fetch(testUrl, { method: 'HEAD' });
        return {
          success: response.ok,
          status: response.status,
          statusText: response.statusText,
          url: testUrl
        };
      } catch (error) {
        return {
          success: false,
          error: error.message,
          url: testUrl
        };
      }
    }
    
    // Function to help find the correct bucket name
    function findCorrectBucket() {
      console.log('=== FINDING CORRECT BUCKET ===');
      console.log('1. Go to Firebase Console: https://console.firebase.google.com/');
      console.log('2. Select your project: ibantayv2');
      console.log('3. Click on "Storage" in the left sidebar');
      console.log('4. Look at the URL in your browser - it should show the bucket name');
      console.log('5. Common bucket formats:');
      console.log('   - projectId.appspot.com');
      console.log('   - projectId.firebasestorage.app');
      console.log('   - custom-bucket-name (if you set a custom name)');
      console.log('6. Check your Firebase project settings:');
      console.log('   - Project Settings > General > Your apps > Config');
      console.log('   - Look for storageBucket in the config');
      console.log('================================');
      
      // Also try to get bucket from Firebase config if available
      if (typeof firebase !== 'undefined' && firebase.app) {
        try {
          const config = firebase.app().options;
          console.log('Firebase config found:', config);
          if (config.storageBucket) {
            console.log('Storage bucket from config:', config.storageBucket);
          }
        } catch (e) {
          console.log('Could not access Firebase config:', e);
        }
      }
    }
    
    // Function to help fix Firebase Storage rules
    function fixStorageRules() {
      console.log('=== FIXING FIREBASE STORAGE RULES ===');
      console.log('The 403 error means Firebase Storage rules are blocking access.');
      console.log('');
      console.log('To fix this, follow these steps:');
      console.log('');
      console.log('1. Go to Firebase Console: https://console.firebase.google.com/');
      console.log('2. Select your project: ibantayv2');
      console.log('3. Click on "Storage" in the left sidebar');
      console.log('4. Click on "Rules" tab');
      console.log('5. Replace the current rules with these:');
      console.log('');
      console.log('rules_version = "2";');
      console.log('service firebase.storage {');
      console.log('  match /b/{bucket}/o {');
      console.log('    // Allow public read access to all files');
      console.log('    match /{allPaths=**} {');
      console.log('      allow read: if true;');
      console.log('      allow write: if request.auth != null;');
      console.log('    }');
      console.log('  }');
      console.log('');
      console.log('6. Click "Publish" to save the rules');
      console.log('7. Wait a few minutes for rules to propagate');
      console.log('8. Try viewing the proof of residency again');
      console.log('');
      console.log('⚠️  WARNING: These rules allow public read access to ALL files.');
      console.log('   For production, you should restrict access based on user authentication.');
      console.log('================================');
    }

    // Enhanced Firebase Storage rules fix function
    window.fixFirebaseStorageRules = function() {
      const rulesText = `rules_version = '2';
service firebase.storage {
  match /b/{bucket}/o {
    // Allow public read access to registration images (for admin verification)
    match /registration_images/{allPaths=**} {
      allow read: if true; // Public read for admin verification
      allow write: if request.auth != null; // Only authenticated users can upload
    }
    
    // Allow public read access to proof of residency images
    match /proof_of_residency/{allPaths=**} {
      allow read: if true; // Public read for admin verification  
      allow write: if request.auth != null; // Only authenticated users can upload
    }
    
    // Secure all other paths to only allow access for authenticated users
    match /{allPaths=**} {
      allow read, write: if request.auth != null;
    }
  }
}`;

      console.log('🔥 FIREBASE STORAGE RULES FIX');
      console.log('');
      console.log('1. Go to Firebase Console: https://console.firebase.google.com/');
      console.log('2. Select your project: ibantayv2');
      console.log('3. Click "Storage" → "Rules"');
      console.log('4. Replace current rules with:');
      console.log('');
      console.log(rulesText);
      console.log('');
      console.log('5. Click "Publish"');
      console.log('6. Wait 2-3 minutes for rules to update');
      console.log('7. Refresh this page');

      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(rulesText).then(() => {
          alert('✅ Firebase Storage rules copied to clipboard!\n\n📋 Now:\n1. Go to Firebase Console → Storage → Rules\n2. Paste the rules (Ctrl+V)\n3. Click "Publish"\n4. Wait 2-3 minutes\n5. Refresh this page');
        });
      } else {
        // Fallback for non-HTTPS or older browsers
        const textArea = document.createElement('textarea');
        textArea.value = rulesText;
        document.body.appendChild(textArea);
        textArea.select();
        try {
          document.execCommand('copy');
          alert('✅ Firebase Storage rules copied!\n\n📋 Now:\n1. Go to Firebase Console → Storage → Rules\n2. Paste the rules (Ctrl+V)\n3. Click "Publish"\n4. Wait 2-3 minutes\n5. Refresh this page');
        } catch (err) {
          alert('📋 Copy the rules from the browser console!\n\nCheck the console for the exact rules to copy.');
        }
        document.body.removeChild(textArea);
      }
    };

    // Function to view proof (called from user cards)
    window.viewProof = function(proofPath, userName) {
      console.log('🔍 viewProof function called with:', proofPath, userName);

      // Get modal elements
      const proofModal = document.getElementById('proofModal');
      const proofContent = document.getElementById('proofContent');
      const proofModalTitle = document.getElementById('proofModalTitle');
      const proofImageLink = document.getElementById('proofImageLink');

      // Check if modal elements exist
      if (!proofModal) {
        console.error('❌ Modal element not found!');
        alert('Modal element not found! Check console for details.');
        return;
      }

      console.log('✅ Modal found, showing it...');
      console.log('Modal element:', proofModal);
      console.log('Modal current classes:', proofModal.className);
      console.log('Modal current style:', proofModal.style.display);
      
      // Show modal
      proofModal.classList.remove('hidden');
      proofModal.style.display = 'block'; // Ensure modal is visible

      // Update modal title
      if (proofModalTitle) {
        proofModalTitle.textContent = `Document for ${userName}`;
      }

      // Show loading state in main content
      if (proofContent) {
      proofContent.innerHTML = `
        <div class="text-center text-slate-500 py-10">
          <div class="w-8 h-8 border-2 border-slate-400 border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
          <p class="font-medium">Loading document...</p>
        </div>
      `;
      }

      // No media provided case
      if (!proofPath || proofPath.trim() === '' || proofPath === 'null' || proofPath === 'undefined') {
        console.log('No proof path provided');
        if (proofContent) {
          proofContent.innerHTML = `
            <div class="text-center text-slate-500 py-10">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 mx-auto mb-4 text-slate-300">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
              </svg>
              <p class="text-lg font-medium mb-2">No Document Provided</p>
              <p class="text-sm text-slate-400">No document was uploaded by ${userName}.</p>
            </div>
          `;
        }
        if (proofImageLink) proofImageLink.href = '#';
        return; // Exit early
      }

      // Load the actual image from Firebase Storage
      try {
        // Ensure proofPath is a string
        proofPath = String(proofPath);
        console.log('🔥 Processing Firebase image URL:', proofPath);
        
        // Check if it's already a complete Firebase Storage URL
        let firebaseStorageUrl;
        if (proofPath.startsWith('https://firebasestorage.googleapis.com/') || proofPath.startsWith('http')) {
          // It's already a complete URL, use it directly
          firebaseStorageUrl = proofPath;
          console.log('✅ Using complete Firebase URL directly:', firebaseStorageUrl);
        } else {
          // It's a path, convert to Firebase Storage URL
          const bucket = (typeof FIREBASE_CONFIG !== 'undefined' && FIREBASE_CONFIG.storageBucket) ? FIREBASE_CONFIG.storageBucket : 'ibantayv2.firebasestorage.app';
          const encodedPath = encodeURIComponent(proofPath);
          firebaseStorageUrl = `https://firebasestorage.googleapis.com/v0/b/${bucket}/o/${encodedPath}?alt=media`;
          console.log('🔗 Converted path to Firebase URL:', firebaseStorageUrl);
        }

        console.log('🖼️ Final URL to load:', firebaseStorageUrl);

        // Update the link for "Open in New Tab" immediately
        if (proofImageLink) {
          proofImageLink.href = firebaseStorageUrl;
          // Ensure it opens in a new tab
          proofImageLink.target = "_blank";
          // Prevent default behavior if href is #
          proofImageLink.onclick = function(e) {
             if (this.getAttribute('href') === '#') {
                 e.preventDefault();
                 alert('No valid image URL to open.');
             }
          };
        }

        // Test URL accessibility before trying to load image
        fetch(firebaseStorageUrl, { method: 'HEAD' })
          .then(response => {
            console.log('📡 URL test result:', response.status, response.statusText);
            if (response.ok) {
              console.log('✅ URL is accessible');
            } else {
              console.log('⚠️ URL returned status:', response.status);
            }
          })
          .catch(error => {
            console.log('❌ URL accessibility test failed:', error.message);
          });

        // Reset all media elements
        // Note: proofImage, proofVideo, proofMediaNone are not defined in this scope in the original code
        // We should check if they exist or remove this block if they are not used
        // Based on the HTML structure, we are injecting HTML into proofContent, so we don't need to reset these variables if they are not global.
        
        // Function to determine if URL is a video file
        const isVideo = (url) => {
          const videoExtensions = ['.mp4', '.webm', '.ogg', '.avi', '.mov', '.wmv', '.flv', '.mkv', '.m4v', '.3gp'];
          const urlLower = url.toLowerCase();
          return videoExtensions.some(ext => urlLower.includes(ext));
        };

        if (isVideo(firebaseStorageUrl)) {
          // Handle video
          console.log('🎥 Detected video file');
          proofContent.innerHTML = `
            <video controls autoplay class="max-w-full max-h-[70vh] mx-auto rounded-lg">
              <source src="${firebaseStorageUrl}" type="video/mp4">
              Your browser does not support the video tag.
            </video>
          `;
        } else {
          // Handle image
          console.log('🖼️ Loading image from Firebase Storage');
          const img = new Image();
          img.src = firebaseStorageUrl;
          img.alt = `Proof for ${userName}`;
          img.className = "max-w-full max-h-[70vh] mx-auto rounded-lg shadow-md";

          img.onload = function() {
            console.log('✅ Image loaded successfully!');
            proofContent.innerHTML = ''; // Clear loading spinner
            proofContent.appendChild(img);
          };

          img.onerror = function() {
            console.error('❌ Failed to load image:', firebaseStorageUrl);
            proofContent.innerHTML = `
              <div class="text-center text-red-500 py-10">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 mx-auto mb-4 text-red-400">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
                <p class="text-lg font-medium mb-2">Image Failed to Load</p>
                <p class="text-sm text-slate-500">The document could not be loaded. It might be missing or there's a permission issue.</p>
                <button onclick="fixStorageRules()" class="mt-4 px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700">Fix Permissions</button>
              </div>
            `;
          };
        }

      } catch (error) {
        console.error('Error in media loading process:', error);
        proofContent.innerHTML = `
          <div class="text-center text-red-500 py-10">
            <p>An unexpected error occurred: ${error.message}</p>
          </div>
        `;
      }
    }; // Close window.viewProof function

  </script>
  <!-- Current User Context -->
  <script>
    window.CURRENT_USER = {
      role: "<?php echo htmlspecialchars($userRole); ?>",
      isAdmin: <?php echo $isAdmin ? 'true' : 'false'; ?>,
      isTanod: <?php echo $isTanod ? 'true' : 'false'; ?>,
      assignedBarangay: "<?php echo htmlspecialchars($assignedBarangay); ?>"
    };
    console.log('Current User Context:', window.CURRENT_USER);
  </script>

  <!-- Firebase + real-time logic -->
  <script type="module">
    import { initializeApp } from "https://www.gstatic.com/firebasejs/10.12.5/firebase-app.js";
    import {
      getFirestore, collection, doc, getDocs, query, where, orderBy, limit, onSnapshot,
      updateDoc, serverTimestamp
    } from "https://www.gstatic.com/firebasejs/10.12.5/firebase-firestore.js";

    // Your Firebase project configuration
    const CONFIG = {
      apiKey: "AIzaSyDiNgvmttAwhAjPthjJtcZ1Hr9PLWnhErQ",
      authDomain: "ibantayv2.firebaseapp.com",
      projectId: "ibantayv2", // Added projectId
      storageBucket: "ibantayv2.firebasestorage.app",
      messagingSenderId: "978957037468",
      appId: "1:978957037468:web:ab01a25d49e09244716259"
    };

    // Initialize Firebase
    const app = initializeApp(CONFIG);
    const db  = getFirestore(app);

    // DOM elements
    const vuList  = document.getElementById('vuList');
    const vuEmpty = document.getElementById('vuEmpty');
    const vuToast = document.getElementById('vuToast');
    const pendingCount = document.getElementById('pendingCount');
    
    function toast(msg, type='info') {
      const toastSpan = vuToast.querySelector('span');
      toastSpan.textContent = msg;
      
      // Set background and icon color based on type
      if (type === 'success') {
        vuToast.style.background = '#10B981'; // Emerald 500
        vuToast.style.borderColor = '#059669';
      } else if (type === 'error') {
        vuToast.style.background = '#EF4444'; // Red 500
        vuToast.style.borderColor = '#DC2626';
      } else {
        vuToast.style.background = '#1f2937'; // Gray 800
        vuToast.style.borderColor = '#374151';
      }
      
      vuToast.classList.remove('hidden');
      clearTimeout(toast._t);
      toast._t = setTimeout(() => {
        vuToast.classList.add('hidden');
      }, 3000);
    }

    function normalizeUser(id, data) {
      // Normalize common user fields so the UI can render details consistently.
      const name = data.fullName || data.name || data.displayName || 'New user';
      const email = data.email || data.emailAddress || '';
      const contact = data.contact || data.mobile || data.phone || data.mobileNumber || '';
      const birthdate = data.birthdate || data.birthDate || '';
      const address = data.address || data.currentAddress || data.permanentAddress || '';
      // Support multiple keys for the pending status so we don't miss any variants.
      const status = data.accountStatus ?? data.status ?? data.Status ?? data.AccountStatus ?? 'pending';
      // Proof document path (optional). Try several keys for compatibility.
      const proof = data.proofOfResidencyPath || data.proofPath || '';
      
      console.log('Normalizing user:', {
        id,
        name,
        status,
        rawStatus: data.status,
        rawAccountStatus: data.accountStatus,
        hasData: !!data
      });
      
      return {
        id,
        name,
        email,
        contact,
        birthdate,
        address,
        proof,
        status,
        createdAt: data.createdAt || data._created || data.timestamp || data.registrationDate || null,
        data // Pass the entire data object so we can access all fields
      };
    }

    function userCardHTML(u) {
      // Format created date if available.
      const createdAtStr = u.createdAt
        ? new Date(u.createdAt.seconds ? u.createdAt.seconds * 1000 : u.createdAt).toLocaleString()
        : '';

      // Debug: Log user data
      console.log('🔍 User card data:', {
        id: u.id,
        name: u.name,
        data: u.data
      });

      // Get all the detailed information from the user data
     
      const userData = u.data || {};
      const firstName = userData.firstName || '';
      const middleName = userData.middleName || '';
      const lastName = userData.lastName || '';
      const suffix = userData.suffix || '';
      const fullName = userData.fullName || u.name || '';
      const username = userData.username || '';
      const email = userData.email || u.email || '';
      const mobileNumber = userData.mobileNumber || userData.contact || u.contact || '';
      const birthdate = userData.birthdate || u.birthdate || '';
      const gender = userData.gender || '';
      
      // Current Address Details
      const currentAddress = userData.currentAddress || userData.address || u.address || '';
      const currentStreetAddress = userData.currentStreetAddress || '';
      const currentBarangay = userData.currentBarangay || '';
      const currentCity = userData.currentCity || '';
      const currentProvince = userData.currentProvince || '';
      const currentRegion = userData.currentRegion || '';
      const currentZipCode = userData.currentZipCode || '';
      
      // ID Address Details  
      const idStreetAddress = userData.idStreetAddress || '';
      const idBarangay = userData.idBarangay || '';
      const idCity = userData.idCity || '';
      const idProvince = userData.idProvince || '';
      const idRegion = userData.idRegion || '';
      const idZipCode = userData.idZipCode || '';
      const permanentAddress = userData.permanentAddress || '';
      const sameAsIdAddress = userData.sameAsIdAddress || false;
      
      // Document URLs - prioritize the exact field names from Firebase
      const frontIdImageUrl = userData.frontIdImageUrl || userData.frontIdPath || userData.frontId || userData.frontImageUrl || userData.frontImage || '';
      const backIdImageUrl = userData.backIdImageUrl || userData.backIdPath || userData.backId || userData.backImageUrl || userData.backImage || '';
      const selfieImageUrl = userData.selfieImageUrl || userData.selfiePath || userData.selfie || userData.selfieImage || '';
      const proofOfResidencyPath = userData.proofOfResidencyPath || userData.proofPath || userData.proofOfResidency || userData.proof || u.proof || '';
      
      // Debug: Log the detected image URLs
      console.log('🔍 Document URLs detected:', {
        frontIdImageUrl: frontIdImageUrl,
        backIdImageUrl: backIdImageUrl, 
        selfieImageUrl: selfieImageUrl,
        proofOfResidencyPath: proofOfResidencyPath,
        hasValidFrontId: !!(frontIdImageUrl && frontIdImageUrl !== 'null' && frontIdImageUrl.trim() !== ''),
        hasValidBackId: !!(backIdImageUrl && backIdImageUrl !== 'null' && backIdImageUrl.trim() !== ''),
        hasValidSelfie: !!(selfieImageUrl && selfieImageUrl !== 'null' && selfieImageUrl.trim() !== '')
      });
      
      // Status and dates
      const status = userData.status || u.status || 'pending';
      const registrationDate = userData.registrationDate ? new Date(userData.registrationDate.seconds ? userData.registrationDate.seconds * 1000 : userData.registrationDate).toLocaleString() : '';
      const lastLoginDate = userData.lastLoginDate ? new Date(userData.lastLoginDate.seconds ? userData.lastLoginDate.seconds * 1000 : userData.lastLoginDate).toLocaleString() : '';
      const role = userData.role || 'user';

      // Helper function to create beautiful document preview with image
      const createDocPreview = (imageUrl, label, iconPath) => {
        // Handle null, undefined, empty string, or "null" string
        if (!imageUrl || imageUrl === 'null' || imageUrl.toString().trim() === '' || imageUrl === 'undefined') {
          return `
            <div class="doc-preview bg-gradient-to-br from-slate-50 to-slate-100 flex items-center justify-center">
              <div class="text-center">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-10 h-10 mx-auto mb-2 text-slate-300">
                  ${iconPath}
                </svg>
                <p class="text-xs text-slate-400 font-medium">${label}</p>
                <p class="text-xs text-slate-300 mt-1">Not provided</p>
              </div>
            </div>
          `;
        }
        
        // Convert to Firebase Storage URL if needed
        let displayUrl = imageUrl;
        if (!imageUrl.startsWith('http')) {
          const encodedPath = encodeURIComponent(imageUrl);
          displayUrl = `https://firebasestorage.googleapis.com/v0/b/ibantayv2.firebasestorage.app/o/${encodedPath}?alt=media`;
        }
        
        return `
          <div class="doc-preview group" onclick="window.viewProof('${imageUrl}', '${fullName} - ${label}')">
            <img src="${displayUrl}" alt="${label}" onerror="this.parentElement.innerHTML='<div class=&quot;flex items-center justify-center h-full bg-slate-100&quot;><svg xmlns=&quot;http://www.w3.org/2000/svg&quot; fill=&quot;none&quot; viewBox=&quot;0 0 24 24&quot; stroke-width=&quot;1.5&quot; stroke=&quot;currentColor&quot; class=&quot;w-10 h-10 text-slate-400&quot;><path stroke-linecap=&quot;round&quot; stroke-linejoin=&quot;round&quot; d=&quot;M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z&quot; /></svg></div>'">
            <div class="doc-preview-overlay">
              <div class="text-white flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <span class="text-sm font-semibold">${label}</span>
              </div>
            </div>
          </div>
        `;
      };

      // Document previews (Front ID, Back ID, Selfie only - NO Proof of Residency)
      const frontIdPreview = createDocPreview(frontIdImageUrl, 'Front ID', 
        '<path stroke-linecap="round" stroke-linejoin="round" d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5zm6-10.125a1.875 1.875 0 11-3.75 0 1.875 1.875 0 013.75 0z" />');
      
      const backIdPreview = createDocPreview(backIdImageUrl, 'Back ID',
        '<path stroke-linecap="round" stroke-linejoin="round" d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5zm6-10.125a1.875 1.875 0 11-3.75 0 1.875 1.875 0 013.75 0z" />');
      
      const selfiePreview = createDocPreview(selfieImageUrl, 'Selfie Photo',
        '<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />');

      return `
        <div class="user-card rounded-2xl p-8 border border-slate-200/60 shadow-sm" data-uid="${u.id}">
          <div class="space-y-8">
            <!-- Header Section -->
            <div class="flex items-start justify-between pb-6 border-b border-slate-100">
              <div class="flex items-start gap-4">
                <div class="relative">
                  <div class="flex items-center justify-center w-16 h-16 bg-gradient-to-br from-emerald-50 to-emerald-100 rounded-2xl border-2 border-emerald-200/50">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-8 h-8 text-emerald-600">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                    </svg>
                  </div>
                  <div class="absolute -bottom-1 -right-1 w-5 h-5 bg-emerald-500 rounded-full border-2 border-white flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" class="w-3 h-3 text-white">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                  </div>
                </div>
                <div>
                  <h3 class="text-2xl font-bold text-slate-900 mb-1">${fullName}</h3>
                  <p class="text-slate-500 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                    </svg>
                    ${email || 'No email provided'}
                  </p>
                </div>
              </div>
              <span class="status-badge status-badge-pending">
                <span class="w-2 h-2 bg-amber-500 rounded-full animate-pulse"></span>
                Pending Review
              </span>
            </div>

            <!-- User Information Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div class="info-grid-item">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Username</p>
                <p class="text-base font-semibold text-slate-800">${username || '—'}</p>
              </div>
              <div class="info-grid-item">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Mobile Number</p>
                <p class="text-base font-semibold text-slate-800">${mobileNumber || '—'}</p>
              </div>
              <div class="info-grid-item">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Birthdate</p>
                <p class="text-base font-semibold text-slate-800">${birthdate || '—'}</p>
              </div>
              <div class="info-grid-item">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Gender</p>
                <p class="text-base font-semibold text-slate-800">${gender || '—'}</p>
              </div>
              <div class="info-grid-item md:col-span-2">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Current Address</p>
                <p class="text-base font-semibold text-slate-800">${currentAddress || '—'}</p>
              </div>
            </div>

            <!-- Verification Documents Section -->
            <div class="pt-6 border-t border-slate-100">
              <div class="flex items-center gap-2 mb-5">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-emerald-600">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                </svg>
                <h4 class="text-lg font-bold text-slate-800">Verification Documents</h4>
              </div>
              <div class="grid grid-cols-3 gap-4">
                ${frontIdPreview}
                ${backIdPreview}
                ${selfiePreview}
              </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row gap-3 pt-6 border-t border-slate-100">
              <button class="btn-reject flex-1 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold px-6 py-3.5 rounded-xl transition-all duration-200 flex items-center justify-center gap-2.5" data-id="${u.id}">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
                <span>Reject Application</span>
              </button>
              <button class="btn-approve flex-1 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-6 py-3.5 rounded-xl shadow-lg shadow-emerald-600/30 transition-all duration-200 flex items-center justify-center gap-2.5" data-id="${u.id}">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>Approve User</span>
              </button>
            </div>
          </div>
        </div>
      `;
    }

    function shouldShowUser(u) {
      const currentUser = window.CURRENT_USER;
      // Admin sees all
      if (currentUser.isAdmin) return true;
      
      // Tanod logic
      if (currentUser.isTanod) {
          const staffBarangay = (currentUser.assignedBarangay || '').toLowerCase().trim();
          
          // If staff has no assigned barangay, they can't verify anyone (safety fallback)
          if (!staffBarangay) return false;

          const userData = u.data || {};
          // Check multiple possible fields for barangay
          const userBarangay = (userData.currentBarangay || userData.barangay || userData.Barangay || '').toLowerCase().trim();
          const userAddress = (userData.currentAddress || userData.address || u.address || '').toLowerCase().trim();

          // 1. Direct match on barangay field
          if (userBarangay === staffBarangay) return true;

          // 2. Check if address contains the barangay name (e.g. "Purok 1, Balayong")
          if (userAddress.includes(staffBarangay)) return true;
          
          return false;
      }

      return false;
    }

    function upsertCard(u) {
      // Filter users based on role and barangay
      if (!shouldShowUser(u)) {
        // If user shouldn't be shown, ensure they are removed if they exist (e.g. if data changed)
        removeCard(u.id);
        return;
      }

      const existing = vuList.querySelector(`.user-card[data-uid="${u.id}"]`);
      if (existing) {
        existing.outerHTML = userCardHTML(u);
      } else {
        vuList.insertAdjacentHTML('afterbegin', userCardHTML(u));
      }
      
      updateEmpty();
      updatePendingCount();
    }

    function removeCard(id) {
      const el = vuList.querySelector(`.user-card[data-uid="${id}"]`);
      if (el) el.remove();
      updateEmpty();
      updatePendingCount();
    }

    function updateEmpty() {
      // Hide loading indicator
      const loadingEl = document.getElementById('vuLoading');
      if (loadingEl) {
        loadingEl.remove();
      }
      
      const hasCards = !!vuList.querySelector('.user-card');
      if (hasCards) {
        vuEmpty.classList.add('hidden');
      } else {
        vuEmpty.classList.remove('hidden');
      }
    }
    
    // Function to update pending count in real-time
    function updatePendingCount() {
      const pendingCards = vuList.querySelectorAll('.user-card');
      const count = pendingCards.length;
      pendingCount.textContent = count;
      
      // Update the display text
      if (count === 0) {
        pendingCount.textContent = '0';
      } else if (count === 1) {
        pendingCount.textContent = '1';
      } else {
        pendingCount.textContent = count.toString();
      }

      // Update Sidebar Badge for Verify Users
      const badge = document.getElementById('verifyUsersBadge');
      const mobileBadge = document.getElementById('verifyUsersBadgeMobile');
      
      if (count > 0) {
          if (badge) {
              badge.textContent = count;
              badge.classList.remove('hidden');
          }
          if (mobileBadge) {
              mobileBadge.textContent = count;
              mobileBadge.classList.remove('hidden');
          }
      } else {
          if (badge) badge.classList.add('hidden');
          if (mobileBadge) mobileBadge.classList.add('hidden');
      }
    }

    // Live Support Badge Logic
    async function updateLiveSupportBadge() {
        try {
            const response = await fetch('api/support_chat.php?action=get_chats');
            const result = await response.json();
            
            if (Array.isArray(result.chats)) {
                // Count pending chats
                const pendingCount = result.chats.filter(c => !c.status || c.status === 'pending' || c.status === 'waiting').length;
                
                const badgeDesktop = document.getElementById('liveSupportBadge');
                const badgeMobile = document.getElementById('liveSupportBadgeMobile');
                
                if (pendingCount > 0) {
                    if (badgeDesktop) {
                        badgeDesktop.textContent = pendingCount;
                        badgeDesktop.classList.remove('hidden');
                    }
                    if (badgeMobile) {
                        badgeMobile.textContent = pendingCount;
                        badgeMobile.classList.remove('hidden');
                    }
                } else {
                    if (badgeDesktop) badgeDesktop.classList.add('hidden');
                    if (badgeMobile) badgeMobile.classList.add('hidden');
                }
            }
        } catch (error) {
            console.error('Error updating live support badge:', error);
        }
    }

    // Start polling for live support badge updates
    setInterval(updateLiveSupportBadge, 5000); // Poll every 5 seconds
    updateLiveSupportBadge(); // Initial call

    async function setStatus(id, newStatus) {
      try {
        await updateDoc(doc(db, 'users', id), {
          accountStatus: newStatus,
          status: newStatus,
          Status: newStatus,
          AccountStatus: newStatus,
          reviewedAt: serverTimestamp()
        });
        
        // If user is approved, send FCM notification
        if (newStatus === 'approved') {
          try {
            const response = await fetch(window.location.href, {
              method: 'POST',
              headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
              },
              body: new URLSearchParams({
                'action': 'send_approval_notification',
                'userId': id
              })
            });
            
            const result = await response.json();
            if (result.success) {
              console.log('✅ Approval notification sent successfully');
              toast('User approved and notification sent! 🎉', 'success');
            } else {
              console.error('❌ Failed to send approval notification:', result.error);
              toast('User approved, but notification failed to send', 'error');
            }
          } catch (notificationError) {
            console.error('❌ Error sending approval notification:', notificationError);
            toast('User approved, but notification failed to send', 'error');
          }
        } else {
          toast('User ' + newStatus, 'success');
        }
        
        removeCard(id);
        
        // Update pending count in real-time
        updatePendingCount();
      } catch (err) {
        console.error(err);
        toast('Failed to update status', 'error');
      }
    }

    // Delegate click events for Approve/Reject
    document.addEventListener('click', (e) => {
      const approveBtn = e.target.closest('.btn-approve');
      if (approveBtn) {
        const id = approveBtn.getAttribute('data-id');
        if (id) setStatus(id, 'approved');
        return;
      }
      const rejectBtn = e.target.closest('.btn-reject');
      if (rejectBtn) {
        const id = rejectBtn.getAttribute('data-id');
        if (id) setStatus(id, 'rejected');
        return;
      }
    });

    // Load initial pending users (Fast Mode - Non-blocking)
    async function loadPending() {
      console.log('Starting to load pending users (Fast Mode)...');
      const processedIds = new Set();
      
      // Function to process any snapshot immediately
      const processResult = (snap, sourceName) => {
          console.log(`Source [${sourceName}] returned ${snap.size} docs`);
          if (snap.empty) return;
          
          let newFound = 0;
          snap.forEach(docSnap => {
              const id = docSnap.id;
              // Avoid duplicates
              if (!processedIds.has(id)) {
                  const data = docSnap.data();
                  // Client-side verification of status to be safe
                  const status = (data.accountStatus ?? data.status ?? data.Status ?? data.AccountStatus ?? 'pending').toLowerCase();
                  
                  if (status === 'pending') {
                      processedIds.add(id);
                      upsertCard(normalizeUser(id, data));
                      newFound++;
                  }
              }
          });
          
          if (newFound > 0) {
              updateEmpty();
              updatePendingCount();
          }
      };

      // Define queries - Fire multiple strategies at once
      const queries = [
          // Strategy 1: Direct status query (Fastest if indexed)
          { name: 'status=pending', q: query(collection(db, 'users'), where('status', '==', 'pending'), limit(50)) },
          
          // Strategy 2: Alternative field name
          { name: 'accountStatus=pending', q: query(collection(db, 'users'), where('accountStatus', '==', 'pending'), limit(50)) },
          
          // Strategy 3: Newest users first (Uses auto-created index on registrationDate)
          // This is crucial for finding recently registered users if the status index is missing
          { name: 'newest_by_reg', q: query(collection(db, 'users'), orderBy('registrationDate', 'desc'), limit(100)) },

          // Strategy 4: Newest users by createdAt (Alternative timestamp field)
          { name: 'newest_by_created', q: query(collection(db, 'users'), orderBy('createdAt', 'desc'), limit(100)) },
          
          // Strategy 5: Fallback - Get a larger chunk of users (by ID) and filter client-side
          { name: 'latest_users_fallback', q: query(collection(db, 'users'), limit(500)) }
      ];

      // Execute all independently - don't wait for one to block the others
      queries.forEach(({ name, q }) => {
          getDocs(q)
            .then(snap => processResult(snap, name))
            .catch(err => {
                console.warn(`Query [${name}] failed (likely missing index):`, err);
            });
      });
      
      // Safety timeout: If nothing loads in 10 seconds, remove the spinner so user isn't stuck
      setTimeout(() => {
          const loadingEl = document.getElementById('vuLoading');
          if (loadingEl) {
             console.log('Safety timeout reached. Checking if any users found...');
             updateEmpty(); // This will show "All Caught Up" if no cards found yet
          }
      }, 10000);
    }

    // Setup real-time listener for all users (filter client-side)
    console.log('Setting up real-time listener...');
    const qAll = query(
      collection(db, 'users'),
      orderBy('registrationDate', 'desc'),
      limit(500)
    );
    onSnapshot(qAll, (snap) => {
      console.log('Real-time update received, processing changes...');
      snap.docChanges().forEach(change => {
        const id = change.doc.id;
        const data = change.doc.data();
        const status = (data.accountStatus ?? data.status ?? data.Status ?? data.AccountStatus ?? 'pending').toLowerCase();
        const isPending = status === 'pending';
        
        console.log(`Processing ${change.type} for user ${id}, status: ${status}, isPending: ${isPending}`);
        
        if (change.type === 'added') {
          if (isPending) {
            console.log('Adding new pending user:', id);
            upsertCard(normalizeUser(id, data));
          }
        } else if (change.type === 'modified') {
          if (isPending) {
            console.log('Updating pending user:', id);
            upsertCard(normalizeUser(id, data));
          } else {
            console.log('Removing non-pending user:', id);
            removeCard(id);
          }
        } else if (change.type === 'removed') {
          console.log('Removing deleted user:', id);
          removeCard(id);
        }
      });
      // Update pending count after processing all changes
      updatePendingCount();
    }, (err) => {
      console.warn('Realtime listener error:', err);
      toast('Realtime listener error', 'error');
    });

    // Start initial load
    loadPending();

  </script>
</body>
</html>