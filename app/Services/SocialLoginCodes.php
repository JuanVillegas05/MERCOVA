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

    /**
     * $isNewUser viaja con el código para que el frontend sepa si debe
     * mostrar el letrero de bienvenida (cuenta recién creada) o no.
     */
    public function issue(User $user, bool $isNewUser = false): string
    {
        $code = Str::random(64);

        Cache::put($this->cacheKey($code), [
            'user_id' => $user->id,
            'is_new_user' => $isNewUser,
        ], self::TTL_SECONDS);

        return $code;
    }

    /**
     * Devuelve el usuario dueño del código (y si la cuenta se acaba de crear)
     * y lo invalida; null si el código no existe, ya se usó o expiró.
     *
     * @return array{user: User, is_new_user: bool}|null
     */
    public function redeem(string $code): ?array
    {
        $payload = Cache::pull($this->cacheKey($code));

        if (! is_array($payload)) {
            return null;
        }

        $user = User::find($payload['user_id']);

        return $user ? ['user' => $user, 'is_new_user' => (bool) $payload['is_new_user']] : null;
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
