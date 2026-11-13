<div align="center">

# 🛍️ Mercova

**Marketplace para comprar, vender, alquilar productos y reservar servicios, con un espacio para emprendimientos locales.**

![PHP](https://img.shields.io/badge/PHP-8.3%2B-777BB4?logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)
![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-4-06B6D4?logo=tailwindcss&logoColor=white)
![Vite](https://img.shields.io/badge/Vite-8-646CFF?logo=vite&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-TiDB_Cloud-4479A1?logo=mysql&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-Render-2496ED?logo=docker&logoColor=white)
![License](https://img.shields.io/badge/licencia-MIT-green)

</div>

---

## 📋 Tabla de contenido

- [Descripción](#-descripción)
- [Funcionalidades](#-funcionalidades)
- [Tecnologías](#-tecnologías)
- [Arquitectura](#-arquitectura)
- [Instalación local](#-instalación-local)
- [Variables de entorno](#-variables-de-entorno)
- [Cuentas de prueba](#-cuentas-de-prueba)
- [Pruebas](#-pruebas)
- [API](#-api)
- [Despliegue](#-despliegue)
- [Estructura del proyecto](#-estructura-del-proyecto)
- [Trabajo futuro](#-trabajo-futuro)

---

## 📖 Descripción

**Mercova** es una aplicación web full-stack tipo marketplace. Los usuarios pueden comprar y alquilar productos, reservar servicios y publicar los suyos. Los pequeños emprendimientos pueden registrarse y contratar un plan de membresía para publicar con su propia marca.

La plataforma cobra una **comisión del 6 %** por transacción e incluye un **panel administrativo** independiente para gestionar usuarios, publicaciones, pedidos, categorías, planes y reportes.

El backend es una **API REST**, así que puede usarse desde las vistas incluidas (Blade + JavaScript) o desde un frontend externo o una app móvil.

---

## ✨ Funcionalidades

### Usuarios
- 🔐 Registro, inicio de sesión, recuperación de contraseña y login con **Google / Apple**.
- 🎉 Letrero y **correo de bienvenida** al crear la cuenta.
- 👤 Panel personal (`/panel`): perfil, publicaciones, compras, reservas y alquileres.

### Marketplace
- 🛒 Catálogo con categorías y subcategorías, búsqueda y página de detalle de producto.
- 🧺 **Carrito persistente** con validación de stock; el checkout crea **un pedido por vendedor**.
- 📅 **Alquileres por días** con disponibilidad en vivo y depósito (`pendiente → confirmado → activo → finalizado`).
- 🗓️ **Reservas de servicios** por horario, sin cruces con otras reservas.
- ❤️ Favoritos, ⭐ reseñas (solo si se compró o alquiló el producto) y 🔔 notificaciones internas.
- 💳 Pagos con **Wompi** (tarjeta, PSE, Nequi, Bancolombia).

### Emprendimientos y planes
| Plan | Precio mensual |
|---|---|
| Despegue | Gratis |
| Impulso | $29.900 COP |
| Élite | $59.900 COP |

Cada plan define cupos mensuales de publicaciones y productos destacados.

### Administración (`/admin`)
- 📊 Estadísticas, actividad reciente y **exportación de métricas en PDF y Excel**.
- Gestión de pedidos, reservas, alquileres, publicaciones, usuarios, emprendimientos, categorías y planes.
- Las cuentas cuyo correo esté en `MERCOVA_ADMIN_EMAILS` se vuelven administradoras automáticamente. El registro público nunca crea administradores.

---

## 🧰 Tecnologías

| Capa | Tecnología |
|---|---|
| Backend | PHP 8.3+ · Laravel 13 · Eloquent ORM |
| Autenticación | Laravel Sanctum (tokens) · Laravel Socialite |
| Frontend | Blade · JavaScript (ES Modules) · Tailwind CSS 4 · Vite 8 |
| Base de datos | SQLite (local) · MySQL / TiDB Cloud (producción) |
| Pagos | Wompi (Web Checkout + webhooks) |
| Imágenes | Cloudinary |
| Correo | Brevo (API HTTP) · SMTP |
| Reportes | DomPDF · Laravel Excel |
| Pruebas | PHPUnit 12 · Laravel Pint |
| Infraestructura | Docker · Render · GitHub Actions |

---

## 🏗️ Arquitectura

```
Navegador / Frontend externo
        │  fetch + token Bearer
        ▼
routes/api.php ──► Middleware (auth:sanctum, admin)
        │
        ▼
Controladores (app/Http/Controllers/Api)
        │
        ▼
Servicios (app/Services) ── lógica de negocio: comisiones, pagos, suscripciones
        │
        ▼
Modelos Eloquent (app/Models) ──► Base de datos
```

**Decisiones de diseño destacadas:**
- **Comisión centralizada** en `CommissionCalculator`. Cada venta, alquiler y reserva guarda una copia de la comisión aplicada, así un cambio futuro de tasa no altera el historial.
- **Concurrencia segura:** `DB::transaction()` + `lockForUpdate()` evitan la sobreventa de stock y las reservas o alquileres que se cruzan.
- **Seguridad:** escape de HTML contra XSS, middleware `admin`, firma verificada en los webhooks de Wompi y secretos solo en variables de entorno.
- **Tablas polimórficas** para favoritos y reseñas, pensadas para crecer.

---

## 🚀 Instalación local

### Requisitos
- PHP 8.3+ y Composer
- Node.js 18+ y npm
- SQLite (o MySQL)

### Pasos

```bash
# 1. Clonar el repositorio
git clone https://github.com/JuanVillegas05/MERCOVA.git
cd MERCOVA

# 2. Instalar dependencias
composer install
npm install

# 3. Configurar el entorno
cp .env.example .env
php artisan key:generate

# 4. Crear la base de datos con datos de ejemplo
php artisan migrate --seed

# 5. Compilar el frontend
npm run build

# 6. Iniciar el servidor
php artisan serve --port=8000
```

Abre **http://localhost:8000** en el navegador.

> 💡 Para desarrollo con recarga automática usa `npm run dev` en otra terminal.
> Si la página aparece sin estilos después de detenerlo, borra `public/hot` y ejecuta `npm run build`.

> 📨 Los correos se envían por cola. Con `QUEUE_CONNECTION=database` necesitas `php artisan queue:work`; con `QUEUE_CONNECTION=sync` salen al instante.

---

## 🔑 Variables de entorno

Copia `.env.example` a `.env` y completa lo que necesites. **Nunca subas tu `.env` a GitHub.**

| Variable | Para qué sirve |
|---|---|
| `DB_CONNECTION`, `DB_*` | Conexión a la base de datos |
| `MYSQL_ATTR_SSL_CA` | Certificado TLS (TiDB Cloud); si se deja vacío usa el del sistema |
| `MAIL_MAILER`, `BREVO_API_KEY`, `MAIL_FROM_ADDRESS` | Envío de correos (`brevo`, `smtp` o `log`) |
| `CLOUDINARY_URL` | Almacenamiento de imágenes (obligatorio en producción) |
| `WOMPI_PUBLIC_KEY`, `WOMPI_PRIVATE_KEY`, `WOMPI_INTEGRITY_SECRET`, `WOMPI_EVENTS_SECRET` | Pasarela de pagos |
| `GOOGLE_CLIENT_ID/SECRET`, `APPLE_CLIENT_ID/SECRET` | Login social (los botones aparecen solo si están configurados) |
| `MERCOVA_ADMIN_EMAILS` | Correos que serán administradores (separados por coma) |
| `MERCOVA_COMMISSION_RATE` | Comisión de la plataforma (por defecto `0.06`) |
| `MERCOVA_FRONTEND_URL` | URL del frontend externo, si existe |
| `MERCOVA_CRON_SECRET` | Protege el endpoint de tareas programadas |

---

## 👥 Cuentas de prueba

Las crea `php artisan migrate --seed` y **solo existen en entornos de desarrollo**:

| Rol | Correo | Contraseña |
|---|---|---|
| Administrador | `admin@mercova.test` | `password123` |
| Vendedor | `seller@mercova.test` | `password123` |
| Cliente | `customer@mercova.test` | `password123` |

---

## 🧪 Pruebas

El proyecto tiene **35 archivos de pruebas de funcionalidad** (más de 200 casos). Cubren autenticación, carrito y checkout, comisiones, alquileres, reservas sin cruces, reseñas, planes, pagos con Wompi, subida de imágenes, correos y permisos de administrador.

```bash
php artisan test              # toda la suite
php artisan test --filter=Rental   # solo un grupo
vendor/bin/pint               # formato de código
```

---

## 🔌 API

Base: `/api` · Autenticación: `Authorization: Bearer <token>`

| Método | Endpoint | Descripción |
|---|---|---|
| `POST` | `/register`, `/login` | Registro e inicio de sesión |
| `GET` | `/products`, `/services`, `/categories` | Catálogo público |
| `POST` | `/products`, `/services` | Publicar 🔒 |
| `POST` | `/uploads/image` | Subir imagen 🔒 |
| `GET/POST` | `/cart`, `/cart/items` | Carrito 🔒 |
| `POST` | `/checkout` | Crear pedidos desde el carrito 🔒 |
| `POST` | `/rentals` | Solicitar alquiler 🔒 |
| `POST` | `/services/{id}/book` | Reservar servicio 🔒 |
| `POST` | `/reviews` | Calificar producto 🔒 |
| `GET/PUT` | `/me/subscription` | Plan del usuario 🔒 |
| `POST` | `/webhooks/wompi` | Confirmación de pagos (Wompi) |
| `GET` | `/admin/dashboard` | Estadísticas 🛡️ |
| `GET` | `/admin/metrics/export/{pdf\|excel}` | Reportes 🛡️ |

🔒 requiere sesión · 🛡️ requiere rol administrador. Lista completa: `php artisan route:list --path=api`.

---

## ☁️ Despliegue

El proyecto se despliega en **[Render](https://render.com)** con Docker (PHP + Apache), según [`render.yaml`](render.yaml) y el [`Dockerfile`](Dockerfile):

1. Crear un **Blueprint** en Render apuntando a este repositorio.
2. Completar las variables secretas (base de datos, `APP_KEY`, `BREVO_API_KEY`, `CLOUDINARY_URL`, llaves de Wompi…).
3. Al iniciar, el contenedor ejecuta las migraciones automáticamente.

**Servicios externos en producción:** TiDB Cloud (MySQL), Cloudinary (imágenes), Brevo (correo) y Wompi (pagos).

**Tareas programadas:** el plan gratuito de Render no ejecuta `schedule:run`. Por eso el workflow [`.github/workflows/scheduled-tasks.yml`](.github/workflows/scheduled-tasks.yml) llama cada 10 minutos a `/api/cron/run`, que cancela los pedidos vencidos sin pagar y concilia los pagos pendientes con Wompi. Requiere el secret `MERCOVA_CRON_SECRET` en GitHub.

---

## 📁 Estructura del proyecto

```
mercova/
├── app/
│   ├── Http/Controllers/Api/   # Controladores de la API REST
│   ├── Http/Middleware/        # Middleware (p. ej. EnsureUserIsAdmin)
│   ├── Mail/                   # Correos y transporte de Brevo
│   ├── Models/                 # Modelos Eloquent
│   └── Services/               # Lógica de negocio (comisión, pagos, planes…)
├── config/mercova.php          # Configuración propia (comisión, pagos, admins)
├── database/
│   ├── migrations/             # Esquema de la base de datos
│   └── seeders/                # Datos de ejemplo y cuentas de prueba
├── resources/
│   ├── views/                  # Vistas Blade (sitio y panel admin)
│   └── js/                     # Lógica del frontend por página
├── routes/
│   ├── api.php                 # Endpoints de la API
│   └── web.php                 # Páginas
├── tests/Feature/              # Pruebas automatizadas
├── Dockerfile
└── render.yaml
```

---

## 🔭 Trabajo futuro

- 💬 Mensajería entre comprador y vendedor.
- ⭐ Reseñas y favoritos para servicios.
- 📆 Precios de alquiler por semana o mes y varias unidades por producto.
- 🖼️ Gestión de banners y productos destacados desde el panel administrativo.

---

## 📄 Licencia

Proyecto distribuido bajo la licencia **MIT**.
