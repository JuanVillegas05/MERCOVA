# Despliegue

## Producción actual

| Componente | Servicio |
|---|---|
| Aplicación (API + vistas Blade) | Render, servicio web `mercova-api`, plan gratuito, región Oregón |
| Base de datos | TiDB Cloud (compatible con MySQL), con TLS obligatorio |
| Imágenes | Cloudinary |
| Correo | Brevo, por API HTTP |
| Pagos | Wompi |
| Tareas programadas | GitHub Actions |

URL: [mercova-api.onrender.com](https://mercova-api.onrender.com). Render despliega automáticamente cada push a la rama `principal`.

## Imagen Docker

El [Dockerfile](../Dockerfile) se construye en dos etapas:

1. **assets** (Node 22): `npm ci` y `npm run build` generan los assets de Vite y Tailwind.
2. **aplicación** (PHP 8.5 + Apache): extensiones `bcmath`, `exif`, `gd`, `intl`, `pdo_mysql` y `zip`; `composer install --no-dev`; OPcache activado; Apache con DocumentRoot en `public/`.

Las variables de entorno llegan cuando el contenedor arranca, no al construir la imagen. Por eso la caché de configuración y de rutas se genera al iniciar.

**Arranque.** En [render.yaml](../render.yaml), `startCommand` ejecuta `php artisan migrate --force && mercova-web`. El script `mercova-web` configura Apache en el `$PORT` asignado por Render, ejecuta `php artisan optimize`, ajusta los permisos de `storage/` y arranca Apache. Si se reemplaza por `apache2-foreground` directamente, Apache queda en el puerto 80 y el health check (`/up`) falla.

## Variables de entorno

Los valores marcados como secretos (`sync: false` en `render.yaml`) se configuran en Render → mercova-api → Environment. Nunca se suben al repositorio.

| Variable | Obligatoria | Uso |
|---|---|---|
| `APP_KEY`, `APP_URL` | Sí | Cifrado y URL pública |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Sí | Base de datos |
| `MYSQL_ATTR_SSL_CA` | No | Certificado TLS; si está vacío se usa el del sistema |
| `MAIL_MAILER=brevo`, `BREVO_API_KEY`, `MAIL_FROM_ADDRESS` | Sí | Correo; el remitente debe estar verificado en Brevo |
| `CLOUDINARY_URL` | Sí | `cloudinary://{api_key}:{api_secret}@{cloud_name}`; sin ella la subida de imágenes responde 503 |
| `WOMPI_PUBLIC_KEY`, `WOMPI_PRIVATE_KEY`, `WOMPI_INTEGRITY_SECRET`, `WOMPI_EVENTS_SECRET` | Sí | Pagos (ver [Pagos](pagos.md)) |
| `MERCOVA_ADMIN_EMAILS` | Sí | Correos que serán administradores, separados por coma |
| `MERCOVA_CRON_SECRET` | Sí | Protege `POST /api/cron/run` |
| `MERCOVA_FRONTEND_URL` | No | Frontend externo permitido para redirecciones de pago y login social |
| `GOOGLE_CLIENT_ID/SECRET`, `APPLE_CLIENT_ID/SECRET` | No | Login social; los botones aparecen solo si están configuradas |
| `MERCOVA_COMMISSION_RATE` | No | Comisión (por defecto `0.06`) |

## Restricciones del plan gratuito de Render

| Restricción | Solución en Mercova |
|---|---|
| El disco se borra en cada despliegue o reinicio | Imágenes en Cloudinary; en producción no se guardan en disco ([decisión 0003](decisiones/0003-imagenes-cloudinary.md)) |
| Puertos SMTP salientes bloqueados | Correo por la API HTTP de Brevo ([decisión 0002](decisiones/0002-correo-api-brevo.md)) |
| Sin worker de colas ni `schedule:run` | `QUEUE_CONNECTION=sync` y cron externo con GitHub Actions ([decisión 0004](decisiones/0004-tareas-programadas-github-actions.md)) |
| El servicio se suspende sin tráfico (arranque en frío de ~50 s) | El workflow de cron reintenta la petición |

## Tareas programadas

| Comando | Frecuencia | Qué hace |
|---|---|---|
| `orders:cancel-stale` | Cada hora (local) / cada 10 min (producción) | Cancela pedidos sin pagar vencidos y devuelve el stock |
| `subscriptions:reconcile-payments` | Cada 10 min | Concilia con Wompi los pagos de planes pendientes |

En local las ejecuta `php artisan schedule:work`. En producción, el workflow [scheduled-tasks.yml](../.github/workflows/scheduled-tasks.yml) llama cada 10 minutos a `POST /api/cron/run` con el secreto `MERCOVA_CRON_SECRET`, que debe existir con el mismo valor en GitHub (Settings → Secrets and variables → Actions) y en Render.

## Publicar una nueva versión

```bash
git checkout principal
git merge main
git push origin principal
```

Antes de publicar, ejecuta `php artisan test` y `vendor/bin/pint --test`. Las migraciones se aplican solas al arrancar el contenedor, así que una migración que falla impide que la nueva versión arranque; Render mantiene la anterior.

## Desarrollo local con Docker (Sail)

`compose.yaml` levanta la aplicación con Laravel Sail en el puerto 8000. Si aparece "attempt to write a readonly database" o "Permission denied" en `storage/`, es por la diferencia de usuarios entre Windows/WSL y el contenedor:

```bash
docker compose exec laravel.test chmod -R g+w /var/www/html
```
