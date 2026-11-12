<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AdminDashboardExportController;
use App\Http\Controllers\Api\AdminMetricsExportController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BusinessController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\MembershipPlanController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\RentalController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\ScheduledTasksController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\SubscriptionCheckoutController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Api\WompiWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => ['status' => 'ok']);
Route::get('/payment-methods', fn () => response()->json(['data' => config('mercova.payment_methods')]));
// Qué botones de login social mostrar: { google: bool, apple: bool }.
Route::get('/auth/providers', fn () => response()->json(['data' => config('mercova.social_login')]));

// Pública (sin auth:sanctum a propósito): Wompi no manda un token nuestro.
// Cada evento trae un checksum (WOMPI_EVENTS_SECRET) y la transacción se
// vuelve a consultar a su API. Ver WompiWebhookController.
Route::post('/webhooks/wompi', [WompiWebhookController::class, 'handle'])
    ->middleware('throttle:120,1')
    ->name('webhooks.wompi');
// Tareas programadas disparadas por un cron externo (Render free no corre
// schedule:run). Protegido con MERCOVA_CRON_SECRET; ver ScheduledTasksController.
Route::post('/cron/run', ScheduledTasksController::class)
    ->middleware('throttle:6,1')
    ->name('cron.run');

// Estado de la pasarela y llave pública de Wompi (nada secreto).
Route::get('/payments/config', [PaymentController::class, 'config']);

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

// 10 intentos por minuto por IP: suficiente para un usuario real que se
// equivoca de contraseña varias veces, pero corta la fuerza bruta y el
// "credential stuffing" contra login/registro/recuperación de contraseña.
// (Todas las respuestas de /api llevan Cache-Control: no-store, ver
// PreventResponseCaching en bootstrap/app.php.)
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/auth/check-identifier', [AuthController::class, 'checkIdentifier']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/auth/social/exchange', [AuthController::class, 'socialExchange']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

// Todo lo autenticado se resuelve con el token de ESTA petición.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/me', [AuthController::class, 'updateProfile']);
    Route::put('/me/password', [AuthController::class, 'updatePassword']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/me/subscription', [SubscriptionController::class, 'show']);
    Route::put('/me/subscription', [SubscriptionController::class, 'update']);

    // Planes de pago: el checkout solo crea el intento y el enlace de Wompi;
    // el plan se activa cuando llega el webhook (/api/webhooks/wompi).
    Route::get('/subscriptions/checkout/preview', [SubscriptionCheckoutController::class, 'preview']);
    Route::post('/subscriptions/checkout', [SubscriptionCheckoutController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/subscriptions/payments', [SubscriptionCheckoutController::class, 'index']);
    Route::get('/subscriptions/payments/{subscriptionPayment}', [SubscriptionCheckoutController::class, 'show']);

    // plan.quota:* descuenta del cupo mensual del plan del usuario (el
    // gratuito tiene límite; Pro/Élite pueden ser ilimitados). Ver
    // App\Http\Middleware\EnforcePlanQuota.
    Route::get('/my-products', [ProductController::class, 'mine']);
    Route::post('/products', [ProductController::class, 'store'])->middleware('plan.quota:listings');
    Route::put('/products/{product}', [ProductController::class, 'update']);
    Route::delete('/products/{product}', [ProductController::class, 'destroy']);

    Route::post('/uploads/image', [UploadController::class, 'store']);
    Route::post('/uploads', [UploadController::class, 'store']);

    Route::post('/services', [ServiceController::class, 'store'])->middleware('plan.quota:listings');
    Route::put('/services/{service}', [ServiceController::class, 'update']);
    Route::delete('/services/{service}', [ServiceController::class, 'destroy']);
    Route::post('/services/{service}/book', [ServiceController::class, 'book'])->middleware('plan.quota:requests');

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
    Route::post('/rentals', [RentalController::class, 'store'])->middleware('plan.quota:requests');
    Route::patch('/rentals/{rental}/status', [RentalController::class, 'updateStatus']);

    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/my-sales', [OrderController::class, 'mySales']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::post('/checkout', [OrderController::class, 'checkout']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::get('/orders/{order}/payment', [OrderController::class, 'paymentUrl']);
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);

    // Enlace del Web Checkout de Wompi y estado de una transacción, para
    // cualquier referencia de pedido, carrito o plan del usuario.
    Route::post('/payments', [PaymentController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/payments/{transactionId}', [PaymentController::class, 'show'])->where('transactionId', '[A-Za-z0-9-]+');

    Route::get('/my-business', [BusinessController::class, 'me']);
    Route::post('/businesses', [BusinessController::class, 'store']);
    Route::put('/businesses/{business}', [BusinessController::class, 'update']);
    Route::post('/businesses/{business}/subscribe', [BusinessController::class, 'subscribe']);

    // Todo lo que cuelga de /admin/* pasa primero por el middleware "admin":
    // ningún controlador necesita volver a comprobar el rol a mano.
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard']);
        Route::get('/dashboard/export/pdf', [AdminDashboardExportController::class, 'pdf']);
        Route::get('/dashboard/export/excel', [AdminDashboardExportController::class, 'excel']);

        Route::get('/metrics/export/excel', [AdminMetricsExportController::class, 'excel']);
        Route::get('/metrics/export/pdf', [AdminMetricsExportController::class, 'pdf']);

        Route::get('/users', [AdminController::class, 'users']);
        Route::get('/users/{user}', [AdminController::class, 'showUser']);
        Route::patch('/users/{user}/status', [AdminController::class, 'updateUserStatus']);
        Route::delete('/users/{user}', [AdminController::class, 'destroyUser']);
        Route::put('/users/{user}/subscription', [SubscriptionController::class, 'adminUpdate']);

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
