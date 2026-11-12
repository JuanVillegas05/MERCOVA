<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Comisión MERCOVA
    |--------------------------------------------------------------------------
    |
    | Porcentaje que MERCOVA retiene sobre cada publicación/venta/alquiler.
    | Nunca se debe escribir "0.06" directamente en controladores, modelos
    | o vistas: siempre leer este valor a través de App\Services\CommissionCalculator.
    |
    */
    'commission' => [
        'rate' => (float) env('MERCOVA_COMMISSION_RATE', 0.06),
    ],

    /*
    |--------------------------------------------------------------------------
    | Moneda
    |--------------------------------------------------------------------------
    */
    'currency' => [
        'code' => 'COP',
        'locale' => 'es-CO',
    ],

    /*
    |--------------------------------------------------------------------------
    | Métodos de pago
    |--------------------------------------------------------------------------
    |
    | Lista centralizada de métodos de pago disponibles en el checkout.
    | Hoy no hay integración real con ninguna pasarela: el pago queda
    | registrado como "pending" para que un proveedor real se conecte
    | después sin tener que tocar el checkout ni el modelo de datos.
    |
    */
    'payment_methods' => [
        'debit_card' => 'Tarjeta débito',
        'credit_card' => 'Tarjeta crédito',
        'pse' => 'PSE',
        'addi' => 'Addi',
        'sistecredito' => 'Sistecrédito',
    ],

    /*
    |--------------------------------------------------------------------------
    | Administradores por correo
    |--------------------------------------------------------------------------
    |
    | Cualquier cuenta cuyo correo esté en esta lista se vuelve administrador
    | automáticamente al registrarse o iniciar sesión (sin pasar por ninguna
    | pantalla de registro de admin). Se controla por variable de entorno,
    | nunca hay que tocar código para agregar o quitar un administrador.
    | Formato en .env: MERCOVA_ADMIN_EMAILS=correo1@mercova.test,correo2@mercova.test
    |
    */
    'admin_emails' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('MERCOVA_ADMIN_EMAILS', ''))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Pasarela de pagos (Wompi)
    |--------------------------------------------------------------------------
    |
    | Llaves de https://comercios.wompi.co → Desarrolladores. Sin llave
    | pública + secreto de integridad, el checkout con pago real queda
    | deshabilitado pero el resto de la app funciona. El ambiente (sandbox o
    | producción) lo decide la propia llave pública (pub_test_ / pub_prod_).
    |
    | - private_key: consulta de transacciones por referencia (conciliación).
    | - integrity_secret: firma "signature:integrity" del Web Checkout, para
    |   que nadie pueda cambiar el monto del enlace de pago.
    | - events_secret: valida el checksum de cada evento del webhook.
    |
    */
    'wompi' => [
        'public_key' => env('WOMPI_PUBLIC_KEY'),
        'private_key' => env('WOMPI_PRIVATE_KEY'),
        'integrity_secret' => env('WOMPI_INTEGRITY_SECRET'),
        'events_secret' => env('WOMPI_EVENTS_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Expiración de pedidos sin pagar
    |--------------------------------------------------------------------------
    |
    | Un pedido queda "pending" con el stock ya descontado desde que se crea.
    | Si el comprador nunca llega a pagar (cierra el checkout de Mercado Pago sin
    | terminar, por ejemplo) o su último intento fue rechazado y no reintenta, el
    | comando "orders:cancel-stale" (programado cada hora) cancela esos
    | pedidos tras esta cantidad de horas y repone su stock.
    |
    */
    'order_expiry_hours' => (int) env('MERCOVA_ORDER_EXPIRY_HOURS', 48),

    /*
    |--------------------------------------------------------------------------
    | Login social
    |--------------------------------------------------------------------------
    |
    | Mientras no haya credenciales reales (Google Cloud / Apple Developer),
    | estos flags quedan en false y los botones correspondientes del login
    | no se muestran en vez de fallar al hacer clic.
    |
    */
    'social_login' => [
        'google' => filled(env('GOOGLE_CLIENT_ID')) && filled(env('GOOGLE_CLIENT_SECRET')),
        'apple' => filled(env('APPLE_CLIENT_ID')) && filled(env('APPLE_CLIENT_SECRET')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Frontend externo (login social)
    |--------------------------------------------------------------------------
    |
    | El frontend propio del equipo (fuera de este repo) puede iniciar el
    | login con Google/Apple mandando a este backend y pidiendo que, al
    | terminar, regrese el token a una URL suya (?redirect_uri=...). Por
    | seguridad (evitar un "open redirect") solo se acepta ese parámetro si
    | su dominio coincide exactamente con MERCOVA_FRONTEND_URL; si no
    | coincide (o no está configurada), se ignora y el token se entrega en
    | la página propia de este backend (/auth/callback).
    |
    */
    'frontend_url' => env('MERCOVA_FRONTEND_URL'),
];
