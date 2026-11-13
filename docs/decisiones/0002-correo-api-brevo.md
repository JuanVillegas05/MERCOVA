# 0002. Enviar el correo por la API HTTP de Brevo

**Estado:** vigente

## Contexto

El correo de bienvenida y el de recuperación de contraseña se enviaban por SMTP (`smtp-relay.brevo.com:587`). Al desplegar en el plan gratuito de Render dejaron de llegar: Render bloquea los puertos SMTP salientes. El error quedaba registrado en el log, pero el registro seguía funcionando, así que el fallo no era visible.

## Decisión

Nuevo transporte de correo `brevo` (`App\Mail\Transport\BrevoTransport`) que envía por `POST https://api.brevo.com/v3/smtp/email` usando el cliente HTTP de Laravel. Se activa con `MAIL_MAILER=brevo` y `BREVO_API_KEY`. No agrega dependencias.

## Alternativas descartadas

- **Plan de pago de Render:** habilita SMTP, pero tiene costo.
- **Resend u otro proveedor con paquete propio:** obligaba a cambiar de proveedor y agregar una dependencia.

## Consecuencias y límites

- Funciona en cualquier servidor que permita HTTPS saliente.
- El remitente (`MAIL_FROM_ADDRESS`) debe estar verificado en Brevo.
- Si Brevo rechaza el envío, el registro del usuario no falla: el error se reporta en el log.
- En local se puede seguir usando `smtp` o `log`.
