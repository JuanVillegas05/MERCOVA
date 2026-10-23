import { setToken, clearToken } from '../lib/api.js';
import { fetchCurrentUser } from '../lib/auth.js';
import { toast } from '../lib/toast.js';

export async function initSocialCallback() {
    const params = new URLSearchParams(window.location.search);
    const token = params.get('token');

    if (!token) {
        window.location.href = '/login';
        return;
    }

    setToken(token);

    const user = await fetchCurrentUser();

    if (!user) {
        clearToken();
        toast('No pudimos iniciar sesión. Intenta de nuevo.', 'error');
        window.location.href = '/login';
        return;
    }

    window.location.href = user.role === 'admin' ? '/admin' : '/panel';
}
