# 0005. Usar Wompi como pasarela de pagos

**Estado:** vigente; reemplaza la integración con Mercado Pago

## Contexto

Los primeros pagos usaban métodos simulados y luego Mercado Pago (Checkout Pro). El público de Mercova está en Colombia y los medios más usados son PSE, Nequi y Bancolombia.

## Decisión

Integrar el Web Checkout de Wompi (`WompiService`, `PaymentService`):

- El backend genera un enlace con firma de integridad (referencia, monto en centavos y moneda) usando `WOMPI_INTEGRITY_SECRET`.
- El resultado llega a `POST /api/webhooks/wompi`, que valida el checksum con `WOMPI_EVENTS_SECRET` y vuelve a consultar la transacción a la API de Wompi.
- El sandbox o producción se elige según el prefijo de la llave pública (`pub_test_` o `pub_prod_`).

## Alternativas descartadas

- **Mercado Pago:** funcionaba, pero con menos cobertura de medios locales; su código y sus variables se retiraron.
- **Formulario de pago propio con tokenización:** exige más requisitos de cumplimiento PCI y no aporta nada para este proyecto.

## Consecuencias y límites

- Mercova nunca recibe datos de tarjeta.
- No hay cobros recurrentes automáticos: cada periodo de un plan es un pago independiente.
- Un webhook perdido se recupera con `subscriptions:reconcile-payments` o cuando el usuario vuelve de la pasarela.
