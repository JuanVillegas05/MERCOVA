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
use App\Services\DashboardSnapshotService;
use App\Services\UserAccountDeletion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function __construct(private readonly DashboardSnapshotService $dashboardSnapshot) {}

    public function dashboard(Request $request)
    {
        $snapshot = $this->dashboardSnapshot->build((string) $request->query('range'));

        return response()->json([
            'data' => [
                ...$snapshot,
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

    /**
     * Elimina una cuenta: revoca sus tokens al instante, libera su correo y
     * conserva los pedidos/reseñas de otros usuarios (ver UserAccountDeletion).
     * 422 si es admin o tiene compromisos en curso que deben cerrarse antes.
     */
    public function destroyUser(Request $request, User $user, UserAccountDeletion $deletion)
    {
        $blockingReason = $deletion->blockingReason($user);

        if ($blockingReason !== null) {
            abort(422, $blockingReason);
        }

        $deletion->delete($user);

        // Sin el correo original: sus datos personales se acaban de anonimizar.
        AdminNotification::record(
            'user.deleted',
            'Cuenta eliminada',
            "La cuenta #{$user->id} fue eliminada por {$request->user()->email}.",
        );

        return response()->json(['message' => 'Usuario eliminado correctamente']);
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

        // "completed" y "cancelled" son terminales: ningún pedido vuelve de
        // ahí (no se puede "des-entregar" ni reabrir uno cancelado).
        $allowedTransitions = [
            'pending' => ['processing', 'cancelled'],
            'processing' => ['completed', 'cancelled'],
        ];

        if ($validated['status'] !== $order->status
            && ! in_array($validated['status'], $allowedTransitions[$order->status] ?? [], true)) {
            abort(422, "No se puede pasar de \"{$order->status}\" a \"{$validated['status']}\".");
        }

        $order = DB::transaction(function () use ($order, $validated) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            $order->update(['status' => $validated['status']]);

            if ($validated['status'] === 'cancelled') {
                $order->restoreProductStock();
            }

            return $order;
        });

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
