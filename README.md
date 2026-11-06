# Mercova

Marketplace full-stack (backend Laravel + frontend Blade/Tailwind/JS) para comprar, vender y alquilar productos y servicios, con un espacio dedicado para que pequeños emprendimientos se registren y contraten una membresía para publicar, y un panel administrativo independiente.

## Características

- **Público**: Inicio, Nosotros, Marketplace y Emprendimientos navegables sin cuenta.
- **Autenticación**: registro/login con Sanctum (tokens), recuperación de contraseña, edición de perfil y cambio de contraseña.
- **Usuario normal** (`customer`/`seller`): comprar, alquilar, reservar servicios, publicar productos/servicios propios o a nombre de un emprendimiento, ver sus compras/reservas/publicaciones en `/panel`.
- **Emprendimientos**: cualquier usuario puede registrar su emprendimiento, elegir una membresía (Básico/Pro/Premium) y publicar a su nombre mientras la membresía esté activa.
- **Comisión MERCOVA (6%)**: centralizada en `config/mercova.php` (override con `MERCOVA_COMMISSION_RATE`) y calculada por `App\Services\CommissionCalculator`. Al publicar un producto, el vendedor puede indicar el precio de venta o la ganancia neta que quiere recibir; el sistema calcula el otro valor y guarda un snapshot de `commission_rate`/`net_amount` en el producto (para que cambios futuros en la tasa no alteren publicaciones ya existentes).
- **Carrito y checkout** (`/carrito`): cada usuario tiene un carrito persistente (`carts`/`cart_items`) con validación de stock al agregar/actualizar cantidades. El checkout pide información de entrega y método de pago (lista centralizada en `config/mercova.php`: tarjeta débito/crédito, PSE, Addi, Sistecrédito — sin integración real todavía, ver sección de pagos reales) y crea **un pedido por cada vendedor** presente en el carrito (un pedido nunca mezcla productos de dos vendedores).
- **Favoritos** (`/favoritos`): botón de corazón en cada tarjeta de producto (marketplace y favoritos); tabla polimórfica `favorites` (`favoritable_type`/`favoritable_id`) pensada para poder guardar también servicios en el futuro sin otra migración.
- **Página individual de producto** (`/producto/{slug}`): galería (imagen principal), reseñas con comentario completo, favorito, compartir (Web Share API con fallback a copiar el enlace), y las mismas acciones de comprar/agregar al carrito/solicitar alquiler que el marketplace. Las tarjetas del marketplace y de favoritos ahora enlazan aquí (antes "ver producto" no existía en ningún lado).
- **Notificaciones para usuarios normales** (no solo administradores): comprador y vendedor reciben notificación real (tabla `user_notifications`, mismo patrón que `AdminNotification`) en: nueva compra (al vendedor), cambio de estado del pedido (al comprador), nueva solicitud de alquiler (al propietario), cambio de estado del alquiler (al arrendatario), nueva reserva de servicio y cambio de estado de la reserva. Campana con contador de no leídas en el header, igual que en `/admin` (mismo componente, reutilizado).
- **Calificaciones** (tabla `reviews`, polimórfica sobre `Product`): solo se puede calificar un producto que se compró (pedido en estado `completed`) o se alquiló (alquiler `finished`) — se valida en el servidor, no es un formulario decorativo. Calificar de nuevo actualiza la reseña anterior en vez de duplicarla. El promedio y el conteo aparecen como estrellas en cada tarjeta del marketplace y de favoritos.
- **Alquileres de productos con fechas** (tabla `rentals`, separada de `orders`/`bookings`): los productos de alquiler tienen `price_per_day` y `deposit_amount` propios (ya no comparten el flujo de venta ni el carrito). El comprador elige fechas y ve disponibilidad en vivo antes de enviar la solicitud; el sistema impide solapamientos (`pending`/`confirmed`/`active` bloquean el rango). El propietario confirma/rechaza desde `/panel`, y el ciclo de vida es `pending → confirmed → active → finished` (o `cancelled` en cualquier punto antes de `finished`). Cada alquiler guarda su propio snapshot de comisión (`commission_rate`, `commission_amount`, `net_amount`) igual que los productos de venta.
- **Administración** (`/admin`, panel separado del de usuario normal): resumen general con estadísticas y actividad reciente, gestión de pedidos y reservas (con notificaciones automáticas de pedidos nuevos), gestión de publicaciones (activar/archivar/eliminar cualquier producto o servicio), gestión de usuarios (buscar, ver detalle, activar/desactivar), categorías/subcategorías y planes de membresía. Un usuario normal nunca puede entrar a `/admin`, ni escribiendo la URL directamente.
- **Admin por lista de correos**: cualquier cuenta cuyo correo esté en `MERCOVA_ADMIN_EMAILS` (`.env`, separados por coma) se vuelve administrador automáticamente al registrarse o iniciar sesión (`User::syncRoleFromAdminEmailList()`) — sin pasar por ninguna pantalla especial ni poder forzarlo desde el formulario de registro público. Nunca degrada a nadie, solo promueve.
- **Correo de bienvenida**: cada registro dispara `WelcomeNotification` (Laravel Notifications, canal `mail`). Con `MAIL_MAILER=log` (valor por defecto hoy) el correo se genera y queda registrado en `storage/logs/laravel.log`, listo para enviarse de verdad en cuanto se configure `RESEND_KEY` y se cambie `MAIL_MAILER=resend`.
- Base de datos SQLite para desarrollo local.

### Frontend aparte (proyecto separado)

El equipo tiene un frontend propio (fuera de este repo) que por defecto apunta a `http://localhost:8000/api`. Por eso el backend corre en el puerto **8000** (`APP_PORT=8000` en `.env`, ver `compose.yaml`), no en el 80 por defecto de Sail. El backend ya acepta peticiones de cualquier origen en `/api/*` (CORS abierto, ver `vendor/laravel/framework/config/cors.php` — no hay `config/cors.php` propio que lo restrinja). Si ese frontend corre en la misma máquina, no necesita ningún `.env` especial; si corre en otro lugar (un preview en la nube, por ejemplo), hay que apuntar su `VITE_API_URL` (o equivalente) a donde este backend sea alcanzable desde ahí — `localhost` nunca funciona entre máquinas distintas.

## Requisitos

- PHP 8.3+ / Composer
- Node 18+ y npm
- Laravel 13

## Puesta en marcha

```bash
cd /home/ville/mercova
export PATH="$HOME/.config/herd-lite/bin:$PATH"

composer install
npm install

cp .env.example .env   # si no existe
php artisan key:generate

php artisan migrate --seed
npm run build

php artisan serve --host=0.0.0.0 --port=8000
```

Luego abre `http://localhost:8000` en el navegador.

### Desarrollo del frontend (hot reload)

```bash
npm run dev
```

> Si detienes `npm run dev` de forma abrupta puede quedar un archivo `public/hot` colgado, lo que hace que Laravel intente cargar los assets desde el servidor de Vite aunque ya no esté corriendo. Si ves la página sin estilos, borra `public/hot` y vuelve a ejecutar `npm run build` (o `npm run dev`).

> **Si ves "attempt to write a readonly database" o "Permission denied" en `storage/logs` o en el `.sqlite`**: pasa cuando un archivo se crea desde Windows (dueño con un UID) y Laravel corre dentro de un contenedor Sail con un usuario de *otro* UID (mismo grupo, pero sin permiso de escritura). Se arregla con `chmod -R g+w .` ejecutado dentro del contenedor (`docker compose exec laravel.test chmod -R g+w /var/www/html`) — no hace falta repetirlo seguido, solo cuando aparece el error.

## Credenciales de demo

- Admin 1: admin@mercova.test / password123
- Admin 2: admin2@mercova.test / password123
- Vendedor (dueño del emprendimiento "Taller Creativo Mercova"): seller@mercova.test / password123
- Cliente: customer@mercova.test / password123

Solo existen estas 2 cuentas de administrador; el registro público (`/registro`, `POST /api/register`) nunca puede crear una cuenta admin.

## Páginas

**Público:**
- `/` Inicio · `/nosotros` Nosotros · `/marketplace` Comprar/alquilar/reservar · `/emprendimientos` Registro de emprendimientos y membresías
- `/producto/{slug}` Detalle de producto (galería, reseñas, favorito, compartir)
- `/login` Iniciar sesión · `/registro` Registrarse · `/olvide-password` y `/restablecer-password` Recuperar contraseña
- `/panel` Panel del usuario (perfil, publicaciones, compras, reservas, alquileres propios y recibidos) · `/carrito` Carrito y checkout · `/favoritos` Productos guardados

**Administración** (requiere `role: admin`, redirige a `/login` si no):
- `/admin` Resumen · `/admin/pedidos` Pedidos y reservas · `/admin/productos` Publicaciones · `/admin/categorias` Categorías y subcategorías · `/admin/planes` Planes de membresía · `/admin/usuarios` Usuarios

> La protección de `/admin/*` es del lado del cliente (el JS valida `role` contra `/api/me` antes de pintar datos) porque la autenticación es por token Bearer en `localStorage`, no por sesión/cookies. Todos los datos reales siempre pasan por la API, que sí valida `role === admin` en el servidor, así que no hay fuga de información aunque el HTML estático se sirva primero.

## Endpoints principales

- `GET /api/health`, `/api/categories`, `/api/products[/{slug}]`, `/api/services[/{slug}]`, `/api/membership-plans`, `/api/businesses[/{slug}]`
- `POST /api/products/commission-preview` — recibe `price` o `desired_net_price` y devuelve el desglose de comisión sin crear nada (pública, para vista previa en vivo)
- `GET /api/payment-methods` — lista centralizada de métodos de pago (pública)
- `GET /api/cart`, `POST /api/cart/items`, `PUT/DELETE /api/cart/items/{id}` (auth)
- `POST /api/checkout` — convierte el carrito en uno o más pedidos (auth)
- `GET /api/favorites`, `POST /api/favorites`, `DELETE /api/favorites/{product}` (auth) — `GET /api/products[/{slug}]` incluye `is_favorited` cuando la petición trae un token válido
- `GET /api/products/{product}/availability?start_date=&end_date=` — disponibilidad de un producto de alquiler (pública)
- `GET /api/rentals` (mis solicitudes como arrendatario), `GET /api/rentals/owned` (solicitudes recibidas como propietario), `POST /api/rentals`, `PATCH /api/rentals/{id}/status` (auth)
- `GET /api/products/{product}/reviews` (pública); `POST /api/reviews` (auth, requiere compra/alquiler finalizado)
- `GET /api/notifications[?unread=1]`, `POST /api/notifications/{id}/read`, `POST /api/notifications/read-all` (auth)
- `POST /api/register`, `POST /api/login`, `POST /api/forgot-password`, `POST /api/reset-password`
- `GET/PUT /api/me`, `PUT /api/me/password`, `POST /api/logout` (auth)
- `POST/PUT/DELETE /api/products[/{id}]`, `POST/PUT/DELETE /api/services[/{id}]`, `POST /api/services/{id}/book` (auth)
- `GET/POST /api/orders`, `GET /api/orders/{id}` (auth)
- `GET /api/my-business`, `POST /api/businesses`, `PUT /api/businesses/{id}`, `POST /api/businesses/{id}/subscribe` (auth)
- `GET /api/admin/dashboard` — stats + actividad reciente
- `GET /api/admin/users[?search=]`, `GET /api/admin/users/{id}`, `PATCH /api/admin/users/{id}/status`
- `GET /api/admin/products`, `GET /api/admin/services`
- `GET /api/admin/orders[?status=&window=new]`, `PATCH /api/admin/orders/{id}/status`
- `GET /api/admin/bookings[?status=&window=new]`, `PATCH /api/admin/bookings/{id}/status`
- `GET /api/admin/notifications[?unread=1]`, `POST /api/admin/notifications/{id}/read`, `POST /api/admin/notifications/read-all`
- `POST/PUT/DELETE /api/admin/categories[/{id}]` — crear/editar/eliminar categorías y subcategorías (bloquea el borrado si tiene productos, servicios o subcategorías)
- `GET /api/admin/membership-plans` (todos los planes, activos e inactivos) y `POST/PUT/DELETE /api/admin/membership-plans[/{id}]`
- (todos los `/api/admin/*` requieren `role: admin`)

Estados de `orders.status` y `bookings.status`: `pending`, `processing`, `completed`, `cancelled`.

## Revisión general (seguridad + concurrencia)

- **XSS almacenado corregido**: todo el frontend insertaba títulos, descripciones, nombres, reseñas y notificaciones directamente en `innerHTML` sin escapar. Se agregó `resources/js/lib/escape.js` (`escapeHtml`) y se aplicó en las ~13 páginas/componentes que renderizan contenido de usuario (marketplace, producto, favoritos, carrito, panel, emprendimientos, inicio, notificaciones y las 4 páginas de admin). También se centralizó el escape del título en `lib/modal.js`, porque un valor ya escapado en un atributo `data-*` vuelve a decodificarse al leerlo con `.dataset`.
- **Condiciones de carrera corregidas** con `DB::transaction()` + `lockForUpdate()`: compra directa y checkout del carrito (sobreventa de stock), solicitud y confirmación de alquiler (doble reserva de fechas), y el límite de publicaciones del plan de membresía (productos y servicios).
- **Bug corregido**: cambiar un producto de alquiler a venta (o viceversa) sin indicar el nuevo precio dejaba el precio/comisión del tipo anterior guardado silenciosamente; ahora se exige el precio correspondiente al cambiar el tipo.
- **Panel admin: gestión de categorías y planes** (`/admin/categorias`, `/admin/planes`): categorías con subcategorías de un solo nivel (`categories.parent_id`), bloqueando el borrado de una categoría con productos/servicios/subcategorías para no romper publicaciones existentes. Planes de membresía con precio/límite/características editables desde el panel — eliminar un plan no borra los emprendimientos que lo tenían (`membership_plan_id` queda en null), solo deja de estar disponible para nuevas suscripciones.
- **Comisión extendida a pedidos**: `Order`/`OrderItem` ahora guardan su propio snapshot de comisión (antes solo lo hacían `Rental` y `Booking`). Cada línea del pedido calcula su `commission_rate`/`commission_amount`/`net_amount` al momento de la compra; el pedido agrega la suma exacta de sus líneas (no se recalcula aparte, para que nunca se desajuste por redondeo). Ya las 4 formas de transacción (venta, alquiler, servicio directo, reserva de servicio) dejan un registro histórico de comisión que no cambia aunque la tasa global cambie después.
- **Comisión extendida a servicios**: `Service` y `Booking` ahora pasan por el mismo `CommissionCalculator` que productos/alquileres. Al publicar un servicio, el vendedor puede indicar el precio o la ganancia neta deseada (igual que con productos de venta); cada reserva guarda su propio snapshot de `commission_rate`/`commission_amount`/`net_amount`.
- **Reservas de servicio sin solapamiento de horario**: `Service::durationInMinutes()` interpreta el texto libre de duración ("2 horas", "45 minutos"; si no se entiende, asume 1 hora) para calcular el rango de tiempo real que ocupa cada reserva. `ServiceController::book()` bloquea la fila del servicio y rechaza una reserva cuyo horario se solape con otra reserva `pending`/`processing` del mismo servicio — mismo patrón que ya protegía los alquileres.

## Limpieza de código

- **Middleware `admin`** (`App\Http\Middleware\EnsureUserIsAdmin`): todas las rutas `/api/admin/*` pasan ahora por un único middleware en vez de que cada controlador repita `if ($request->user()->role !== 'admin') abort(403, ...)`. Eliminó 15+ copias de ese chequeo (incluida la de `AdminController::ensureAdmin()` que yo mismo había duplicado en `CategoryController` y `MembershipPlanController`).
- **`Business::canBeManagedBy()`** y **`Business::assertCanPublishListing()`**: la validación de "¿el emprendimiento es mío? ¿tiene membresía activa? ¿no superó el límite del plan?" vivía copiada letra por letra en `ProductController` y `ServiceController` (y la de ownership, también en `BusinessController`). Ahora es un solo método en el modelo, reutilizado en los tres controladores — un cambio futuro a esa regla se hace en un solo lugar.
- **`User::isSellerOrAdmin()`**: el chequeo de rol duplicado en `ProductController`/`ServiceController`.
- **`CommissionCalculator::resolveBreakdown()`**: la lógica de "si viene `desired_net_price` úsala, si no usa `price`, si no vino ninguno avisa" estaba repetida 4 veces (productos y servicios, crear y editar). Ahora es un método del propio servicio de comisión.
- **Código muerto eliminado**: `Rental::overlaps()` (nunca se llamaba, y además estaba mal — no excluía alquileres cancelados/finalizados) y `AdminController::updateListingStatus()` (ruta sin usar desde el frontend, con un bug real: buscaba `Product::find($id)` antes de `Service::find($id)`, así que un producto y un servicio con el mismo id habrían actualizado el registro equivocado).
- Todo verificado con los 89 tests existentes más 10 nuevos para los helpers extraídos, y confirmado por API real que el comportamiento es idéntico al de antes del refactor.

### Pendiente, documentado explícitamente (no oculto)

- Un vendedor puede evitar el límite de publicaciones de su plan simplemente omitiendo `business_id` al publicar (queda como "publicación personal", que no cuenta contra ningún límite).

## En construcción: pagos reales, login social y por teléfono

Pedido explícitamente por el cliente; cada pieza necesita credenciales reales de un tercero que todavía no tenemos, así que la arquitectura queda preparada pero la conexión final está pendiente:

- **Pasarela de pagos (Mercado Pago)**: `config('mercova.mercadopago')` lee `MERCADOPAGO_PUBLIC_KEY`/`MERCADOPAGO_ACCESS_TOKEN`/`MERCADOPAGO_WEBHOOK_SECRET` desde `.env`. Los checkouts devuelven `payment_url` (Checkout Pro) y el frontend puede cobrar la misma referencia con tarjeta o PSE vía `GET /api/payments/config` + `POST /api/payments`. El webhook es `POST /api/webhooks/mercadopago` (evento "Pagos", necesita una URL pública https). Credenciales de prueba en https://www.mercadopago.com.co/developers/panel/app.
- **Login con Google/Apple**: `config('mercova.social_login')` detecta si hay credenciales (`GOOGLE_CLIENT_ID`/`GOOGLE_CLIENT_SECRET`, `APPLE_CLIENT_ID`/`APPLE_CLIENT_SECRET`) y expondrá o esconderá los botones según eso. Falta instalar Laravel Socialite (+ `socialiteproviders/apple` para Apple, que no es un driver nativo), las rutas `/auth/{provider}/redirect` y `/auth/{provider}/callback`, y la tabla `social_accounts` para enlazar la cuenta. Google requiere un proyecto en Google Cloud Console (gratis); Apple requiere Apple Developer Program (~99 USD/año).
- **Login por número de teléfono**: sin SMS/OTP (no se pidió, y requeriría un proveedor como Twilio con costo por mensaje) funciona como identificador alterno al correo — mismo formulario, misma contraseña. Falta: hacer único el campo `phone`, y que `/api/login` acepte un `identifier` (correo o teléfono) en vez de solo `email`.
- **Admin por lista de correos** y **correo de bienvenida**: estos dos ya quedaron funcionando (ver arriba) porque no dependían de un tercero nuevo — el correo usa la infraestructura de `log`/Resend que Laravel ya trae.

## Arquitectura pensada para crecer

Estas piezas todavía no están construidas, pero el diseño actual (tipos polimórficos, tablas separadas por dominio) no las bloquea:

- **Mensajería comprador-vendedor**: tablas `conversations` + `messages`.
- **Calificaciones de servicios**: el modelo `Review` ya es polimórfico; falta el mismo flujo para `Booking`/`Service`.
- **Precio por semana/mes en alquileres**: hoy solo existe `price_per_day`; el modelo `Rental` ya centraliza el cálculo así que agregar esas unidades es extender `CommissionCalculator`/`RentalController`, no rehacer el flujo.
- **Múltiples unidades por producto de alquiler**: hoy cada producto de alquiler se trata como una sola unidad física (sin solapamiento de fechas); no hay concepto de "stock" para alquiler.
- **Calificaciones**: tabla `reviews` (`reviewer_id`, `reviewable_type`, `reviewable_id`, `rating`, `comment`).
- **Favoritos de servicios**: la tabla ya es polimórfica, solo falta exponer el mismo botón en las tarjetas de servicio.
- **Pagos reales**: tabla `payments` (`order_id`, `provider`, `provider_reference`, `status`, `amount`) — hoy `orders.payment_method` es un string simulado.
- **Banners / productos destacados administrables**: `is_featured` ya existe en `Product`/`Service`, pero no hay pantalla en `/admin` para curarlos ni para gestionar banners de portada.

## Estado de validación

- `php artisan test`: 93 tests (todo lo anterior, más: admin automático por lista de correos al registrarse/iniciar sesión, nunca degrada a nadie; y envío del correo de bienvenida en cada registro).
- Flujo verificado manualmente end-to-end: registro → login → crear emprendimiento → contratar membresía → publicar producto → compra/reserva desde el marketplace → notificación automática al admin → gestión desde `/admin` (pedidos, publicaciones, categorías, planes, usuarios) → recuperación de contraseña vía log de correo.
