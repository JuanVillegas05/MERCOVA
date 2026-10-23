@extends('layouts.app', ['page' => 'product'])

@section('title', 'Producto')

@section('content')
    <section class="max-w-5xl mx-auto px-4 sm:px-6 py-10">
        <a href="{{ url('/marketplace') }}" class="text-sm text-slate-500 hover:text-emerald-600 mb-4 inline-block">&larr; Volver al marketplace</a>

        <div id="product-detail" class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <p class="col-span-full text-slate-400 text-center py-20">Cargando...</p>
        </div>

        <div id="product-reviews" class="mt-10"></div>
    </section>
@endsection
