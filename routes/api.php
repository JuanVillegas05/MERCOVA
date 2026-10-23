<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BusinessController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\MembershipPlanController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\RentalController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Api\WompiWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => ['status' => 'ok']);
Route::get('/payment-methods', fn () => response()->json(['data' => config('mercova.payment_methods')]));

// Pública (sin auth:sanctum a propósito): Wompi no manda un token nuestro,
// firma el payload con WOMPI_EVENTS_SECRET y esa firma es la que se valida
// dentro del controlador. Ver App\Services\WompiService::verifyEventSignature().
Route::post('/webhooks/wompi', [WompiWebhookController::class, 'handle'])->name('webhooks.wompi');

Route::get('/categories', [CategoryController::class, 'index']);

Route::get('/products', [ProductController::class, 'index']);
Route::post('/products/commission-preview', [ProductController::class, 'commissionPreview']);
Route::get('/products/{product:slug}', [ProductController::class, 'show']);
Route::get('/products/{product}/availability', [RentalController::class, 'availability']);
Route::get('/products/{product}/reviews', [ReviewController::class, 'index']);
Route::get('/services', [ServiceController::class, 'index']);
Route::get('/services/{service:slug}', [ServiceController::class, 'show']);

Route::get('/membership-plans', [MembershipPlanController::class, 'index']);
Route::get('/businesses', [BusinessController::class, 'index']);
Route::get('/businesses/{business:slug}', [BusinessController::class, 'show']);

Route::post('/auth/check-identifier', [AuthController::class, 'checkIdentifier']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/me', [AuthController::class, 'updateProfile']);
    Route::put('/me/password', [AuthController::class, 'updatePassword']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/my-products', [ProductController::class, 'mine']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{product}', [ProductController::class, 'update']);
    Route::delete('/products/{product}', [ProductController::class, 'destroy']);

    Route::post('/uploads/image', [UploadController::class, 'store']);
    Route::post('/uploads', [UploadController::class, 'store']);

    Route::post('/services', [ServiceController::class, 'store']);
    Route::put('/services/{service}', [ServiceController::class, 'update']);
    Route::delete('/services/{service}', [ServiceController::class, 'destroy']);
    Route::post('/services/{service}/book', [ServiceController::class, 'book']);

    Route::post('/reviews', [ReviewController::class, 'store']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);

    Route::get('/favorites', [FavoriteController::class, 'index']);
    Route::post('/favorites', [FavoriteController::class, 'store']);
    Route::delete('/favorites/{product}', [FavoriteController::class, 'destroy']);

    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart/items', [CartController::class, 'store']);
    Route::put('/cart/items/{cartItem}', [CartController::class, 'update']);
    Route::delete('/cart/items/{cartItem}', [CartController::class, 'destroy']);

    Route::get('/rentals', [RentalController::class, 'index']);
    Route::get('/rentals/owned', [RentalController::class, 'owned']);
    Route::post('/rentals', [RentalController::class, 'store']);
    Route::patch('/rentals/{rental}/status', [RentalController::class, 'updateStatus']);

    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/my-sales', [OrderController::class, 'mySales']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::post('/checkout', [OrderController::class, 'checkout']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::get('/orders/{order}/payment', [OrderController::class, 'paymentUrl']);

    Route::get('/my-business', [BusinessController::class, 'me']);
    Route::post('/businesses', [BusinessController::class, 'store']);
    Route::put('/businesses/{business}', [BusinessController::class, 'update']);
    Route::post('/businesses/{business}/subscribe', [BusinessController::class, 'subscribe']);

    // Todo lo que cuelga de /admin/* pasa primero por el middleware "admin":
    // ningún controlador necesita volver a comprobar el rol a mano.
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard']);

        Route::get('/users', [AdminController::class, 'users']);
        Route::get('/users/{user}', [AdminController::class, 'showUser']);
        Route::patch('/users/{user}/status', [AdminController::class, 'updateUserStatus']);

        Route::get('/products', [AdminController::class, 'products']);
        Route::get('/services', [AdminController::class, 'services']);

        Route::get('/orders', [AdminController::class, 'orders']);
        Route::patch('/orders/{order}/status', [AdminController::class, 'updateOrderStatus']);

        Route::get('/bookings', [AdminController::class, 'bookings']);
        Route::patch('/bookings/{booking}/status', [AdminController::class, 'updateBookingStatus']);

        Route::get('/rentals', [AdminController::class, 'rentals']);

        Route::get('/notifications', [AdminController::class, 'notifications']);
        Route::post('/notifications/{notification}/read', [AdminController::class, 'markNotificationRead']);
        Route::post('/notifications/read-all', [AdminController::class, 'markAllNotificationsRead']);

        Route::post('/categories', [CategoryController::class, 'store']);
        Route::put('/categories/{category}', [CategoryController::class, 'update']);
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);

        Route::get('/membership-plans', [MembershipPlanController::class, 'adminIndex']);
        Route::post('/membership-plans', [MembershipPlanController::class, 'store']);
        Route::put('/membership-plans/{membershipPlan}', [MembershipPlanController::class, 'update']);
        Route::delete('/membership-plans/{membershipPlan}', [MembershipPlanController::class, 'destroy']);

        Route::get('/businesses', [BusinessController::class, 'adminIndex']);
        Route::patch('/businesses/{business}/status', [BusinessController::class, 'updateStatus']);
        Route::delete('/businesses/{business}', [BusinessController::class, 'destroy']);
    });
});
