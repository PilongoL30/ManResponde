    <script>
    // ========================================
    // DARK MODE FUNCTIONALITY
    // ========================================
    function toggleTheme() {
        const html = document.documentElement;
        const isDark = html.classList.toggle('dark');
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
        
        // Update toggle icon
        const icons = document.querySelectorAll('.theme-toggle-icon');
        icons.forEach(icon => {
            icon.className = isDark ? 'theme-toggle-icon fas fa-moon' : 'theme-toggle-icon fas fa-sun';
        });
        
        // Optional: Show toast notification
        if (typeof showToast === 'function') {
            showToast(isDark ? '🌙 Dark mode enabled' : '☀️ Light mode enabled', 'success');
        }
    }
    
    // Initialize theme on page load
    (function() {
        const savedTheme = localStorage.getItem('theme');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        const isDark = savedTheme === 'dark' || (!savedTheme && prefersDark);
        
        if (isDark) {
            document.documentElement.classList.add('dark');
            const icons = document.querySelectorAll('.theme-toggle-icon');
            icons.forEach(icon => {
                icon.className = 'theme-toggle-icon fas fa-moon';
            });
        }
    })();
    
    // Listen for system theme changes
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
        if (!localStorage.getItem('theme')) {
            if (e.matches) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        }
    });
    
    // ========================================
    // PWA SERVICE WORKER REGISTRATION
    // ========================================
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('sw.js')
                .then((registration) => {
                    console.log('✅ Service Worker registered:', registration.scope);
                })
                .catch((error) => {
                    console.log('❌ Service Worker registration failed:', error);
                });
        });
    }
    
    // PWA Install Prompt
    let deferredPrompt;
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        
        // Show install button or banner (optional)
        console.log('💡 PWA can be installed');
    });
    
    // Function to trigger PWA install
    function installPWA() {
        if (deferredPrompt) {
            deferredPrompt.prompt();
            deferredPrompt.userChoice.then((choiceResult) => {
                if (choiceResult.outcome === 'accepted') {
                    console.log('✅ User accepted PWA install');
                } else {
                    console.log('❌ User dismissed PWA install');
                }
                deferredPrompt = null;
            });
        }
    }
    </script>
    
    <script>
        window.currentUserId = '<?php echo $_SESSION['user_id'] ?? ''; ?>';
        window.categories = <?php echo json_encode($categories); ?>;

        // Set your Firebase Web config here to enable realtime updates (onSnapshot).
        window.FIREBASE_CLIENT_CONFIG = {
            apiKey: "AIzaSyDiNgvmttAwhAjPthjJtcZ1Hr9PLWnhErQ", // Firebase Web config
            authDomain: "ibantayv2.firebaseapp.com",
            projectId: "ibantayv2"
        };
    </script>
    <script src="assets/js/dashboard-core.js"></script>
    
    <script>
    // Ensure theme preference is applied ASAP
    (function() {
      try {
        // Force light mode as per user request
        document.documentElement.classList.remove('dark');
        localStorage.setItem('theme', 'light');
      } catch(e) {}
    })();
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
        
            // Request notification permission on page load
            if (Notification && Notification.permission === 'default') {
                Notification.requestPermission().then(permission => {
                    console.log('Notification permission:', permission);
                });
            }
    
        // window.categories moved to global scope at top of file
    
        // Helper function for SVG icons in JavaScript
        // --- HELPER FUNCTIONS ---
        // Moved to assets/js/main.js: svg_icon, animateCount, normalizeFirebaseReportData, formatFirebaseTimestamp
    
        // --- TOAST NOTIFICATIONS ---
        // Moved to assets/js/main.js: showToast

        // --- NOTIFICATION SOUND ---
        // Moved to assets/js/main.js: playNotificationSound, showNotificationWithSound
    
        // --- API & FORM HANDLING ---
        // Moved to assets/js/main.js: handleApiFormSubmit, showDeclineConfirmation, handleStatusUpdate, moveReportToSection, addReportToAppropriateTab, updateTabCounts, updateStatusCounters, updateProgressBars
        

        
        // Original handleApiFormSubmit preserved for other forms
        window.handleApiFormSubmitOriginal = async function(form, button) {
            const result = await handleApiFormSubmit(form, button);
            if (!result || !result.success) {
                // Re-enable real-time sync if update failed
                if (typeof window.setStatusUpdateInProgress === 'function') {
                    window.setStatusUpdateInProgress(false);
                }
                return;
            }

            const docId = (form.querySelector('input[name="docId"]')?.value || '').trim();
            if (docId && newStatus) {
                updateActivityItemStatus(docId, newStatus);
            }

            // Close modal if not in a table row
            if (!row && typeof window.closeReportModal === 'function') {
                window.closeReportModal();
            }
            
            // Refresh admin statistics if on dashboard view
            if (window.location.search.includes('view=dashboard') || !window.location.search.includes('view=')) {
                refreshAdminStats();
            }
            
            // Refresh staff data if on staff view
            if (!window.location.search.includes('view=') && typeof renderStaffReports === 'function' && window.staffData) {
                // Quick update: update the specific report status in the UI first
                const docId = (form.querySelector('input[name="docId"]')?.value || '').trim();
                const collection = (form.querySelector('input[name="collection"]')?.value || '').trim();
                
                if (docId && collection) {
                    // Find the slug for this collection
                    const slug = Object.keys(categories).find(key => categories[key].collection === collection);
                    
                    if (slug) {
                        // IMMEDIATE VISUAL FEEDBACK: Update tab counts right away
                        if (typeof window.forceUpdateTabCounts === 'function') {
                            window.forceUpdateTabCounts(slug, docId, newStatus);
                        }
                        
                        // Update the report status in the current data
                        if (window.staffData && window.staffData.cards) {
                            Object.keys(window.staffData.cards).forEach(slug => {
                                const reports = window.staffData.cards[slug];
                                const reportIndex = reports.findIndex(r => r.id === docId);
                                if (reportIndex !== -1) {
                                    reports[reportIndex].status = newStatus;
                                    
                                    return;
                                }
                            });
                        }
                        
                        // Double-check tab counts are updated after a short delay
                        setTimeout(() => {
                            if (typeof window.manualUpdateTabCounts === 'function') {
                                window.manualUpdateTabCounts(slug);
                            }
                        }, 100);
                    }
                }
                
                // Also do a full refresh to ensure data consistency
                setTimeout(async () => {
                    const formData = new FormData();
                    formData.append('api_action', 'load_staff_data');
                    
                    try {
                        const response = await fetch(window.location.href, {
                            method: 'POST',
                            body: formData
                        });
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            window.staffData = result.data;
                            renderStaffReports(result.data.cards, categories);
                            
                            // Update tab counts after re-render to ensure they're correct
                            if (typeof window.updateTabCounts === 'function' && docId && collection) {
                                const slug = Object.keys(categories).find(key => categories[key].collection === collection);
                                if (slug) {
                                    // Update tab counts immediately after re-render
                                    window.updateTabCounts(slug, docId, newStatus);
                                }
                            }
                        }
                    } catch (error) {
                        console.error('Error refreshing staff data:', error);
                    }
                }, 300); // Reduced delay to ensure the server has processed the update
                
                // Re-enable real-time sync after status update is complete
                setTimeout(() => {
                    if (typeof window.setStatusUpdateInProgress === 'function') {
                        window.setStatusUpdateInProgress(false);
                    }
                }, 500); // Wait 0.5 seconds after status update to re-enable sync
            }
        };
        
        // Update the existing handleUserVerification function
        window.handleUserVerification = async function(event) {
            event.preventDefault();
            const form = event.currentTarget;
            const button = form.querySelector('button[type="submit"]');
            const uid = form.querySelector('input[name="uid"]')?.value;
            
            const formData = new FormData();
            formData.append('api_action', 'verify_user');
            formData.append('uid', uid);
            formData.append('decision', form.querySelector('input[name="newStatus"]')?.value);

            const btnSpinner = '<?php echo svg_icon('spinner', 'w-4 h-4 animate-spin-fast'); ?>';
            const btnOriginalContent = button.innerHTML;
            
            button.innerHTML = btnSpinner;
            button.disabled = true;

            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                if (!response.ok) throw new Error('Network response was not ok.');
                
                const result = await response.json();

                if (result.success) {
                    showToast(result.message, 'success');
                    // For user verification view, remove the user card
                    const userCard = form.closest('.user-card') || form.closest('tr.user-row');
                    if (userCard) {
                        userCard.classList.add('animate-fade-out-down');
                        userCard.addEventListener('animationend', () => userCard.remove());
                    }
                    
                    // Refresh the pending users list to show updated counts
                    if (window.location.search.includes('view=verify-users')) {
                        // Immediate refresh to show updated list
                        setTimeout(() => {
                            loadPendingUsers(currentPage);
                        }, 100); // Faster refresh
                    }
                    
                    // Refresh admin statistics if on dashboard view
                    if (window.location.search.includes('view=dashboard') || !window.location.search.includes('view=')) {
                        refreshAdminStats();
                    }
                } else {
                    throw new Error(result.message || 'An unknown error occurred.');
                }
            } catch (error) {
                showToast(error.message, 'error');
                if (document.body.contains(button)) {
                    button.innerHTML = btnOriginalContent;
                    button.disabled = false;
                }
            }
        };

        // Legacy Create Staff/Responder forms removed (unified into createAccountForm)

        // Create Account Logic moved to assets/js/create-account.js

        // Staff Statistics and Management
        if (window.location.search.includes('view=create-staff')) {
            loadStaffData();

            // Auto-refresh staff data every 30 seconds
            setInterval(loadStaffData, 30000);
        }

        async function loadStaffData() {
            try {
                console.log('Loading staff data...');

                // Get DOM elements
                const staffList = document.getElementById('staffList');
                const staffLoading = document.getElementById('staffLoading');
                const staffEmpty = document.getElementById('staffEmpty');
                const totalStaffCount = document.getElementById('totalStaffCount');
                const activeStaffCount = document.getElementById('activeStaffCount');
                const reportsAssignedCount = document.getElementById('reportsAssignedCount');

                if (!staffList || !staffLoading) return;

                // Show loading
                staffLoading.classList.remove('hidden');
                staffEmpty.classList.add('hidden');

                // Fetch staff data
                const response = await fetch('api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'api_action=get_staff_data'
                });

                const result = await response.json();

                if (result.success) {
                    const staffData = result.data;
                    console.log('Staff data loaded:', staffData);

                    // Update statistics
                    if (totalStaffCount) totalStaffCount.textContent = staffData.total || 0;
                    if (activeStaffCount) activeStaffCount.textContent = staffData.active || 0;
                    if (reportsAssignedCount) reportsAssignedCount.textContent = staffData.reportsAssigned || 0;

                    // Update staff list
                    updateStaffList(staffData.staff || []);
                } else {
                    console.error('Failed to load staff data:', result.message);
                    showStaffError();
                }

            } catch (error) {
                console.error('Error loading staff data:', error);
                showStaffError();
            }
        }

        function updateStaffList(staff) {
            const staffList = document.getElementById('staffList');
            const staffLoading = document.getElementById('staffLoading');
            const staffEmpty = document.getElementById('staffEmpty');

            if (!staffList) return;

            // Hide loading
            staffLoading.classList.add('hidden');

            if (staff.length === 0) {
                staffEmpty.classList.remove('hidden');
                staffList.innerHTML = '';
                return;
            }

            staffEmpty.classList.add('hidden');

            // Generate staff list HTML
            const staffHtml = staff.map(staffMember => {
                const isActive = staffMember.status === 'active';
                const categoryCount = staffMember.categories ? staffMember.categories.length : 0;

                return `
                    <div class="bg-white rounded-lg border border-slate-200 p-4 hover:shadow-md transition-shadow">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-gradient-to-br from-sky-500 to-blue-600 rounded-full flex items-center justify-center text-white font-bold">
                                    ${staffMember.name ? staffMember.name.charAt(0).toUpperCase() : '?'}
                                </div>
                                <div>
                                    <h4 class="font-semibold text-slate-800">${staffMember.name || 'Unknown'}</h4>
                                    <p class="text-sm text-slate-600">${staffMember.email || 'No email'}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-4">
                                <div class="text-right">
                                    <div class="text-sm font-medium ${isActive ? 'text-green-600' : 'text-slate-500'}">
                                        ${isActive ? 'Active' : 'Inactive'}
                                    </div>
                                    <div class="text-xs text-slate-500">
                                        ${categoryCount} categories
                                    </div>
                                </div>
                                <div class="w-2 h-2 rounded-full ${isActive ? 'bg-green-400' : 'bg-slate-400'}"></div>
                            </div>
                        </div>
                        ${categoryCount > 0 ? `
                            <div class="mt-3 flex flex-wrap gap-1">
                                ${staffMember.categories.slice(0, 3).map(cat =>
                                    `<span class="px-2 py-1 bg-slate-100 text-slate-600 rounded text-xs">${cat}</span>`
                                ).join('')}
                                ${categoryCount > 3 ? `<span class="px-2 py-1 bg-slate-100 text-slate-600 rounded text-xs">+${categoryCount - 3}</span>` : ''}
                            </div>
                        ` : ''}
                    </div>
                `;
            }).join('');

            staffList.innerHTML = staffHtml;
        }

        function showStaffError() {
            const staffLoading = document.getElementById('staffLoading');
            const staffEmpty = document.getElementById('staffEmpty');

            if (staffLoading) staffLoading.classList.add('hidden');
            if (staffEmpty) {
                staffEmpty.classList.remove('hidden');
                staffEmpty.innerHTML = `
                    <div class="text-center py-8">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12 mx-auto mb-3 text-red-400">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                        </svg>
                        <p class="text-sm text-red-600 font-medium mb-1">Failed to load staff data</p>
                        <p class="text-xs text-slate-500">Please try refreshing the page</p>
                    </div>
                `;
            }
        }

        // --- MODAL SCRIPT ---
        // Function to fetch report data directly from database
        async function fetchReportDataDirectly(collection, docId) {
            try {
                const fd = new FormData();
                fd.append('api_action', 'get_report_data');
                fd.append('collection', collection);
                fd.append('docId', docId);
                
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 3000); // 3 second timeout
                
                const res = await fetch(window.location.href, {
                    method: 'POST',
                    body: fd,
                    signal: controller.signal
                });
                
                clearTimeout(timeoutId);
                
                if (!res.ok) {
                    throw new Error(`HTTP ${res.status}: ${res.statusText}`);
                }
                
                const json = await res.json();
                
                if (!json || !json.success) {
                    throw new Error(json?.message || 'Failed to fetch report data');
                }
                
                return json.data;
            } catch (error) {
                if (error.name === 'AbortError') {
                    console.log('Database fetch timed out, keeping current purpose value');
                } else {
                    console.error('Error fetching report data directly:', error);
                }
                return null;
            }
        }
        
        window.showReportModal = function(btn) {
            const reportModal = document.getElementById('reportModal');
            const modalContent = document.getElementById('modalContent');
            const ds = btn.dataset;
            
            // Debug: Log all button data attributes
            console.log('=== Report Modal Debug ===');
            console.log('Button dataset:', ds);
            console.log('Contact value:', ds.contact);
            console.log('Collection:', ds.collection);
            console.log('Doc ID:', ds.id || ds.docid);
            console.log('Full dataset keys:', Object.keys(ds));
    
            const setText = (id, v) => {
                const element = document.getElementById(id);
                if (element) {
                    const value = v && v.trim() ? v : '—';
                    element.textContent = value;
                    console.log(`Set ${id} to: "${value}"`);
                }
            };
    
            // Handle field mapping for different report types
            setText('m_fullName', ds.fullname);
            setText('m_contact', ds.contact);
            setText('m_location', ds.location);
            setText('m_reporterId', ds.reporterid);

            // --- MAP INITIALIZATION ---
            const mapContainer = document.getElementById('m_map_container');
            const mapStatus = document.getElementById('m_map_status');
            const googleBtn = document.getElementById('m_btn_google');
            const wazeBtn = document.getElementById('m_btn_waze');
            window.reportMapLat = null;
            window.reportMapLng = null;
            
            // Reset map container
            if (mapContainer) {
                mapContainer.classList.add('hidden');
                if (window.reportMap) {
                    window.reportMap.remove();
                    window.reportMap = null;
                }
            }
            if (googleBtn) {
                googleBtn.disabled = true;
                googleBtn.onclick = null;
            }
            if (wazeBtn) {
                wazeBtn.disabled = true;
                wazeBtn.onclick = null;
            }

            if (ds.location && ds.location !== '—' && ds.location.trim() !== '' && mapContainer) {
                mapContainer.classList.remove('hidden');
                if (mapStatus) mapStatus.textContent = 'Locating...';
                
                // Function to init map
                const enableNavButtons = (lat, lng) => {
                    window.reportMapLat = lat;
                    window.reportMapLng = lng;
                    const googleUrl = `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}`;
                    const wazeUrl = `https://waze.com/ul?ll=${lat},${lng}&navigate=yes`;
                    if (googleBtn) {
                        googleBtn.disabled = false;
                        googleBtn.onclick = () => window.open(googleUrl, '_blank');
                    }
                    if (wazeBtn) {
                        wazeBtn.disabled = false;
                        wazeBtn.onclick = () => window.open(wazeUrl, '_blank');
                    }
                };

                const initMap = (lat, lng, label) => {
                    setTimeout(() => {
                        if (window.reportMap) {
                            window.reportMap.remove();
                            window.reportMap = null;
                        }
                        
                        // Create map instance
                        window.reportMap = L.map('m_map').setView([lat, lng], 16);
                        
                        // Add OpenStreetMap tile layer
                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                        }).addTo(window.reportMap);
                        
                        // Add marker
                        L.marker([lat, lng]).addTo(window.reportMap)
                            .bindPopup(label)
                            .openPopup();

                        enableNavButtons(lat, lng);
                            
                        // Force map redraw after modal animation to prevent gray tiles
                        setTimeout(() => {
                            window.reportMap.invalidateSize();
                        }, 300);
                        
                        if (mapStatus) mapStatus.textContent = 'Location found';
                    }, 100);
                };

                // 1. Try to parse coordinates from string (e.g. "14.5, 121.0")
                const coordMatch = ds.location.match(/(-?\d+\.\d+),\s*(-?\d+\.\d+)/);
                
                if (coordMatch) {
                    const lat = parseFloat(coordMatch[1]);
                    const lng = parseFloat(coordMatch[2]);
                    initMap(lat, lng, ds.location);
                } else {
                    // 2. Geocode address using Nominatim
                    // Append 'Philippines' context if not present to improve accuracy
                    let queryStr = ds.location;
                    if (!queryStr.toLowerCase().includes('philippines')) {
                        queryStr += ', Philippines';
                    }
                    
                    const query = encodeURIComponent(queryStr);
                    
                    fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${query}&limit=1`)
                        .then(res => res.json())
                        .then(data => {
                            if (data && data.length > 0) {
                                const lat = parseFloat(data[0].lat);
                                const lng = parseFloat(data[0].lon);
                                initMap(lat, lng, ds.location);
                            } else {
                                if (mapStatus) mapStatus.textContent = 'Location not found on map';
                                // Fallback to Manila
                                initMap(14.5995, 120.9842, 'Location not found: ' + ds.location);
                            }
                        })
                        .catch(err => {
                            console.error('Geocoding error:', err);
                            if (mapStatus) mapStatus.textContent = 'Map error';
                        });
                }
            }
            
            // If contact is empty or "—", fetch from Firebase to get mobileNumber
            if (!ds.contact || ds.contact === '—' || ds.contact.trim() === '') {
                console.log('Contact is empty, attempting Firebase fallback...');
                const docId = ds.id || ds.docid || btn.getAttribute('data-id');
                if (docId && ds.collection) {
                    console.log(`Fetching contact from Firebase: ${ds.collection}/${docId}`);
                    fetchReportDataDirectly(ds.collection, docId).then(function(data) {
                        console.log('Firebase fallback response:', data);
                        if (data && data.mobileNumber) {
                            console.log('Setting contact from Firebase:', data.mobileNumber);
                            setText('m_contact', data.mobileNumber);
                        } else {
                            console.log('No mobileNumber in Firebase response');
                        }
                    }).catch(function(err) {
                        console.warn('Contact fetch fallback failed:', err);
                    });
                } else {
                    console.log('Missing docId or collection for Firebase fallback');
                }
            } else {
                console.log('Contact already has value:', ds.contact);
            }

            


            // Simple purpose extraction - same as other fields
            let purposeValue = ds.purpose || ds.Purpose || ds.description || ds.Description || btn.getAttribute('data-purpose') || btn.dataset.purpose;

            // Simple fallback - same as other fields
            if (!purposeValue || purposeValue === '—' || purposeValue === '') {
                purposeValue = '—';
            }

            // Set the purpose immediately with available data (same as other fields)
            setText('m_purpose', purposeValue || '—');
            
            
            // If tanod report or other report and purpose still empty, fetch directly from DB as a fallback
            if (ds.collection === 'tanod_reports' || ds.collection === 'other_reports') {
                const pv = (purposeValue || '').trim();
                if (!pv || pv === '—') {
                    try {
                        const docId = ds.id || ds.docid || btn.getAttribute('data-id');
                        if (docId) {
                            fetchReportDataDirectly(ds.collection, docId).then(function(data){
                                if (data) {
                                    const pur = (data.purpose && String(data.purpose).trim()) 
                                                || (data.description && String(data.description).trim()) 
                                                || '';
                                    if (pur) {
                                        setText('m_purpose', pur);
                                    }
                                }
                            }).catch(function(err){
                                console.warn('Purpose fetch fallback failed:', err);
                            });
                        }
                    } catch (e) {
                        console.warn('Purpose fetch fallback error:', e);
                    }
                }
            }
// Show Purpose field for tanod reports and other reports, hide for others
            const purposeContainer = document.getElementById('m_purpose').parentElement;
            if (ds.collection === 'tanod_reports' || ds.collection === 'other_reports') {
                purposeContainer.style.display = '';
            } else {
                purposeContainer.style.display = 'none';
            }
            
            // Handle timestamp - ensure it's displayed properly  
            let timestampDisplay = ds.timestamp;
            
            // Debug: Log the timestamp value
            console.log('Modal timestamp data:', {
                raw: ds.timestamp,
                type: typeof ds.timestamp
            });
            
            // If timestamp is empty, try to format it again if we have raw data
            if (!timestampDisplay || timestampDisplay === '—' || timestampDisplay === '') {
                // Try to get the raw timestamp and format it
                if (btn.dataset.rawtimestamp) {
                    try {
                        timestampDisplay = formatFirebaseTimestamp(btn.dataset.rawtimestamp);
                        console.log('Formatted timestamp from raw data:', timestampDisplay);
                    } catch (error) {
                        console.error('Error formatting raw timestamp:', error);
                        timestampDisplay = 'Invalid timestamp';
                    }
                } else {
                    timestampDisplay = 'No timestamp available';
                }
            }
            
            // If still no timestamp display, try to use current timestamp formatting
            if (!timestampDisplay || timestampDisplay === 'No timestamp available') {
                // Look for the timestamp in the table row
                const row = btn.closest('tr.report-row');
                if (row) {
                    const timestampCell = row.querySelector('td:nth-child(3)');
                    if (timestampCell && timestampCell.textContent.trim() !== '—' && timestampCell.textContent.trim() !== '') {
                        timestampDisplay = timestampCell.textContent.trim();
                        console.log('Got timestamp from table cell:', timestampDisplay);
                    }
                }
            }
            
            setText('m_timestamp', timestampDisplay);

            // Show tanod report details for tanod_reports on both staff and admin sides
            if (ds.collection === 'tanod_reports') {
                setText('m_contact', ds.mobileNumber || ds.mobileNumber || '—');
                setText('m_fullName', ds.fullName || ds.fullname || '—');
                setText('m_location', ds.location || ds.Location || '—');
                setText('m_purpose', ds.purpose || ds.Purpose || ds.description || ds.Description || '—');
                setText('m_reporterId', ds.reporterId || ds.reporterid || ds.ReporterId || '—');
                setText('m_status', ds.status || ds.Status || 'Pending');
                setText('m_timestamp', timestampDisplay);
            }
const meta = categories[ds.slug] || {};
            const color = meta.color || 'gray';
            document.getElementById('m_header').innerHTML = `
                <div class="w-10 h-10 rounded-lg bg-${color}-100 text-${color}-600 flex items-center justify-center flex-shrink-0">
                    <?php echo svg_icon($meta['icon'] ?? 'question-mark-circle', 'w-6, h-6'); ?>
                </div>
                <h3 class="text-lg font-bold text-slate-900">Report Details</h3>
            `;

            const statusEl = document.getElementById('m_status');
            const st = (ds.status || 'Pending').toLowerCase();
            statusEl.innerHTML = `<span class="h-2 w-2 rounded-full bg-current mr-2"></span>${ds.status || 'Pending'}`;
            statusEl.className = 'status-badge ml-2';
            if (st === 'approved') statusEl.classList.add('status-badge-success');
            else if (st === 'declined') statusEl.classList.add('status-badge-declined');
            else statusEl.classList.add('status-badge-pending');

            const imgEl = document.getElementById('m_image');
            const videoEl = document.getElementById('m_video');
            const videoSource = document.getElementById('m_video_source');
            const imgNone = document.getElementById('m_image_none');
            const link = document.getElementById('m_image_link');
            const mediaHint = document.getElementById('m_media_hint');

            if (ds.imageurl) {
                // Function to determine if URL is a video file
                const isVideo = (url) => {
                    const videoExtensions = ['.mp4', '.webm', '.ogg', '.avi', '.mov', '.wmv', '.flv', '.mkv', '.m4v', '.3gp'];
                    const urlLower = url.toLowerCase();
                    return videoExtensions.some(ext => urlLower.includes(ext));
                };
                
                // Function to get video MIME type
                const getVideoType = (url) => {
                    const urlLower = url.toLowerCase();
                    if (urlLower.includes('.mp4') || urlLower.includes('.m4v')) return 'video/mp4';
                    if (urlLower.includes('.webm')) return 'video/webm';
                    if (urlLower.includes('.ogg')) return 'video/ogg';
                    if (urlLower.includes('.avi')) return 'video/avi';
                    if (urlLower.includes('.mov')) return 'video/quicktime';
                    if (urlLower.includes('.wmv')) return 'video/x-ms-wmv';
                    if (urlLower.includes('.flv')) return 'video/x-flv';
                    if (urlLower.includes('.mkv')) return 'video/x-matroska';
                    if (urlLower.includes('.3gp')) return 'video/3gpp';
                    return 'video/mp4'; // Default fallback
                };
                
                if (isVideo(ds.imageurl)) {
                    // Show video
                    videoSource.src = ds.imageurl;
                    videoSource.type = getVideoType(ds.imageurl);
                    videoEl.load(); // Reload video element
                    videoEl.classList.remove('hidden');
                    imgEl.classList.add('hidden');
                    imgNone.classList.add('hidden');
                    link.href = ds.imageurl;
                    mediaHint.textContent = 'Click to open video in new tab or use controls to play.';
                    
                    // Add video error handling
                    videoEl.onerror = function() {
                        console.error('Failed to load video:', ds.imageurl);
                        videoEl.classList.add('hidden');
                        imgNone.classList.remove('hidden');
                        imgNone.textContent = 'Video failed to load';
                        mediaHint.textContent = 'Click link to open video in new tab.';
                    };
                    
                    // Add video load success handling
                    videoEl.onloadedmetadata = function() {
                        console.log('Video loaded successfully:', ds.imageurl);
                    };
                } else {
                    // Show image
                    imgEl.src = ds.imageurl;
                    imgEl.classList.remove('hidden');
                    videoEl.classList.add('hidden');
                    imgNone.classList.add('hidden');
                    link.href = ds.imageurl;
                    mediaHint.textContent = 'Click image to open full size.';
                    
                    // Add image error handling
                    imgEl.onerror = function() {
                        console.error('Failed to load image:', ds.imageurl);
                        imgEl.classList.add('hidden');
                        imgNone.classList.remove('hidden');
                        imgNone.textContent = 'Image failed to load';
                        mediaHint.textContent = 'Click link to open media in new tab.';
                    };
                }
            } else {
                imgEl.src = '';
                imgEl.classList.add('hidden');
                videoEl.classList.add('hidden');
                videoSource.src = '';
                imgNone.classList.remove('hidden');
                imgNone.textContent = 'No media provided';
                link.href = '#';
                mediaHint.textContent = 'No media attached to this report.';
            }
            
            const actionsContainer = document.getElementById('m_actions');
            const isFinal = st === 'approved' || st === 'declined';
            
            const approveBtnClass = isFinal ? 'btn-disabled' : 'btn-approve';
            const declineBtnClass = isFinal ? 'btn-disabled' : 'btn-decline';
            const disabledAttr = isFinal ? 'disabled' : '';
    
            actionsContainer.innerHTML = `
                <button type="button" class="btn ${approveBtnClass}" ${disabledAttr} title="Approve Report" onclick="showApproveConfirmation('${ds.collection}', '${ds.id}', '${ds.fullName}', '${ds.slug}')">
                    <?php echo svg_icon('check-circle', 'w-4 h-4'); ?><span>Approve</span>
                </button>
                <button type="button" class="btn ${declineBtnClass}" ${disabledAttr} title="Decline Report" onclick="showDeclineConfirmation('${ds.collection}', '${ds.id}', '${ds.fullName}', '${ds.slug}')">
                    <?php echo svg_icon('x-circle', 'w-4 h-4'); ?><span>Decline</span>
                </button>
            `;
            
            reportModal.classList.remove('pointer-events-none');
            reportModal.classList.add('opacity-100');
            modalContent.classList.remove('scale-95', 'opacity-0');
        };
        
        window.closeReportModal = function() {
            const reportModal = document.getElementById('reportModal');
            const modalContent = document.getElementById('modalContent');
            if (!reportModal || !modalContent) return;

            modalContent.classList.add('scale-95', 'opacity-0');
            reportModal.classList.remove('opacity-100');
            reportModal.classList.add('opacity-0');
            reportModal.addEventListener('transitionend', () => {
                reportModal.classList.add('pointer-events-none');
            }, { once: true });
        };
        
        // --- EXPORT FUNCTIONS ---
        window.showExportModal = function() {
            const exportModal = document.getElementById('exportModal');
            const modalContent = exportModal.querySelector('.relative');
            
            exportModal.classList.remove('opacity-0', 'pointer-events-none');
            modalContent.classList.remove('scale-95', 'opacity-0');
        };
        
        window.closeExportModal = function() {
            const exportModal = document.getElementById('exportModal');
            const modalContent = exportModal.querySelector('.relative');
            
            exportModal.classList.add('opacity-0', 'pointer-events-none');
            modalContent.classList.add('scale-95', 'opacity-0');
        };
        
        window.exportReports = function(format) {
            const category = document.getElementById('exportCategory').value;
            const url = `export_reports.php?format=${format}&category=${category}`;
            
            // Show loading state
            showToast('Preparing export...', 'info');
            
            // Create a temporary link to trigger download
            const link = document.createElement('a');
            link.href = url;
            link.download = '';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            // Close modal and show success message
            closeExportModal();
            setTimeout(() => {
                showToast(`Export completed! ${format.toUpperCase()} file downloaded.`, 'success');
            }, 1000);
        };
        
        // --- PROOF MODAL SCRIPT ---
        window.showProofModal = function(btn) {
            const proofModal = document.getElementById('proofModal');
            const modalContent = document.getElementById('proofModalContent');
            const ds = btn.dataset;

            document.getElementById('p_header').textContent = `Proof for ${ds.fullname}`;
            const imgEl = document.getElementById('p_image');
            const linkEl = document.getElementById('p_image_link');

            if (ds.proofurl) {
                console.log('Setting proof image URL:', ds.proofurl);
                
                // Clear any previous error messages
                const existingError = imgEl.parentNode.querySelector('.error-message');
                if (existingError) {
                    existingError.remove();
                }
                
                // Add loading state
                imgEl.style.opacity = '0.5';
                imgEl.alt = 'Loading...';
                linkEl.href = ds.proofurl;
                
                // Create a new image to test loading
                const testImg = new Image();
                testImg.onload = function() {
                    console.log('Image loaded successfully');
                    imgEl.src = ds.proofurl;
                    imgEl.alt = 'Proof of Residency';
                    imgEl.style.opacity = '1';
                };
                testImg.onerror = function() {
                    console.error('Failed to load image:', ds.proofurl);
                    imgEl.style.opacity = '1';
                    imgEl.alt = 'Failed to load image';
                    
                    // Show error message
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'error-message text-red-600 text-sm mt-2 text-center';
                    errorDiv.innerHTML = 'Failed to load image. <a href="' + ds.proofurl + '" target="_blank" class="underline text-blue-600">Click here to open directly</a>';
                    imgEl.parentNode.appendChild(errorDiv);
                };
                testImg.src = ds.proofurl;
            } else {
                console.error('No proof URL provided');
                imgEl.src = '';
                imgEl.alt = 'No image available';
                linkEl.href = '#';
            }

            proofModal.classList.remove('pointer-events-none');
            proofModal.classList.add('opacity-100');
            modalContent.classList.remove('scale-95', 'opacity-0');
        };

        window.closeProofModal = function() {
            const proofModal = document.getElementById('proofModal');
            const modalContent = document.getElementById('proofModalContent');
            if (!proofModal || !modalContent) return;

            modalContent.classList.add('scale-95', 'opacity-0');
            proofModal.classList.remove('opacity-100');
            proofModal.classList.add('opacity-0');
            proofModal.addEventListener('transitionend', () => {
                proofModal.classList.add('pointer-events-none');
            }, { once: true });
        };

        // --- ID MODAL SCRIPT FOR VERIFICATION DOCUMENTS ---
        window.showIdModal = function(btn, imageType) {
            const proofModal = document.getElementById('proofModal');
            const modalContent = document.getElementById('proofModalContent');
            const ds = btn.dataset;

            document.getElementById('p_header').textContent = `${imageType} - ${ds.fullname}`;
            const imgEl = document.getElementById('p_image');
            const linkEl = document.getElementById('p_image_link');

            if (ds.imageurl) {
                console.log('Setting ID image URL:', ds.imageurl);
                
                // Clear any previous error messages
                const existingError = imgEl.parentNode.querySelector('.error-message');
                if (existingError) {
                    existingError.remove();
                }
                
                // Add loading state
                imgEl.style.opacity = '0.5';
                imgEl.alt = 'Loading...';
                linkEl.href = ds.imageurl;
                
                // Create a new image to test loading
                const testImg = new Image();
                testImg.onload = function() {
                    console.log('ID image loaded successfully');
                    imgEl.src = ds.imageurl;
                    imgEl.alt = imageType;
                    imgEl.style.opacity = '1';
                };
                testImg.onerror = function() {
                    console.error('Failed to load ID image:', ds.imageurl);
                    imgEl.style.opacity = '1';
                    imgEl.alt = 'Failed to load image';
                    
                    // Show error message
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'error-message text-red-600 text-sm mt-2 text-center';
                    errorDiv.innerHTML = 'Failed to load image. <a href="' + ds.imageurl + '" target="_blank" class="underline text-blue-600">Click here to open directly</a>';
                    imgEl.parentNode.appendChild(errorDiv);
                };
                testImg.src = ds.imageurl;
            } else {
                console.error('No image URL provided');
                imgEl.src = '';
                imgEl.alt = 'No image available';
                linkEl.href = '#';
            }

            proofModal.classList.remove('pointer-events-none');
            proofModal.classList.add('opacity-100');
            modalContent.classList.remove('scale-95', 'opacity-0');
        };

        const segmentedStyle = document.createElement('style');
        segmentedStyle.innerHTML = `
            .segmented { display: inline-flex; align-items: center; gap: 4px; padding: 4px; border-radius: 9999px; background: rgba(226,232,240,0.7); border: 1px solid rgba(203,213,225,0.8); box-shadow: inset 0 1px 0 rgba(255,255,255,0.35); backdrop-filter: saturate(1.2); }
            .seg-btn { appearance: none; border: 0; background: transparent; color: #475569; font-weight: 700; font-size: 0.875rem; line-height: 1; padding: 0.5rem 0.75rem; border-radius: 9999px; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; transition: color .2s, transform .15s, background-color .2s, box-shadow .2s; will-change: transform; }
            .seg-btn:hover { color: #0c4a6e; transform: translateY(-1px); }
            .seg-btn:active { transform: translateY(0); }
            .seg-btn.active { background: #0284c7; color: #ffffff; box-shadow: 0 6px 14px rgba(2,132,199,0.25), inset 0 1px 0 rgba(255,255,255,.18); }
            .seg-btn .tab-count { min-width: 22px; height: 20px; padding: 0 6px; border-radius: 9999px; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800; letter-spacing: .2px; background: rgba(100,116,139,0.18); color: #475569; transition: background-color .2s, color .2s, transform .2s; }
            .seg-btn:hover .tab-count { transform: translateY(-1px); }
            .seg-btn.active .tab-count { background: rgba(255,255,255,0.28); color: #ffffff; }
            .panel-content { display: none; }
            .panel-content.active { display: block; }
        `;
        document.head.appendChild(segmentedStyle);

        (function() {
            const easeOutCubic = t => 1 - Math.pow(1 - t, 3);
            document.querySelectorAll('[data-countup]').forEach(el => {
                const target = Number(el.getAttribute('data-countup')) || 0;
                const dur = 900;
                const start = performance.now();
                function step(now) {
                    const p = Math.min(1, (now - start) / dur);
                    el.textContent = Math.round(target * easeOutCubic(p)).toLocaleString();
                    if (p < 1) requestAnimationFrame(step);
                }
                requestAnimationFrame(step);
            });
        })();

        (function() {
            document.querySelectorAll('.progress-seg').forEach(seg => {
                const w = seg.getAttribute('data-w') || '0%';
                seg.style.transition = 'width 900ms cubic-bezier(0.16, 1, 0.3, 1)';
                requestAnimationFrame(() => { seg.style.width = w; });
            });
        })();
        
        (function() {
            document.querySelectorAll('.segmented').forEach(group => {
                group.addEventListener('click', e => {
                    const btn = e.target.closest('.seg-btn');
                    if (!btn) return;
                    const tab = btn.dataset.tab;
                    const container = group.closest('.report-category-group');
                    if (!container) return;
                    group.querySelectorAll('.seg-btn').forEach(b => b.classList.toggle('active', b === btn));
                    container.querySelectorAll('.panel-content').forEach(p => {
                        p.classList.toggle('active', p.dataset.tab === tab);
                    });
                });
            });
        })();

        // Initialize global refresh function placeholder
        window.refreshRecentActivity = function() { 
            if (typeof loadRecentPage === 'function') {
                loadRecentPage(currentPage);
            } else {
                console.log('Recent activity refresh not initialized yet'); 
            }
        };

        (function() {
            const list = document.getElementById('activityList');
            if (!list) return;

            const pageSizeEl = document.getElementById('activityPageSize');
            const rangeEl   = document.getElementById('activityRange');
            const prevBtn   = document.getElementById('activityPrev');
            const nextBtn   = document.getElementById('activityNext');

            let total = 0;
            let currentPage = 1;
            let pageSize = pageSizeEl ? parseInt(pageSizeEl.value || '20', 10) : 20;

            // Enhanced loading with retry mechanism and better error handling
            async function loadRecentPage(page = 1, retryCount = 0) {
                const maxRetries = 3;
                const retryDelay = 1000 * Math.pow(2, retryCount); // Exponential backoff
                
                // Show loading state with better feedback only on first load or explicit page change
                // Don't show full loading spinner on background refreshes
                const isBackgroundRefresh = window.isBackgroundRefresh === true;
                window.isBackgroundRefresh = false; // Reset flag

                if (retryCount === 0 && !isBackgroundRefresh) {
                    list.innerHTML = `
                        <div class="text-center py-16">
                            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-gradient-to-br from-blue-100 to-purple-100 flex items-center justify-center">
                                <svg class="w-8 h-8 text-blue-500 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                            </div>
                            <p class="text-lg font-semibold text-gray-600">Loading Recent Activity</p>
                            <p class="text-sm text-gray-400 mt-1">Fetching latest emergency reports...</p>
                        </div>
                    `;
                }
                
                try {
                    const searchEl = document.getElementById('activitySearch');
                    const categoryEl = document.getElementById('activityCategory');
                    const statusEl = document.getElementById('activityStatus');
                    
                    const fd = new FormData();
                    fd.append('api_action', 'recent_feed');
                    fd.append('page', String(page));
                    fd.append('pageSize', String(pageSize));
                    fd.append('search', searchEl ? searchEl.value.trim() : '');
                    fd.append('category', categoryEl ? categoryEl.value : 'all');
                    fd.append('status', statusEl ? statusEl.value : 'all');
                    
                    // Add timestamp to prevent browser caching
                    fd.append('_t', Date.now());
                    
                    const res = await fetch(window.location.href, {
                        method: 'POST',
                        body: fd
                    });
                    
                    if (!res.ok) {
                        throw new Error(`HTTP ${res.status}: ${res.statusText}`);
                    }
                    
                    const json = await res.json();
                    
                    if (!json || !json.success) {
                        throw new Error(json?.message || 'Failed to load recent activity');
                    }
                    
                    total = Number(json.total || 0);
                    currentPage = Number(json.page || page);
                    const data = Array.isArray(json.data) ? json.data : [];
                    
                    // Store recent feed data globally for modal fallback
                    window.recentFeedData = data;
                    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
                    
                    const html = data.length > 0 ? data.map((row, index) => {
                        const st = String(row.status || 'Pending').toLowerCase();
                        const getStatusConfig = (status) => {
                            switch(status) {
                                case 'approved':
                                    return {
                                        bgColor: 'from-green-500 to-emerald-600',
                                        textColor: 'text-green-700',
                                        dotColor: 'bg-green-500',
                                        borderColor: 'border-green-200',
                                        label: 'Approved'
                                    };
                                case 'declined':
                                    return {
                                        bgColor: 'from-red-500 to-rose-600',
                                        textColor: 'text-red-700',
                                        dotColor: 'bg-red-500',
                                        borderColor: 'border-red-200',
                                        label: 'Declined'
                                    };
                                default:
                                    return {
                                        bgColor: 'from-yellow-500 to-amber-600',
                                        textColor: 'text-yellow-700',
                                        dotColor: 'bg-yellow-500',
                                        borderColor: 'border-yellow-200',
                                        label: 'Pending'
                                    };
                            }
                        };
                        
                        const statusConfig = getStatusConfig(st);
                        
                        return `
                        <li
                            onclick="showReportModal(this)" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();showReportModal(this);}"
                            role="button" tabindex="0"
                            data-slug="${esc(row.slug)}" data-id="${esc(row.id)}" data-collection="${esc(row.collection)}"
                            data-fullname="${esc(row.fullName)}" data-contact="${esc(row.mobileNumber || row.contact)}"
                            data-location="${esc(row.location)}" data-purpose="${esc(row.purpose)}"
                            data-latitude="${esc(row.latitude || (row.coordinates ? (row.coordinates._lat || row.coordinates.latitude) : ''))}"
                            data-longitude="${esc(row.longitude || (row.coordinates ? (row.coordinates._long || row.coordinates.longitude) : ''))}"
                            data-reporterid="${esc(row.reporterId)}" data-imageurl="${esc(row.imageUrl)}"
                            data-status="${esc(st)}" data-timestamp="${esc(row.tsDisplay)}"
                            class="glass-card p-5 cursor-pointer animate-fade-in-up group hover:scale-[1.02] transition-all duration-300"
                            style="--anim-delay: ${index * 50}ms"
                        >
                            <div class="flex items-start gap-4">
                                <div class="relative flex-shrink-0">
                                    <div class="w-14 h-14 rounded-2xl bg-gradient-to-br ${statusConfig.bgColor} flex items-center justify-center text-white shadow-lg">
                                        ${row.iconSvg}
                                    </div>
                                    <div class="absolute -top-1 -right-1 w-5 h-5 ${statusConfig.dotColor} rounded-full border-2 border-white shadow-sm animate-pulse"></div>
                                </div>
                                
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-start justify-between gap-3 mb-2">
                                        <div>
                                            <h4 class="text-base font-bold text-gray-800 mb-1">${esc(row.label)}</h4>
                                            <p class="text-sm font-semibold text-gray-600">${esc(row.fullName || 'Unknown')}</p>
                                        </div>
                                        <div class="text-right flex-shrink-0">
                                            <span class="text-xs text-gray-500 font-medium">${esc(row.tsDisplay)}</span>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center gap-2 mb-3">
                                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        </svg>
                                        <span class="text-sm text-gray-500 truncate">${esc(row.location || 'No location specified')}</span>
                                    </div>
                                    
                                    <div class="flex items-center justify-between">
                                        <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-semibold ${statusConfig.textColor} bg-gradient-to-r from-white to-gray-50 border ${statusConfig.borderColor} shadow-sm">
                                            <span class="w-2 h-2 rounded-full ${statusConfig.dotColor} animate-pulse"></span>
                                            ${statusConfig.label}
                                        </span>
                                        
                                        <div class="opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </li>`;
                    }).join('') : `
                        <div class="text-center py-16">
                            <div class="w-20 h-20 mx-auto mb-6 rounded-full bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center">
                                <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                                </svg>
                            </div>
                            <p class="text-xl font-semibold text-gray-600 mb-2">No Recent Activity</p>
                            <p class="text-gray-400">No emergency reports found matching your criteria</p>
                        </div>
                    `;

                    list.innerHTML = html;
                    const totalPages = Math.max(1, Math.ceil(total / pageSize));
                    if (rangeEl) {
                        const start = total ? ((currentPage - 1) * pageSize + 1) : 0;
                        const end = total ? Math.min(currentPage * pageSize, total) : 0;
                        rangeEl.textContent = `Showing ${start}-${end} of ${total}`;
                    }
                    if (prevBtn) prevBtn.disabled = currentPage <= 1;
                    if (nextBtn) nextBtn.disabled = currentPage >= totalPages;
                    
                    // Update activity count display with performance info
                    const countEl = document.getElementById('activityCount');
                    if (countEl) {
                        const filters = json.filters || {};
                        const hasFilters = filters.search || filters.category !== 'all' || filters.status !== 'all';
                        const perfInfo = json.executionTime ? ` (${json.executionTime})` : '';
                        countEl.textContent = hasFilters ? `${total} filtered results${perfInfo}` : `Last ${Math.min(total, 50)} updates${perfInfo}`;
                    }
                    
                } catch (error) {
                    console.error("Failed to load recent page:", error);
                    
                    if (retryCount < maxRetries && (error.message.includes('timeout') || error.message.includes('fetch'))) {
                        // Retry with exponential backoff
                        setTimeout(() => {
                            loadRecentPage(page, retryCount + 1);
                        }, retryDelay);
                        
                        list.innerHTML = `
                            <div class="text-center py-16">
                                <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-gradient-to-br from-yellow-100 to-orange-100 flex items-center justify-center">
                                    <svg class="w-8 h-8 text-yellow-500 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                    </svg>
                                </div>
                                <p class="text-lg font-semibold text-gray-600">Retrying Connection</p>
                                <p class="text-sm text-gray-400 mt-1">Attempt ${retryCount + 1} of ${maxRetries}</p>
                            </div>
                        `;
                    } else {
                        // Show error with retry button
                        list.innerHTML = `
                            <div class="text-center py-16">
                                <div class="w-20 h-20 mx-auto mb-6 rounded-full bg-gradient-to-br from-red-100 to-pink-100 flex items-center justify-center">
                                    <svg class="w-10 h-10 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.464 0L4.35 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                    </svg>
                                </div>
                                <p class="text-xl font-semibold text-red-600 mb-2">Connection Failed</p>
                                <p class="text-gray-500 mb-6 max-w-sm mx-auto">Unable to load recent activity: ${error.message}</p>
                                <button onclick="loadRecentPage(${page})" class="btn btn-primary glow">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                    </svg>
                                    Try Again
                                </button>
                            </div>
                        `;
                    }
                }
            }

            // Enhanced event listeners with real-time filtering
            if (pageSizeEl) pageSizeEl.addEventListener('change', () => {
                pageSize = parseInt(pageSizeEl.value || '20', 10) || 20;
                loadRecentPage(1);
            });
            
            if (prevBtn) prevBtn.addEventListener('click', () => currentPage > 1 && loadRecentPage(currentPage - 1));
            if (nextBtn) nextBtn.addEventListener('click', () => {
                const totalPages = Math.max(1, Math.ceil(total / pageSize));
                if (currentPage < totalPages) loadRecentPage(currentPage + 1);
            });

            // Real-time search functionality
            const searchEl = document.getElementById('activitySearch');
            const categoryEl = document.getElementById('activityCategory');
            const statusEl = document.getElementById('activityStatus');
            const resetEl = document.getElementById('activityReset');
            
            // Debounce function for search input
            function debounce(func, wait) {
                let timeout;
                return function executedFunction(...args) {
                    const later = () => {
                        clearTimeout(timeout);
                        func(...args);
                    };
                    clearTimeout(timeout);
                    timeout = setTimeout(later, wait);
                };
            }
            
            // Real-time search with debouncing
            if (searchEl) {
                searchEl.addEventListener('input', debounce(() => {
                    currentPage = 1; // Reset to first page when searching
                    loadRecentPage(1);
                }, 300));
            }
            
            // Instant category filtering
            if (categoryEl) {
                categoryEl.addEventListener('change', () => {
                    currentPage = 1;
                    loadRecentPage(1);
                });
            }
            
            // Instant status filtering
            if (statusEl) {
                statusEl.addEventListener('change', () => {
                    currentPage = 1;
                    loadRecentPage(1);
                });
            }
            
            // Reset filters
            if (resetEl) {
                resetEl.addEventListener('click', () => {
                    if (searchEl) searchEl.value = '';
                    if (categoryEl) categoryEl.value = 'all';
                    if (statusEl) statusEl.value = 'all';
                    currentPage = 1;
                    loadRecentPage(1);
                });
            }

            // Initial load with cache warming
            loadRecentPage(1);
            
            // Pre-warm cache for common filters
            setTimeout(() => {
                const warmCache = async () => {
                    try {
                        const fd = new FormData();
                        fd.append('api_action', 'recent_feed');
                        fd.append('page', '1');
                        fd.append('pageSize', '10');
                        fd.append('category', 'all');
                        fd.append('status', 'all');
                        await fetch(window.location.href, { method: 'POST', body: fd });
                    } catch (e) {
                        // Silent fail for cache warming
                    }
                };
                warmCache();
            }, 2000);

            // Expose loadRecentPage to global scope
            window.loadRecentPage = loadRecentPage;
            
            // Override global refresh function
            window.refreshRecentActivity = function() {
                window.isBackgroundRefresh = true; // Use background refresh to avoid spinner
                loadRecentPage(currentPage);
            };

            // Real-time polling (every 5 seconds)
            setInterval(() => {
                // Only poll if tab is visible to save resources
                if (!document.hidden) {
                    window.isBackgroundRefresh = true;
                    loadRecentPage(currentPage);
                }
            }, 5000);
        })();

        // KPI Helper Functions for Overview Section
        function getKpiAggregatesFromStats(stats) {
            let totalPending = 0, totalApproved = 0, totalDeclined = 0, totalResponded = 0, grandTotal = 0;
            
            Object.values(stats).forEach(stat => {
                totalPending += parseInt(stat.pending || 0);
                totalApproved += parseInt(stat.approved || 0);
                totalDeclined += parseInt(stat.declined || 0);
                totalResponded += parseInt(stat.responded || 0);
                grandTotal += parseInt(stat.total || 0);
            });
            
            return {
                pending: totalPending,
                approved: totalApproved,
                declined: totalDeclined,
                responded: totalResponded,
                total: grandTotal
            };
        }
        
        function pushKpiHistory(aggregates) {
            try {
                const history = JSON.parse(localStorage.getItem('kpiHistory') || '[]');
                const now = Date.now();
                
                // Add current values with timestamp
                history.push({
                    timestamp: now,
                    ...aggregates
                });
                
                // Keep only last 50 entries (about 24 hours of data if updated every 30 minutes)
                if (history.length > 50) {
                    history.splice(0, history.length - 50);
                }
                
                localStorage.setItem('kpiHistory', JSON.stringify(history));
            } catch (e) {
                console.warn('Failed to save KPI history:', e);
            }
        }
        
        function drawSparkline(element, values, strokeColor = '#0284c7') {
            if (!values || values.length < 2) {
                element.innerHTML = '<div class="text-xs text-slate-400">Insufficient data</div>';
                return;
            }
            
            const width = 60;
            const height = 20;
            const max = Math.max(...values, 1);
            const min = Math.min(...values);
            const range = max - min || 1;
            
            const points = values.map((value, index) => {
                const x = (index / (values.length - 1)) * width;
                const y = height - ((value - min) / range) * height;
                return `${x},${y}`;
            }).join(' ');
            
            element.innerHTML = `
                <svg width="${width}" height="${height}" class="opacity-70">
                    <polyline
                        fill="none"
                        stroke="${strokeColor}"
                        stroke-width="1.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        points="${points}"
                    />
                </svg>
            `;
        }
        
        function renderTopKpis(aggregates) {
            const container = document.getElementById('topKpiContainer');
            if (!container) return;
            
            try {
                const history = JSON.parse(localStorage.getItem('kpiHistory') || '[]');
                const kpis = [
                    { key: 'pending', label: 'Pending', value: aggregates.pending, color: 'amber' },
                    { key: 'approved', label: 'Approved', value: aggregates.approved, color: 'emerald' },
                    { key: 'responded', label: 'Responded', value: aggregates.responded, color: 'cyan' },
                    { key: 'declined', label: 'Declined', value: aggregates.declined, color: 'rose' },
                    { key: 'total', label: 'Total', value: aggregates.total, color: 'slate' }
                ];
                
                container.innerHTML = kpis.map(kpi => {
                    const historicalValues = history.map(h => h[kpi.key] || 0);
                    const sparklineId = `sparkline-${kpi.key}`;
                    
                    return `
                        <div class="kpi-card">
                            <div class="kpi-label">${kpi.label}</div>
                            <div class="kpi-value text-${kpi.color}-600" data-countup="${kpi.value}">${kpi.value}</div>
                            <div id="${sparklineId}" class="kpi-sparkline"></div>
                        </div>
                    `;
                }).join('');
                
                // Draw sparklines after DOM update
                setTimeout(() => {
                    kpis.forEach(kpi => {
                        const element = document.getElementById(`sparkline-${kpi.key}`);
                        if (element) {
                            const historicalValues = history.map(h => h[kpi.key] || 0);
                            const colors = {
                                'amber': '#f59e0b',
                                'emerald': '#10b981',
                                'cyan': '#06b6d4',
                                'rose': '#f43f5e',
                                'slate': '#64748b'
                            };
                            drawSparkline(element, historicalValues, colors[kpi.color]);
                        }
                    });
                }, 50);
                
                // Trigger count-up animations
                setTimeout(() => {
                    container.querySelectorAll('[data-countup]').forEach(el => {
                        const target = parseInt(el.dataset.countup) || 0;
                        animateCount(el, target);
                    });
                }, 100);
                
            } catch (e) {
                console.error('Error rendering KPIs:', e);
                container.innerHTML = kpis.map(kpi => `
                    <div class="kpi-card">
                        <div class="kpi-label">${kpi.label}</div>
                        <div class="kpi-value text-${kpi.color}-600">${kpi.value}</div>
                    </div>
                `).join('');
            }
        }

        // Function to refresh admin statistics
        // Moved to assets/js/main.js: refreshAdminStats

        // Initial load of admin statistics and Overview KPIs
        setTimeout(() => {
            refreshAdminStats();
        }, 1000);

        // Quick Action: Clear All Cache
        window.clearAllCache = async function() {
            try {
                showToast('Clearing cache...', 'info');
                
                const formData = new FormData();
                formData.append('api_action', 'clear_cache');
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast('Cache cleared successfully', 'success');
                    // Refresh stats after clearing cache
                    setTimeout(() => refreshAdminStats(), 500);
                } else {
                    showToast('Failed to clear cache: ' + result.message, 'error');
                }
            } catch (error) {
                showToast('Error clearing cache: ' + error.message, 'error');
            }
        };



        // Quick Action: Test Notifications (Debug triple notifications)
        window.testNotifications = async function() {
            try {
                const collection = prompt('Enter collection name (e.g., flood_reports):');
                const docId = prompt('Enter document ID:');
                
                if (!collection || !docId) {
                    showToast('Collection and Document ID are required', 'error');
                    return;
                }
                
                showToast('Testing notification flow...', 'info');
                
                const formData = new FormData();
                formData.append('api_action', 'test_notifications');
                formData.append('collection', collection);
                formData.append('docId', docId);
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    console.log('Notification Test Results:', result.results);
                    showToast('✅ Test completed! Check browser console and server logs for details.', 'success');
                    
                    // Show key results in a more readable format
                    const results = result.results;
                    let summary = `Test Results for ${results.collection}/${results.docId}:\n`;
                    summary += `Reporter ID: ${results.reporterId}\n`;
                    summary += `Emergency Type: ${results.emergency_type}\n`;
                    summary += `Total Responders: ${results.total_responders}\n`;
                    summary += `Reporter is Responder: ${results.reporter_is_responder ? 'YES' : 'NO'}\n`;
                    summary += `User Notification: ${results.user_notification ? 'SUCCESS' : 'FAILED'}\n`;
                    summary += `Responder Notification: ${results.responder_notification ? 'SUCCESS' : 'FAILED'}`;
                    
                    alert(summary);
                } else {
                    showToast('❌ Test failed: ' + result.message, 'error');
                }
            } catch (error) {
                showToast('❌ Test error: ' + error.message, 'error');
            }
        };

        // Quick Action: View Pending Reports
        window.viewPendingReports = function() {
            // Find any pending tab and click it
            const pendingTabs = document.querySelectorAll('[data-tab="pending"]');
            if (pendingTabs.length > 0) {
                pendingTabs[0].click();
                pendingTabs[0].scrollIntoView({ behavior: 'smooth' });
                showToast('Switched to Pending reports view', 'success');
            } else {
                showToast('No pending reports tabs found', 'warning');
            }
        };

        // Admin: Load dashboard statistics asynchronously
        <?php if ($isAdmin && $view === 'dashboard'): ?>
        
        // Function to load admin dashboard data
        async function loadAdminDashboardData(isInitial = false) {
            try {
                const statsContainer = document.getElementById('adminStatsContainer');
                const recentContainer = document.getElementById('recentActivityList');
                
                // Show loading state only on initial load
                if (isInitial && statsContainer) {
                    statsContainer.innerHTML = `
                        <div class="col-span-full text-center py-6 text-slate-500">
                            <div class="inline-flex items-center gap-2">
                                ${svg_icon('spinner', 'w-4 h-4 animate-spin')}
                                <span class="text-sm">Loading statistics...</span>
                            </div>
                        </div>
                    `;
                }
                
                // Prepare requests
                const requests = [];
                
                if (statsContainer) {
                    const statsFormData = new FormData();
                    statsFormData.append('api_action', 'load_admin_stats');
                    requests.push(fetch(window.location.href, { method: 'POST', body: statsFormData }));
                } else {
                    requests.push(Promise.resolve(null));
                }
                
                const recentFormData = new FormData();
                recentFormData.append('api_action', 'recent_feed');
                recentFormData.append('page', '1');
                recentFormData.append('pageSize', '10');
                recentFormData.append('search', '');
                recentFormData.append('category', 'all');
                recentFormData.append('status', 'all');
                requests.push(fetch(window.location.href, { method: 'POST', body: recentFormData }));
                
                // Execute requests
                const [statsResponse, recentResponse] = await Promise.all(requests);
                
                // Process stats
                let statsResult = { success: false };
                if (statsResponse) {
                    statsResult = await statsResponse.json();
                }
                
                if (statsResult.success && statsContainer) {
                    const categories = <?php echo json_encode($categories); ?>;
                    const stats = statsResult.data;
                    
                    // If initial load, clear container
                    if (isInitial) statsContainer.innerHTML = '';
                    
                    // Aggregated totals for Overview
                    let grandTotal = 0;
                    let grandPending = 0;
                    let grandApproved = 0;
                    let grandDeclined = 0;
                    
                    Object.entries(categories).forEach(([slug, meta]) => {
                        const stat = stats[slug] || { total: 0, approved: 0, pending: 0, declined: 0, responded: 0 };
                        const total = Math.max(0, parseInt(stat.total) || 0);
                        const approved = Math.max(0, parseInt(stat.approved) || 0);
                        const pending = Math.max(0, parseInt(stat.pending) || 0);
                        const declined = Math.max(0, parseInt(stat.declined) || 0);
                        const responded = Math.max(0, parseInt(stat.responded) || 0);
                        
                        // Accumulate totals
                        grandTotal += total;
                        grandPending += pending;
                        grandApproved += approved;
                        grandDeclined += declined;
                        
                        const approvedPct = total > 0 ? Math.round((approved / total) * 100) : 0;
                        const pendingPct = total > 0 ? Math.round((pending / total) * 100) : 0;
                        const declinedPct = total > 0 ? Math.round((declined / total) * 100) : 0;
                        const respondedPct = total > 0 ? Math.round((responded / total) * 100) : 0;
                        
                        // Check if card exists
                        let card = document.getElementById(`stat-card-${slug}`);
                        
                        if (card) {
                            // Update existing card
                            const pendingEl = card.querySelector('[data-status="pending"]');
                            const approvedEl = card.querySelector('[data-status="approved"]');
                            const declinedEl = card.querySelector('[data-status="declined"]');
                            const respondedEl = card.querySelector('[data-status="responded"]');
                            
                            if (pendingEl) {
                                pendingEl.dataset.countup = pending;
                                pendingEl.textContent = pending;
                            }
                            if (approvedEl) {
                                approvedEl.dataset.countup = approved;
                                approvedEl.textContent = approved;
                            }
                            if (declinedEl) {
                                declinedEl.dataset.countup = declined;
                                declinedEl.textContent = declined;
                            }
                            if (respondedEl) {
                                respondedEl.dataset.countup = responded;
                                respondedEl.textContent = responded;
                            }
                            
                            // Update progress bars
                            const pendingBar = card.querySelector('.progress-seg.pending');
                            const approvedBar = card.querySelector('.progress-seg.approved');
                            const declinedBar = card.querySelector('.progress-seg.declined');
                            const respondedBar = card.querySelector('.progress-seg.responded');
                            
                            if (pendingBar) pendingBar.style.width = `${pendingPct}%`;
                            if (approvedBar) approvedBar.style.width = `${approvedPct}%`;
                            if (declinedBar) declinedBar.style.width = `${declinedPct}%`;
                            if (respondedBar) respondedBar.style.width = `${respondedPct}%`;
                            
                            // Update percentages text
                            const pendingText = card.querySelector('.text-pending-pct');
                            const approvedText = card.querySelector('.text-approved-pct');
                            const declinedText = card.querySelector('.text-declined-pct');
                            const respondedText = card.querySelector('.text-responded-pct');
                            
                            if (pendingText) pendingText.textContent = `${pendingPct}% Pend`;
                            if (approvedText) approvedText.textContent = `${approvedPct}% Appr`;
                            if (declinedText) declinedText.textContent = `${declinedPct}% Decl`;
                            if (respondedText) respondedText.textContent = `${respondedPct}% Resp`;
                            
                        } else {
                            // Create new card
                            card = document.createElement('div');
                            card.id = `stat-card-${slug}`;
                            card.className = 'stat-card p-5';
                            card.innerHTML = `
                                <div class="flex items-center gap-4 mb-4">
                                    <div class="w-12 h-12 rounded-xl bg-${meta.color}-100 text-${meta.color}-600 flex items-center justify-center">
                                        ${svg_icon(meta.icon, 'w-7 h-7')}
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-bold text-slate-800">${meta.label}</h3>
                                        <p class="text-xs text-slate-500">Overview</p>
                                    </div>
                                </div>
                                <div class="grid grid-cols-4 gap-2 text-center mb-4">
                                    <div>
                                        <div class="text-2xl font-extrabold text-amber-600 tracking-tighter">
                                            <span data-countup="${pending}" data-status="pending">${pending}</span>
                                        </div>
                                        <div class="text-[10px] text-slate-500 uppercase tracking-wider font-medium">Pend</div>
                                    </div>
                                    <div>
                                        <div class="text-2xl font-extrabold text-emerald-600 tracking-tighter">
                                            <span data-countup="${approved}" data-status="approved">${approved}</span>
                                        </div>
                                        <div class="text-[10px] text-slate-500 uppercase tracking-wider font-medium">Appr</div>
                                    </div>
                                    <div>
                                        <div class="text-2xl font-extrabold text-cyan-600 tracking-tighter">
                                            <span data-countup="${responded}" data-status="responded">${responded}</span>
                                        </div>
                                        <div class="text-[10px] text-slate-500 uppercase tracking-wider font-medium">Resp</div>
                                    </div>
                                    <div>
                                        <div class="text-2xl font-extrabold text-red-600 tracking-tighter">
                                            <span data-countup="${declined}" data-status="declined">${declined}</span>
                                        </div>
                                        <div class="text-[10px] text-slate-500 uppercase tracking-wider font-medium">Decl</div>
                                    </div>
                                </div>
                                <div class="space-y-2">
                                    <div class="progress-track">
                                        <span class="progress-seg pending" style="width: ${pendingPct}%"></span>
                                        <span class="progress-seg approved" style="width: ${approvedPct}%"></span>
                                        <span class="progress-seg responded" style="background-color: #06b6d4; width: ${respondedPct}%"></span>
                                        <span class="progress-seg declined" style="width: ${declinedPct}%"></span>
                                    </div>
                                    <div class="grid grid-cols-2 gap-1 text-[10px] text-slate-500">
                                        <div class="flex items-center gap-1">
                                            <span class="inline-block w-2 h-2 rounded-full bg-amber-500"></span>
                                            <span class="text-pending-pct">${pendingPct}% Pend</span>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <span class="inline-block w-2 h-2 rounded-full bg-emerald-500"></span>
                                            <span class="text-approved-pct">${approvedPct}% Appr</span>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <span class="inline-block w-2 h-2 rounded-full bg-cyan-500"></span>
                                            <span class="text-responded-pct">${respondedPct}% Resp</span>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <span class="inline-block w-2 h-2 rounded-full bg-red-500"></span>
                                            <span class="text-declined-pct">${declinedPct}% Decl</span>
                                        </div>
                                    </div>
                                </div>
                            `;
                            statsContainer.appendChild(card);
                        }
                    });
                    
                    // Update Overview KPIs
                    const kpiPending = document.getElementById('kpi-pending');
                    const kpiApproved = document.getElementById('kpi-approved');
                    const kpiDeclined = document.getElementById('kpi-declined');
                    const kpiTotal = document.getElementById('kpi-total');
                    
                    if (kpiPending) {
                        kpiPending.textContent = grandPending;
                        if (isInitial) {
                            kpiPending.dataset.countup = grandPending;
                            animateCount(kpiPending, grandPending);
                        }
                    }
                    if (kpiApproved) {
                        kpiApproved.textContent = grandApproved;
                        if (isInitial) {
                            kpiApproved.dataset.countup = grandApproved;
                            animateCount(kpiApproved, grandApproved);
                        }
                    }
                    if (kpiDeclined) {
                        kpiDeclined.textContent = grandDeclined;
                        if (isInitial) {
                            kpiDeclined.dataset.countup = grandDeclined;
                            animateCount(kpiDeclined, grandDeclined);
                        }
                    }
                    if (kpiTotal) {
                        kpiTotal.textContent = grandTotal;
                        if (isInitial) {
                            kpiTotal.dataset.countup = grandTotal;
                            animateCount(kpiTotal, grandTotal);
                        }
                    }
                    
                    if (isInitial) {
                        // Trigger countup animations only on initial load
                        setTimeout(() => {
                            document.querySelectorAll('[data-countup]').forEach(el => {
                                const target = parseInt(el.dataset.countup) || 0;
                                animateCount(el, target);
                            });
                        }, 100);
                    }
                }
                
                // Process recent activity
                const recentResult = await recentResponse.json();
                if (recentResult.success && recentContainer) {
                    if (typeof loadRecentPage === 'function') {
                        displayRecentItems(recentResult.data);
                    }
                }
                
            } catch (error) {
                console.error('Error loading admin dashboard data:', error);
                if (isInitial) {
                    const statsContainer = document.getElementById('adminStatsContainer');
                    if (statsContainer) {
                        statsContainer.innerHTML = `
                            <div class="col-span-full text-center py-8 text-red-500">
                                <p>Failed to load statistics. Retrying...</p>
                            </div>
                        `;
                    }
                }
            }
        }

        // Initial load
        loadAdminDashboardData(true);
        
        // Poll every 5 seconds for realtime updates
        setInterval(() => {
            if (document.visibilityState === 'visible') {
                loadAdminDashboardData(false);
            }
        }, 5000);
        <?php endif; ?>

        window.updateActivityItemStatus = function(id, newStatus) {
            const li = document.querySelector(`#activityList li[data-id="${id}"]`);
            if (!li) return;

            const st = (newStatus || 'Pending').toLowerCase();
            li.dataset.status = st;

            const badge = li.querySelector('.status-badge');
            if (badge) {
                badge.className = 'mt-2 inline-flex status-badge';
                if (st === 'approved') badge.classList.add('status-badge-success');
                else if (st === 'declined') badge.classList.add('status-badge-declined');
                else badge.classList.add('status-badge-pending');
                badge.innerHTML = `<span class="h-2 w-2 rounded-full bg-current mr-2"></span>${newStatus}`;
            }
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                window.closeReportModal();
                window.closeProofModal();
            }
        });

        // User Verification JavaScript (only run on verify-users view)
        if (window.location.search.includes('view=verify-users')) {
            (function() {
                const vuList = document.getElementById('vuList');
                const vuLoading = document.getElementById('vuLoading');
                const vuEmpty = document.getElementById('vuEmpty');
                const vuRange = document.getElementById('vuRange');
                const vuPrev = document.getElementById('vuPrev');
                const vuNext = document.getElementById('vuNext');
                const vuPageSize = document.getElementById('vuPageSize');
                const vuSearch = document.getElementById('vuSearch');
                const vuRefresh = document.getElementById('vuRefresh');


                let currentPage = 1;
                let pageSize = 20;
                let searchTerm = '';
                let totalUsers = 0;
                let lastCheckTime = new Date().toISOString();
                let realTimeSyncInterval = null;
                let isRealTimeUpdating = false;

                // Start real-time sync for pending users - Fresh session every 2 seconds
                function startRealTimeSync() {
                    if (realTimeSyncInterval) {
                        clearInterval(realTimeSyncInterval);
                    }
                    
                    console.log('🚀 Starting fresh session real-time sync for pending users...');
                    
                    // Check for new users every 2 seconds with fresh session
                    realTimeSyncInterval = setInterval(async () => {
                        if (isRealTimeUpdating || searchTerm.trim() !== '') {
                            return; // Skip if already updating or if search is active
                        }
                        
                        try {
                            isRealTimeUpdating = true;
                            
                            // 🔄 Reset session every 2 seconds for fresh detection
                            console.log('🔄 Resetting session for fresh user detection...');
                            const resetFormData = new FormData();
                            resetFormData.append('api_action', 'reset_user_session');
                            
                            await fetch(window.location.href, {
                                method: 'POST',
                                body: resetFormData
                            });
                            
                            // Silent background check for new users with fresh session
                            const formData = new FormData();
                            formData.append('api_action', 'get_new_pending_users');
                            formData.append('last_check', lastCheckTime);
                            
                            const response = await fetch(window.location.href, {
                                method: 'POST',
                                body: formData
                            });
                            
                            // Check if response is valid JSON
                            const responseText = await response.text();
                            let result;
                            try {
                                result = JSON.parse(responseText);
                            } catch (parseError) {
                                console.error('Invalid JSON response:', responseText);
                                return;
                            }
                            
                            console.log('Real-time check result:', result);
                            
                            if (result.success && result.hasNew) {
                                console.log(`🆕 ${result.count} new pending users detected, silently updating list...`);
                                console.log('New users:', result.newUsers);
                                
                                // Update last check time
                                lastCheckTime = result.timestamp || new Date().toISOString();
                                
                                // Silently add new users to the TOP of the list
                                if (result.newUsers && result.newUsers.length > 0) {
                                    addNewUsersToList(result.newUsers);
                                    
                                    // Show subtle notification without disrupting user experience
                                    showNotificationWithSound(`🆕 ${result.count} new user registration(s) received!`, 'success');
                                }
                            } else if (result.success) {
                                console.log(`✅ No new users (${result.totalPending} total pending)`);
                            }
                            
                        } catch (error) {
                            console.error('Error in fresh session real-time sync:', error);
                        } finally {
                            isRealTimeUpdating = false;
                        }
                    }, 2000); // Check every 2 seconds with fresh session
                }

                // Stop real-time sync
                function stopRealTimeSync() {
                    if (realTimeSyncInterval) {
                        clearInterval(realTimeSyncInterval);
                        realTimeSyncInterval = null;
                    }
                }

                // Debug database function
                async function debugDatabase() {
                    console.log('🔍 Manual debug: Checking database...');
                    try {
                        const formData = new FormData();
                        formData.append('api_action', 'debug_pending_users');
                        
                        const response = await fetch(window.location.href, {
                            method: 'POST',
                            body: formData
                        });
                        
                        if (response.ok) {
                            const result = await response.json();
                            console.log('🔍 Manual debug result:', result);
                            
                            if (result.success) {
                                if (result.allPendingUsers && result.allPendingUsers.length > 0) {
                                    console.log(`🔍 Found ${result.allPendingUsers.length} pending users in database:`, result.allPendingUsers);
                                    alert(`Found ${result.allPendingUsers.length} pending users in database. Check console for details.`);
                                } else {
                                    console.log('🔍 No pending users found in database');
                                    alert('No pending users found in database. This explains why real-time is not working!');
                                }
                            } else {
                                console.error('Debug failed:', result.message);
                                alert('Debug failed: ' + result.message);
                            }
                        }
                    } catch (error) {
                        console.error('Debug error:', error);
                        alert('Debug error: ' + error.message);
                    }
                }

                // Add new users to the list silently and smoothly - NO visible refresh
                function addNewUsersToList(newUsers) {
                    if (!newUsers || newUsers.length === 0) return;
                    
                    console.log('Silently adding new users to list:', newUsers);
                    
                    // Hide empty message if it's showing
                    if (vuEmpty) vuEmpty.classList.add('hidden');
                    
                    // Generate HTML for new users
                    const newUsersHtml = newUsers.map((user, index) => {
                        const uid = user.id || '';
                        const fullName = escapeHtml(user.fullName || '—');
                        const email = escapeHtml(user.email || '—');
                        const contact = escapeHtml(user.mobileNumber || user.contact || '—');
                        const currentAddress = escapeHtml(user.currentAddress || '');
                        const permanentAddress = escapeHtml(user.permanentAddress || '');
                        const address = currentAddress || permanentAddress || escapeHtml(user.address || '—');
                        const birthdate = escapeHtml(user.birthdate || '—');
                        
                        // Handle multiple ID images
                        const frontIdUrl = user.frontIdImageUrl || '';
                        const backIdUrl = user.backIdImageUrl || '';
                        const selfieUrl = user.selfieImageUrl || '';
                        
                        // Fallback to old proof path for backward compatibility
                        const proofPath = user.proofOfResidencyPath || '';
                        const proofUrl = proofPath ? `proof_proxy.php?path=${encodeURIComponent(proofPath)}&user=${encodeURIComponent(uid)}` : '';

                        return `
                            <div class="user-card bg-gradient-to-r from-green-50 to-emerald-50 border-2 border-green-200 rounded-xl p-6 animate-fade-in-up shadow-lg" data-uid="${uid}" style="--anim-delay: ${index * 50}ms">
                                <div class="flex flex-col gap-4">
                                    <!-- NEW USER Badge -->
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <span class="px-3 py-1 text-xs font-bold rounded-full bg-green-500 text-white animate-pulse">NEW USER</span>
                                            <span class="text-xs text-green-600 font-medium">Just registered!</span>
                                        </div>
                                    </div>
                                    
                                    <!-- User Info Header -->
                                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                                        <div class="flex-1">
                                            <div class="flex items-center gap-3 mb-2">
                                                <h4 class="font-semibold text-slate-800 text-lg">${fullName}</h4>
                                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-amber-100 text-amber-800">PENDING</span>
                                            </div>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
                                                <div>
                                                    <span class="text-slate-500">Email:</span> <span class="text-slate-800">${email}</span>
                                                </div>
                                                <div>
                                                    <span class="text-slate-500">Mobile:</span> <span class="text-slate-800">${contact}</span>
                                                </div>
                                                <div>
                                                    <span class="text-slate-500">Birthdate:</span> <span class="text-slate-800">${birthdate}</span>
                                                </div>
                                            </div>
                                            <div class="mt-2">
                                                <span class="text-slate-500 text-sm">Address:</span> 
                                                <span class="text-slate-800 text-sm" title="${address}">${address.length > 80 ? address.substring(0, 80) + '...' : address}</span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- ID Documents Section -->
                                    ${(frontIdUrl || backIdUrl || selfieUrl || proofUrl) ? `
                                        <div class="border-t border-green-200 pt-4">
                                            <h5 class="text-sm font-medium text-slate-700 mb-3">Verification Documents</h5>
                                            <div class="flex flex-wrap gap-2">
                                                ${frontIdUrl ? `
                                                    <button type="button" class="btn btn-view text-xs" title="View Front ID"
                                                            onclick="showIdModal(this, 'Front ID')"
                                                            data-fullname="${escapeHtml(user.fullName || '')}"
                                                            data-imageurl="${escapeHtml(frontIdUrl)}"
                                                            data-imagetype="Front ID">
                                                        <?php echo svg_icon('identification', 'w-3 h-3'); ?><span>Front ID</span>
                                                    </button>
                                                ` : ''}
                                                ${backIdUrl ? `
                                                    <button type="button" class="btn btn-view text-xs" title="View Back ID"
                                                            onclick="showIdModal(this, 'Back ID')"
                                                            data-fullname="${escapeHtml(user.fullName || '')}"
                                                            data-imageurl="${escapeHtml(backIdUrl)}"
                                                            data-imagetype="Back ID">
                                                        <?php echo svg_icon('identification', 'w-3 h-3'); ?><span>Back ID</span>
                                                    </button>
                                                ` : ''}
                                                ${selfieUrl ? `
                                                    <button type="button" class="btn btn-view text-xs" title="View Selfie"
                                                            onclick="showIdModal(this, 'Selfie')"
                                                            data-fullname="${escapeHtml(user.fullName || '')}"
                                                            data-imageurl="${escapeHtml(selfieUrl)}"
                                                            data-imagetype="Selfie">
                                                        <?php echo svg_icon('user-circle', 'w-3 h-3'); ?><span>Selfie</span>
                                                    </button>
                                                ` : ''}
                                                ${proofUrl ? `
                                                    <button type="button" class="btn btn-view text-xs" title="View Proof of Residency"
                                                            onclick="showProofModal(this)"
                                                            data-fullname="${escapeHtml(user.fullName || '')}"
                                                            data-proofurl="${escapeHtml(proofUrl)}">
                                                        <?php echo svg_icon('home', 'w-3 h-3'); ?><span>Proof</span>
                                                    </button>
                                                ` : ''}
                                            </div>
                                        </div>
                                    ` : ''}
                                    
                                    <!-- Action Buttons -->
                                    <div class="border-t border-green-200 pt-4">
                                        <div class="flex justify-end gap-2">
                                            <form class="inline-flex" onsubmit="handleUserVerification(event)">
                                                <input type="hidden" name="uid" value="${uid}">
                                                <input type="hidden" name="newStatus" value="approved">
                                                <button type="submit" class="btn btn-approve" title="Approve Registration">
                                                    <?php echo svg_icon('check-circle', 'w-4 h-4'); ?><span>Approve</span>
                                                </button>
                                            </form>
                                            <form class="inline-flex" onsubmit="handleUserVerification(event)">
                                                <input type="hidden" name="uid" value="${uid}">
                                                <input type="hidden" name="newStatus" value="rejected">
                                                <button type="submit" class="btn btn-decline" title="Reject Registration">
                                                    <?php echo svg_icon('x-circle', 'w-4 h-4'); ?><span>Reject</span>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    }).join('');
                    
                    // Silently add new users to the TOP of the list - NO visible refresh
                    if (vuList && newUsersHtml) {
                        // Create temporary container for new users
                        const tempDiv = document.createElement('div');
                        tempDiv.innerHTML = newUsersHtml;
                        
                        // Insert each new user at the top with smooth animation
                        const newUserElements = Array.from(tempDiv.children);
                        newUserElements.reverse().forEach((userElement, index) => {
                            vuList.insertBefore(userElement, vuList.firstChild);
                            
                            // Silent glow effect that fades after 5 seconds
                            setTimeout(() => {
                                userElement.classList.remove('from-green-50', 'to-emerald-50', 'border-green-200');
                                userElement.classList.add('bg-white', 'border-slate-200');
                                userElement.querySelector('.animate-pulse')?.classList.remove('animate-pulse');
                            }, 5000); // Remove highlight after 5 seconds
                        });
                        
                        // Update total users count silently
                        totalUsers += newUsers.length;
                        updatePagination();
                    }
                }

                async function loadPendingUsers(page = 1, retryCount = 0) {
                    if (!vuList) return;
                    
                    const maxRetries = 2; // Reduced retries for faster response
                    const retryDelay = 500 * Math.pow(2, retryCount); // Faster retry delays

                    // Show loading state only on first load or manual refresh
                    if (retryCount === 0) {
                        // Only show loading spinner if this is a manual refresh or first load
                        if (vuList.children.length === 0 || vuList.querySelector('.user-card') === null) {
                            vuLoading.style.display = 'block';
                            vuEmpty.classList.add('hidden');
                            vuList.innerHTML = '<div class="text-center py-10 text-slate-500 text-sm"><div class="inline-flex items-center gap-2"><?php echo svg_icon('spinner', 'w-5 h-5 animate-spin'); ?> Loading users...</div></div>';
                        }
                    }

                    try {
                        const formData = new FormData();
                        formData.append('api_action', 'list_pending_users');
                        formData.append('page', String(page));
                        formData.append('pageSize', String(pageSize));
                        formData.append('search', searchTerm);

                        console.log('DEBUG: Sending request to list_pending_users with:', {
                            page: page,
                            pageSize: pageSize,
                            search: searchTerm
                        });

                        const controller = new AbortController();
                        const timeoutId = setTimeout(() => controller.abort(), 10000); // 10 second timeout for faster response

                        const response = await fetch(window.location.href, {
                            method: 'POST',
                            body: formData,
                            signal: controller.signal
                        });

                        clearTimeout(timeoutId);

                        if (!response.ok) {
                            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                        }

                        const result = await response.json();
                        console.log('DEBUG: Received response:', result);

                        if (result.success) {
                            currentPage = result.page;
                            totalUsers = result.total;
                            const users = result.data || [];

                            console.log('DEBUG: Processing users:', users.length, 'users found');
                            renderUserList(users);
                            updatePagination();
                            
                            // Update last check time for real-time sync
                            lastCheckTime = new Date().toISOString();
                            
                            // Show execution time if available
                            if (result.executionTime) {
                                console.log(`User verification loaded in ${result.executionTime}`);
                            }
                        } else {
                            console.error('DEBUG: API returned error:', result.message);
                            throw new Error(result.message || 'Failed to load users');
                        }
                    } catch (error) {
                        console.error('Error loading pending users:', error);
                        
                        const isTimeout = error.name === 'AbortError' || error.message.includes('timeout');
                        const isNetworkError = error.message.includes('fetch') || error.message.includes('Network');
                        
                        if (retryCount < maxRetries && (isTimeout || isNetworkError)) {
                            // Retry with exponential backoff
                            setTimeout(() => {
                                loadPendingUsers(page, retryCount + 1);
                            }, retryDelay);
                            
                            vuList.innerHTML = `<div class="text-center py-10 text-slate-500 text-sm">
                                <div class="inline-flex items-center gap-2 mb-2">
                                    <?php echo svg_icon('spinner', 'w-5 h-5 animate-spin'); ?>
                                    Retrying... (${retryCount + 1}/${maxRetries})
                                </div>
                                <div class="text-xs text-slate-400">
                                    ${isTimeout ? 'Request timed out' : 'Network error occurred'}
                                </div>
                            </div>`;
                        } else {
                            // Show error with retry button and more helpful message
                            let errorMsg = 'Failed to load users. ';
                            if (isTimeout) {
                                errorMsg += 'Request timed out. Please try again.';
                            } else if (isNetworkError) {
                                errorMsg += 'Network connection issue. Please check your internet connection and try again.';
                            } else {
                                errorMsg += error.message || 'Unknown error occurred.';
                            }
                            
                            vuList.innerHTML = `<div class="text-center py-10">
                                <div class="text-red-500 mb-3 text-sm">
                                    <?php echo svg_icon('x-mark', 'w-6 h-6 mx-auto mb-2'); ?>
                                    ${errorMsg}
                                </div>
                                <div class="space-y-2">
                                <button onclick="loadPendingUsers(${page})" class="btn btn-primary text-sm">
                                    Try Again
                                </button>
                                    <button onclick="loadPendingUsers(1)" class="btn btn-view text-sm">
                                        Reset to First Page
                                    </button>
                                </div>
                            </div>`;
                        }
                    }
                }

                function renderUserList(users) {
                    if (users.length === 0) {
                        vuList.innerHTML = '';
                        vuEmpty.classList.remove('hidden');
                                vuEmpty.innerHTML = searchTerm ? 'No users found for your search.' : `
                            <div class="text-center">
                                <div class="text-slate-500 mb-3">No pending user registrations. ✨</div>
                                <button type="button" onclick="debugDatabase()" class="px-3 py-1 text-xs bg-blue-100 text-blue-700 rounded hover:bg-blue-200">
                                    🔍 Debug Database
                                </button>
                            </div>
                        `;
                        return;
                    }

                    vuEmpty.classList.add('hidden');

                    const html = users.map((user, index) => {
                        const uid = user.id || '';
                        const fullName = escapeHtml(user.fullName || '—');
                        const firstName = escapeHtml(user.firstName || '');
                        const lastName = escapeHtml(user.lastName || '');
                        const middleName = escapeHtml(user.middleName || '');
                        const email = escapeHtml(user.email || '—');
                        const contact = escapeHtml(user.mobileNumber || user.contact || '—');
                        const currentAddress = escapeHtml(user.currentAddress || '');
                        const permanentAddress = escapeHtml(user.permanentAddress || '');
                        const address = currentAddress || permanentAddress || escapeHtml(user.address || '—');
                        const birthdate = escapeHtml(user.birthdate || '—');
                        const gender = escapeHtml(user.gender || '—');
                        const accountStatus = escapeHtml(user.accountStatus || 'pending');
                        
                        // Handle multiple ID images
                        const frontIdUrl = user.frontIdImageUrl || '';
                        const backIdUrl = user.backIdImageUrl || '';
                        const selfieUrl = user.selfieImageUrl || '';
                        
                        // Fallback to old proof path for backward compatibility
                        const proofPath = user.proofOfResidencyPath || '';
                        const proofUrl = proofPath ? `proof_proxy.php?path=${encodeURIComponent(proofPath)}&user=${encodeURIComponent(uid)}` : '';

                        const animDelay = `style="--anim-delay: ${index * 50}ms"`;

                        return `
                            <div class="user-card bg-white rounded-xl border border-slate-200 p-6 animate-fade-in-up" ${animDelay} data-uid="${uid}">
                                <div class="flex flex-col gap-4">
                                    <!-- User Info Header -->
                                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                                        <div class="flex-1">
                                            <div class="flex items-center gap-3 mb-2">
                                                <h4 class="font-semibold text-slate-800 text-lg">${fullName}</h4>
                                                <span class="px-2 py-1 text-xs font-medium rounded-full ${accountStatus === 'pending' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600'}">${accountStatus.toUpperCase()}</span>
                                            </div>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
                                                <div>
                                                    <span class="text-slate-500">Email:</span> <span class="text-slate-800">${email}</span>
                                                </div>
                                                <div>
                                                    <span class="text-slate-500">Mobile:</span> <span class="text-slate-800">${contact}</span>
                                                </div>
                                                <div>
                                                    <span class="text-slate-500">Birthdate:</span> <span class="text-slate-800">${birthdate}</span>
                                                </div>
                                            </div>
                                            <div class="mt-2">
                                                <span class="text-slate-500 text-sm">Address:</span> 
                                                <span class="text-slate-800 text-sm" title="${address}">${address.length > 80 ? address.substring(0, 80) + '...' : address}</span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- ID Documents Section -->
                                    ${(frontIdUrl || backIdUrl || selfieUrl || proofUrl) ? `
                                        <div class="border-t border-slate-200 pt-4">
                                            <h5 class="text-sm font-medium text-slate-700 mb-3">Verification Documents</h5>
                                            <div class="flex flex-wrap gap-2">
                                                ${frontIdUrl ? `
                                                    <button type="button" class="btn btn-view text-xs" title="View Front ID"
                                                            onclick="showIdModal(this, 'Front ID')"
                                                            data-fullname="${escapeHtml(user.fullName || '')}"
                                                            data-imageurl="${escapeHtml(frontIdUrl)}"
                                                            data-imagetype="Front ID">
                                                        <?php echo svg_icon('identification', 'w-3 h-3'); ?><span>Front ID</span>
                                                    </button>
                                                ` : ''}
                                                ${backIdUrl ? `
                                                    <button type="button" class="btn btn-view text-xs" title="View Back ID"
                                                            onclick="showIdModal(this, 'Back ID')"
                                                            data-fullname="${escapeHtml(user.fullName || '')}"
                                                            data-imageurl="${escapeHtml(backIdUrl)}"
                                                            data-imagetype="Back ID">
                                                        <?php echo svg_icon('identification', 'w-3 h-3'); ?><span>Back ID</span>
                                                    </button>
                                                ` : ''}
                                                ${selfieUrl ? `
                                                    <button type="button" class="btn btn-view text-xs" title="View Selfie"
                                                            onclick="showIdModal(this, 'Selfie')"
                                                            data-fullname="${escapeHtml(user.fullName || '')}"
                                                            data-imageurl="${escapeHtml(selfieUrl)}"
                                                            data-imagetype="Selfie">
                                                        <?php echo svg_icon('user-circle', 'w-3 h-3'); ?><span>Selfie</span>
                                                    </button>
                                                ` : ''}
                                                ${proofUrl ? `
                                                    <button type="button" class="btn btn-view text-xs" title="View Proof of Residency"
                                                            onclick="showProofModal(this)"
                                                            data-fullname="${escapeHtml(user.fullName || '')}"
                                                            data-proofurl="${escapeHtml(proofUrl)}">
                                                        <?php echo svg_icon('home', 'w-3 h-3'); ?><span>Proof</span>
                                                    </button>
                                                ` : ''}
                                            </div>
                                        </div>
                                    ` : ''}
                                    
                                    <!-- Action Buttons -->
                                    <div class="border-t border-slate-200 pt-4">
                                        <div class="flex justify-end gap-2">
                                            <form class="inline-flex" onsubmit="handleUserVerification(event)">
                                                <input type="hidden" name="uid" value="${uid}">
                                                <input type="hidden" name="newStatus" value="approved">
                                                <button type="submit" class="btn btn-approve" title="Approve Registration">
                                                    <?php echo svg_icon('check-circle', 'w-4 h-4'); ?><span>Approve</span>
                                                </button>
                                            </form>
                                            <form class="inline-flex" onsubmit="handleUserVerification(event)">
                                                <input type="hidden" name="uid" value="${uid}">
                                                <input type="hidden" name="newStatus" value="rejected">
                                                <button type="submit" class="btn btn-decline" title="Reject Registration">
                                                    <?php echo svg_icon('x-circle', 'w-4 h-4'); ?><span>Reject</span>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    }).join('');

                    vuList.innerHTML = html;
                }

                function updatePagination() {
                    const totalPages = Math.max(1, Math.ceil(totalUsers / pageSize));
                    const start = totalUsers ? ((currentPage - 1) * pageSize + 1) : 0;
                    const end = totalUsers ? Math.min(currentPage * pageSize, totalUsers) : 0;

                    if (vuRange) vuRange.textContent = `Showing ${start}-${end} of ${totalUsers}`;
                    if (vuPrev) vuPrev.disabled = currentPage <= 1;
                    if (vuNext) vuNext.disabled = currentPage >= totalPages;
                }

                function escapeHtml(text) {
                    const div = document.createElement('div');
                    div.textContent = text;
                    return div.innerHTML;
                }

                function getStorageUrl(path) {
                    if (!path) return '';
                    const projectId = 'ibantayv2';
                    const encodedPath = encodeURIComponent(path);
                    // Use the correct Firebase Storage bucket format (.firebasestorage.app instead of .appspot.com)
                    return `https://firebasestorage.googleapis.com/v0/b/${projectId}.firebasestorage.app/o/${encodedPath}?alt=media`;
                }

                // Event listeners
                if (vuPageSize) {
                    vuPageSize.addEventListener('change', () => {
                        pageSize = parseInt(vuPageSize.value || '20', 10);
                        loadPendingUsers(1);
                    });
                }

                if (vuSearch) {
                    vuSearch.addEventListener('input', debounce(() => {
                        const newSearchTerm = vuSearch.value.trim();
                        const wasSearching = searchTerm.trim() !== '';
                        searchTerm = newSearchTerm;
                        
                        // If we were searching and now we're not, restart real-time updates
                        if (wasSearching && searchTerm.trim() === '') {
                            startRealTimeSync();
                        }
                        // If we started searching, stop real-time updates
                        else if (!wasSearching && searchTerm.trim() !== '') {
                            stopRealTimeSync();
                        }
                        
                        loadPendingUsers(1);
                    }, 300));
                }









                if (vuPrev) {
                    vuPrev.addEventListener('click', () => {
                        if (currentPage > 1) loadPendingUsers(currentPage - 1);
                    });
                }

                if (vuNext) {
                    vuNext.addEventListener('click', () => {
                        const totalPages = Math.max(1, Math.ceil(totalUsers / pageSize));
                        if (currentPage < totalPages) loadPendingUsers(currentPage + 1);
                    });
                }

                // Debounce function
                function debounce(func, wait) {
                    let timeout;
                    return function executedFunction(...args) {
                        const later = () => {
                            clearTimeout(timeout);
                            func(...args);
                        };
                        clearTimeout(timeout);
                        timeout = setTimeout(later, wait);
                    };
                }

                // Initial load
                loadPendingUsers(1);
                
                // Reset session and force initial check
                setTimeout(async () => {
                    console.log('🔄 Resetting session and checking for users...');
                    try {
                        // First reset the session to get a fresh start
                        const resetFormData = new FormData();
                        resetFormData.append('api_action', 'reset_user_session');
                        
                        await fetch(window.location.href, {
                            method: 'POST',
                            body: resetFormData
                        });
                        
                        // Debug: Check what users exist in database
                        console.log('🔍 Debug: Checking database for users...');
                        const debugFormData = new FormData();
                        debugFormData.append('api_action', 'debug_pending_users');
                        
                        const debugResponse = await fetch(window.location.href, {
                            method: 'POST',
                            body: debugFormData
                        });
                        
                        if (debugResponse.ok) {
                            const debugResult = await debugResponse.json();
                            console.log('🔍 Database debug result:', debugResult);
                            
                            if (debugResult.success && debugResult.allPendingUsers && debugResult.allPendingUsers.length > 0) {
                                console.log(`🔍 Found ${debugResult.allPendingUsers.length} pending users in database:`, debugResult.allPendingUsers);
                            } else {
                                console.log('🔍 No pending users found in database. This might be the issue!');
                            }
                        }
                        
                        // Then check for any users
                        const formData = new FormData();
                        formData.append('api_action', 'get_new_pending_users');
                        
                        const response = await fetch(window.location.href, {
                            method: 'POST',
                            body: formData
                        });
                        
                        if (response.ok) {
                            const result = await response.json();
                            console.log('Initial check result:', result);
                            
                            if (result.success && result.hasNew && result.newUsers && result.newUsers.length > 0) {
                                console.log(`🆕 ${result.newUsers.length} users found on initial check!`);
                                addNewUsersToList(result.newUsers);
                                lastCheckTime = result.timestamp || new Date().toISOString();
                                showNotificationWithSound(`🆕 ${result.newUsers.length} pending user(s) found!`, 'info');
                            }
                        }
                    } catch (error) {
                        console.error('Initial check error:', error);
                    }
                }, 500); // Check after 500ms
                
                // Start real-time sync for pending users
                startRealTimeSync();
                
                // Add real-time indicator - Fresh session updates every 2 seconds
                const realTimeIndicator = document.createElement('div');
                realTimeIndicator.id = 'realTimeIndicator';
                realTimeIndicator.className = 'fixed bottom-4 right-4 bg-green-500 text-white px-3 py-1 rounded-full text-xs font-medium shadow-lg z-50 flex items-center gap-2 opacity-80';
                realTimeIndicator.innerHTML = `
                    <div class="w-2 h-2 bg-white rounded-full animate-pulse"></div>
                    <span>Fresh Session Updates (2s)</span>
                `;
                document.body.appendChild(realTimeIndicator);
                
                // Clean up on page unload
                window.addEventListener('beforeunload', () => {
                    stopRealTimeSync();
                });
            })();
        }
        
        // Staff: Load assigned reports data via AJAX for better performance
        <?php if (!$isAdmin): ?>
        // Logic moved to assets/js/main.js

        
        // Real-time sync moved to assets/js/main.js

        
        // Emergency sync and debug functions moved to assets/js/main.js


        // Tab count functions moved to assets/js/main.js


        // Refresh functions moved to assets/js/main.js

        
        // renderStaffReports moved to assets/js/main.js


        // Tab update and switch functions moved to assets/js/main.js


        // Emergency alerts functions moved to assets/js/main.js


        // Function to render emergency alerts
        // Moved to assets/js/main.js

        // New function to render the report table HTML
        function renderReportsTable(reports, collection, categories) {
            if (reports.length === 0) {
                return ``;
            }

            const slug = Object.keys(categories).find(key => categories[key].collection === collection);

            let tableRows = '';
            reports.forEach((it, i) => {
                const st = (it.status || 'Pending').toLowerCase();
                const displayStatus = it.status || 'Pending';
                const isApproved = (st === 'approved');
                const isDeclined = (st === 'declined');
                const isFinal = isApproved || isDeclined;
                const tDisplay = it.tsDisplay || formatFirebaseTimestamp(it.timestamp);
                const imgUrl = it.imageUrl || '';
                
                // Normalize the data for consistent field mapping
                const normalizedData = normalizeFirebaseReportData(it);
                
                let statusClass = 'status-badge-pending';
                if (isApproved) statusClass = 'status-badge-success';
                if (isDeclined) statusClass = 'status-badge-declined';

                const animDelay = `style="--anim-delay: ${i * 50}ms"`;

                tableRows += `
                    <tr class='report-row animate-fade-in-up' ${animDelay} data-id='${it.id}' data-collection='${collection}'>
                        <td class="p-4 whitespace-nowrap">
                            <div class="font-semibold text-slate-800">${normalizedData.fullName || '—'}</div>
                            <div class="text-slate-500">${normalizedData.contact || '—'}</div>
                        </td>
                        <td class="p-4 text-slate-600 max-w-xs truncate">${normalizedData.location || '—'}</td>
                        <td class="p-4 text-slate-600 whitespace-nowrap">${tDisplay || formatFirebaseTimestamp(it.timestamp)}</td>
                        <td class="p-4">
                            <span class="status-badge ${statusClass}">
                                <span class="h-2 w-2 rounded-full bg-current mr-2"></span>
                                ${displayStatus}
                            </span>
                        </td>
                        <td class="p-4 text-right">
                            <div class="inline-flex items-center gap-2">
                                <button type="button" class="btn btn-view" title="View Details"
                                    onclick="showReportModal(this)"
                                    data-slug="${slug}" data-id="${it.id}" data-collection="${collection}"
                                    data-fullname="${normalizedData.fullName}" data-contact="${normalizedData.mobileNumber || normalizedData.contact}"
                                    data-location="${normalizedData.location}" data-purpose="${normalizedData.purpose}"
                                    data-latitude="${normalizedData.latitude || ''}" data-longitude="${normalizedData.longitude || ''}"
                                    data-status="${displayStatus}" data-timestamp="${tDisplay}"
                                    data-rawtimestamp="${JSON.stringify(it.timestamp).replace(/"/g, '&quot;')}"
                                    data-reporterid="${normalizedData.reporterId}" data-imageurl="${imgUrl}">
                                    <?php echo svg_icon('eye', 'w-4 h-4'); ?><span>View</span>
                                </button>
                                <button type="button" class="btn ${isFinal ? 'btn-disabled' : 'btn-approve'}" ${isFinal ? 'disabled' : ''} title="Approve Report" onclick="showApproveConfirmation('${collection}', '${it.id}', '${normalizedData.fullName}', '${slug}')">
                                    <?php echo svg_icon('check-circle', 'w-4 h-4'); ?><span>Approve</span>
                                </button>
                                <button type="button" class="btn ${isFinal ? 'btn-disabled' : 'btn-decline'}" ${isFinal ? 'disabled' : ''} title="Decline Report" onclick="showDeclineConfirmation('${collection}', '${it.id}', '${normalizedData.fullName}', '${slug}')">
                                    <?php echo svg_icon('x-circle', 'w-4 h-4'); ?><span>Decline</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            });

            return `
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr>
                                <th class="p-4 text-left font-semibold text-slate-500 uppercase tracking-wider">Reporter Details</th>
                                <th class="p-4 text-left font-semibold text-slate-500 uppercase tracking-wider">Location</th>
                                <th class="p-4 text-left font-semibold text-slate-500 uppercase tracking-wider">Timestamp</th>
                                <th class="p-4 text-left font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                                <th class="p-4 text-right font-semibold text-slate-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/50">
                            ${tableRows}
                        </tbody>
                    </table>
                </div>
            `;
        }

        <?php endif; ?>
    });
    </script>

    <script type="module">
    import { initializeApp } from "https://www.gstatic.com/firebasejs/10.12.5/firebase-app.js";
    import { getFirestore, doc, onSnapshot, collection, query, where, orderBy, limit } from "https://www.gstatic.com/firebasejs/10.12.5/firebase-firestore.js";

    if (window.FIREBASE_CLIENT_CONFIG && window.FIREBASE_CLIENT_CONFIG.projectId) {
        const app = initializeApp(window.FIREBASE_CLIENT_CONFIG);
        const db = getFirestore(app);
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
        setInterval(loadNotificationCount, 30000);
        
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

        // Modal and status update logic moved to assets/js/main.js

        // Live Support Chat Logic moved to assets/js/main.js

        // Remaining logic moved to assets/js/main.js
</body>
</html>
