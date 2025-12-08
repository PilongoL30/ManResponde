# ManResponde Improvement Plan

## Phase 1: Refactoring & Modularization (Completed)
- [x] Create `includes/` directory.
- [x] Create `assets/js/` directory.
- [x] Extract Modals to `includes/modals.php`.
- [x] Extract JavaScript to `assets/js/dashboard-core.js`.
- [x] Update `dashboard.php` to include the new files.
- [x] Verify `dashboard.php` integrity.

## Phase 2: Advanced Analytics (Completed)
- [x] Create `includes/analytics_view.php`.
- [x] Add Chart.js library (CDN or local).
- [x] Implement "Reports by Category" (Pie Chart).
- [x] Implement "Response Time" (Bar Chart).
- [x] Implement "Heatmap Data" preparation.
- [x] Integrate into `dashboard.php` as `?view=analytics`.

## Phase 3: Interactive Map (Completed)
- [x] Create `includes/map_view.php`.
- [x] Implement full-screen Leaflet map.
- [x] Fetch all active reports and plot markers.
- [x] Add real-time updates to markers.
- [x] Integrate into `dashboard.php` as `?view=map`.

## Phase 4: Real-time Chat & Notifications (Completed)
- [x] Enhance `notification_system.php`.
- [x] Create `includes/live_support_view.php`.
- [x] Create `assets/js/chat-system.js`.
- [x] Implement chat logic using `api/support_chat.php`.

## Phase 5: UI/UX Polish (Next)
- [ ] Standardize Tailwind classes.
- [ ] Add loading skeletons.
- [ ] Improve mobile responsiveness.
