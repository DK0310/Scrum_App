/**
 * Chatbot Module - DriveNow
 * Handles: AI chatbot with memory, N8N webhook integration
 */

// Configuration (will be set from footer.html.php)
let N8N_WEBHOOK_URL = '';
const CHATBOT_API_URL = '/api/chatbot-with-memory.php';

/**
 * Generate a stable anonymous session UUID, stored in localStorage.
 * For logged-in users the server overrides this with user_{userId}.
 * For anonymous users this ensures each browser tab/device has its own history.
 */
function getChatSessionId() {
    const STORAGE_KEY = 'privatehire_chat_uuid';
    let uuid = localStorage.getItem(STORAGE_KEY);
    if (!uuid) {
        // Generate a random UUID-like string
        uuid = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            const r = (Math.random() * 16) | 0;
            const v = c === 'x' ? r : (r & 0x3) | 0x8;
            return v.toString(16);
        });
        localStorage.setItem(STORAGE_KEY, uuid);
    }
    return uuid;
}

const CHAT_CLIENT_SESSION_ID = getChatSessionId();

function initChatbot(webhookUrl) {
    N8N_WEBHOOK_URL = webhookUrl;
}

function toggleChatbot() {
    const win = document.getElementById('chatbotWindow');
    win.classList.toggle('open');
    document.getElementById('chatbotToggle').textContent = win.classList.contains('open') ? '✕' : '💬';
}

function openChatbot() {
    const win = document.getElementById('chatbotWindow');
    if (!win.classList.contains('open')) toggleChatbot();
}

/**
 * Lightweight Markdown → HTML renderer
 * Handles: **bold**, *italic*, `code`, bullet lists, numbered lists, links, line breaks
 */
function renderMarkdown(text) {
    if (!text) return '';

    // Escape HTML special chars first (prevent XSS)
    let html = text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');

    // Code blocks (```...```) — handle before inline code
    html = html.replace(/```[\w]*\n?([\s\S]*?)```/g, '<pre><code>$1</code></pre>');

    // Inline code `...`
    html = html.replace(/`([^`]+)`/g, '<code>$1</code>');

    // Bold **text** or __text__
    html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
    html = html.replace(/__(.+?)__/g, '<strong>$1</strong>');

    // Italic *text* or _text_ (after bold so ** is handled first)
    html = html.replace(/\*([^*\n]+?)\*/g, '<em>$1</em>');
    html = html.replace(/_([^_\n]+?)_/g, '<em>$1</em>');

    // Links [text](url)
    html = html.replace(/\[([^\]]+)\]\((https?:\/\/[^\)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');

    // Process line by line for lists and paragraphs
    const lines = html.split('\n');
    const result = [];
    let inUl = false;
    let inOl = false;

    for (let i = 0; i < lines.length; i++) {
        const line = lines[i];

        // Unordered list: lines starting with * or - or •
        if (/^[\*\-•]\s+(.+)/.test(line)) {
            if (!inUl) { result.push('<ul>'); inUl = true; }
            if (inOl)  { result.push('</ol>'); inOl = false; }
            result.push('<li>' + line.replace(/^[\*\-•]\s+/, '') + '</li>');
            continue;
        }

        // Ordered list: lines starting with 1. 2. etc.
        if (/^\d+\.\s+(.+)/.test(line)) {
            if (!inOl) { result.push('<ol>'); inOl = true; }
            if (inUl)  { result.push('</ul>'); inUl = false; }
            result.push('<li>' + line.replace(/^\d+\.\s+/, '') + '</li>');
            continue;
        }

        // Close open lists when non-list line is encountered
        if (inUl) { result.push('</ul>'); inUl = false; }
        if (inOl) { result.push('</ol>'); inOl = false; }

        // Headings ### ## #
        if (/^###\s+(.+)/.test(line)) {
            result.push('<strong style="display:block;margin-top:8px">' + line.replace(/^###\s+/, '') + '</strong>');
            continue;
        }
        if (/^##\s+(.+)/.test(line)) {
            result.push('<strong style="display:block;margin-top:10px;font-size:1.05em">' + line.replace(/^##\s+/, '') + '</strong>');
            continue;
        }

        // Horizontal rule ---
        if (/^---+$/.test(line.trim())) {
            result.push('<hr style="border:none;border-top:1px solid rgba(255,255,255,0.15);margin:8px 0">');
            continue;
        }

        // Empty line → paragraph break
        if (line.trim() === '') {
            result.push('<div style="height:6px"></div>');
            continue;
        }

        // Regular line
        result.push('<span style="display:block">' + line + '</span>');
    }

    // Close any remaining open list
    if (inUl) result.push('</ul>');
    if (inOl) result.push('</ol>');

    return result.join('');
}

function addChatMessage(text, isUser = false) {
    const messagesDiv = document.getElementById('chatMessages');
    const typing = document.getElementById('typingIndicator');

    const msgDiv = document.createElement('div');
    msgDiv.className = 'chat-message ' + (isUser ? 'user' : 'bot');

    const bubble = document.createElement('div');
    bubble.className = 'chat-bubble';

    if (isUser) {
        // User messages: plain text only
        bubble.textContent = text;
    } else {
        // Bot messages: render markdown
        bubble.innerHTML = renderMarkdown(text);
    }

    msgDiv.appendChild(bubble);
    messagesDiv.insertBefore(msgDiv, typing);
    messagesDiv.scrollTop = messagesDiv.scrollHeight;
}

function showChatTyping(show) {
    document.getElementById('typingIndicator').classList.toggle('active', show);
    if (show) {
        document.getElementById('chatMessages').scrollTop = document.getElementById('chatMessages').scrollHeight;
    }
}

async function sendChatMessage() {
    const input = document.getElementById('chatInput');
    const message = input.value.trim();
    if (!message) return;

    addChatMessage(message, true);
    input.value = '';
    showChatTyping(true);

    try {
        const response = await fetch(CHATBOT_API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                message:         message,
                chatInput:       message,
                clientSessionId: CHAT_CLIENT_SESSION_ID,   // stable localStorage UUID
                timestamp:       new Date().toISOString()
            })
        });

        const responseText = await response.text();
        showChatTyping(false);

        if (!responseText || responseText.trim() === '') {
            addChatMessage('✅ Message received! Processing your request...');
            return;
        }

        try {
            const data = JSON.parse(responseText);
            if (data.success) {
                addChatMessage(data.response);
            } else {
                addChatMessage('❌ ' + (data.message || 'Something went wrong. Please try again.'));
            }
        } catch (e) {
            // n8n might return plain text
            addChatMessage(responseText);
        }
    } catch (error) {
        showChatTyping(false);
        addChatMessage('⚠️ Connection error. Please check if the server is running.');
        console.error('Chat error:', error);
    }
}
