<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Favorite;
use App\Models\Product;
use App\Models\User;
use App\Services\CommissionCalculator;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function __construct(
        private readonly CommissionCalculator $commission,
        private readonly SubscriptionService $subscriptions,
    ) {}

    /**
     * Calcula el desglose de comisión sin crear ningún registro.
     * Útil para mostrar en vivo el "valor publicado / comisión / neto" mientras el vendedor escribe.
     */
    public function commissionPreview(Request $request)
    {
        $validated = $request->validate([
            'price' => ['nullable', 'numeric', 'min:0'],
            'desired_net_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        // Ruta pública: si viene token, el desglose usa la comisión del plan
        // del vendedor que está publicando.
        $breakdown = $this->commission
            ->forSeller($request->user('sanctum'))
            ->resolveBreakdown($validated['price'] ?? null, $validated['desired_net_price'] ?? null);

        if (! $breakdown) {
            abort(422, 'Debes indicar el precio de venta o la ganancia neta que deseas recibir.');
        }

        return response()->json(['data' => $breakdown]);
    }

    public function index(Request $request)
    {
        $query = Product::query()
            ->with(['category', 'seller'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->where('status', 'active');

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('transaction_type')) {
            $query->where('transaction_type', $request->transaction_type);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $products = $query->latest()->paginate(12);
        $this->markFavorited($request, $products->getCollection());

        return response()->json([
            'data' => $products,
        ]);
    }

    /**
     * Productos publicados por el usuario autenticado (cualquier status, no
     * solo "active"), para su panel de vendedor.
     */
    public function mine(Request $request)
    {
        $products = Product::query()
            ->where('seller_id', $request->user()->id)
            ->with('category')
            ->withCount('reviews')
            ->latest()
            ->get();

        return response()->json([
            'data' => $products,
        ]);
    }

    public function show(Request $request, Product $product)
    {
        $product->load(['category', 'seller', 'business']);
        $product->loadAvg('reviews', 'rating');
        $product->loadCount('reviews');
        $this->markFavorited($request, collect([$product]));

        return response()->json([
            'data' => $product,
        ]);
    }

    private function markFavorited(Request $request, $products): void
    {
        // Rutas públicas: no hay middleware auth:sanctum, así que el guard por
        // defecto nunca intenta leer el token Bearer. Lo resolvemos a mano.
        $user = $request->user('sanctum');
        if (! $user) {
            return;
        }

        $favoriteIds = Favorite::query()
            ->where('user_id', $user->id)
            ->where('favoritable_type', Product::class)
            ->whereIn('favoritable_id', $products->pluck('id'))
            ->pluck('favoritable_id')
            ->all();

        $products->each(function (Product $product) use ($favoriteIds) {
            $product->is_favorited = in_array($product->id, $favoriteIds, true);
        });
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
            'price_per_day' => ['nullable', 'numeric', 'min:0'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'transaction_type' => ['required', 'in:sale,rent'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'condition' => ['nullable', 'string', 'max:50'],
            'location' => ['nullable', 'string', 'max:255'],
            'image_url' => ['nullable', 'url'],
        ]);

        if (! empty($validated['seller_id']) && $user->role !== 'admin') {
            unset($validated['seller_id']);
        }

        $validated['seller_id'] = (int) ($validated['seller_id'] ?? $user->id);
        $seller = $validated['seller_id'] === $user->id ? $user : User::findOrFail($validated['seller_id']);
        $commission = $this->commission->forSeller($seller);

        $validated['is_featured'] = $request->boolean('is_featured', false);

        if ($validated['is_featured']) {
            $this->subscriptions->assertCanFeatureListing($user);
        }

        if ($validated['transaction_type'] === 'rent') {
            if (empty($validated['price_per_day'])) {
                abort(422, 'Debes indicar el precio por día para productos en alquiler.');
            }

            $breakdown = $commission->breakdown((float) $validated['price_per_day']);
            $validated['price'] = $validated['price_per_day'];
            $validated['commission_rate'] = $breakdown['commission_rate'];
            $validated['net_amount'] = $breakdown['net_amount'];
            unset($validated['desired_net_price']);
        } else {
            $breakdown = $commission->resolveBreakdown($validated['price'] ?? null, $validated['desired_net_price'] ?? null);

            if (! $breakdown) {
                abort(422, 'Debes indicar el precio de venta o la ganancia neta que deseas recibir.');
            }

            unset($validated['desired_net_price'], $validated['price_per_day'], $validated['deposit_amount']);
            $validated['price'] = $breakdown['gross_price'];
            $validated['commission_rate'] = $breakdown['commission_rate'];
            $validated['net_amount'] = $breakdown['net_amount'];
        }

        $validated['slug'] = Product::uniqueSlugFor($validated['title']);
        $validated['status'] = 'active';

        $product = DB::transaction(function () use ($validated, $user) {
            if (! empty($validated['business_id'])) {
                // Bloquea la fila del emprendimiento mientras se cuenta el límite
                // del plan: evita que dos publicaciones simultáneas lean el mismo
                // conteo y ambas pasen la validación, superando el límite.
                $business = Business::query()->lockForUpdate()->findOrFail($validated['business_id']);
                $business->assertCanPublishListing($user, 'productos');
            }

            return Product::create($validated);
        });

        return response()->json([
            'message' => 'Producto publicado con éxito.',
            'data' => $product->load('category', 'seller', 'business'),
        ], 201);
    }

    public function update(Request $request, Product $product)
    {
        $user = $request->user();

        if ($user->role !== 'admin' && $product->seller_id !== $user->id) {
            abort(403, 'No tienes permisos para actualizar este producto.');
        }

        $validated = $request->validate([
            'category_id' => ['sometimes', 'exists:categories,id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'desired_net_price' => ['sometimes', 'numeric', 'min:0'],
            'price_per_day' => ['sometimes', 'numeric', 'min:0'],
            'deposit_amount' => ['sometimes', 'numeric', 'min:0'],
            'transaction_type' => ['sometimes', 'in:sale,rent'],
            'stock' => ['sometimes', 'integer', 'min:0'],
            'condition' => ['sometimes', 'string', 'max:50'],
            'location' => ['sometimes', 'string', 'max:255'],
            'image_url' => ['sometimes', 'url'],
            'status' => ['sometimes', 'in:active,draft,archived'],
            'is_featured' => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['title'])) {
            $validated['slug'] = Product::uniqueSlugFor($validated['title'], $product->id);
        }

        if (! empty($validated['is_featured']) && ! $product->is_featured) {
            $this->subscriptions->assertCanFeatureListing($user);
        }

        $commission = $this->commission->forSeller($product->seller);

        $effectiveType = $validated['transaction_type'] ?? $product->transaction_type;
        $isSwitchingType = isset($validated['transaction_type']) && $validated['transaction_type'] !== $product->transaction_type;

        if ($effectiveType === 'rent') {
            if ($isSwitchingType && empty($validated['price_per_day'])) {
                abort(422, 'Debes indicar el precio por día al cambiar este producto a alquiler.');
            }

            if (isset($validated['price_per_day'])) {
                $breakdown = $commission->breakdown((float) $validated['price_per_day']);
                $validated['price'] = $validated['price_per_day'];
                $validated['commission_rate'] = $breakdown['commission_rate'];
                $validated['net_amount'] = $breakdown['net_amount'];
            }
            unset($validated['desired_net_price']);
        } else {
            if ($isSwitchingType && empty($validated['price']) && empty($validated['desired_net_price'])) {
                abort(422, 'Debes indicar el precio de venta o la ganancia neta al cambiar este producto a venta.');
            }

            $breakdown = $commission->resolveBreakdown($validated['price'] ?? null, $validated['desired_net_price'] ?? null);

            if ($breakdown) {
                $validated['price'] = $breakdown['gross_price'];
                $validated['commission_rate'] = $breakdown['commission_rate'];
                $validated['net_amount'] = $breakdown['net_amount'];
            }
            unset($validated['desired_net_price']);
        }

        $product->update($validated);

        return response()->json([
            'message' => 'Producto actualizado correctamente.',
            'data' => $product->fresh()->load('category', 'seller'),
        ]);
    }

    public function destroy(Request $request, Product $product)
    {
        $user = $request->user();

        if ($user->role !== 'admin' && $product->seller_id !== $user->id) {
            abort(403, 'No tienes permisos para eliminar este producto.');
        }

        // rentals.product_id es cascadeOnDelete: borrar un producto con
        // alquileres borraría el historial (y los alquileres en curso) de
        // sus arrendatarios, y dejaría sin producto los pedidos pasados. Se
        // archiva: sale del marketplace igual.
        if ($product->rentals()->exists() || $product->orderItems()->exists()) {
            $product->update(['status' => 'archived']);
        } else {
            $product->delete();
        }

        return response()->json([
            'message' => 'Producto eliminado correctamente.',
        ]);
    }
}
