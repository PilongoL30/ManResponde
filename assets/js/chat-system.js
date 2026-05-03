document.addEventListener('DOMContentLoaded', function() {
    if (!document.getElementById('chatListSidebar')) return;

    const chatList = document.getElementById('chatList');
    const messagesArea = document.getElementById('messagesArea');
    const messageInput = document.getElementById('messageInput');
    const messageForm = document.getElementById('messageForm');
    const chatHeader = document.getElementById('chatHeader');
    const messageInputArea = document.getElementById('messageInputArea');
    
    const acceptRequestArea = document.getElementById('acceptRequestArea');
    const acceptChatBtn = document.getElementById('acceptChatBtn');
    const endChatBtn = document.getElementById('endChatBtn');
    
    let currentChatId = null;
    let pollingInterval = null;
    let chatListInterval = null;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    let lastMessagesJson = '';
    let pendingMessages = [];

    // Load Chats
    function loadChats() {
        fetch('api/support_chat.php?action=get_chats')
            .then(res => res.json())
            .then(data => {
                if (data.chats) {
                    renderChatList(data.chats);
                }
            })
            .catch(err => console.error('Error loading chats:', err));
    }

    function renderChatList(chats) {
        const oldScrollTop = chatList.scrollTop;
        const chatsJson = JSON.stringify(chats);
        if (window._lastChatsJson === chatsJson) return;
        window._lastChatsJson = chatsJson;

        chatList.innerHTML = '';
        if (chats.length === 0) {
            chatList.innerHTML = '<div class="text-center py-8 text-slate-500 text-sm">No active chats</div>';
            return;
        }

        chats.forEach(chat => {
            const isPending = (chat.status || 'pending').toLowerCase() === 'pending' || (chat.status || '').toLowerCase() === 'waiting';
            const div = document.createElement('div');
            div.className = `p-3 hover:bg-slate-100 cursor-pointer rounded-lg transition-colors mb-1 ${currentChatId === chat.id ? 'bg-sky-50 border-l-4 border-sky-500' : ''}`;
            div.onclick = () => selectChat(chat);
            div.innerHTML = `
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full ${isPending ? 'bg-amber-100 text-amber-600' : 'bg-sky-100 text-sky-600'} flex items-center justify-center font-bold relative">
                        ${getInitials(chat.userName || 'User')}
                        ${isPending ? '<span class="absolute -top-1 -right-1 w-3 h-3 bg-red-500 border-2 border-white rounded-full animate-pulse"></span>' : ''}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex justify-between items-start">
                            <h4 class="font-bold text-slate-800 truncate">${chat.userName || 'Anonymous'}</h4>
                            <span class="text-[10px] text-slate-400">${formatTime(chat.lastMessageTime)}</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            ${isPending ? '<span class="text-[10px] font-bold text-amber-600 bg-amber-50 px-1 rounded uppercase tracking-tighter">New Request</span>' : ''}
                            <p class="text-xs text-slate-500 truncate">${chat.lastMessage || 'No messages yet'}</p>
                        </div>
                    </div>
                </div>
            `;
            chatList.appendChild(div);
        });
        chatList.scrollTop = oldScrollTop;
    }

    function selectChat(chat) {
        const isAlreadySelected = currentChatId === chat.id;
        currentChatId = chat.id;
        lastMessagesJson = ''; // Reset to force re-render on new chat
        pendingMessages = []; // Clear pending on chat switch
        
        // Update Header
        document.getElementById('chatUserName').textContent = chat.userName || 'Anonymous';
        document.getElementById('chatUserInitials').textContent = getInitials(chat.userName || 'User');
        
        const isPending = (chat.status || 'pending').toLowerCase() === 'pending' || (chat.status || '').toLowerCase() === 'waiting';
        
        // Update Status Indicator
        const statusEl = document.getElementById('chatUserStatus');
        if (statusEl) {
            if (isPending) {
                statusEl.innerHTML = '<span class="w-2 h-2 rounded-full bg-amber-400"></span> Requesting Assistance';
            } else {
                statusEl.innerHTML = '<span class="w-2 h-2 rounded-full bg-green-500"></span> Online';
            }
        }

        // Show UI
        chatHeader.classList.remove('hidden');
        
        if (isPending) {
            acceptRequestArea.classList.remove('hidden');
            messageInputArea.classList.add('hidden');
            endChatBtn.classList.add('hidden');
        } else {
            acceptRequestArea.classList.add('hidden');
            messageInputArea.classList.remove('hidden');
            endChatBtn.classList.remove('hidden');
            setTimeout(() => messageInput.focus(), 100);
        }
        
        if (!isAlreadySelected) {
            // Load Messages
            loadMessages(chat.id);
            
            // Start Polling for messages
            if (pollingInterval) clearInterval(pollingInterval);
            pollingInterval = setInterval(() => loadMessages(chat.id), 3000); 
        }
        
        // Highlight in list
        loadChats(); 
    }

    window.confirmEndChat = function() {
        if (!currentChatId) return;
        const modal = document.getElementById('endChatModal');
        if (modal) modal.classList.remove('hidden');
    };

    window.closeEndChatModal = function() {
        const modal = document.getElementById('endChatModal');
        if (modal) modal.classList.add('hidden');
    };

    window.executeEndChat = function() {
        if (!currentChatId) return;
        
        window.closeEndChatModal();

        const formData = new FormData();
        formData.append('action', 'end_chat');
        formData.append('chat_id', currentChatId);
        if (csrfToken) formData.append('csrf_token', csrfToken);

        fetch('api/support_chat.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Reset view
                currentChatId = null;
                chatHeader.classList.add('hidden');
                messageInputArea.classList.add('hidden');
                messagesArea.innerHTML = `
                    <div class="h-full flex flex-col items-center justify-center text-slate-400">
                        <svg class="w-16 h-16 mb-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                        </svg>
                        <p class="text-lg font-medium">Chat session ended successfully</p>
                    </div>
                `;
                if (pollingInterval) clearInterval(pollingInterval);
                loadChats();
            } else {
                alert('Error ending chat: ' + (data.error || 'Unknown error'));
            }
        });
    };

    acceptChatBtn.onclick = function() {
        if (!currentChatId) return;
        
        acceptChatBtn.disabled = true;
        acceptChatBtn.innerHTML = '<svg class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg> <span>Accepting...</span>';

        const formData = new FormData();
        formData.append('action', 'accept_chat');
        formData.append('chat_id', currentChatId);
        if (csrfToken) formData.append('csrf_token', csrfToken);

        fetch('api/support_chat.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                acceptRequestArea.classList.add('hidden');
                messageInputArea.classList.remove('hidden');
                setTimeout(() => messageInput.focus(), 100);
                loadChats(); 
            } else {
                alert('Error: ' + (data.error || 'Failed to accept chat'));
            }
        })
        .finally(() => {
            acceptChatBtn.disabled = false;
            acceptChatBtn.innerHTML = '<span>Accept Request</span><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>';
        });
    };

    function loadMessages(chatId) {
        if (currentChatId !== chatId) return;
        
        fetch(`api/support_chat.php?action=get_messages&chat_id=${chatId}`)
            .then(res => res.json())
            .then(data => {
                if (data.messages && currentChatId === chatId) {
                    // Filter out pending messages that have now been received from server
                    const serverTexts = data.messages.map(m => m.text);
                    pendingMessages = pendingMessages.filter(pm => !serverTexts.includes(pm.text));
                    
                    const combined = [...data.messages, ...pendingMessages];
                    const currentJson = JSON.stringify(combined);
                    if (currentJson !== lastMessagesJson) {
                        lastMessagesJson = currentJson;
                        renderMessages(combined);
                    }
                }
            })
            .catch(err => console.error('Error loading messages:', err));
    }

    function renderMessages(messages) {
        const isAtBottom = messagesArea.scrollHeight - messagesArea.scrollTop <= messagesArea.clientHeight + 100;
        
        messagesArea.innerHTML = '';
        messages.forEach(msg => {
            const isMe = msg.senderId === window.dashboardConfig.userId;
            const isPending = msg.isPending;
            const div = document.createElement('div');
            div.className = `flex ${isMe ? 'justify-end' : 'justify-start'} ${isPending ? 'opacity-70 animate-pulse' : 'animate-fade-in'}`;
            div.innerHTML = `
                <div class="max-w-[80%] rounded-2xl px-4 py-2 shadow-sm ${isMe ? 'bg-sky-600 text-white rounded-br-none' : 'bg-white border border-slate-200 text-slate-800 rounded-bl-none'}">
                    <p class="text-sm leading-relaxed">${msg.text}</p>
                    <p class="text-[9px] ${isMe ? 'text-sky-100' : 'text-slate-400'} mt-1 text-right font-medium">
                        ${isPending ? 'Sending...' : formatTime(msg.timestamp)}
                    </p>
                </div>
            `;
            messagesArea.appendChild(div);
        });
        
        if (isAtBottom) {
            messagesArea.scrollTop = messagesArea.scrollHeight;
        }
    }

    // Handle Enter Key
    messageInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            messageForm.requestSubmit();
        }
    });

    messageForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const text = messageInput.value.trim();
        if (!text || !currentChatId) return;

        // Optimistic message
        const optimisticMsg = {
            text: text,
            senderId: window.dashboardConfig.userId,
            timestamp: new Date().toISOString(),
            isPending: true
        };
        pendingMessages.push(optimisticMsg);
        
        // Immediate re-render with pending
        lastMessagesJson = ''; // Force re-render
        loadMessages(currentChatId);
        
        messageInput.value = '';
        messageInput.style.height = 'auto';

        const formData = new FormData();
        formData.append('action', 'send_message');
        formData.append('chat_id', currentChatId);
        formData.append('message', text);
        if (csrfToken) formData.append('csrf_token', csrfToken);

        fetch('api/support_chat.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Keep in pending until loadMessages confirms it's on server
                loadMessages(currentChatId);
            } else {
                pendingMessages = pendingMessages.filter(pm => pm !== optimisticMsg);
                alert('Failed to send: ' + (data.error || 'Unknown error'));
                loadMessages(currentChatId);
            }
        })
        .catch(err => {
            pendingMessages = pendingMessages.filter(pm => pm !== optimisticMsg);
            loadMessages(currentChatId);
        });
    });

    // Helpers
    function getInitials(name) {
        if (!name) return '?';
        return name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
    }

    function formatTime(ts) {
        if (!ts) return '';
        try {
            const date = new Date(ts);
            if (isNaN(date.getTime())) return '';
            return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        } catch(e) { return ''; }
    }

    loadChats();
    chatListInterval = setInterval(loadChats, 4000); 
});
