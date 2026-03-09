<x-filament-panels::page>
    <style>
        .chat-tester-container {
            width: 100%;
            height: 70vh;
            display: flex;
            flex-direction: column;
            background: #1f2937;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            border: 1px solid #374151;
        }
        .chat-tester-header {
            background: #111827;
            padding: 16px;
            text-align: center;
            border-bottom: 1px solid #374151;
        }
        .chat-tester-header h2 { font-size: 16px; color: #f3f4f6; font-weight: 600; }
        .chat-tester-header .subtitle { font-size: 12px; color: #9ca3af; margin-top: 4px; }
        .chat-tester-messages {
            flex: 1;
            overflow-y: auto;
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .chat-msg {
            max-width: 85%;
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 14px;
            line-height: 1.5;
            word-break: break-word;
        }
        .chat-msg.user {
            align-self: flex-start;
            align-self: flex-end;
            background: #3b82f6; 
            color: white;
            border-bottom-right-radius: 4px;
        }
        [dir="rtl"] .chat-msg.user {
            align-self: flex-start;
            border-bottom-left-radius: 4px;
            border-bottom-right-radius: 12px;
        }
        .chat-msg.bot {
            align-self: flex-start;
            background: #374151;
            color: #f3f4f6;
            border-bottom-left-radius: 4px;
        }
        [dir="rtl"] .chat-msg.bot {
            align-self: flex-end;
            border-bottom-right-radius: 4px;
            border-bottom-left-radius: 12px;
        }
        .chat-msg img { max-width: 100%; border-radius: 8px; margin-top: 8px; cursor: pointer; }
        .chat-msg video { max-width: 100%; border-radius: 8px; margin-top: 8px; }
        .chat-typing-indicator {
            align-self: flex-start;
            padding: 8px 16px;
            font-style: italic;
            color: #9ca3af;
            font-size: 13px;
            display: none;
        }
        [dir="rtl"] .chat-typing-indicator { align-self: flex-end; }
        .chat-input-area {
            display: flex;
            padding: 16px;
            background: #111827;
            border-top: 1px solid #374151;
            gap: 8px;
            align-items: center;
        }
        .chat-input-area input[type="text"] {
            flex: 1;
            padding: 12px 16px;
            border: 1px solid #374151;
            border-radius: 8px;
            background: #1f2937;
            color: #f3f4f6;
            font-size: 14px;
            outline: none;
        }
        .chat-input-area input[type="text"]:focus { border-color: #3b82f6; }
        .chat-input-area button {
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 12px 20px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .chat-input-area button:hover { background: #2563eb; }
        .chat-input-area button:disabled { background: #4b5563; cursor: not-allowed; }
        .upload-btn {
            background: #374151 !important;
            padding: 10px !important;
            border-radius: 50% !important;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .upload-btn:hover { background: #4b5563 !important; }
        .attachment-preview {
            display: none;
            padding: 8px 16px;
            background: #1f2937;
            border-top: 1px solid #374151;
            font-size: 12px;
            color: #10b981;
            align-items: center;
            justify-content: space-between;
        }
        .attachment-preview button {
            background: transparent;
            color: #ef4444;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }
    </style>

    <div class="chat-tester-container" dir="rtl">
        <div class="chat-tester-header">
            <h2>{{ $tenantName }} AI</h2>
            <div class="subtitle">Chat Simulator (Tenant ID: {{ $tenantId }})</div>
            <div class="subtitle" style="font-size: 10px; margin-top: 2px;">Session ID: <span id="chatSessionId">...</span></div>
        </div>

        <div class="chat-tester-messages" id="chatMessages">
            <div class="chat-msg bot">أهلاً بيك! أنا المساعد الذكي لصفحة {{ $tenantName }}، تحت أمرك في أي وقت! إزاي أقدر أساعدك؟</div>
        </div>

        <div class="chat-typing-indicator" id="chatTypingIndicator">المساعد يكتب الآن...</div>

        <div class="attachment-preview" id="attachmentPreview">
            <span id="attachmentFileName"></span>
            <button id="removeAttachmentBtn">&times;</button>
        </div>

        <div class="chat-input-area">
            <input type="file" id="mediaUpload" style="display: none;" accept="image/*,video/*">
            <button class="upload-btn" id="uploadTriggerBtn" title="Attach Image/Video">
                <x-heroicon-o-paper-clip class="w-5 h-5"/>
            </button>
            <input type="text" id="chatInput" placeholder="اكتب رسالتك لـ {{ $tenantName }}..." autocomplete="off">
            <button id="chatSendBtn">إرسال</button>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sessionId = 'test_' + Math.random().toString(36).substring(2, 10);
            document.getElementById('chatSessionId').innerText = sessionId;

            const tenantId = {{ $tenantId ?? 'null' }};
            const tenantName = "{{ $tenantName }}";
            const input = document.getElementById('chatInput');
            const sendBtn = document.getElementById('chatSendBtn');
            const messagesContainer = document.getElementById('chatMessages');
            const typingIndicator = document.getElementById('chatTypingIndicator');
            
            const mediaUpload = document.getElementById('mediaUpload');
            const uploadTriggerBtn = document.getElementById('uploadTriggerBtn');
            const attachmentPreview = document.getElementById('attachmentPreview');
            const attachmentFileName = document.getElementById('attachmentFileName');
            const removeAttachmentBtn = document.getElementById('removeAttachmentBtn');

            let pollInterval = null;
            let currentAttachmentUrl = null;
            let isGenerating = false;

            uploadTriggerBtn.addEventListener('click', () => mediaUpload.click());

            mediaUpload.addEventListener('change', function(e) {
                if (e.target.files && e.target.files[0]) {
                    const file = e.target.files[0];
                    attachmentFileName.innerText = '📎 ' + file.name;
                    attachmentPreview.style.display = 'flex';
                    
                    // In a real scenario we'd upload this to S3 and get a URL. 
                    // For the tester, we will just create a blob URL, but the backend requires a real URL for analysis.
                    // To simulate RAG vision natively, we'd need a publicly accessible URL, so we pass a placeholder for demonstration
                    // unless you have a local image upload API built. I will pass a dummy URL or base64.
                    
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        currentAttachmentUrl = e.target.result; // Base64 data URI
                    };
                    reader.readAsDataURL(file);
                }
            });

            removeAttachmentBtn.addEventListener('click', function() {
                mediaUpload.value = '';
                currentAttachmentUrl = null;
                attachmentPreview.style.display = 'none';
            });

            function addMessage(text, sender, isHtml = false) {
                const msgDiv = document.createElement('div');
                msgDiv.className = `chat-msg ${sender}`;
                
                if (isHtml) {
                    msgDiv.innerHTML = text; 
                } else {
                    const formattedText = text.replace(/\n/g, '<br>');
                    msgDiv.innerHTML = formattedText;
                }
                
                messagesContainer.appendChild(msgDiv);
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }

            async function sendMessage() {
                let text = input.value.trim();
                
                if (!text && !currentAttachmentUrl) return;

                // If there's an attachment, format it so the backend parser recognizes it
                if (currentAttachmentUrl) {
                    text = `[IMAGE_URLS: ${currentAttachmentUrl}] ` + text;
                }

                const displayMsg = input.value.trim() ? input.value.trim() : '📷 صورة مرفقة';

                input.value = '';
                input.disabled = true;
                sendBtn.disabled = true;
                uploadTriggerBtn.disabled = true;
                attachmentPreview.style.display = 'none';

                addMessage(displayMsg, 'user');
                if (currentAttachmentUrl) {
                     addMessage(`<img src="${currentAttachmentUrl}" style="max-height: 150px;">`, 'user', true);
                }
                typingIndicator.style.display = 'block';

                try {
                    isGenerating = true;
                    // START POLLING IMMEDIATELY for mid-turn RAG messages
                    startPolling();

                    const response = await fetch('/api/chat', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Tenant-ID': tenantId
                        },
                        body: JSON.stringify({
                            session_id: sessionId,
                            message: text
                        })
                    });

                    if (!response.ok) throw new Error('API Error');
                    
                    mediaUpload.value = '';
                    currentAttachmentUrl = null;
                } catch (error) {
                    console.error('Error sending message:', error);
                    addMessage(`⚠️ حدث خطأ في الاتصال بسيرفر ${tenantName}`, 'bot');
                } finally {
                    isGenerating = false;
                }
            }

            function startPolling() {
                if (pollInterval) clearInterval(pollInterval);
                
                pollInterval = setInterval(async () => {
                    try {
                        const response = await fetch(`/api/chat/poll?session_id=${sessionId}`, {
                            method: 'GET',
                            headers: {
                                'X-Tenant-ID': tenantId
                            }
                        });
                        
                        if (!response.ok) return;

                        const data = await response.json();
                        
                        if (data.messages && data.messages.length > 0) {
                            data.messages.forEach(msg => {
                                if (msg.type === 'text') {
                                    addMessage(msg.content, 'bot');
                                } else if (msg.type === 'image') {
                                    addMessage(`<img src="${msg.content}" alt="Image" onclick="window.open(this.src)">`, 'bot', true);
                                } else if (msg.type === 'video') {
                                    addMessage(`<video src="${msg.content}" controls></video>`, 'bot', true);
                                }
                            });
                        }
                        
                        if (!isGenerating) {
                            clearInterval(pollInterval);
                            resetInput();
                        }
                    } catch (error) {
                        console.error('Polling error:', error);
                    }
                }, 2000);
            }

            function resetInput() {
                typingIndicator.style.display = 'none';
                input.disabled = false;
                sendBtn.disabled = false;
                uploadTriggerBtn.disabled = false;
                input.focus();
            }

            sendBtn.addEventListener('click', sendMessage);
            input.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') sendMessage();
            });
        });
    </script>
</x-filament-panels::page>
