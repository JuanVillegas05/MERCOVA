# Mercova

Mercova es un marketplace web para comprar, vender y alquilar productos y reservar servicios, con un espacio para que los emprendimientos locales publiquen bajo su propia marca mediante planes de membresía. La plataforma cobra una comisión del 6 % por transacción y cuenta con un panel administrativo independiente.

El backend es una API REST en Laravel 13 (PHP 8.3+) autenticada con tokens de Sanctum. Las vistas incluidas (Blade, JavaScript y Tailwind CSS 4) consumen esa misma API, de modo que también puede usarla un frontend externo o una aplicación móvil.

## Documentación

La documentación técnica está en [docs/](docs/README.md). Cada documento explica una parte del sistema:

| Documento | Qué resuelve |
|---|---|
| [Arquitectura](docs/arquitectura.md) | Componentes, modelo de datos, roles y reglas del dominio |
| [API](docs/api.md) | Rutas, autenticación, límites de peticiones y formato de errores |
| [Pagos y planes](docs/pagos.md) | Integración con Wompi, comisiones, suscripciones y conciliación |
| [Despliegue](docs/despliegue.md) | Docker, Render, servicios externos y tareas programadas |
| [Pruebas](docs/pruebas.md) | Comandos, cobertura y comprobación manual |
| [Decisiones](docs/decisiones/README.md) | Motivos, alternativas y límites de las decisiones técnicas |
| [Contribución](CONTRIBUTING.md) | Convenciones, ramas, commits y cambios de esquema |
| [Seguridad](SECURITY.md) | Secretos, permisos, privacidad y reporte de fallos |
| [Historial](CHANGELOG.md) | Cambios entregados y límites conocidos |

## Funcionalidades

- **Cuentas:** registro, inicio de sesión, login con Google y Apple, recuperación de contraseña, letrero y correo de bienvenida.
- **Marketplace:** catálogo con categorías y subcategorías, página de producto, favoritos, reseñas verificadas y notificaciones internas.
- **Transacciones:** carrito persistente con un pedido por vendedor, alquileres por días con disponibilidad en vivo y reservas de servicios sin cruces de horario.
- **Pagos:** Wompi Web Checkout (tarjeta, PSE, Nequi y Bancolombia) con confirmación por webhook firmado.
- **Emprendimientos:** planes Despegue (gratis), Impulso ($29.900) y Élite ($59.900) con cupos mensuales.
- **Administración:** estadísticas, gestión de usuarios, publicaciones, pedidos, alquileres, categorías y planes, con exportación de métricas a PDF y Excel.

## Tecnologías

| Capa | Tecnología |
|---|---|
| Backend | PHP 8.3+, Laravel 13, Eloquent, Sanctum, Socialite |
| Frontend | Blade, JavaScript (ES Modules), Tailwind CSS 4, Vite 8 |
| Datos | SQLite en local; MySQL (TiDB Cloud) en producción |
| Integraciones | Wompi (pagos), Cloudinary (imágenes), Brevo (correo) |
| Reportes | DomPDF, Laravel Excel |
| Calidad | PHPUnit 12, Laravel Pint |
| Infraestructura | Docker, Render, GitHub Actions |

## Inicio rápido

Requisitos: PHP 8.3+, Composer, Node.js 18+ y npm.

```bash
git clone https://github.com/JuanVillegas05/MERCOVA.git
cd MERCOVA
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run build
php artisan serve --port=8000
```

La aplicación queda en `http://localhost:8000`. El seeder crea planes, categorías, productos de ejemplo y estas cuentas de desarrollo:

| Rol | Correo | Contraseña |
|---|---|---|
| Administrador | `admin@mercova.test` | `password123` |
| Vendedor | `seller@mercova.test` | `password123` |
| Cliente | `customer@mercova.test` | `password123` |

Estas cuentas existen solo donde se ejecute el seeder y no deben crearse en producción. Las variables de entorno disponibles están descritas en [.env.example](.env.example) y en [Despliegue](docs/despliegue.md#variables-de-entorno).

## Producción: Render

API: [mercova-api.onrender.com](https://mercova-api.onrender.com). Rama desplegada: `principal`.

La aplicación corre en Render (plan gratuito) como un contenedor Docker con PHP 8.5 y Apache, definido en el [Dockerfile](Dockerfile) y el Blueprint [render.yaml](render.yaml). Al arrancar, el contenedor ejecuta las migraciones y luego el script `mercova-web`, que configura Apache en el puerto asignado por Render.

El plan gratuito tiene un disco efímero, bloquea los puertos SMTP y no ejecuta el planificador de Laravel. Por eso las imágenes se guardan en Cloudinary, el correo sale por la API HTTP de Brevo y las tareas programadas se disparan desde GitHub Actions. Las razones de cada decisión están en [docs/decisiones](docs/decisiones/README.md).

## Estado de verificación

La suite tiene 229 pruebas automatizadas en 37 archivos (`php artisan test`), que cubren autenticación, carrito, comisiones, alquileres, reservas, planes, pagos con Wompi, subida de imágenes, correo y permisos. El flujo completo se comprobó manualmente: registro, publicación, compra, pago, notificación y gestión desde `/admin`.

Pendiente de verificar en producción: el envío de correos y la subida de imágenes dependen de que `BREVO_API_KEY` y `CLOUDINARY_URL` estén configuradas en Render. Consulta los [límites conocidos](CHANGELOG.md#límites-conocidos).

## Licencia

Distribuido bajo la licencia MIT.
