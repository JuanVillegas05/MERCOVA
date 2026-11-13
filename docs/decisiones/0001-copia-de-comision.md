# 0001. Guardar una copia de la comisión en cada transacción

**Estado:** vigente

## Contexto

Mercova cobra un porcentaje de cada venta, alquiler y reserva. La tasa se configura (`MERCOVA_COMMISSION_RATE`) y puede cambiar. Si la comisión se calculara al consultar, un cambio de tasa alteraría retroactivamente lo que el vendedor ganó y los reportes de ingresos de periodos ya cerrados.

## Decisión

Cada producto, servicio, línea de pedido, alquiler y reserva guarda `commission_rate`, `commission_amount` y `net_amount` en el momento en que se crea. Todo cálculo pasa por `CommissionCalculator`. El total de un pedido es la suma de sus líneas, sin recalcularlo.

## Alternativas descartadas

- **Calcular al vuelo con la tasa actual:** más simple, pero cambia el historial.
- **Tabla histórica de tasas con fechas de vigencia:** correcta, pero obliga a cruzar fechas en cada consulta y no cubre tasas distintas por plan.

## Consecuencias y límites

- Los reportes leen columnas guardadas; no recalculan nada.
- Cambiar la tasa solo afecta a las transacciones nuevas.
- Una publicación existente conserva la comisión con la que se creó hasta que se edita su precio.
