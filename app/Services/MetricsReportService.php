<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Rental;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Fuente única de las métricas que se muestran/exportan desde el panel de
 * administración (Excel, PDF, Word en el futuro). Cualquier formato de
 * exportación debe construirse a partir de build(), nunca recalcular estas
 * cifras por su cuenta, para que Excel/PDF/Word nunca puedan mostrar números
 * distintos para el mismo rango de fechas.
 */
class MetricsReportService
{
    /**
     * @return array<string, mixed>
     */
    public function build(Carbon $from, Carbon $to): array
    {
        $ordersInRange = Order::query()->whereBetween('created_at', [$from, $to]);
        $validOrdersInRange = (clone $ordersInRange)->where('status', '!=', 'cancelled');

        return [
            'range' => ['from' => $from, 'to' => $to],
            'summary' => $this->summary($ordersInRange),
            'financial' => $this->financial($validOrdersInRange),
            'performance' => [
                'top_categories' => $this->topCategories($from, $to),
                'rental_occupancy_rate' => $this->rentalOccupancyRate($from, $to),
                'order_status_breakdown' => $this->orderStatusBreakdown($ordersInRange),
            ],
            'transactions' => (clone $ordersInRange)->with(['buyer', 'seller'])->latest()->get(),
            'users' => User::query()->latest()->get(),
        ];
    }

    /**
     * @param  Builder<Order>  $ordersInRange
     */
    private function summary($ordersInRange): array
    {
        return [
            'total_users' => User::count(),
            'active_businesses' => Business::where('status', 'active')->count(),
            'listings_sale' => Product::where('transaction_type', 'sale')->count(),
            'listings_rent' => Product::where('transaction_type', 'rent')->count(),
            'services' => Service::count(),
            'transactions_in_range' => (clone $ordersInRange)->count(),
        ];
    }

    /**
     * @param  Builder<Order>  $validOrdersInRange  Pedidos del rango que no se cancelaron.
     */
    private function financial($validOrdersInRange): array
    {
        return [
            'total_revenue' => round((float) (clone $validOrdersInRange)->sum('total'), 2),
            'total_commission' => round((float) (clone $validOrdersInRange)->sum('commission_amount'), 2),
            'net_amount' => round((float) (clone $validOrdersInRange)->sum('net_amount'), 2),
            'by_day' => $this->salesByPeriod($validOrdersInRange, 'day'),
            'by_week' => $this->salesByPeriod($validOrdersInRange, 'week'),
            'by_month' => $this->salesByPeriod($validOrdersInRange, 'month'),
        ];
    }

    /**
     * Agrupa ventas por día/semana ISO/mes. MySQL (producción) y SQLite
     * (local y tests, ver .env DB_CONNECTION) no comparten ninguna función
     * de formato de fecha: no hay un solo `selectRaw` portable entre los
     * dos, así que se elige la expresión según el driver activo.
     *
     * @param  Builder<Order>  $validOrdersInRange
     * @return Collection<int, array{period: string, revenue: float, transactions: int}>
     */
    private function salesByPeriod($validOrdersInRange, string $granularity): Collection
    {
        $periodExpression = match (DB::connection()->getDriverName()) {
            'sqlite' => match ($granularity) {
                'day' => "strftime('%Y-%m-%d', created_at)",
                'week' => "strftime('%Y-%W', created_at)",
                default => "strftime('%Y-%m', created_at)",
            },
            default => match ($granularity) {
                'day' => "DATE_FORMAT(created_at, '%Y-%m-%d')",
                'week' => "DATE_FORMAT(created_at, '%x-%v')",
                default => "DATE_FORMAT(created_at, '%Y-%m')",
            },
        };

        return (clone $validOrdersInRange)
            ->selectRaw("{$periodExpression} as period, SUM(total) as revenue, COUNT(*) as transactions")
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->map(fn ($row) => [
                'period' => $row->period,
                'revenue' => round((float) $row->revenue, 2),
                'transactions' => (int) $row->transactions,
            ]);
    }

    /**
     * Top 5 categorías por ingresos vendidos en el rango (no por número de
     * publicaciones): refleja qué se está vendiendo de verdad, no solo qué
     * hay más publicado.
     *
     * @return Collection<int, array{name: string, revenue: float, units: int}>
     */
    private function topCategories(Carbon $from, Carbon $to, int $limit = 5): Collection
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->where('orders.status', '!=', 'cancelled')
            ->whereBetween('orders.created_at', [$from, $to])
            ->selectRaw('categories.name as name, SUM(order_items.total) as revenue, SUM(order_items.quantity) as units')
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'revenue' => round((float) $row->revenue, 2),
                'units' => (int) $row->units,
            ]);
    }

    /**
     * % de productos de alquiler activos que tuvieron al menos una solicitud
     * de alquiler (en cualquier estado) dentro del rango. Es una medida de
     * rotación del catálogo de alquiler, no de días-ocupados reales.
     */
    private function rentalOccupancyRate(Carbon $from, Carbon $to): float
    {
        $rentalProductsCount = Product::where('transaction_type', 'rent')->where('status', 'active')->count();

        if ($rentalProductsCount === 0) {
            return 0.0;
        }

        $rentedProductsCount = Rental::query()
            ->whereBetween('created_at', [$from, $to])
            ->distinct('product_id')
            ->count('product_id');

        return round(($rentedProductsCount / $rentalProductsCount) * 100, 1);
    }

    /**
     * @param  Builder<Order>  $ordersInRange
     * @return Collection<int, array{status: string, label: string, count: int}>
     */
    private function orderStatusBreakdown($ordersInRange): Collection
    {
        $labels = [
            'pending' => 'Pendiente',
            'processing' => 'En proceso',
            'completed' => 'Completado',
            'cancelled' => 'Cancelado',
        ];

        return collect($labels)->map(fn ($label, $status) => [
            'status' => $status,
            'label' => $label,
            'count' => (clone $ordersInRange)->where('status', $status)->count(),
        ])->values();
    }
}
