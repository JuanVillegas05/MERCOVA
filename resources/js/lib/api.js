const TOKEN_KEY = 'mercova_token';

export function getToken() {
    return localStorage.getItem(TOKEN_KEY);
}

export function setToken(token) {
    localStorage.setItem(TOKEN_KEY, token);
}

export function clearToken() {
    localStorage.removeItem(TOKEN_KEY);
}

export async function apiFetch(path, options = {}) {
    const token = getToken();
    const isFormData = options.body instanceof FormData;

    const headers = {
        Accept: 'application/json',
        ...(isFormData ? {} : { 'Content-Type': 'application/json' }),
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
        ...(options.headers || {}),
    };

    const response = await fetch(`/api${path}`, { ...options, headers });

    let data = null;
    try {
        data = await response.json();
    } catch (error) {
        data = null;
    }

    if (!response.ok) {
        const message = data?.message
            || (data?.errors ? Object.values(data.errors).flat()[0] : null)
            || 'Ocurrió un error inesperado.';
        const err = new Error(message);
        err.status = response.status;
        err.errors = data?.errors || null;
        throw err;
    }

    return data;
}
