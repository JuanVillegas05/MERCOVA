# API

Base: `/api`. Todas las respuestas son JSON y llevan `Cache-Control: no-store`. La lista completa y actualizada se obtiene con `php artisan route:list --path=api`.

## Autenticación

1. `POST /api/register` o `POST /api/login` devuelven `token`, `user` y `subscription`.
2. Las rutas protegidas se llaman con la cabecera `Authorization: Bearer <token>`.
3. `POST /api/logout` revoca el token actual.

Login social: `GET /auth/{google|apple}/redirect` lleva al proveedor. Al volver, el callback redirige al frontend con `?code=` (un código de un solo uso válido por 2 minutos, nunca el token). El frontend lo canjea en `POST /api/auth/social/exchange`, que responde como `/login` y además incluye `is_new_user`. `GET /api/auth/providers` indica qué botones mostrar.

## Límites de peticiones

| Rutas | Límite |
|---|---|
| Registro, login, recuperación de contraseña, canje social | 10 por minuto por IP |
| `POST /payments`, `POST /subscriptions/checkout` | 10 por minuto |
| `POST /webhooks/wompi` | 120 por minuto |
| `POST /cron/run` | 6 por minuto |

## Errores

| Código | Significado | Cuerpo |
|---|---|---|
| `401` | Falta el token o no es válido | `{ "message": "..." }` |
| `403` | Sin permiso (rol, propiedad o cuenta desactivada) | `{ "message": "..." }` |
| `404` | Recurso inexistente | `{ "message": "..." }` |
| `422` | Validación o regla de negocio (stock, fechas, cupos) | `{ "message": "...", "errors": { "campo": ["..."] } }` |
| `429` | Límite de peticiones superado | `{ "message": "..." }` |
| `503` | Integración externa no disponible (Wompi, Cloudinary) | `{ "message": "..." }` |

Los mensajes están en español y se pueden mostrar directamente al usuario.

## Rutas públicas

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/health` | Estado del servicio |
| GET | `/categories` | Categorías y subcategorías |
| GET | `/products`, `/products/{slug}` | Catálogo y detalle; incluye `is_favorited` si se envía token |
| POST | `/products/commission-preview` | Desglose de comisión a partir de `price` o `desired_net_price` |
| GET | `/products/{id}/availability` | Disponibilidad de alquiler (`start_date`, `end_date`) |
| GET | `/products/{id}/reviews` | Reseñas del producto |
| GET | `/services`, `/services/{slug}` | Servicios |
| GET | `/businesses`, `/businesses/{slug}` | Emprendimientos |
| GET | `/membership-plans` | Planes disponibles |
| GET | `/payment-methods`, `/payments/config` | Métodos de pago y llave pública de Wompi |
| POST | `/webhooks/wompi` | Eventos de Wompi (firmados, ver [Pagos](pagos.md)) |
| POST | `/cron/run` | Tareas programadas (requiere `MERCOVA_CRON_SECRET`) |

## Rutas autenticadas

| Método | Ruta | Descripción |
|---|---|---|
| GET / PUT | `/me` | Perfil |
| PUT | `/me/password` | Cambio de contraseña |
| GET / PUT | `/me/subscription` | Plan actual y cambio de plan |
| GET | `/subscriptions/checkout/preview` | Cotización de un plan de pago |
| POST | `/subscriptions/checkout` | Crea el intento de pago de un plan |
| GET | `/my-products` | Publicaciones propias |
| POST / PUT / DELETE | `/products`, `/services` | Publicar, editar y eliminar (publicar consume cupo) |
| POST | `/uploads/image` | Sube una imagen (`image`, `file` o `photo`; jpg, png o webp; máximo 5 MB) y devuelve `url` |
| POST | `/services/{id}/book` | Reservar servicio (consume cupo) |
| GET / POST / PUT / DELETE | `/cart`, `/cart/items/{id}` | Carrito |
| POST | `/checkout` | Convierte el carrito en pedidos, uno por vendedor |
| GET / POST | `/orders`, `/orders/{id}` | Mis pedidos |
| GET | `/orders/{id}/payment` | Enlace de pago de Wompi del pedido |
| POST | `/orders/{id}/cancel` | Cancelar un pedido propio sin pagar |
| GET | `/my-sales` | Ventas recibidas |
| GET / POST | `/rentals`, `/rentals/owned` | Alquileres como arrendatario o propietario |
| PATCH | `/rentals/{id}/status` | Cambiar el estado de un alquiler |
| POST | `/payments` · GET `/payments/{id}` | Enlace de pago y estado de una transacción |
| POST | `/reviews` | Calificar (requiere compra o alquiler finalizado) |
| GET / POST / DELETE | `/favorites` | Favoritos |
| GET / POST | `/notifications`, `/notifications/{id}/read`, `/notifications/read-all` | Notificaciones |
| GET / POST / PUT | `/my-business`, `/businesses` | Emprendimiento propio |

## Rutas de administración

Requieren rol `admin` (middleware `admin`).

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/admin/dashboard` | Estadísticas y actividad reciente |
| GET | `/admin/dashboard/export/{pdf,excel}` | Exportación del panel |
| GET | `/admin/metrics/export/{pdf,excel}` | Reporte de métricas por rango de fechas |
| GET / PATCH / DELETE | `/admin/users`, `/admin/users/{id}`, `/admin/users/{id}/status` | Usuarios (DELETE anonimiza la cuenta) |
| PUT | `/admin/users/{id}/subscription` | Cambiar el plan de un usuario |
| GET | `/admin/products`, `/admin/services`, `/admin/rentals` | Publicaciones y alquileres |
| GET / PATCH | `/admin/orders`, `/admin/bookings` (`/{id}/status`) | Pedidos y reservas |
| POST / PUT / DELETE | `/admin/categories` | Categorías (no se borran si tienen contenido) |
| GET / POST / PUT / DELETE | `/admin/membership-plans` | Planes |
| GET / PATCH / DELETE | `/admin/businesses` | Emprendimientos |
| GET / POST | `/admin/notifications` | Notificaciones del administrador |
