/**
 * Auth State Module - DriveNow
 * Single source of truth for navbar auth UI based on /api/session.php
 */

(function initAuthStateModule() {
    const SESSION_API = '/api/session.php';

    function normalizeRole(role) {
        const normalized = String(role || 'user').toLowerCase().replace(/[-_\s]/g, '');
        if (normalized === 'staff') return 'controlstaff';
        return normalized;
    }

    function removeByText(root, selector, texts) {
        root.querySelectorAll(selector).forEach(el => {
            const text = (el.textContent || '').trim().toLowerCase();
            if (texts.includes(text)) el.remove();
        });
    }

    function clearNavbarAuthControls(actions) {
        actions.querySelectorAll('.navbar-profile-link').forEach(el => el.remove());
        actions.querySelectorAll('a.navbar-action-link').forEach(el => el.remove());
        actions.querySelectorAll('button.navbar-notification').forEach(el => el.remove());
        actions.querySelectorAll('button.btn-danger.btn-sm[onclick="logout()"]').forEach(el => el.remove());
        removeByText(actions, 'button.btn.btn-outline.btn-sm', ['sign in']);
        removeByText(actions, 'button.btn.btn-primary.btn-sm', ['sign up']);
    }

    function insertBeforeMenu(actions, element) {
        const menuToggle = actions.querySelector('#sideMenuToggle');
        if (menuToggle) actions.insertBefore(element, menuToggle);
        else actions.appendChild(element);
    }

    function renderLoggedInNavbar(actions, user, currentPage) {
        const role = normalizeRole(user.role);

        const notifBtn = document.createElement('button');
        notifBtn.className = 'navbar-notification';
        notifBtn.id = 'notifBtn';
        notifBtn.setAttribute('onclick', 'toggleNotifications()');
        notifBtn.innerHTML = '🔔<span class="notification-badge" id="notifCount" style="display:none;">0</span>';

        const profile = document.createElement('a');
        profile.href = '/profile.php';
        profile.className = 'navbar-profile-link';
        profile.textContent = '👤 ' + (user.full_name || user.email || 'User');
        if (currentPage === 'profile') {
            profile.style.background = 'var(--primary-50)';
            profile.style.color = 'var(--primary)';
        }

        insertBeforeMenu(actions, notifBtn);
        insertBeforeMenu(actions, profile);

        if (role !== 'admin' && role !== 'controlstaff' && role !== 'callcenterstaff' && role !== 'driver') {
            const ordersLink = document.createElement('a');
            ordersLink.href = '/orders.php';
            ordersLink.className = 'btn btn-outline btn-sm navbar-action-link navbar-my-orders';
            ordersLink.textContent = '📋 My Orders';
            if (currentPage === 'orders') {
                ordersLink.classList.add('active');
            }
            insertBeforeMenu(actions, ordersLink);
        }

        const logoutBtn = document.createElement('button');
        logoutBtn.className = 'btn btn-danger btn-sm';
        logoutBtn.textContent = 'Logout';
        logoutBtn.setAttribute('onclick', 'logout()');
        insertBeforeMenu(actions, logoutBtn);
    }

    function renderLoggedOutNavbar(actions) {
        const signInBtn = document.createElement('button');
        signInBtn.className = 'btn btn-outline btn-sm';
        signInBtn.textContent = 'Sign In';
        signInBtn.setAttribute('onclick', "showAuthModal('login'); return false;");

        const signUpBtn = document.createElement('button');
        signUpBtn.className = 'btn btn-primary btn-sm';
        signUpBtn.textContent = 'Sign Up';
        signUpBtn.setAttribute('onclick', "showAuthModal('register'); return false;");

        insertBeforeMenu(actions, signInBtn);
        insertBeforeMenu(actions, signUpBtn);
    }

    async function fetchSessionState() {
        const response = await fetch(SESSION_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ action: 'check-session' })
        });

        const data = await response.json();
        if (!data || data.success !== true) {
            return { logged_in: false, user: null };
        }

        return {
            logged_in: !!data.logged_in,
            user: data.user || null
        };
    }

    async function syncNavbarAuthState(serverLoggedIn = false, currentPage = '') {
        const actions = document.querySelector('.navbar-actions');
        if (!actions) return;

        clearNavbarAuthControls(actions);

        let sessionState = { logged_in: !!serverLoggedIn, user: null };
        try {
            sessionState = await fetchSessionState();
        } catch (e) {
            // Keep server-rendered fallback if network/session API fails.
        }

        if (sessionState.logged_in && sessionState.user) {
            renderLoggedInNavbar(actions, sessionState.user, currentPage);
            if (typeof initNotifications === 'function' && !window.__notificationsInitialized) {
                window.__notificationsInitialized = true;
                initNotifications(true);
            }
        } else {
            renderLoggedOutNavbar(actions);
        }
    }

    window.initAuthStateSync = syncNavbarAuthState;
})();
