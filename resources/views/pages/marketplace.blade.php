@extends('layouts.app', ['page' => 'marketplace'])

@section('title', 'Marketplace')

@section('content')
    <section class="max-w-6xl mx-auto px-4 sm:px-6 py-10">
        <h1 class="text-3xl font-extrabold text-slate-900 mb-1">Marketplace</h1>
        <p class="text-slate-500 mb-6">Explora productos y servicios disponibles para comprar, alquilar o reservar.</p>

        <div class="flex flex-wrap items-center gap-3 mb-6">
            <div class="inline-flex rounded-lg border border-slate-200 bg-slate-100 p-1">
                <button type="button" id="tab-products" class="px-4 py-2 rounded-md text-sm font-semibold transition">Productos</button>
                <button type="button" id="tab-services" class="px-4 py-2 rounded-md text-sm font-semibold transition">Servicios</button>
            </div>

            <select id="filter-category" class="rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                <option value="">Todas las categorías</option>
            </select>

            <select id="filter-transaction-type" class="rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                <option value="">Venta y alquiler</option>
                <option value="sale">Solo venta</option>
                <option value="rent">Solo alquiler</option>
            </select>

            <input type="search" id="filter-search" placeholder="Buscar..." class="flex-1 min-w-[180px] rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
        </div>

        <div id="listing-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            <p class="col-span-full text-slate-400 text-center py-10">Cargando...</p>
        </div>
        <p id="listing-empty" class="hidden text-slate-400 text-center py-10">No encontramos resultados con esos filtros.</p>
    </section>
@endsection
