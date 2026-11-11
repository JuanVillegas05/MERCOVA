<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Service;
use App\Models\User;
use App\Services\CommissionCalculator;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ServiceController extends Controller
{
    public function __construct(
        private readonly CommissionCalculator $commission,
        private readonly SubscriptionService $subscriptions,
    ) {}

    public function index(Request $request)
    {
        $query = Service::query()->with(['category', 'seller'])->where('status', 'active');

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return response()->json([
            'data' => $query->latest()->paginate(12),
        ]);
    }

    public function show(Service $service)
    {
        return response()->json([
            'data' => $service->load(['category', 'seller']),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'business_id' => ['nullable', 'exists:businesses,id'],
            'seller_id' => ['nullable', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'desired_net_price' => ['nullable', 'numeric', 'min:0'],
            'duration' => ['required', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:255'],
            'image_url' => ['nullable', 'url'],
        ]);

        if (! empty($validated['seller_id']) && $user->role !== 'admin') {
            unset($validated['seller_id']);
        }

        $validated['seller_id'] = (int) ($validated['seller_id'] ?? $user->id);
        $seller = $validated['seller_id'] === $user->id ? $user : User::findOrFail($validated['seller_id']);

        $validated['is_featured'] = $request->boolean('is_featured', false);

        if ($validated['is_featured']) {
            $this->subscriptions->assertCanFeatureListing($user);
        }

        $breakdown = $this->commission
            ->forSeller($seller)
            ->resolveBreakdown($validated['price'] ?? null, $validated['desired_net_price'] ?? null);

        if (! $breakdown) {
            abort(422, 'Debes indicar el precio del servicio o la ganancia neta que deseas recibir.');
        }

        unset($validated['desired_net_price']);
        $validated['price'] = $breakdown['gross_price'];
        $validated['commission_rate'] = $breakdown['commission_rate'];
        $validated['net_amount'] = $breakdown['net_amount'];
        $validated['slug'] = Service::uniqueSlugFor($validated['title']);
        $validated['status'] = 'active';

        $service = DB::transaction(function () use ($validated, $user) {
            if (! empty($validated['business_id'])) {
                // Bloquea la fila del emprendimiento mientras se cuenta el límite
                // del plan: evita que dos publicaciones simultáneas superen el límite.
                $business = Business::query()->lockForUpdate()->findOrFail($validated['business_id']);
                $business->assertCanPublishListing($user, 'servicios');
            }

            return Service::create($validated);
        });

        return response()->json([
            'message' => 'Servicio publicado con éxito.',
            'data' => $service->load('category', 'seller', 'business'),
        ], 201);
    }

    public function update(Request $request, Service $service)
    {
        $user = $request->user();

        if ($user->role !== 'admin' && $service->seller_id !== $user->id) {
            abort(403, 'No tienes permisos para actualizar este servicio.');
        }

        $validated = $request->validate([
            'category_id' => ['sometimes', 'exists:categories,id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'desired_net_price' => ['sometimes', 'numeric', 'min:0'],
            'duration' => ['sometimes', 'string', 'max:100'],
            'location' => ['sometimes', 'string', 'max:255'],
            'image_url' => ['sometimes', 'url'],
            'status' => ['sometimes', 'in:active,draft,archived'],
            'is_featured' => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['title'])) {
            $validated['slug'] = Service::uniqueSlugFor($validated['title'], $service->id);
        }

        if (! empty($validated['is_featured']) && ! $service->is_featured) {
            $this->subscriptions->assertCanFeatureListing($user);
        }

        $breakdown = $this->commission
            ->forSeller($service->seller)
            ->resolveBreakdown($validated['price'] ?? null, $validated['desired_net_price'] ?? null);

        if ($breakdown) {
            $validated['price'] = $breakdown['gross_price'];
            $validated['commission_rate'] = $breakdown['commission_rate'];
            $validated['net_amount'] = $breakdown['net_amount'];
        }
        unset($validated['desired_net_price']);

        $service->update($validated);

        return response()->json([
            'message' => 'Servicio actualizado correctamente.',
            'data' => $service->fresh()->load('category', 'seller'),
        ]);
    }

    public function destroy(Request $request, Service $service)
    {
        $user = $request->user();

        if ($user->role !== 'admin' && $service->seller_id !== $user->id) {
            abort(403, 'No tienes permisos para eliminar este servicio.');
        }

        // bookings.service_id es cascadeOnDelete: borrar un servicio con
        // reservas borraría el historial (y las reservas en curso) de sus
        // compradores. Se archiva: sale del marketplace igual.
        if ($service->bookings()->exists()) {
            $service->update(['status' => 'archived']);
        } else {
            $service->delete();
        }

        return response()->json([
            'message' => 'Servicio eliminado correctamente.',
        ]);
    }

    public function book(Request $request, Service $service)
    {
        $validated = $request->validate([
            'scheduled_at' => ['required', 'date', 'after:now'],
            'guest_count' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $booking = DB::transaction(function () use ($validated, $request, $service) {
            // Bloquea la fila del servicio: así dos reservas simultáneas para
            // horarios que se solapan no pueden pasar ambas la validación
            // sobre el mismo estado leído.
            $lockedService = Service::query()->where('id', $service->id)->lockForUpdate()->firstOrFail();

            if ($lockedService->status !== 'active') {
                abort(422, 'Este servicio ya no está disponible para reservas.');
            }

            if ($lockedService->seller_id === $request->user()->id) {
                abort(422, 'No puedes reservar tu propio servicio.');
            }

            $durationMinutes = $lockedService->durationInMinutes();

            $start = Carbon::parse($validated['scheduled_at']);
            $end = $start->copy()->addMinutes($durationMinutes);

            $overlaps = Booking::query()
                ->where('service_id', $lockedService->id)
                ->whereIn('status', ['pending', 'processing'])
                ->get()
                ->contains(function (Booking $existing) use ($start, $end, $durationMinutes) {
                    $existingStart = $existing->scheduled_at;
                    $existingEnd = $existingStart->copy()->addMinutes($durationMinutes);

                    return $start->lt($existingEnd) && $end->gt($existingStart);
                });

            if ($overlaps) {
                abort(422, 'Ese horario ya está reservado para este servicio. Elige otra hora.');
            }

            $total = round((float) $lockedService->price * $validated['guest_count'], 2);
            $breakdown = $this->commission->forSeller($lockedService->seller)->breakdown($total);

            return Booking::create([
                'service_id' => $lockedService->id,
                'buyer_id' => $request->user()->id,
                'scheduled_at' => $validated['scheduled_at'],
                'guest_count' => $validated['guest_count'],
                'total' => $breakdown['gross_price'],
                'commission_rate' => $breakdown['commission_rate'],
                'commission_amount' => $breakdown['commission_amount'],
                'net_amount' => $breakdown['net_amount'],
                'status' => 'pending',
                'notes' => $validated['notes'] ?? null,
            ]);
        });

        return response()->json([
            'message' => 'Reserva realizada con éxito.',
            'data' => $booking->load(['service', 'buyer']),
        ], 201);
    }
}
