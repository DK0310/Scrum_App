/**
 * Chatbot Module - DriveNow
 * Handles: AI chatbot with memory, N8N webhook integration
 */

// Configuration (will be set from footer.html.php)
let N8N_WEBHOOK_URL = '';
const CHATBOT_API_URL = '/api/chatbot-with-memory.php';

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

function addChatMessage(text, isUser = false) {
    const messagesDiv = document.getElementById('chatMessages');
    const typing = document.getElementById('typingIndicator');
    
    const msgDiv = document.createElement('div');
    msgDiv.className = 'chat-message ' + (isUser ? 'user' : 'bot');
    msgDiv.innerHTML = '<div class="chat-bubble">' + text + '</div>';
    
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
                message: message,
                chatInput: message,
                timestamp: new Date().toISOString()
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
