<?php

namespace App\Services;

/**
 * Valida URLs de retorno que manda el frontend externo (login social,
 * retorno de Wompi). Solo se acepta una URL cuyo esquema+dominio(+puerto)
 * coincida con MERCOVA_FRONTEND_URL; cualquier otra se descarta para no
 * abrir un "open redirect" que entregue tokens o códigos a terceros.
 */
class FrontendRedirect
{
    public function allowed(?string $url): ?string
    {
        $frontendUrl = config('mercova.frontend_url');

        if (! $url || ! $frontendUrl) {
            return null;
        }

        return $this->origin($url) !== null && $this->origin($url) === $this->origin($frontendUrl) ? $url : null;
    }

    private function origin(string $url): ?string
    {
        $parts = parse_url($url);

        if (! isset($parts['scheme'], $parts['host']) || ! in_array($parts['scheme'], ['http', 'https'], true)) {
            return null;
        }

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return strtolower($parts['scheme'].'://'.$parts['host']).$port;
    }
}
