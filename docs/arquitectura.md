# Arquitectura

Mercova es una aplicación Laravel monolítica que expone una API REST. Las páginas Blade del propio repositorio son clientes de esa API, igual que cualquier frontend externo: no leen la base de datos directamente.

## Componentes

```
Navegador (Blade + JS) / Frontend externo
        │  HTTPS + Authorization: Bearer <token>
        ▼
routes/api.php ──► Middleware: auth:sanctum · admin · plan.quota · throttle
        │
        ▼
Controladores (app/Http/Controllers/Api)   validan la petición y responden JSON
        │
        ▼
Servicios (app/Services)                   reglas de negocio reutilizables
        │
        ▼
Modelos Eloquent (app/Models)  ──►  MySQL / SQLite
        │
        └──► Integraciones: Wompi · Cloudinary · Brevo
```

| Capa | Ubicación | Responsabilidad |
|---|---|---|
| Rutas | `routes/api.php`, `routes/web.php` | Endpoints de la API y páginas Blade |
| Middleware | `app/Http/Middleware` | Rol de administrador, cupos del plan, funciones del plan y `Cache-Control: no-store` |
| Controladores | `app/Http/Controllers/Api` | Validación, autorización y respuesta |
| Servicios | `app/Services` | Comisión, pagos, suscripciones, métricas, imágenes y eliminación de cuentas |
| Modelos | `app/Models` | Relaciones y reglas propias de cada entidad |
| Frontend | `resources/views`, `resources/js` | Una vista Blade y un módulo JS por página |

### Servicios principales

| Servicio | Qué hace |
|---|---|
| `CommissionCalculator` | Calcula la comisión y la ganancia neta a partir del precio, o el precio a partir de la ganancia deseada |
| `SubscriptionService` | Única entrada para el plan del usuario: suscribir, consultar estado y consumir cupos |
| `SubscriptionCheckoutService` | Crea el intento de pago de un plan y lo concilia con Wompi |
| `PaymentService` / `WompiService` | Enlaces de pago, firma de integridad, consulta de transacciones y aplicación del resultado |
| `MetricsReportService` | Fuente única de las cifras del panel y de las exportaciones PDF y Excel |
| `CloudinaryService` | Subida firmada de imágenes; el secreto nunca sale del backend |
| `UserAccountDeletion` | Eliminación de cuentas por anonimización (ver más abajo) |

## Roles

| Rol | Puede |
|---|---|
| Visitante | Ver el catálogo, emprendimientos, planes y reseñas |
| `customer` | Comprar, alquilar, reservar, calificar, guardar favoritos y publicar dentro de los cupos de su plan |
| `seller` | Lo mismo que `customer`, con el panel orientado a sus ventas |
| `admin` | Todo lo anterior y las rutas `/api/admin/*` |

El rol `admin` no se puede obtener desde el registro público. Se asigna solo si el correo está en `MERCOVA_ADMIN_EMAILS` (`User::syncRoleFromAdminEmailList()`, que nunca degrada a nadie). El middleware `EnsureUserIsAdmin` protege todo el grupo `/api/admin`; las páginas `/admin/*` además comprueban el rol con `/api/me` antes de pedir datos.

## Modelo de datos

| Tabla | Contenido |
|---|---|
| `users` | Cuenta, rol, estado, datos OAuth y borrado lógico |
| `categories` | Categorías con un nivel de subcategorías (`parent_id`) |
| `products` | Publicaciones de venta (`sale`) o alquiler (`rent`) con precio, stock y copia de la comisión |
| `services` | Servicios con precio, duración y copia de la comisión |
| `businesses` | Emprendimientos de un usuario |
| `membership_plans`, `subscriptions`, `plan_usages` | Planes, suscripción vigente y consumo mensual de cupos |
| `subscription_payments` | Intentos de pago de planes en Wompi |
| `carts`, `cart_items` | Carrito persistente por usuario |
| `orders`, `order_items` | Pedidos con entrega, estado de pago y comisión por línea |
| `rentals` | Alquileres con fechas, precio por día, depósito y comisión |
| `bookings` | Reservas de servicios con fecha, hora y comisión |
| `reviews`, `favorites` | Tablas polimórficas, preparadas para incluir servicios |
| `user_notifications`, `admin_notifications` | Notificaciones internas |

## Reglas del dominio

**Comisión.** La tasa global es 6 % (`MERCOVA_COMMISSION_RATE`). Cada producto, servicio, línea de pedido, alquiler y reserva guarda su propia copia de `commission_rate`, `commission_amount` y `net_amount`, así un cambio futuro de tasa no altera el historial. El total de comisión de un pedido es la suma exacta de sus líneas, sin recalcularlo, para evitar diferencias de redondeo.

**Pedidos.** El checkout crea un pedido por cada vendedor del carrito; un pedido nunca mezcla vendedores. Estados: `pending → processing → completed`, o `cancelled`. Los pedidos sin pagar vencen a las 48 horas (`MERCOVA_ORDER_EXPIRY_HOURS`) y se cancelan devolviendo el stock.

**Alquileres.** Un producto de alquiler es una sola unidad física. Transiciones permitidas: `pending → confirmed → active → finished`, con `cancelled` posible desde cualquier estado anterior a `finished`. Los estados `pending`, `confirmed` y `active` bloquean el rango de fechas.

**Reservas de servicios.** La duración en texto libre ("2 horas", "45 minutos") se interpreta con `Service::durationInMinutes()`; si no se entiende, se asume una hora. Una reserva no puede cruzarse con otra `pending` o `processing` del mismo servicio.

**Reseñas.** Solo puede calificar quien compró el producto (pedido `completed`) o lo alquiló (alquiler `finished`). Calificar de nuevo actualiza la reseña anterior.

**Planes.** Los cupos mensuales se descuentan con el middleware `plan.quota:listings` (publicaciones) y `plan.quota:requests` (alquileres y reservas). `null` significa ilimitado.

| Plan | Precio | Publicaciones/mes | Solicitudes/mes | Destacados |
|---|---|---|---|---|
| Despegue | Gratis | 5 | 10 | 0 |
| Impulso | $29.900 | 30 | Ilimitadas | 5 |
| Élite | $59.900 | Ilimitadas | Ilimitadas | Ilimitados |

**Eliminación de cuentas.** No hay borrado físico, porque las claves foráneas en cascada borrarían pedidos y pagos de otras personas. `UserAccountDeletion` revoca los tokens, anonimiza los datos personales, libera el correo, archiva las publicaciones, suspende el emprendimiento, cancela los pedidos sin pagar y marca la cuenta con borrado lógico.

## Concurrencia

Las operaciones que compiten por el mismo recurso se ejecutan dentro de `DB::transaction()` con `lockForUpdate()` sobre la fila disputada:

- Compra directa y checkout: evitan vender más unidades que el stock.
- Solicitud y confirmación de alquiler: evitan dos alquileres en las mismas fechas.
- Reserva de servicio: evita dos reservas en el mismo horario.
- Cupos del plan y cambio de plan: evitan superar el límite o tener dos suscripciones activas.
