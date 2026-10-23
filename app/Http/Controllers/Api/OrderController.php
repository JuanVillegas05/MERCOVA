<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\CommissionCalculator;
use App\Services\WompiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function __construct(
        private readonly CommissionCalculator $commission,
        private readonly WompiService $wompi,
    ) {}

    /**
     * URL de Wompi a la que debe redirigirse al comprador para pagar un
     * pedido (o grupo de pedidos de un mismo checkout, que comparten
     * `payment_reference` y por tanto se cobran una sola vez). Null si la
     * pasarela no está configurada (sin llaves en .env) o el pedido ya fue
     * pagado.
     */
    private function paymentUrlFor(Order $order, float $amount): ?string
    {
        if (! $this->wompi->enabled() || ! $order->payment_reference || $order->payment_status !== 'pending') {
            return null;
        }

        return $this->wompi->checkoutUrl(
            reference: $order->payment_reference,
            amount: $amount,
            redirectUrl: route('payment.return'),
        );
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
            'payment_url' => $this->paymentUrlFor($order, (float) $order->total),
        ]);
    }

    /**
     * Permite reintentar el pago de un pedido que sigue en
     * `payment_status: pending` (p. ej. el comprador cerró el widget de
     * Wompi sin terminar, o el link expiró).
     */
    public function paymentUrl(Request $request, Order $order)
    {
        if ($request->user()->role !== 'admin' && $order->buyer_id !== $request->user()->id) {
            abort(403, 'No tienes permisos para pagar este pedido.');
        }

        $url = $this->paymentUrlFor($order, (float) $order->total);

        if (! $url) {
            abort(422, $order->payment_status !== 'pending'
                ? 'Este pedido ya fue pagado o el pago fue rechazado definitivamente.'
                : 'La pasarela de pagos no está configurada todavía.');
        }

        return response()->json(['payment_url' => $url]);
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

            if ($product->stock < $validated['quantity']) {
                abort(422, 'No hay stock suficiente para esta cantidad.');
            }

            $total = round((float) $product->price * $validated['quantity'], 2);
            $breakdown = $this->commission->breakdown($total);

            $order = Order::create([
                'buyer_id' => $request->user()->id,
                'seller_id' => $product->seller_id,
                'status' => 'pending',
                'total' => $total,
                'commission_amount' => $breakdown['commission_amount'],
                'net_amount' => $breakdown['net_amount'],
                'payment_method' => $validated['payment_method'] ?? 'debit_card',
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
            'payment_url' => $this->paymentUrlFor($order, (float) $order->total),
        ], 201);
    }

    /**
     * Finaliza la compra de todo el carrito. Se crea un pedido por cada vendedor
     * involucrado (un pedido nunca mezcla productos de dos vendedores distintos).
     */
    public function checkout(Request $request)
    {
        $validated = $request->validate([
            'recipient_name' => ['required', 'string', 'max:255'],
            'recipient_phone' => ['required', 'string', 'max:30'],
            'address' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'department' => ['required', 'string', 'max:100'],
            'payment_method' => ['required', Rule::in(array_keys(config('mercova.payment_methods')))],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $orders = DB::transaction(function () use ($validated, $request) {
            $cart = Cart::with('items')->firstOrCreate(['user_id' => $request->user()->id]);

            if ($cart->items->isEmpty()) {
                abort(422, 'Tu carrito está vacío.');
            }

            // Bloquea todas las filas de producto involucradas, en un orden
            // consistente (por id), antes de validar/decrementar stock: así dos
            // checkouts simultáneos sobre el mismo producto no pueden pasar
            // ambos la validación sobre el mismo valor leído, y el orden fijo
            // evita deadlocks entre checkouts que comparten productos.
            $productIds = $cart->items->pluck('product_id')->unique()->sort()->values();
            $products = Product::query()->whereIn('id', $productIds)->lockForUpdate()->get()->keyBy('id');

            foreach ($cart->items as $item) {
                $product = $products->get($item->product_id);

                if (! $product || $product->status !== 'active') {
                    abort(422, 'Uno de los productos de tu carrito ya no está disponible.');
                }

                if ($product->transaction_type !== 'sale') {
                    abort(422, "\"{$product->title}\" es de alquiler y no se compra desde el carrito.");
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

            $orders = $cart->items
                ->groupBy(fn ($item) => $products->get($item->product_id)->seller_id)
                ->map(function ($items, $sellerId) use ($validated, $request, $products, $paymentReference) {
                    $total = round($items->sum(fn ($item) => $products->get($item->product_id)->price * $item->quantity), 2);

                    $order = Order::create([
                        'buyer_id' => $request->user()->id,
                        'seller_id' => $sellerId,
                        'status' => 'pending',
                        'total' => $total,
                        'payment_method' => $validated['payment_method'],
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
                        $itemBreakdown = $this->commission->breakdown($itemTotal);
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

            $cart->items()->delete();

            return $orders;
        });

        $paymentReference = $orders->first()?->payment_reference;
        $totalAmount = (float) $orders->sum('total');

        $paymentUrl = ($paymentReference && $this->wompi->enabled())
            ? $this->wompi->checkoutUrl(
                reference: $paymentReference,
                amount: $totalAmount,
                redirectUrl: route('payment.return'),
            )
            : null;

        return response()->json([
            'message' => 'Compra realizada correctamente.',
            'data' => $orders,
            'payment_url' => $paymentUrl,
        ], 201);
    }
}
