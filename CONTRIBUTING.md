# Contribución

## Ramas

| Rama | Uso |
|---|---|
| `main` | Desarrollo integrado; siempre debe pasar las pruebas |
| `principal` | Producción: cada push despliega en Render |
| `feature/<tema>`, `fix/<tema>` | Trabajo en curso; se integra en `main` |

Flujo: rama de trabajo → pull request a `main` → revisión → merge → `main` se integra en `principal` para publicar.

## Commits

- En español, en infinitivo y describiendo el cambio: `Enviar correos por la API HTTP de Brevo`.
- Un commit por cambio coherente. No mezcles formato con lógica.
- El cuerpo explica el porqué cuando no es evidente.

## Código

- Sigue las convenciones de Laravel y de los archivos vecinos antes de crear uno nuevo.
- Genera archivos con `php artisan make:* --no-interaction`.
- PHP 8 con tipos explícitos en parámetros y retornos, promoción de propiedades en el constructor y llaves en todas las estructuras de control.
- Comentarios en PHPDoc, en español, explicando el porqué, no el qué.
- Las reglas de negocio van en `app/Services` o en el modelo, no repetidas en varios controladores.
- Todo contenido de usuario que se inserta en el HTML pasa por `escapeHtml` (`resources/js/lib/escape.js`).
- Antes de hacer commit: `vendor/bin/pint --dirty`.

## Pruebas

Todo cambio de comportamiento incluye o actualiza una prueba de funcionalidad (`php artisan make:test --phpunit NombreTest`). Los cambios solo de texto o de estilos no la necesitan. Detalles en [docs/pruebas.md](docs/pruebas.md).

## Cambios de esquema

- Siempre con una migración nueva (`php artisan make:migration`); nunca se edita una migración que ya llegó a producción.
- Las migraciones se ejecutan solas al desplegar, así que deben poder aplicarse sobre datos existentes: columnas nuevas `nullable` o con valor por defecto.
- Cada migración implementa `down()`.
- Si el cambio afecta a factories o seeders, actualízalos en el mismo commit.

## Dependencias

No agregues paquetes de Composer ni de npm sin acordarlo antes. Cada dependencia nueva se justifica en una [decisión técnica](docs/decisiones/README.md).
