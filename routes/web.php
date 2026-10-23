<?php

use App\Http\Controllers\Auth\SocialAuthController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'pages.home')->name('home');
Route::view('/nosotros', 'pages.about')->name('about');
Route::view('/login', 'pages.login')->name('login');
Route::view('/registro', 'pages.register')->name('register');
Route::view('/olvide-password', 'pages.forgot-password')->name('password.request');
Route::view('/restablecer-password', 'pages.reset-password')->name('password.reset');
Route::view('/marketplace', 'pages.marketplace')->name('marketplace');
Route::view('/producto/{slug}', 'pages.product')->name('product.show');
Route::view('/emprendimientos', 'pages.emprendimientos')->name('emprendimientos');
Route::view('/panel', 'pages.dashboard')->name('dashboard');
Route::view('/carrito', 'pages.cart')->name('cart');
Route::view('/favoritos', 'pages.favorites')->name('favorites');
Route::view('/pago/retorno', 'pages.payment-return')->name('payment.return');

Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])
    ->whereIn('provider', ['google', 'apple'])
    ->name('social.redirect');
Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])
    ->whereIn('provider', ['google', 'apple'])
    ->name('social.callback');
Route::view('/auth/callback', 'pages.social-callback')->name('social.landing');

Route::view('/admin', 'admin.dashboard')->name('admin.dashboard');
Route::view('/admin/pedidos', 'admin.orders')->name('admin.orders');
Route::view('/admin/productos', 'admin.products')->name('admin.products');
Route::view('/admin/usuarios', 'admin.users')->name('admin.users');
Route::view('/admin/categorias', 'admin.categories')->name('admin.categories');
Route::view('/admin/planes', 'admin.plans')->name('admin.plans');
Route::view('/admin/emprendimientos', 'admin.businesses')->name('admin.businesses');
