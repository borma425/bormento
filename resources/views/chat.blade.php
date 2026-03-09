<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Gravoni Store Agent - Chat</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #0a0a0a;
            color: #e0e0e0;
            height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .chat-container {
            width: 100%;
            max-width: 600px;
            height: 90vh;
            display: flex;
            flex-direction: column;
            background: #1a1a2e;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 0 40px rgba(100, 100, 255, 0.1);
        }
        .chat-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 16px 20px;
            text-align: center;
        }
        .chat-header h1 { font-size: 18px; color: white; }
        .chat-header .user-id { font-size: 12px; color: rgba(255,255,255,0.7); margin-top: 4px; }
        .messages {
            flex: 1;
            overflow-y: auto;
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .message {
            max-width: 80%;
            padding: 10px 16px;
            border-radius: 16px;
            font-size: 14px;
            line-height: 1.5;
            word-break: break-word;
        }
        .message.user {
            align-self: flex-end;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-bottom-left-radius: 4px;
        }
        .message.bot {
            align-self: flex-start;
            background: #2a2a4a;
            color: #e0e0e0;
            border-bottom-right-radius: 4px;
        }
        .message img {
            max-width: 100%;
            border-radius: 8px;
            margin-top: 8px;
            cursor: pointer;
        }
        .message video {
            max-width: 100%;
            border-radius: 8px;
            margin-top: 8px;
        }
        .typing-indicator {
            align-self: flex-start;
            padding: 8px 16px;
            font-style: italic;
            color: #888;
            font-size: 13px;
            display: none;
        }
        .input-area {
            display: flex;
            padding: 12px;
            background: #16213e;
            gap: 8px;
        }
        .input-area input {
            flex: 1;
            padding: 12px 16px;
            border: none;
            border-radius: 24px;
            background: #2a2a4a;
            color: white;
            font-size: 14px;
            outline: none;
        }
        .input-area input::placeholder { color: #888; }
        .input-area button {
            padding: 12px 24px;
            border: none;
            border-radius: 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-size: 14px;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        .input-area button:hover { opacity: 0.9; }
        .input-area button:disabled { opacity: 0.5; cursor: not-allowed; }
    </style>
</head>
<body>
    <div class="chat-container">
        <div class="chat-header">
            <h1 id="storeTitle">مرحباً بك في النظام</h1>
            <select id="tenantSelect" style="margin-top: 8px; padding: 4px; border-radius: 4px; background: #2a2a4a; color: white; border: 1px solid #667eea;">
                <option value="1">Gravoni Store (Clothing)</option>
                <option value="2">TechWave Store (Electronics)</option>
            </select>
            <div class="user-id" id="userIdDisplay"></div>
        </div>
        <div class="messages" id="messages">
            <div class="typing-indicator" id="typing">يكتب...</div>
        </div>
        <div class="input-area">
            <input type="text" id="messageInput" placeholder="اكتب رسالتك هنا..." autofocus />
            <button id="sendBtn">إرسال</button>
        </div>
    </div>

    <script>
        const sessionId = Math.random().toString(36).substring(2, 8);
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

        document.getElementById('userIdDisplay').textContent = 'Session: ' + sessionId;

        const messagesDiv = document.getElementById('messages');
        const typing = document.getElementById('typing');
        const input = document.getElementById('messageInput');
        const sendBtn = document.getElementById('sendBtn');

        function addMessage(content, type) {
            const div = document.createElement('div');
            div.classList.add('message', type);
            div.innerHTML = content;
            messagesDiv.insertBefore(div, typing);
            messagesDiv.scrollTop = messagesDiv.scrollHeight;
        }

        function showTyping(show) {
            typing.style.display = show ? 'block' : 'none';
            messagesDiv.scrollTop = messagesDiv.scrollHeight;
        }

        async function sendMessage() {
            const message = input.value.trim();
            const tenantId = document.getElementById('tenantSelect').value;
            
            if (!message) return;

            addMessage(message, 'user');
            input.value = '';
            sendBtn.disabled = true;
            showTyping(true);

            try {
                const res = await fetch('/api/chat', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Tenant-ID': tenantId
                    },
                    body: JSON.stringify({ session_id: sessionId, message }),
                });

                const data = await res.json();

                // Main text response from the AI
                if (data.response && data.response.trim()) {
                    addMessage(data.response, 'bot');
                }

                // Immediately poll for any media (images, videos) queued by tools
                await pollMessages();
            } catch (err) {
                addMessage('⚠️ ' + err.message, 'bot');
            }

            showTyping(false);
            sendBtn.disabled = false;
            input.focus();
        }

        async function pollMessages() {
            const tenantId = document.getElementById('tenantSelect').value;
            try {
                const res = await fetch(`/api/chat/poll?session_id=${encodeURIComponent(sessionId)}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Tenant-ID': tenantId
                    }
                });
                const data = await res.json();

                for (const msg of (data.messages || [])) {
                    if (msg.type === 'text') {
                        addMessage(msg.content, 'bot');
                    } else if (msg.type === 'image') {
                        addMessage(`<img src="${msg.content}" alt="Product" loading="lazy" />`, 'bot');
                    } else if (msg.type === 'video') {
                        addMessage(`<video src="${msg.content}" controls preload="metadata"></video>`, 'bot');
                    }
                }
            } catch (err) {
                // Silent
            }
        }

        sendBtn.addEventListener('click', sendMessage);
        input.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') sendMessage();
        });

        document.getElementById('tenantSelect').addEventListener('change', (e) => {
            const title = e.target.options[e.target.selectedIndex].text;
            document.getElementById('storeTitle').textContent = '🛍️ ' + title.split('(')[0].trim();
            // Clear messages when switching stores
            document.querySelectorAll('.message').forEach(el => el.remove());
        });

        // Initialize title
        document.getElementById('storeTitle').textContent = '🛍️ Gravoni Store';

        // Poll every 2 seconds for any async media messages
        setInterval(pollMessages, 2000);
    </script>
</body>
</html>
