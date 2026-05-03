/**
 * dashboard-ui.js
 * Extracted pure JavaScript blocks from dashboard.php
 * Contains: Theme Init, Live Support Badge, End Chat Modal, Verify Users Badge
 */

// ============================================================
// SECTION 1: Theme Preference Init
// Ensure theme preference is applied ASAP (force light mode)
// ============================================================
(function() {
    try {
        // Force light mode as per user request
        document.documentElement.classList.remove('dark');
        localStorage.setItem('theme', 'light');
    } catch(e) {}
})();


// ============================================================
// SECTION 2: Live Support Badge Logic
// Polls every 5 seconds for pending live support chats
// ============================================================
async function updateLiveSupportBadge() {
    try {
        const response = await fetch('api/support_chat.php?action=get_chats');
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const result = await response.json();

        if (Array.isArray(result.chats)) {
            // Count pending chats
            const pendingCount = result.chats.filter(c => !c.status || c.status === 'pending' || c.status === 'waiting').length;

            const badgeDesktop = document.getElementById('liveSupportBadge');
            const badgeMobile  = document.getElementById('liveSupportBadgeMobile');

            if (pendingCount > 0) {
                if (badgeDesktop) {
                    badgeDesktop.textContent = pendingCount;
                    badgeDesktop.classList.remove('hidden');
                    badgeDesktop.style.display = 'inline-flex';
                }
                if (badgeMobile) {
                    badgeMobile.textContent = pendingCount;
                    badgeMobile.classList.remove('hidden');
                    badgeMobile.style.display = 'inline-flex';
                }
            } else {
                if (badgeDesktop) {
                    badgeDesktop.classList.add('hidden');
                    badgeDesktop.style.display = 'none';
                }
                if (badgeMobile) {
                    badgeMobile.classList.add('hidden');
                    badgeMobile.style.display = 'none';
                }
            }
        }
    } catch (error) {
        console.error('Error updating live support badge:', error);
    }
}

// Start polling for live support badge updates
(function() {
    const startPolling = () => {
        updateLiveSupportBadge();
        setInterval(() => {
            if (document.hidden) return;
            updateLiveSupportBadge();
        }, 5000); // Poll every 5 seconds
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', startPolling);
    } else {
        startPolling();
    }
})();


// ============================================================
// SECTION 3: End Chat Modal Functions
// Animated modal for confirming end of live chat session
// ============================================================
function showEndChatModal() {
    const modal    = document.getElementById('endChatModal');
    const backdrop = document.getElementById('endChatModalBackdrop');
    const panel    = document.getElementById('endChatModalPanel');

    if (modal && backdrop && panel) {
        modal.classList.remove('hidden');
        // Trigger reflow
        void modal.offsetWidth;

        // Animate in
        backdrop.classList.remove('opacity-0');
        panel.classList.remove('opacity-0', 'translate-y-4', 'sm:translate-y-0', 'sm:scale-95');
        panel.classList.add('opacity-100', 'translate-y-0', 'sm:scale-100');
    }
}

function closeEndChatModal() {
    const modal    = document.getElementById('endChatModal');
    const backdrop = document.getElementById('endChatModalBackdrop');
    const panel    = document.getElementById('endChatModalPanel');

    if (modal && backdrop && panel) {
        // Animate out
        backdrop.classList.add('opacity-0');
        panel.classList.remove('opacity-100', 'translate-y-0', 'sm:scale-100');
        panel.classList.add('opacity-0', 'translate-y-4', 'sm:translate-y-0', 'sm:scale-95');

        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }
}


// ============================================================
// SECTION 4: Verify Users Badge Logic
// Polls every 10 seconds for pending user verifications
// ============================================================
async function updateVerifyUsersBadge() {
    try {
        const response = await fetch('api/get_pending_users_count.php');
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const result = await response.json();

        const count = result.count || 0;

        const badgeDesktop = document.getElementById('verifyUsersBadge');
        const badgeMobile  = document.getElementById('verifyUsersBadgeMobile');

        if (count > 0) {
            if (badgeDesktop) {
                badgeDesktop.textContent = count;
                badgeDesktop.classList.remove('hidden');
                badgeDesktop.style.display = 'inline-flex';
            }
            if (badgeMobile) {
                badgeMobile.textContent = count;
                badgeMobile.classList.remove('hidden');
                badgeMobile.style.display = 'inline-flex';
            }
        } else {
            if (badgeDesktop) {
                badgeDesktop.classList.add('hidden');
                badgeDesktop.style.display = 'none';
            }
            if (badgeMobile) {
                badgeMobile.classList.add('hidden');
                badgeMobile.style.display = 'none';
            }
        }
    } catch (error) {
        console.error('Error updating verify users badge:', error);
    }
}

// Start polling for verify users badge updates
(function() {
    const startPolling = () => {
        updateVerifyUsersBadge();
        setInterval(() => {
            if (document.hidden) return;
            updateVerifyUsersBadge();
        }, 10000); // Poll every 10 seconds
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', startPolling);
    } else {
        startPolling();
    }
})();
