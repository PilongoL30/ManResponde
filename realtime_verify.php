<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$userName = $_SESSION['fullName'] ?? 'Admin';
$userRole = $_SESSION['role'] ?? 'admin';
$view = 'realtime-verify';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Realtime User Verification - iBantay</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-in-out',
                        'fade-in-up': 'fadeInUp 0.5s ease-out',
                        'fade-out-down': 'fadeOutDown 0.3s ease-in',
                        'pulse': 'pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' },
                        },
                        fadeInUp: {
                            '0%': { opacity: '0', transform: 'translateY(20px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' },
                        },
                        fadeOutDown: {
                            '0%': { opacity: '1', transform: 'translateY(0)' },
                            '100%': { opacity: '0', transform: 'translateY(20px)' },
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .aurora-background {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .animate-fade-in { animation: fadeIn 0.5s ease-in-out; }
        .animate-fade-in-up { animation: fadeInUp 0.5s ease-out; }
        .animate-fade-out-down { animation: fadeOutDown 0.3s ease-in; }
        .animate-pulse { animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite; }
        
        /* Custom button styles */
        .btn {
            @apply inline-flex items-center gap-2 px-4 py-2 rounded-lg font-medium transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2;
        }
        .btn-primary {
            @apply bg-sky-600 text-white hover:bg-sky-700 focus:ring-sky-500;
        }
        .btn-view {
            @apply bg-slate-100 text-slate-700 hover:bg-slate-200 focus:ring-slate-500;
        }
        .btn-approve {
            @apply bg-emerald-600 text-white hover:bg-emerald-700 focus:ring-emerald-500;
        }
        .btn-decline {
            @apply bg-red-600 text-white hover:bg-red-700 focus:ring-red-500;
        }
        
        /* Custom checkbox styles */
        .custom-checkbox {
            @apply flex items-center gap-2 cursor-pointer;
        }
        .custom-checkbox input[type="checkbox"] {
            @apply hidden;
        }
        .custom-checkbox .box {
            @apply w-4 h-4 border-2 border-slate-300 rounded flex items-center justify-center transition-colors;
        }
        .custom-checkbox input[type="checkbox"]:checked + .box {
            @apply bg-sky-600 border-sky-600;
        }
        .custom-checkbox .text {
            @apply text-sm text-slate-700;
        }
    </style>
</head>
<body class="bg-slate-50">
    <!-- Header -->
    <header class="bg-white shadow-sm border-b border-slate-200 fixed top-0 left-0 right-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center">
                    <img src="responde.png" alt="ManResponde Logo" class="h-8 w-auto object-contain" onerror="this.style.display='none'">
                    <h1 class="ml-3 text-xl font-semibold text-slate-900">ManResponde</h1>
                </div>
                <div class="flex items-center gap-4">
                    <span class="text-sm text-slate-600">Welcome, <?php echo htmlspecialchars($userName); ?></span>
                    <a href="dashboard.php" class="btn btn-view text-sm">Back to Dashboard</a>
                    <a href="logout.php" class="btn btn-view text-sm">Logout</a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="pt-20 pb-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Page Header -->
            <div class="mb-8">
                <h1 class="text-3xl md:text-4xl font-extrabold text-slate-900 tracking-tighter">
                    Realtime User Verification
                </h1>
                <p class="text-slate-500 mt-2 text-lg">
                    Live monitoring and approval of new user registrations with real-time updates.
                </p>
            </div>

            <!-- Realtime Interface -->
            <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-lg shadow-sky-500/5 border border-slate-200/80 p-6 md:p-8">
                <div class="flex items-center gap-3 mb-6">
                    <span class="flex items-center justify-center w-12 h-12 bg-green-100 rounded-xl">
                        <svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </span>
                    <div>
                        <h2 class="text-2xl font-bold text-slate-800">Live User Monitoring</h2>
                        <p class="text-slate-600">Real-time updates without page refresh</p>
                    </div>
                </div>

                <!-- Realtime Controls -->
                <div class="grid gap-4 md:grid-cols-3 mb-6">
                    <div class="flex items-center gap-2">
                        <div class="w-3 h-3 bg-green-500 rounded-full animate-pulse"></div>
                        <span class="text-sm font-medium text-green-700">Live Connection Active</span>
                    </div>
                    <div class="text-sm text-slate-600">
                        Listening for: <code class="bg-slate-100 px-2 py-1 rounded">accountStatus == "pending"</code>
                    </div>
                    <div class="flex items-center gap-2">
                        <input id="autoscrollToggle" type="checkbox" checked class="rounded border-slate-300">
                        <label for="autoscrollToggle" class="text-sm text-slate-600">Auto-scroll to newest</label>
                    </div>
                </div>

                <!-- User List Container -->
                <div id="realtime-user-list" class="space-y-4">
                    <!-- Users will be populated here by JavaScript -->
                </div>

                <!-- Empty State -->
                <div id="empty-state" class="text-center py-12 text-slate-500">
                    <svg class="w-16 h-16 mx-auto mb-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                    </svg>
                    <h3 class="text-lg font-medium text-slate-600 mb-2">No Pending Users</h3>
                    <p class="text-slate-500">New registrations will appear here automatically</p>
                </div>
            </div>
        </div>
    </main>

    <!-- Toast Notifications -->
    <div id="toast" class="fixed left-50% transform -translate-x-1/2 bottom-6 bg-slate-900 text-white px-4 py-3 rounded-lg shadow-lg z-50 hidden">
        <div class="flex items-center gap-3">
            <div id="toast-icon" class="w-5 h-5"></div>
            <span id="toast-message"></span>
        </div>
    </div>

    <!-- Realtime Firebase Implementation -->
    <script type="module">
        // Firebase SDKs
        import { initializeApp } from "https://www.gstatic.com/firebasejs/10.12.5/firebase-app.js";
        import {
            getFirestore, collection, doc, getDocs, query, where, orderBy, limit, onSnapshot,
            updateDoc, serverTimestamp
        } from "https://www.gstatic.com/firebasejs/10.12.5/firebase-firestore.js";

        // Firebase config
        const CONFIG = {
            apiKey: "AIzaSyDiNgvmttAwhAjPthjJtcZ1Hr9PLWnhErQ",
            authDomain: "ibantayv2.firebaseapp.com",
            projectId: "ibantayv2",
            storageBucket: "ibantayv2.firebasestorage.app",
            messagingSenderId: "978957037468",
            appId: "1:978957037468:web:ab01a25d49e09244716259"
        };

        // Initialize Firebase
        const app = initializeApp(CONFIG);
        const db = getFirestore(app);

        // DOM elements
        const userList = document.getElementById('realtime-user-list');
        const emptyState = document.getElementById('empty-state');
        const toast = document.getElementById('toast');
        const toastMessage = document.getElementById('toast-message');
        const toastIcon = document.getElementById('toast-icon');
        const autoscrollToggle = document.getElementById('autoscrollToggle');

        // Toast function
        function showToast(message, type = 'info') {
            toastMessage.textContent = message;
            
            // Set icon based on type
            if (type === 'success') {
                toastIcon.innerHTML = '<svg fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>';
                toast.className = 'fixed left-50% transform -translate-x-1/2 bottom-6 bg-green-600 text-white px-4 py-3 rounded-lg shadow-lg z-50 flex items-center gap-3';
            } else if (type === 'error') {
                toastIcon.innerHTML = '<svg fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>';
                toast.className = 'fixed left-50% transform -translate-x-1/2 bottom-6 bg-red-600 text-white px-4 py-3 rounded-lg shadow-lg z-50 flex items-center gap-3';
            } else {
                toastIcon.innerHTML = '<svg fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path></svg>';
                toast.className = 'fixed left-50% transform -translate-x-1/2 bottom-6 bg-slate-900 text-white px-4 py-3 rounded-lg shadow-lg z-50 flex items-center gap-3';
            }

            toast.classList.remove('hidden');
            setTimeout(() => {
                toast.classList.add('hidden');
            }, 3000);
        }

        // Normalize user data
        function normalizeUser(id, data) {
            return {
                id: id,
                fullName: data.fullName || data.name || data.displayName || 'New User',
                email: data.email || data.emailAddress || 'admin@gmail.com',
                mobileNumber: data.mobileNumber || data.contact || '09646466464',
                currentAddress: data.currentAddress || data.address || 'admin',
                permanentAddress: data.permanentAddress || '',
                birthdate: data.birthdate || '08/25/2025',
                gender: data.gender || '',
                accountStatus: data.accountStatus ?? data.status ?? data.Status ?? data.AccountStatus ?? 'pending',
                frontIdImageUrl: data.frontIdImageUrl || data.photoURL || data.photoUrl || data.avatar || '',
                backIdImageUrl: data.backIdImageUrl || '',
                selfieImageUrl: data.selfieImageUrl || '',
                proofOfResidencyPath: data.proofOfResidencyPath || '',
                createdAt: data.createdAt || data._created || data.timestamp || null
            };
        }

        // Create user card HTML
        function createUserCard(user) {
            const createdAt = user.createdAt ? 
                new Date(user.createdAt.seconds ? user.createdAt.seconds * 1000 : user.createdAt).toLocaleString() : 
                'Just now';

            return `
                <div class="user-card bg-white rounded-xl border border-slate-200 p-6 animate-fade-in-up shadow-sm hover:shadow-md transition-shadow" data-user-id="${user.id}">
                    <div class="flex flex-col gap-4">
                        <!-- User Info Header -->
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                            <div class="flex-1">
                                <div class="flex items-center gap-3 mb-3">
                                    <h4 class="font-semibold text-slate-800 text-xl">${user.fullName}</h4>
                                    <span class="px-3 py-1 text-xs font-bold rounded-full bg-amber-100 text-amber-800 animate-pulse">NEW REGISTRATION</span>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                                    <div>
                                        <span class="text-slate-500">Email:</span> 
                                        <span class="text-slate-800 font-medium">${user.email}</span>
                                    </div>
                                    <div>
                                        <span class="text-slate-500">Mobile:</span> 
                                        <span class="text-slate-800 font-medium">${user.mobileNumber}</span>
                                    </div>
                                    <div>
                                        <span class="text-slate-500">Birthdate:</span> 
                                        <span class="text-slate-800 font-medium">${user.birthdate}</span>
                                    </div>
                                    <div>
                                        <span class="text-slate-500">Address:</span> 
                                        <span class="text-slate-800 font-medium">${user.currentAddress}</span>
                                    </div>
                                </div>
                                <div class="mt-3 text-xs text-slate-500">
                                    Registered: ${createdAt}
                                </div>
                            </div>
                        </div>
                        
                        <!-- Verification Documents Section -->
                        ${(user.frontIdImageUrl || user.backIdImageUrl || user.selfieImageUrl || user.proofOfResidencyPath) ? `
                            <div class="border-t border-slate-200 pt-4">
                                <h5 class="text-sm font-medium text-slate-700 mb-3">Verification Documents</h5>
                                <div class="flex flex-wrap gap-2">
                                    ${user.frontIdImageUrl ? `
                                        <button type="button" class="btn btn-view text-xs" title="View Front ID"
                                                onclick="showDocumentModal('Front ID', '${user.frontIdImageUrl}', '${user.fullName}')">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V4a2 2 0 114 0v2m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"></path>
                                            </svg>
                                            <span>Front ID</span>
                                        </button>
                                    ` : ''}
                                    ${user.backIdImageUrl ? `
                                        <button type="button" class="btn btn-view text-xs" title="View Back ID"
                                                onclick="showDocumentModal('Back ID', '${user.backIdImageUrl}', '${user.fullName}')">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V4a2 2 0 114 0v2m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"></path>
                                            </svg>
                                            <span>Back ID</span>
                                        </button>
                                    ` : ''}
                                    ${user.selfieImageUrl ? `
                                        <button type="button" class="btn btn-view text-xs" title="View Selfie"
                                                onclick="showDocumentModal('Selfie', '${user.selfieImageUrl}', '${user.fullName}')">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                            </svg>
                                            <span>Selfie</span>
                                        </button>
                                    ` : ''}
                                    ${user.proofOfResidencyPath ? `
                                        <button type="button" class="btn btn-view text-xs" title="View Proof of Residency"
                                                onclick="showDocumentModal('Proof of Residency', '${user.proofOfResidencyPath}', '${user.fullName}')">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                                            </svg>
                                            <span>Proof</span>
                                        </button>
                                    ` : ''}
                                </div>
                            </div>
                        ` : ''}
                        
                        <!-- Action Buttons -->
                        <div class="border-t border-slate-200 pt-4">
                            <div class="flex justify-end gap-3">
                                <button onclick="approveUser('${user.id}')" class="btn btn-approve">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    <span>Approve</span>
                                </button>
                                <button onclick="rejectUser('${user.id}')" class="btn btn-decline">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                    <span>Reject</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

        // Add user to list
        function addUser(user) {
            const userCard = createUserCard(user);
            userList.insertAdjacentHTML('afterbegin', userCard);
            
            // Hide empty state
            emptyState.style.display = 'none';
            
            // Auto-scroll if enabled
            if (autoscrollToggle.checked) {
                userList.firstElementChild?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            
            // Show success toast
            showToast(`🆕 New registration: ${user.fullName}`, 'success');
        }

        // Remove user from list
        function removeUser(userId) {
            const userCard = userList.querySelector(`[data-user-id="${userId}"]`);
            if (userCard) {
                userCard.classList.add('animate-fade-out-down');
                userCard.addEventListener('animationend', () => {
                    userCard.remove();
                    
                    // Show empty state if no users left
                    if (userList.children.length === 0) {
                        emptyState.style.display = 'block';
                    }
                });
            }
        }

        // Approve user
        async function approveUser(userId) {
            try {
                await updateDoc(doc(db, 'users', userId), {
                    accountStatus: 'approved',
                    status: 'approved',
                    Status: 'approved',
                    AccountStatus: 'approved',
                    reviewedAt: serverTimestamp()
                });
                
                removeUser(userId);
                showToast('User approved successfully!', 'success');
            } catch (error) {
                console.error('Error approving user:', error);
                showToast('Failed to approve user', 'error');
            }
        }

        // Reject user
        async function rejectUser(userId) {
            try {
                await updateDoc(doc(db, 'users', userId), {
                    accountStatus: 'rejected',
                    status: 'rejected',
                    Status: 'rejected',
                    AccountStatus: 'rejected',
                    reviewedAt: serverTimestamp()
                });
                
                removeUser(userId);
                showToast('User rejected successfully!', 'success');
            } catch (error) {
                console.error('Error rejecting user:', error);
                showToast('Failed to reject user', 'error');
            }
        }

        // Show document modal
        window.showDocumentModal = function(type, url, userName) {
            // Create a simple modal for viewing documents
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
            modal.innerHTML = `
                <div class="bg-white rounded-lg p-6 max-w-2xl w-full mx-4">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold">${type} - ${userName}</h3>
                        <button onclick="this.closest('.fixed').remove()" class="text-slate-400 hover:text-slate-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                    <div class="text-center">
                        <img src="${url}" alt="${type}" class="max-w-full h-auto rounded-lg border border-slate-200">
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            
            // Close modal on background click
            modal.addEventListener('click', (e) => {
                if (e.target === modal) modal.remove();
            });
        };

        // Initial load of pending users
        async function loadPendingUsers() {
            try {
                const base = collection(db, 'users');
                const variants = [
                    query(base, where('accountStatus', '==', 'pending'), limit(200)),
                    query(base, where('status', '==', 'pending'), limit(200)),
                    query(base, where('Status', '==', 'pending'), limit(200)),
                    query(base, where('AccountStatus', '==', 'pending'), limit(200)),
                ];
                
                const seen = new Set();
                for (const qy of variants) {
                    try {
                        const snap = await getDocs(qy);
                        snap.forEach(d => {
                            if (seen.has(d.id)) return;
                            seen.add(d.id);
                            addUser(normalizeUser(d.id, d.data()));
                        });
                    } catch (e) {
                        console.warn('Initial fetch warning:', e);
                    }
                }
            } catch (error) {
                console.error('Error loading pending users:', error);
                showToast('Failed to load pending users', 'error');
            }
        }

        // Real-time listener
        const qAll = query(collection(db, 'users'), orderBy('createdAt', 'desc'), limit(500));
        const unsubscribe = onSnapshot(qAll, (snap) => {
            snap.docChanges().forEach(chg => {
                const user = normalizeUser(chg.doc.id, chg.doc.data());
                const isPending = String(user.accountStatus).toLowerCase() === 'pending';
                
                if (chg.type === 'added' && isPending) {
                    // New pending user
                    addUser(user);
                } else if (chg.type === 'modified') {
                    if (isPending) {
                        // User is still pending - update if needed
                        const existingCard = userList.querySelector(`[data-user-id="${user.id}"]`);
                        if (!existingCard) {
                            addUser(user);
                        }
                    } else {
                        // User status changed from pending - remove from list
                        removeUser(user.id);
                    }
                } else if (chg.type === 'removed') {
                    // User removed - remove from list
                    removeUser(user.id);
                }
            });
        }, (err) => {
            console.warn('Real-time listener error:', err);
            showToast('Real-time connection error', 'error');
        });

        // Start the system
        loadPendingUsers();

        // Cleanup on page unload
        window.addEventListener('beforeunload', () => {
            if (unsubscribe) unsubscribe();
        });
    </script>
</body>
</html>
