<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

/**
 * Subida de imágenes a Cloudinary (API REST, subida firmada). Se usa en
 * producción porque el disco de Render es efímero: lo que se guarda en
 * storage/ desaparece en cada despliegue o reinicio.
 *
 * Credenciales: CLOUDINARY_URL=cloudinary://{api_key}:{api_secret}@{cloud_name}
 * (Dashboard de Cloudinary → "API environment variable"). El secreto nunca
 * sale del backend: firma cada subida aquí.
 */
class CloudinaryService
{
    /**
     * Transformación aplicada al subir: limita el lado mayor a 1600 px y deja
     * que Cloudinary elija calidad, para no guardar fotos de 10 MB.
     */
    private const INCOMING_TRANSFORMATION = 'c_limit,w_1600,h_1600,q_auto';

    public function __construct(
        private readonly ?string $cloudName,
        private readonly ?string $apiKey,
        private readonly ?string $apiSecret,
        private readonly string $folder,
    ) {}

    public static function make(): self
    {
        $credentials = parse_url((string) config('services.cloudinary.url'));

        return new self(
            cloudName: $credentials['host'] ?? null,
            apiKey: isset($credentials['user']) ? rawurldecode($credentials['user']) : null,
            apiSecret: isset($credentials['pass']) ? rawurldecode($credentials['pass']) : null,
            folder: (string) config('services.cloudinary.folder', 'mercova'),
        );
    }

    public function enabled(): bool
    {
        return filled($this->cloudName) && filled($this->apiKey) && filled($this->apiSecret);
    }

    /**
     * Sube la imagen y devuelve su URL pública (https).
     *
     * @throws ConnectionException|RequestException
     */
    public function uploadImage(UploadedFile $file, string $subfolder): string
    {
        $params = [
            'folder' => trim($this->folder.'/'.$subfolder, '/'),
            'timestamp' => (string) now()->timestamp,
            'transformation' => self::INCOMING_TRANSFORMATION,
        ];

        $response = Http::connectTimeout(5)
            ->timeout(30)
            ->acceptJson()
            ->attach('file', (string) file_get_contents($file->getRealPath()), $file->hashName())
            ->post("https://api.cloudinary.com/v1_1/{$this->cloudName}/image/upload", [
                ...$params,
                'api_key' => $this->apiKey,
                'signature' => $this->sign($params),
            ])
            ->throw();

        return (string) $response->json('secure_url');
    }

    /**
     * Firma de Cloudinary: SHA-1 de los parámetros ordenados por nombre
     * ("a=1&b=2") seguidos del secreto.
     *
     * @param  array<string, string>  $params
     */
    public function sign(array $params): string
    {
        ksort($params);

        $toSign = collect($params)
            ->map(fn (string $value, string $key) => "{$key}={$value}")
            ->join('&');

        return sha1($toSign.$this->apiSecret);
    }
}
