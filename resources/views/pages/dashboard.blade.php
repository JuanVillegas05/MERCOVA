@extends('layouts.app', ['page' => 'dashboard'])

@section('title', 'Mi panel')

@section('content')
    <section class="max-w-6xl mx-auto px-4 sm:px-6 py-10 grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-1 space-y-6">
            <div id="dashboard-profile" class="bg-white rounded-xl border border-slate-200 p-6"></div>

            <div class="bg-white rounded-xl border border-slate-200 p-6">
                <h2 class="font-bold text-slate-800 mb-3">Editar perfil</h2>
                <form id="profile-form" class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">Nombre</label>
                        <input type="text" name="name" required class="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">Teléfono</label>
                        <input type="tel" name="phone" class="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">Biografía</label>
                        <textarea name="bio" rows="2" class="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500"></textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">URL de avatar</label>
                        <input type="url" name="avatar_url" placeholder="https://..." class="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                    </div>
                    <button type="submit" class="w-full bg-emerald-600 text-white text-sm font-semibold rounded-lg py-2 hover:bg-emerald-700 transition">Guardar cambios</button>
                </form>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 p-6">
                <h2 class="font-bold text-slate-800 mb-3">Cambiar contraseña</h2>
                <form id="password-form" class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">Contraseña actual</label>
                        <input type="password" name="current_password" required class="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">Nueva contraseña</label>
                        <input type="password" name="password" required minlength="8" class="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">Confirmar nueva contraseña</label>
                        <input type="password" name="password_confirmation" required minlength="8" class="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                    </div>
                    <button type="submit" class="w-full bg-slate-800 text-white text-sm font-semibold rounded-lg py-2 hover:bg-slate-900 transition">Actualizar contraseña</button>
                </form>
            </div>
        </div>

        <div class="lg:col-span-2 space-y-8">
            <div class="bg-white rounded-xl border border-slate-200 p-6">
                <h2 class="font-bold text-slate-800 mb-2">Mis publicaciones</h2>
                <div id="dashboard-listings"></div>
            </div>

            <div id="create-listing-section" class="hidden bg-white rounded-xl border border-slate-200 p-6">
                <h2 class="font-bold text-slate-800 mb-1">Publicar producto o servicio</h2>
                <p class="text-xs text-slate-500 mb-4" id="business-hint"></p>

                <div class="inline-flex rounded-lg border border-slate-200 bg-slate-100 p-1 mb-4">
                    <button type="button" data-listing-type="products" class="bg-white px-4 py-2 rounded-md text-sm font-semibold transition">Producto</button>
                    <button type="button" data-listing-type="services" class="px-4 py-2 rounded-md text-sm font-semibold transition">Servicio</button>
                </div>

                <form id="create-listing-form" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Publicar como</label>
                        <select id="listing-business" name="business_id" class="w-full rounded-lg border-slate-300 focus:border-emerald-500 focus:ring-emerald-500">
                            <option value="">Publicación personal</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Título</label>
                        <input type="text" name="title" required class="w-full rounded-lg border-slate-300 focus:border-emerald-500 focus:ring-emerald-500">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Descripción</label>
                        <textarea name="description" rows="3" required class="w-full rounded-lg border-slate-300 focus:border-emerald-500 focus:ring-emerald-500"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Categoría</label>
                        <select id="listing-category" name="category_id" required class="w-full rounded-lg border-slate-300 focus:border-emerald-500 focus:ring-emerald-500"></select>
                    </div>
                    <div id="sale-price-section" class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Precio</label>
                        <div id="price-mode-toggle" class="inline-flex rounded-lg border border-slate-200 bg-slate-100 p-1 mb-2">
                            <button type="button" data-price-mode="price" class="bg-white px-4 py-2 rounded-md text-sm font-semibold transition">Precio de venta</button>
                            <button type="button" data-price-mode="desired_net_price" class="px-4 py-2 rounded-md text-sm font-semibold transition">Ganancia neta que quiero recibir</button>
                        </div>
                        <input type="number" id="listing-price-input" name="price" step="0.01" min="0" required placeholder="Ej: 50000"
                            class="w-full rounded-lg border-slate-300 focus:border-emerald-500 focus:ring-emerald-500">
                    </div>

                    <div id="rent-price-section" class="hidden sm:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Precio por día</label>
                            <input type="number" id="rent-price-input" name="price_per_day" step="0.01" min="0" placeholder="Ej: 50000"
                                class="w-full rounded-lg border-slate-300 focus:border-emerald-500 focus:ring-emerald-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Depósito/garantía (opcional)</label>
                            <input type="number" id="rent-deposit-input" name="deposit_amount" step="0.01" min="0" placeholder="Ej: 100000"
                                class="w-full rounded-lg border-slate-300 focus:border-emerald-500 focus:ring-emerald-500">
                        </div>
                    </div>

                    <div id="commission-breakdown" class="hidden sm:col-span-2 text-xs rounded-lg bg-slate-50 border border-slate-200 p-3 space-y-1">
                        <p class="flex justify-between"><span class="text-slate-500" id="breakdown-gross-label">Valor publicado (lo que paga el comprador)</span><span class="font-semibold text-slate-800" id="breakdown-gross">—</span></p>
                        <p class="flex justify-between"><span class="text-slate-500">Comisión MERCOVA (<span id="breakdown-rate">6</span>%)</span><span class="font-semibold text-red-500" id="breakdown-commission">—</span></p>
                        <p class="flex justify-between"><span class="text-slate-500">Tú recibes (neto)</span><span class="font-semibold text-emerald-600" id="breakdown-net">—</span></p>
                    </div>

                    <div id="product-only-fields" class="sm:col-span-2 grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Tipo</label>
                            <select id="listing-transaction-type" name="transaction_type" class="w-full rounded-lg border-slate-300 focus:border-emerald-500 focus:ring-emerald-500">
                                <option value="sale">Venta</option>
                                <option value="rent">Alquiler</option>
                            </select>
                        </div>
                        <div id="stock-field">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Stock</label>
                            <input type="number" name="stock" min="0" value="1" class="w-full rounded-lg border-slate-300 focus:border-emerald-500 focus:ring-emerald-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Condición</label>
                            <input type="text" name="condition" placeholder="Nuevo, usado..." class="w-full rounded-lg border-slate-300 focus:border-emerald-500 focus:ring-emerald-500">
                        </div>
                    </div>

                    <div id="service-only-fields" class="hidden sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Duración</label>
                        <input type="text" name="duration" placeholder="Ej: 2 horas" class="w-full rounded-lg border-slate-300 focus:border-emerald-500 focus:ring-emerald-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Ubicación</label>
                        <input type="text" name="location" class="w-full rounded-lg border-slate-300 focus:border-emerald-500 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">URL de imagen</label>
                        <input type="url" name="image_url" placeholder="https://..." class="w-full rounded-lg border-slate-300 focus:border-emerald-500 focus:ring-emerald-500">
                    </div>

                    <div class="sm:col-span-2">
                        <button type="submit" class="w-full bg-emerald-600 text-white font-semibold rounded-lg py-2.5 hover:bg-emerald-700 transition">Publicar</button>
                    </div>
                </form>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 p-6">
                <h2 class="font-bold text-slate-800 mb-2">Mis compras</h2>
                <div id="dashboard-orders"></div>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 p-6">
                <h2 class="font-bold text-slate-800 mb-2">Mis reservas</h2>
                <div id="dashboard-bookings"></div>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 p-6">
                <h2 class="font-bold text-slate-800 mb-2">Mis alquileres</h2>
                <div id="dashboard-rentals"></div>
            </div>

            <div id="owned-rentals-section" class="hidden bg-white rounded-xl border border-slate-200 p-6">
                <h2 class="font-bold text-slate-800 mb-2">Solicitudes de alquiler recibidas</h2>
                <div id="dashboard-owned-rentals"></div>
            </div>
        </div>
    </section>
@endsection
