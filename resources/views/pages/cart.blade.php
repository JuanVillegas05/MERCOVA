@extends('layouts.app', ['page' => 'cart'])

@section('title', 'Mi carrito')

@section('content')
    <section class="max-w-5xl mx-auto px-4 sm:px-6 py-10 grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2 space-y-4">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-extrabold text-slate-900">Mi carrito</h1>
                <a href="{{ url('/marketplace') }}" class="text-sm font-medium text-emerald-600 hover:underline">Seguir comprando</a>
            </div>

            <div id="cart-items" class="bg-white rounded-xl border border-slate-200 divide-y divide-slate-100">
                <p class="p-6 text-slate-400 text-center">Cargando...</p>
            </div>
        </div>

        <div class="space-y-6">
            <div class="bg-white rounded-xl border border-slate-200 p-6">
                <h2 class="font-bold text-slate-800 mb-3">Resumen</h2>
                <div class="flex justify-between text-sm text-slate-600 mb-1">
                    <span>Subtotal</span>
                    <span id="cart-subtotal">$ 0 COP</span>
                </div>
                <div class="flex justify-between text-base font-bold text-slate-900 mt-2 pt-2 border-t border-slate-100">
                    <span>Total</span>
                    <span id="cart-total">$ 0 COP</span>
                </div>
                <p class="text-xs text-slate-400 mt-2">El precio de cada producto ya incluye la comisión de intermediación de Mercova; no se suman cargos adicionales al comprador.</p>
            </div>

            <div id="checkout-section" class="hidden bg-white rounded-xl border border-slate-200 p-6">
                <h2 class="font-bold text-slate-800 mb-3">Información de entrega</h2>
                <form id="checkout-form" class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">Nombre de quien recibe</label>
                        <input type="text" name="recipient_name" required class="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">Teléfono</label>
                        <input type="tel" name="recipient_phone" required class="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">Dirección</label>
                        <input type="text" name="address" required class="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-500 mb-1">Ciudad</label>
                            <input type="text" name="city" required class="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500 mb-1">Departamento</label>
                            <input type="text" name="department" required class="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">Método de pago</label>
                        <select id="checkout-payment-method" name="payment_method" required class="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500"></select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">Notas (opcional)</label>
                        <textarea name="notes" rows="2" class="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500"></textarea>
                    </div>
                    <button type="submit" class="w-full bg-emerald-600 text-white font-semibold rounded-lg py-2.5 hover:bg-emerald-700 transition">Confirmar pedido</button>
                </form>
            </div>
        </div>
    </section>
@endsection
