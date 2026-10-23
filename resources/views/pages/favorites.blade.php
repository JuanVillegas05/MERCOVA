@extends('layouts.app', ['page' => 'favorites'])

@section('title', 'Mis favoritos')

@section('content')
    <section class="max-w-6xl mx-auto px-4 sm:px-6 py-10">
        <h1 class="text-2xl font-extrabold text-slate-900 mb-1">Mis favoritos</h1>
        <p class="text-slate-500 mb-6">Productos que guardaste para revisar más tarde.</p>

        <div id="favorites-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            <p class="col-span-full text-slate-400 text-center py-10">Cargando...</p>
        </div>
        <p id="favorites-empty" class="hidden text-slate-400 text-center py-10">
            Todavía no tienes favoritos. <a href="{{ url('/marketplace') }}" class="text-emerald-600 font-medium hover:underline">Explora el marketplace</a>.
        </p>
    </section>
@endsection
