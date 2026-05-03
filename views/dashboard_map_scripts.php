    import { initializeApp } from "https://www.gstatic.com/firebasejs/10.12.5/firebase-app.js";
    import { getFirestore, doc, onSnapshot, collection, query, where, orderBy, limit } from "https://www.gstatic.com/firebasejs/10.12.5/firebase-firestore.js";

    if (window.FIREBASE_CLIENT_CONFIG && window.FIREBASE_CLIENT_CONFIG.projectId) {
        const app = initializeApp(window.FIREBASE_CLIENT_CONFIG);
        const db = getFirestore(app);
        
        // ============ LISTEN FOR NEW REPORTS (Realtime) ============
        const reportCollections = ['ambulance_reports', 'fire_reports', 'police_reports', 'tanod_reports', 'flood_reports', 'other_reports'];
        let isInitialLoad = true;
        
        // Wait a moment for initial page load before enabling notifications
        setTimeout(() => { isInitialLoad = false; }, 3000);
        
        reportCollections.forEach(collName => {
            try {
                const q = query(
                    collection(db, collName),
                    orderBy('timestamp', 'desc'),
                    limit(1)
                );
                
                onSnapshot(q, (snapshot) => {
                    snapshot.docChanges().forEach(change => {
                        if (change.type === 'added' && !isInitialLoad) {
                            const data = change.doc.data();
                            const st = String(data?.status || '').toLowerCase();
                            
                            // Only notify for pending reports
                            if (st && st !== 'pending') return;
                            
                            // Only notify if report is recent (last 5 minutes)
                            const reportTime = data.timestamp?.seconds ? (data.timestamp.seconds * 1000) : Date.now();
                            if (Date.now() - reportTime > 5 * 60 * 1000) return;
                            
                            console.log('[Firebase Realtime] 🆕 New report detected in:', collName);
                            
                            // Show notification
                            if (typeof window.showNotificationWithSound === 'function') {
                                window.showNotificationWithSound(`New ${collName.replace('_reports', '')} report received!`, 'info', 'siren');
                            }
                            
                            // Refresh the activity feed with a small delay to ensure DOM is ready
                            setTimeout(() => {
                                console.log('[Firebase Realtime] 🔄 Refreshing activity feed...');
                                if (typeof window.loadRecentPage === 'function') {
                                    window.forceRecentFeedRefresh = true;
                                    window.loadRecentPage(1);
                                    console.log('[Firebase Realtime] ✅ Called loadRecentPage(1)');
                                } else if (typeof window.refreshRecentActivity === 'function') {
                                    window.forceRecentFeedRefresh = true;
                                    window.refreshRecentActivity();
                                    console.log('[Firebase Realtime] ✅ Called refreshRecentActivity()');
                                } else {
                                    console.warn('[Firebase Realtime] ⚠️ No refresh function available, reloading activityList...');
                                    // Fallback: manually reload via AJAX
                                    const list = document.getElementById('activityList');
                                    if (list) {
                                        const fd = new FormData();
                                        fd.append('recent_feed', '1');
                                        fd.append('page', '1');
                                        fd.append('pageSize', '10');
                                        fd.append('category', 'all');
                                        fd.append('status', 'all');
                                        fd.append('force_refresh', 'true');
                                        fetch(window.location.href, { method: 'POST', body: fd })
                                            .then(r => r.json())
                                            .then(data => {
                                                if (data.html) {
                                                    list.innerHTML = data.html;
                                                    console.log('[Firebase Realtime] ✅ Activity list refreshed via fallback');
                                                }
                                            })
                                            .catch(e => console.error('[Firebase Realtime] Fallback refresh failed:', e));
                                    }
                                }
                            }, 100);
                        }
                    });
                }, (error) => {
                    console.log(`[Firebase Realtime] Listener error for ${collName}:`, error.message);
                });
                
                console.log('[Firebase Realtime] ✅ Listening to:', collName);
            } catch (e) {
                console.error(`[Firebase Realtime] Failed to setup listener for ${collName}:`, e);
            }
        });
        
        console.log('[Firebase Realtime] ✅ All report listeners initialized');
        
        // ============ WATCH EXISTING ITEMS FOR STATUS CHANGES ============
        const list = document.getElementById('activityList');
        if (list) {
            const attached = new Set();
            const watch = (li) => {
                const coll = li.dataset.collection;
                const id = li.dataset.id;
                if (!coll || !id) return;
                const key = `${coll}/${id}`;
                if (attached.has(key)) return;
                attached.add(key);
                try {
                    onSnapshot(doc(db, coll, id), (snap) => {
                        const data = snap.data();
                        if (!data || typeof window.updateActivityItemStatus !== 'function') return;
                        const status = typeof data.status === 'string' ? data.status : 'Pending';
                        window.updateActivityItemStatus(id, status);
                    });
                } catch(e) { console.error(`Failed to watch document ${key}:`, e); }
            };
            Array.from(list.querySelectorAll('li[data-id]')).forEach(watch);
            const mo = new MutationObserver((mutations) => {
                mutations.forEach(mutation => {
                    if (mutation.addedNodes.length) {
                        Array.from(list.querySelectorAll('li[data-id]')).forEach(watch);
                    }
                });
            });
            mo.observe(list, { childList: true });
        }
    }

    // Notification System
    const notificationBell = document.getElementById('notificationBell');
    const notificationDropdown = document.getElementById('notificationDropdown');
    const notificationBadge = document.getElementById('notificationBadge');
    const notificationList = document.getElementById('notificationList');
    const markAllRead = document.getElementById('markAllRead');

    if (notificationBell) {
        // Toggle notification dropdown
        notificationBell.addEventListener('click', (e) => {
            e.stopPropagation();
            notificationDropdown.classList.toggle('hidden');
            if (!notificationDropdown.classList.contains('hidden')) {
                loadNotifications();
            }
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!notificationBell.contains(e.target) && !notificationDropdown.contains(e.target)) {
                notificationDropdown.classList.add('hidden');
            }
        });

        // Load notification count on page load
        loadNotificationCount();
        
        // Refresh notification count every 30 seconds
        setInterval(() => {
            if (document.hidden) return;
            loadNotificationCount();
        }, 30000);
        
        // Real-time notification updates
        if (window.FIREBASE_CLIENT_CONFIG && window.FIREBASE_CLIENT_CONFIG.projectId) {
            setupRealtimeNotifications();
        }
    }

    // Setup real-time notifications
    function setupRealtimeNotifications() {
        const app = initializeApp(window.FIREBASE_CLIENT_CONFIG);
        const db = getFirestore(app);
        
        // Define report collections to watch
        const reportCollections = [
            'ambulance_reports',
            'fire_reports', 
            'flood_reports',
            'other_reports',
            'tanod_reports'
        ];
        
        // Watch each collection for new documents
        reportCollections.forEach(colName => {
            try {
                // Use collection query to listen for new documents
                const q = query(
                    collection(db, colName),
                    where('status', '==', 'Pending'),
                    orderBy('timestamp', 'desc'),
                    limit(1)
                );
                
                const unsubscribe = onSnapshot(q, (snapshot) => {
                    snapshot.docChanges().forEach((change) => {
                        if (change.type === 'added') {
                            const data = change.doc.data();
                            // Check if this is a recent document (within last 5 minutes)
                            const timestamp = data.timestamp;
                            const now = new Date();
                            let docTime = new Date();
                            
                            // Properly handle Firebase timestamp
                            try {
                                if (timestamp && typeof timestamp.toDate === 'function') {
                                    docTime = timestamp.toDate();
                                } else if (timestamp && timestamp.seconds) {
                                    docTime = new Date(timestamp.seconds * 1000);
                                } else if (timestamp) {
                                    docTime = new Date(timestamp);
                                }
                            } catch (error) {
                                console.warn('Error parsing timestamp:', timestamp, error);
                            }
                            
                            const timeDiff = now - docTime;
                            
                            // Only create notification for documents created in the last 5 minutes
                            if (timeDiff < 5 * 60 * 1000) {
                                createNotificationForNewReport(colName, change.doc.id, data);
                            }
                        }
                    });
                });
                
                // Store unsubscribe function for cleanup
                if (!window.notificationUnsubscribers) {
                    window.notificationUnsubscribers = [];
                }
                window.notificationUnsubscribers.push(unsubscribe);
                
            } catch (error) {
                console.error(`Error setting up real-time listener for ${colName}:`, error);
            }
        });
    }

    // Create notification for new report
    function createNotificationForNewReport(collection, reportId, reportData) {
        const formData = new FormData();
        formData.append('api_action', 'create_notification_for_report');
        formData.append('collection', collection);
        formData.append('reportId', reportId);
        
        // Normalize the report data before sending
        const normalizedData = normalizeFirebaseReportData(reportData);
        formData.append('reportData', JSON.stringify(normalizedData));
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update notification count and list
                loadNotificationCount();
                if (!notificationDropdown.classList.contains('hidden')) {
                    loadNotifications();
                }
                
                // Show toast notification
                showToast(`New ${getCollectionLabel(collection)} report received!`, 'info');
            }
        })
        .catch(error => console.error('Error creating notification:', error));
    }

    // Get collection label
    function getCollectionLabel(collection) {
        const labels = {
            'ambulance_reports': '🚑 Ambulance',
            'fire_reports': '🔥 Fire',
            'flood_reports': '🌊 Flood',
            'other_reports': '📋 Other',
            'tanod_reports': '👮 Tanod'
        };
        return labels[collection] || '📋 Report';
    }

    function loadNotificationCount() {
        const formData = new FormData();
        formData.append('api_action', 'get_notification_count');
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const count = data.count;
                if (count > 0) {
                    notificationBadge.textContent = count;
                    notificationBadge.classList.remove('hidden');
                    // Add pulsing animation for urgent notifications
                    notificationBell.classList.add('animate-pulse');
                } else {
                    notificationBadge.classList.add('hidden');
                    notificationBell.classList.remove('animate-pulse');
                }
            }
        })
        .catch(error => console.error('Error loading notification count:', error));
    }

    function loadNotifications() {
        const formData = new FormData();
        formData.append('api_action', 'get_notifications');
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayNotifications(data.notifications);
            }
        })
        .catch(error => console.error('Error loading notifications:', error));
    }

    function displayNotifications(notifications) {
        if (notifications.length === 0) {
            notificationList.innerHTML = '<div class="p-4 text-center text-slate-500">No new notifications</div>';
            return;
        }

        const html = notifications.map(notification => {
            // Get status from notification data
            const status = notification.data?.status || 'Pending';
            let statusClass = 'bg-red-100 text-red-600';
            let statusIcon = '🚨';
            
            if (status === 'Approved') {
                statusClass = 'bg-green-100 text-green-600';
                statusIcon = '✅';
            } else if (status === 'Declined') {
                statusClass = 'bg-orange-100 text-orange-600';
                statusIcon = '❌';
            }
            
            return `
                <div class="p-4 border-b border-slate-100 hover:bg-slate-50 transition-colors cursor-pointer" 
                     onclick="markNotificationRead('${notification._id}')">
                    <div class="flex items-start gap-3">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 ${statusClass} rounded-full flex items-center justify-center">
                                <span class="text-sm">${statusIcon}</span>
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <p class="text-sm font-semibold text-slate-800">${notification.title}</p>
                                <span class="text-xs px-2 py-1 rounded-full ${statusClass}">${status}</span>
                            </div>
                            <p class="text-xs text-slate-600 mt-1">${notification.message}</p>
                            <p class="text-xs text-slate-400 mt-2">${formatNotificationTime(notification.timestamp)}</p>
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        notificationList.innerHTML = html;
    }

    function markNotificationRead(notificationId) {
        const formData = new FormData();
        formData.append('api_action', 'mark_notification_read');
        formData.append('notification_id', notificationId);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadNotificationCount();
                loadNotifications();
            }
        })
        .catch(error => console.error('Error marking notification as read:', error));
    }

    function formatNotificationTime(timestamp) {
        const now = new Date();
        const time = new Date(timestamp);
        const diff = now - time;
        
        if (diff < 60000) return 'Just now';
        if (diff < 3600000) return Math.floor(diff / 60000) + ' minutes ago';
        if (diff < 86400000) return Math.floor(diff / 3600000) + ' hours ago';
        return Math.floor(diff / 86400000) + ' days ago';
    }

    if (markAllRead) {
        markAllRead.addEventListener('click', () => {
            // Mark all notifications as read
            const notifications = notificationList.querySelectorAll('[onclick*="markNotificationRead"]');
            notifications.forEach(notification => {
                const notificationId = notification.getAttribute('onclick').match(/'([^']+)'/)[1];
                markNotificationRead(notificationId);
            });
        });
    }

    // Mobile menu functionality
    document.addEventListener('DOMContentLoaded', function() {
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const mobileMenuOverlay = document.getElementById('mobileMenuOverlay');
        
        if (mobileMenuBtn && mobileMenuOverlay) {
            // Toggle mobile menu
            mobileMenuBtn.addEventListener('click', function() {
                mobileMenuOverlay.classList.toggle('hidden');
            });
            
            // Close mobile menu when clicking overlay
            mobileMenuOverlay.addEventListener('click', function(e) {
                if (e.target === mobileMenuOverlay) {
                    mobileMenuOverlay.classList.add('hidden');
                }
            });
            
            // Close mobile menu when clicking any navigation link
            const mobileNavLinks = mobileMenuOverlay.querySelectorAll('a');
            mobileNavLinks.forEach(link => {
                link.addEventListener('click', function() {
                    mobileMenuOverlay.classList.add('hidden');
                });
            });
            
            // Handle escape key to close menu
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && !mobileMenuOverlay.classList.contains('hidden')) {
                    mobileMenuOverlay.classList.add('hidden');
                }
            });
        }
        
        // Global function to close mobile sidebar
        window.closeMobileSidebar = function() {
            const mobileMenuOverlay = document.getElementById('mobileMenuOverlay');
            if (mobileMenuOverlay) {
                mobileMenuOverlay.classList.add('hidden');
            }
        };
    });

// Global variables for tracking modal state
        let pendingApproveAction = null;
        let pendingDeclineAction = null;

        // Approve modal functions
        function showApproveModal(collection, docId, reporterName, categoryType) {
            console.log('showApproveModal called:', { collection, docId, reporterName, categoryType });
            
            // Close the report modal first
            const reportModal = document.getElementById('reportModal');
            if (reportModal) {
                reportModal.classList.remove('opacity-100');
                reportModal.classList.add('opacity-0', 'pointer-events-none');
            }
            
            const modal = document.getElementById('approveModal');
            const content = modal.querySelector('.relative');
            const detailsDiv = document.getElementById('approve-report-details');

            if (!modal) {
                console.error('Approve modal not found!');
                return;
            }

            // Store the action details for later execution
            pendingApproveAction = {
                collection: collection,
                docId: docId,
                reporterName: reporterName,
                categoryType: categoryType
            };

            // Populate report details
            detailsDiv.innerHTML = `
                <p><strong>Reporter:</strong> ${reporterName}</p>
                <p><strong>Type:</strong> ${categoryType}</p>
                <p><strong>ID:</strong> ${docId}</p>
            `;

            // Show modal
            modal.classList.remove('opacity-0', 'pointer-events-none');
            setTimeout(() => {
                content.classList.remove('opacity-0', 'scale-95');
            }, 50);
            console.log('Approve modal should now be visible');
        }

        function closeApproveModal() {
            console.log('closeApproveModal called');
            const modal = document.getElementById('approveModal');
            const content = modal.querySelector('.relative');

            content.classList.add('opacity-0', 'scale-95');
            modal.classList.add('opacity-0');

            setTimeout(() => {
                modal.classList.add('pointer-events-none');
                pendingApproveAction = null;
            }, 300);
        }

        // Function to update report status (used by confirmation modals)
        async function updateReportStatus(collection, docId, newStatus, declineReason = null) {
            console.log('🚀 updateReportStatus called:', { collection, docId, newStatus, declineReason });
            console.log('🔍 Starting status update process...');
            
            // Show loading toast
            const actionType = newStatus === 'Approved' ? 'Approving' : 'Declining';
            showToast(`${actionType} report...`, 'info');
            
            try {
                console.log('📝 Creating FormData...');
                const formData = new FormData();
                formData.append('api_action', 'update_status');
                formData.append('collection', collection);
                formData.append('docId', docId);
                formData.append('newStatus', newStatus);
                
                // Add decline reason if provided
                if (declineReason && newStatus === 'Declined') {
                    formData.append('declineReason', declineReason);
                    console.log('📝 Added decline reason to request:', declineReason);
                }
                
                console.log('📤 Sending request with data:', {
                    api_action: 'update_status',
                    collection: collection,
                    docId: docId,
                    newStatus: newStatus,
                    declineReason: declineReason || 'N/A'
                });
                console.log('🌐 Request URL:', window.location.href);
                
                console.log('⏰ Making fetch request...');
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                console.log('📨 Response received:', response.status, response.statusText);
                console.log('📨 Response headers:', response.headers);
                
                if (!response.ok) {
                    console.error('❌ Response not OK:', response.status, response.statusText);
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                console.log('📝 Parsing JSON response...');
                const result = await response.json();
                console.log('📋 Response data:', result);
                
                if (result.success) {
                    console.log('✅ Status update successful!');
                    
                    // Clear timeout
                    if (window.currentTimeoutId) {
                        clearTimeout(window.currentTimeoutId);
                        window.currentTimeoutId = null;
                        console.log('⏰ Timeout cleared');
                    }
                    
                    // Restore external buttons if they exist
                    if (window.currentExternalApproveBtn && window.originalExternalApproveText) {
                        console.log('🔄 Restoring external approve button...');
                        window.currentExternalApproveBtn.innerHTML = window.originalExternalApproveText;
                        window.currentExternalApproveBtn.disabled = false;
                        window.currentExternalApproveBtn.classList.remove('opacity-75');
                        console.log('✅ External approve button restored');
                        
                        // Clean up references
                        window.currentExternalApproveBtn = null;
                        window.originalExternalApproveText = null;
                    }
                    
                    if (window.currentExternalDeclineBtn && window.originalExternalDeclineText) {
                        console.log('🔄 Restoring external decline button...');
                        window.currentExternalDeclineBtn.innerHTML = window.originalExternalDeclineText;
                        window.currentExternalDeclineBtn.disabled = false;
                        window.currentExternalDeclineBtn.classList.remove('opacity-75');
                        console.log('✅ External decline button restored');
                        
                        // Clean up references
                        window.currentExternalDeclineBtn = null;
                        window.originalExternalDeclineText = null;
                    }
                    
                    // Show success message
                    console.log('🎉 Showing success toast and modal...');
                    showToast(`Report ${newStatus.toLowerCase()} successfully!`, 'success');
                    
                    // Show success modal
                    showSuccessModal(newStatus, docId);
                    
                    // Update the UI immediately
                    let reportRows = Array.from(document.querySelectorAll(`tr.report-row[data-id="${docId}"]`));
                    
                    // Fallback: Try to find row via the button that triggered the action
                    if (reportRows.length === 0) {
                        console.log('⚠️ No rows found by data-id, trying button reference...');
                        let btn = null;
                        if (newStatus === 'Approved') btn = window.currentExternalApproveBtn;
                        else if (newStatus === 'Declined') btn = window.currentExternalDeclineBtn;
                        
                        if (btn) {
                            const row = btn.closest('tr.report-row') || btn.closest('tr');
                            if (row) {
                                console.log('🎯 Found row via button reference');
                                reportRows.push(row);
                            }
                        }
                    }
                    
                    console.log('🎯 Total report rows to update:', reportRows.length);
                    reportRows.forEach(row => {
                        // Update status badge
                        const badge = row.querySelector('.status-badge');
                        if (badge) {
                            badge.classList.remove('status-badge-success', 'status-badge-pending', 'status-badge-declined');
                            const st = newStatus.toLowerCase();
                            if (st === 'approved') badge.classList.add('status-badge-success');
                            else badge.classList.add('status-badge-declined');
                            badge.innerHTML = `<span class="h-2 w-2 rounded-full bg-current mr-2"></span>${newStatus}`;
                        }
                        
                        // Disable action buttons
                        row.querySelectorAll('button[onclick*="showApproveConfirmation"], button[onclick*="showDeclineConfirmation"]').forEach(btn => {
                            btn.disabled = true;
                            btn.classList.add('opacity-50', 'btn-disabled');
                        });
                        
                        // Move report to correct section with animation
                        setTimeout(() => {
                            row.style.transition = 'opacity 0.3s ease-out, transform 0.3s ease-out';
                            row.style.opacity = '0';
                            row.style.transform = 'translateX(-20px)';
                            
                            setTimeout(() => {
                                row.remove();
                            }, 300);
                        }, 2000); // Longer delay to see the success feedback
                    });

                    // Update Recent Activity items immediately (Real-time UI update)
                    const recentItems = document.querySelectorAll(`#activityList li[data-id="${docId}"]`);
                    console.log('🎯 Updating recent activity items:', recentItems.length);
                    recentItems.forEach(item => {
                        // Update data attribute
                        item.dataset.status = newStatus.toLowerCase();
                        
                        // Update icon background
                        const iconBg = item.querySelector('.w-14.h-14');
                        if (iconBg) {
                            // Remove old gradient classes
                            iconBg.classList.remove('from-yellow-500', 'to-amber-600', 'from-green-500', 'to-emerald-600', 'from-red-500', 'to-rose-600');
                            
                            // Add new gradient classes
                            if (newStatus === 'Approved') {
                                iconBg.classList.add('from-green-500', 'to-emerald-600');
                            } else if (newStatus === 'Declined') {
                                iconBg.classList.add('from-red-500', 'to-rose-600');
                            } else {
                                iconBg.classList.add('from-yellow-500', 'to-amber-600');
                            }
                        }
                        
                        // Update status dot
                        const statusDot = item.querySelector('.absolute.-top-1.-right-1');
                        if (statusDot) {
                            statusDot.classList.remove('bg-yellow-500', 'bg-green-500', 'bg-red-500');
                            statusDot.classList.add(newStatus === 'Approved' ? 'bg-green-500' : (newStatus === 'Declined' ? 'bg-red-500' : 'bg-yellow-500'));
                        }
                        
                        // Update status badge
                        const statusBadge = item.querySelector('.inline-flex.items-center.gap-2');
                        if (statusBadge) {
                            // Update text color
                            statusBadge.classList.remove('text-yellow-700', 'text-green-700', 'text-red-700');
                            statusBadge.classList.add(newStatus === 'Approved' ? 'text-green-700' : (newStatus === 'Declined' ? 'text-red-700' : 'text-yellow-700'));
                                
                            // Update border color
                            statusBadge.classList.remove('border-yellow-200', 'border-green-200', 'border-red-200');
                            statusBadge.classList.add(newStatus === 'Approved' ? 'border-green-200' : (newStatus === 'Declined' ? 'border-red-200' : 'border-yellow-200'));
                                
                            // Update inner dot
                            const innerDot = statusBadge.querySelector('.w-2.h-2');
                            if (innerDot) {
                                innerDot.classList.remove('bg-yellow-500', 'bg-green-500', 'bg-red-500');
                                innerDot.classList.add(newStatus === 'Approved' ? 'bg-green-500' : (newStatus === 'Declined' ? 'bg-red-500' : 'bg-yellow-500'));
                            }
                            
                            // Update text content
                            // Find the text node (it's usually the last child after the dot span)
                            let textUpdated = false;
                            statusBadge.childNodes.forEach(node => {
                                if (node.nodeType === 3 && node.textContent.trim().length > 0) {
                                    node.textContent = ' ' + newStatus;
                                    textUpdated = true;
                                }
                            });
                            
                            if (!textUpdated) {
                                statusBadge.appendChild(document.createTextNode(' ' + newStatus));
                            }
                        }
                    });
                    
                    // Update tab counts in real-time
                    updateTabCountsRealtime(collection, docId, newStatus);
                    
                    // Refresh Recent Activity list to reflect changes
                    if (typeof window.refreshRecentActivity === 'function') {
                        console.log('🔄 Refreshing recent activity list...');
                        window.refreshRecentActivity();
                    }
                    
                    // Update counters and refresh data
                    if (typeof updateStatusCounters === 'function') {
                        updateStatusCounters(collection, newStatus);
                    }
                    
                    if (typeof loadStaffData === 'function') {
                        setTimeout(() => {
                            loadStaffData(true); // Force refresh
                        }, 1000);
                    }
                    
                } else {
                    console.error('❌ Server returned error:', result.message);
                    
                    // Clear timeout
                    if (window.currentTimeoutId) {
                        clearTimeout(window.currentTimeoutId);
                        window.currentTimeoutId = null;
                    }
                    
                    // Restore external buttons on error
                    if (window.currentExternalApproveBtn && window.originalExternalApproveText) {
                        window.currentExternalApproveBtn.innerHTML = window.originalExternalApproveText;
                        window.currentExternalApproveBtn.disabled = false;
                        window.currentExternalApproveBtn.classList.remove('opacity-75');
                        console.log('✅ External approve button restored after error');
                        
                        // Clean up references
                        window.currentExternalApproveBtn = null;
                        window.originalExternalApproveText = null;
                    }
                    
                    if (window.currentExternalDeclineBtn && window.originalExternalDeclineText) {
                        window.currentExternalDeclineBtn.innerHTML = window.originalExternalDeclineText;
                        window.currentExternalDeclineBtn.disabled = false;
                        window.currentExternalDeclineBtn.classList.remove('opacity-75');
                        console.log('✅ External decline button restored after error');
                        
                        // Clean up references
                        window.currentExternalDeclineBtn = null;
                        window.originalExternalDeclineText = null;
                    }
                    
                    showToast(result.message || 'Failed to update status', 'error');
                }
                
            } catch (error) {
                console.error('💥 Error updating status:', error);
                console.error('💥 Error details:', error.stack);
                
                // Clear timeout
                if (window.currentTimeoutId) {
                    clearTimeout(window.currentTimeoutId);
                    window.currentTimeoutId = null;
                }
                
                // Restore external buttons on error
                if (window.currentExternalApproveBtn && window.originalExternalApproveText) {
                    window.currentExternalApproveBtn.innerHTML = window.originalExternalApproveText;
                    window.currentExternalApproveBtn.disabled = false;
                    window.currentExternalApproveBtn.classList.remove('opacity-75');
                    console.log('✅ External approve button restored after network error');
                    
                    // Clean up references
                    window.currentExternalApproveBtn = null;
                    window.originalExternalApproveText = null;
                }
                
                if (window.currentExternalDeclineBtn && window.originalExternalDeclineText) {
                    window.currentExternalDeclineBtn.innerHTML = window.originalExternalDeclineText;
                    window.currentExternalDeclineBtn.disabled = false;
                    window.currentExternalDeclineBtn.classList.remove('opacity-75');
                    console.log('✅ External decline button restored after network error');
                    
                    // Clean up references
                    window.currentExternalDeclineBtn = null;
                    window.originalExternalDeclineText = null;
                }
                
                showToast('Failed to update status - please try again', 'error');
            }
        }

        // Function to show success modal after approval/decline
        function showSuccessModal(status, docId) {
            const isApproved = status === 'Approved';
            const color = isApproved ? 'green' : 'red';
            const icon = isApproved ? 'check-circle' : 'x-circle';
            const title = isApproved ? 'Report Approved!' : 'Report Declined!';
            const message = isApproved 
                ? 'Emergency responders have been notified and will respond shortly.'
                : 'The reporter has been notified and can resubmit if needed.';

            // Create success modal
            const successModal = document.createElement('div');
            successModal.id = 'successModal';
            successModal.className = 'fixed inset-0 z-[60] flex items-center justify-center p-4';
            successModal.innerHTML = `
                <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm"></div>
                <div class="relative max-w-md w-full bg-white rounded-2xl shadow-xl overflow-hidden animate-fade-in-up">
                    <div class="p-6 text-center">
                        <div class="w-16 h-16 bg-${color}-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <?php echo svg_icon('${icon}', 'w-8 h-8 text-${color}-600'); ?>
                        </div>
                        <h3 class="text-xl font-bold text-${color}-800 mb-2">${title}</h3>
                        <p class="text-gray-600 mb-6">${message}</p>
                        <div class="text-xs text-gray-500 mb-4">Report ID: ${docId}</div>
                        <button onclick="closeSuccessModal()" class="btn btn-primary w-full" style="background-color: ${isApproved ? '#10b981' : '#dc2626'};">
                            Continue
                        </button>
                    </div>
                </div>
            `;

            document.body.appendChild(successModal);

            // Auto-close after 5 seconds
            setTimeout(() => {
                closeSuccessModal();
            }, 5000);
        }

        // Function to close success modal
        window.closeSuccessModal = function() {
            const modal = document.getElementById('successModal');
            if (modal) {
                modal.style.opacity = '0';
                setTimeout(() => {
                    modal.remove();
                }, 300);
            }
        }

        // Function to update tab counts in real-time
        function updateTabCountsRealtime(collection, docId, newStatus) {
            console.log('Updating tab counts in real-time:', { collection, docId, newStatus });
            
            // Find the appropriate slug based on collection
            const collectionToSlug = {
                'ambulance_reports': 'ambulance',
                'fire_reports': 'fire', 
                'flood_reports': 'flood',
                'tanod_reports': 'tanod',
                'other_reports': 'other'
            };
            
            const slug = collectionToSlug[collection];
            if (!slug) {
                console.log('No slug found for collection:', collection);
                return;
            }
            
            // Find the segmented control for this category
            const segmentedControl = document.querySelector(`.segmented[data-slug="${slug}"]`);
            if (!segmentedControl) {
                console.log('No segmented control found for slug:', slug);
                return;
            }
            
            // Get current counts
            const pendingTab = segmentedControl.querySelector('[data-tab="pending"] .tab-count');
            const approvedTab = segmentedControl.querySelector('[data-tab="approved"] .tab-count');
            const declinedTab = segmentedControl.querySelector('[data-tab="declined"] .tab-count');
            
            if (pendingTab && approvedTab && declinedTab) {
                // Decrease pending count
                const pendingCount = parseInt(pendingTab.textContent) || 0;
                const newPendingCount = Math.max(0, pendingCount - 1);
                pendingTab.textContent = newPendingCount;
                
                // Increase appropriate count based on new status
                if (newStatus === 'Approved') {
                    const approvedCount = parseInt(approvedTab.textContent) || 0;
                    approvedTab.textContent = approvedCount + 1;
                    
                    // Add pulse animation
                    approvedTab.classList.add('animate-pulse');
                    setTimeout(() => approvedTab.classList.remove('animate-pulse'), 1000);
                } else if (newStatus === 'Declined') {
                    const declinedCount = parseInt(declinedTab.textContent) || 0;
                    declinedTab.textContent = declinedCount + 1;
                    
                    // Add pulse animation
                    declinedTab.classList.add('animate-pulse');
                    setTimeout(() => declinedTab.classList.remove('animate-pulse'), 1000);
                }
                
                // Add pulse animation to pending (decreased)
                pendingTab.classList.add('animate-pulse');
                setTimeout(() => pendingTab.classList.remove('animate-pulse'), 1000);
                
                console.log('✅ Tab counts updated successfully:', {
                    pending: newPendingCount,
                    approved: approvedTab.textContent,
                    declined: declinedTab.textContent
                });
            } else {
                console.log('❌ Could not find tab count elements');
            }
        }

        function confirmApprove() {
            console.log('🚀 confirmApprove called, pendingApproveAction:', pendingApproveAction);
            if (!pendingApproveAction) {
                console.error('❌ No pending approve action found!');
                return;
            }

            // Find and update the external approve button to loading state
            const docId = pendingApproveAction.docId;
            console.log('🔍 Looking for approve button with docId:', docId);
            
            // Try multiple selectors to find the button
            let externalApproveBtn = document.querySelector(`button[onclick*="showApproveConfirmation"][onclick*="${docId}"]`);
            if (!externalApproveBtn) {
                // Try alternative selector
                externalApproveBtn = document.querySelector(`button[onclick*="showApproveConfirmation("][onclick*="'${docId}'"]`);
            }
            if (!externalApproveBtn) {
                // Try finding by data attribute if it exists
                externalApproveBtn = document.querySelector(`button[data-doc-id="${docId}"][onclick*="showApproveConfirmation"]`);
            }
            if (!externalApproveBtn) {
                // Debug: List all approve buttons to see their onclick attributes
                const allApproveButtons = document.querySelectorAll('button[onclick*="showApproveConfirmation"]');
                console.log('🔍 All approve buttons found:', allApproveButtons.length);
                allApproveButtons.forEach((btn, index) => {
                    console.log(`Button ${index}:`, btn.onclick?.toString() || btn.getAttribute('onclick'));
                });
            }
            
            console.log('🔍 External approve button found:', externalApproveBtn);
            
            let originalExternalText = '';
            if (externalApproveBtn) {
                originalExternalText = externalApproveBtn.innerHTML;
                externalApproveBtn.innerHTML = '<?php echo svg_icon('spinner', 'w-4 h-4 animate-spin'); ?> Approving...';
                externalApproveBtn.disabled = true;
                externalApproveBtn.classList.add('opacity-75');
                console.log('✅ External button set to loading state');
            } else {
                console.warn('⚠️ External approve button not found - will continue without loading state');
            }

            // Show loading on the modal confirm button
            const confirmBtn = document.querySelector('#approveModal button[onclick="confirmApprove()"]');
            console.log('🔍 Confirm button found:', confirmBtn);
            if (confirmBtn) {
                const originalText = confirmBtn.innerHTML;
                confirmBtn.innerHTML = '<?php echo svg_icon('spinner', 'w-4 h-4 animate-spin'); ?> Approving...';
                confirmBtn.disabled = true;
                
                // Restore button after action completes
                setTimeout(() => {
                    if (confirmBtn && document.body.contains(confirmBtn)) {
                        confirmBtn.innerHTML = originalText;
                        confirmBtn.disabled = false;
                    }
                }, 3000);
            }

            // Close the modal first
            console.log('🔒 Closing approve modal...');
            closeApproveModal();

            // Call the status update directly
            console.log('📡 Calling updateReportStatus with:', {
                collection: pendingApproveAction.collection,
                docId: pendingApproveAction.docId,
                status: 'Approved'
            });
            
            // Store external button reference for restoration after completion
            window.currentExternalApproveBtn = externalApproveBtn;
            window.originalExternalApproveText = originalExternalText;
            
            // Add a timeout to restore button if API call takes too long
            const timeoutId = setTimeout(() => {
                console.warn('⚠️ API call timeout - restoring button');
                if (window.currentExternalApproveBtn && window.originalExternalApproveText) {
                    window.currentExternalApproveBtn.innerHTML = window.originalExternalApproveText;
                    window.currentExternalApproveBtn.disabled = false;
                    window.currentExternalApproveBtn.classList.remove('opacity-75');
                    window.currentExternalApproveBtn = null;
                    window.originalExternalApproveText = null;
                }
                showToast('Request timeout - please try again', 'error');
            }, 30000); // 30 second timeout
            
            // Store timeout ID for cleanup
            window.currentTimeoutId = timeoutId;
            
            updateReportStatus(pendingApproveAction.collection, pendingApproveAction.docId, 'Approved');
        }

        // Decline modal functions
        function showDeclineModal(collection, docId, reporterName, categoryType) {
            console.log('showDeclineModal called:', { collection, docId, reporterName, categoryType });
            
            // Close the report modal first
            const reportModal = document.getElementById('reportModal');
            if (reportModal) {
                reportModal.classList.remove('opacity-100');
                reportModal.classList.add('opacity-0', 'pointer-events-none');
            }
            
            const modal = document.getElementById('declineModal');
            const content = modal.querySelector('.relative');
            const detailsDiv = document.getElementById('decline-report-details');

            if (!modal) {
                console.error('Decline modal not found!');
                return;
            }

            // Store the action details for later execution
            pendingDeclineAction = {
                collection: collection,
                docId: docId,
                reporterName: reporterName,
                categoryType: categoryType
            };

            // Populate report details
            detailsDiv.innerHTML = `
                <p><strong>Reporter:</strong> ${reporterName}</p>
                <p><strong>Type:</strong> ${categoryType}</p>
                <p><strong>ID:</strong> ${docId}</p>
            `;

            // Clear and reset the decline reason textarea
            const reasonTextarea = document.getElementById('declineReason');
            if (reasonTextarea) {
                reasonTextarea.value = '';
                reasonTextarea.classList.remove('border-red-500', 'ring-2', 'ring-red-500');
                
                // Update character counter
                const charCount = document.getElementById('reasonCharCount');
                if (charCount) charCount.textContent = '0/500';
                
                // Add character counter event listener
                reasonTextarea.addEventListener('input', function() {
                    const count = this.value.length;
                    const maxLength = 500;
                    if (charCount) {
                        charCount.textContent = `${count}/${maxLength}`;
                        if (count > maxLength * 0.9) {
                            charCount.classList.add('text-red-500');
                        } else {
                            charCount.classList.remove('text-red-500');
                        }
                    }
                });
            }

            // Show modal
            modal.classList.remove('opacity-0', 'pointer-events-none');
            setTimeout(() => {
                content.classList.remove('opacity-0', 'scale-95');
            }, 50);
            console.log('Decline modal should now be visible');
        }

        function closeDeclineModal() {
            console.log('closeDeclineModal called');
            const modal = document.getElementById('declineModal');
            const content = modal.querySelector('.relative');

            content.classList.add('opacity-0', 'scale-95');
            modal.classList.add('opacity-0');

            setTimeout(() => {
                modal.classList.add('pointer-events-none');
                pendingDeclineAction = null;
                
                // Clear the decline reason textarea when modal closes
                const reasonTextarea = document.getElementById('declineReason');
                if (reasonTextarea) {
                    reasonTextarea.value = '';
                    reasonTextarea.classList.remove('border-red-500', 'ring-2', 'ring-red-500');
                }
            }, 300);
        }

        function confirmDecline() {
            console.log('🚀 confirmDecline called, pendingDeclineAction:', pendingDeclineAction);
            if (!pendingDeclineAction) {
                console.error('❌ No pending decline action found!');
                return;
            }

            // Get and validate the decline reason
            const reasonTextarea = document.getElementById('declineReason');
            if (!reasonTextarea) {
                console.error('❌ Decline reason textarea not found!');
                showToast('Error: Could not find decline reason field', 'error');
                return;
            }

            const declineReason = reasonTextarea.value.trim();
            if (!declineReason) {
                // Show validation error
                reasonTextarea.classList.add('border-red-500', 'ring-2', 'ring-red-500');
                reasonTextarea.focus();
                showToast('Please provide a reason for declining this report', 'error');
                return;
            }
            
            if (declineReason.length < 10) {
                // Ensure reason is meaningful
                reasonTextarea.classList.add('border-red-500', 'ring-2', 'ring-red-500');
                reasonTextarea.focus();
                showToast('Please provide a more detailed reason (at least 10 characters)', 'error');
                return;
            }

            // Remove validation styling if present
            reasonTextarea.classList.remove('border-red-500', 'ring-2', 'ring-red-500');

            console.log('📝 Decline reason captured:', declineReason);

            // Find and update the external decline button to loading state
            const docId = pendingDeclineAction.docId;
            console.log('🔍 Looking for decline button with docId:', docId);
            
            // Try multiple selectors to find the button
            let externalDeclineBtn = document.querySelector(`button[onclick*="showDeclineConfirmation"][onclick*="${docId}"]`);
            if (!externalDeclineBtn) {
                // Try alternative selector
                externalDeclineBtn = document.querySelector(`button[onclick*="showDeclineConfirmation("][onclick*="'${docId}'"]`);
            }
            if (!externalDeclineBtn) {
                // Try finding by data attribute if it exists
                externalDeclineBtn = document.querySelector(`button[data-doc-id="${docId}"][onclick*="showDeclineConfirmation"]`);
            }
            if (!externalDeclineBtn) {
                // Debug: List all decline buttons to see their onclick attributes
                const allDeclineButtons = document.querySelectorAll('button[onclick*="showDeclineConfirmation"]');
                console.log('🔍 All decline buttons found:', allDeclineButtons.length);
                allDeclineButtons.forEach((btn, index) => {
                    console.log(`Button ${index}:`, btn.onclick?.toString() || btn.getAttribute('onclick'));
                });
            }
            
            console.log('🔍 External decline button found:', externalDeclineBtn);
            
            let originalExternalText = '';
            if (externalDeclineBtn) {
                originalExternalText = externalDeclineBtn.innerHTML;
                externalDeclineBtn.innerHTML = '<?php echo svg_icon('spinner', 'w-4 h-4 animate-spin'); ?> Declining...';
                externalDeclineBtn.disabled = true;
                externalDeclineBtn.classList.add('opacity-75');
                console.log('✅ External decline button set to loading state');
            } else {
                console.warn('⚠️ External decline button not found - will continue without loading state');
            }

            // Show loading on the modal confirm button
            const confirmBtn = document.querySelector('#declineModal button[onclick="confirmDecline()"]');
            if (confirmBtn) {
                const originalText = confirmBtn.innerHTML;
                confirmBtn.innerHTML = '<?php echo svg_icon('spinner', 'w-4 h-4 animate-spin'); ?> Declining...';
                confirmBtn.disabled = true;
                
                // Restore button after action completes
                setTimeout(() => {
                    if (confirmBtn && document.body.contains(confirmBtn)) {
                        confirmBtn.innerHTML = originalText;
                        confirmBtn.disabled = false;
                    }
                }, 3000);
            }

            // Close the modal first
            closeDeclineModal();

            // Store external button reference for restoration after completion
            window.currentExternalDeclineBtn = externalDeclineBtn;
            window.originalExternalDeclineText = originalExternalText;
            
            // Add a timeout to restore button if API call takes too long
            const timeoutId = setTimeout(() => {
                console.warn('⚠️ API call timeout - restoring decline button');
                if (window.currentExternalDeclineBtn && window.originalExternalDeclineText) {
                    window.currentExternalDeclineBtn.innerHTML = window.originalExternalDeclineText;
                    window.currentExternalDeclineBtn.disabled = false;
                    window.currentExternalDeclineBtn.classList.remove('opacity-75');
                    window.currentExternalDeclineBtn = null;
                    window.originalExternalDeclineText = null;
                }
                showToast('Request timeout - please try again', 'error');
            }, 30000); // 30 second timeout
            
            // Store timeout ID for cleanup
            window.currentTimeoutId = timeoutId;

            // Call the status update directly with the decline reason
            updateReportStatus(pendingDeclineAction.collection, pendingDeclineAction.docId, 'Declined', declineReason);
        }

        // Function to refresh tab counts periodically for real-time updates
        function startRealTimeTabCountUpdates() {
            // Update tab counts every 10 seconds
            setInterval(async () => {
                if (document.hidden) return;
                try {
                    // Get fresh data from server
                    const formData = new FormData();
                    formData.append('api_action', 'get_tab_counts');
                    
                    const response = await fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    });
                    
                    if (response.ok) {
                        const result = await response.json();
                        if (result.success && result.data) {
                            // Update each category's tab counts
                            Object.keys(result.data).forEach(slug => {
                                const counts = result.data[slug];
                                updateTabCountsFromServer(slug, counts);
                            });
                        }
                    }
                } catch (error) {
                    console.log('Tab count refresh error (silent):', error);
                }
            }, 10000); // Every 10 seconds
        }

        // Function to update tab counts from server data
        function updateTabCountsFromServer(slug, counts) {
            const segmentedControl = document.querySelector(`.segmented[data-slug="${slug}"]`);
            if (!segmentedControl) return;
            
            const pendingTab = segmentedControl.querySelector('[data-tab="pending"] .tab-count');
            const approvedTab = segmentedControl.querySelector('[data-tab="approved"] .tab-count');
            const declinedTab = segmentedControl.querySelector('[data-tab="declined"] .tab-count');
            
            if (pendingTab && approvedTab && declinedTab) {
                // Only update if counts have changed to avoid unnecessary animations
                const currentPending = parseInt(pendingTab.textContent) || 0;
                const currentApproved = parseInt(approvedTab.textContent) || 0;
                const currentDeclined = parseInt(declinedTab.textContent) || 0;
                
                if (currentPending !== counts.pending) {
                    pendingTab.textContent = counts.pending;
                    pendingTab.classList.add('animate-pulse');
                    setTimeout(() => pendingTab.classList.remove('animate-pulse'), 1000);
                }
                
                if (currentApproved !== counts.approved) {
                    approvedTab.textContent = counts.approved;
                    approvedTab.classList.add('animate-pulse');
                    setTimeout(() => approvedTab.classList.remove('animate-pulse'), 1000);
                }
                
                if (currentDeclined !== counts.declined) {
                    declinedTab.textContent = counts.declined;
                    declinedTab.classList.add('animate-pulse');
                    setTimeout(() => declinedTab.classList.remove('animate-pulse'), 1000);
                }
            }
        }

        // Start real-time updates when page loads
        document.addEventListener('DOMContentLoaded', () => {
            // Delay to ensure all elements are loaded
            setTimeout(() => {
                startRealTimeTabCountUpdates();
            }, 2000);
        });

        // Live Support Chat Logic
        document.addEventListener('DOMContentLoaded', function() {
            const chatListSidebar = document.getElementById('chatListSidebar');
            const chatArea = document.getElementById('chatArea');
            const backToChatListBtn = document.getElementById('backToChatList');
            const chatList = document.getElementById('chatList');
            const messagesArea = document.getElementById('messagesArea');
            const messageForm = document.getElementById('messageForm');
            const messageInput = document.getElementById('messageInput');
            const chatSearch = document.getElementById('chatSearch');
            
            let currentChatId = null;
            let currentChatStatus = null;
            let chatPollInterval = null;
            let messagePollInterval = null;
            let latestChats = [];
            let lastFetchedMessages = [];
            let pendingMessages = [];

            // Mobile UI: Show Chat List
            function showChatList() {
                if (window.innerWidth < 768) {
                    chatListSidebar.classList.remove('-translate-x-full', 'hidden');
                    chatArea.classList.add('hidden');
                }
            }

            // Mobile UI: Show Chat Area
            function showChatArea() {
                if (window.innerWidth < 768) {
                    chatListSidebar.classList.add('hidden');
                    chatArea.classList.remove('hidden');
                }
            }

            if (backToChatListBtn) {
                backToChatListBtn.addEventListener('click', showChatList);
            }

            // Fetch Chats
            let isFetchingChats = false;
            const recentlyAcceptedChats = new Set();

            async function fetchChats() {
                if (isFetchingChats) return;
                isFetchingChats = true;
                try {
                    // Add timestamp to prevent caching
                    const response = await fetch('api/support_chat.php?action=get_chats&t=' + new Date().getTime());
                    const result = await response.json();
                    
                    if (Array.isArray(result.chats)) {
                        const normalizedChats = result.chats
                            .map(chat => {
                                const chatId = chat.id || chat._id || chat.userId || chat.user_id || chat.uid || '';
                                const lastMessageTime = chat.lastMessageTimestamp || chat.lastMessageTime || chat.timestamp || chat._created || null;
                                
                                // Override status if recently accepted
                                let status = chat.status;
                                if (recentlyAcceptedChats.has(chatId)) {
                                    status = 'active';
                                }

                                return {
                                    ...chat,
                                    id: chatId,
                                    status: status,
                                    lastMessageTime
                                };
                            })
                            .filter(chat => chat.id);

                        if (normalizedChats.length !== result.chats.length) {
                            console.warn('Some chats were missing IDs and were skipped.');
                        }

                        latestChats = normalizedChats;
                        renderChatList(normalizedChats);
                        return normalizedChats;
                    }
                } catch (error) {
                    console.error('Error fetching chats:', error);
                } finally {
                    isFetchingChats = false;
                }
                return latestChats;
            }

            // Render Chat List
            function renderChatList(chats) {
                chatList.innerHTML = '';
                if (chats.length === 0) {
                    chatList.innerHTML = '<div class="text-center py-8 text-slate-500 text-sm">No active chats or pending requests</div>';
                    return;
                }

                const pendingChats = chats.filter(c => !c.status || c.status === 'pending' || c.status === 'waiting');
                const activeChats = chats.filter(c => c.status === 'active');
                const endedChats = chats.filter(c => c.status === 'ended');

                if (pendingChats.length > 0) {
                    const pendingHeader = document.createElement('div');
                    pendingHeader.className = 'px-3 py-2 text-xs font-bold text-slate-500 uppercase tracking-wider';
                    pendingHeader.textContent = 'Pending Requests';
                    chatList.appendChild(pendingHeader);

                    pendingChats.forEach(chat => renderChatItem(chat));
                }

                if (activeChats.length > 0) {
                    const activeHeader = document.createElement('div');
                    activeHeader.className = 'px-3 py-2 text-xs font-bold text-slate-500 uppercase tracking-wider mt-2';
                    activeHeader.textContent = 'Active Conversations';
                    chatList.appendChild(activeHeader);

                    activeChats.forEach(chat => renderChatItem(chat));
                }

                if (endedChats.length > 0) {
                    const endedHeader = document.createElement('div');
                    endedHeader.className = 'px-3 py-2 text-xs font-bold text-slate-500 uppercase tracking-wider mt-2';
                    endedHeader.textContent = 'Past Conversations';
                    chatList.appendChild(endedHeader);

                    endedChats.forEach(chat => renderChatItem(chat));
                }
            }

            function normalizeChatCategory(value) {
                const v = String(value || '').trim().toLowerCase();
                if (!v) return '';
                if (v.includes('ambulance')) return 'ambulance';
                if (v.includes('fire')) return 'fire';
                if (v.includes('tanod')) return 'tanod';
                if (v.includes('barangay') || v.includes('brgy')) return 'barangay';
                return v;
            }

            function getChatCategory(chat) {
                return normalizeChatCategory(
                    chat.chatCategory || chat.category || chat.relatedReportType || chat.reportType || chat.type || ''
                );
            }

            const pendingCategorySelections = {};

            window.setPendingChatCategory = function(chatId, category, buttonEl = null) {
                if (!chatId) return;
                pendingCategorySelections[chatId] = normalizeChatCategory(category);

                const group = document.querySelector(`[data-chat-category-group="${chatId}"]`);
                if (!group) return;

                group.querySelectorAll('button[data-category]').forEach(btn => {
                    const isActive = btn.dataset.category === pendingCategorySelections[chatId];
                    btn.className = isActive
                        ? 'px-3 py-1.5 rounded-full text-xs font-semibold border bg-sky-600 text-white border-sky-600 transition-all'
                        : 'px-3 py-1.5 rounded-full text-xs font-semibold border bg-white text-slate-700 border-slate-300 hover:border-sky-400 hover:text-sky-700 transition-all';
                });
            };

            function renderChatItem(chat) {
                const chatId = chat.id || chat._id || chat.userId || chat.user_id || chat.uid || '';
                if (!chatId) {
                    console.warn('Skipping chat with no identifier', chat);
                    return;
                }

                const div = document.createElement('div');
                div.className = `p-3 rounded-xl cursor-pointer hover:bg-slate-100 transition-colors mb-1 ${currentChatId === chatId ? 'bg-sky-50 border-l-4 border-sky-500' : ''}`;
                div.onclick = () => loadChat({ ...chat, id: chatId });
                
                const lastMessage = chat.lastMessage ? (chat.lastMessage.length > 30 ? chat.lastMessage.substring(0, 30) + '...' : chat.lastMessage) : 'No messages yet';
                const time = chat.lastMessageTime ? new Date(chat.lastMessageTime).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : '';
                const isPending = !chat.status || chat.status === 'pending' || chat.status === 'waiting';
                const isEnded = chat.status === 'ended';
                const chatCategory = getChatCategory(chat);
                const chatCategoryLabel = {
                    ambulance: '🚑 Ambulance',
                    fire: '🔥 Fire',
                    tanod: '👮 Tanod',
                    barangay: '🏘️ Barangay'
                }[chatCategory] || '';
                
                const chatName = chat.userName || 'Unknown User';
                const chatInitials = chatName.substring(0, 2).toUpperCase();
                
                let avatarClass = 'bg-sky-100 text-sky-600';
                if (isPending) avatarClass = 'bg-amber-100 text-amber-600';
                if (isEnded) avatarClass = 'bg-slate-200 text-slate-500';

                div.innerHTML = `
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full ${avatarClass} flex items-center justify-center font-bold text-sm relative overflow-visible shadow-none ring-0">
                            ${chatInitials}
                            ${isPending ? '<span class="absolute -top-1 -right-1 w-3 h-3 bg-amber-500 rounded-full border border-white"></span>' : ''}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex justify-between items-start">
                                <h4 class="font-bold text-slate-800 text-sm truncate ${isEnded ? 'text-slate-500' : ''}">${chatName}</h4>
                                <span class="text-xs text-slate-400 whitespace-nowrap ml-2">${time}</span>
                            </div>
                            <p class="text-xs text-slate-500 truncate mt-0.5 ${isPending ? 'font-semibold text-slate-700' : ''}">${lastMessage}</p>
                            ${chatCategoryLabel ? `<span class="inline-flex items-center px-2 py-0.5 mt-1 text-[10px] font-semibold rounded-full bg-sky-50 text-sky-700 border border-sky-200">${chatCategoryLabel}</span>` : ''}
                            ${chat.unreadCount > 0 ? `<span class="inline-flex items-center justify-center px-2 py-0.5 mt-1 text-xs font-bold leading-none text-white bg-red-500 rounded-full">${chat.unreadCount}</span>` : ''}
                        </div>
                    </div>
                `;
                chatList.appendChild(div);
            }

            // Load Chat
            async function loadChat(chat) {
                const resolvedChatId = chat.id || chat._id || chat.userId || chat.user_id || chat.uid || '';
                if (!resolvedChatId) {
                    console.error('Cannot load chat without an identifier', chat);
                    return;
                }

                // Clear previous chat data if switching chats
                if (currentChatId !== resolvedChatId) {
                    pendingMessages = [];
                    lastFetchedMessages = [];
                    messagesArea.innerHTML = ''; // Clear visual area immediately
                }

                currentChatId = resolvedChatId;
                currentChatStatus = chat.status || 'pending';
                showChatArea();
                
                // Update Header
                const chatName = chat.userName || 'Unknown User';
                document.getElementById('chatUserName').textContent = chatName;
                document.getElementById('chatUserInitials').textContent = chatName.substring(0, 2).toUpperCase();
                document.getElementById('chatHeader').classList.remove('hidden');
                
                const statusEl = document.getElementById('chatUserStatus');
                const endChatBtn = document.getElementById('endChatBtn');
                
                if (currentChatStatus === 'pending' || currentChatStatus === 'waiting') {
                    statusEl.innerHTML = '<span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span> Pending Request';
                    if(endChatBtn) endChatBtn.classList.add('hidden');
                } else if (currentChatStatus === 'ended') {
                    statusEl.innerHTML = '<span class="w-2 h-2 rounded-full bg-slate-400"></span> Ended';
                    if(endChatBtn) endChatBtn.classList.add('hidden');
                } else {
                    statusEl.innerHTML = '<span class="w-2 h-2 rounded-full bg-emerald-500"></span> Active';
                    if(endChatBtn) endChatBtn.classList.remove('hidden');
                }

                // Handle Input Area based on status
                const inputArea = document.getElementById('messageInputArea');
                if (currentChatStatus === 'pending' || currentChatStatus === 'waiting') {
                    inputArea.classList.add('hidden');

                    const detectedCategory = getChatCategory(chat);
                    if (!pendingCategorySelections[resolvedChatId] && detectedCategory) {
                        pendingCategorySelections[resolvedChatId] = detectedCategory;
                    }

                    const selectedCategory = pendingCategorySelections[resolvedChatId] || '';
                    const categoryButtons = [
                        { key: 'ambulance', label: '🚑 Ambulance' },
                        { key: 'fire', label: '🔥 Fire' },
                        { key: 'tanod', label: '👮 Tanod' },
                        { key: 'barangay', label: '🏘️ Barangay' }
                    ];

                    const categoryChooser = `
                        <div class="mb-4 w-full max-w-xs text-left">
                            <p class="text-xs font-bold text-slate-600 mb-2 uppercase tracking-wide">Category Routing</p>
                            <div class="flex flex-wrap gap-2" data-chat-category-group="${resolvedChatId}">
                                ${categoryButtons.map(item => {
                                    const isActive = selectedCategory === item.key;
                                    const cls = isActive
                                        ? 'px-3 py-1.5 rounded-full text-xs font-semibold border bg-sky-600 text-white border-sky-600 transition-all'
                                        : 'px-3 py-1.5 rounded-full text-xs font-semibold border bg-white text-slate-700 border-slate-300 hover:border-sky-400 hover:text-sky-700 transition-all';
                                    return `<button type="button" class="${cls}" data-category="${item.key}" onclick="setPendingChatCategory('${resolvedChatId}', '${item.key}', this)">${item.label}</button>`;
                                }).join('')}
                            </div>
                            <p class="text-[11px] text-slate-500 mt-2">Choose a category before accepting to route this chat to the correct responders.</p>
                        </div>
                    `;

                    // Show Accept Button in messages area
                    messagesArea.innerHTML = `
                        <div class="h-full flex flex-col items-center justify-center p-6 text-center">
                            <div class="w-16 h-16 bg-amber-100 rounded-full flex items-center justify-center mb-4">
                                <svg class="w-8 h-8 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <h3 class="text-lg font-bold text-slate-800 mb-2">New Chat Request</h3>
                            <p class="text-slate-500 mb-6 max-w-xs">This user is requesting live support. Accept the request to start messaging.</p>
                            ${chat.relatedReportId ? `<div class="mb-6 p-3 bg-slate-50 rounded-lg text-sm text-left w-full max-w-xs border border-slate-200">
                                <p class="font-bold text-slate-700 mb-1">Related Report:</p>
                                <p class="text-slate-600">Type: ${chat.relatedReportType || 'Report'}</p>
                                <p class="text-slate-600 text-xs mt-1">ID: ${chat.relatedReportId}</p>
                            </div>` : ''}
                            ${categoryChooser}
                            <button onclick="acceptChat('${resolvedChatId}', this)" class="bg-sky-600 hover:bg-sky-700 text-white px-6 py-3 rounded-xl font-bold shadow-lg shadow-sky-500/20 transition-all hover:scale-105 flex items-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Accept Chat Request
                            </button>
                        </div>
                    `;
                } else if (currentChatStatus === 'ended') {
                    inputArea.classList.add('hidden');
                    messagesArea.innerHTML = `
                        <div class="h-full flex flex-col items-center justify-center text-slate-400">
                            <p class="text-sm">This chat has ended.</p>
                        </div>
                    `;
                    // Load Messages (read-only)
                    await fetchMessages();
                } else {
                    inputArea.classList.remove('hidden');
                    messagesArea.innerHTML = `
                        <div class="h-full flex flex-col items-center justify-center text-slate-400">
                            <p class="text-sm">Loading conversation...</p>
                        </div>
                    `;
                    // Load Messages
                    await fetchMessages();
                    // Start polling messages
                    if (window.messagePollTimeout) clearTimeout(window.messagePollTimeout);
                    fetchMessages(); // Initial fetch, will trigger subsequent polls
                }
            }

            // End Chat Functions
            window.confirmEndChat = function() {
                showEndChatModal();
            };

            window.confirmEndChatAction = function() {
                console.log('confirmEndChatAction called');
                closeEndChatModal();
                if (typeof endChat === 'function') {
                    endChat();
                } else {
                    alert('Error: endChat function not found! Please refresh the page.');
                }
            };

            async function endChat() {
                console.log('endChat called, currentChatId:', currentChatId);
                if (!currentChatId) {
                    alert('Error: No active chat selected. Please refresh the page.');
                    return;
                }

                try {
                    const formData = new FormData();
                    formData.append('action', 'end_chat');
                    formData.append('chat_id', currentChatId);

                    const response = await fetch('api/support_chat.php', {
                        method: 'POST',
                        body: formData
                    });

                    const text = await response.text();
                    console.log('End chat response:', text); // Debug log
                    
                    let result;
                    try {
                        result = JSON.parse(text);
                    } catch (e) {
                        console.error('Invalid JSON response:', text);
                        // Show the actual text response in the alert for debugging
                        throw new Error('Server returned invalid response: ' + text.substring(0, 100));
                    }

                    if (result.success) {
                        // Update local status
                        currentChatStatus = 'ended';
                        
                        // Update UI
                        const statusEl = document.getElementById('chatUserStatus');
                        if (statusEl) statusEl.innerHTML = '<span class="w-2 h-2 rounded-full bg-slate-400"></span> Ended';
                        
                        const endChatBtn = document.getElementById('endChatBtn');
                        if(endChatBtn) endChatBtn.classList.add('hidden');
                        
                        const inputArea = document.getElementById('messageInputArea');
                        if (inputArea) inputArea.classList.add('hidden');
                        
                        // Refresh messages to show system message
                        await fetchMessages();
                        
                        // Refresh chat list to update status there too
                        if (typeof fetchChats === 'function') fetchChats();
                    } else {
                        alert('Failed to end chat: ' + (result.error || 'Unknown error'));
                    }
                } catch (error) {
                    console.error('Error ending chat:', error);
                    alert('An error occurred while ending the chat: ' + error.message);
                }
            }

            // Accept Chat Function (Global)
            window.acceptChat = async function(chatId, triggerBtn = null) {
                if (!chatId) {
                    alert('Chat ID is missing. Please refresh and try again.');
                    return;
                }

                const btn = triggerBtn || document.querySelector(`button[onclick*="${chatId}"]`) || document.querySelector('button[onclick^="acceptChat"]');
                let originalContent = '';
                
                try {
                    const formData = new FormData();
                    formData.append('chat_id', chatId);
                    const selectedCategory = pendingCategorySelections[chatId] || '';
                    if (selectedCategory) {
                        formData.append('chat_category', selectedCategory);
                    }
                    
                    if(btn) {
                        // Store original content to restore on error
                        originalContent = btn.innerHTML;
                        btn.dataset.originalContent = originalContent;
                        btn.innerHTML = '<svg class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg> Accepting...';
                        btn.disabled = true;
                    }

                    // Add timeout to fetch
                    const controller = new AbortController();
                    const timeoutId = setTimeout(() => controller.abort(), 30000); // Increased to 30s timeout

                    const response = await fetch('api/support_chat.php?action=accept_chat', {
                        method: 'POST',
                        body: formData,
                        signal: controller.signal
                    });
                    
                    clearTimeout(timeoutId);
                    
                    // Check if response is ok
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    const text = await response.text();
                    let result;
                    try {
                        result = JSON.parse(text);
                    } catch (e) {
                        console.error('Invalid JSON response:', text);
                        throw new Error('Server returned invalid response');
                    }
                    
                    if (result.success) {
                        const chatName = document.getElementById('chatUserName').textContent || 'Resident';
                        
                        // Add to recently accepted set to prevent race conditions
                        recentlyAcceptedChats.add(chatId);
                        setTimeout(() => recentlyAcceptedChats.delete(chatId), 15000); // Keep for 15 seconds

                        // Optimistically update UI immediately
                        const optimisticChat = { 
                            id: chatId, 
                            userName: chatName, 
                            status: 'active',
                            chatCategory: selectedCategory,
                            lastMessageTime: new Date()
                        };
                        
                        // Update local cache if exists
                        if (Array.isArray(latestChats)) {
                            const existingIndex = latestChats.findIndex(c => c.id === chatId);
                            if (existingIndex !== -1) {
                                latestChats[existingIndex] = { ...latestChats[existingIndex], ...optimisticChat };
                            }
                        }

                        // Force load chat with active status
                        await loadChat(optimisticChat);

                        if (btn) {
                            btn.innerHTML = 'Chat Accepted';
                            btn.disabled = true;
                        }
                        
                        // Fetch latest data in background
                        fetchChats();
                    } else {
                        throw new Error(result.error || 'Unknown error');
                    }
                } catch (error) {
                    console.error('Error accepting chat:', error);
                    
                    // Handle AbortError or Timeout specifically
                    if (error.name === 'AbortError' || error.message.includes('aborted')) {
                        console.log('Request timed out or aborted, checking if chat was accepted anyway...');
                        // Check if the chat status actually changed
                        try {
                            const chats = await fetchChats();
                            const updatedChat = Array.isArray(chats) ? chats.find(c => c.id === chatId) : null;
                            
                            if (updatedChat && updatedChat.status === 'active') {
                                console.log('Chat was accepted despite timeout');
                                await loadChat(updatedChat);
                                if (btn) {
                                    btn.innerHTML = 'Chat Accepted';
                                    btn.disabled = true;
                                }
                                return; // Exit successfully
                            }
                        } catch (checkError) {
                            console.error('Failed to verify chat status:', checkError);
                        }
                        alert('Request timed out. Please check your internet connection and try again.');
                    } else {
                        alert('Failed to accept chat: ' + error.message);
                    }

                    // Restore button if failed
                    if(btn && originalContent && !btn.disabled) { // Only restore if we didn't succeed above
                        btn.innerHTML = originalContent;
                        btn.disabled = false;
                    }
                }
            };

            // Fetch Messages
            async function fetchMessages() {
                if (!currentChatId || (currentChatStatus === 'pending' || currentChatStatus === 'waiting')) return;
                
                try {
                    const response = await fetch(`api/support_chat.php?action=get_messages&chat_id=${currentChatId}`);
                    const result = await response.json();
                    
                    if (result.messages) {
                        lastFetchedMessages = result.messages;
                        renderMessages(lastFetchedMessages);
                    } else if (result.error) {
                        console.error('API Error:', result.error);
                        // Only show error if we don't have messages yet
                        if (messagesArea.innerHTML.includes('Loading conversation...')) {
                            messagesArea.innerHTML = `
                                <div class="h-full flex flex-col items-center justify-center text-red-400">
                                    <svg class="w-8 h-8 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    <p class="text-sm">${result.error}</p>
                                </div>
                            `;
                        }
                    }
                } catch (error) {
                    console.error('Error fetching messages:', error);
                    // Don't show error UI for transient network errors during polling, just log it
                } finally {
                    // Schedule next poll if still active
                    if (currentChatId && currentChatStatus === 'active') {
                        window.messagePollTimeout = setTimeout(fetchMessages, 1000);
                    }
                }
            }

            // Render Messages
            function renderMessages(messages) {
                messagesArea.innerHTML = '';
                
                // Combine fetched messages with pending messages
                const allMessages = [...messages, ...pendingMessages];

                if (allMessages.length === 0) {
                    messagesArea.innerHTML = `
                        <div class="h-full flex flex-col items-center justify-center text-slate-400">
                            <p class="text-sm">No messages yet. Start the conversation!</p>
                        </div>
                    `;
                    return;
                }

                let lastDate = null;
                
                allMessages.forEach(msg => {
                    // Handle timestamp: Firestore timestamp or ISO string or JS Date
                    let dateObj;
                    if (msg.timestamp instanceof Date) {
                        dateObj = msg.timestamp;
                    } else if (msg.timestamp && msg.timestamp.seconds) {
                        dateObj = new Date(msg.timestamp.seconds * 1000);
                    } else {
                        dateObj = new Date(msg.timestamp);
                    }
                    
                    const date = dateObj.toLocaleDateString();
                    if (date !== lastDate) {
                        const dateDiv = document.createElement('div');
                        dateDiv.className = 'flex justify-center my-4';
                        dateDiv.innerHTML = `<span class="bg-slate-100 text-slate-500 text-xs px-3 py-1 rounded-full">${date}</span>`;
                        messagesArea.appendChild(dateDiv);
                        lastDate = date;
                    }

                    const isMe = msg.senderId === '<?php echo $_SESSION['user_id'] ?? ''; ?>';
                    const isSystem = msg.isSystem === true;
                    const isPending = msg.isPending === true;
                    
                    const div = document.createElement('div');
                    
                    if (isSystem) {
                        div.className = 'flex justify-center mb-4';
                        div.innerHTML = `<span class="text-xs text-slate-400 italic bg-slate-50 px-3 py-1 rounded-full">${msg.text}</span>`;
                    } else {
                        div.className = `flex ${isMe ? 'justify-end' : 'justify-start'} mb-4 ${isPending ? 'opacity-70' : ''}`;
                        div.innerHTML = `
                            <div class="max-w-[75%] ${isMe ? 'bg-sky-600 text-white rounded-l-2xl rounded-tr-2xl' : 'bg-white border border-slate-200 text-slate-800 rounded-r-2xl rounded-tl-2xl'} p-3 shadow-sm relative group">
                                <p class="text-sm leading-relaxed">${msg.text || msg.message}</p>
                                <span class="text-[10px] ${isMe ? 'text-sky-100' : 'text-slate-400'} block text-right mt-1 opacity-70 flex items-center justify-end gap-1">
                                    ${dateObj.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}
                                    ${isPending ? '<svg class="w-3 h-3 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>' : ''}
                                </span>
                            </div>
                        `;
                    }
                    messagesArea.appendChild(div);
                });
                
                messagesArea.scrollTop = messagesArea.scrollHeight;
            }

            // Send Message
            if (messageForm) {
                messageForm.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const message = messageInput.value.trim();
                    if (!message || !currentChatId) return;
                    
                    // Optimistic Update
                    const tempId = 'temp-' + Date.now();
                    const optimisticMsg = {
                        text: message,
                        senderId: '<?php echo $_SESSION['user_id'] ?? ''; ?>',
                        timestamp: new Date(),
                        isPending: true,
                        id: tempId
                    };
                    
                    pendingMessages.push(optimisticMsg);
                    messageInput.value = '';
                    renderMessages(lastFetchedMessages); // Re-render with pending message

                    try {
                        const formData = new FormData();
                        formData.append('chat_id', currentChatId);
                        formData.append('text', message);
                        
                        const response = await fetch('api/support_chat.php?action=send_message', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const result = await response.json();
                        if (result.success) {
                            // Remove from pending
                            pendingMessages = pendingMessages.filter(m => m.id !== tempId);
                            fetchMessages();
                            fetchChats(); // Update last message in list
                        } else {
                            console.error('Failed to send message');
                            pendingMessages = pendingMessages.filter(m => m.id !== tempId);
                            renderMessages(lastFetchedMessages);
                            alert('Failed to send message');
                        }
                    } catch (error) {
                        console.error('Error sending message:', error);
                        pendingMessages = pendingMessages.filter(m => m.id !== tempId);
                        renderMessages(lastFetchedMessages);
                        alert('Error sending message');
                    }
                });
                
                // Enter to send
                messageInput.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        messageForm.dispatchEvent(new Event('submit'));
                    }
                });
            }

            // Initial Load
            if (document.getElementById('chatList')) {
                fetchChats();
                chatPollInterval = setInterval(fetchChats, 5000);
            }
        });

        // Updated confirmation functions to use modals instead of browser confirm
        window.showApproveConfirmation = function(collection, docId, reporterName, categoryType) {
            console.log('showApproveConfirmation called:', { collection, docId, reporterName, categoryType });
            showApproveModal(collection, docId, reporterName, categoryType);
        }

        window.showDeclineConfirmation = function(collection, docId, reporterName, categoryType) {
            console.log('showDeclineConfirmation called:', { collection, docId, reporterName, categoryType });
            showDeclineModal(collection, docId, reporterName, categoryType);
        }

        // Make modal functions globally accessible
        window.showApproveModal = showApproveModal;
        window.closeApproveModal = closeApproveModal;
        window.confirmApprove = confirmApprove;
        window.showDeclineModal = showDeclineModal;
        window.closeDeclineModal = closeDeclineModal;
        window.confirmDecline = confirmDecline;
        window.updateReportStatus = updateReportStatus;
