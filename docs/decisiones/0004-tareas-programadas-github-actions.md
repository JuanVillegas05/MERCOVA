# 0004. Disparar las tareas programadas desde GitHub Actions

**Estado:** vigente

## Contexto

Dos tareas necesitan ejecutarse periódicamente: cancelar los pedidos sin pagar vencidos (`orders:cancel-stale`) y conciliar con Wompi los pagos de planes pendientes (`subscriptions:reconcile-payments`). El plan gratuito de Render no ofrece cron jobs ni workers, y el servicio web se suspende cuando no tiene tráfico.

## Decisión

El endpoint `POST /api/cron/run` (`ScheduledTasksController`) ejecuta las dos tareas. Está protegido con `MERCOVA_CRON_SECRET` y limitado a 6 peticiones por minuto. El workflow `.github/workflows/scheduled-tasks.yml` lo llama cada 10 minutos y reintenta para cubrir el arranque en frío del servicio.

## Alternativas descartadas

- **Cron job de Render:** solo existe en planes de pago.
- **Servicio externo de cron (cron-job.org u otros):** funciona igual, pero agrega una cuenta más que administrar y el secreto quedaría fuera del repositorio.

## Consecuencias y límites

- GitHub no garantiza el minuto exacto; los retrasos de algunos minutos no afectan estas tareas.
- El secreto debe ser igual en GitHub y en Render.
- Cada llamada despierta el servicio, así que en la práctica no se suspende.
