                    <!-- Live Support UI -->
                    <div class="h-[calc(100vh-140px)] bg-white/80 backdrop-blur-sm rounded-2xl shadow-lg border border-slate-200/80 overflow-hidden flex flex-col md:flex-row relative">
                        <!-- Chat List Sidebar -->
                        <div id="chatListSidebar" class="w-full md:w-80 border-r border-slate-200 flex flex-col bg-slate-50/50 absolute md:relative z-20 h-full transition-transform duration-300 transform translate-x-0">
                            <div class="p-4 border-b border-slate-200 bg-white/50 backdrop-blur-sm">
                                <h2 class="font-bold text-slate-800 text-lg">Active Chats</h2>
                                <div class="relative mt-2">
                                    <input type="text" id="chatSearch" placeholder="Search chats..." class="w-full pl-9 pr-4 py-2 rounded-lg border border-slate-200 text-sm focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
                                    <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                    </svg>
                                </div>
                            </div>
                            <div id="chatList" class="flex-1 overflow-y-auto p-2 space-y-1">
                                <!-- Chat items will be loaded here -->
                                <div class="text-center py-8 text-slate-500 text-sm">Loading chats...</div>
                            </div>
                        </div>

                        <!-- Chat Area -->
                        <div id="chatArea" class="flex-1 flex flex-col bg-white relative z-10 w-full h-full hidden md:flex">
                            <!-- Chat Header -->
                            <div id="chatHeader" class="p-4 border-b border-slate-200 flex items-center justify-between bg-white/80 backdrop-blur-sm hidden">
                                <div class="flex items-center gap-3">
                                    <button id="backToChatList" class="md:hidden p-2 -ml-2 text-slate-600 hover:bg-slate-100 rounded-full">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                                        </svg>
                                    </button>
                                    <div class="w-10 h-10 rounded-full bg-sky-100 flex items-center justify-center text-sky-600 font-bold text-lg" id="chatUserInitials">
                                        <!-- Initials -->
                                    </div>
                                    <div>
                                        <h3 class="font-bold text-slate-800" id="chatUserName">Select a chat</h3>
                                        <p class="text-xs text-slate-500 flex items-center gap-1" id="chatUserStatus">
                                            <span class="w-2 h-2 rounded-full bg-slate-300"></span> Offline
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button id="endChatBtn" class="p-2 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-full transition-colors hidden" title="End Chat" onclick="confirmEndChat()">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                    <button class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-full transition-colors" title="View Profile">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Messages Area -->
                            <div id="messagesArea" class="flex-1 overflow-y-auto p-4 space-y-4 bg-slate-50/30 scroll-smooth">
                                <div class="h-full flex flex-col items-center justify-center text-slate-400">
                                    <svg class="w-16 h-16 mb-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                    </svg>
                                    <p class="text-lg font-medium">Select a conversation to start messaging</p>
                                </div>
                            </div>

                            <!-- Input Area -->
                            <div id="messageInputArea" class="p-4 border-t border-slate-200 bg-white hidden">
                                <form id="messageForm" class="flex items-end gap-2">
                                    <div class="flex-1 relative">
                                        <textarea id="messageInput" rows="1" class="w-full pl-4 pr-10 py-3 rounded-xl border border-slate-200 focus:ring-2 focus:ring-sky-500 focus:border-sky-500 resize-none max-h-32 min-h-[44px]" placeholder="Type your message..."></textarea>
                                        <button type="button" class="absolute right-2 bottom-2 p-1.5 text-slate-400 hover:text-sky-600 transition-colors">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                                            </svg>
                                        </button>
                                    </div>
                                    <button type="submit" class="bg-sky-600 hover:bg-sky-700 text-white p-3 rounded-xl shadow-none transition-all hover:scale-105 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
