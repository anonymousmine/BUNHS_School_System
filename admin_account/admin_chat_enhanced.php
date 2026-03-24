<?php

/**
 * admin_chat_enhanced.php
 * Enhanced admin chatbox with support for:
 * - Student messaging (existing functionality)
 * - Admin-to-admin messaging (new functionality)
 * - Real-time status indicators
 * - Improved UI with tabbed interface
 */

require_once '../session_config.php';

if (
    !(isset($_SESSION['user_id']) && in_array($_SESSION['user_type'] ?? '', ['admin', 'sub-admin']))
    && !isset($_SESSION['admin_id'])
) {
    header('Location: ../index.php');
    exit;
}

include '../db_connection.php';

// Build base URLs
$_script     = $_SERVER['SCRIPT_NAME'];
$adminBase   = rtrim(dirname($_script), '/') . '/';
$assetsBase  = rtrim(dirname(dirname($_script)), '/') . '/';

$preloadConvId  = (int) ($_GET['conv'] ?? 0);
$preloadTab    = $_GET['tab'] ?? 'students';
$adminInitial   = strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enhanced Chatbox – BUNHS Admin</title>
    <link rel="stylesheet" href="<?= $assetsBase ?>admin_account/admin_assets/cs/admin_style.css">
    <link rel="stylesheet" href="../overall_body.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=Syne:wght@600;700;800&display=swap" rel="stylesheet">

    <style>
        /* Enhanced Chat Styles */
        :root {
            --moss: #7a8f4e;
            --moss-dark: #5c6b38;
            --moss-light: #adbf72;
            --moss-ultra: rgba(122, 143, 78, .1);
            --moss-glow: rgba(122, 143, 78, .3);
            --bg: #f2f5f0;
            --surface: #ffffff;
            --border: #e4e9de;
            --text: #1a2010;
            --muted: #6b7c55;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, .06);
            --shadow-md: 0 6px 24px rgba(0, 0, 0, .09);
            --shadow-lg: 0 16px 48px rgba(0, 0, 0, .12);
            --radius: 14px;
            --radius-sm: 8px;
            --font: 'DM Sans', sans-serif;
            --font-d: 'Syne', sans-serif;
        }

        body {
            font-family: var(--font);
            background: var(--bg);
            color: var(--text);
        }

        /* Tab Navigation */
        .chat-tabs {
            display: flex;
            background: var(--surface);
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border);
            margin-bottom: 20px;
            overflow: hidden;
        }

        .chat-tab {
            flex: 1;
            padding: 16px 20px;
            background: transparent;
            border: none;
            font-family: var(--font-d);
            font-weight: 700;
            font-size: 15px;
            color: var(--muted);
            cursor: pointer;
            transition: all .2s ease;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .chat-tab:hover {
            background: var(--moss-ultra);
            color: var(--moss);
        }

        .chat-tab.active {
            background: var(--moss);
            color: #fff;
        }

        .chat-tab .badge {
            background: rgba(255, 255, 255, .3);
            color: inherit;
            font-size: 11px;
            padding: 2px 6px;
            border-radius: 10px;
            font-weight: 700;
        }

        .chat-tab:not(.active) .badge {
            background: var(--moss);
            color: #fff;
        }

        /* Tab Content */
        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Online Admins Sidebar */
        .online-admins-sidebar {
            width: 280px;
            background: var(--surface);
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .online-admins-header {
            padding: 16px 18px;
            border-bottom: 1px solid var(--border);
            background: linear-gradient(135deg, rgba(122, 143, 78, .03), transparent);
        }

        .online-admins-header h3 {
            font-family: var(--font-d);
            font-size: 15px;
            font-weight: 700;
            color: var(--text);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .online-admins-list {
            flex: 1;
            overflow-y: auto;
            padding: 8px;
        }

        .online-admin-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: background .15s;
            margin-bottom: 4px;
        }

        .online-admin-item:hover {
            background: var(--moss-ultra);
        }

        .online-admin-avatar {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--moss), var(--moss-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            font-size: 14px;
            position: relative;
        }

        .online-status {
            position: absolute;
            bottom: -2px;
            right: -2px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            border: 2px solid var(--surface);
        }

        .online-status.online { background: #22c55e; }
        .online-status.away { background: #f59e0b; }
        .online-status.busy { background: #ef4444; }

        .online-admin-info {
            flex: 1;
            min-width: 0;
        }

        .online-admin-name {
            font-weight: 600;
            font-size: 13px;
            color: var(--text);
            margin-bottom: 2px;
        }

        .online-admin-role {
            font-size: 11px;
            color: var(--muted);
        }

        /* Admin Chat Layout */
        .admin-chat-layout {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 20px;
            flex: 1;
            min-height: 0;
        }

        /* Enhanced conversation items */
        .admin-conv-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            cursor: pointer;
            border-bottom: 1px solid #f5f8f2;
            transition: all .15s ease;
            position: relative;
        }

        .admin-conv-item:hover {
            background: var(--moss-ultra);
        }

        .admin-conv-item.active {
            background: var(--moss-ultra);
            border-left: 4px solid var(--moss);
        }

        .admin-conv-avatar {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            font-size: 16px;
            flex-shrink: 0;
        }

        .admin-type-badge {
            position: absolute;
            top: 8px;
            right: 8px;
            background: var(--moss);
            color: #fff;
            font-size: 9px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 8px;
            text-transform: uppercase;
        }

        /* Responsive Design */
        @media (max-width: 900px) {
            .admin-chat-layout {
                grid-template-columns: 280px 1fr;
                gap: 16px;
            }
            
            .online-admins-sidebar {
                display: none;
            }
        }

        @media (max-width: 600px) {
            .admin-chat-layout {
                grid-template-columns: 1fr;
            }
            
            .chat-tabs {
                flex-direction: column;
            }
            
            .chat-tab {
                border-bottom: 1px solid var(--border);
            }
        }
    </style>
</head>

<body>
    <?php include 'admin_nav.php'; ?>
    <script>
        if (typeof initializeNavigation === 'function') {
            initializeNavigation();
        }
    </script>

    <!-- Enhanced Chat Page Content -->
    <section class="page-content" id="chatbox-content" style="display: none;">
        <!-- Chat Tabs -->
        <div class="chat-tabs">
            <button class="chat-tab <?= $preloadTab === 'students' ? 'active' : '' ?>" onclick="switchTab('students')">
                <i class="fas fa-user-graduate"></i>
                Student Messages
                <span class="badge" id="studentBadge">0</span>
            </button>
            <button class="chat-tab <?= $preloadTab === 'admins' ? 'active' : '' ?>" onclick="switchTab('admins')">
                <i class="fas fa-users"></i>
                Admin Chat
                <span class="badge" id="adminBadge">0</span>
            </button>
        </div>

        <!-- Students Tab Content -->
        <div class="tab-content <?= $preloadTab === 'students' ? 'active' : '' ?>" id="studentsTab">
            <!-- Original student chat interface will be loaded here -->
            <div class="chat-page">
                <div class="chat-layout">
                    <div class="conv-list">
                        <div class="conv-list-header">
                            <h3><i class="fas fa-list"></i>Student Conversations</h3>
                            <input type="text" class="conv-search" placeholder="Search conversations..." id="studentConvSearch" oninput="searchStudentConversations(this.value)">
                        </div>
                        <div class="conv-items" id="studentConvItems"></div>
                    </div>
                    <div class="chat-win">
                        <div id="studentChatArea" style="display:none; flex:1; display:flex; flex-direction:column;">
                            <div class="chat-win-header">
                                <div class="chat-win-avatar" id="studentChatAvatar">S</div>
                                <div class="chat-win-info">
                                    <div class="chat-win-name" id="studentChatName">—</div>
                                    <div class="chat-win-status" id="studentChatStatus">—</div>
                                </div>
                            </div>
                            <div class="chat-messages" id="studentChatMessages"></div>
                            <div class="chat-input-box">
                                <div class="msg-input-wrapper">
                                    <textarea class="msg-input" id="studentMsgInput" placeholder="Type your message..." rows="1" onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault(); sendStudentMessage();}"></textarea>
                                    <button class="send-btn" id="studentSendBtn" onclick="sendStudentMessage()">
                                        <i class="fas fa-paper-plane"></i>
                                        <span>Send</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="chat-empty" id="studentChatEmpty">
                            <i class="fas fa-comments"></i>
                            <p>Select a student conversation to start messaging</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Admins Tab Content -->
        <div class="tab-content <?= $preloadTab === 'admins' ? 'active' : '' ?>" id="adminsTab">
            <div class="admin-chat-layout">
                <!-- Online Admins Sidebar -->
                <div class="online-admins-sidebar">
                    <div class="online-admins-header">
                        <h3><i class="fas fa-circle"></i>Online Admins</h3>
                    </div>
                    <div class="online-admins-list" id="onlineAdminsList">
                        <div style="padding: 20px; text-align: center; color: var(--muted);">
                            <i class="fas fa-spinner fa-spin"></i> Loading...
                        </div>
                    </div>
                </div>

                <!-- Admin Conversations -->
                <div class="conv-list">
                    <div class="conv-list-header">
                        <h3><i class="fas fa-comments"></i>Admin Conversations</h3>
                        <input type="text" class="conv-search" placeholder="Search conversations..." id="adminConvSearch" oninput="searchAdminConversations(this.value)">
                    </div>
                    <div class="conv-items" id="adminConvItems"></div>
                </div>

                <!-- Admin Chat Window -->
                <div class="chat-win">
                    <div id="adminChatArea" style="display:none; flex:1; display:flex; flex-direction:column;">
                        <div class="chat-win-header">
                            <div class="chat-win-avatar" id="adminChatAvatar">A</div>
                            <div class="chat-win-info">
                                <div class="chat-win-name" id="adminChatName">—</div>
                                <div class="chat-win-status" id="adminChatStatus">—</div>
                            </div>
                        </div>
                        <div class="chat-messages" id="adminChatMessages"></div>
                        <div class="chat-input-box">
                            <div class="msg-input-wrapper">
                                <textarea class="msg-input" id="adminMsgInput" placeholder="Type your message..." rows="1" onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault(); sendAdminMessage();}"></textarea>
                                <button class="send-btn" id="adminSendBtn" onclick="sendAdminMessage()">
                                    <i class="fas fa-paper-plane"></i>
                                    <span>Send</span>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="chat-empty" id="adminChatEmpty">
                        <i class="fas fa-comments"></i>
                        <p>Select an admin conversation to start messaging</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Toast notification zone -->
    <div id="toastZone"></div>

    <script>
        // Configuration
        const STUDENT_API = '<?= $adminBase ?>chat_api.php';
        const ADMIN_API = '<?= $adminBase ?>admin_chat_api.php';
        const ADMIN_INIT = '<?= $adminInitial ?>';

        // State Management
        let currentTab = '<?= $preloadTab ?>';
        let activeStudentConvId = <?= $preloadConvId ?>;
        let activeAdminConvId = 0;
        let activeStudent = {};
        let activeAdminUser = {};
        let studentConversations = [];
        let adminConversations = [];
        let onlineAdmins = [];

        // Tab Switching
        function switchTab(tab) {
            currentTab = tab;
            
            // Update tab buttons
            document.querySelectorAll('.chat-tab').forEach(btn => btn.classList.remove('active'));
            event.target.closest('.chat-tab').classList.add('active');
            
            // Update content
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            document.getElementById(tab + 'Tab').classList.add('active');
            
            // Load appropriate data
            if (tab === 'students') {
                loadStudentConversations();
            } else {
                loadAdminConversations();
                loadOnlineAdmins();
            }
            
            // Update URL
            const url = new URL(window.location);
            url.searchParams.set('tab', tab);
            window.history.replaceState({}, '', url);
        }

        // Load Online Admins
        async function loadOnlineAdmins() {
            try {
                const response = await fetch(ADMIN_API + '?action=get_online_admins');
                const data = await response.json();
                
                if (data.success) {
                    onlineAdmins = data.admins;
                    renderOnlineAdmins();
                }
            } catch (error) {
                console.error('Failed to load online admins:', error);
            }
        }

        function renderOnlineAdmins() {
            const container = document.getElementById('onlineAdminsList');
            
            if (!onlineAdmins.length) {
                container.innerHTML = '<div style="padding: 20px; text-align: center; color: var(--muted);">No admins online</div>';
                return;
            }

            container.innerHTML = onlineAdmins.map(admin => `
                <div class="online-admin-item" onclick="startAdminChat(${admin.id}, '${escHtml(admin.full_name)}', '${admin.user_type}')">
                    <div class="online-admin-avatar">
                        ${admin.avatar_letter}
                        <div class="online-status ${admin.chat_status}"></div>
                    </div>
                    <div class="online-admin-info">
                        <div class="online-admin-name">${admin.full_name}</div>
                        <div class="online-admin-role">${admin.user_type === 'admin' ? 'Administrator' : 'Sub-Admin'}</div>
                    </div>
                </div>
            `).join('');
        }

        // Start Admin Chat
        async function startAdminChat(adminId, adminName, adminType) {
            // Create or get conversation
            const fd = new FormData();
            fd.append('action', 'send_admin_message');
            fd.append('receiver_id', adminId);
            fd.append('message', ''); // Empty message to create conversation
            
            try {
                const response = await fetch(ADMIN_API, {
                    method: 'POST',
                    body: fd
                });
                const data = await response.json();
                
                if (data.success) {
                    // Load the conversation
                    await loadAdminConversations();
                    const conv = adminConversations.find(c => c.other_user_id === adminId);
                    if (conv) {
                        selectAdminConversation(conv.conversation_id, conv);
                    }
                }
            } catch (error) {
                console.error('Failed to start admin chat:', error);
                toast('Failed to start conversation', 'error');
            }
        }

        // Load Admin Conversations
        async function loadAdminConversations() {
            try {
                const fd = new FormData();
                fd.append('action', 'fetch_admin_conversations');
                
                const response = await fetch(ADMIN_API, {
                    method: 'POST',
                    body: fd
                });
                const data = await response.json();
                
                if (data.success) {
                    adminConversations = data.conversations;
                    renderAdminConversations();
                    updateAdminBadge();
                }
            } catch (error) {
                console.error('Failed to load admin conversations:', error);
            }
        }

        function renderAdminConversations() {
            const container = document.getElementById('adminConvItems');
            
            if (!adminConversations.length) {
                container.innerHTML = '<div style="padding:20px; text-align:center; color:var(--muted);"><i class="fas fa-inbox" style="display:block; font-size:32px; opacity:.2; margin-bottom:8px;"></i>No admin conversations</div>';
                return;
            }

            container.innerHTML = adminConversations.map(conv => `
                <div class="admin-conv-item ${activeAdminConvId === conv.conversation_id ? 'active' : ''}" 
                     onclick="selectAdminConversation(${conv.conversation_id}, ${JSON.stringify(conv).replace(/"/g, '&quot;')})">
                    <div class="admin-conv-avatar">${conv.avatar_letter}</div>
                    <div class="conv-info">
                        <div class="conv-name">
                            ${escHtml(conv.other_user_name)}
                            ${conv.unread_count > 0 ? `<span class="conv-unread">${conv.unread_count}</span>` : ''}
                            <span class="admin-type-badge">${conv.other_user_type === 'admin' ? 'Admin' : 'Sub-Admin'}</span>
                        </div>
                        <div class="conv-preview">${escHtml(conv.last_message || '—')}</div>
                        <div class="conv-time">${escHtml(conv.time_ago || '')}</div>
                    </div>
                </div>
            `).join('');
        }

        // Select Admin Conversation
        async function selectAdminConversation(convId, conv) {
            activeAdminConvId = convId;
            activeAdminUser = conv;

            // Update UI
            document.querySelectorAll('.admin-conv-item').forEach(el => el.classList.remove('active'));
            event.target.closest('.admin-conv-item').classList.add('active');

            document.getElementById('adminChatAvatar').textContent = conv.avatar_letter;
            document.getElementById('adminChatName').textContent = conv.other_user_name;
            document.getElementById('adminChatStatus').textContent = `${conv.other_user_type === 'admin' ? 'Administrator' : 'Sub-Admin'} • Online`;

            document.getElementById('adminChatEmpty').style.display = 'none';
            document.getElementById('adminChatArea').style.display = 'flex';

            // Mark as read and load messages
            await markAdminAsRead();
            await loadAdminMessages();
        }

        // Load Admin Messages
        async function loadAdminMessages() {
            try {
                const fd = new FormData();
                fd.append('action', 'fetch_admin_messages');
                fd.append('conversation_id', activeAdminConvId);

                const response = await fetch(ADMIN_API, {
                    method: 'POST',
                    body: fd
                });
                const data = await response.json();

                if (data.success) {
                    renderAdminMessages(data.messages);
                }
            } catch (error) {
                console.error('Failed to load admin messages:', error);
            }
        }

        function renderAdminMessages(msgs) {
            const container = document.getElementById('adminChatMessages');
            const atBottom = container.scrollHeight - container.scrollTop <= container.clientHeight + 40;

            let lastDate = '';
            
            container.innerHTML = msgs.map(msg => {
                const msgDate = (msg.created_at || '').substring(0, 10);
                let divider = '';
                
                if (msgDate && msgDate !== lastDate) {
                    lastDate = msgDate;
                    divider = `<div class="date-sep"><span>${formatDate(msgDate)}</span></div>`;
                }

                const isMine = msg.is_mine;
                const avatar = isMine ? ADMIN_INIT : msg.avatar_letter;

                return `${divider}
                <div class="msg-row ${isMine ? 'mine' : ''}">
                    <div class="msg-av">${escHtml(avatar)}</div>
                    <div>
                        <div class="msg-bubble">
                            <div class="msg-text">${escHtml(msg.message)}</div>
                        </div>
                        <div class="msg-time">${escHtml(msg.time_label)}</div>
                    </div>
                </div>`;
            }).join('');

            if (atBottom) container.scrollTop = container.scrollHeight;
        }

        // Send Admin Message
        async function sendAdminMessage() {
            const input = document.getElementById('adminMsgInput');
            const text = input.value.trim();
            
            if (!text || !activeAdminConvId) return;

            const btn = document.getElementById('adminSendBtn');
            btn.disabled = true;
            input.value = '';

            const fd = new FormData();
            fd.append('action', 'send_admin_message');
            fd.append('conversation_id', activeAdminConvId);
            fd.append('message', text);

            try {
                const response = await fetch(ADMIN_API, {
                    method: 'POST',
                    body: fd
                });
                const data = await response.json();

                btn.disabled = false;

                if (data.success) {
                    await loadAdminMessages();
                    await loadAdminConversations();
                } else {
                    input.value = text;
                    toast('Failed to send message', 'error');
                }
            } catch (error) {
                btn.disabled = false;
                input.value = text;
                toast('Failed to send message', 'error');
            }
        }

        // Mark Admin Messages as Read
        async function markAdminAsRead() {
            const fd = new FormData();
            fd.append('action', 'mark_admin_read');
            fd.append('conversation_id', activeAdminConvId);
            
            try {
                await fetch(ADMIN_API, {
                    method: 'POST',
                    body: fd
                });
            } catch (error) {
                console.error('Failed to mark as read:', error);
            }
        }

        // Update Admin Badge
        function updateAdminBadge() {
            const unreadCount = adminConversations.reduce((total, conv) => total + conv.unread_count, 0);
            const badge = document.getElementById('adminBadge');
            badge.textContent = unreadCount > 99 ? '99+' : unreadCount;
            badge.style.display = unreadCount > 0 ? '' : 'none';
        }

        // Search Admin Conversations
        function searchAdminConversations(query) {
            const q = query.toLowerCase().trim();
            const filtered = adminConversations.filter(conv =>
                conv.other_user_name.toLowerCase().includes(q)
            );
            renderAdminConversationsFiltered(filtered);
        }

        function renderAdminConversationsFiltered(convs) {
            const container = document.getElementById('adminConvItems');
            
            if (!convs.length) {
                container.innerHTML = '<div style="padding:20px; text-align:center; color:var(--muted);">No conversations found</div>';
                return;
            }

            container.innerHTML = convs.map(conv => `
                <div class="admin-conv-item ${activeAdminConvId === conv.conversation_id ? 'active' : ''}" 
                     onclick="selectAdminConversation(${conv.conversation_id}, ${JSON.stringify(conv).replace(/"/g, '&quot;')})">
                    <div class="admin-conv-avatar">${conv.avatar_letter}</div>
                    <div class="conv-info">
                        <div class="conv-name">
                            ${escHtml(conv.other_user_name)}
                            ${conv.unread_count > 0 ? `<span class="conv-unread">${conv.unread_count}</span>` : ''}
                            <span class="admin-type-badge">${conv.other_user_type === 'admin' ? 'Admin' : 'Sub-Admin'}</span>
                        </div>
                        <div class="conv-preview">${escHtml(conv.last_message || '—')}</div>
                        <div class="conv-time">${escHtml(conv.time_ago || '')}</div>
                    </div>
                </div>
            `).join('');
        }

        // Helper Functions
        function escHtml(s) {
            if (s == null) return '';
            return String(s)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function formatDate(d) {
            const today = new Date().toISOString().substring(0, 10);
            if (d === today) return 'Today';
            const yest = new Date(Date.now() - 86400000).toISOString().substring(0, 10);
            if (d === yest) return 'Yesterday';
            return new Date(d).toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric'
            });
        }

        function toast(msg, type = 'success') {
            const zone = document.getElementById('toastZone');
            const t = document.createElement('div');
            t.className = `toast ${type}`;
            t.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i><span>${escHtml(msg)}</span>`;
            zone.appendChild(t);
            requestAnimationFrame(() => t.classList.add('show'));
            setTimeout(() => {
                t.classList.remove('show');
                setTimeout(() => t.remove(), 300);
            }, 3500);
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Load initial data based on current tab
            if (currentTab === 'students') {
                // Load student conversations (existing functionality)
                loadStudentConversations();
            } else {
                loadAdminConversations();
                loadOnlineAdmins();
            }

            // Update chat status to online
            updateChatStatus('online');

            // Set up periodic status updates
            setInterval(updateChatStatus, 30000); // Update every 30 seconds
        });

        // Update Chat Status
        async function updateChatStatus(status = 'online') {
            const fd = new FormData();
            fd.append('action', 'update_chat_status');
            fd.append('status', status);
            
            try {
                await fetch(ADMIN_API, {
                    method: 'POST',
                    body: fd
                });
            } catch (error) {
                console.error('Failed to update chat status:', error);
            }
        }

        // Student chat functions (simplified versions of existing functionality)
        async function loadStudentConversations() {
            // This would contain the existing student chat loading logic
            // For now, show placeholder
            document.getElementById('studentConvItems').innerHTML = 
                '<div style="padding:20px; text-align:center; color:var(--muted);">Student conversations will be loaded here</div>';
        }

        function searchStudentConversations(query) {
            // Student conversation search logic
        }

        async function sendStudentMessage() {
            // Student message sending logic
            toast('Student messaging functionality to be integrated', 'success');
        }

        // Load Navigation
        function loadNavigation() {
            const container = document.getElementById('navigation-container');
            if (!container) return;

            fetch('admin_nav.php')
                .then(response => {
                    if (!response.ok) throw new Error('Failed to load navigation');
                    return response.text();
                })
                .then(data => {
                    container.innerHTML = data;
                    if (typeof initializeNavigation === 'function') {
                        initializeNavigation();
                    }
                    document.getElementById('chatbox-content').style.display = 'block';
                })
                .catch(error => {
                    console.error('Navigation error:', error);
                    container.innerHTML = `
                        <div style="padding: 24px; text-align: center; color: var(--danger);">
                            <i class="fas fa-exclamation-triangle" style="font-size: 48px; margin-bottom: 12px;"></i>
                            <h3>Unable to Load Navigation</h3>
                            <p>Please refresh the page.</p>
                        </div>
                    `;
                });
        }

        // Initialize navigation when page loads
        loadNavigation();
    </script>
</body>
</html>
