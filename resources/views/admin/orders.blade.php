@extends('layouts.admin', ['page' => 'admin-orders'])

@section('title', 'Pedidos')

@section('content')
    <h1 class="text-2xl font-bold text-slate-800 mb-1">Pedidos y reservas</h1>
    <p class="text-slate-500 mb-6">Gestiona compras de productos y reservas de servicios.</p>

    <div class="flex flex-wrap items-center gap-3 mb-6">
        <div class="inline-flex rounded-lg border border-slate-200 bg-white p-1">
            <button type="button" id="tab-orders" class="px-4 py-2 rounded-md text-sm font-semibold transition">Compras</button>
            <button type="button" id="tab-bookings" class="px-4 py-2 rounded-md text-sm font-semibold transition">Reservas</button>
        </div>

        <select id="filter-status" class="rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
            <option value="">Todos los estados</option>
            <option value="pending">Pendientes</option>
            <option value="processing">En proceso</option>
            <option value="completed">Completados</option>
            <option value="cancelled">Cancelados</option>
        </select>

        <label class="inline-flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" id="filter-new" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
            Solo nuevos (últimas 24h)
        </label>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-200">
                    <th class="px-4 py-3 font-medium">ID</th>
                    <th class="px-4 py-3 font-medium">Cliente</th>
                    <th class="px-4 py-3 font-medium" id="col-extra">Vendedor</th>
                    <th class="px-4 py-3 font-medium">Total</th>
                    <th class="px-4 py-3 font-medium">Fecha</th>
                    <th class="px-4 py-3 font-medium">Estado</th>
                    <th class="px-4 py-3 font-medium">Acción</th>
                </tr>
            </thead>
            <tbody id="orders-table-body">
                <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">Cargando...</td></tr>
            </tbody>
        </table>
    </div>
@endsection
