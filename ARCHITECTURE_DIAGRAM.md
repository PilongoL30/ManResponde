# Phase 2 Architecture Diagram

## Before Phase 2: Monolithic Architecture

```
┌─────────────────────────────────────────────────────────┐
│                                                         │
│                   dashboard.php                         │
│                   (11,190 lines)                        │
│                                                         │
│  ┌────────────────────────────────────────────────┐   │
│  │ • Authentication logic                          │   │
│  │ • Session management                            │   │
│  │ • CSRF protection                               │   │
│  │ • Database operations                           │   │
│  │ • Report business logic                         │   │
│  │ • User management                               │   │
│  │ • Statistics calculations                       │   │
│  │ • Notification sending                          │   │
│  │ • View rendering (HTML)                         │   │
│  │ • AJAX handlers                                 │   │
│  │ • Routing logic                                 │   │
│  │ • Everything mixed together!                    │   │
│  └────────────────────────────────────────────────┘   │
│                                                         │
└─────────────────────────────────────────────────────────┘
                            │
                            ▼
                ❌ Hard to maintain
                ❌ Hard to test
                ❌ Hard to scale
                ❌ Code duplication
                ❌ No separation of concerns
```

## After Phase 2: MVC Architecture with Services

```
┌─────────────────────────────────────────────────────────────────────┐
│                        dashboard_v2.php                             │
│                        (302 lines - 97% reduction!)                 │
│                                                                     │
│  ┌─────────────────────────────────────────────────────────────┐  │
│  │ • Bootstrap & Configuration                                  │  │
│  │ • Authentication Check                                       │  │
│  │ • CSRF Protection                                            │  │
│  │ • Route Dispatcher                                           │  │
│  │ • Minimal HTML template                                      │  │
│  └─────────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────┘
                                   │
                    ┌──────────────┼──────────────┐
                    ▼              ▼              ▼
        ┌───────────────┐ ┌──────────────┐ ┌──────────────┐
        │   Routing     │ │ Controllers  │ │    Views     │
        └───────────────┘ └──────────────┘ └──────────────┘
```

### Detailed Layer Breakdown

```
┌─────────────────────────────────────────────────────────────────────┐
│                         PRESENTATION LAYER                          │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  ┌──────────────────────┐  ┌──────────────────────┐               │
│  │   includes/View.php  │  │   views/*.php        │               │
│  │   (174 lines)        │  │   (7 templates)      │               │
│  ├──────────────────────┤  ├──────────────────────┤               │
│  │ • render()           │  │ • dashboard_home     │               │
│  │ • renderWithLayout() │  │ • staff_dashboard    │               │
│  │ • json()             │  │ • analytics          │               │
│  │ • e() - XSS protect  │  │ • interactive_map    │               │
│  │ • partial()          │  │ • live_support       │               │
│  │ • asset()            │  │ • create_account     │               │
│  │ • url()              │  │ • verify_users       │               │
│  └──────────────────────┘  └──────────────────────┘               │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
                                   ▲
                                   │
┌─────────────────────────────────────────────────────────────────────┐
│                         CONTROLLER LAYER                            │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  ┌──────────────────────────────────────────────────────────────┐ │
│  │  DashboardController.php (145 lines)                          │ │
│  ├──────────────────────────────────────────────────────────────┤ │
│  │  • index() - Main dashboard                                   │ │
│  │  • analytics() - Analytics view                               │ │
│  │  • map() - Map view                                           │ │
│  │  • liveSupport() - Chat view                                  │ │
│  │  • createAccount() - Account creation                         │ │
│  │  • verifyUsers() - User verification                          │ │
│  │  • getPendingCount() - AJAX endpoint                          │ │
│  │  • getCacheStats() - AJAX endpoint                            │ │
│  └──────────────────────────────────────────────────────────────┘ │
│                                                                     │
│  ┌──────────────────────────────────────────────────────────────┐ │
│  │  ReportController.php (168 lines)                             │ │
│  ├──────────────────────────────────────────────────────────────┤ │
│  │  • getRecentFeed() - Recent reports                           │ │
│  │  • getByCategory() - Category reports                         │ │
│  │  • updateStatus() - Update report                             │ │
│  │  • getReportById() - Get single report                        │ │
│  │  • getStats() - Report statistics                             │ │
│  │  • search() - Search reports                                  │ │
│  └──────────────────────────────────────────────────────────────┘ │
│                                                                     │
│  ┌──────────────────────────────────────────────────────────────┐ │
│  │  UserController.php (156 lines)                               │ │
│  ├──────────────────────────────────────────────────────────────┤ │
│  │  • getAllUsers() - Get all users                              │ │
│  │  • getPendingUsers() - Get pending                            │ │
│  │  • getVerifiedUsers() - Get verified                          │ │
│  │  • verifyUser() - Verify/unverify                             │ │
│  │  • deleteUser() - Delete user                                 │ │
│  │  • updateProfile() - Update user                              │ │
│  │  • getStats() - User statistics                               │ │
│  └──────────────────────────────────────────────────────────────┘ │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
                                   ▲
                                   │
┌─────────────────────────────────────────────────────────────────────┐
│                          SERVICE LAYER                              │
│                        (Business Logic)                             │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  ┌──────────────────────────────────────────────────────────────┐ │
│  │  ReportService.php (175 lines)                                │ │
│  ├──────────────────────────────────────────────────────────────┤ │
│  │  • getLatestReports() - Fetch reports with caching            │ │
│  │  • getRecentFeed() - Activity feed                            │ │
│  │  • updateReportStatus() - Update status                       │ │
│  │  • getReportById() - Get single report                        │ │
│  │  • countReports() - Count by status                           │ │
│  │  • getDashboardStats() - Statistics                           │ │
│  │  • searchReports() - Search across collections                │ │
│  └──────────────────────────────────────────────────────────────┘ │
│                                                                     │
│  ┌──────────────────────────────────────────────────────────────┐ │
│  │  UserService.php (212 lines)                                  │ │
│  ├──────────────────────────────────────────────────────────────┤ │
│  │  • getAllUsers() - Fetch all users                            │ │
│  │  • getUserByUid() - Get single user                           │ │
│  │  • setUserVerificationStatus() - Verify/unverify              │ │
│  │  • getPendingUsersCount() - Count pending                     │ │
│  │  • getPendingUsers() - List pending                           │ │
│  │  • getVerifiedUsers() - List verified                         │ │
│  │  • updateUserProfile() - Update user                          │ │
│  │  • deleteUser() - Delete user                                 │ │
│  │  • getUserStats() - User statistics                           │ │
│  └──────────────────────────────────────────────────────────────┘ │
│                                                                     │
│  ┌──────────────────────────────────────────────────────────────┐ │
│  │  NotificationService.php (158 lines)                          │ │
│  ├──────────────────────────────────────────────────────────────┤ │
│  │  • sendNotification() - Send FCM to user                      │ │
│  │  • sendBulkNotification() - Send to multiple                  │ │
│  │  • sendTopicNotification() - Send to topic                    │ │
│  │  • notifyReportStatusChange() - Auto-notify                   │ │
│  │  • notifyNearbyUsers() - Location-based                       │ │
│  │  • getNotificationHistory() - Fetch history                   │ │
│  │  • logNotification() - Save to Firestore                      │ │
│  └──────────────────────────────────────────────────────────────┘ │
│                                                                     │
│  ┌──────────────────────────────────────────────────────────────┐ │
│  │  StatisticsService.php (234 lines)                            │ │
│  ├──────────────────────────────────────────────────────────────┤ │
│  │  • getDashboardStats() - Comprehensive stats                  │ │
│  │  • getReportStats() - Report statistics                       │ │
│  │  • getUserStats() - User statistics                           │ │
│  │  • getActivityStats() - Activity over time                    │ │
│  │  • getResponseStats() - Response time metrics                 │ │
│  │  • getChartData() - Chart data for visualizations             │ │
│  └──────────────────────────────────────────────────────────────┘ │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
                                   ▲
                                   │
┌─────────────────────────────────────────────────────────────────────┐
│                         DATA ACCESS LAYER                           │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  ┌──────────────────────────────────────────────────────────────┐ │
│  │  Firebase Firestore                                           │ │
│  ├──────────────────────────────────────────────────────────────┤ │
│  │  • tanod_reports collection                                   │ │
│  │  • fire_reports collection                                    │ │
│  │  • medical_reports collection                                 │ │
│  │  • traffic_reports collection                                 │ │
│  │  • disaster_reports collection                                │ │
│  │  • crime_reports collection                                   │ │
│  │  • notifications collection                                   │ │
│  └──────────────────────────────────────────────────────────────┘ │
│                                                                     │
│  ┌──────────────────────────────────────────────────────────────┐ │
│  │  Firebase Authentication                                      │ │
│  ├──────────────────────────────────────────────────────────────┤ │
│  │  • User accounts                                              │ │
│  │  • Custom claims (isVerified, role)                           │ │
│  │  • Email/password auth                                        │ │
│  └──────────────────────────────────────────────────────────────┘ │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

## Request Flow Diagram

```
┌──────────────┐
│   Browser    │
│              │
│  User clicks │
│  "Analytics" │
└──────┬───────┘
       │
       │ GET dashboard_v2.php?view=analytics
       │
       ▼
┌──────────────────────────────────────────┐
│         dashboard_v2.php                 │
│                                          │
│  1. Session check ✓                     │
│  2. CSRF validation (if POST)           │
│  3. Route parsing                       │
└──────┬───────────────────────────────────┘
       │
       │ view = "analytics"
       │
       ▼
┌──────────────────────────────────────────┐
│      DashboardController                 │
│                                          │
│  analytics() method called               │
└──────┬───────────────────────────────────┘
       │
       │ Needs data
       │
       ▼
┌──────────────────────────────────────────┐
│      StatisticsService                   │
│                                          │
│  getDashboardStats() called              │
│  ├─ Check cache first                   │
│  ├─ Cache miss? Query Firestore         │
│  └─ Return data                          │
└──────┬───────────────────────────────────┘
       │
       │ Data returned
       │
       ▼
┌──────────────────────────────────────────┐
│      DashboardController                 │
│                                          │
│  Receives data from service              │
└──────┬───────────────────────────────────┘
       │
       │ Render view with data
       │
       ▼
┌──────────────────────────────────────────┐
│      View Helper                         │
│                                          │
│  View::render('analytics', $data)        │
│  ├─ Load views/analytics.php            │
│  ├─ Extract data variables              │
│  └─ Execute PHP template                │
└──────┬───────────────────────────────────┘
       │
       │ HTML output
       │
       ▼
┌──────────────┐
│   Browser    │
│              │
│  Analytics   │
│  page shown  │
└──────────────┘
```

## AJAX Request Flow

```
┌──────────────┐
│   Browser    │
│              │
│  JavaScript  │
│  AJAX call   │
└──────┬───────┘
       │
       │ POST dashboard_v2.php
       │ action=update_report_status
       │ + CSRF token (auto-injected)
       │
       ▼
┌──────────────────────────────────────────┐
│         dashboard_v2.php                 │
│                                          │
│  1. CSRF validation ✓                   │
│  2. Parse action parameter              │
└──────┬───────────────────────────────────┘
       │
       │ action = "update_report_status"
       │
       ▼
┌──────────────────────────────────────────┐
│      ReportController                    │
│                                          │
│  updateStatus() method called            │
│  ├─ Validate parameters                 │
│  ├─ Call service                         │
│  └─ Return JSON                          │
└──────┬───────────────────────────────────┘
       │
       │ Call service
       │
       ▼
┌──────────────────────────────────────────┐
│      ReportService                       │
│                                          │
│  updateReportStatus() called             │
│  ├─ Update Firestore document           │
│  ├─ Invalidate cache                    │
│  └─ Return success/failure               │
└──────┬───────────────────────────────────┘
       │
       │ Result returned
       │
       ▼
┌──────────────────────────────────────────┐
│      ReportController                    │
│                                          │
│  Receives result from service            │
│  View::json(['success' => true])         │
└──────┬───────────────────────────────────┘
       │
       │ JSON response
       │
       ▼
┌──────────────┐
│   Browser    │
│              │
│  JavaScript  │
│  .then()     │
│  handles it  │
└──────────────┘
```

## Benefits of New Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                        SEPARATION                               │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  Business Logic (Services)                                     │
│  ✓ Reusable across controllers                                │
│  ✓ Testable independently                                     │
│  ✓ No HTML/presentation code                                  │
│                                                                 │
│  Request Handling (Controllers)                                │
│  ✓ Thin layer between routes and services                     │
│  ✓ Validation and orchestration only                          │
│  ✓ No database queries directly                               │
│                                                                 │
│  Presentation (Views)                                          │
│  ✓ Pure HTML/PHP templates                                    │
│  ✓ No business logic                                          │
│  ✓ XSS protection via View::e()                               │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                        MAINTAINABILITY                          │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  Before: Find code in 11,190 lines ❌                          │
│  After:  Know exactly which file to edit ✓                    │
│                                                                 │
│  Before: Change breaks multiple features ❌                    │
│  After:  Changes isolated to one service ✓                    │
│                                                                 │
│  Before: Code duplication everywhere ❌                        │
│  After:  DRY - reusable components ✓                          │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                        SCALABILITY                              │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  Add new feature:                                              │
│  1. Add method to service                                      │
│  2. Add controller action                                      │
│  3. Create view (if needed)                                    │
│  4. Done! ✓                                                    │
│                                                                 │
│  Services can be used by:                                      │
│  • Web dashboard                                               │
│  • REST API                                                    │
│  • CLI scripts                                                 │
│  • Cron jobs                                                   │
│  • Queue workers                                               │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

## File Size Comparison

```
Before Phase 2:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ 11,190 lines
dashboard.php (everything mixed)


After Phase 2:
━━━ 302 lines (Entry point - dashboard_v2.php)
━━━━ 469 lines (Controllers)
━━━━━ 779 lines (Services)
━━━ 430 lines (Router + View helper)
━ 219 lines (New views)

Total organized code: ~2,199 lines
Entry point: 97% smaller!
```

## Summary

**Old Way**: One massive 11,190-line file
**New Way**: Clean MVC with 14+ modular components

✅ Easier to maintain
✅ Easier to test
✅ Easier to scale
✅ Better performance (caching)
✅ Better security (separation)
✅ Better code quality (SOLID principles)
