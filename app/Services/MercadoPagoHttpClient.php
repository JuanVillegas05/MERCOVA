<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Net\MPHttpClient;
use MercadoPago\Net\MPRequest;
use MercadoPago\Net\MPResponse;

/**
 * Transporte del SDK oficial de Mercado Pago (mercadopago/dx-php) sobre el
 * cliente HTTP de Laravel en vez de cURL directo. Así las llamadas a Mercado
 * Pago tienen timeouts explícitos y se pueden simular con Http::fake() en los
 * tests, igual que el resto de integraciones de la app.
 *
 * No reintenta: la creación de pagos lleva X-Idempotency-Key y quien llama
 * decide qué hacer con un error (el webhook devuelve 500 y Mercado Pago
 * reintenta solo; el comando de conciliación lo vuelve a intentar luego).
 */
class MercadoPagoHttpClient implements MPHttpClient
{
    /**
     * @throws MPApiException Si Mercado Pago responde con un status distinto de 2xx.
     */
    public function send(MPRequest $request): MPResponse
    {
        $pendingRequest = Http::withHeaders($this->headers($request))
            ->connectTimeout(5)
            ->timeout(20);

        if ($request->getPayload() !== null) {
            $pendingRequest->withBody($request->getPayload(), 'application/json');
        }

        $response = $pendingRequest->send($request->getMethod(), MercadoPagoConfig::$BASE_URL.$request->getUri());

        $mpResponse = new MPResponse($response->status(), (array) $response->json());

        if (! $response->successful()) {
            throw new MPApiException('Mercado Pago respondió con un error.', $mpResponse);
        }

        return $mpResponse;
    }

    /**
     * El SDK arma los headers como líneas "Nombre: valor" (formato cURL).
     *
     * @return array<string, string>
     */
    private function headers(MPRequest $request): array
    {
        $headers = [];

        foreach ($request->getHeaders() ?? [] as $line) {
            [$name, $value] = array_pad(explode(':', $line, 2), 2, '');
            $headers[trim($name)] = trim($value);
        }

        return $headers;
    }
}
