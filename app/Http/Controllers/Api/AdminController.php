<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Order;
use App\Models\Product;
use App\Models\Rental;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AdminController extends Controller
{
    private const RANGES = ['today', 'week', 'month', 'all'];

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

    public function dashboard(Request $request)
    {
        $range = in_array($request->query('range'), self::RANGES, true) ? $request->query('range') : 'today';
        $start = $this->rangeStart($range);

        $ordersInRange = Order::query()->when($start, fn ($q) => $q->where('created_at', '>=', $start));
        $bookingsInRange = Booking::query()->when($start, fn ($q) => $q->where('created_at', '>=', $start));
        $rentalsInRange = Rental::query()->when($start, fn ($q) => $q->where('created_at', '>=', $start));

        // "Dinero ingresado": ventas registradas que no se cancelaron. No se
        // limita a `payment_status: approved` porque la pasarela (Wompi) es
        // opcional — muchos pedidos siguen su ciclo de vida por fuera de ella
        // (pago contra entrega, efectivo, etc.) y el panel debe reflejar la
        // operación real de la tienda, no solo lo cobrado por la pasarela.
        $revenue = (clone $ordersInRange)->where('status', '!=', 'cancelled')->sum('total');
        $ordersCount = (clone $ordersInRange)->count();

        $statusColors = [
            'pending' => '#f59e0b',
            'processing' => '#6366f1',
            'completed' => '#10b981',
            'cancelled' => '#ef4444',
        ];
        $statusLabels = [
            'pending' => 'Pendiente',
            'processing' => 'En proceso',
            'completed' => 'Completado',
            'cancelled' => 'Cancelado',
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

        return response()->json([
            'data' => [
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
                // (ese selector es para las 4 tarjetas, este panel siempre
                // mira la misma ventana para poder comparar día contra día).
                'growth' => $this->dailyGrowth(14),
                'stats' => [
                    'users' => User::count(),
                    'new_users' => User::where('created_at', '>=', now()->subDay())->count(),
                    'products' => Product::count(),
                    'services' => Service::count(),
                    'new_listings' => Product::where('created_at', '>=', now()->subDay())->count()
                        + Service::where('created_at', '>=', now()->subDay())->count(),
                    'orders' => Order::count(),
                    'new_orders' => Order::where('created_at', '>=', now()->subDay())->count(),
                    'pending_orders' => Order::where('status', 'pending')->count(),
                    'processing_orders' => Order::where('status', 'processing')->count(),
                    'completed_orders' => Order::where('status', 'completed')->count(),
                    'pending_bookings' => Booking::where('status', 'pending')->count(),
                    'processing_bookings' => Booking::where('status', 'processing')->count(),
                    'businesses' => Business::count(),
                    'active_businesses' => Business::where('status', 'active')->count(),
                    'unread_notifications' => AdminNotification::unread()->count(),
                ],
                'recent_products' => Product::with('seller')->latest()->take(5)->get(),
                'recent_orders' => Order::with(['buyer', 'seller'])->latest()->take(5)->get(),
                'recent_activity' => AdminNotification::latest()->take(8)->get(),
            ],
        ]);
    }

    public function users(Request $request)
    {
        $query = User::query()->withCount(['products', 'services', 'orders']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return response()->json([
            'data' => $query->latest()->get(),
        ]);
    }

    public function showUser(Request $request, User $user)
    {
        return response()->json([
            'data' => $user->load(['products', 'services', 'orders', 'bookings']),
        ]);
    }

    public function updateUserStatus(Request $request, User $user)
    {
        if ($user->role === 'admin') {
            abort(422, 'No puedes desactivar una cuenta de administrador.');
        }

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $user->update(['is_active' => $validated['is_active']]);

        return response()->json([
            'message' => $validated['is_active'] ? 'Usuario activado.' : 'Usuario desactivado.',
            'data' => $user->fresh(),
        ]);
    }

    public function products(Request $request)
    {
        return response()->json([
            'data' => Product::with(['seller', 'business'])->latest()->get(),
        ]);
    }

    public function services(Request $request)
    {
        return response()->json([
            'data' => Service::with(['seller', 'business'])->latest()->get(),
        ]);
    }

    public function orders(Request $request)
    {
        $query = Order::query()->with(['buyer', 'seller', 'items.product']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->query('window') === 'new') {
            $query->where('created_at', '>=', now()->subDay());
        }

        return response()->json([
            'data' => $query->latest()->get(),
        ]);
    }

    public function updateOrderStatus(Request $request, Order $order)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending,processing,completed,cancelled'],
        ]);

        $order->update(['status' => $validated['status']]);

        return response()->json([
            'message' => 'Estado del pedido actualizado.',
            'data' => $order->fresh(),
        ]);
    }

    public function bookings(Request $request)
    {
        $query = Booking::query()->with(['service', 'buyer']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->query('window') === 'new') {
            $query->where('created_at', '>=', now()->subDay());
        }

        return response()->json([
            'data' => $query->latest()->get(),
        ]);
    }

    public function updateBookingStatus(Request $request, Booking $booking)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending,processing,completed,cancelled'],
        ]);

        $booking->update(['status' => $validated['status']]);

        return response()->json([
            'message' => 'Estado de la reserva actualizado.',
            'data' => $booking->fresh(),
        ]);
    }

    /**
     * A diferencia de /api/rentals (mis solicitudes como arrendatario) y
     * /api/rentals/owned (solicitudes sobre mis productos), esta sí ve todos
     * los alquileres de la plataforma sin importar quién sea el propietario
     * o el arrendatario: es la que necesita el panel de admin para
     * "alquileres activos" (RentalController::updateStatus ya deja al admin
     * cambiar el estado de cualquiera, esto solo le faltaba la lista).
     */
    public function rentals(Request $request)
    {
        $query = Rental::query()->with(['product', 'renter']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->query('window') === 'active') {
            $query->whereIn('status', Rental::ACTIVE_STATUSES);
        }

        return response()->json([
            'data' => $query->latest()->get(),
        ]);
    }

    public function notifications(Request $request)
    {
        $query = AdminNotification::query();

        if ($request->boolean('unread')) {
            $query->unread();
        }

        return response()->json([
            'data' => $query->latest()->paginate(20),
            'unread_count' => AdminNotification::unread()->count(),
        ]);
    }

    public function markNotificationRead(Request $request, AdminNotification $notification)
    {
        $notification->markAsRead();

        return response()->json([
            'message' => 'Notificación marcada como leída.',
            'data' => $notification->fresh(),
        ]);
    }

    public function markAllNotificationsRead(Request $request)
    {
        AdminNotification::unread()->update(['read_at' => now()]);

        return response()->json([
            'message' => 'Todas las notificaciones fueron marcadas como leídas.',
        ]);
    }
}
