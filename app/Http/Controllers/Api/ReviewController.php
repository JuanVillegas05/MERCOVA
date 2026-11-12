<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\Rental;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Product $product)
    {
        $reviews = $product->reviews()->with('reviewer:'.User::PUBLIC_PROFILE_COLUMNS)->latest()->get();

        return response()->json([
            'data' => $reviews,
            'meta' => [
                'average' => $reviews->isEmpty() ? null : round((float) $reviews->avg('rating'), 2),
                'count' => $reviews->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = $request->user();
        $product = Product::findOrFail($validated['product_id']);

        $hasCompletedOrder = Order::query()
            ->where('buyer_id', $user->id)
            ->where('status', 'completed')
            ->whereHas('items', fn ($q) => $q->where('product_id', $product->id))
            ->exists();

        $hasFinishedRental = Rental::query()
            ->where('renter_id', $user->id)
            ->where('product_id', $product->id)
            ->where('status', 'finished')
            ->exists();

        if (! $hasCompletedOrder && ! $hasFinishedRental) {
            abort(403, 'Solo puedes calificar productos que hayas comprado o alquilado y que ya estén finalizados.');
        }

        $review = Review::updateOrCreate(
            [
                'reviewer_id' => $user->id,
                'reviewable_type' => Product::class,
                'reviewable_id' => $product->id,
            ],
            [
                'rating' => $validated['rating'],
                'comment' => $validated['comment'] ?? null,
            ]
        );

        return response()->json([
            'message' => 'Gracias por tu calificación.',
            'data' => $review->load('reviewer'),
        ], 201);
    }
}
