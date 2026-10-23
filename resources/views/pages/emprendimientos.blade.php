@extends('layouts.app', ['page' => 'emprendimientos'])

@section('title', 'Emprendimientos')

@section('content')
    <section class="bg-indigo-50 border-b border-indigo-100">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 py-14 text-center">
            <span class="text-sm font-semibold uppercase tracking-wide text-indigo-600">Espacio para emprendimientos</span>
            <h1 class="text-3xl sm:text-4xl font-extrabold text-slate-900 mt-2">Haz crecer tu emprendimiento en Mercova</h1>
            <p class="mt-3 text-slate-600 max-w-2xl mx-auto">
                Registra tu emprendimiento, elige una membresía y comienza a ofrecer tus productos y servicios a toda la comunidad.
            </p>
        </div>
    </section>

    <section class="max-w-5xl mx-auto px-4 sm:px-6 py-12">
        <div id="emprendimientos-guest-cta" class="hidden bg-white border border-slate-200 rounded-xl p-6 text-center mb-10">
            <p class="text-slate-700 font-medium mb-3">Inicia sesión o crea una cuenta para registrar tu emprendimiento.</p>
            <div class="flex justify-center gap-3">
                <a href="{{ url('/login?next=/emprendimientos') }}" class="px-5 py-2 rounded-lg border border-slate-300 font-semibold text-slate-700 hover:border-emerald-500 hover:text-emerald-600 transition">Iniciar sesión</a>
                <a href="{{ url('/registro') }}" class="px-5 py-2 rounded-lg bg-emerald-600 text-white font-semibold hover:bg-emerald-700 transition">Registrarse</a>
            </div>
        </div>

        <div id="business-section" class="hidden mb-12">
            <div id="business-panel" class="hidden mb-6"></div>

            <div id="business-form-wrapper" class="hidden bg-white border border-slate-200 rounded-xl p-6">
                <h2 class="text-lg font-bold text-slate-800 mb-1">Registra tu emprendimiento</h2>
                <p class="text-sm text-slate-500 mb-4">Cuéntanos sobre tu emprendimiento para crear su perfil en Mercova.</p>
                <form id="business-form" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Nombre del emprendimiento</label>
                        <input type="text" name="name" required class="w-full rounded-lg border-slate-300 focus:border-emerald-500 focus:ring-emerald-500">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Descripción</label>
                        <textarea name="description" rows="3" class="w-full rounded-lg border-slate-300 focus:border-emerald-500 focus:ring-emerald-500"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Categoría</label>
                        <input type="text" name="category" placeholder="Ej: Alimentos, Diseño..." class="w-full rounded-lg border-slate-300 focus:border-emerald-500 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Teléfono</label>
                        <input type="tel" name="phone" class="w-full rounded-lg border-slate-300 focus:border-emerald-500 focus:ring-emerald-500">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Dirección</label>
                        <input type="text" name="address" class="w-full rounded-lg border-slate-300 focus:border-emerald-500 focus:ring-emerald-500">
                    </div>
                    <div class="sm:col-span-2">
                        <button type="submit" class="w-full bg-emerald-600 text-white font-semibold rounded-lg py-2.5 hover:bg-emerald-700 transition">Registrar emprendimiento</button>
                    </div>
                </form>
            </div>
            <p id="business-hint" class="text-xs text-amber-600 mt-3"></p>
        </div>

        <h2 class="text-2xl font-bold text-slate-800 mb-6 text-center">Planes de membresía</h2>
        <div id="plans-grid" class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-16">
            <p class="col-span-full text-slate-400 text-center py-8">Cargando planes...</p>
        </div>

        <h2 class="text-2xl font-bold text-slate-800 mb-6 text-center">Emprendimientos destacados</h2>
        <div id="business-directory" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <p class="col-span-full text-slate-400 text-center py-8">Cargando emprendimientos...</p>
        </div>
    </section>
@endsection
