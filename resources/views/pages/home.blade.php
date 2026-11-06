@extends('layouts.app', ['page' => 'home'])

@section('title', 'Inicio')

@section('content')
    <section class="bg-gradient-to-b from-emerald-50 to-slate-50 border-b border-slate-200">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-16 sm:py-20 text-center">
            <h1 class="text-4xl sm:text-5xl font-extrabold text-slate-900 leading-tight">
                Compra, vende y alquila<br class="hidden sm:block"> en un solo lugar
            </h1>
            <p class="mt-4 text-lg text-slate-600 max-w-2xl mx-auto">
                Mercova conecta a compradores, vendedores y pequeños emprendimientos en un marketplace simple y confiable.
            </p>
            <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                <a href="{{ url('/marketplace') }}" class="px-6 py-3 rounded-lg bg-emerald-600 text-white font-semibold hover:bg-emerald-700 transition">Explorar productos</a>
                <a href="{{ url('/marketplace?type=services') }}" class="px-6 py-3 rounded-lg bg-white border border-slate-300 text-slate-700 font-semibold hover:border-emerald-500 hover:text-emerald-600 transition">Alquilar y reservar</a>
                <a href="{{ url('/emprendimientos') }}" class="px-6 py-3 rounded-lg bg-indigo-600 text-white font-semibold hover:bg-indigo-700 transition">Vender como emprendimiento</a>
            </div>
        </div>
    </section>

    <section class="max-w-6xl mx-auto px-4 sm:px-6 py-6">
        <div id="home-categories" class="flex gap-2 overflow-x-auto pb-2"></div>
    </section>

    <section class="max-w-6xl mx-auto px-4 sm:px-6 py-8">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-2xl font-bold text-slate-800">Productos destacados</h2>
            <a href="{{ url('/marketplace') }}" class="text-sm font-semibold text-emerald-600 hover:underline">Ver todo</a>
        </div>
        <div id="home-featured-products" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            <p class="text-slate-400 col-span-full text-center py-8">Cargando productos...</p>
        </div>
    </section>

    <section class="max-w-6xl mx-auto px-4 sm:px-6 py-8">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-2xl font-bold text-slate-800">Servicios populares</h2>
            <a href="{{ url('/marketplace?type=services') }}" class="text-sm font-semibold text-indigo-600 hover:underline">Ver todo</a>
        </div>
        <div id="home-featured-services" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            <p class="text-slate-400 col-span-full text-center py-8">Cargando servicios...</p>
        </div>
    </section>

    <section class="bg-slate-900 border-t border-slate-800">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-16 sm:py-20">
            <div class="text-center mb-12">
                <span class="text-sm font-semibold uppercase tracking-wide text-emerald-400">Para emprendimientos</span>
                <h2 class="text-2xl sm:text-3xl font-bold text-white mt-2">Elige el plan que impulsa tu negocio</h2>
                <p class="mt-3 text-slate-300 max-w-2xl mx-auto">
                    Desde un primer paso gratuito hasta marketing y domicilios prioritarios. Crece a tu ritmo dentro de Mercova.
                </p>
            </div>
            <div id="home-plans" class="grid grid-cols-1 sm:grid-cols-3 gap-6 items-stretch">
                <p class="text-slate-400 col-span-full text-center py-8">Cargando planes...</p>
            </div>
            <p class="text-center text-slate-400 text-sm mt-8">
                ¿Ya tienes claro tu plan?
                <a href="{{ url('/emprendimientos') }}" class="font-semibold text-emerald-400 hover:underline">Registra tu emprendimiento y suscríbete</a>
            </p>
        </div>
    </section>
@endsection
