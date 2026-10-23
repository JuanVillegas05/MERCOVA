<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
use App\Models\Product;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function index(Request $request)
    {
        $productIds = Favorite::query()
            ->where('user_id', $request->user()->id)
            ->where('favoritable_type', Product::class)
            ->latest()
            ->pluck('favoritable_id');

        $products = Product::query()
            ->with(['category', 'seller'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->whereIn('id', $productIds)
            ->get()
            ->sortBy(fn ($product) => $productIds->search($product->id))
            ->values();

        return response()->json(['data' => $products]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
        ]);

        Favorite::firstOrCreate([
            'user_id' => $request->user()->id,
            'favoritable_type' => Product::class,
            'favoritable_id' => $validated['product_id'],
        ]);

        return response()->json([
            'message' => 'Producto agregado a favoritos.',
        ], 201);
    }

    public function destroy(Request $request, Product $product)
    {
        Favorite::query()
            ->where('user_id', $request->user()->id)
            ->where('favoritable_type', Product::class)
            ->where('favoritable_id', $product->id)
            ->delete();

        return response()->json([
            'message' => 'Producto eliminado de favoritos.',
        ]);
    }
}
