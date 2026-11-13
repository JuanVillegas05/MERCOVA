# Documentación de Mercova

Punto de entrada: [README del proyecto](../README.md). El código es la fuente de verdad; esta documentación explica sus contratos, sus decisiones y cómo operarlo.

| Documento | Qué resuelve |
|---|---|
| [Arquitectura](arquitectura.md) | Componentes, modelo de datos, roles y reglas del dominio |
| [API](api.md) | Rutas, autenticación, límites de peticiones y formato de errores |
| [Pagos y planes](pagos.md) | Integración con Wompi, comisiones, suscripciones y conciliación |
| [Despliegue](despliegue.md) | Docker, Render, variables de entorno y tareas programadas |
| [Pruebas](pruebas.md) | Comandos, cobertura, comprobación manual y criterios de entrega |
| [Decisiones](decisiones/README.md) | Motivos, alternativas y límites de cada decisión técnica |
| [Contribución](../CONTRIBUTING.md) | Convenciones, ramas, commits y cambios de esquema |
| [Seguridad](../SECURITY.md) | Secretos, permisos, privacidad y reporte de fallos |
| [Historial](../CHANGELOG.md) | Cambios entregados y límites conocidos |

El código incluye comentarios (PHPDoc) en los puntos que necesitan contexto: concurrencia, comisiones, firma de webhooks, anonimización de cuentas y restricciones de Render.
