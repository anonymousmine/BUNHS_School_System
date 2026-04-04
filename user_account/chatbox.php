<?php
/**
 * chatbox.php - Student Chatbox
 * Fixed version with proper JavaScript function scoping
 */

require_once '../session_config.php';

if (!isset($_SESSION['student_id'])) {
    header('Location: ../index.php');
    exit;
}

include '../db_connection.php';

$student_id = (int) $_SESSION['student_id'];
$student_initial = strtoupper(substr($_SESSION['first_name'] ?? 'S', 0, 1));

$_script = $_SERVER['SCRIPT_NAME'];
$apiBase = rtrim(dirname($_script), '/') . '/';
$assetsBase = rtrim(dirname(dirname($_script)), '/') . '/';

// API paths
$chatApi = $apiBase . 'chat_api.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chatbox – BUNHS Student</title>
    <meta name="csrf-token" content="<?= generateCSRFToken() ?>">
    <link rel="stylesheet" href="<?= $assetsBase ?>overall_body.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --forest: #2d4a2b;
            --forest-mid: #3a5f38;
            --forest-light: #4a7347;
            --bg: #f8faf9;
            --surface: #ffffff;
            --surface2: #f1f5f9;
            --border: #e5e7eb;
            --text: #1f2937;
            --muted: #64748b;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --sidebar-w: 280px;
            --radius: 12px;
            --radius-sm: 8px;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.1);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.15);
            --shadow-lg: 0 8px 24px rgba(0,0,0,0.12);
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
        }

        .main-content {
            margin-left: var(--sidebar-w);
            margin-right: 0;
            min-height: 100vh;
            padding: 22px 24px;
            display: flex;
            flex-direction: column;
            width: calc(100% - var(--sidebar-w));
        }

        .chat-page {
            display: flex;
            flex-direction: column;
            height: calc(100vh - 150px);
            flex: 1;
            background: var(--surface);
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            overflow: hidden;
        }

        .chat-sidebar {
            width: 320px;
            background: var(--surface2);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            background: var(--surface);
        }

        .sidebar-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 4px;
        }

        .search-box {
            position: relative;
            margin-bottom: 12px;
        }

        .search-box input {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 13.5px;
            background: var(--surface);
            outline: none;
            transition: all .18s ease;
        }

        .search-box input:focus {
            border-color: var(--forest);
            background: var(--bg);
            box-shadow: var(--shadow-sm);
        }

        .conv-list {
            flex: 1;
            overflow-y: auto;
            padding: 8px;
        }

        .conv-item {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            transition: all .18s ease;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .conv-item:hover {
            background: var(--bg);
        }

        .conv-item.active {
            background: var(--forest);
            color: white;
        }

        .conv-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
            background: var(--surface2);
            color: var(--text);
        }

        .conv-item.active .conv-avatar {
            background: white;
            color: var(--forest);
        }

        .conv-av.club-av {
            background: var(--warning);
            color: white;
        }

        .conv-info {
            flex: 1;
            min-width: 0;
        }

        .conv-name {
            font-weight: 600;
            color: var(--text);
            margin-bottom: 2px;
        }

        .conv-item.active .conv-name {
            color: white;
        }

        .conv-preview {
            font-size: 12px;
            color: var(--muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .conv-item.active .conv-preview {
            color: rgba(255,255,255,0.8);
        }

        .club-tag {
            font-size: 9px;
            padding: 2px 6px;
            background: var(--warning);
            color: white;
            border-radius: 10px;
            font-weight: 600;
            margin-left: 6px;
        }

        .conv-time {
            font-size: 11px;
            color: var(--muted);
        }

        .conv-unread {
            background: var(--danger);
            color: white;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 10px;
            font-weight: 600;
            margin-left: auto;
        }

        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: var(--surface);
        }

        .chat-header {
            padding: 16px 22px;
            border-bottom: 1px solid var(--border);
            background: var(--surface);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .chat-info {
            flex: 1;
        }

        .chat-name {
            font-size: 16px;
            font-weight: 700;
            color: var(--text);
        }

        .chat-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 16px;
            background: var(--forest);
            color: white;
        }

        .club-header-badge {
            font-size: 11px;
            padding: 4px 8px;
            background: var(--warning);
            color: white;
            border-radius: 12px;
            font-weight: 600;
            margin-left: 8px;
        }

        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 16px;
            background: var(--bg);
            min-height: 300px;
        }

        .msg-row {
            display: flex;
            margin-bottom: 16px;
            align-items: flex-start;
            gap: 10px;
        }

        .msg-row.sent {
            flex-direction: row-reverse;
        }

        .msg-av {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
            background: var(--surface2);
            color: var(--text);
            flex-shrink: 0;
        }

        .msg-bubble {
            max-width: 70%;
            padding: 12px 16px;
            border-radius: 16px;
            background: var(--surface2);
            box-shadow: var(--shadow-sm);
            position: relative;
        }

        .msg-row.sent .msg-bubble {
            background: var(--forest);
            color: white;
        }

        .msg-text {
            font-size: 14px;
            line-height: 1.5;
            word-wrap: break-word;
        }

        .msg-time {
            font-size: 11px;
            color: var(--muted);
            margin-top: 4px;
        }

        .chat-input-box {
            padding: 16px 22px;
            border-top: 1px solid var(--border);
            background: var(--surface);
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }

        .msg-input {
            flex: 1;
            padding: 12px 16px;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 13.5px;
            background: var(--surface2);
            outline: none;
            transition: all .18s ease;
        }

        .msg-input:focus {
            border-color: var(--forest);
            background: var(--surface);
            box-shadow: var(--shadow-sm);
        }

        .send-btn {
            padding: 12px 22px;
            background: var(--forest);
            color: white;
            border: none;
            border-radius: var(--radius-sm);
            font-weight: 700;
            font-size: 13.5px;
            cursor: pointer;
            transition: all .18s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .send-btn:hover:not(:disabled) {
            background: var(--forest-mid);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .send-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .file-req-btn {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text);
            padding: 8px 12px;
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: all .18s ease;
            font-size: 13px;
        }

        .file-req-btn:hover {
            background: var(--surface2);
            border-color: var(--forest);
        }

        .file-req-btn.active {
            background: var(--forest);
            color: white;
            border-color: var(--forest);
        }

        .file-dropdown {
            position: absolute;
            bottom: 100%;
            left: 0;
            right: 0;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            max-height: 300px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }

        .file-dropdown.open {
            display: block;
        }

        .file-dropdown-header {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
            background: var(--surface2);
            font-weight: 600;
            color: var(--text);
        }

        .file-dropdown-body {
            max-height: 200px;
            overflow-y: auto;
        }

        .file-dropdown-item {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            transition: all .18s ease;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .file-dropdown-item:hover {
            background: var(--bg);
        }

        .file-dropdown-item-icon {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .file-dropdown-item-icon.pdf { background: #dc2626; color: white; }
        .file-dropdown-item-icon.img { background: #10b981; color: white; }
        .file-dropdown-item-icon.doc { background: #2563eb; color: white; }
        .file-dropdown-item-icon.xls { background: #0d47a1; color: white; }
        .file-dropdown-item-icon.other { background: var(--muted); color: white; }

        .file-dropdown-item-name {
            flex: 1;
            font-weight: 500;
            color: var(--text);
        }

        .file-dropdown-item-cat {
            font-size: 11px;
            color: var(--muted);
        }

        .file-dropdown-empty {
            padding: 24px;
            text-align: center;
            color: var(--muted);
            font-style: italic;
        }

        .reason-bar {
            padding: 12px 22px;
            background: #fff3cd;
            border-top: 1px solid var(--border);
            display: none;
            align-items: center;
            gap: 10px;
        }

        .reason-bar.show {
            display: flex;
        }

        .reason-input {
            flex: 1;
            padding: 10px 14px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 13px;
            background: var(--surface);
            outline: none;
        }

        .reason-input:focus {
            border-color: var(--forest);
            box-shadow: var(--shadow-sm);
        }

        .toast-zone {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 9999;
        }

        .toast {
            background: var(--surface);
            border-radius: 11px;
            padding: 13px 18px;
            box-shadow: var(--shadow-lg);
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 8px;
            opacity: 0;
            transform: translateY(20px);
            transition: all .3s ease;
        }

        .toast.show {
            opacity: 1;
            transform: translateY(0);
        }

        .chat-loading {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: var(--muted);
        }

        @media (max-width: 900px) {
            .main-content {
                padding: 90px 16px 24px;
            }
            .chat-sidebar {
                width: 280px;
            }
        }

        @media (max-width: 600px) {
            .main-content {
                padding: 80px 12px 24px;
            }
            .chat-page {
                flex-direction: column;
            }
            .chat-sidebar {
                width: 100%;
                height: 200px;
                border-right: none;
                border-bottom: 1px solid var(--border);
            }
            .chat-area {
                margin-left: 0;
            }
        }
    </style>
</head>

<body>
    <main class="main-content">
        <div class="chat-page">
            <aside class="chat-sidebar">
                <div class="sidebar-header">
                    <div class="sidebar-title">Messages</div>
                    <div class="search-box">
                        <input type="text" id="convSearch" placeholder="Search chats…" oninput="filterConvs(this.value)">
                    </div>
                </div>
                <div class="conv-list" id="convList">
                    <div class="conv-item active" id="adminConvItem" onclick="openAdminChat()">
                        <div class="conv-avatar"><?= $student_initial ?></div>
                        <div class="conv-info">
                            <div class="conv-name">Admin Department</div>
                            <div class="conv-preview" id="adminPreview">No messages yet</div>
                        </div>
                    </div>
                </div>
                <div id="clubChatsSection"></div>
            </aside>

            <section class="chat-area">
                <div class="chat-header">
                    <div class="chat-info">
                        <div class="chat-name" id="chatName">Admin Department</div>
                        <div class="chat-avatar" id="chatAvatar">AD</div>
                        <span class="club-header-badge" id="clubHeaderBadge" style="display: none;">
                            <span id="clubMemberCount">0 members</span>
                        </span>
                    </div>
                    <button class="file-req-btn" id="fileReqBtn" onclick="toggleFileDropdown()">
                        <i class="fas fa-paperclip"></i> Request File
                    </button>
                </div>

                <div class="chat-messages" id="chatMessages">
                    <div class="chat-loading">
                        <i class="fas fa-spinner fa-spin"></i>
                        Loading messages...
                    </div>
                </div>

                <div class="chat-input-box">
                    <div class="reason-bar" id="reasonBar">
                        <input type="text" id="reasonInput" placeholder="Why do you need this file?" onkeydown="if(event.key==='Enter'){event.preventDefault();sendMsg();}">
                        <button onclick="cancelFileRequest()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <input type="text" class="msg-input" id="msgInput" placeholder="Type your message…" onkeydown="if(event.key==='Enter' && !event.shiftKey){event.preventDefault();sendMsg();}">
                    <button class="send-btn" id="sendBtn" onclick="sendMsg()">
                        <i class="fas fa-paper-plane"></i>
                        <span>Send</span>
                    </button>
                </div>

                <div class="file-dropdown" id="fileDropdown">
                    <div class="file-dropdown-header">Request a File</div>
                    <div class="file-dropdown-body" id="fileDropdownBody">
                        <div class="file-dropdown-empty">
                            <i class="fas fa-folder-open"></i>
                            Loading files...
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <div id="toastZone"></div>

    <script>
        /* ════════ CONFIG ════════ */
        const API = '<?= htmlspecialchars($chatApi, ENT_QUOTES, "UTF-8") ?>';
        const FILE_REQ_API = '<?= htmlspecialchars($fileReqApi, ENT_QUOTES, "UTF-8") ?>';
        const CLUB_API = '<?= htmlspecialchars($clubChatApi, ENT_QUOTES, "UTF-8") ?>';
        const STUDENT_INIT = '<?= $student_initial ?>';

        let activeMode = 'admin';
        let activeChatId = null;
        let convId = null;
        let selectedFile = null;
        let pollTimer = null;

        /* ════════ GLOBAL FUNCTIONS ════════ */
        function filterConvs(q) {
            q = q.toLowerCase();
            document.querySelectorAll('.conv-item').forEach(el => {
                const name = el.querySelector('.conv-name')?.textContent?.toLowerCase() || '';
                el.style.display = (!q || name.includes(q)) ? '' : 'none';
            });
        }

        function getCSRFToken() {
            const metaTag = document.querySelector('meta[name="csrf-token"]');
            if (metaTag) {
                return metaTag.getAttribute('content');
            }
            console.warn('CSRF token not found in meta tag');
            return '';
        }

        function toast(msg, type = 'success') {
            const zone = document.getElementById('toastZone');
            const t = document.createElement('div');
            t.className = `toast ${type}`;
            t.innerHTML = `<i class="fas ${type==='success'?'fa-check-circle':'fa-exclamation-circle'}"></i><span>${msg}</span>`;
            zone.appendChild(t);
            requestAnimationFrame(() => t.classList.add('show'));
            setTimeout(() => {
                t.classList.remove('show');
                setTimeout(() => t.remove(), 320);
            }, 3500);
        }

        function openAdminChat() {
            activeMode = 'admin';
            activeChatId = convId;

            document.querySelectorAll('.conv-item').forEach(el => el.classList.remove('active'));
            document.getElementById('adminConvItem').classList.add('active');
            const chatNameEl = document.getElementById('chatName');
            if (chatNameEl) chatNameEl.textContent = 'Admin Department';
            const chatAvatarEl = document.getElementById('chatAvatar');
            if (chatAvatarEl) chatAvatarEl.textContent = 'AD';
            document.getElementById('clubHeaderBadge').style.display = 'none';
            document.getElementById('fileReqBtn').style.display = '';

            clearInterval(pollTimer);
            loadAdminMessages();
            pollTimer = setInterval(loadAdminMessages, 5000);
        }

        async function sendMsg() {
            try {
                const msgInput = document.getElementById('msgInput');
                const reasonInput = document.getElementById('reasonInput');
                const sendBtn = document.getElementById('sendBtn');

                if (selectedFile) {
                    const reason = reasonInput.value.trim();
                    if (!reason) {
                        reasonInput.focus();
                        toast('Please enter a reason for the file request.', 'error');
                        return;
                    }
                    sendBtn.disabled = true;
                    await sendFileRequest(selectedFile, reason);
                    sendBtn.disabled = false;
                    return;
                }

                const text = msgInput.value.trim();
                if (!text) return;
                sendBtn.disabled = true;
                msgInput.value = '';

                if (activeMode === 'admin') {
                    const fd = new FormData();
                    fd.append('action', 'send_message');
                    fd.append('message', text);
                    if (convId) fd.append('conversation_id', convId);
                    fd.append('csrf_token', getCSRFToken());
                    
                    const res = await fetch(API, {
                        method: 'POST',
                        body: fd
                    });
                    
                    if (!res.ok) {
                        throw new Error(`HTTP error! status: ${res.status}`);
                    }
                    
                    const data = await res.json();
                    sendBtn.disabled = false;
                    if (data.success) {
                        if (data.conv_id && !convId) convId = data.conv_id;
                        await loadAdminMessages();
                    } else {
                        msgInput.value = text;
                        toast(data.message || 'Could not send message. Please try again.', 'error');
                    }
                }
            } catch (error) {
                console.error('Send message error:', error);
                const sendBtn = document.getElementById('sendBtn');
                const msgInput = document.getElementById('msgInput');
                
                if (sendBtn) sendBtn.disabled = false;
                
                toast('Network error. Please check your connection and try again.', 'error');
            }
        }

        function cancelFileRequest() {
            selectedFile = null;
            document.getElementById('msgInput').value = '';
            document.getElementById('msgInput').disabled = false;
            document.getElementById('reasonInput').value = '';
            document.getElementById('reasonBar').classList.remove('show');
        }

        function toggleFileDropdown() {
            const dd = document.getElementById('fileDropdown');
            const btn = document.getElementById('fileReqBtn');
            dd.classList.toggle('open');
            btn.classList.toggle('active');
            if (dd.classList.contains('open')) {
                setTimeout(() => {
                    document.addEventListener('click', closeDropdownOutside, {
                        once: true
                    });
                }, 50);
            }
        }

        function closeDropdownOutside(e) {
            const dd = document.getElementById('fileDropdown');
            const btn = document.getElementById('fileReqBtn');
            if (!dd.contains(e.target) && !btn.contains(e.target)) {
                dd.classList.remove('open');
                btn.classList.remove('active');
            }
        }

        function selectFile(id, title, fileType) {
            selectedFile = {
                id,
                title,
                file_type: fileType
            };
            document.getElementById('fileDropdown').classList.remove('open');
            document.getElementById('fileReqBtn').classList.remove('active');
            const msgInput = document.getElementById('msgInput');
            msgInput.value = `📄 Requesting: ${title}`;
            msgInput.disabled = true;
            document.getElementById('reasonBar').classList.add('show');
            document.getElementById('reasonInput').focus();
            document.getElementById('fileReqBtn').style.color = 'var(--forest)';
        }

        function esc(s) {
            if (s == null) return '';
            return String(s)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;')
                .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        }

        function formatDateLabel(d) {
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

        function getFileIcon(ft) {
            if (!ft) return 'fa-file';
            if (ft.includes('pdf')) return 'fa-file-pdf';
            if (ft.includes('image')) return 'fa-file-image';
            if (ft.includes('word') || ft.includes('document')) return 'fa-file-word';
            if (ft.includes('sheet') || ft.includes('excel')) return 'fa-file-excel';
            return 'fa-file-alt';
        }

        function getFileIconClass(ft) {
            if (!ft) return 'other';
            if (ft.includes('pdf')) return 'pdf';
            if (ft.includes('image')) return 'img';
            if (ft.includes('word') || ft.includes('document')) return 'doc';
            if (ft.includes('sheet') || ft.includes('excel')) return 'xls';
            return 'other';
        }

        /* ════════ INIT ════════ */
        document.addEventListener('DOMContentLoaded', () => {
            const currentDateEl = document.getElementById('currentDate');
            if (currentDateEl) currentDateEl.textContent =
                new Date().toLocaleDateString('en-US', {
                    weekday: 'short',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });

            ensureAdminConv().then(() => {
                openAdminChat();
                loadClubChats();
                loadRestrictedFiles();
            });
        });

        /* ════════ ADMIN CHAT ════════ */
        async function ensureAdminConv() {
            try {
                const fd = new FormData();
                fd.append('action', 'get_student_conv');
                fd.append('csrf_token', getCSRFToken());
                
                const res = await fetch(API, {
                    method: 'POST',
                    body: fd
                });
                
                if (!res.ok) {
                    throw new Error(`HTTP error! status: ${res.status}`);
                }
                
                const data = await res.json();
                if (data.success && data.conv_id) convId = data.conv_id;
            } catch (error) {
                console.error('Ensure admin conversation error:', error);
            }
        }

        async function loadAdminMessages() {
            try {
                if (!convId) return;
                const fd = new FormData();
                fd.append('action', 'fetch_messages');
                fd.append('conversation_id', convId);
                fd.append('csrf_token', getCSRFToken());
                
                const res = await fetch(API, {
                    method: 'POST',
                    body: fd
                });
                
                if (!res.ok) {
                    throw new Error(`HTTP error! status: ${res.status}`);
                }
                
                const data = await res.json();
                if (!data.success) return;
                renderMessages(data.messages, 'admin');
                markAdminRead();
            } catch (error) {
                console.error('Load admin messages error:', error);
            }
        }

        function markAdminRead() {
            try {
                if (!convId) return;
                const fd = new FormData();
                fd.append('action', 'mark_read');
                fd.append('conversation_id', convId);
                fd.append('csrf_token', getCSRFToken());
                
                fetch(API, {
                    method: 'POST',
                    body: fd
                }).catch(error => {
                    console.error('Mark admin read error:', error);
                });
            } catch (error) {
                console.error('Mark admin read error:', error);
            }
        }

        /* ════════ CLUB CHATS ════════ */
        async function loadClubChats() {
            try {
                const fd = new FormData();
                fd.append('action', 'get_student_clubs');
                fd.append('csrf_token', getCSRFToken());
                
                const res = await fetch(CLUB_API, {
                    method: 'POST',
                    body: fd
                });
                
                if (!res.ok) {
                    throw new Error(`HTTP error! status: ${res.status}`);
                }
                
                const data = await res.json();
                if (!data.success || !data.clubs.length) return;

                const section = document.getElementById('clubChatsSection');
                section.innerHTML = `<div class="conv-section-label">Club Chats</div>` +
                    data.clubs.map(club => `
                    <div class="conv-item" id="club_${club.id}"
                         onclick="openClubChat(${club.id}, '${esc(club.name)}', ${club.member_count})">
                        <div class="conv-avatar club-av">${esc(club.name.charAt(0).toUpperCase())}</div>
                        <div class="conv-info">
                            <div class="conv-name">${esc(club.name)} <span class="club-tag">Club</span></div>
                            <div class="conv-preview">${esc(club.last_message || 'No messages yet')}</div>
                        </div>
                        <span class="conv-time">${esc(club.last_time || '')}</span>
                        ${club.unread > 0 ? `<span class="conv-unread">${club.unread}</span>` : ''}
                    </div>`).join('');
            } catch (error) {
                console.error('Load club chats error:', error);
            }
        }

        function openClubChat(clubId, clubName, memberCount) {
            activeMode = 'club';
            activeChatId = clubId;
            document.querySelectorAll('.conv-item').forEach(el => el.classList.remove('active'));
            const el = document.getElementById('club_' + clubId);
            if (el) el.classList.add('active');
            const chatNameEl = document.getElementById('chatName');
            if (chatNameEl) chatNameEl.textContent = clubName;
            const chatAvatarEl = document.getElementById('chatAvatar');
            if (chatAvatarEl) chatAvatarEl.textContent = clubName.charAt(0).toUpperCase();
            const clubHeaderBadgeEl = document.getElementById('clubHeaderBadge');
            if (clubHeaderBadgeEl) clubHeaderBadgeEl.style.display = '';
            const clubMemberCountEl = document.getElementById('clubMemberCount');
            if (clubMemberCountEl) clubMemberCountEl.textContent = memberCount + ' members';
            document.getElementById('fileReqBtn').style.display = 'none';
            cancelFileRequest();
            clearInterval(pollTimer);
            loadClubMessages(clubId);
            pollTimer = setInterval(() => loadClubMessages(clubId), 5000);
        }

        async function loadClubMessages(clubId) {
            try {
                const fd = new FormData();
                fd.append('action', 'fetch_club_messages');
                fd.append('club_id', clubId);
                fd.append('csrf_token', getCSRFToken());
                
                const res = await fetch(CLUB_API, {
                    method: 'POST',
                    body: fd
                });
                
                if (!res.ok) {
                    throw new Error(`HTTP error! status: ${res.status}`);
                }
                
                const data = await res.json();
                if (!data.success) return;
                renderMessages(data.messages, 'club');
            } catch (error) {
                console.error('Load club messages error:', error);
            }
        }

        /* ════════ RENDER MESSAGES ════════ */
        function renderMessages(msgs, mode) {
            const box = document.getElementById('chatMessages');
            const atBottom = box.scrollHeight - box.scrollTop <= box.clientHeight + 60;

            if (!msgs || !msgs.length) {
                box.innerHTML = `<div style="text-align:center;padding:48px;color:var(--muted);">
                    <i class="fas fa-comments" style="font-size:36px;opacity:.2;display:block;margin-bottom:10px;color:var(--forest);"></i>
                    No messages yet. Say hello!
                </div>`;
                return;
            }

            let lastDate = '';
            box.innerHTML = msgs.map(m => {
                const isMine = (mode === 'admin') ?
                    m.sender_role === 'student' :
                    m.sender_id == <?= (int)$student_id ?>;

                const init = isMine ? STUDENT_INIT : (m.avatar_letter || (mode === 'club' && m.sender_name ? m.sender_name.charAt(0).toUpperCase() : 'AD'));
                const msgDate = (m.created_at || '').substring(0, 10);
                let divider = '';
                if (msgDate && msgDate !== lastDate) {
                    lastDate = msgDate;
                    divider = `<div class="date-sep"><span>${formatDateLabel(msgDate)}</span></div>`;
                }

                return `${divider}
<div class="msg-row ${isMine ? 'sent' : ''}">
    <div class="msg-av">${esc(init)}</div>
    <div>
        ${mode === 'club' && !isMine ? `<div style="font-size:11px;color:var(--muted);margin-bottom:3px;padding-left:2px;">${esc(m.sender_name)}</div>` : ''}
        <div class="msg-bubble">
            <div class="msg-text">${esc(m.message)}</div>
        </div>
        <div class="msg-time"><i class="far fa-clock"></i>${esc(m.time_label)}</div>
    </div>
</div>`;
            }).join('');

            if (atBottom) box.scrollTop = box.scrollHeight;

            if (mode === 'admin' && msgs.length) {
                const last = msgs[msgs.length - 1];
                const adminPreviewEl = document.getElementById('adminPreview');
                if (adminPreviewEl) adminPreviewEl.textContent = (last.message || '').substring(0, 45);
            }
        }

        async function sendFileRequest(file, reason) {
            try {
                const fd = new FormData();
                fd.append('action', 'submit_file_request');
                fd.append('file_id', file.id);
                fd.append('reason', reason);
                fd.append('conversation_id', convId);
                fd.append('csrf_token', getCSRFToken());
                
                const res = await fetch(FILE_REQ_API, {
                    method: 'POST',
                    body: fd
                });
                
                if (!res.ok) {
                    throw new Error(`HTTP error! status: ${res.status}`);
                }
                
                const data = await res.json();
                if (data.success) {
                    cancelFileRequest();
                    toast('File request sent! Waiting for admin approval.', 'success');
                    await loadAdminMessages();
                } else {
                    toast(data.message || 'Could not send request.', 'error');
                }
            } catch (error) {
                console.error('Send file request error:', error);
                toast('Network error. Please check your connection and try again.', 'error');
            }
        }

        /* ════════ FILE DROPDOWN ════════ */
        async function loadRestrictedFiles() {
            try {
                const fd = new FormData();
                fd.append('action', 'get_restricted_files');
                fd.append('csrf_token', getCSRFToken());
                
                const res = await fetch(FILE_REQ_API, {
                    method: 'POST',
                    body: fd
                });
                
                if (!res.ok) {
                    throw new Error(`HTTP error! status: ${res.status}`);
                }
                
                const data = await res.json();
                const body = document.getElementById('fileDropdownBody');
                if (!data.success || !data.files.length) {
                    body.innerHTML = `<div class="file-dropdown-empty"><i class="fas fa-folder-open"></i>No restricted files available.</div>`;
                    return;
                }
                body.innerHTML = data.files.map(f => `
                <div class="file-dropdown-item" onclick="selectFile(${f.id}, '${esc(f.title)}', '${esc(f.file_type)}')">
                    <div class="file-dropdown-item-icon ${getFileIconClass(f.file_type)}">
                        <i class="fas ${getFileIcon(f.file_type)}"></i>
                    </div>
                    <div>
                        <div class="file-dropdown-item-name">${esc(f.title)}</div>
                        <div class="file-dropdown-item-cat"><i class="fas fa-tag"></i>${esc(f.category)}</div>
                    </div>
                </div>`).join('');
            } catch (error) {
                console.error('Load restricted files error:', error);
            }
        }
    </script>
</body>

</html>

<?php
function generateCSRFToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_expires'] = time() + 1800; // 30 minutes
    }
    
    return $_SESSION['csrf_token'] ?? '';
}
?>
