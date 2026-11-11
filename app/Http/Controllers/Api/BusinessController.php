<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\MembershipPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BusinessController extends Controller
{
    public function index()
    {
        $businesses = Business::query()
            ->with(['plan', 'owner'])
            ->where('status', 'active')
            ->latest()
            ->get();

        return response()->json(['data' => $businesses]);
    }

    public function show(Business $business)
    {
        return response()->json([
            'data' => $business->load([
                'plan',
                'owner',
                'products' => fn ($q) => $q->where('status', 'active'),
                'services' => fn ($q) => $q->where('status', 'active'),
            ]),
        ]);
    }

    public function me(Request $request)
    {
        $business = $request->user()->business()->with('plan')->first();

        return response()->json(['data' => $business]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        if ($user->business()->exists()) {
            abort(422, 'Ya tienes un emprendimiento registrado.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:100'],
            'logo_url' => ['nullable', 'url'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        $validated['user_id'] = $user->id;
        $validated['slug'] = Str::slug($validated['name']).'-'.Str::random(5);
        $validated['status'] = 'pending';

        $business = Business::create($validated);

        if ($user->role === 'customer') {
            $user->update(['role' => 'seller']);
        }

        return response()->json([
            'message' => 'Emprendimiento registrado. Elige una membresía para empezar a publicar.',
            'data' => $business,
        ], 201);
    }

    public function update(Request $request, Business $business)
    {
        $user = $request->user();

        if (! $business->canBeManagedBy($user)) {
            abort(403, 'No tienes permisos para editar este emprendimiento.');
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
            'category' => ['sometimes', 'string', 'max:100'],
            'logo_url' => ['sometimes', 'url'],
            'phone' => ['sometimes', 'string', 'max:20'],
            'address' => ['sometimes', 'string', 'max:255'],
        ]);

        $business->update($validated);

        return response()->json([
            'message' => 'Emprendimiento actualizado correctamente.',
            'data' => $business->fresh(),
        ]);
    }

    public function subscribe(Request $request, Business $business)
    {
        $user = $request->user();

        if (! $business->canBeManagedBy($user)) {
            abort(403, 'No tienes permisos para gestionar la membresía de este emprendimiento.');
        }

        $validated = $request->validate([
            'membership_plan_id' => ['required', 'exists:membership_plans,id'],
        ]);

        $plan = MembershipPlan::findOrFail($validated['membership_plan_id']);

        $expiresAt = $plan->billing_period === 'anual' ? now()->addYear() : now()->addMonth();

        // Solo el admin asigna planes sin pago. El dueño puede vincular un
        // plan gratuito o el plan de pago que ya contrató (y Mercado Pago
        // confirmó) por POST /api/subscriptions/checkout, hasta que venza.
        if ($user->role !== 'admin') {
            if (! $plan->is_active) {
                abort(422, 'Este plan no está disponible.');
            }

            if (! $plan->isFree()) {
                $subscription = $user->activeSubscription()->first();

                if ($subscription?->membership_plan_id !== $plan->id) {
                    return response()->json([
                        'message' => 'Este plan requiere pago. Contrátalo desde el checkout de planes y luego vincúlalo a tu emprendimiento.',
                        'code' => 'payment_required',
                    ], 422);
                }

                $expiresAt = $subscription->ends_at ?? $expiresAt;
            }
        }

        $business->update([
            'membership_plan_id' => $plan->id,
            'status' => 'active',
            'membership_started_at' => now(),
            'membership_expires_at' => $expiresAt,
        ]);

        return response()->json([
            'message' => "Membresía \"{$plan->name}\" activada con éxito.",
            'data' => $business->fresh()->load('plan'),
        ]);
    }

    /**
     * A diferencia de index() (pública, solo emprendimientos activos), este
     * lista todos sin importar el estado: el admin necesita ver también los
     * pendientes de membresía y los que ya suspendió.
     */
    public function adminIndex(Request $request)
    {
        $businesses = Business::query()
            ->with(['plan', 'owner'])
            ->withCount(['products', 'services'])
            ->latest()
            ->get();

        return response()->json(['data' => $businesses]);
    }

    /**
     * Silencia un emprendimiento que el admin considera que no va con la
     * plataforma, sin borrar nada: dentro del status 'suspended' sus
     * productos/servicios siguen archivados y vuelve a 'active' si el admin
     * se arrepiente (a diferencia de destroy(), que sí es permanente).
     */
    public function updateStatus(Request $request, Business $business)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'suspended', 'pending'])],
        ]);

        DB::transaction(function () use ($business, $validated) {
            $business->update(['status' => $validated['status']]);

            if ($validated['status'] === 'suspended') {
                $business->products()->where('status', 'active')->update(['status' => 'archived']);
                $business->services()->where('status', 'active')->update(['status' => 'archived']);
            }
        });

        return response()->json([
            'message' => 'Estado del emprendimiento actualizado.',
            'data' => $business->fresh()->load(['plan', 'owner']),
        ]);
    }

    /**
     * Elimina el emprendimiento por completo. Sus productos/servicios no se
     * borran (los pedidos pasados siguen necesitando la fila del producto),
     * pero se archivan y se desvinculan del emprendimiento para que
     * desaparezcan del marketplace igual que si el admin los hubiera
     * eliminado uno por uno.
     */
    public function destroy(Business $business)
    {
        DB::transaction(function () use ($business) {
            $business->products()->update(['status' => 'archived', 'business_id' => null]);
            $business->services()->update(['status' => 'archived', 'business_id' => null]);
            $business->delete();
        });

        return response()->json([
            'message' => 'Emprendimiento eliminado correctamente.',
        ]);
    }
}
