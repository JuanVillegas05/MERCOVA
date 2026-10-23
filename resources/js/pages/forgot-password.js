import { apiFetch } from '../lib/api.js';
import { toast } from '../lib/toast.js';

export function initForgotPassword() {
    const form = document.getElementById('forgot-password-form');
    if (!form) return;

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Enviando...';

        try {
            const formData = new FormData(form);
            await apiFetch('/forgot-password', {
                method: 'POST',
                body: JSON.stringify({ email: formData.get('email') }),
            });
            toast('Si el correo existe, te enviamos un enlace de recuperación.', 'success');
            form.reset();
        } catch (error) {
            toast(error.message, 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Enviar enlace';
        }
    });
}
