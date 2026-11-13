# Pagos y planes

Mercova cobra con el **Web Checkout de Wompi** (tarjeta, PSE, Nequi y Bancolombia). El backend nunca recibe datos de tarjeta: genera un enlace firmado, el usuario paga en Wompi y el resultado llega por webhook.

## Configuración

| Variable | Uso |
|---|---|
| `WOMPI_PUBLIC_KEY` | Llave pública. Si empieza por `pub_prod_` se usa producción; si no, el sandbox |
| `WOMPI_PRIVATE_KEY` | Consulta de transacciones a la API de Wompi |
| `WOMPI_INTEGRITY_SECRET` | Firma de integridad del enlace de pago (referencia, monto y moneda) |
| `WOMPI_EVENTS_SECRET` | Validación del checksum de cada webhook |

En el panel de Wompi (Desarrolladores), la URL de eventos debe apuntar a `https://<dominio>/api/webhooks/wompi`. Sin llaves configuradas, `GET /api/payments/config` informa que la pasarela está deshabilitada.

## Flujo de un pedido

```
Carrito ─► POST /api/checkout ─► pedidos "pending" (uno por vendedor)
                                        │
                     GET /api/orders/{id}/payment ─► enlace Wompi firmado
                                        │
                                Usuario paga en Wompi
                                        │
          POST /api/webhooks/wompi ─► se valida el checksum
                                        │
               se consulta la transacción a la API de Wompi
                                        │
          approved ─► pedido "processing" · notificación al vendedor
```

**Defensas del webhook.** El checksum del evento se valida con `WOMPI_EVENTS_SECRET` antes de hacer nada. El estado y el monto no se toman del cuerpo del evento: se vuelven a consultar a la API de Wompi con la llave privada. Si el monto cobrado no coincide con el del pedido, el pago queda en `amount_mismatch` y no se aprueba.

**Estados de pago.**

| Wompi | Mercova | Efecto |
|---|---|---|
| `APPROVED` | `approved` | Definitivo: ningún evento posterior lo degrada |
| `DECLINED`, `ERROR` | `declined` | El comprador puede reintentar; no se cancela ni se devuelve stock |
| `VOIDED` | `voided` | Igual que `declined` |
| Otro | `pending` | Se espera el siguiente evento |

Un pedido sin pagar vence a las 48 horas (`MERCOVA_ORDER_EXPIRY_HOURS`). El comando `orders:cancel-stale` lo cancela y devuelve el stock.

## Comisión

`CommissionCalculator` aplica la tasa global (6 %, `MERCOVA_COMMISSION_RATE`). Al publicar, el vendedor indica el precio de venta o la ganancia neta que quiere recibir, y el sistema calcula el otro valor. `POST /api/products/commission-preview` muestra el desglose sin crear nada.

Cada transacción guarda una copia (`commission_rate`, `commission_amount`, `net_amount`) en el momento en que ocurre. Por eso un cambio de tasa solo afecta a las transacciones nuevas.

## Planes de suscripción

El cobro de planes lo maneja `SubscriptionCheckoutService`:

1. `GET /api/subscriptions/checkout/preview` cotiza el cambio: alta, mejora, renovación o bajada de plan.
2. `POST /api/subscriptions/checkout` registra un `SubscriptionPayment` en estado `pending`, con referencia `SUB-...`, y devuelve el enlace de Wompi. La suscripción todavía no cambia. Si el usuario vuelve a pulsar "Pagar", se reutiliza el intento pendiente para no cobrar dos veces.
3. El webhook activa (o programa) la suscripción solo si el pago quedó aprobado y el monto coincide.

No hay cobros automáticos recurrentes. Cada periodo es un pago independiente: un plan que no se renueva vence y el usuario vuelve al plan gratuito. Bajar de plan no genera reembolso; el plan inferior empieza cuando termina el periodo ya pagado.

## Conciliación

Si un webhook se pierde o llega tarde, `subscriptions:reconcile-payments` consulta a Wompi los pagos de planes que siguen abiertos (de los últimos 2 días) y aplica su estado real. Los que llevan más de 2 días abiertos pasan a `expired`. El mismo método se usa cuando el frontend vuelve de la pasarela. Cómo se programan estas tareas en producción se explica en [Despliegue](despliegue.md#tareas-programadas).
