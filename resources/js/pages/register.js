import { register } from '../lib/auth.js';
import { toast } from '../lib/toast.js';

export function initRegister() {
    const form = document.getElementById('register-form');
    if (!form) return;

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Creando cuenta...';

        try {
            const formData = new FormData(form);
            const role = formData.get('role') || 'customer';

            await register({
                name: formData.get('name'),
                email: formData.get('email'),
                password: formData.get('password'),
                password_confirmation: formData.get('password_confirmation'),
                phone: formData.get('phone') || null,
                role,
            });

            toast('Cuenta creada con éxito. ¡Bienvenido a Mercova!', 'success');
            window.location.href = role === 'seller' ? '/panel' : '/marketplace';
        } catch (error) {
            toast(error.message, 'error');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Crear cuenta';
        }
    });
}
