<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Order;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Las 4 tarjetas de "Resumen y crecimiento" del panel admin (dinero
 * ingresado, clientes activos, pedidos, promedio por pedido), más el estado
 * de los pedidos, el origen de las solicitudes y la gráfica de crecimiento
 * de 14 días. Extraído de AdminController::dashboard() para que la
 * exportación a PDF/Excel (AdminDashboardExportController) muestre
 * exactamente los mismos números que ve el admin en pantalla, sin
 * recalcularlos por separado.
 */
class DashboardSnapshotService
{
    public const RANGES = ['today', 'week', 'month', 'all'];

    /**
     * @return array<string, mixed>
     */
    public function build(string $range): array
    {
        $range = in_array($range, self::RANGES, true) ? $range : 'today';
        $start = $this->rangeStart($range);

        $ordersInRange = Order::query()->when($start, fn ($q) => $q->where('created_at', '>=', $start));
        $bookingsInRange = Booking::query()->when($start, fn ($q) => $q->where('created_at', '>=', $start));
        $rentalsInRange = Rental::query()->when($start, fn ($q) => $q->where('created_at', '>=', $start));

        // "Dinero ingresado": ventas registradas que no se cancelaron. No se
        // limita a `payment_status: approved` porque la pasarela (Mercado Pago) es
        // opcional — muchos pedidos siguen su ciclo de vida por fuera de ella
        // (pago contra entrega, efectivo, etc.) y el panel debe reflejar la
        // operación real de la tienda, no solo lo cobrado por la pasarela.
        $revenue = (clone $ordersInRange)->where('status', '!=', 'cancelled')->sum('total');
        $ordersCount = (clone $ordersInRange)->count();

        $statusLabels = [
            'pending' => 'Pendiente',
            'processing' => 'En proceso',
            'completed' => 'Completado',
            'cancelled' => 'Cancelado',
        ];
        $statusColors = [
            'pending' => '#f59e0b',
            'processing' => '#6366f1',
            'completed' => '#10b981',
            'cancelled' => '#ef4444',
        ];
        $statusBreakdown = collect($statusLabels)->map(fn ($label, $status) => [
            'status' => $status,
            'label' => $label,
            'color' => $statusColors[$status],
            'count' => (clone $ordersInRange)->where('status', $status)->count(),
        ])->values();

        // "Origen de las solicitudes": a diferencia de un bot de WhatsApp o
        // una app aparte, en Mercova todo entra por la misma web; lo que sí
        // distingue el origen real de cada solicitud es el tipo de flujo que
        // disparó: compra directa, reserva de servicio o solicitud de
        // alquiler. Cada uno tiene su propio ciclo de vida y tabla.
        $requestOrigin = collect([
            ['type' => 'orders', 'label' => 'Compras', 'color' => '#10b981', 'count' => $ordersCount],
            ['type' => 'bookings', 'label' => 'Reservas de servicio', 'color' => '#6366f1', 'count' => (clone $bookingsInRange)->count()],
            ['type' => 'rentals', 'label' => 'Alquileres', 'color' => '#f59e0b', 'count' => (clone $rentalsInRange)->count()],
        ]);

        return [
            'range' => $range,
            'summary' => [
                'revenue' => round((float) $revenue, 2),
                'customers' => User::where('is_active', true)->count(),
                'orders_count' => $ordersCount,
                'average_order_value' => $ordersCount > 0 ? round($revenue / $ordersCount, 2) : 0,
            ],
            'order_status_breakdown' => $statusBreakdown,
            'request_origin_breakdown' => $requestOrigin,
            // "Crecimiento de la app": usuarios y pedidos nuevos por día de
            // las últimas 2 semanas, sin importar el rango elegido arriba
            // (ese selector es para las 4 tarjetas, este panel siempre mira
            // la misma ventana para poder comparar día contra día).
            'growth' => $this->dailyGrowth(14),
        ];
    }

    /**
     * Inicio del rango seleccionado en el selector "Hoy" del panel. `null`
     * para "all" significa "sin filtro de fecha" (no "desde epoch").
     */
    private function rangeStart(string $range): ?Carbon
    {
        return match ($range) {
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            'all' => null,
            default => now()->startOfDay(),
        };
    }

    /**
     * Usuarios y pedidos nuevos agrupados por día, para la gráfica de
     * crecimiento. Se rellenan los días sin actividad con 0 en vez de
     * omitirlos, para que las barras del frontend queden espaciadas por
     * fecha real y no se "corra" el eje cuando falta un día.
     *
     * @return array<int, array{date: string, label: string, new_users: int, new_orders: int}>
     */
    private function dailyGrowth(int $days): array
    {
        $since = now()->subDays($days - 1)->startOfDay();

        $usersByDay = User::query()
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $ordersByDay = Order::query()
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        return collect(range(0, $days - 1))
            ->map(function (int $offset) use ($since, $usersByDay, $ordersByDay) {
                $date = $since->copy()->addDays($offset);
                $key = $date->format('Y-m-d');

                return [
                    'date' => $key,
                    'label' => $date->isoFormat('D MMM'),
                    'new_users' => (int) ($usersByDay[$key] ?? 0),
                    'new_orders' => (int) ($ordersByDay[$key] ?? 0),
                ];
            })
            ->values()
            ->all();
    }
}
