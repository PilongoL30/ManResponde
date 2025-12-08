<?php
/**
 * Verify Users View
 * Shows list of pending users for admin verification
 */
?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-slate-800">Verify Users</h2>
            <p class="text-sm text-slate-600 mt-1">Review and approve pending user accounts</p>
        </div>
        
        <div class="flex gap-3">
            <button onclick="refreshUserList()" class="px-4 py-2 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors">
                <i class="fas fa-refresh mr-2"></i>Refresh
            </button>
        </div>
    </div>
    
    <!-- Pending Users Section -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
        <div class="p-6 border-b border-slate-200">
            <h3 class="text-lg font-semibold text-slate-800">Pending Verification</h3>
            <p class="text-sm text-slate-600 mt-1">Users awaiting admin approval</p>
        </div>
        
        <div id="pendingUsersList" class="divide-y divide-slate-200">
            <div class="p-8 text-center text-slate-500">
                <i class="fas fa-spinner fa-spin text-2xl mb-3"></i>
                <p>Loading pending users...</p>
            </div>
        </div>
    </div>
    
    <!-- Verified Users Section -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
        <div class="p-6 border-b border-slate-200">
            <h3 class="text-lg font-semibold text-slate-800">Verified Users</h3>
            <p class="text-sm text-slate-600 mt-1">Approved user accounts</p>
        </div>
        
        <div id="verifiedUsersList" class="divide-y divide-slate-200">
            <div class="p-8 text-center text-slate-500">
                <i class="fas fa-spinner fa-spin text-2xl mb-3"></i>
                <p>Loading verified users...</p>
            </div>
        </div>
    </div>
</div>

<script>
// Load users on page load
document.addEventListener('DOMContentLoaded', function() {
    loadPendingUsers();
    loadVerifiedUsers();
});

function loadPendingUsers() {
    // This will be handled by the controller
    fetch('dashboard.php?action=get_pending_users')
        .then(response => response.json())
        .then(data => {
            renderUserList(data.users, 'pendingUsersList', true);
        })
        .catch(error => {
            console.error('Error loading pending users:', error);
            document.getElementById('pendingUsersList').innerHTML = 
                '<div class="p-8 text-center text-red-600">Error loading users</div>';
        });
}

function loadVerifiedUsers() {
    fetch('dashboard.php?action=get_verified_users')
        .then(response => response.json())
        .then(data => {
            renderUserList(data.users, 'verifiedUsersList', false);
        })
        .catch(error => {
            console.error('Error loading verified users:', error);
            document.getElementById('verifiedUsersList').innerHTML = 
                '<div class="p-8 text-center text-red-600">Error loading users</div>';
        });
}

function renderUserList(users, containerId, showVerifyButton) {
    const container = document.getElementById(containerId);
    
    if (users.length === 0) {
        container.innerHTML = '<div class="p-8 text-center text-slate-500">No users found</div>';
        return;
    }
    
    container.innerHTML = users.map(user => `
        <div class="p-4 hover:bg-slate-50 transition-colors">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-gradient-to-br from-blue-500 to-cyan-500 flex items-center justify-center text-white font-semibold text-lg">
                        ${user.displayName ? user.displayName.charAt(0).toUpperCase() : 'U'}
                    </div>
                    <div>
                        <h4 class="font-medium text-slate-800">${user.displayName || 'No name'}</h4>
                        <p class="text-sm text-slate-600">${user.email}</p>
                        <p class="text-xs text-slate-500 mt-1">
                            Created: ${new Date(user.metadata.createdAt).toLocaleDateString()}
                        </p>
                    </div>
                </div>
                
                <div class="flex gap-2">
                    ${showVerifyButton ? `
                        <button onclick="verifyUser('${user.uid}', '${user.displayName}')" 
                                class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors">
                            <i class="fas fa-check mr-2"></i>Verify
                        </button>
                        <button onclick="declineUser('${user.uid}', '${user.displayName}')" 
                                class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors">
                            <i class="fas fa-times mr-2"></i>Decline
                        </button>
                    ` : `
                        <button onclick="unverifyUser('${user.uid}', '${user.displayName}')" 
                                class="px-4 py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 transition-colors">
                            <i class="fas fa-undo mr-2"></i>Unverify
                        </button>
                    `}
                </div>
            </div>
        </div>
    `).join('');
}

function verifyUser(uid, displayName) {
    if (!confirm(`Verify user "${displayName}"?`)) return;
    
    const formData = createFormDataWithCsrf();
    formData.append('action', 'verify_user');
    formData.append('uid', uid);
    formData.append('verify', '1');
    
    fetch('dashboard.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('User verified successfully!');
                refreshUserList();
            } else {
                alert('Error: ' + (data.error || 'Failed to verify user'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while verifying the user');
        });
}

function unverifyUser(uid, displayName) {
    if (!confirm(`Unverify user "${displayName}"?`)) return;
    
    const formData = createFormDataWithCsrf();
    formData.append('action', 'verify_user');
    formData.append('uid', uid);
    formData.append('verify', '0');
    
    fetch('dashboard.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('User unverified successfully!');
                refreshUserList();
            } else {
                alert('Error: ' + (data.error || 'Failed to unverify user'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while unverifying the user');
        });
}

function declineUser(uid, displayName) {
    if (!confirm(`Delete/decline user "${displayName}"? This action cannot be undone.`)) return;
    
    const formData = createFormDataWithCsrf();
    formData.append('action', 'delete_user');
    formData.append('uid', uid);
    
    fetch('dashboard.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('User deleted successfully!');
                refreshUserList();
            } else {
                alert('Error: ' + (data.error || 'Failed to delete user'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while deleting the user');
        });
}

function refreshUserList() {
    loadPendingUsers();
    loadVerifiedUsers();
}
</script>
