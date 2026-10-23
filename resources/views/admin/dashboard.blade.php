@extends('layouts.admin', ['page' => 'admin-dashboard'])

@section('title', 'Resumen')

@section('content')
    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div>
            <p class="flex items-center gap-2 text-xs font-semibold text-emerald-600 uppercase tracking-wide mb-1">
                <span class="relative flex h-2 w-2">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                </span>
                Panel en vivo
            </p>
            <h1 class="text-2xl font-extrabold text-slate-900">Mercova Admin</h1>
            <p class="text-sm text-slate-500 mt-0.5">Control de ventas, conversión y actividad de la plataforma.</p>
        </div>

        <div class="flex items-center gap-3">
            <select id="dashboard-range" class="rounded-lg border-slate-300 text-sm font-medium text-slate-700 focus:border-emerald-500 focus:ring-emerald-500">
                <option value="today">Hoy</option>
                <option value="week">Esta semana</option>
                <option value="month">Este mes</option>
                <option value="all">Todo el tiempo</option>
            </select>
            <a href="{{ url('/') }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 bg-slate-900 text-white text-sm font-semibold rounded-lg px-4 py-2 hover:bg-slate-800 transition">
                Ver sitio web
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                </svg>
            </a>
        </div>
    </div>

    <div class="flex items-center gap-2 border-b border-slate-200 mb-6">
        <button type="button" data-tab="summary" class="dashboard-tab px-4 py-2.5 rounded-t-lg text-sm font-semibold">Resumen general</button>
        <button type="button" data-tab="live" class="dashboard-tab px-4 py-2.5 rounded-t-lg text-sm font-semibold flex items-center gap-2">
            Pedidos en tiempo real
            <span id="live-orders-badge" class="min-w-[20px] h-5 px-1.5 rounded-full bg-red-500 text-white text-[11px] font-bold flex items-center justify-center">0</span>
        </button>
    </div>

    <section id="dashboard-tab-summary">
        <div id="admin-stats" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
            <p class="col-span-full text-slate-400 text-center py-8">Cargando estadísticas...</p>
        </div>

        <div id="admin-extra-stats" class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8"></div>

        <div class="bg-white rounded-xl border border-slate-200 p-6 mb-8">
            <h2 class="font-bold text-slate-800">Crecimiento de la app</h2>
            <p class="text-xs text-slate-400 mb-4">Usuarios y pedidos nuevos por día, últimos 14 días</p>
            <div id="growth-chart" class="flex items-end gap-1.5 h-40">
                <p class="text-slate-400 text-sm py-4">Cargando...</p>
            </div>
            <div class="flex items-center gap-4 mt-3 text-xs text-slate-500">
                <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-emerald-500"></span> Usuarios nuevos</span>
                <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-indigo-400"></span> Pedidos nuevos</span>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <div class="bg-white rounded-xl border border-slate-200 p-6">
                <h2 class="font-bold text-slate-800">Estado de los pedidos</h2>
                <p class="text-xs text-slate-400 mb-4">Según el rango de fecha seleccionado arriba</p>
                <div id="status-breakdown" class="space-y-3">
                    <p class="text-slate-400 text-sm py-4">Cargando...</p>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-6">
                <h2 class="font-bold text-slate-800">Tipo de solicitud</h2>
                <p class="text-xs text-slate-400 mb-4">Compras, reservas de servicio y alquileres</p>
                <div id="origin-chart" class="flex items-center gap-6">
                    <p class="text-slate-400 text-sm py-4">Cargando...</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white rounded-xl border border-slate-200 p-6">
                <h2 class="font-bold text-slate-800 mb-3">Pedidos recientes</h2>
                <div id="admin-recent-orders"></div>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-6">
                <h2 class="font-bold text-slate-800 mb-3">Actividad reciente</h2>
                <div id="admin-recent-activity"></div>
            </div>
        </div>
    </section>

    <section id="dashboard-tab-live" class="hidden">
        <div class="bg-white rounded-xl border border-slate-200 p-6">
            <div class="flex items-center justify-between mb-3">
                <div>
                    <h2 class="font-bold text-slate-800">Pedidos pendientes por atender</h2>
                    <p class="text-xs text-slate-400">Se actualiza solo cada 15 segundos</p>
                </div>
                <a href="{{ url('/admin/pedidos') }}" class="text-sm font-medium text-emerald-600 hover:underline">Ver todos los pedidos →</a>
            </div>
            <div id="live-orders-list"></div>
        </div>
    </section>
@endsection
