# Historial de cambios

Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/). Fechas en formato AAAA-MM-DD.

## 2026-11-12

### Agregado
- Letrero de bienvenida al registrarse, también con Google y Apple (`is_new_user` en `/api/auth/social/exchange`).
- Documentación técnica en `docs/`, decisiones técnicas, guía de contribución y política de seguridad.

### Corregido
- El correo de bienvenida no llegaba en producción: Render bloquea SMTP. Ahora se usa `MAIL_MAILER=brevo`.
- Las imágenes subidas por los vendedores se perdían en cada despliegue. En producción solo se aceptan si Cloudinary está configurado.
- `render.yaml`: variables de Wompi en lugar de las de Mercado Pago y arranque con `mercova-web` para respetar el puerto de Render.

## 2026-11-11

### Agregado
- Imágenes en Cloudinary y recuperación de contraseña desde el frontend.
- Tareas programadas mediante un cron externo (GitHub Actions → `/api/cron/run`).
- Envío de correo por la API HTTP de Brevo.
- Despliegue con Docker en Render y conexión a TiDB con TLS verificado.

### Cambiado
- Pasarela de pagos: Mercado Pago reemplazado por Wompi Web Checkout.
- Checkout con los ítems en el cuerpo de la petición, método de pago opcional y mensajes de validación en español.

### Seguridad
- Los endpoints públicos ya no exponen correo ni teléfono de los usuarios.

## 2026-11-10

### Agregado
- Eliminación de cuentas desde el panel de administración (borrado lógico y anonimización).

### Seguridad
- API solo por token, sin sesión, y respuestas con `Cache-Control: no-store`.
- Revocación de tokens al desactivar un usuario.
- No se puede activar un plan de pago en un emprendimiento sin pagarlo.
- Integridad del marketplace: slugs únicos, reservas sin cruces y borrados seguros.
- Configuración para producción detrás del balanceador de Render.

## 2026-11-05

### Agregado
- Planes de suscripción con cupos mensuales y exportación de métricas del administrador a PDF y Excel.
- Correo de bienvenida transaccional.

## 2026-10-22

### Agregado
- Versión inicial: marketplace con catálogo, carrito, alquileres, reservas, emprendimientos, comisiones, reseñas, favoritos, notificaciones y panel de administración.

## Límites conocidos

- El límite de publicaciones activas del emprendimiento (`max_listings`) solo se comprueba al publicar a su nombre. Una publicación personal (sin `business_id`) sí consume el cupo mensual del plan, pero no cuenta para ese límite.
- Cada producto de alquiler es una sola unidad; no hay stock para alquileres.
- Los alquileres solo tienen precio por día, no por semana ni por mes.
- Las reseñas y los favoritos solo están disponibles para productos, no para servicios.
- No existe mensajería entre comprador y vendedor.
- Los productos destacados (`is_featured`) no tienen pantalla de gestión en `/admin`.
- Los planes no se renuevan solos: cada periodo es un pago independiente.
