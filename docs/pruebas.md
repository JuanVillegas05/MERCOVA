# Pruebas

## Comandos

```bash
php artisan test --compact                          # suite completa
php artisan test --compact tests/Feature/RentalTest.php   # un archivo
php artisan test --compact --filter=test_nombre     # una prueba
vendor/bin/pint --dirty                             # formato de los archivos modificados
```

Las pruebas usan una base de datos aparte llamada `testing` (definida en `phpunit.xml`, con la misma conexión del `.env`) y la reinician en cada prueba con `RefreshDatabase`. **Nunca apuntes las pruebas a la base de datos de producción.** El correo usa el mailer `array` y las colas corren en modo `sync`. No hace falta ningún servicio externo: Wompi, Cloudinary, Brevo y Socialite se simulan con `Http::fake()` y Mockery.

## Cobertura

229 pruebas en 37 archivos.

| Área | Archivos | Pruebas | Qué se comprueba |
|---|---|---|---|
| Pagos con Wompi | `WompiPaymentTest` | 18 | Firma de integridad, checksum del webhook, consulta de la transacción, `amount_mismatch` y que `approved` sea definitivo |
| Planes y suscripciones | `SubscriptionCheckoutTest`, `UserSubscriptionTest`, `PlanAccessControlTest`, `BusinessMembershipTest` | 42 | Cotización, alta, mejora, bajada de plan, renovación, cupos mensuales y funciones por plan |
| Carrito y pedidos | `CartAndCheckoutTest`, `OrderLifecycleTest`, `OrderCommissionTest` | 19 | Stock, un pedido por vendedor, vencimiento y comisión por línea |
| Integridad del marketplace | `MarketplaceIntegrityTest`, `MarketplaceApiTest`, `ProductDetailPageTest` | 17 | Slugs únicos, reservas, borrados seguros y datos públicos sin correo ni teléfono |
| Comisiones | `CommissionCalculatorTest`, `ProductCommissionTest`, `ServiceCommissionTest` | 17 | Precio y ganancia neta, redondeo y copia de la comisión |
| Alquileres y reservas | `RentalTest`, `ServiceBookingOverlapTest`, `AdminRentalsTest` | 16 | Fechas que se cruzan, transiciones de estado y horarios |
| Autenticación | `AuthSecurityTest`, `SocialLoginTest`, `PasswordResetTest`, `AdminEmailListTest` | 24 | Límites de peticiones, tokens, Google y Apple, código de un solo uso y administradores por lista |
| Administración | `AdminManagementTest`, `AdminCategoryManagementTest`, `AdminMembershipPlanManagementTest`, `AdminBusinessManagementTest`, `AdminUserDeletionTest` | 32 | Permisos, categorías, planes, emprendimientos y anonimización de cuentas |
| Reportes | `AdminMetricsExportTest`, `AdminDashboardExportTest` | 10 | Exportación PDF y Excel con las mismas cifras |
| Otros | Reseñas, favoritos, notificaciones, imágenes, correo, tareas programadas y helpers | 34 | Reseña solo con compra, Cloudinary, correo de bienvenida y endpoint de cron |

## Comprobación manual

Antes de cada entrega se recorre este flujo en el navegador:

1. Registro de un cliente nuevo: aparece el letrero de bienvenida y llega el correo.
2. Inicio de sesión como vendedor, creación de un emprendimiento y publicación de un producto con imagen.
3. Desde otro navegador o desde el celular: la imagen se ve, el producto se agrega al carrito y se completa el checkout.
4. Pago en el sandbox de Wompi: el pedido pasa a `processing` y el vendedor recibe la notificación.
5. Solicitud de alquiler con fechas que se cruzan: se rechaza.
6. En `/admin`: el pedido aparece, se cambia su estado y se exportan las métricas a PDF y Excel.

## Criterios de entrega

- `php artisan test` sin fallos.
- `vendor/bin/pint --test` sin cambios pendientes.
- `npm run build` sin errores.
- Todo cambio de comportamiento incluye o actualiza su prueba.
