<?php

namespace App\Services;

use App\Models\User;

class CommissionCalculator
{
    /**
     * $rateOverride solo lo usa forSeller(): el contenedor siempre construye
     * esta clase sin él, con la comisión general de config/mercova.php.
     */
    public function __construct(private readonly ?float $rateOverride = null) {}

    public function rate(): float
    {
        return $this->rateOverride ?? (float) config('mercova.commission.rate', 0.06);
    }

    /**
     * Calculadora con la comisión que le corresponde a $seller según su
     * plan (p. ej. Élite cobra menos). Si su plan no define comisión propia,
     * o no hay vendedor, devuelve la calculadora con la comisión general.
     */
    public function forSeller(?User $seller): self
    {
        $planRate = $seller?->currentPlan()?->commission_rate;

        return $planRate === null ? $this : new self((float) $planRate);
    }

    /**
     * Precio que se debe publicar para que, tras descontar la comisión,
     * al vendedor le queden $net pesos netos.
     */
    public function grossFromNet(float $net): float
    {
        return round($net / (1 - $this->rate()), 2);
    }

    /**
     * Lo que recibe el vendedor si el producto se publica en $gross pesos.
     */
    public function netFromGross(float $gross): float
    {
        return round($gross * (1 - $this->rate()), 2);
    }

    public function commissionAmount(float $gross): float
    {
        return round($gross * $this->rate(), 2);
    }

    /**
     * Desglose completo a partir del precio publicado (lo que paga el comprador).
     */
    public function breakdown(float $gross): array
    {
        return [
            'gross_price' => $gross,
            'commission_rate' => $this->rate(),
            'commission_amount' => $this->commissionAmount($gross),
            'net_amount' => $this->netFromGross($gross),
        ];
    }

    /**
     * Desglose completo a partir de la ganancia neta deseada por el vendedor.
     */
    public function breakdownFromNet(float $net): array
    {
        $gross = $this->grossFromNet($net);

        return [
            'gross_price' => $gross,
            'commission_rate' => $this->rate(),
            'commission_amount' => round($gross - $net, 2),
            'net_amount' => $net,
        ];
    }

    /**
     * El vendedor da exactamente uno de los dos: el precio de venta o la
     * ganancia neta que quiere recibir. Resuelve el desglose a partir del que
     * venga. Devuelve null si no vino ninguno (el controlador decide qué
     * mensaje de error mostrar en ese caso).
     */
    public function resolveBreakdown(?float $price, ?float $desiredNetPrice): ?array
    {
        if (empty($price) && empty($desiredNetPrice)) {
            return null;
        }

        return ! empty($desiredNetPrice)
            ? $this->breakdownFromNet($desiredNetPrice)
            : $this->breakdown($price);
    }
}
