<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\CommissionCalculator;
use App\Services\FrontendRedirect;
use App\Services\PaymentService;
use App\Services\WompiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    /** El medio concreto (tarjeta, PSE, Nequi...) lo elige el comprador en Wompi. */
    private const DEFAULT_PAYMENT_METHOD = 'wompi';

    /** Máximo de líneas distintas en un checkout con `items`. */
    private const MAX_CHECKOUT_ITEMS = 50;

    public function __construct(
        private readonly CommissionCalculator $commission,
        private readonly WompiService $wompi,
        private readonly PaymentService $payments,
        private readonly FrontendRedirect $frontendRedirect,
    ) {}

    /**
     * Enlace del Web Checkout de Wompi al que debe redirigirse al comprador
     * para pagar un pedido (o grupo de pedidos de un mismo checkout, que
     * comparten `payment_reference` y se cobran una sola vez por el total de
     * todos). Null si la pasarela no está configurada o el pedido ya no se
     * puede pagar. Se genera localmente (no llama a Wompi), así que no falla
     * por red.
     *
     * Wompi devuelve al comprador a `return_url` (parámetro opcional de la
     * petición) solo si es del frontend configurado; si no, a la página de
     * retorno propia de este backend.
     */
    private function paymentUrlFor(Order $order, Request $request): ?string
    {
        if (! $this->wompi->enabled() || ! $order->payment_reference) {
            return null;
        }

        $returnUrl = $request->input('return_url');
        $redirectUrl = $this->frontendRedirect->allowed(is_string($returnUrl) ? $returnUrl : null) ?? route('payment.return');

        return $this->payments->checkoutUrlFor($order->buyer, $order->payment_reference, $redirectUrl);
    }

    public function index(Request $request)
    {
        $query = Order::query()->with(['buyer', 'seller', 'items.product']);

        if ($request->user()->role !== 'admin') {
            $query->where('buyer_id', $request->user()->id);
        }

        return response()->json([
            'data' => $query->latest()->get(),
        ]);
    }

    /**
     * Pedidos donde el usuario autenticado es el vendedor: historial de
     * ventas para su panel ("Actividad de la semana", etc.).
     */
    public function mySales(Request $request)
    {
        $orders = Order::query()
            ->where('seller_id', $request->user()->id)
            ->with(['buyer', 'items.product'])
            ->latest()
            ->get();

        return response()->json([
            'data' => $orders,
        ]);
    }

    public function show(Request $request, Order $order)
    {
        if ($request->user()->role !== 'admin' && $order->buyer_id !== $request->user()->id) {
            abort(403, 'No tienes permisos para ver esta compra.');
        }

        $order->load(['buyer', 'seller', 'items.product']);

        return response()->json([
            'data' => $order,
            'payment_url' => $this->paymentUrlFor($order, $request),
        ]);
    }

    /**
     * Permite reintentar el pago de un pedido que sigue abierto (p. ej. el
     * comprador cerró el checkout de Wompi sin terminar, o su tarjeta fue rechazada
     * y quiere usar otra).
     */
    public function paymentUrl(Request $request, Order $order)
    {
        if ($request->user()->role !== 'admin' && $order->buyer_id !== $request->user()->id) {
            abort(403, 'No tienes permisos para pagar este pedido.');
        }

        if (! $this->wompi->enabled()) {
            abort(422, 'La pasarela de pagos no está configurada todavía.');
        }

        $url = $this->paymentUrlFor($order, $request);

        if (! $url) {
            abort(422, 'Este pedido ya fue pagado o ya no se puede pagar.');
        }

        return response()->json(['payment_url' => $url]);
    }

    /**
     * Cancelación del comprador para un pedido que todavía no fue pagado.
     * Repone el stock al instante en vez de dejarlo bloqueado hasta que
     * expire solo. Un pedido ya pagado no se puede cancelar aquí: requiere
     * reembolso manual por soporte desde el panel de Wompi.
     */
    public function cancel(Request $request, Order $order)
    {
        if ($request->user()->role !== 'admin' && $order->buyer_id !== $request->user()->id) {
            abort(403, 'No tienes permisos para cancelar este pedido.');
        }

        $order = DB::transaction(function () use ($order) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            if (! in_array($order->status, ['pending', 'processing'], true)) {
                abort(422, "No se puede cancelar un pedido en estado \"{$order->status}\".");
            }

            if ($order->payment_status === 'approved') {
                abort(422, 'Este pedido ya fue pagado. Contacta a soporte para gestionar la cancelación y el reembolso.');
            }

            $order->update(['status' => 'cancelled']);
            $order->restoreProductStock();

            return $order;
        });

        return response()->json([
            'message' => 'Pedido cancelado correctamente.',
            'data' => $order->fresh(['buyer', 'seller', 'items.product']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'payment_method' => ['nullable', Rule::in(array_keys(config('mercova.payment_methods')))],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $order = DB::transaction(function () use ($validated, $request) {
            // Bloquea la fila del producto para que dos compras simultáneas no
            // puedan pasar ambas la validación de stock sobre el mismo valor leído.
            $product = Product::query()->where('status', 'active')->lockForUpdate()->findOrFail($validated['product_id']);

            if ($product->transaction_type !== 'sale') {
                abort(422, 'Este producto es de alquiler. Usa el flujo de solicitud de alquiler (/api/rentals).');
            }

            if ($product->seller_id === $request->user()->id) {
                abort(422, 'No puedes comprar tu propio producto.');
            }

            if ($product->stock < $validated['quantity']) {
                abort(422, 'No hay stock suficiente para esta cantidad.');
            }

            $total = round((float) $product->price * $validated['quantity'], 2);
            $breakdown = $this->commission->forSeller($product->seller)->breakdown($total);

            $order = Order::create([
                'buyer_id' => $request->user()->id,
                'seller_id' => $product->seller_id,
                'status' => 'pending',
                'total' => $total,
                'commission_amount' => $breakdown['commission_amount'],
                'net_amount' => $breakdown['net_amount'],
                'payment_method' => $validated['payment_method'] ?? self::DEFAULT_PAYMENT_METHOD,
                'payment_reference' => $this->wompi->generateReference('ORDER'),
                'payment_status' => 'pending',
                'transaction_type' => $product->transaction_type,
                'notes' => $validated['notes'] ?? null,
            ]);

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'type' => 'product',
                'quantity' => $validated['quantity'],
                'unit_price' => $product->price,
                'total' => $total,
                'commission_rate' => $breakdown['commission_rate'],
                'commission_amount' => $breakdown['commission_amount'],
                'net_amount' => $breakdown['net_amount'],
            ]);

            $product->decrement('stock', $validated['quantity']);

            return $order;
        });

        $order->load(['buyer', 'seller', 'items.product']);

        return response()->json([
            'message' => 'Compra realizada correctamente.',
            'data' => $order,
            'payment_url' => $this->paymentUrlFor($order, $request),
        ], 201);
    }

    /**
     * Finaliza la compra de un carrito. Se crea un pedido por cada vendedor
     * involucrado (un pedido nunca mezcla productos de dos vendedores distintos).
     *
     * El carrito puede venir en el body (`items: [{product_id, quantity}]`,
     * para un frontend que lo guarda en el navegador) o, si no se manda, es
     * el carrito guardado en el servidor (/api/cart), que se vacía al terminar.
     * Precios y stock siempre salen de la base de datos.
     */
    public function checkout(Request $request)
    {
        $validated = $request->validate([
            'recipient_name' => ['required', 'string', 'max:255'],
            'recipient_phone' => ['required', 'string', 'max:30'],
            'address' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'department' => ['required', 'string', 'max:100'],
            'payment_method' => ['nullable', Rule::in(array_keys(config('mercova.payment_methods')))],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['sometimes', 'array', 'min:1', 'max:'.self::MAX_CHECKOUT_ITEMS],
            'items.*.product_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        $orders = DB::transaction(function () use ($validated, $request) {
            $cart = null;

            if (isset($validated['items'])) {
                $lines = collect($validated['items'])->map(fn (array $item) => (object) [
                    'product_id' => (int) $item['product_id'],
                    'quantity' => (int) $item['quantity'],
                ]);
            } else {
                $cart = Cart::with('items')->firstOrCreate(['user_id' => $request->user()->id]);
                $lines = $cart->items;
            }

            if ($lines->isEmpty()) {
                abort(422, 'Tu carrito está vacío.');
            }

            // Bloquea todas las filas de producto involucradas, en un orden
            // consistente (por id), antes de validar/decrementar stock: así dos
            // checkouts simultáneos sobre el mismo producto no pueden pasar
            // ambos la validación sobre el mismo valor leído, y el orden fijo
            // evita deadlocks entre checkouts que comparten productos.
            $productIds = $lines->pluck('product_id')->unique()->sort()->values();
            $products = Product::query()->whereIn('id', $productIds)->lockForUpdate()->get()->keyBy('id');

            foreach ($lines as $item) {
                $product = $products->get($item->product_id);

                if (! $product || $product->status !== 'active') {
                    abort(422, 'Uno de los productos de tu carrito ya no está disponible.');
                }

                if ($product->transaction_type !== 'sale') {
                    abort(422, "\"{$product->title}\" es de alquiler y no se compra desde el carrito.");
                }

                if ($product->seller_id === $request->user()->id) {
                    abort(422, "\"{$product->title}\" es una publicación tuya. Quítala del carrito para continuar.");
                }

                if ($item->quantity > $product->stock) {
                    abort(422, "No hay stock suficiente de \"{$product->title}\".");
                }
            }

            // Una sola referencia para todos los pedidos de este checkout: a
            // Wompi se le cobra una sola vez por el total del carrito, aunque
            // internamente se reparta en un pedido por vendedor. El webhook
            // busca por esta referencia y marca todos los pedidos a la vez.
            $paymentReference = $this->wompi->generateReference('CART');

            $orders = $lines
                ->groupBy(fn ($item) => $products->get($item->product_id)->seller_id)
                ->map(function ($items, $sellerId) use ($validated, $request, $products, $paymentReference) {
                    $sellerCommission = $this->commission->forSeller(User::find($sellerId));

                    $total = round($items->sum(fn ($item) => $products->get($item->product_id)->price * $item->quantity), 2);

                    $order = Order::create([
                        'buyer_id' => $request->user()->id,
                        'seller_id' => $sellerId,
                        'status' => 'pending',
                        'total' => $total,
                        'payment_method' => $validated['payment_method'] ?? self::DEFAULT_PAYMENT_METHOD,
                        'payment_reference' => $paymentReference,
                        'payment_status' => 'pending',
                        'transaction_type' => $products->get($items->first()->product_id)->transaction_type,
                        'notes' => $validated['notes'] ?? null,
                        'recipient_name' => $validated['recipient_name'],
                        'recipient_phone' => $validated['recipient_phone'],
                        'address' => $validated['address'],
                        'city' => $validated['city'],
                        'department' => $validated['department'],
                    ]);

                    $commissionAmount = 0;
                    $netAmount = 0;

                    foreach ($items as $item) {
                        $product = $products->get($item->product_id);
                        $itemTotal = round((float) $product->price * $item->quantity, 2);
                        $itemBreakdown = $sellerCommission->breakdown($itemTotal);
                        $commissionAmount += $itemBreakdown['commission_amount'];
                        $netAmount += $itemBreakdown['net_amount'];

                        OrderItem::create([
                            'order_id' => $order->id,
                            'product_id' => $product->id,
                            'type' => 'product',
                            'quantity' => $item->quantity,
                            'unit_price' => $product->price,
                            'total' => $itemTotal,
                            'commission_rate' => $itemBreakdown['commission_rate'],
                            'commission_amount' => $itemBreakdown['commission_amount'],
                            'net_amount' => $itemBreakdown['net_amount'],
                        ]);

                        $product->decrement('stock', $item->quantity);
                    }

                    $order->update([
                        'commission_amount' => round($commissionAmount, 2),
                        'net_amount' => round($netAmount, 2),
                    ]);

                    return $order->load(['buyer', 'seller', 'items.product']);
                })
                ->values();

            // El carrito del servidor solo se vacía si fue el que se compró.
            $cart?->items()->delete();

            return $orders;
        });

        // La URL cobra el total de todo el carrito (referencia compartida).
        $paymentUrl = $orders->isNotEmpty() ? $this->paymentUrlFor($orders->first(), $request) : null;

        return response()->json([
            'message' => 'Compra realizada correctamente.',
            'data' => $orders,
            'payment_url' => $paymentUrl,
        ], 201);
    }
}
