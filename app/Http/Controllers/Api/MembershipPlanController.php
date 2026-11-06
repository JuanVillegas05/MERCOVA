<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MembershipPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MembershipPlanController extends Controller
{
    public function index()
    {
        return response()->json([
            'data' => MembershipPlan::query()
                ->where('is_active', true)
                ->orderBy('price')
                ->get(),
        ]);
    }

    /**
     * Todos los planes (activos e inactivos), para administrarlos.
     */
    public function adminIndex(Request $request)
    {
        return response()->json([
            'data' => MembershipPlan::query()
                ->withCount(['businesses', 'subscriptions' => fn ($q) => $q->current()])
                ->orderBy('price')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'billing_period' => ['required', 'string', 'in:mensual,anual'],
            'max_listings' => ['nullable', 'integer', 'min:1'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            ...$this->subscriptionCapabilityRules(),
        ]);

        $validated['slug'] = $this->uniqueSlug($validated['name']);
        $validated['is_active'] = $request->boolean('is_active', true);

        $plan = MembershipPlan::create($validated);
        $this->keepSingleDefaultPlan($plan);

        return response()->json([
            'message' => 'Plan creado con éxito.',
            'data' => $plan,
        ], 201);
    }

    public function update(Request $request, MembershipPlan $membershipPlan)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'billing_period' => ['sometimes', 'string', 'in:mensual,anual'],
            'max_listings' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'features' => ['sometimes', 'nullable', 'array'],
            'features.*' => ['string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            ...$this->subscriptionCapabilityRules(),
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = $this->uniqueSlug($validated['name'], $membershipPlan->id);
        }

        $membershipPlan->update($validated);
        $this->keepSingleDefaultPlan($membershipPlan);

        return response()->json([
            'message' => 'Plan actualizado correctamente.',
            'data' => $membershipPlan->fresh(),
        ]);
    }

    public function destroy(Request $request, MembershipPlan $membershipPlan)
    {
        // Los emprendimientos que tenían este plan quedan sin plan (membership_plan_id
        // es nullOnDelete en la migración), no se eliminan ni se bloquea el borrado.
        $membershipPlan->delete();

        return response()->json([
            'message' => 'Plan eliminado correctamente.',
        ]);
    }

    /**
     * Reglas de las capacidades que hace cumplir el backend. Los límites en
     * null significan "ilimitado"; commission_rate en null, "comisión general".
     *
     * @return array<string, array<int, string>>
     */
    private function subscriptionCapabilityRules(): array
    {
        return [
            'tier' => ['sometimes', 'integer', 'min:0', 'max:255'],
            'monthly_listing_limit' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'monthly_request_limit' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'featured_listings_limit' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'commission_rate' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:1'],
            'has_verified_badge' => ['sometimes', 'boolean'],
            'has_advanced_stats' => ['sometimes', 'boolean'],
            'has_realtime_analytics' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Solo un plan puede ser el gratuito por defecto: al marcar uno, los
     * demás dejan de serlo.
     */
    private function keepSingleDefaultPlan(MembershipPlan $plan): void
    {
        if ($plan->is_default) {
            MembershipPlan::query()->whereKeyNot($plan->id)->update(['is_default' => false]);
        }
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 1;

        while (
            MembershipPlan::query()
                ->where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
