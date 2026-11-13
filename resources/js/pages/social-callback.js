import { apiFetch, setToken, clearToken } from '../lib/api.js';
import { fetchCurrentUser } from '../lib/auth.js';
import { toast } from '../lib/toast.js';
import { queueWelcome } from '../lib/welcome.js';

export async function initSocialCallback() {
    const params = new URLSearchParams(window.location.search);
    const code = params.get('code');

    if (!code) {
        window.location.href = '/login?social_error=1';
        return;
    }

    // Quita el código de la barra de direcciones/historial antes de canjearlo.
    window.history.replaceState(null, '', window.location.pathname);

    let isNewUser = false;

    try {
        const data = await apiFetch('/auth/social/exchange', {
            method: 'POST',
            body: JSON.stringify({ code }),
        });
        setToken(data.token);
        isNewUser = Boolean(data.is_new_user);
    } catch (error) {
        clearToken();
        window.location.href = '/login?social_error=1';
        return;
    }

    const user = await fetchCurrentUser();

    if (!user) {
        clearToken();
        toast('No pudimos iniciar sesión. Intenta de nuevo.', 'error');
        window.location.href = '/login?social_error=1';
        return;
    }

    if (isNewUser && user.role !== 'admin') {
        queueWelcome(user.name, user.role);
    }

    window.location.href = user.role === 'admin' ? '/admin' : '/panel';
}
