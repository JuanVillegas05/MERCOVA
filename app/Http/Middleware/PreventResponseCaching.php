<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Respuestas con datos de la cuenta (login, registro, perfil...) que ningún
 * navegador, proxy ni caché HTTP del cliente debe guardar: si se reutilizan,
 * otra sesión en el mismo navegador podría ver los datos del usuario
 * anterior.
 *
 * No se usa el middleware `cache.headers` de Laravel porque ignora POST y las
 * respuestas de error, y aquí deben cubrirse todas.
 */
class PreventResponseCaching
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Cache-Control', 'no-store, private, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }
}
