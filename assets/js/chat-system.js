document.addEventListener('DOMContentLoaded', function() {
    if (!document.getElementById('chatListSidebar')) return;

    const chatList = document.getElementById('chatList');
    const messagesArea = document.getElementById('messagesArea');
    const messageInput = document.getElementById('messageInput');
    const messageForm = document.getElementById('messageForm');
    const chatHeader = document.getElementById('chatHeader');
    const messageInputArea = document.getElementById('messageInputArea');
    
    let currentChatId = null;
    let pollingInterval = null;

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
        chatList.innerHTML = '';
        if (chats.length === 0) {
            chatList.innerHTML = '<div class="text-center py-8 text-slate-500 text-sm">No active chats</div>';
            return;
        }

        chats.forEach(chat => {
            const div = document.createElement('div');
            div.className = `p-3 hover:bg-slate-100 cursor-pointer rounded-lg transition-colors ${currentChatId === chat.id ? 'bg-sky-50 border-l-4 border-sky-500' : ''}`;
            div.onclick = () => selectChat(chat);
            div.innerHTML = `
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-slate-200 flex items-center justify-center font-bold text-slate-600">
                        ${getInitials(chat.userName || 'User')}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex justify-between items-start">
                            <h4 class="font-bold text-slate-800 truncate">${chat.userName || 'Anonymous'}</h4>
                            <span class="text-xs text-slate-400">${formatTime(chat.lastMessageTime)}</span>
                        </div>
                        <p class="text-xs text-slate-500 truncate">${chat.lastMessage || 'No messages yet'}</p>
                    </div>
                </div>
            `;
            chatList.appendChild(div);
        });
    }

    function selectChat(chat) {
        currentChatId = chat.id;
        
        // Update Header
        document.getElementById('chatUserName').textContent = chat.userName || 'Anonymous';
        document.getElementById('chatUserInitials').textContent = getInitials(chat.userName || 'User');
        
        // Show UI
        chatHeader.classList.remove('hidden');
        messageInputArea.classList.remove('hidden');
        
        // Load Messages
        loadMessages(chat.id);
        
        // Start Polling
        if (pollingInterval) clearInterval(pollingInterval);
        pollingInterval = setInterval(() => loadMessages(chat.id), 3000); // Poll every 3s
        
        // Highlight in list
        loadChats(); // Refresh list to update active state
    }

    function loadMessages(chatId) {
        fetch(`api/support_chat.php?action=get_messages&chat_id=${chatId}`)
            .then(res => res.json())
            .then(data => {
                if (data.messages) {
                    renderMessages(data.messages);
                }
            });
    }

    function renderMessages(messages) {
        messagesArea.innerHTML = '';
        messages.forEach(msg => {
            const isMe = msg.senderId === window.dashboardConfig.userId;
            const div = document.createElement('div');
            div.className = `flex ${isMe ? 'justify-end' : 'justify-start'}`;
            div.innerHTML = `
                <div class="max-w-[70%] rounded-2xl px-4 py-2 ${isMe ? 'bg-sky-600 text-white rounded-br-none' : 'bg-white border border-slate-200 text-slate-800 rounded-bl-none'}">
                    <p class="text-sm">${msg.text}</p>
                    <p class="text-[10px] ${isMe ? 'text-sky-100' : 'text-slate-400'} mt-1 text-right">${formatTime(msg.timestamp)}</p>
                </div>
            `;
            messagesArea.appendChild(div);
        });
        messagesArea.scrollTop = messagesArea.scrollHeight;
    }

    messageForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const text = messageInput.value.trim();
        if (!text || !currentChatId) return;

        const formData = new FormData();
        formData.append('action', 'send_message');
        formData.append('chat_id', currentChatId);
        formData.append('message', text);

        fetch('api/support_chat.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                messageInput.value = '';
                loadMessages(currentChatId);
            }
        });
    });

    // Helpers
    function getInitials(name) {
        return name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
    }

    function formatTime(ts) {
        if (!ts) return '';
        const date = new Date(ts);
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    // Initial Load
    loadChats();
    setInterval(loadChats, 10000); // Refresh chat list every 10s
});
