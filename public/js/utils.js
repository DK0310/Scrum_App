/**
 * Utilities Module - DriveNow
 * Shared utility functions: toasts, modals, language, auth
 */

// ===== TOAST NOTIFICATIONS =====
function showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    const icons = { success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️' };
    
    const toast = document.createElement('div');
    toast.className = 'toast toast-' + type;
    toast.innerHTML = `
        <span class="toast-icon">${icons[type] || 'ℹ️'}</span>
        <span class="toast-message">${message}</span>
        <button class="toast-close" onclick="this.parentElement.remove()">✕</button>
    `;
    
    container.appendChild(toast);
    setTimeout(() => toast.remove(), 5000);
}

// ===== MODALS =====
function closeModal(id) {
    document.getElementById(id).classList.remove('open');
}

document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) overlay.classList.remove('open');
    });
});

// ===== LANGUAGE =====
const languages = ['EN', 'VI', 'FR', 'DE', 'ES', 'JP', 'KR', 'ZH'];
let currentLangIndex = 0;

function toggleLanguageMenu() {
    currentLangIndex = (currentLangIndex + 1) % languages.length;
    document.getElementById('langBtn').textContent = '🌐 ' + languages[currentLangIndex];
    showToast('Language switched to ' + languages[currentLangIndex], 'info');
}

// ===== AUTH =====
async function logout() {
    try {
        await fetch('/api/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'logout' })
        });
        window.location.reload();
    } catch (error) {
        console.error('Logout error:', error);
    }
}
