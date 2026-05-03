        document.addEventListener('DOMContentLoaded', () => {
        
            // Request notification permission on page load
            if (Notification && Notification.permission === 'default') {
                Notification.requestPermission().then(permission => {
                    console.log('Notification permission:', permission);
                });
            }
    
        const categories = <?php echo json_encode($categories); ?>;
    
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
                'logout': '<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />',
                'download': '<path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M7.5 10.5L12 15m0 0l4.5-4.5M12 15V3" />',
                'chart-pie': '<path stroke-linecap="round" stroke-linejoin="round" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" /><path stroke-linecap="round" stroke-linejoin="round" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" />',
                'map': '<path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />',
                'chat': '<path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>',
                'x-mark': '<path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />',
                'eye': '<path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />',
                'check-circle': '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />',
                'x-circle': '<path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />',
                'identification': '<path stroke-linecap="round" stroke-linejoin="round" d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5zm6-10.125a1.875 1.875 0 11-3.75 0 1.875 1.875 0 013.75 0zm1.294 6.336a6.721 6.721 0 01-3.17.789 6.721 6.721 0 01-3.168-.789 3.376 3.376 0 016.338 0z" />',
                'user-circle': '<path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z" />',
                'clock': '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />',
                'info': '<path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />',
                'check': '<path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />',
                'spinner': '<path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />',
                'phone': '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-2.824-1.47-5.112-3.758-6.582-6.582l1.293-.97c.362-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" />',
                'user': '<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />'
            };
            const path = icons[name] || '';
            return `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="${className}">${path}</svg>`;
        }

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
                reporterEmail: reportData.reporterEmail || ''
            };
        }

        // Helper function to format Firebase timestamp with Philippines timezone
        function formatFirebaseTimestamp(timestamp) {
            if (!timestamp) return '—';
            
            console.log('Formatting timestamp:', timestamp, 'Type:', typeof timestamp);
            
            try {
                let date;
                
                // Handle Firebase Timestamp object
                if (timestamp && typeof timestamp.toDate === 'function') {
                    date = timestamp.toDate();
                    console.log('Firebase timestamp object converted to date:', date);
                }
                // Handle Firestore timestamp object with seconds/nanoseconds
                else if (timestamp && timestamp.seconds) {
                    date = new Date(timestamp.seconds * 1000);
                    console.log('Firestore timestamp converted to date:', date);
                }
                // Handle Firebase format "August 19, 2025 at 2:28:29 AM UTC+8"
                else if (typeof timestamp === 'string' && timestamp.includes(' at ') && timestamp.includes('UTC')) {
                    // Parse: "August 19, 2025 at 2:28:29 AM UTC+8"
                    const cleanTime = timestamp.replace(' at ', ' ').replace(/\s+UTC[+-]\d+$/, '');
                    date = new Date(cleanTime);
                    console.log('Firebase format string converted to date:', cleanTime, '=>', date);
                }
                // Handle ISO string or other date strings
                else if (typeof timestamp === 'string') {
                    date = new Date(timestamp);
                    console.log('String timestamp converted to date:', date);
                }
                else {
                    date = new Date(timestamp);
                    console.log('Other timestamp converted to date:', date);
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
                
                console.log('Final formatted timestamp:', formatted);
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
                success: `<?php echo svg_icon('check', 'w-6 h-6 text-emerald-500'); ?>`,
                error: `<?php echo svg_icon('x-mark', 'w-6 h-6 text-red-500'); ?>`,
                info: `<?php echo svg_icon('info', 'w-6 h-6 text-sky-500'); ?>`
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

        // --- AUDIO UNLOCK ON FIRST USER INTERACTION ---
        // Browsers require user interaction before playing audio
        // This unlocks audio on the first click/touch/keypress
        let audioUnlocked = false;
        let audioContext = null;
        
        function unlockAudio() {
            if (audioUnlocked) return;
            
            try {
                // Create and resume AudioContext
                audioContext = new (window.AudioContext || window.webkitAudioContext)();
                
                // Create a silent buffer and play it
                const buffer = audioContext.createBuffer(1, 1, 22050);
                const source = audioContext.createBufferSource();
                source.buffer = buffer;
                source.connect(audioContext.destination);
                source.start(0);
                
                // Resume context if suspended
                if (audioContext.state === 'suspended') {
                    audioContext.resume();
                }
                
                // Also try HTML5 Audio
                const silentAudio = new Audio('data:audio/wav;base64,UklGRiQAAABXQVZFZm10IBAAAAABAAEARKwAAIhYAQACABAAZGF0YQAAAAA=');
                silentAudio.volume = 0.01;
                silentAudio.play().catch(() => {});
                
                audioUnlocked = true;
                console.log('🔊 Audio unlocked - notification sounds will now work');
                
                // Remove listeners after unlock
                document.removeEventListener('click', unlockAudio);
                document.removeEventListener('touchstart', unlockAudio);
                document.removeEventListener('keydown', unlockAudio);
            } catch (e) {
                console.log('Audio unlock attempt:', e.message);
            }
        }
        
        // Listen for first user interaction
        document.addEventListener('click', unlockAudio, { once: false });
        document.addEventListener('touchstart', unlockAudio, { once: false });
        document.addEventListener('keydown', unlockAudio, { once: false });

        // --- NOTIFICATION SOUND ---
        // Create a simple notification sound
        function playNotificationSound(soundType = 'default') {
            // Check if audio is unlocked
            if (!audioUnlocked) {
                console.log('⚠️ Audio not yet unlocked - click anywhere on page first');
            }
            
            // Special handling for emergency siren
            if (soundType === 'siren') {
                try {
                    const audio = new Audio('alarmsiren.mp3');
                    audio.volume = 1.0; // Max volume for emergency
                    audio.play().then(() => {
                        console.log('🔊 Emergency siren played');
                    }).catch(e => {
                        // Silently fall back to beep sound
                        console.log('Siren unavailable, using beep');
                        playBeepSound();
                    });
                    return;
                } catch (e) {
                    playBeepSound();
                }
                return;
            }
            
            playBeepSound();
        }
        
        function playBeepSound() {
            try {
                // Use the unlocked AudioContext if available
                const ctx = audioContext || new (window.AudioContext || window.webkitAudioContext)();
                
                if (ctx.state === 'suspended') {
                    ctx.resume();
                }
                
                // Facebook Messenger-style "pop" notification sound
                // Two quick notes: high then higher, with a nice decay
                const now = ctx.currentTime;
                
                // First note - the "pop"
                const osc1 = ctx.createOscillator();
                const gain1 = ctx.createGain();
                osc1.connect(gain1);
                gain1.connect(ctx.destination);
                osc1.frequency.setValueAtTime(830, now); // E5
                osc1.frequency.setValueAtTime(880, now + 0.08); // A5 slide up
                osc1.type = 'sine';
                gain1.gain.setValueAtTime(0, now);
                gain1.gain.linearRampToValueAtTime(0.4, now + 0.02);
                gain1.gain.exponentialRampToValueAtTime(0.01, now + 0.15);
                osc1.start(now);
                osc1.stop(now + 0.15);
                
                // Second note - the "ding" (slightly delayed)
                const osc2 = ctx.createOscillator();
                const gain2 = ctx.createGain();
                osc2.connect(gain2);
                gain2.connect(ctx.destination);
                osc2.frequency.value = 1320; // E6 - higher octave
                osc2.type = 'sine';
                gain2.gain.setValueAtTime(0, now + 0.08);
                gain2.gain.linearRampToValueAtTime(0.3, now + 0.1);
                gain2.gain.exponentialRampToValueAtTime(0.01, now + 0.35);
                osc2.start(now + 0.08);
                osc2.stop(now + 0.35);
                
                console.log('🔊 Notification sound played');
            } catch (error) {
                console.log('Notification sound failed:', error.message);
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
            const btnSpinner = '<?php echo svg_icon('spinner', 'w-4 h-4 animate-spin-fast'); ?>';
            const btnOriginalContent = button.innerHTML;
            
            button.innerHTML = btnSpinner;
            button.disabled = true;
    
            try {
                const formData = new FormData(form);
                // Add CSRF token
                formData.append('<?php echo CSRF_TOKEN_NAME; ?>', getCsrfToken());
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
            button.innerHTML = '<?php echo svg_icon('spinner', 'w-4 h-4 animate-spin'); ?>';
            button.disabled = true;
            
            try {
                const formData = new FormData(form);
                formData.append('<?php echo CSRF_TOKEN_NAME; ?>', getCsrfToken());
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
                        let userItem = form.closest('.vu-user-row, .vu-user-card, .vu-user-item');
                        if (!userItem) userItem = form.parentNode;
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
            const reportData = {
                id: row.dataset.id,
                collection: row.dataset.collection,
                fullName: row.querySelector('td:nth-child(1) .font-semibold')?.textContent || '',
                contact: row.querySelector('td:nth-child(1) .text-slate-500')?.textContent || '',
                location: row.querySelector('td:nth-child(2)')?.textContent || '',
                timestamp: row.querySelector('td:nth-child(3)')?.textContent || '',
                status: newStatus
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
                if (currentView && typeof renderStaffReports === 'function') {
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
                            data-timestamp="${reportData.timestamp}">
                            <?php echo svg_icon('eye', 'w-4 h-4'); ?><span>View</span>
                        </button>
                        <button type="button" class="btn btn-disabled" disabled title="Report Processed">
                            <?php echo svg_icon('check-circle', 'w-4 h-4'); ?><span>Processed</span>
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
                    const formData = createFormDataWithCsrf();
                    formData.append('api_action', 'load_staff_data');
                    formData.append('force_refresh', 'true');
                    
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
        if (window.location.search.includes('view=create-account') || window.location.search.includes('view=create-staff')) {
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

            // Generate staff/responder list HTML
            const staffHtml = staff.map(staffMember => {
                const normalizedStatus = String(staffMember.status || '').toLowerCase();
                const isActive = normalizedStatus === 'active' || normalizedStatus === 'approved';
                const normalizedRole = String(staffMember.role || 'staff').toLowerCase();
                const roleLabel = normalizedRole === 'responder' ? 'Responder' : 'Staff';
                const roleBadgeClass = normalizedRole === 'responder'
                    ? 'bg-violet-100 text-violet-700'
                    : 'bg-sky-100 text-sky-700';
                const categoryCount = staffMember.categories ? staffMember.categories.length : 0;

                return `
                    <div class="bg-white rounded-lg border border-slate-200 p-4 hover:shadow-md transition-shadow">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-gradient-to-br from-sky-500 to-blue-600 rounded-full flex items-center justify-center text-white font-bold">
                                    ${staffMember.name ? staffMember.name.charAt(0).toUpperCase() : '?'}
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h4 class="font-semibold text-slate-800">${staffMember.name || 'Unknown'}</h4>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold ${roleBadgeClass}">${roleLabel}</span>
                                    </div>
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

                // 0. Check for direct coordinates from dataset
                if (ds.lat && ds.lng && ds.lat !== 'null' && ds.lng !== 'null' && !isNaN(parseFloat(ds.lat)) && !isNaN(parseFloat(ds.lng))) {
                     initMap(parseFloat(ds.lat), parseFloat(ds.lng), ds.location);
                } else {
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
            const stRaw = String(ds.status || 'Pending').trim().toLowerCase();
            const st = stRaw === 'faile' ? 'failed' : stRaw;
            const statusLabel = st === 'failed' ? 'Failed' : (ds.status || 'Pending');
            statusEl.innerHTML = `<span class="h-2 w-2 rounded-full bg-current mr-2"></span>${statusLabel}`;
            statusEl.className = 'status-badge ml-2';
            if (st === 'approved') statusEl.classList.add('status-badge-success');
            else if (st === 'declined' || st === 'failed') statusEl.classList.add('status-badge-declined');
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
            const isFinal = st === 'approved' || st === 'declined' || st === 'responded';
            
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
        
        // Export functions are now handled by assets/js/common-modals.js
        
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
            async function loadRecentPage(page = 1, retryCount = 0, forceRefresh = false) {
                const maxRetries = 3;
                const retryDelay = 1000 * Math.pow(2, retryCount); // Exponential backoff
                
                // Show loading state with better feedback only on first load or explicit page change
                // Don't show full loading spinner on background refreshes
                const isBackgroundRefresh = window.isBackgroundRefresh === true;
                window.isBackgroundRefresh = false; // Reset flag

                if (retryCount === 0 && !isBackgroundRefresh && !forceRefresh && list.children.length === 0) {
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
                    
                    if (forceRefresh) {
                        fd.append('force_refresh', 'true');
                    }
                    
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
                    
                    // Initialize server signature from the response if available
                    if (json.signature && page === 1) {
                        window.lastServerSignature = json.signature;
                    }
                    
                    // Store recent feed data globally for modal fallback
                    window.recentFeedData = data;
                    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
                    
                    // Debug: log approver fields for first approved item
                    const approvedItem = data.find(r => r.status?.toLowerCase() === 'approved');
                    if (approvedItem) {
                        console.log('Approved item data:', {
                            id: approvedItem.id,
                            status: approvedItem.status,
                            approvedBy: approvedItem.approvedBy,
                            approvedByName: approvedItem.approvedByName,
                            approvedAt: approvedItem.approvedAt,
                            updatedBy: approvedItem.updatedBy,
                            updatedAt: approvedItem.updatedAt
                        });
                    }
                    
                    const html = data.length > 0 ? data.map((row, index) => {
                        const stRaw = String(row.status || 'Pending').trim().toLowerCase();
                        const st = stRaw === 'faile' ? 'failed' : stRaw;
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
                                case 'failed':
                                    return {
                                        bgColor: 'from-red-500 to-rose-600',
                                        textColor: 'text-red-700',
                                        dotColor: 'bg-red-500',
                                        borderColor: 'border-red-200',
                                        label: 'Failed'
                                    };
                                case 'responding':
                                    return {
                                        bgColor: 'from-purple-500 to-fuchsia-600',
                                        textColor: 'text-purple-700',
                                        dotColor: 'bg-purple-500',
                                        borderColor: 'border-purple-200',
                                        label: 'Responding'
                                    };
                                case 'responded':
                                    return {
                                        bgColor: 'from-blue-500 to-cyan-600',
                                        textColor: 'text-blue-700',
                                        dotColor: 'bg-blue-500',
                                        borderColor: 'border-blue-200',
                                        label: 'Responded'
                                    };
                                case 'resolved':
                                    return {
                                        bgColor: 'from-gray-500 to-slate-600',
                                        textColor: 'text-gray-700',
                                        dotColor: 'bg-gray-500',
                                        borderColor: 'border-gray-200',
                                        label: 'Resolved'
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
                        
                        // Build approver/action info - check multiple possible fields
                        let actionInfo = '';
                        // For approved: show approver name, with multiple fallbacks
                        if (st === 'approved') {
                            const approverName = row.approvedByName || row.updatedBy || '';
                            const approveTime = row.approvedAt || row.updatedAt || '';
                            if (approverName) {
                                actionInfo = `<div class="flex items-center gap-1.5 mt-2 text-xs text-green-600">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <span>Approved by <strong>${esc(approverName)}</strong>${approveTime ? ' • ' + esc(approveTime) : ''}</span>
                                </div>`;
                            }
                        } else if (st === 'declined' || st === 'failed') {
                            const declinerName = row.declinedByName || row.updatedBy || '';
                            const declineTime = row.declinedAt || row.updatedAt || '';
                            if (declinerName) {
                                actionInfo = `<div class="flex items-center gap-1.5 mt-2 text-xs text-red-600">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <span>${st === 'failed' ? 'Failed' : 'Declined'} by <strong>${esc(declinerName)}</strong>${declineTime ? ' • ' + esc(declineTime) : ''}</span>
                                </div>`;
                            }
                        } else if (st === 'responded') {
                            const responderName = row.respondedByName || row.respondedBy || '';
                            const respondTime = row.respondedAt || '';
                            if (responderName) {
                                actionInfo = `<div class="flex items-center gap-1.5 mt-2 text-xs text-blue-600">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                    </svg>
                                    <span>Responded by <strong>${esc(responderName)}</strong>${respondTime ? ' • ' + esc(respondTime) : ''}</span>
                                </div>`;
                            }
                        }
                        
                        return `
                        <li
                            onclick="showReportModal(this)" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();showReportModal(this);}"
                            role="button" tabindex="0"
                            data-slug="${esc(row.slug)}" data-id="${esc(row.id)}" data-collection="${esc(row.collection)}"
                            data-fullname="${esc(row.fullName)}" data-contact="${esc(row.mobileNumber || row.contact)}"
                            data-location="${esc(row.location)}" data-purpose="${esc(row.purpose)}"
                            data-reporterid="${esc(row.reporterId)}" data-imageurl="${esc(row.imageUrl)}"
                            data-status="${esc(st)}" data-timestamp="${esc(row.tsDisplay)}"
                            data-lat="${esc(row.lat)}" data-lng="${esc(row.lng)}"
                            data-approvedbyname="${esc(row.approvedByName || '')}" data-approvedat="${esc(row.approvedAt || '')}"
                            data-declinedbyname="${esc(row.declinedByName || '')}" data-declinedat="${esc(row.declinedAt || '')}"
                            data-respondedbyname="${esc(row.respondedByName || '')}" data-respondedat="${esc(row.respondedAt || '')}"
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
                                    ${actionInfo}
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

            // Initial load: use preloaded data if available (instant render, no AJAX spinner)
            if (window.__preloadedRecentFeed && window.__preloadedRecentFeed.success) {
                // Set background refresh flag so loadRecentPage skips the loading spinner
                window.isBackgroundRefresh = true;
                
                // Call loadRecentPage which will hit the 15s server cache (fast)
                // The spinner won't show because isBackgroundRefresh=true
                loadRecentPage(1, 0, false);
                
                // Clear preloaded data to avoid stale renders
                delete window.__preloadedRecentFeed;
                console.log('[RecentActivity] Server preloaded data available — skipping loading spinner');
            } else {
                loadRecentPage(1);
            }

            // Expose loadRecentPage to global scope
            window.loadRecentPage = loadRecentPage;
            
            // Override global refresh function
            window.refreshRecentActivity = function() {
                window.isBackgroundRefresh = true; // Use background refresh to avoid spinner
                // Check if force refresh was requested (e.g., from head-check signature change)
                const shouldForceRefresh = window.forceRecentFeedRefresh === true;
                window.forceRecentFeedRefresh = false;
                console.log('[RecentActivity] Refreshing feed, forceRefresh:', shouldForceRefresh);
                loadRecentPage(currentPage, 0, shouldForceRefresh);
            };
            
            console.log('[RecentActivity] Inline JS initialized');

            // Check for updates every 2 seconds (Faster polling for realtime feel)
            async function checkForUpdates() {
                // Only poll if tab is visible to save resources
                if (document.hidden) return;
                
                try {
                    const categoryEl = document.getElementById('activityCategory');
                    const statusEl = document.getElementById('activityStatus');
                    
                    const fd = new FormData();
                    fd.append('api_action', 'check_recent_updates');
                    fd.append('category', categoryEl ? categoryEl.value : 'all');
                    fd.append('status', statusEl ? statusEl.value : 'all');
                    
                    const res = await fetch(window.location.href, { method: 'POST', body: fd });
                    const json = await res.json();
                    
                    if (json.success) {
                        // If we have a new signature that is different from our last known one
                        // This ensures we only reload the full list when there is actually new data
                        // We use a simple MD5-like comparison (server sends MD5, we can't easily MD5 on client without lib)
                        // So we rely on the server sending a signature, and we just check if it changed from what we last saw
                        // Wait, we can't compare server MD5 with client string.
                        // Let's just store the server signature!
                        
                        if (json.signature && json.signature !== window.lastServerSignature) {
                            console.log('New activity detected (signature change), refreshing feed...');
                            window.lastServerSignature = json.signature; // Update immediately to prevent double refresh
                            window.isBackgroundRefresh = true;
                            // Force refresh to bypass cache
                            loadRecentPage(currentPage, 0, true);
                        }
                    }
                } catch (e) {
                    // Silent fail for background checks
                    // console.error('Failed to check for updates:', e);
                }
            }

            setInterval(checkForUpdates, 2000);
        })();

        // KPI Helper Functions for Overview Section
        function getKpiAggregatesFromStats(stats) {
            let totalPending = 0, totalApproved = 0, totalDeclined = 0, totalResponding = 0, totalResponded = 0, grandTotal = 0;

            Object.values(stats).forEach(stat => {
                totalPending += parseInt(stat.pending || 0);
                totalApproved += parseInt(stat.approved || 0);
                totalDeclined += parseInt(stat.declined || 0);
                totalResponding += parseInt(stat.responding || 0);
                totalResponded += parseInt(stat.responded || 0);
                grandTotal += parseInt(stat.total || 0);
            });

            return {
                pending: totalPending,
                approved: totalApproved,
                declined: totalDeclined,
                responding: totalResponding,
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
                    { key: 'responding', label: 'Responding', value: aggregates.responding, color: 'purple' },
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
        window.refreshAdminStats = async function(options = {}) {
            const config = (typeof options === 'boolean') ? { forceRefresh: options } : (options || {});
            const forceRefresh = config.forceRefresh === true;
            const showLoading = config.showLoading !== false;
            const container = document.getElementById('adminStatsContainer');
            if (!container) return;
            
            const originalContent = container.innerHTML;
            if (showLoading) {
                container.innerHTML = `
                    <div class="col-span-full text-center py-6 text-slate-500">
                        <div class="inline-flex items-center gap-2">
                            ${svg_icon('spinner', 'w-4 h-4 animate-spin')}
                            Refreshing statistics...
                        </div>
                    </div>
                `;
            }
            
            try {
                const formData = createFormDataWithCsrf();
                formData.append('api_action', 'load_admin_stats');
                if (forceRefresh) {
                    formData.append('force_refresh', 'true');
                }
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    const container = document.getElementById('adminStatsContainer');
                    if (container) {
                        const categories = <?php echo json_encode($categories); ?>;
                        const stats = result.data;
                        
                        // Clear loading state
                        container.innerHTML = '';
                        
                        // Render stats cards
                        Object.entries(categories).forEach(([slug, meta]) => {
                            const stat = stats[slug] || { total: 0, approved: 0, pending: 0, declined: 0, responding: 0, responded: 0 };
                            const total = Math.max(0, parseInt(stat.total) || 0);
                            const approved = Math.max(0, parseInt(stat.approved) || 0);
                            const pending = Math.max(0, parseInt(stat.pending) || 0);
                            const declined = Math.max(0, parseInt(stat.declined) || 0);
                            const responding = Math.max(0, parseInt(stat.responding) || 0);
                            const responded = Math.max(0, parseInt(stat.responded) || 0);

                            const approvedPct = total > 0 ? Math.round((approved / total) * 100) : 0;
                            const pendingPct = total > 0 ? Math.round((pending / total) * 100) : 0;
                            const declinedPct = total > 0 ? Math.round((declined / total) * 100) : 0;
                            const respondingPct = total > 0 ? Math.round((responding / total) * 100) : 0;
                            const respondedPct = total > 0 ? Math.round((responded / total) * 100) : 0;                            const card = document.createElement('div');
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
                                        <span class="progress-seg pending" data-w="${pendingPct}%"></span>
                                        <span class="progress-seg approved" data-w="${approvedPct}%"></span>
                                        <span class="progress-seg responding" style="background-color: #9333ea;" data-w="${respondingPct}%"></span>
                                        <span class="progress-seg responded" style="background-color: #06b6d4;" data-w="${respondedPct}%"></span>
                                        <span class="progress-seg declined" data-w="${declinedPct}%"></span>
                                    </div>
                                    <div class="grid grid-cols-2 gap-1 text-[10px] text-slate-500">
                                        <div class="flex items-center gap-1">
                                            <span class="inline-block w-2 h-2 rounded-full bg-amber-500"></span>
                                            <span>${pendingPct}% Pend</span>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <span class="inline-block w-2 h-2 rounded-full bg-emerald-500"></span>
                                            <span>${approvedPct}% Appr</span>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <span class="inline-block w-2 h-2 rounded-full" style="background-color: #9333ea;"></span>
                                            <span>${respondingPct}% Rdng</span>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <span class="inline-block w-2 h-2 rounded-full bg-cyan-500"></span>
                                            <span>${respondedPct}% Resp</span>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <span class="inline-block w-2 h-2 rounded-full bg-red-500"></span>
                                            <span>${declinedPct}% Decl</span>
                                        </div>
                                    </div>
                                </div>
                            `;
                            
                            container.appendChild(card);
                        });
                        
                        // Update Overview KPIs with aggregated data
                        const aggregates = getKpiAggregatesFromStats(stats);
                        renderTopKpis(aggregates);
                        pushKpiHistory(aggregates);
                        
                        // Trigger countup animations
                        setTimeout(() => {
                            document.querySelectorAll('[data-countup]').forEach(el => {
                                const target = parseInt(el.dataset.countup) || 0;
                                animateCount(el, target);
                            });
                        }, 100);
                        
                        console.log('Admin stats refreshed successfully:', result.executionTime);
                    }
                } else {
                    console.error('Failed to refresh admin stats:', result.message);
                    showToast('Failed to refresh statistics: ' + result.message, 'error');
                    if (showLoading) {
                        container.innerHTML = originalContent;
                    }
                }
            } catch (error) {
                console.error('Error refreshing admin stats:', error);
                showToast('Error refreshing statistics: ' + error.message, 'error');
                if (showLoading) {
                    container.innerHTML = originalContent;
                }
            }
        };

        // Stats panel removed from dashboard home; no auto-init required.

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
                    setTimeout(() => refreshAdminStats({ forceRefresh: true, showLoading: true }), 500);
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
        <?php if ($isAdmin && $view === 'analytics'): ?>
        (async () => {
            try {
                // Show loading states for both sections
                const statsContainer = document.getElementById('adminStatsContainer');
                const recentContainer = document.getElementById('recentActivityList');
                
                if (statsContainer) {
                    statsContainer.innerHTML = `
                        <div class="col-span-full grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 animate-pulse">
                            <div class="h-40 rounded-2xl bg-slate-100"></div>
                            <div class="h-40 rounded-2xl bg-slate-100"></div>
                            <div class="h-40 rounded-2xl bg-slate-100"></div>
                        </div>
                    `;
                }
                
                // Prepare both requests in parallel
                const statsFormData = new FormData();
                statsFormData.append('api_action', 'load_admin_stats');
                
                const recentFormData = new FormData();
                recentFormData.append('api_action', 'recent_feed');
                recentFormData.append('page', '1');
                recentFormData.append('pageSize', '10');
                recentFormData.append('search', '');
                recentFormData.append('category', 'all');
                recentFormData.append('status', 'all');
                
                // Execute both requests simultaneously, but render statistics first
                const statsRequest = fetch(window.location.href, {
                    method: 'POST',
                    body: statsFormData
                });
                const recentRequest = fetch(window.location.href, {
                    method: 'POST',
                    body: recentFormData
                });

                // Process stats response as soon as it arrives
                const statsResponse = await statsRequest;
                const statsResult = await statsResponse.json();
                if (statsResult.success) {
                    const container = document.getElementById('adminStatsContainer');
                    if (container) {
                        const categories = <?php echo json_encode($categories); ?>;
                        const stats = statsResult.data;
                        
                        // Clear loading state
                        container.innerHTML = '';
                        
                        // Render stats cards
                        Object.entries(categories).forEach(([slug, meta]) => {
                            const stat = stats[slug] || { total: 0, approved: 0, pending: 0, declined: 0, responding: 0, responded: 0 };
                            const total = Math.max(0, parseInt(stat.total) || 0);
                            const approved = Math.max(0, parseInt(stat.approved) || 0);
                            const pending = Math.max(0, parseInt(stat.pending) || 0);
                            const declined = Math.max(0, parseInt(stat.declined) || 0);
                            const responding = Math.max(0, parseInt(stat.responding) || 0);
                            const responded = Math.max(0, parseInt(stat.responded) || 0);

                            const approvedPct = total > 0 ? Math.round((approved / total) * 100) : 0;
                            const pendingPct = total > 0 ? Math.round((pending / total) * 100) : 0;
                            const declinedPct = total > 0 ? Math.round((declined / total) * 100) : 0;
                            const respondingPct = total > 0 ? Math.round((responding / total) * 100) : 0;
                            const respondedPct = total > 0 ? Math.round((responded / total) * 100) : 0;
                            
                            const card = document.createElement('div');
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
                                <div class="grid grid-cols-3 gap-3 text-center mb-4">
                                    <div>
                                        <div class="text-4xl font-extrabold text-amber-600 tracking-tighter">
                                            <span data-countup="${pending}" data-status="pending">${pending}</span>
                                        </div>
                                        <div class="text-xs text-slate-500 uppercase tracking-wider font-medium">Pending</div>
                                    </div>
                                    <div>
                                        <div class="text-4xl font-extrabold text-emerald-600 tracking-tighter">
                                            <span data-countup="${approved}" data-status="approved">${approved}</span>
                                        </div>
                                        <div class="text-xs text-slate-500 uppercase tracking-wider font-medium">Approved</div>
                                    </div>
                                    <div>
                                        <div class="text-4xl font-extrabold text-red-600 tracking-tighter">
                                            <span data-countup="${declined}" data-status="declined">${declined}</span>
                                        </div>
                                        <div class="text-xs text-slate-500 uppercase tracking-wider font-medium">Declined</div>
                                    </div>
                                </div>
                                <div class="space-y-2">
                                    <div class="progress-track">
                                        <span class="progress-seg pending" data-w="${pendingPct}%"></span>
                                        <span class="progress-seg approved" data-w="${approvedPct}%"></span>
                                        <span class="progress-seg declined" data-w="${declinedPct}%"></span>
                                    </div>
                                    <div class="flex items-center justify-between text-xs text-slate-500">
                                        <div class="flex items-center gap-2">
                                            <span class="inline-block w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                                            <span>${pendingPct}% Pending</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="inline-block w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                                            <span>${approvedPct}% Approved</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="inline-block w-2.5 h-2.5 rounded-full bg-red-500"></span>
                                            <span>${declinedPct}% Declined</span>
                                        </div>
                                    </div>
                                </div>
                            `;
                            
                            container.appendChild(card);
                        });
                        
                        // Trigger countup animations
                        setTimeout(() => {
                            document.querySelectorAll('[data-countup]').forEach(el => {
                                const target = parseInt(el.dataset.countup) || 0;
                                animateCount(el, target);
                            });
                        }, 100);
                        
                        console.log('Admin stats loaded successfully:', statsResult.executionTime);
                    }
                } else {
                    console.error('Failed to load admin stats:', statsResult.message);
                    showToast('Failed to load statistics: ' + statsResult.message, 'error');
                    
                    // Show error state in container
                    const container = document.getElementById('adminStatsContainer');
                    if (container) {
                        container.innerHTML = `
                            <div class="col-span-full text-center py-8 text-red-500">
                                <div class="inline-flex items-center gap-3">
                                    ${svg_icon('x-mark', 'w-5 h-5')}
                                    <div>
                                        <div class="text-sm font-medium">Failed to load statistics</div>
                                        <div class="text-xs text-red-400">Click to retry</div>
                                    </div>
                                </div>
                            </div>
                        `;
                        container.onclick = () => {
                            container.onclick = null;
                            window.location.reload();
                        };
                        container.style.cursor = 'pointer';
                    }
                }
                
                // Process recent activity after the stats are already visible
                try {
                    const recentResponse = await recentRequest;
                    const recentResult = await recentResponse.json();
                    if (recentResult.success && recentContainer) {
                        if (typeof loadRecentPage === 'function') {
                            displayRecentItems(recentResult.data);
                            console.log('Recent activity loaded successfully:', recentResult.executionTime);
                        }
                    } else if (recentResult && !recentResult.success) {
                        console.error('Failed to load recent activity:', recentResult.message);
                    }
                } catch (recentError) {
                    console.error('Error loading recent activity:', recentError);
                }
                
                // Prefetch data for next load (background refresh)
                setTimeout(() => {
                    if (document.visibilityState === 'visible') {
                        Promise.all([
                            fetch(window.location.href, {
                                method: 'POST',
                                body: (() => {
                                    const fd = new FormData();
                                    fd.append('api_action', 'load_admin_stats');
                                    return fd;
                                })()
                            }),
                            fetch(window.location.href, {
                                method: 'POST',
                                body: (() => {
                                    const fd = new FormData();
                                    fd.append('api_action', 'recent_feed');
                                    fd.append('page', '1');
                                    fd.append('pageSize', '10');
                                    fd.append('search', '');
                                    fd.append('category', 'all');
                                    fd.append('status', 'all');
                                    return fd;
                                })()
                            })
                        ]).then(() => {
                            console.log('Admin dashboard data prefetched for next load');
                        }).catch(() => {}); // Silent fail for prefetch
                    }
                }, 30000); // Prefetch after 30 seconds
                
            } catch (error) {
                console.error('Error loading admin stats:', error);
                let errorMessage = 'Error loading statistics: ' + error.message;
                if (error.name === 'AbortError') {
                    errorMessage = 'Statistics loading timed out. Please try again.';
                }
                showToast(errorMessage, 'error');
                
                // Show error state in container
                const container = document.getElementById('adminStatsContainer');
                if (container) {
                    container.innerHTML = `
                        <div class="col-span-full text-center py-8 text-red-500">
                            <div class="inline-flex items-center gap-3">
                                ${svg_icon('x-mark', 'w-5 h-5')}
                                <div>
                                    <div class="text-sm font-medium">Failed to load statistics</div>
                                    <div class="text-xs text-red-400">Click to retry</div>
                                </div>
                            </div>
                        </div>
                    `;
                    container.onclick = () => {
                        container.onclick = null;
                        window.location.reload();
                    };
                    container.style.cursor = 'pointer';
                }
            }
        })();
        <?php endif; ?>

        window.updateActivityItemStatus = function(id, newStatus) {
            const li = document.querySelector(`#activityList li[data-id="${id}"]`);
            if (!li) return;

            const st = (newStatus || 'Pending').toLowerCase();
            const normalizedStatus = st === 'faile' ? 'failed' : st;
            li.dataset.status = normalizedStatus;

            const badge = li.querySelector('.status-badge');
            if (badge) {
                badge.className = 'mt-2 inline-flex status-badge';
                if (normalizedStatus === 'approved') badge.classList.add('status-badge-success');
                else if (normalizedStatus === 'declined' || normalizedStatus === 'failed') badge.classList.add('status-badge-declined');
                else badge.classList.add('status-badge-pending');
                badge.innerHTML = `<span class="h-2 w-2 rounded-full bg-current mr-2"></span>${normalizedStatus === 'failed' ? 'Failed' : newStatus}`;
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
        (async () => {
            const cardsContainer = document.getElementById('staffReportCards');
            if (!cardsContainer) return;
        
            try {
                // Load staff data
                const formData = new FormData();
                formData.append('api_action', 'load_staff_data');
                formData.append('force_refresh', 'true');
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    window.staffData = result.data;
                    renderStaffReports(result.data.cards, categories);
                } else {
                    console.error('Failed to load staff data:', result.message);
                    showToast('Failed to load reports data: ' + result.message, 'error');
                    cardsContainer.innerHTML = `<div class="text-center py-12 text-red-500">
                        <div class="inline-flex items-center gap-3">
                            ${svg_icon('x-mark', 'w-5 h-5')}
                            <div>
                                <div class="text-lg font-medium">Failed to load reports</div>
                                <div class="text-sm text-red-400">${result.message}</div>
                            </div>
                        </div>
                    </div>`;
                }
            } catch (error) {
                console.error('Error loading staff data:', error);
                showToast('Error loading reports data: ' + error.message, 'error');
                cardsContainer.innerHTML = `<div class="text-center py-12 text-red-500">
                    <div class="inline-flex items-center gap-3">
                        ${svg_icon('x-mark', 'w-5 h-5')}
                        <div>
                            <div class="text-lg font-medium">Error loading reports</div>
                            <div class="text-sm text-red-400">${error.message}</div>
                        </div>
                    </div>
                </div>`;
            }
        })();
        
        // Smart real-time sync function for staff reports
        function startRealTimeSync() {
            let lastCheckTime = new Date().toISOString();
            let isUpdating = false; // Prevent multiple simultaneous updates
            let isStatusUpdateInProgress = false; // Track if status update is happening
            
            // Check for new reports every 2 seconds (more reasonable)
            setInterval(async () => {
                // Skip if already updating or if status update is in progress
                if (isUpdating || isStatusUpdateInProgress) return;
                
                try {
                    isUpdating = true;
                    
                    // Quick check for new reports
                    const formData = new FormData();
                    formData.append('api_action', 'check_new_reports');
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
                    
                    if (result.success && result.hasNew) {
                        console.log('🆕 New reports detected, updating dashboard...');
                        
                        // Update last check time
                        lastCheckTime = result.timestamp || new Date().toISOString();
                        
                        // Reload full staff data
                        const fullDataForm = new FormData();
                        fullDataForm.append('api_action', 'load_staff_data');
                        fullDataForm.append('force_refresh', 'true');
                        
                        const fullResponse = await fetch(window.location.href, {
                            method: 'POST',
                            body: fullDataForm
                        });
                        
                        // Check if full response is valid JSON
                        const fullResponseText = await fullResponse.text();
                        let fullResult;
                        try {
                            fullResult = JSON.parse(fullResponseText);
                        } catch (parseError) {
                            console.error('Invalid JSON in full response:', fullResponseText);
                            return;
                        }
                        
                        if (fullResult.success) {
                            window.staffData = fullResult.data;
                            renderStaffReports(fullResult.data.cards, categories);
                            
                            // Also refresh emergency alerts
                            if (typeof loadEmergencyAlerts === 'function') {
                                loadEmergencyAlerts();
                            }
                            
                            // Update all tab counts after refresh to ensure accuracy
                            if (fullResult.data.cards) {
                                Object.keys(fullResult.data.cards).forEach(slug => {
                                    if (typeof window.manualUpdateTabCounts === 'function') {
                                        window.manualUpdateTabCounts(slug);
                                    }
                                });
                            }
                            
                            // Determine sound type based on new reports
                            let soundType = 'default';
                            if (result.data && Array.isArray(result.data)) {
                                const hasEmergency = result.data.some(item => {
                                    const cat = (item.category || '').toLowerCase();
                                    return cat === 'ambulance' || cat === 'fire';
                                });
                                if (hasEmergency) {
                                    soundType = 'siren';
                                }
                            }

                            // Show notification with sound and visual effects
                            showNotificationWithSound('🆕 New reports received!', 'success', soundType);
                        }
                    }
                } catch (error) {
                    console.error('Error in real-time sync:', error);
                } finally {
                    isUpdating = false;
                }
            }, 2000); // Check every 2 seconds (more reasonable)
            
            // Expose the status update flag for handleStatusUpdate to use
            window.setStatusUpdateInProgress = function(inProgress) {
                isStatusUpdateInProgress = inProgress;
            };
        }
        
        // Start real-time sync
        startRealTimeSync();
        
        // Also start emergency alerts specific sync (less frequent for stability)
        setInterval(async () => {
            try {
                if (typeof loadEmergencyAlerts === 'function') {
                    loadEmergencyAlerts();
                }
            } catch (error) {
                console.error('Error in emergency alerts sync:', error);
            }
        }, 5000); // Check emergency alerts every 5 seconds for stability
        
        // Debug function to test tab count updates
        window.testTabCountUpdate = function() {
            console.log('🧪 Testing tab count update...');
            if (window.staffData && window.staffData.cards && window.staffData.cards.ambulance) {
                const reports = window.staffData.cards.ambulance;
                if (reports.length > 0) {
                    const firstReport = reports[0];
                    console.log('📋 Testing with report:', firstReport);
                    window.forceUpdateTabCounts('ambulance', firstReport.id, 'Approved');
                } else {
                    console.log('❌ No reports available for testing');
                }
            } else {
                console.log('❌ No staff data available for testing');
            }
        };

        // Manual function to update tab counts for any slug
        window.manualUpdateTabCounts = function(slug) {
            console.log('🔧 Manual tab count update for slug:', slug);
            if (window.staffData && window.staffData.cards && window.staffData.cards[slug]) {
                const reports = window.staffData.cards[slug];
                console.log('📊 Current reports:', reports);
                
                // Recalculate counts
                const urgentReports = reports.filter(r => (r.priority || '').toUpperCase() === 'HIGH' && (r.status || 'pending').toLowerCase() === 'pending');
                const pendingItems = reports.filter(r => (r.status || 'pending').toLowerCase() === 'pending' && (r.priority || '').toUpperCase() !== 'HIGH');
                const approvedItems = reports.filter(r => (r.status || 'pending').toLowerCase() === 'approved');
                const declinedItems = reports.filter(r => (r.status || 'pending').toLowerCase() === 'declined');
                
                console.log('📊 Calculated counts:', {
                    pending: pendingItems.length + urgentReports.length,
                    approved: approvedItems.length,
                    declined: declinedItems.length
                });
                
                // Find and update the tab counts
                const segmentedControl = document.querySelector(`.segmented[data-slug="${slug}"]`);
                if (segmentedControl) {
                    const pendingTab = segmentedControl.querySelector('.seg-btn[data-tab="pending"] .tab-count');
                    const approvedTab = segmentedControl.querySelector('.seg-btn[data-tab="approved"] .tab-count');
                    const declinedTab = segmentedControl.querySelector('.seg-btn[data-tab="declined"] .tab-count');
                    
                    if (pendingTab) {
                        pendingTab.textContent = pendingItems.length + urgentReports.length;
                        console.log('✅ Manual updated pending count:', pendingTab.textContent);
                    }
                    if (approvedTab) {
                        approvedTab.textContent = approvedItems.length;
                        console.log('✅ Manual updated approved count:', approvedTab.textContent);
                    }
                    if (declinedTab) {
                        declinedTab.textContent = declinedItems.length;
                        console.log('✅ Manual updated declined count:', declinedTab.textContent);
                    }
                } else {
                    console.log('❌ Could not find segmented control for manual update');
                }
            } else {
                console.log('❌ No staff data available for manual update');
            }
        };

        // Function to refresh all tab counts
        window.refreshAllTabCounts = function() {
            console.log('🔄 Refreshing all tab counts...');
            if (window.staffData && window.staffData.cards) {
                Object.keys(window.staffData.cards).forEach(slug => {
                    if (typeof window.manualUpdateTabCounts === 'function') {
                        window.manualUpdateTabCounts(slug);
                    }
                });
            }
        };

        // Add manual refresh function
        window.refreshStaffReports = async function() {
                try {
                    const formData = createFormDataWithCsrf();
                    formData.append('api_action', 'load_staff_data');
                    formData.append('force_refresh', 'true');
                    const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    window.staffData = result.data;
                    renderStaffReports(result.data.cards, categories);
                    
                    // Also refresh emergency alerts
                    if (typeof loadEmergencyAlerts === 'function') {
                        loadEmergencyAlerts();
                    }
                    
                    showToast('Reports refreshed successfully', 'success');
                } else {
                    showToast('Failed to refresh reports: ' + result.message, 'error');
                }
            } catch (error) {
                console.error('Error refreshing reports:', error);
                showToast('Error refreshing reports: ' + error.message, 'error');
            }
        };
        
        // Add immediate refresh function for instant updates
        window.immediateRefresh = async function() {
            try {
                console.log('🔄 Immediate refresh triggered...');
                
                // Force immediate refresh without checking last update time
                const formData = new FormData();
                formData.append('api_action', 'load_staff_data');
                formData.append('force_refresh', 'true');
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    window.staffData = result.data;
                    renderStaffReports(result.data.cards, categories);
                    
                    // Also refresh emergency alerts immediately
                    if (typeof loadEmergencyAlerts === 'function') {
                        loadEmergencyAlerts();
                    }
                    
                    console.log('✅ Immediate refresh completed');
                } else {
                    console.error('❌ Immediate refresh failed:', result.message);
                }
            } catch (error) {
                console.error('❌ Error in immediate refresh:', error);
            }
        };
        
        // Add ultra-fast refresh function (can be called from report submission)
        window.ultraFastRefresh = async function() {
            try {
                console.log('⚡ Ultra-fast refresh triggered...');
                
                // Immediate refresh with minimal delay
                const formData = new FormData();
                formData.append('api_action', 'load_staff_data');
                formData.append('force_refresh', 'true');
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    window.staffData = result.data;
                    renderStaffReports(result.data.cards, categories);
                    
                    // Also refresh emergency alerts immediately
                    if (typeof loadEmergencyAlerts === 'function') {
                        loadEmergencyAlerts();
                    }
                    
                    console.log('⚡ Ultra-fast refresh completed');
                } else {
                    console.error('❌ Ultra-fast refresh failed:', result.message);
                }
            } catch (error) {
                console.error('❌ Error in ultra-fast refresh:', error);
            }
        };
        
        // New function to render the staff report cards
        function renderStaffReports(cards, categories) {
            const cardsContainer = document.getElementById('staffReportCards');
            if (!cardsContainer) return;
            
            // --- STAFF PAGINATION STATE ---
            if (!window.staffPagination) {
                window.staffPagination = {
                    page: 1,
                    pageSize: 20,
                    slug: null
                };
            }
            let html = '';
            let animDelayCounter = 200;
            for (const [slug, reports] of Object.entries(cards)) {
                const meta = categories[slug];
                if (!meta) continue;
                window.staffPagination.slug = slug;
                // Filter reports by status
                const pendingItems = reports.filter(r => (r.status || 'pending').toLowerCase() === 'pending');
                const approvedItems = reports.filter(r => (r.status || 'pending').toLowerCase() === 'approved');
                const declinedItems = reports.filter(r => (r.status || 'pending').toLowerCase() === 'declined');
                const respondingItems = reports.filter(r => (r.status || 'pending').toLowerCase() === 'responding');
                const respondedItems = reports.filter(r => (r.status || 'pending').toLowerCase() === 'responded');
                // Emergency alerts section removed
                let emergencySection = '';
                // --- PAGINATION LOGIC ---
                const page = window.staffPagination.page;
                const pageSize = window.staffPagination.pageSize;
                const total = pendingItems.length;
                const startIdx = (page - 1) * pageSize;
                const endIdx = Math.min(startIdx + pageSize, total);
                const paginatedPending = pendingItems.slice(startIdx, endIdx);
                html += `
                <div class="report-category-group bg-white rounded-2xl shadow-lg shadow-sky-500/5 border border-slate-200/60 overflow-hidden animate-fade-in-up" style="--anim-delay: ${animDelayCounter}ms;">
                    <div class="p-4 border-b border-slate-200/80 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <div class="w-11 h-11 rounded-lg bg-${meta.color}-100 text-${meta.color}-600 flex items-center justify-center flex-shrink-0">
                                <?php echo svg_icon($meta['icon'], 'w-6 h-6'); ?>
                            </div>
                            <h3 class="text-lg font-bold text-slate-800">${meta.label} Reports</h3>
                        </div>
                        <div class="segmented" data-slug="${slug}">
                            <button type="button" class="seg-btn active" data-tab="pending" onclick="switchTab('${slug}', 'pending')">
                                <span class="seg-label">Pending</span>
                                <span class="tab-count">${pendingItems.length}</span>
                            </button>
                            <button type="button" class="seg-btn" data-tab="approved" onclick="switchTab('${slug}', 'approved')">
                                <span class="seg-label">Approved</span>
                                <span class="tab-count">${approvedItems.length}</span>
                            </button>
                            <button type="button" class="seg-btn" data-tab="declined" onclick="switchTab('${slug}', 'declined')">
                                <span class="seg-label">Declined</span>
                                <span class="tab-count">${declinedItems.length}</span>
                            </button>
                            <button type="button" class="seg-btn" data-tab="responding" onclick="switchTab('${slug}', 'responding')">
                                <span class="seg-label">Responding</span>
                                <span class="tab-count">${respondingItems.length}</span>
                            </button>
                            <button type="button" class="seg-btn" data-tab="responded" onclick="switchTab('${slug}', 'responded')">
                                <span class="seg-label">Responded</span>
                                <span class="tab-count">${respondedItems.length}</span>
                            </button>
                        </div>
                    </div>
                    <div class="panel-content active" data-slug="${slug}" data-tab="pending">
                        ${emergencySection}
                        ${renderReportsTable(paginatedPending, meta.collection, categories)}
                        <div class="flex items-center justify-between mt-4">
                            <div class="text-sm text-slate-600 font-semibold bg-slate-100 px-4 py-2 rounded-lg shadow-sm">
                                <span class="inline-block mr-2 text-blue-600 font-bold">Showing</span>
                                <span class="inline-block">${total === 0 ? 0 : startIdx + 1}-${endIdx}</span>
                                <span class="inline-block mx-2">of</span>
                                <span class="inline-block font-bold">${total}</span>
                            </div>
                            <div class="flex items-center gap-2 bg-slate-50 px-3 py-2 rounded-lg shadow-sm">
                                <label for="staffPageSize" class="mr-2 text-sm font-medium text-slate-700">Rows:</label>
                                <select id="staffPageSize" class="border border-blue-300 rounded-lg px-2 py-1 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all">
                                    <option value="10" ${pageSize === 10 ? 'selected' : ''}>10</option>
                                    <option value="20" ${pageSize === 20 ? 'selected' : ''}>20</option>
                                    <option value="50" ${pageSize === 50 ? 'selected' : ''}>50</option>
                                </select>
                                <button id="staffPrev" class="ml-4 px-3 py-1 border border-blue-300 rounded-lg text-sm font-semibold text-blue-700 bg-white hover:bg-blue-50 transition-all ${page === 1 ? 'opacity-50 cursor-not-allowed' : ''}">Prev</button>
                                <button id="staffNext" class="px-3 py-1 border border-blue-300 rounded-lg text-sm font-semibold text-blue-700 bg-white hover:bg-blue-50 transition-all ${endIdx >= total ? 'opacity-50 cursor-not-allowed' : ''}">Next</button>
                            </div>
                        </div>
                    </div>
                    <div class="panel-content" data-slug="${slug}" data-tab="approved">
                        ${(() => {
                            const page = window.staffPagination.page;
                            const pageSize = window.staffPagination.pageSize;
                            const total = approvedItems.length;
                            const startIdx = (page - 1) * pageSize;
                            const endIdx = Math.min(startIdx + pageSize, total);
                            const paginatedApproved = approvedItems.slice(startIdx, endIdx);
                            return `
                                ${renderReportsTable(paginatedApproved, meta.collection, categories)}
                                <div class='flex items-center justify-between mt-4'>
                                    <div class='text-sm text-slate-600 font-semibold bg-slate-100 px-4 py-2 rounded-lg shadow-sm'>
                                        <span class='inline-block mr-2 text-blue-600 font-bold'>Showing</span>
                                        <span class='inline-block'>${total === 0 ? 0 : startIdx + 1}-${endIdx}</span>
                                        <span class='inline-block mx-2'>of</span>
                                        <span class='inline-block font-bold'>${total}</span>
                                    </div>
                                    <div class='flex items-center gap-2 bg-slate-50 px-3 py-2 rounded-lg shadow-sm'>
                                        <label for='staffPageSize' class='mr-2 text-sm font-medium text-slate-700'>Rows:</label>
                                        <select id='staffPageSize' class='border border-blue-300 rounded-lg px-2 py-1 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all'>
                                            <option value='10' ${pageSize === 10 ? 'selected' : ''}>10</option>
                                            <option value='20' ${pageSize === 20 ? 'selected' : ''}>20</option>
                                            <option value='50' ${pageSize === 50 ? 'selected' : ''}>50</option>
                                        </select>
                                        <button id='staffPrev' class='ml-4 px-3 py-1 border border-blue-300 rounded-lg text-sm font-semibold text-blue-700 bg-white hover:bg-blue-50 transition-all ${page === 1 ? 'opacity-50 cursor-not-allowed' : ''}'>Prev</button>
                                        <button id='staffNext' class='px-3 py-1 border border-blue-300 rounded-lg text-sm font-semibold text-blue-700 bg-white hover:bg-blue-50 transition-all ${endIdx >= total ? 'opacity-50 cursor-not-allowed' : ''}'>Next</button>
                                    </div>
                                </div>
                            `;
                        })()}
                    </div>
                    <div class="panel-content" data-slug="${slug}" data-tab="declined">
                        ${(() => {
                            const page = window.staffPagination.page;
                            const pageSize = window.staffPagination.pageSize;
                            const total = declinedItems.length;
                            const startIdx = (page - 1) * pageSize;
                            const endIdx = Math.min(startIdx + pageSize, total);
                            const paginatedDeclined = declinedItems.slice(startIdx, endIdx);
                            return `
                                ${renderReportsTable(paginatedDeclined, meta.collection, categories)}
                                <div class='flex items-center justify-between mt-4'>
                                    <div class='text-sm text-slate-600 font-semibold bg-slate-100 px-4 py-2 rounded-lg shadow-sm'>
                                        <span class='inline-block mr-2 text-blue-600 font-bold'>Showing</span>
                                        <span class='inline-block'>${total === 0 ? 0 : startIdx + 1}-${endIdx}</span>
                                        <span class='inline-block mx-2'>of</span>
                                        <span class='inline-block font-bold'>${total}</span>
                                    </div>
                                    <div class='flex items-center gap-2 bg-slate-50 px-3 py-2 rounded-lg shadow-sm'>
                                        <label for='staffPageSize' class='mr-2 text-sm font-medium text-slate-700'>Rows:</label>
                                        <select id='staffPageSize' class='border border-blue-300 rounded-lg px-2 py-1 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all'>
                                            <option value='10' ${pageSize === 10 ? 'selected' : ''}>10</option>
                                            <option value='20' ${pageSize === 20 ? 'selected' : ''}>20</option>
                                            <option value='50' ${pageSize === 50 ? 'selected' : ''}>50</option>
                                        </select>
                                        <button id='staffPrev' class='ml-4 px-3 py-1 border border-blue-300 rounded-lg text-sm font-semibold text-blue-700 bg-white hover:bg-blue-50 transition-all ${page === 1 ? 'opacity-50 cursor-not-allowed' : ''}'>Prev</button>
                                        <button id='staffNext' class='px-3 py-1 border border-blue-300 rounded-lg text-sm font-semibold text-blue-700 bg-white hover:bg-blue-50 transition-all ${endIdx >= total ? 'opacity-50 cursor-not-allowed' : ''}'>Next</button>
                                    </div>
                                </div>
                            `;
                        })()}
                    </div>
                    <div class="panel-content" data-slug="${slug}" data-tab="responded">
                        ${(() => {
                            const page = window.staffPagination.page;
                            const pageSize = window.staffPagination.pageSize;
                            const total = respondedItems.length;
                            const startIdx = (page - 1) * pageSize;
                            const endIdx = Math.min(startIdx + pageSize, total);
                            const paginatedResponded = respondedItems.slice(startIdx, endIdx);
                            return `
                                ${renderReportsTable(paginatedResponded, meta.collection, categories)}
                                <div class='flex items-center justify-between mt-4'>
                                    <div class='text-sm text-slate-600 font-semibold bg-slate-100 px-4 py-2 rounded-lg shadow-sm'>
                                        <span class='inline-block mr-2 text-blue-600 font-bold'>Showing</span>
                                        <span class='inline-block'>${total === 0 ? 0 : startIdx + 1}-${endIdx}</span>
                                        <span class='inline-block mx-2'>of</span>
                                        <span class='inline-block font-bold'>${total}</span>
                                    </div>
                                    <div class='flex items-center gap-2 bg-slate-50 px-3 py-2 rounded-lg shadow-sm'>
                                        <label for='staffPageSize' class='mr-2 text-sm font-medium text-slate-700'>Rows:</label>
                                        <select id='staffPageSize' class='border border-blue-300 rounded-lg px-2 py-1 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all'>
                                            <option value='10' ${pageSize === 10 ? 'selected' : ''}>10</option>
                                            <option value='20' ${pageSize === 20 ? 'selected' : ''}>20</option>
                                            <option value='50' ${pageSize === 50 ? 'selected' : ''}>50</option>
                                        </select>
                                        <button id='staffPrev' class='ml-4 px-3 py-1 border border-blue-300 rounded-lg text-sm font-semibold text-blue-700 bg-white hover:bg-blue-50 transition-all ${page === 1 ? 'opacity-50 cursor-not-allowed' : ''}'>Prev</button>
                                        <button id='staffNext' class='px-3 py-1 border border-blue-300 rounded-lg text-sm font-semibold text-blue-700 bg-white hover:bg-blue-50 transition-all ${endIdx >= total ? 'opacity-50 cursor-not-allowed' : ''}'>Next</button>
                                    </div>
                                </div>
                            `;
                        })()}
                    </div>
                </div>`;
                animDelayCounter += 100;
            }
            cardsContainer.innerHTML = html;
            // --- PAGINATION EVENTS ---
            setTimeout(() => {
                const pageSizeEl = document.getElementById('staffPageSize');
                const prevBtn = document.getElementById('staffPrev');
                const nextBtn = document.getElementById('staffNext');
                if (pageSizeEl) {
                    pageSizeEl.addEventListener('change', function() {
                        window.staffPagination.pageSize = parseInt(this.value, 10);
                        window.staffPagination.page = 1;
                        renderStaffReports(cards, categories);
                    });
                }
                if (prevBtn) {
                    prevBtn.addEventListener('click', function() {
                        if (window.staffPagination.page > 1) {
                            window.staffPagination.page--;
                            renderStaffReports(cards, categories);
                        }
                    });
                }
                if (nextBtn) {
                    nextBtn.addEventListener('click', function() {
                        const total = cards[window.staffPagination.slug].filter(r => (r.status || 'pending').toLowerCase() === 'pending').length;
                        const endIdx = window.staffPagination.page * window.staffPagination.pageSize;
                        if (endIdx < total) {
                            window.staffPagination.page++;
                            renderStaffReports(cards, categories);
                        }
                    });
                }
            }, 10);
            // Update all tab counts after rendering to ensure they're accurate
            Object.keys(cards).forEach(slug => {
                if (typeof window.manualUpdateTabCounts === 'function') {
                    setTimeout(() => {
                        window.manualUpdateTabCounts(slug);
                    }, 50);
                }
            });
        }

        // Function to update tab counts in real-time
        window.updateTabCounts = function(slug, docId, newStatus) {
            console.log('🔄 Updating tab counts for:', { slug, docId, newStatus });
            
            if (!window.staffData || !window.staffData.cards || !window.staffData.cards[slug]) {
                console.log('❌ No staff data available');
                return;
            }
            
            const reports = window.staffData.cards[slug];
            
            // Find the report and update its status
            const reportIndex = reports.findIndex(r => r.id === docId);
            if (reportIndex !== -1) {
                reports[reportIndex].status = newStatus;
                console.log('✅ Updated report status:', reports[reportIndex]);
                
                // Recalculate counts - no more urgent separation
                const pendingItems = reports.filter(r => (r.status || 'pending').toLowerCase() === 'pending');
                const approvedItems = reports.filter(r => (r.status || 'pending').toLowerCase() === 'approved');
                const declinedItems = reports.filter(r => (r.status || 'pending').toLowerCase() === 'declined');
                
                console.log('📊 New counts:', {
                    pending: pendingItems.length,
                    approved: approvedItems.length,
                    declined: declinedItems.length
                });
                
                // Update tab counts - try multiple selectors to find the elements
                let segmentedControl = document.querySelector(`[data-slug="${slug}"]`);
                if (!segmentedControl) {
                    // Try alternative selector
                    segmentedControl = document.querySelector(`.segmented[data-slug="${slug}"]`);
                }
                if (!segmentedControl) {
                    // Try finding by class and data attribute
                    segmentedControl = document.querySelector(`.segmented-control[data-slug="${slug}"]`);
                }
                
                if (segmentedControl) {
                    console.log('✅ Found segmented control:', segmentedControl);
                    
                    // Try multiple selectors for each tab
                    let pendingTab = segmentedControl.querySelector('[data-tab="pending"] .tab-count');
                    if (!pendingTab) pendingTab = segmentedControl.querySelector('.seg-btn[data-tab="pending"] .tab-count');
                    
                    let approvedTab = segmentedControl.querySelector('[data-tab="approved"] .tab-count');
                    if (!approvedTab) approvedTab = segmentedControl.querySelector('.seg-btn[data-tab="approved"] .tab-count');
                    
                    let declinedTab = segmentedControl.querySelector('[data-tab="declined"] .tab-count');
                    if (!declinedTab) declinedTab = segmentedControl.querySelector('.seg-btn[data-tab="declined"] .tab-count');
                    
                    console.log('🔍 Found tab elements:', { pendingTab, approvedTab, declinedTab });
                    
                    if (pendingTab) {
                        pendingTab.textContent = pendingItems.length;
                        console.log('✅ Updated pending count:', pendingTab.textContent);
                    } else {
                        console.log('❌ Could not find pending tab');
                    }
                    if (approvedTab) {
                        approvedTab.textContent = approvedItems.length;
                        console.log('✅ Updated approved count:', approvedTab.textContent);
                    } else {
                        console.log('❌ Could not find approved tab');
                    }
                    if (declinedTab) {
                        declinedTab.textContent = declinedItems.length;
                        console.log('✅ Updated declined count:', declinedTab.textContent);
                    } else {
                        console.log('❌ Could not find declined tab');
                    }
                } else {
                    console.log('❌ Could not find segmented control for slug:', slug);
                    console.log('🔍 Available segmented controls:', document.querySelectorAll('[data-slug]'));
                }
            } else {
                console.log('❌ Could not find report with ID:', docId);
            }
        };

        // Function to force update tab counts immediately (for immediate visual feedback)
        window.forceUpdateTabCounts = function(slug, docId, newStatus) {
            console.log('⚡ Force updating tab counts for:', { slug, docId, newStatus });
            
            // Update the report status in memory first
            if (window.staffData && window.staffData.cards && window.staffData.cards[slug]) {
                const reports = window.staffData.cards[slug];
                const reportIndex = reports.findIndex(r => r.id === docId);
                if (reportIndex !== -1) {
                    reports[reportIndex].status = newStatus;
                    console.log('✅ Updated report in memory:', reports[reportIndex]);
                } else {
                    console.log('❌ Report not found in memory:', docId);
                }
            } else {
                console.log('❌ No staff data available for slug:', slug);
            }
            
            // Then update the tab counts
            window.updateTabCounts(slug, docId, newStatus);
        };

        // Function to switch tabs
        window.switchTab = function(slug, tabName) {
            // Remove active class from all buttons in this segmented control
            const segmentedControl = document.querySelector(`[data-slug="${slug}"]`);
            if (segmentedControl) {
                segmentedControl.querySelectorAll('.seg-btn').forEach(btn => {
                    btn.classList.remove('active');
                });
                
                // Add active class to clicked button
                const activeButton = segmentedControl.querySelector(`[data-tab="${tabName}"]`);
                if (activeButton) {
                    activeButton.classList.add('active');
                }
            }
            
            // Hide all panel contents for this slug
            document.querySelectorAll(`[data-slug="${slug}"].panel-content`).forEach(panel => {
                panel.classList.remove('active');
            });
            
            // Show the selected panel
            const selectedPanel = document.querySelector(`[data-slug="${slug}"][data-tab="${tabName}"]`);
            if (selectedPanel) {
                selectedPanel.classList.add('active');
            }
        }

        // Function to load emergency alerts - ULTRA FAST VERSION
        window.loadEmergencyAlerts = async function() {
            const emergencyContainer = document.getElementById('emergencyAlertsContainer');
            if (!emergencyContainer) return;

            try {
                const emergencyFormData = new FormData();
                emergencyFormData.append('api_action', 'check_urgent');
                
                const emergencyResponse = await fetch(window.location.href, {
                    method: 'POST',
                    body: emergencyFormData,
                    signal: AbortSignal.timeout(5000) // Increased to 5-second timeout
                });
                
                const emergencyResult = await emergencyResponse.json();
                
                if (emergencyResult.success) {
                    renderEmergencyAlerts(emergencyResult.data);
                    console.log('⚡ Emergency alerts loaded:', emergencyResult.executionTime);
                } else {
                    console.error('Failed to load emergency alerts:', emergencyResult.message);
                    // Show error state with retry option
                    emergencyContainer.innerHTML = `
                        <div class="text-center py-3 text-red-400">
                            <div class="inline-flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                </svg>
                                <span class="text-xs">Unable to load alerts</span>
                            </div>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Error loading emergency alerts:', error);
                // Show timeout or network error
                emergencyContainer.innerHTML = `
                    <div class="text-center py-3 text-amber-500">
                        <span class="text-xs">Loading alerts...</span>
                    </div>
                `;
            }
        }
        
        // Cache emergency alerts for instant subsequent loads
        let emergencyAlertsCache = null;
        let emergencyAlertsCacheTime = 0;
        
        window.loadEmergencyAlertsCached = async function() {
            const emergencyContainer = document.getElementById('emergencyAlertsContainer');
            if (!emergencyContainer) return;
            
            const now = Date.now();
            const CACHE_DURATION = 30000; // 30 seconds
            
            // Use cache if available and fresh
            if (emergencyAlertsCache && (now - emergencyAlertsCacheTime) < CACHE_DURATION) {
                renderEmergencyAlerts(emergencyAlertsCache);
                console.log('⚡ Emergency alerts loaded from cache');
                return;
            }
            
            // Load fresh data
            try {
                const emergencyFormData = new FormData();
                emergencyFormData.append('api_action', 'check_urgent');
                
                const emergencyResponse = await fetch(window.location.href, {
                    method: 'POST',
                    body: emergencyFormData,
                    signal: AbortSignal.timeout(5000) // Increased to 5-second timeout
                });
                
                const emergencyResult = await emergencyResponse.json();
                
                if (emergencyResult.success) {
                    // Update cache
                    emergencyAlertsCache = emergencyResult.data;
                    emergencyAlertsCacheTime = now;
                    
                    // Debug logging to verify filtering
                    if (emergencyResult.filteredFor) {
                        console.log('🔍 Emergency alerts filtered for categories:', emergencyResult.filteredFor);
                    }
                    if (emergencyResult.data && emergencyResult.data.length > 0) {
                        const collections = [...new Set(emergencyResult.data.map(r => r.collection))];
                        console.log('📋 Emergency alerts from collections:', collections);
                    }
                    
                    renderEmergencyAlerts(emergencyResult.data);
                    console.log('⚡ Emergency alerts loaded and cached:', emergencyResult.executionTime);
                } else {
                    console.error('Failed to load emergency alerts:', emergencyResult.message);
                    // Show error state
                    emergencyContainer.innerHTML = `
                        <div class="text-center py-3 text-red-400">
                            <div class="inline-flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                </svg>
                                <span class="text-xs">Unable to load alerts</span>
                            </div>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Error loading emergency alerts:', error);
                // Use stale cache if available
                if (emergencyAlertsCache) {
                    renderEmergencyAlerts(emergencyAlertsCache);
                    console.log('⚡ Using stale emergency alerts cache');
                } else {
                    // Show error state if no cache available
                    emergencyContainer.innerHTML = `
                        <div class="text-center py-3 text-red-400">
                            <div class="inline-flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                </svg>
                                <span class="text-xs">Unable to load alerts</span>
                            </div>
                        </div>
                    `;
                }
            }
        }
        
        // Replace the original function with the cached version
        window.loadEmergencyAlerts = window.loadEmergencyAlertsCached;

        // Function to render emergency alerts
        function renderEmergencyAlerts(urgentReports) {
            const emergencyContainer = document.getElementById('emergencyAlertsContainer');
            if (!emergencyContainer) return;

            if (!urgentReports || urgentReports.length === 0) {
                emergencyContainer.innerHTML = `
                    <div class="text-center py-4 text-green-600">
                        <div class="inline-flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <div>
                                <div class="text-sm font-medium">All Clear!</div>
                                <div class="text-xs text-green-500">No high priority emergencies.</div>
                            </div>
                        </div>
                    </div>
                `;
                return;
            }

            // Deduplicate reports by ID to prevent duplicates
            const uniqueReports = [];
            const seenIds = new Set();
            
            urgentReports.forEach(report => {
                const reportId = report.id || report._id;
                if (reportId && !seenIds.has(reportId)) {
                    seenIds.add(reportId);
                    uniqueReports.push(report);
                }
            });

            let alertsHtml = '';
            uniqueReports.forEach((report, index) => {
                const animDelay = `style="--anim-delay: ${index * 100}ms"`;
                
                // Debug: Log report data to see what we're working with
                console.log('🚨 Emergency Alert Report:', {
                    id: report.id,
                    _id: report._id,
                    collection: report.collection,
                    fullName: report.fullName,
                    reporterName: report.reporterName,
                    status: report.status,
                    priority: report.priority,
                    timestamp: report.timestamp
                });
                
                // Fix timestamp parsing using Philippines timezone
                let timestamp = 'Unknown time';
                if (report.timestamp) {
                    timestamp = formatFirebaseTimestamp(report.timestamp);
                }
                
                alertsHtml += `
                    <div class="bg-white/80 backdrop-blur-sm rounded-lg border-2 border-red-200 p-3 mb-3 animate-fade-in-up" ${animDelay}>
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-lg">🚨</span>
                                    <h5 class="text-sm font-bold text-red-800">${report.fullName || report.reporterName || 'Unknown Reporter'}</h5>
                                    <span class="px-2 py-0.5 bg-red-100 text-red-800 text-xs font-bold rounded-full">HIGH PRIORITY</span>
                                </div>
                                <p class="text-xs text-red-700 mb-1"><strong>Location:</strong> ${report.location || 'No location specified'}</p>
                                <p class="text-xs text-red-700 mb-1"><strong>Contact:</strong> ${report.contact || report.reporterContact || 'No contact'}</p>
                                <p class="text-xs text-red-600"><strong>Reported:</strong> ${timestamp}</p>
                            </div>
                            <div class="flex flex-col gap-1">
                                <button type="button" class="btn btn-view text-xs px-2 py-1" title="View Details"
                                    onclick="showReportModal(this)"
                                    data-slug="ambulance" data-id="${report.id || report._id || ''}" data-collection="ambulance_reports"
                                    data-fullname="${report.fullName || report.reporterName || ''}" 
                                    data-contact="${report.mobileNumber || report.contact || report.reporterContact || ''}"
                                    data-location="${report.location || ''}" 
                                    data-purpose=""
                                    data-status="${report.status || 'Pending'}" 
                                    data-timestamp="${formatFirebaseTimestamp(report.timestamp)}"
                                    data-rawtimestamp="${report.timestamp}"
                                    data-reporterid="${report.reporterId || ''}" 
                                    data-imageurl="${report.imageUrl || ''}">
                                    <?php echo svg_icon('eye', 'w-3 h-3'); ?><span>View</span>
                                </button>
                                <button type="button" class="btn btn-approve text-xs px-2 py-1" title="Approve Report" onclick="showApproveConfirmation('ambulance_reports', '${report.id || report._id || ''}', '${report.fullName || report.reporterName || ''}', 'ambulance')">
                                    <?php echo svg_icon('check-circle', 'w-3 h-3'); ?><span>Approve</span>
                                </button>
                                <button type="button" class="btn btn-decline text-xs px-2 py-1" title="Decline Report" onclick="showDeclineConfirmation('ambulance_reports', '${report.id || report._id || ''}', '${report.fullName || report.reporterName || ''}', 'ambulance')">
                                    <?php echo svg_icon('x-circle', 'w-3 h-3'); ?><span>Decline</span>
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            });

            emergencyContainer.innerHTML = alertsHtml;
        }

        // New function to render the report table HTML
        function renderReportsTable(reports, collection, categories) {
            if (reports.length === 0) {
                return ``;
            }

            const slug = Object.keys(categories).find(key => categories[key].collection === collection);

            let tableRows = '';
            reports.forEach((it, i) => {
                const stRaw = String(it.status || 'Pending').trim().toLowerCase();
                const st = stRaw === 'faile' ? 'failed' : stRaw;
                const displayStatus = st === 'failed' ? 'Failed' : (it.status || 'Pending');
                const isApproved = (st === 'approved');
                const isDeclined = (st === 'declined' || st === 'failed');
                const isResponded = (st === 'responded');
                const isFinal = isApproved || isDeclined || isResponded;
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
