<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;

class CartController extends Controller
{
    private function cartFor(Request $request): Cart
    {
        return Cart::firstOrCreate(['user_id' => $request->user()->id]);
    }

    private function payload(Cart $cart): array
    {
        $cart->load(['items.product.category', 'items.product.seller']);

        $items = $cart->items->map(function (CartItem $item) {
            $product = $item->product;
            $lineTotal = $product ? round((float) $product->price * $item->quantity, 2) : 0;

            return [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'product' => $product,
                'available_stock' => $product?->transaction_type === 'sale' ? $product->stock : null,
                'line_total' => $lineTotal,
            ];
        })->values();

        $subtotal = round($items->sum('line_total'), 2);

        return [
            'id' => $cart->id,
            'items' => $items,
            'subtotal' => $subtotal,
            'total' => $subtotal,
        ];
    }

    public function index(Request $request)
    {
        return response()->json(['data' => $this->payload($this->cartFor($request))]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        $product = Product::query()->where('status', 'active')->findOrFail($validated['product_id']);

        if ($product->transaction_type !== 'sale') {
            abort(422, 'Este producto es de alquiler. Usa "Solicitar alquiler" para elegir fechas.');
        }

        $quantity = $validated['quantity'] ?? 1;

        $cart = $this->cartFor($request);
        $item = $cart->items()->firstOrNew(['product_id' => $product->id]);
        $item->quantity = ($item->exists ? $item->quantity : 0) + $quantity;

        if ($product->transaction_type === 'sale' && $item->quantity > $product->stock) {
            abort(422, "Solo quedan {$product->stock} unidades disponibles de \"{$product->title}\".");
        }

        $item->cart_id = $cart->id;
        $item->save();

        return response()->json([
            'message' => 'Producto agregado al carrito.',
            'data' => $this->payload($cart),
        ], 201);
    }

    public function update(Request $request, CartItem $cartItem)
    {
        $cart = $this->cartFor($request);

        if ($cartItem->cart_id !== $cart->id) {
            abort(403, 'Este producto no pertenece a tu carrito.');
        }

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $product = $cartItem->product;
        if ($product && $product->transaction_type === 'sale' && $validated['quantity'] > $product->stock) {
            abort(422, "Solo quedan {$product->stock} unidades disponibles de \"{$product->title}\".");
        }

        $cartItem->update(['quantity' => $validated['quantity']]);

        return response()->json(['data' => $this->payload($cart)]);
    }

    public function destroy(Request $request, CartItem $cartItem)
    {
        $cart = $this->cartFor($request);

        if ($cartItem->cart_id !== $cart->id) {
            abort(403, 'Este producto no pertenece a tu carrito.');
        }

        $cartItem->delete();

        return response()->json([
            'message' => 'Producto eliminado del carrito.',
            'data' => $this->payload($cart),
        ]);
    }
}
