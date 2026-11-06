<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Rental;
use App\Services\CommissionCalculator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RentalController extends Controller
{
    public function __construct(private readonly CommissionCalculator $commission) {}

    /**
     * Para que el comprador vea disponibilidad antes de enviar la solicitud.
     */
    public function availability(Request $request, Product $product)
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        return response()->json([
            'data' => [
                'available' => ! $this->hasOverlap($product->id, $validated['start_date'], $validated['end_date']),
            ],
        ]);
    }

    private function hasOverlap(int $productId, string $start, string $end, ?int $exceptRentalId = null): bool
    {
        return Rental::query()
            ->where('product_id', $productId)
            ->whereIn('status', Rental::ACTIVE_STATUSES)
            ->when($exceptRentalId, fn ($q) => $q->where('id', '!=', $exceptRentalId))
            ->where('start_date', '<=', $end)
            ->where('end_date', '>=', $start)
            ->exists();
    }

    /**
     * Mis solicitudes de alquiler (como arrendatario).
     */
    public function index(Request $request)
    {
        $rentals = Rental::query()
            ->where('renter_id', $request->user()->id)
            ->with('product')
            ->latest()
            ->get();

        return response()->json(['data' => $rentals]);
    }

    /**
     * Solicitudes recibidas sobre mis productos (como propietario).
     */
    public function owned(Request $request)
    {
        $rentals = Rental::query()
            ->whereHas('product', fn ($q) => $q->where('seller_id', $request->user()->id))
            ->with(['product', 'renter'])
            ->latest()
            ->get();

        return response()->json(['data' => $rentals]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'payment_method' => ['nullable', Rule::in(array_keys(config('mercova.payment_methods')))],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $rental = DB::transaction(function () use ($validated, $request) {
            // Bloquea la fila del producto: así dos solicitudes simultáneas para
            // fechas que se solapan no pueden pasar ambas la validación de
            // disponibilidad sobre el mismo estado leído.
            $product = Product::query()->where('status', 'active')->lockForUpdate()->findOrFail($validated['product_id']);

            if ($product->transaction_type !== 'rent') {
                abort(422, 'Este producto no está disponible para alquiler.');
            }

            if (! $product->price_per_day) {
                abort(422, 'Este producto todavía no tiene definido un precio por día.');
            }

            if ($this->hasOverlap($product->id, $validated['start_date'], $validated['end_date'])) {
                abort(422, 'Este producto ya está reservado en esas fechas.');
            }

            $days = Carbon::parse($validated['start_date'])->diffInDays(Carbon::parse($validated['end_date'])) + 1;
            $breakdown = $this->commission->forSeller($product->seller)->breakdown(round((float) $product->price_per_day * $days, 2));

            return Rental::create([
                'product_id' => $product->id,
                'renter_id' => $request->user()->id,
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'days' => $days,
                'price_per_day' => $product->price_per_day,
                'deposit_amount' => $product->deposit_amount,
                'total' => $breakdown['gross_price'],
                'commission_rate' => $breakdown['commission_rate'],
                'commission_amount' => $breakdown['commission_amount'],
                'net_amount' => $breakdown['net_amount'],
                'status' => 'pending',
                'payment_method' => $validated['payment_method'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);
        });

        return response()->json([
            'message' => 'Solicitud de alquiler enviada. El propietario debe confirmarla.',
            'data' => $rental->load('product', 'renter'),
        ], 201);
    }

    public function updateStatus(Request $request, Rental $rental)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:confirmed,active,finished,cancelled'],
        ]);

        $user = $request->user();
        $isOwner = $rental->product->seller_id === $user->id;
        $isRenter = $rental->renter_id === $user->id;
        $canCancelOwnPendingRequest = $isRenter && $validated['status'] === 'cancelled' && $rental->status === 'pending';

        if ($user->role !== 'admin' && ! $isOwner && ! $canCancelOwnPendingRequest) {
            abort(403, 'No tienes permisos sobre esta solicitud de alquiler.');
        }

        $allowedTransitions = [
            'pending' => ['confirmed', 'cancelled'],
            'confirmed' => ['active', 'cancelled'],
            'active' => ['finished', 'cancelled'],
        ];

        if (! in_array($validated['status'], $allowedTransitions[$rental->status] ?? [], true)) {
            abort(422, "No se puede pasar de \"{$rental->status}\" a \"{$validated['status']}\".");
        }

        DB::transaction(function () use ($validated, $rental) {
            if ($validated['status'] === 'confirmed') {
                // Bloquea la fila del producto antes de re-validar el solape:
                // evita que dos confirmaciones simultáneas para alquileres
                // pendientes que se solapan queden ambas "confirmed".
                Product::query()->where('id', $rental->product_id)->lockForUpdate()->first();

                if ($this->hasOverlap(
                    $rental->product_id,
                    $rental->start_date->toDateString(),
                    $rental->end_date->toDateString(),
                    $rental->id
                )) {
                    abort(422, 'Ya existe otro alquiler confirmado para estas fechas.');
                }
            }

            $rental->update(['status' => $validated['status']]);
        });

        return response()->json([
            'message' => 'Estado del alquiler actualizado.',
            'data' => $rental->fresh()->load('product', 'renter'),
        ]);
    }
}
