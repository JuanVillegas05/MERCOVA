import { login } from '../lib/auth.js';
import { toast } from '../lib/toast.js';

export function initLogin() {
    const form = document.getElementById('login-form');
    if (!form) return;

    if (new URLSearchParams(window.location.search).get('social_error')) {
        toast('No pudimos iniciar sesión con ese proveedor. Intenta de nuevo o usa tu correo.', 'error');
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Ingresando...';

        try {
            const formData = new FormData(form);
            const user = await login(formData.get('email'), formData.get('password'));
            toast('Inicio de sesión correcto.', 'success');

            if (user.role === 'admin') {
                window.location.href = '/admin';
                return;
            }

            const params = new URLSearchParams(window.location.search);
            window.location.href = params.get('next') || '/panel';
        } catch (error) {
            toast(error.message, 'error');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Iniciar sesión';
        }
    });
}
