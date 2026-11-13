# Seguridad

## Reportar un fallo

No publiques vulnerabilidades en issues públicos. Escribe a los responsables del repositorio con la descripción, los pasos para reproducirla y su impacto. La recepción se confirma en un máximo de 72 horas.

## Secretos

- Las credenciales solo existen en `.env` (local) y en las variables de entorno de Render y de GitHub Actions. `.env` está en `.gitignore`.
- [.env.example](.env.example) documenta los nombres de las variables, nunca sus valores.
- Si un secreto llega a un commit, se considera comprometido: se rota en el proveedor (Wompi, Brevo, Cloudinary, base de datos) aunque el commit se borre.

## Autenticación y permisos

- Tokens de Sanctum por cabecera `Authorization`, sin sesión ni cookies en la API.
- Contraseñas con hash bcrypt.
- 10 intentos por minuto por IP en login, registro, recuperación de contraseña y canje de login social.
- Desactivar o eliminar una cuenta revoca todos sus tokens al instante.
- El rol `admin` no se puede obtener desde el registro público; solo por `MERCOVA_ADMIN_EMAILS`. Todo `/api/admin/*` pasa por el middleware `admin`.
- Login social: solo se vincula una cuenta existente si el proveedor confirma que el correo está verificado (`email_verified`). El token nunca viaja en la URL, sino un código de un solo uso válido por 2 minutos.

## Pagos

- Mercova nunca recibe ni guarda datos de tarjeta; el pago ocurre en Wompi.
- Los enlaces de pago llevan firma de integridad.
- El webhook valida el checksum y vuelve a consultar la transacción a la API de Wompi; no confía en el cuerpo del evento.
- Un monto distinto al esperado deja el pago en `amount_mismatch` y no lo aprueba.

## Privacidad

- Los endpoints públicos no exponen correo ni teléfono de los usuarios (`User::PUBLIC_PROFILE_COLUMNS`).
- Eliminar una cuenta anonimiza los datos personales y libera el correo, sin borrar los pedidos de otras personas.
- Las respuestas de `/api` llevan `Cache-Control: no-store`.

## Frontend

- Todo contenido de usuario se escapa con `escapeHtml` antes de insertarlo en el HTML (protección contra XSS almacenado).
- Las redirecciones de pago y de login social solo aceptan URL del frontend configurado en `MERCOVA_FRONTEND_URL`.

## Infraestructura

- Conexión a la base de datos con TLS verificado.
- `APP_DEBUG=false` en producción.
- `POST /api/cron/run` exige `MERCOVA_CRON_SECRET`.
- Las cuentas de prueba del seeder (`*@mercova.test`) no deben existir en producción.
