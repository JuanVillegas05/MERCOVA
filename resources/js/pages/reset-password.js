import { apiFetch } from '../lib/api.js';
import { toast } from '../lib/toast.js';

export function initResetPassword() {
    const form = document.getElementById('reset-password-form');
    if (!form) return;

    const params = new URLSearchParams(window.location.search);
    const tokenInput = form.querySelector('[name="token"]');
    const emailInput = form.querySelector('[name="email"]');
    if (tokenInput) tokenInput.value = params.get('token') || '';
    if (emailInput) emailInput.value = params.get('email') || '';

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Guardando...';

        try {
            const formData = new FormData(form);
            await apiFetch('/reset-password', {
                method: 'POST',
                body: JSON.stringify({
                    token: formData.get('token'),
                    email: formData.get('email'),
                    password: formData.get('password'),
                    password_confirmation: formData.get('password_confirmation'),
                }),
            });
            toast('Contraseña restablecida. Ya puedes iniciar sesión.', 'success');
            window.location.href = '/login';
        } catch (error) {
            toast(error.message, 'error');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Restablecer contraseña';
        }
    });
}
