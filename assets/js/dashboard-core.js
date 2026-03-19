// Dashboard Core JS
// Extracted from dashboard.php

// Set your Firebase Web config here to enable realtime updates (onSnapshot).
window.FIREBASE_CLIENT_CONFIG = {
    apiKey: "AIzaSyDiNgvmttAwhAjPthjJtcZ1Hr9PLWnhErQ", // Firebase Web config
    authDomain: "ibantayv2.firebaseapp.com",
    projectId: "ibantayv2"
};

// Ensure theme preference is applied ASAP
(function() {
  try {
    // Force light mode as per user request
    document.documentElement.classList.remove('dark');
    localStorage.setItem('theme', 'light');
  } catch(e) {}
})();

// Request notification permission on page load
if (Notification && Notification.permission === 'default') {
    Notification.requestPermission().then(permission => {
        console.log('Notification permission:', permission);
    });
}

const categories = (window.dashboardConfig && window.dashboardConfig.categories) || window.categories;

// Main Dashboard Module - wrapper removed, using direct execution
// Helper function for SVG icons in JavaScript
function svg_icon(name, className = 'w-6 h-6') {
        const icons = {
            'dashboard': '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />',
            'truck': '<path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.125-.504 1.125-1.125V14.25m-17.25 4.5v-1.875a3.375 3.375 0 003.375-3.375h1.5a1.125 1.125 0 011.125 1.125v-1.5a3.375 3.375 0 00-3.375-3.375H9.75V7.5h1.5a3.375 3.375 0 013.375 3.375v1.5a1.125 1.125 0 001.125 1.125h1.5a3.375 3.375 0 003.375-3.375V7.5a1.125 1.125 0 00-1.125-1.125H5.625" />',
            'shield-check': '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.286zm0 13.036h.008v.017h-.008v-.017z" />',
            'fire': '<path stroke-linecap="round" stroke-linejoin="round" d="M15.362 5.214A8.252 8.252 0 0112 21 8.25 8.25 0 016.038 7.048 8.287 8.287 0 009 9.6a8.983 8.983 0 013.362-6.867 8.268 8.268 0 013 2.481z" /><path stroke-linecap="round" stroke-linejoin="round" d="M12 18a3.75 3.75 0 00.495-7.467 5.99 5.99 0 00-1.925 3.546 5.974 5.974 0 01-2.133-1.001A3.75 3.75 0 0012 18z" />',
            'home': '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h7.5" />',
            'question-mark-circle': '<path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" />',
            'user-plus': '<path stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.5a3 3 0 11-6 0 3 3 0 016 0zM4 18.75v-1.5a6.75 6.75 0 017.5-6.75h.5a6.75 6.75 0 016.75 6.75v1.5a6.75 6.75 0 01-6.75 6.75H9.75V21h7.5" />',
            'user-shield': '<path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />',
            'user-check': '<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" /><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75" />',
            'spinner': '<path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />',
            'x-mark': '<path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />',
            'identification': '<path stroke-linecap="round" stroke-linejoin="round" d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5zm6-10.125a1.875 1.875 0 11-3.75 0 1.875 1.875 0 013.75 0zm1.294 6.336a6.721 6.721 0 01-3.17.789 6.721 6.721 0 01-3.168-.789 3.376 3.376 0 016.338 0z" />',
            'user-circle': '<path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z" />',
            'eye': '<path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />',
            'check-circle': '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />',
            'x-circle': '<path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />',
            'info': '<path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />',
            'check': '<path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />'
        };
        const path = icons[name] || '';
        return `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="${className}">${path}</svg>`;
    }
    // Make svg_icon globally accessible
    window.svg_icon = svg_icon;

    // Count animation function
    function animateCount(element, target) {
        const start = 0;
        const duration = 1000; // 1 second
        const startTime = performance.now();
        
        function update(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            
            // Easing function for smooth animation
            const easeOutQuart = 1 - Math.pow(1 - progress, 4);
            const current = Math.floor(start + (target - start) * easeOutQuart);
            
            element.textContent = current;
            
            if (progress < 1) {
                requestAnimationFrame(update);
            } else {
                element.textContent = target;
            }
        }
        
        requestAnimationFrame(update);
    }

    // Helper function to normalize Firebase document data (handle field mapping for different report types)
    function normalizeFirebaseReportData(reportData) {
        // All report types have the same basic fields: contact, fullName, imageUrl, location, reporterId, status, timestamp
        // Some reports (like other_reports) might have description
        const mobileNumber = reportData.mobileNumber || reportData.contact || reportData.reporterContact || '';
        return {
            fullName: reportData.fullName || reportData.reporterName || '',
            contact: mobileNumber,
            mobileNumber: mobileNumber, // Preserve both for compatibility
            location: reportData.location || '',
            purpose: reportData.purpose || reportData.description || '', // Updated to include description
            reporterId: reportData.reporterId || '',
            imageUrl: reportData.imageUrl || '',
            status: reportData.status || 'Pending',
            priority: reportData.priority || '',
            timestamp: reportData.timestamp,
            emergencyType: reportData.emergencyType || '',
            reporterEmail: reportData.reporterEmail || '',
            latitude: reportData.latitude || (reportData.coordinates ? (reportData.coordinates._lat || reportData.coordinates.latitude) : null),
            longitude: reportData.longitude || (reportData.coordinates ? (reportData.coordinates._long || reportData.coordinates.longitude) : null)
        };
    }

    // Helper function to format Firebase timestamp with Philippines timezone
    function formatFirebaseTimestamp(timestamp) {
        if (!timestamp) return '—';
        
        try {
            let date;
            
            // Handle Firebase Timestamp object
            if (timestamp && typeof timestamp.toDate === 'function') {
                date = timestamp.toDate();
            }
            // Handle Firestore timestamp object with seconds/nanoseconds
            else if (timestamp && timestamp.seconds) {
                date = new Date(timestamp.seconds * 1000);
            }
            // Handle Firebase format "August 19, 2025 at 2:28:29 AM UTC+8"
            else if (typeof timestamp === 'string' && timestamp.includes(' at ') && timestamp.includes('UTC')) {
                // Parse: "August 19, 2025 at 2:28:29 AM UTC+8"
                const cleanTime = timestamp.replace(' at ', ' ').replace(/\s+UTC[+-]\d+$/, '');
                date = new Date(cleanTime);
            }
            // Handle ISO string or other date strings
            else if (typeof timestamp === 'string') {
                date = new Date(timestamp);
            }
            else {
                date = new Date(timestamp);
            }
            
            // Check if date is valid
            if (isNaN(date.getTime())) {
                console.error('Invalid date created from timestamp:', timestamp);
                return typeof timestamp === 'string' ? timestamp : '—';
            }
            
            // Convert to Philippines timezone (UTC+8) and format as "Aug 19, 2025 2:28 AM"
            const formatted = date.toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric', 
                year: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                hour12: true,
                timeZone: 'Asia/Manila'  // Philippines timezone
            });
            
            return formatted;
        } catch (error) {
            console.error('Error formatting timestamp:', timestamp, error);
            return typeof timestamp === 'string' ? timestamp : '—';
        }
    }

    // --- TOAST NOTIFICATIONS ---
    function showToast(message, type = 'info') {
        const toastContainer = document.getElementById('toastContainer');
        if (!toastContainer) return;
        const toast = document.createElement('div');
        const icons = {
            success: svg_icon('check', 'w-6 h-6 text-emerald-500'),
            error: svg_icon('x-mark', 'w-6 h-6 text-red-500'),
            info: svg_icon('info', 'w-6 h-6 text-sky-500')
        };
        const colors = {
            success: 'border-emerald-500/30 bg-emerald-50 text-emerald-800',
            error: 'border-red-500/30 bg-red-50 text-red-800',
            info: 'border-sky-500/30 bg-sky-50 text-sky-800'
        };

        toast.className = `relative w-full p-4 pr-12 rounded-lg shadow-lg border ${colors[type]} transform transition-all duration-300 translate-x-full opacity-0 backdrop-blur-sm bg-opacity-80`;
        toast.innerHTML = `
            <div class="flex items-start gap-3">
                <div class="flex-shrink-0">${icons[type]}</div>
                <p class="text-sm font-medium ">${message}</p>
            </div>
        `;
        
        toastContainer.appendChild(toast);

        requestAnimationFrame(() => {
            toast.classList.remove('translate-x-full', 'opacity-0');
        });

        setTimeout(() => {
            toast.classList.add('opacity-0', 'scale-95');
            toast.addEventListener('transitionend', () => toast.remove(), { once: true });
        }, 4000);
    }

    // Make showToast globally accessible
    window.showToast = showToast;

    // --- NOTIFICATION SOUND ---
    // Create a simple notification sound
    function playNotificationSound(soundType = 'default') {
        // Special handling for emergency siren
        if (soundType === 'siren') {
            try {
                const audio = new Audio('alarmsiren.mp3');
                audio.volume = 1.0; // Max volume for emergency
                audio.play().then(() => {
                    console.log('Emergency siren played');
                }).catch(e => {
                    console.error('Siren play failed:', e);
                    // Fallback to default sound if siren fails
                    playNotificationSound('default');
                });
                return;
            } catch (e) {
                console.error('Siren error:', e);
            }
        }

        try {
            // Try multiple methods to ensure sound plays
            
            // Method 1: Web Audio API (most reliable)
            if (window.AudioContext || window.webkitAudioContext) {
                const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                
                // Create a simple beep sound
                const oscillator = audioContext.createOscillator();
                const gainNode = audioContext.createGain();
                
                oscillator.connect(gainNode);
                gainNode.connect(audioContext.destination);
                
                // Simple notification tone
                oscillator.frequency.value = 800; // High pitch
                oscillator.type = 'sine';
                
                // Quick beep
                gainNode.gain.setValueAtTime(0, audioContext.currentTime);
                gainNode.gain.linearRampToValueAtTime(0.2, audioContext.currentTime + 0.01);
                gainNode.gain.linearRampToValueAtTime(0, audioContext.currentTime + 0.2);
                
                oscillator.start(audioContext.currentTime);
                oscillator.stop(audioContext.currentTime + 0.2);
                
                return;
            }
        } catch (error) {
            console.log('Web Audio API failed:', error);
        }
        
        try {
            // Method 2: HTML5 Audio with notification sound
            const audio = new Audio();
            audio.volume = 0.3;
            
            // Use a data URI for a simple beep sound
            audio.src = 'data:audio/wav;base64,UklGRhwBAABXQVZFZm10IBAAAAABAAEARKwAAIhYAQACABAAZGF0YfgAAABBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmUbByGH0O+5cyUFLYfQ8tqJNQgZZ7zv559NEAxPqOPutmMcBjiS2/LNeSsFJHfH8N+QQAoUXrPq66hWFAlFn+DyvmUbByGH0O25cyUGLYfO8tiIOAcXZrPy649OEQxKn+DyvWUcCCOH0O+zdCQDIojQ+dqNOQcXZLTz6Y9PEAxGqODyv2UcCCGF0fG6ciMGMIvO89iJOQYXZLPy6Y9PEQtGn+DyvmQcCCOG0fG6ciMGMIzO89iJOQYZY7Dz6Y5ND';
            
            audio.play().catch((e) => {
                console.log('HTML5 Audio failed:', e);
            });
            
        } catch (error) {
            console.log('HTML5 Audio method failed:', error);
        }
    }

    // Make notification sound globally accessible
    window.playNotificationSound = playNotificationSound;
    
    // Enhanced notification with visual feedback
    function showNotificationWithSound(message, type = 'success', soundType = 'default') {
        // Play sound
        playNotificationSound(soundType);
        
        // Show toast with enhanced visual feedback
        showToast(message, type);
        
        // Add visual flash effect to document title
        const originalTitle = document.title;
        let flashCount = 0;
        const flashInterval = setInterval(() => {
            document.title = flashCount % 2 === 0 ? '🔔 NEW REPORT!' : originalTitle;
            flashCount++;
            if (flashCount >= 6) { // Flash 3 times
                clearInterval(flashInterval);
                document.title = originalTitle;
            }
        }, 500);
        
        // Try to show browser notification if permission is granted
        if (Notification && Notification.permission === 'granted') {
            new Notification('ManResponde Alert', {
                body: message,
                icon: 'responde.png',
                badge: 'responde.png'
            });
        }
    }
    
    // Make enhanced notification globally accessible
    window.showNotificationWithSound = showNotificationWithSound;

    // --- API & FORM HANDLING ---
    async function handleApiFormSubmit(form, button) {
        const btnSpinner = svg_icon('spinner', 'w-4 h-4 animate-spin-fast');
        const btnOriginalContent = button.innerHTML;
        
        button.innerHTML = btnSpinner;
        button.disabled = true;

        try {
            const formData = new FormData(form);
            if (!formData.has('api_action')) {
                let action = 'update_status';
                if (form.id === 'createStaffForm') action = 'create_staff';
                else if (form.id === 'createResponderForm') action = 'create_responder';
                formData.append('api_action', action);
            }
            
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });
            
            if (!response.ok) throw new Error('Network response was not ok.');
            
            const result = await response.json();

            if (result.success) {
                showToast(result.message, 'success');
                if (form.id === 'createStaffForm' || form.id === 'createResponderForm') {
                    form.reset();
                    // Refresh admin statistics if on dashboard view after creating user
                    if (window.location.search.includes('view=dashboard') || !window.location.search.includes('view=')) {
                        refreshAdminStats();
                    }
                }
                return result;
            } else {
                throw new Error(result.message || 'An unknown error occurred.');
            }
        } catch (error) {
            showToast(error.message, 'error');
            return null;
        } finally {
            if (document.body.contains(button)) {
                button.innerHTML = btnOriginalContent;
                button.disabled = false;
            }
        }
    }

    // Show decline confirmation dialog with clear explanation
    window.showDeclineConfirmation = function(collection, docId, reporterName, categoryType) {
        showDeclineModal(collection, docId, reporterName, categoryType);
    }

    // Show approve confirmation dialog
    window.showApproveConfirmation = function(collection, docId, reporterName, categoryType) {
        showApproveModal(collection, docId, reporterName, categoryType);
    }

    // Global handler for status update forms (Approve/Decline)
    window.handleStatusUpdate = async function(event) {
        event.preventDefault();
        
        const form = event.currentTarget;
        const button = form.querySelector('button[type="submit"]');
        const originalText = button.innerHTML;
        const newStatus = form.querySelector('input[name="newStatus"]').value;
        const collection = form.querySelector('input[name="collection"]').value;
        const docId = form.querySelector('input[name="docId"]').value;
        
        // Show loading state
        button.innerHTML = svg_icon('spinner', 'w-4 h-4 animate-spin');
        button.disabled = true;
        
        try {
            const formData = new FormData(form);
            formData.append('api_action', 'update_status');
            
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                showToast(result.message, 'success');
                
                // Get the current row
                const row = form.closest('tr.report-row');
                if (row) {
                    // Update the row status badge immediately
                    const badge = row.querySelector('.status-badge');
                    if (badge) {
                        badge.classList.remove('status-badge-success', 'status-badge-pending', 'status-badge-declined');
                        const st = newStatus.toLowerCase();
                        if (st === 'approved') badge.classList.add('status-badge-success');
                        else badge.classList.add('status-badge-declined');
                        badge.innerHTML = `<span class="h-2 w-2 rounded-full bg-current mr-2"></span>${newStatus}`;
                    }
                    
                    // Disable action buttons
                    row.querySelectorAll('form[onsubmit="handleStatusUpdate(event)"] button').forEach(btn => {
                        btn.disabled = true;
                        btn.classList.add('opacity-50');
                    });
                    
                    // Move report to correct section with animation
                    moveReportToSection(row, newStatus, collection);
                }
                
                // Update counters immediately
                updateStatusCounters(collection, newStatus);
                
                // Update notification count (remove notification for approved reports)
                if (newStatus === 'Approved') {
                    setTimeout(() => {
                        loadNotificationCount();
                    }, 500);
                }

                // Refresh admin recent activity feed immediately (avoid cache delay)
                if (typeof window.refreshRecentActivity === 'function') {
                    window.forceRecentFeedRefresh = true;
                    window.refreshRecentActivity();
                }
                
            } else {
                showToast(result.message || 'Failed to update status', 'error');
            }
            
        } catch (error) {
            console.error('Error updating status:', error);
            showToast('Failed to update status - please try again', 'error');
        } finally {
            // Restore button state
            button.innerHTML = originalText;
            button.disabled = false;
        }
    };

    // Function to move report to correct section
    function moveReportToSection(row, newStatus, collection) {
        // If in Verify Users list and approved, fade out and remove the card/item
        if (newStatus.toLowerCase() === 'approved') {
            // Try to find the user card/item in pending list
            let userItem = row.closest('.vu-user-row, .vu-user-card, .vu-user-item');
            if (!userItem) userItem = row.parentNode;
            if (userItem) {
                userItem.style.transition = 'opacity 0.4s, transform 0.4s';
                userItem.style.opacity = '0';
                userItem.style.transform = 'translateX(-20px)';
                setTimeout(() => {
                    if (userItem.parentNode) userItem.parentNode.removeChild(userItem);
                }, 400);
            }
        }
        // Get report data before removing the row
        const viewBtn = row.querySelector('button.btn-view');
        const reportData = {
            id: row.dataset.id,
            collection: row.dataset.collection,
            fullName: row.querySelector('td:nth-child(1) .font-semibold')?.textContent || '',
            contact: row.querySelector('td:nth-child(1) .text-slate-500')?.textContent || '',
            location: row.querySelector('td:nth-child(2)')?.textContent || '',
            timestamp: row.querySelector('td:nth-child(3)')?.textContent || '',
            status: newStatus,
            latitude: viewBtn ? viewBtn.dataset.latitude : '',
            longitude: viewBtn ? viewBtn.dataset.longitude : ''
        };
        
        // Add fade out animation
        row.style.transition = 'opacity 0.3s ease-out, transform 0.3s ease-out';
        row.style.opacity = '0';
        row.style.transform = 'translateX(-20px)';
        
        setTimeout(() => {
            // Remove the row after animation
            row.remove();
            
            // Add report to the appropriate tab (approved/declined) and update counts
            addReportToAppropriateTab(reportData);
            
            // If we're on a specific category view, refresh that category's data
            if (window.currentView && typeof renderStaffReports === 'function') {
                // Refresh the specific category data after a short delay
                setTimeout(() => {
                    loadStaffData(true); // Force refresh
                }, 500);
            }
            
            // Show success feedback
            showToast(`Report moved to ${newStatus} section`, 'info');
        }, 300);
    }

    // Function to add report to the appropriate tab and update counts
    function addReportToAppropriateTab(reportData) {
        if (!reportData.status || !reportData.collection) return;
        
        // Find the appropriate category based on collection
        const collectionToSlug = {
            'ambulance_reports': 'ambulance',
            'fire_reports': 'fire', 
            'flood_reports': 'flood',
            'other_reports': 'other',
            'tanod_reports': 'tanod'
        };
        
        const slug = collectionToSlug[reportData.collection];
        if (!slug) return;
        
        const tabName = reportData.status.toLowerCase();
        if (!['approved', 'declined'].includes(tabName)) return;
        
        // Find the appropriate tab content panel
        const targetPanel = document.querySelector(`[data-slug="${slug}"][data-tab="${tabName}"]`);
        if (!targetPanel) return;
        
        // Find the table body in the target panel
        const tableBody = targetPanel.querySelector('tbody.divide-y');
        if (!tableBody) return;
        
        // Create new row for the report
        const newRow = document.createElement('tr');
        newRow.className = 'report-row animate-fade-in-up';
        newRow.dataset.id = reportData.id;
        newRow.dataset.collection = reportData.collection;
        newRow.style.setProperty('--anim-delay', '0ms');
        
        const statusClass = reportData.status === 'Declined' ? 'status-badge-declined' : 'status-badge-approved';
        
        newRow.innerHTML = `
            <td class="p-4 whitespace-nowrap">
                <div class="font-semibold text-slate-800">${reportData.fullName || '—'}</div>
                <div class="text-slate-500">${reportData.contact || '—'}</div>
            </td>
            <td class="p-4 text-slate-600 max-w-xs truncate">${reportData.location || '—'}</td>
            <td class="p-4 text-slate-600 whitespace-nowrap">${reportData.timestamp}</td>
            <td class="p-4">
                <span class="status-badge ${statusClass}">
                    <span class="h-2 w-2 rounded-full bg-current mr-2"></span>
                    ${reportData.status}
                </span>
            </td>
            <td class="p-4 text-right">
                <div class="inline-flex items-center gap-2">
                    <button type="button" class="btn btn-view" title="View Details"
                        onclick="showReportModal(this)"
                        data-id="${reportData.id}" data-collection="${reportData.collection}"
                        data-fullname="${reportData.fullName}" data-contact="${reportData.mobileNumber || reportData.contact}"
                        data-location="${reportData.location}" data-status="${reportData.status}"
                        data-latitude="${reportData.latitude || ''}" data-longitude="${reportData.longitude || ''}"
                        data-timestamp="${reportData.timestamp}">
                        ${svg_icon('eye', 'w-4 h-4')}<span>View</span>
                    </button>
                    <button type="button" class="btn btn-disabled" disabled title="Report Processed">
                        ${svg_icon('check-circle', 'w-4 h-4')}<span>Processed</span>
                    </button>
                </div>
            </td>
        `;
        
        // Add to top of table with animation
        tableBody.insertBefore(newRow, tableBody.firstChild);
        
        // Update the tab count
        updateTabCounts(slug, reportData.status);
        
        // Trigger animation
        setTimeout(() => {
            newRow.style.opacity = '1';
            newRow.style.transform = 'translateY(0)';
        }, 50);
        
        showToast(`${reportData.status} report added to ${tabName} tab`, 'success');
    }

    // Function to update tab counts after status change
    function updateTabCounts(slug, newStatus) {
        const segmentedControl = document.querySelector(`.segmented[data-slug="${slug}"]`);
        if (!segmentedControl) return;
        
        // Find tab count elements
        const pendingTab = segmentedControl.querySelector('[data-tab="pending"] .tab-count');
        const approvedTab = segmentedControl.querySelector('[data-tab="approved"] .tab-count');
        const declinedTab = segmentedControl.querySelector('[data-tab="declined"] .tab-count');
        
        if (pendingTab && approvedTab && declinedTab) {
            // Decrease pending count
            const pendingCount = parseInt(pendingTab.textContent) || 0;
            const newPendingCount = Math.max(0, pendingCount - 1);
            pendingTab.textContent = newPendingCount;
            
            // Increase appropriate count
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
            
            showToast(`Tab counts updated for ${slug}`, 'info');
        }
    }

    // Function to update status counters
    function updateStatusCounters(collection, newStatus) {
        // Find the category stats container
        const statsContainer = document.getElementById('adminStatsContainer');
        if (statsContainer) {
            // Update the counters based on collection
            const collectionMapping = {
                'ambulance_reports': 'Ambulance',
                'fire_reports': 'Fire',
                'flood_reports': 'Flood',
                'tanod_reports': 'Tanod',
                'other_reports': 'Other'
            };
            
            const categoryName = collectionMapping[collection];
            if (categoryName) {
                // Find the stat card for this category
                const statCards = statsContainer.querySelectorAll('.stat-card');
                statCards.forEach(card => {
                    const title = card.querySelector('h3, h4');
                    if (title && title.textContent.includes(categoryName)) {
                        // Find all counter elements in this card
                        const counterElements = card.querySelectorAll('[data-countup]');
                        let pendingElement = null;
                        let approvedElement = null;
                        
                        // Find pending, approved, and declined elements by their parent text
                        let declinedElement = null;
                        counterElements.forEach(el => {
                            const parent = el.closest('div');
                            const label = parent.querySelector('.text-xs');
                            if (label) {
                                const labelText = label.textContent.toLowerCase();
                                if (labelText.includes('pending')) {
                                    pendingElement = el;
                                } else if (labelText.includes('approved')) {
                                    approvedElement = el;
                                } else if (labelText.includes('declined')) {
                                    declinedElement = el;
                                }
                            }
                        });
                        
                        if (pendingElement && approvedElement && declinedElement) {
                            // Decrease pending count
                            const pendingCount = parseInt(pendingElement.textContent) || 0;
                            const newPendingCount = Math.max(0, pendingCount - 1);
                            pendingElement.textContent = newPendingCount;
                            pendingElement.dataset.countup = newPendingCount;
                            
                            // Handle status updates
                            if (newStatus === 'Approved') {
                                // Increase approved count
                                const approvedCount = parseInt(approvedElement.textContent) || 0;
                                const newApprovedCount = approvedCount + 1;
                                approvedElement.textContent = newApprovedCount;
                                approvedElement.dataset.countup = newApprovedCount;
                                
                                // Add pulse animation to approved element
                                approvedElement.classList.add('animate-pulse');
                                setTimeout(() => {
                                    approvedElement.classList.remove('animate-pulse');
                                }, 1000);
                            } else if (newStatus === 'Declined') {
                                // Increase declined count
                                const declinedCount = parseInt(declinedElement.textContent) || 0;
                                const newDeclinedCount = declinedCount + 1;
                                declinedElement.textContent = newDeclinedCount;
                                declinedElement.dataset.countup = newDeclinedCount;
                                
                                // Add pulse animation to declined element
                                declinedElement.classList.add('animate-pulse');
                                setTimeout(() => {
                                    declinedElement.classList.remove('animate-pulse');
                                }, 1000);
                                
                                // Update progress bars with new declined count
                                updateProgressBars(card, approvedElement, pendingElement, declinedElement);
                            }
                            
                            // Add pulse animation to pending element (decreased)
                            pendingElement.classList.add('animate-pulse');
                            setTimeout(() => {
                                pendingElement.classList.remove('animate-pulse');
                            }, 1000);
                            
                            // Show success message
                            showToast(`${categoryName} counters updated`, 'success');
                        }
                    }
                });
            }
        }
    }

    // Function to update progress bars after status change
    function updateProgressBars(card, approvedElement, pendingElement, declinedElement) {
        const approved = parseInt(approvedElement.textContent) || 0;
        const pending = parseInt(pendingElement.textContent) || 0;
        const declined = parseInt(declinedElement.textContent) || 0;
        const total = approved + pending + declined;
        
        if (total > 0) {
            const approvedPct = Math.round((approved / total) * 100);
            const pendingPct = Math.round((pending / total) * 100);
            const declinedPct = Math.round((declined / total) * 100);
            
            // Update progress bars
            const progressTrack = card.querySelector('.progress-track');
            if (progressTrack) {
                const pendingSeg = progressTrack.querySelector('.progress-seg.pending');
                const approvedSeg = progressTrack.querySelector('.progress-seg.approved');
                const declinedSeg = progressTrack.querySelector('.progress-seg.declined');
                
                if (pendingSeg) pendingSeg.setAttribute('data-w', `${pendingPct}%`);
                if (approvedSeg) approvedSeg.setAttribute('data-w', `${approvedPct}%`);
                if (declinedSeg) declinedSeg.setAttribute('data-w', `${declinedPct}%`);
                
                // Update progress bar widths with animation
                setTimeout(() => {
                    if (pendingSeg) pendingSeg.style.width = `${pendingPct}%`;
                    if (approvedSeg) approvedSeg.style.width = `${approvedPct}%`;
                    if (declinedSeg) declinedSeg.style.width = `${declinedPct}%`;
                }, 100);
            }
            
            // Update percentage labels
            const percentageLabels = card.querySelectorAll('.text-xs .flex');
            if (percentageLabels.length >= 3) {
                const pendingLabel = percentageLabels[0].querySelector('span:last-child');
                const approvedLabel = percentageLabels[1].querySelector('span:last-child');
                const declinedLabel = percentageLabels[2].querySelector('span:last-child');
                
                if (pendingLabel) pendingLabel.textContent = `${pendingPct}% Pending`;
                if (approvedLabel) approvedLabel.textContent = `${approvedPct}% Approved`;
                if (declinedLabel) declinedLabel.textContent = `${declinedPct}% Declined`;
            }
        }
    }
    

    
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

        const btnSpinner = svg_icon('spinner', 'w-4 h-4 animate-spin-fast');
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

    // Attach listener for Admin 'Create Staff' form
    const createStaffForm = document.getElementById('createStaffForm');
    if (createStaffForm) {
        createStaffForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const button = createStaffForm.querySelector('button[type="submit"]');
            const result = await handleApiFormSubmit(createStaffForm, button);

            // Refresh staff data if a new staff was created
            if (result && result.refreshStaffData) {
                console.log('New staff created, refreshing staff data...');
                setTimeout(() => {
                    loadStaffData();
                }, 1000); // Small delay to ensure backend is updated
            }
        });
    }

    // Attach listener for Admin 'Create Responder' form
    const createResponderForm = document.getElementById('createResponderForm');
    if (createResponderForm) {
        createResponderForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const button = createResponderForm.querySelector('button[type="submit"]');
            await handleApiFormSubmit(createResponderForm, button);
        });
    }

    // Attach listener for Admin 'Create Account' form (unified Staff/Responder)
    const createAccountForm = document.getElementById('createAccountForm');
    if (createAccountForm) {
        // Handle Tanod and Police Barangay/Outpost Selection
        const tanodCheckbox = createAccountForm.querySelector('input[name="categories[]"][value="tanod"]');
        const policeCheckbox = createAccountForm.querySelector('input[name="categories[]"][value="police"]');
        const barangaySection = document.getElementById('barangaySelection');
        const barangaySelect = document.getElementById('assignedBarangay');

        function toggleBarangaySelection() {
            const isTanod = tanodCheckbox && tanodCheckbox.checked;
            const isPolice = policeCheckbox && policeCheckbox.checked;

            if (isTanod || isPolice) {
                barangaySection.classList.remove('hidden');
                barangaySelect.required = true;
            } else {
                barangaySection.classList.add('hidden');
                barangaySelect.required = false;
                barangaySelect.value = '';
            }
        }

        if (barangaySection && barangaySelect) {
            if (tanodCheckbox) {
                tanodCheckbox.addEventListener('change', toggleBarangaySelection);
            }
            if (policeCheckbox) {
                policeCheckbox.addEventListener('change', toggleBarangaySelection);
            }
        }

        createAccountForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            // Check if at least one role is selected
            const selectedRoles = createAccountForm.querySelectorAll('input[name="accountTypes[]"]:checked');
            const roleError = document.getElementById('roleSelectionError');
            
            if (selectedRoles.length === 0) {
                roleError.classList.remove('hidden');
                return;
            }
            
            roleError.classList.add('hidden');
            const button = createAccountForm.querySelector('button[type="submit"]');
            const result = await handleApiFormSubmit(createAccountForm, button);

            // Clear form and show success message
            if (result && result.success) {
                createAccountForm.reset();
                // Uncheck all role checkboxes
                selectedRoles.forEach(cb => cb.checked = false);
            }
        });
    }

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
            const formData = new FormData();
            formData.append('api_action', 'get_staff_data');

            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData
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

    // Skeleton loader generator
    function getSkeletonLoader(count = 3) {
        let html = '';
        for (let i = 0; i < count; i++) {
            html += `
            <div class="animate-pulse flex space-x-4 p-4 border-b border-gray-100">
                <div class="rounded-full bg-gray-200 h-10 w-10"></div>
                <div class="flex-1 space-y-3 py-1">
                    <div class="h-2 bg-gray-200 rounded w-3/4"></div>
                    <div class="space-y-2">
                        <div class="h-2 bg-gray-200 rounded"></div>
                        <div class="h-2 bg-gray-200 rounded w-5/6"></div>
                    </div>
                </div>
            </div>`;
        }
        return html;
    }
    window.getSkeletonLoader = getSkeletonLoader;

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
        
        // Reset map container
        if (mapContainer) {
            mapContainer.classList.add('hidden');
            if (window.reportMap) {
                window.reportMap.remove();
                window.reportMap = null;
            }
        }

        if (ds.location && ds.location !== '—' && ds.location.trim() !== '' && mapContainer) {
            mapContainer.classList.remove('hidden');
            if (mapStatus) mapStatus.textContent = 'Locating...';
            
            // Function to init map
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
                        
                    // Force map redraw after modal animation to prevent gray tiles
                    setTimeout(() => {
                        window.reportMap.invalidateSize();
                    }, 300);
                    
                    if (mapStatus) mapStatus.textContent = 'Location found';
                }, 100);
            };

            // 1. Try to use explicit coordinates from data attributes
            if (ds.latitude && ds.longitude && !isNaN(parseFloat(ds.latitude)) && !isNaN(parseFloat(ds.longitude))) {
                console.log('Using explicit coordinates:', ds.latitude, ds.longitude);
                initMap(parseFloat(ds.latitude), parseFloat(ds.longitude), ds.location);
            }
            // 2. Try to parse coordinates from string (e.g. "14.5, 121.0")
            else {
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
                ${svg_icon(meta.icon || 'question-mark-circle', 'w-6 h-6')}
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

        // Show Approved/Declined By info
        const approvedByContainer = document.getElementById('m_approved_by_container');
        if (approvedByContainer) {
             if ((st === 'approved' || st === 'declined') && ds.updatedby) {
                approvedByContainer.innerHTML = `
                    <div class="inline-block text-left bg-white/50 backdrop-blur-sm rounded-xl p-3 border border-gray-200/50 shadow-sm">
                        <div class="text-sm text-gray-600 flex items-center gap-2 justify-center">
                            ${svg_icon('user-check', 'w-4 h-4 text-gray-400')}
                            <span>${st === 'approved' ? 'Approved' : 'Declined'} by <span class="font-bold text-gray-800">${ds.updatedby}</span></span>
                        </div>
                        <div class="text-xs text-gray-500 mt-1 flex items-center gap-2 justify-center">
                            ${svg_icon('info', 'w-3 h-3 text-gray-400')}
                            <span>${window.formatFirebaseTimestamp ? window.formatFirebaseTimestamp(ds.updatedat) : ds.updatedat}</span>
                        </div>
                    </div>
                `;
                approvedByContainer.classList.remove('hidden');
             } else {
                approvedByContainer.classList.add('hidden');
                approvedByContainer.innerHTML = '';
             }
        }

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
        const isResponded = st === 'responded';
        
        const approveBtnClass = isFinal ? 'btn-disabled' : 'btn-approve';
        const declineBtnClass = isFinal ? 'btn-disabled' : 'btn-decline';
        const respondedBtnClass = (isFinal || isResponded) ? 'btn-disabled' : 'btn-responded';
        const disabledAttr = isFinal ? 'disabled' : '';
        const respondedDisabledAttr = (isFinal || isResponded) ? 'disabled' : '';

        actionsContainer.innerHTML = `
            <button type="button" class="btn ${respondedBtnClass}" ${respondedDisabledAttr} title="Mark as Responded" onclick="updateReportStatus('${ds.collection}', '${ds.id}', 'Responded')">
                ${svg_icon('truck', 'w-4 h-4')}<span>Responded</span>
            </button>
            <button type="button" class="btn ${approveBtnClass}" ${disabledAttr} title="Approve Report" onclick="showApproveConfirmation('${ds.collection}', '${ds.id}', '${ds.fullName}', '${ds.slug}')">
                ${svg_icon('check-circle', 'w-4 h-4')}<span>Approve</span>
            </button>
            <button type="button" class="btn ${declineBtnClass}" ${disabledAttr} title="Decline Report" onclick="showDeclineConfirmation('${ds.collection}', '${ds.id}', '${ds.fullName}', '${ds.slug}')">
                ${svg_icon('x-circle', 'w-4 h-4')}<span>Decline</span>
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
            imgEl.src = '';
            imgEl.alt = 'No image available';
            linkEl.href = '#';
        }

        proofModal.classList.remove('pointer-events-none');
        proofModal.classList.add('opacity-100');
        modalContent.classList.remove('scale-95', 'opacity-0');
    };

    // Segmented Control Styles
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

    // Count Animation
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

    // Progress Bar Animation
    (function() {
        document.querySelectorAll('.progress-seg').forEach(seg => {
            const w = seg.getAttribute('data-w') || '0%';
            seg.style.transition = 'width 900ms cubic-bezier(0.16, 1, 0.3, 1)';
            requestAnimationFrame(() => { seg.style.width = w; });
        });
    })();
    
    // Segmented Control Logic
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

    // Recent Activity Logic
    (function() {
        const list = document.getElementById('activityList');
        console.log('[RecentActivity] activityList element:', list ? 'FOUND' : 'NOT FOUND');
        if (!list) {
            console.warn('[RecentActivity] activityList not found - Recent Activity features disabled');
            return;
        }

        const pageSizeEl = document.getElementById('activityPageSize');
        const rangeEl   = document.getElementById('activityRange');
        const prevBtn   = document.getElementById('activityPrev');
        const nextBtn   = document.getElementById('activityNext');

        let total = 0;
        let currentPage = 1;
        let pageSize = pageSizeEl ? parseInt(pageSizeEl.value || '20', 10) : 20;

        async function loadRecentPage(page = 1, retryCount = 0) {
            const maxRetries = 3;
            const retryDelay = 1000 * Math.pow(2, retryCount);
            
            const isBackgroundRefresh = window.isBackgroundRefresh === true;
            window.isBackgroundRefresh = false;

            if (retryCount === 0 && !isBackgroundRefresh) {
                list.innerHTML = `
                    <div class="text-center py-16">
                        <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-gradient-to-br from-blue-100 to-purple-100 flex items-center justify-center">
                            ${svg_icon('spinner', 'w-8 h-8 text-blue-500 animate-spin')}
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
                if (window.forceRecentFeedRefresh === true) {
                    fd.append('force_refresh', 'true');
                    window.forceRecentFeedRefresh = false;
                }
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

                // Staff-like realtime: if the newest (top) item changes during background refresh,
                // notify admin and keep feed in sync.
                try {
                    const top = data[0];
                    const sig = top ? `${top.collection || ''}:${top.id || ''}:${top.status || ''}:${top.timestamp || top.tsDisplay || ''}` : '';
                    if (!window.__adminRecentSig) {
                        window.__adminRecentSig = sig;
                        window.__adminRecentSigInit = true;
                    } else if (sig && sig !== window.__adminRecentSig) {
                        window.__adminRecentSig = sig;
                        // Only alert on background refresh/polling to avoid firing on filter changes.
                        if (isBackgroundRefresh && !document.hidden && typeof window.showNotificationWithSound === 'function') {
                            window.showNotificationWithSound('New report received!', 'info', 'siren');
                        }
                    }
                } catch (e) {
                    // ignore notify errors
                }
                
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
                            case 'responded':
                                return {
                                    bgColor: 'from-blue-500 to-cyan-600',
                                    textColor: 'text-blue-700',
                                    dotColor: 'bg-blue-500',
                                    borderColor: 'border-blue-200',
                                    label: 'Responded'
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
                        data-updatedby="${esc(row.updatedBy)}" data-updatedat="${esc(row.updatedAt)}"
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
                                    ${svg_icon('home', 'w-4 h-4 text-gray-400')}
                                    <span class="text-sm text-gray-500 truncate">${esc(row.location || 'No location specified')}</span>
                                </div>
                                
                                <div class="flex items-center justify-between">
                                    <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-semibold ${statusConfig.textColor} bg-gradient-to-r from-white to-gray-50 border ${statusConfig.borderColor} shadow-sm">
                                        <span class="w-2 h-2 rounded-full ${statusConfig.dotColor} animate-pulse"></span>
                                        ${statusConfig.label}
                                    </span>
                                    
                                    ${(st === 'approved' || st === 'declined') && row.updatedBy ? `
                                    <div class="text-xs text-gray-500 text-right">
                                        <div>${st === 'approved' ? 'Approved' : 'Declined'} by <span class="font-medium text-gray-700">${esc(row.updatedBy)}</span></div>
                                        <div>${window.formatFirebaseTimestamp ? window.formatFirebaseTimestamp(row.updatedAt) : row.updatedAt}</div>
                                    </div>
                                    ` : `
                                    <div class="opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                        ${svg_icon('check', 'w-5 h-5 text-gray-400')}
                                    </div>
                                    `}
                                </div>
                            </div>
                        </div>
                    </li>`;
                }).join('') : `
                    <div class="text-center py-16">
                        <div class="w-20 h-20 mx-auto mb-6 rounded-full bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center">
                            ${svg_icon('x-circle', 'w-10 h-10 text-gray-400')}
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
                    setTimeout(() => {
                        loadRecentPage(page, retryCount + 1);
                    }, retryDelay);
                    
                    list.innerHTML = `
                        <div class="text-center py-16">
                            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-gradient-to-br from-yellow-100 to-orange-100 flex items-center justify-center">
                                ${svg_icon('spinner', 'w-8 h-8 text-yellow-500 animate-spin')}
                            </div>
                            <p class="text-lg font-semibold text-gray-600">Retrying Connection</p>
                            <p class="text-sm text-gray-400 mt-1">Attempt ${retryCount + 1} of ${maxRetries}</p>
                        </div>
                    `;
                } else {
                    list.innerHTML = `
                        <div class="text-center py-16">
                            <div class="w-20 h-20 mx-auto mb-6 rounded-full bg-gradient-to-br from-red-100 to-pink-100 flex items-center justify-center">
                                ${svg_icon('x-circle', 'w-10 h-10 text-red-500')}
                            </div>
                            <p class="text-xl font-semibold text-red-600 mb-2">Connection Failed</p>
                            <p class="text-gray-500 mb-6 max-w-sm mx-auto">Unable to load recent activity: ${error.message}</p>
                            <button onclick="loadRecentPage(${page})" class="btn btn-primary glow">
                                Try Again
                            </button>
                        </div>
                    `;
                }
            }
        }

        if (pageSizeEl) pageSizeEl.addEventListener('change', () => {
            pageSize = parseInt(pageSizeEl.value || '20', 10) || 20;
            loadRecentPage(1);
        });
        
        if (prevBtn) prevBtn.addEventListener('click', () => currentPage > 1 && loadRecentPage(currentPage - 1));
        if (nextBtn) nextBtn.addEventListener('click', () => {
            const totalPages = Math.max(1, Math.ceil(total / pageSize));
            if (currentPage < totalPages) loadRecentPage(currentPage + 1);
        });

        const searchEl = document.getElementById('activitySearch');
        const categoryEl = document.getElementById('activityCategory');
        const statusEl = document.getElementById('activityStatus');
        const resetEl = document.getElementById('activityReset');
        
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
        
        if (searchEl) {
            searchEl.addEventListener('input', debounce(() => {
                currentPage = 1;
                loadRecentPage(1);
            }, 300));
        }
        
        if (categoryEl) {
            categoryEl.addEventListener('change', () => {
                currentPage = 1;
                loadRecentPage(1);
            });
        }
        
        if (statusEl) {
            statusEl.addEventListener('change', () => {
                currentPage = 1;
                loadRecentPage(1);
            });
        }
        
        if (resetEl) {
            resetEl.addEventListener('click', () => {
                if (searchEl) searchEl.value = '';
                if (categoryEl) categoryEl.value = 'all';
                if (statusEl) statusEl.value = 'all';
                currentPage = 1;
                loadRecentPage(1);
            });
        }

        loadRecentPage(1);
        
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
                } catch (e) {}
            };
            warmCache();
        }, 2000);

        window.loadRecentPage = loadRecentPage;
        
        window.refreshRecentActivity = function() {
            window.isBackgroundRefresh = true;
            loadRecentPage(currentPage);
        };

        // Firebase Realtime listeners handle updates - no polling needed
        // Realtime listeners are set up in dashboard.php using modular Firebase SDK
        console.log('[RecentActivity] ✅ Ready - Firebase Realtime listeners in dashboard.php handle updates');
    })();

// Note: Firebase Realtime listeners are initialized in dashboard.php using the modular SDK
// The compat SDK (firebase.initializeApp) is not loaded, so we skip this section


