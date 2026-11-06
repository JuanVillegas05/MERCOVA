<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Códigos de un solo uso para terminar el login social sin poner el token
 * Sanctum en la URL (donde quedaría en el historial del navegador, logs de
 * servidores y cabeceras Referer). El callback de Google/Apple redirige con
 * ?code=..., y el frontend lo canjea por el token en
 * POST /api/auth/social/exchange. El código vive 2 minutos y se borra al
 * primer canje.
 */
class SocialLoginCodes
{
    public const TTL_SECONDS = 120;

    public function issue(User $user): string
    {
        $code = Str::random(64);

        Cache::put($this->cacheKey($code), $user->id, self::TTL_SECONDS);

        return $code;
    }

    /**
     * Devuelve el usuario dueño del código y lo invalida; null si el código
     * no existe, ya se usó o expiró.
     */
    public function redeem(string $code): ?User
    {
        $userId = Cache::pull($this->cacheKey($code));

        return $userId ? User::find($userId) : null;
    }

    /**
     * Se guarda el hash, no el código, por si el almacenamiento de caché se
     * filtrara.
     */
    private function cacheKey(string $code): string
    {
        return 'social-login-code:'.hash('sha256', $code);
    }
}
