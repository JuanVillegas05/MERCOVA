import { apiFetch, getToken, setToken, clearToken } from './api.js';

const ROLE_LABELS = {
    customer: 'Cliente',
    seller: 'Vendedor',
    admin: 'Administrador',
};

let currentUser = null;

export function roleLabel(role) {
    return ROLE_LABELS[role] || role;
}

export async function fetchCurrentUser() {
    if (!getToken()) {
        currentUser = null;
        return null;
    }

    try {
        const { user } = await apiFetch('/me');
        currentUser = user;
        return user;
    } catch (error) {
        clearToken();
        currentUser = null;
        return null;
    }
}

export function getCurrentUser() {
    return currentUser;
}

export async function login(email, password) {
    const data = await apiFetch('/login', {
        method: 'POST',
        body: JSON.stringify({ email, password }),
    });
    setToken(data.token);
    currentUser = data.user;
    return data.user;
}

export async function register(payload) {
    const data = await apiFetch('/register', {
        method: 'POST',
        body: JSON.stringify(payload),
    });
    setToken(data.token);
    currentUser = data.user;
    return data.user;
}

export async function logout() {
    try {
        await apiFetch('/logout', { method: 'POST' });
    } catch (error) {
        // ignore network/session errors during logout
    }
    clearToken();
    currentUser = null;
}

export async function renderNav() {
    const user = await fetchCurrentUser();
    const guestEls = document.querySelectorAll('[data-auth="guest"]');
    const userEls = document.querySelectorAll('[data-auth="user"]');
    const nameEls = document.querySelectorAll('[data-user-name]');
    const roleEls = document.querySelectorAll('[data-user-role]');

    if (user) {
        guestEls.forEach((el) => el.classList.add('hidden'));
        userEls.forEach((el) => {
            el.classList.remove('hidden');
            el.classList.add('flex');
        });
        nameEls.forEach((el) => { el.textContent = user.name; });
        roleEls.forEach((el) => { el.textContent = roleLabel(user.role); });
        document.querySelectorAll('[data-panel-link]').forEach((el) => {
            el.setAttribute('href', user.role === 'admin' ? '/admin' : '/panel');
        });
    } else {
        guestEls.forEach((el) => el.classList.remove('hidden'));
        userEls.forEach((el) => {
            el.classList.add('hidden');
            el.classList.remove('flex');
        });
    }

    return user;
}

export function bindLogout() {
    document.querySelectorAll('[data-action="logout"]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            await logout();
            window.location.href = '/';
        });
    });
}

export function requireAuth() {
    if (!getToken()) {
        window.location.href = `/login?next=${encodeURIComponent(window.location.pathname)}`;
        return false;
    }
    return true;
}

export async function requireAdmin() {
    if (!requireAuth()) return null;

    const user = await fetchCurrentUser();
    if (!user || user.role !== 'admin') {
        window.location.href = '/login';
        return null;
    }
    return user;
}
