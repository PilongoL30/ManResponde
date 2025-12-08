# Phase 2 Architecture - Quick Reference Guide

## 🎯 Overview
Phase 2 transformed the 11,190-line monolithic dashboard into a clean MVC architecture with 97% reduction in entry point size.

## 📁 File Organization

### Services (Business Logic)
```
services/
├── ReportService.php       - Report operations
├── UserService.php         - User management
├── NotificationService.php - FCM notifications
└── StatisticsService.php   - Analytics & stats
```

### Controllers (Request Handling)
```
controllers/
├── DashboardController.php - View rendering
├── ReportController.php    - Report AJAX actions
└── UserController.php      - User AJAX actions
```

### Views (Templates)
```
views/
├── dashboard_home.php      - Admin dashboard
├── staff_dashboard.php     - Staff dashboard
├── analytics.php           - Analytics page
├── interactive_map.php     - Map view
├── live_support.php        - Chat view
├── create_account.php      - Account creation
└── verify_users.php        - User verification
```

### Core Files
```
includes/
├── Router.php              - URL routing & middleware
├── View.php                - Template rendering
├── csrf.php                - CSRF protection
└── cache.php               - Caching layer

dashboard_v2.php            - New entry point (302 lines)
dashboard.php               - Legacy (11,190 lines)
```

## 🚀 How to Use

### 1. Testing the New Architecture

**Access the new dashboard:**
```
http://localhost/ManResponde/dashboard_v2.php
```

**Compare with old dashboard:**
```
http://localhost/ManResponde/dashboard.php
```

### 2. Using Services in Code

```php
// In a controller or script
require_once 'services/ReportService.php';

$categories = [...]; // Your category array
$reportService = new ReportService($categories);

// Get latest reports with caching
$reports = $reportService->getLatestReports('fire_reports', 20);

// Get dashboard statistics
$stats = $reportService->getDashboardStats();

// Search reports
$results = $reportService->searchReports('fire', ['fire', 'medical']);
```

### 3. Creating a Controller Action

```php
// In controllers/ReportController.php

public function myNewAction(): void {
    // Get parameters
    $param = $_POST['param'] ?? null;
    
    // Validate
    if (!$param) {
        View::json(['error' => 'Parameter required'], 400);
        return;
    }
    
    // Use service
    try {
        $result = $this->reportService->someMethod($param);
        View::json(['success' => true, 'data' => $result]);
    } catch (Exception $e) {
        View::json(['error' => $e->getMessage()], 500);
    }
}
```

### 4. Rendering a View

```php
// In DashboardController.php

public function myView(): void {
    // Prepare data
    $data = [
        'title' => 'My Page',
        'items' => $this->reportService->getLatestReports('tanod_reports', 10)
    ];
    
    // Share global data (available to all views)
    View::share('categories', $this->categories);
    View::share('userRole', $this->userRole);
    
    // Render view
    echo View::render('my_view', $data);
}
```

### 5. Creating a View File

```php
<!-- views/my_view.php -->
<div class="container">
    <h1><?php e($title); ?></h1>
    
    <div class="grid gap-4">
        <?php foreach ($items as $item): ?>
            <div class="card">
                <h2><?php e($item['title']); ?></h2>
                <p><?php e($item['description']); ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</div>
```

### 6. AJAX Request Example

```javascript
// From the frontend
const formData = createFormDataWithCsrf(); // Auto-includes CSRF token
formData.append('action', 'update_report_status');
formData.append('collection', 'fire_reports');
formData.append('docId', reportId);
formData.append('newStatus', 'approved');

fetch('dashboard_v2.php', {
    method: 'POST',
    body: formData
})
.then(response => response.json())
.then(data => {
    if (data.success) {
        console.log('Success:', data);
    } else {
        console.error('Error:', data.error);
    }
});
```

## 🔄 Migration Steps

### Step 1: Test New Dashboard
1. Access `dashboard_v2.php`
2. Test all features:
   - Login/logout
   - Dashboard views
   - Report operations
   - User management
   - Analytics

### Step 2: Update Links
Update any hardcoded links:
```php
<!-- Old -->
<a href="dashboard.php?view=analytics">Analytics</a>

<!-- New (same, works with both) -->
<a href="dashboard_v2.php?view=analytics">Analytics</a>
```

### Step 3: Switch Over
When ready to go live:
```bash
# Backup old version
cp dashboard.php dashboard_legacy.php

# Activate new version
mv dashboard_v2.php dashboard.php
```

### Step 4: Update External Links
Update any external references:
- Email templates
- Mobile app links
- Bookmarks
- Documentation

## 🎨 Common Patterns

### Pattern 1: Service → Controller → View
```php
// 1. Service (business logic)
class ReportService {
    public function getActiveReports() {
        return cache_remember('active_reports', 60, function() {
            return $this->getLatestReports('tanod_reports', 50);
        });
    }
}

// 2. Controller (orchestration)
class ReportController {
    public function getActive() {
        $reports = $this->reportService->getActiveReports();
        View::json(['success' => true, 'reports' => $reports]);
    }
}

// 3. Frontend (AJAX call)
fetch('dashboard_v2.php?action=get_active_reports')
    .then(r => r.json())
    .then(data => console.log(data.reports));
```

### Pattern 2: Caching in Services
```php
public function expensiveOperation($param) {
    $cacheKey = "expensive_{$param}";
    
    return cache_remember($cacheKey, 300, function() use ($param) {
        // Expensive database/API call
        $result = $this->doExpensiveWork($param);
        return $result;
    });
}
```

### Pattern 3: Error Handling
```php
try {
    $result = $this->service->doSomething();
    View::json(['success' => true, 'data' => $result]);
} catch (Exception $e) {
    // Debug mode shows detailed error
    // Production mode shows generic error
    View::json([
        'success' => false,
        'error' => DEBUG_MODE ? $e->getMessage() : 'An error occurred'
    ], 500);
}
```

## 🛠️ Customization

### Add a New Emergency Category
```php
// In dashboard_v2.php (or dashboard.php)
$categories = [
    // ... existing categories ...
    'flood' => [
        'collection' => 'flood_reports',
        'label' => 'Flood Emergency',
        'icon' => 'water',
        'color' => 'cyan',
        'canVerify' => false
    ]
];
```

### Add a New View
```php
// 1. Create view file: views/my_feature.php
<div>My Feature Content</div>

// 2. Add controller method in DashboardController.php
public function myFeature() {
    echo View::render('my_feature', ['data' => 'value']);
}

// 3. Add route in dashboard_v2.php
case 'my-feature':
    $controller->myFeature();
    break;

// 4. Add sidebar link in includes/sidebar.php
<a href="?view=my-feature">My Feature</a>
```

### Add a New AJAX Action
```php
// 1. Add method to appropriate controller
// In controllers/ReportController.php
public function myAction() {
    $param = $_POST['param'] ?? null;
    // Process...
    View::json(['success' => true]);
}

// 2. Add route in dashboard_v2.php
case 'my_action':
    $controller = new ReportController($categories);
    $controller->myAction();
    break;

// 3. Call from frontend
const formData = createFormDataWithCsrf();
formData.append('action', 'my_action');
formData.append('param', 'value');

fetch('dashboard_v2.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => console.log(data));
```

## ⚡ Performance Tips

1. **Use Caching**: Always cache expensive operations
   ```php
   $data = cache_remember('key', 60, function() {
       return expensiveOperation();
   });
   ```

2. **Limit Query Results**: Don't fetch more than needed
   ```php
   $reports = $reportService->getLatestReports('tanod_reports', 20); // Not 1000
   ```

3. **Invalidate Cache**: Clear cache when data changes
   ```php
   public function updateReport($id, $data) {
       $result = $this->doUpdate($id, $data);
       cache_delete('reports_list'); // Invalidate cache
       return $result;
   }
   ```

## 🔒 Security Checklist

- ✅ CSRF protection on all POST requests (automatic)
- ✅ HTML escaping in views (use `View::e()` or `e()`)
- ✅ Admin-only view protection (controller checks)
- ✅ Session validation (automatic)
- ✅ SQL injection protection (Firestore SDK handles this)

## 📊 Monitoring

### Cache Statistics
```javascript
fetch('dashboard_v2.php?action=cache_stats')
    .then(r => r.json())
    .then(stats => console.log('Cache Stats:', stats));
```

### Pending Users Count
```javascript
fetch('dashboard_v2.php?action=get_pending_count')
    .then(r => r.json())
    .then(data => console.log('Pending:', data.count));
```

## 🐛 Debugging

### Enable Debug Mode
```php
// In config.php
define('DEBUG_MODE', true);
```

### Check Error Logs
```php
// Errors logged to PHP error log
if (DEBUG_MODE) {
    error_log("Debug message: " . print_r($data, true));
}
```

### View Cache Contents
```php
// Get cache statistics
$stats = cache_stats();
print_r($stats);

// Clear all cache
cache_clear();
```

## 📚 Additional Resources

- **PHASE2_COMPLETE.md**: Comprehensive implementation documentation
- **PHASE1_COMPLETE.md**: Security & performance improvements
- **CSRF_FIX.md**: CSRF protection details
- **QUICK_REFERENCE.md**: Phase 1 quick reference

## 🎉 Summary

**Before**: 11,190-line monolithic file
**After**: Clean MVC architecture with:
- 4 service classes (779 lines)
- 3 controllers (469 lines)
- Router & view helper (430 lines)
- Entry point (302 lines)

**Result**: 97% reduction in entry point size, vastly improved maintainability!
