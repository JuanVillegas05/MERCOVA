@extends('layouts.app', ['page' => 'about'])

@section('title', 'Nosotros')

@section('content')
    <section class="max-w-4xl mx-auto px-4 sm:px-6 py-16 text-center">
        <span class="text-sm font-semibold uppercase tracking-wide text-emerald-600">Nosotros</span>
        <h1 class="text-4xl font-extrabold text-slate-900 mt-2">Somos Mercova</h1>
        <p class="mt-4 text-lg text-slate-600">
            Un marketplace creado por estudiantes para conectar a personas y pequeños emprendimientos que quieren comprar,
            vender y alquilar productos y servicios de forma simple y segura.
        </p>
    </section>

    <section class="max-w-5xl mx-auto px-4 sm:px-6 grid grid-cols-1 sm:grid-cols-3 gap-6 pb-16">
        <div class="bg-white rounded-xl border border-slate-200 p-6">
            <h3 class="font-bold text-slate-800 mb-2">Nuestra misión</h3>
            <p class="text-sm text-slate-600">Facilitar el acceso a un espacio de compra, venta y arriendo confiable, con oportunidades reales para emprendimientos pequeños.</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-6">
            <h3 class="font-bold text-slate-800 mb-2">Nuestra visión</h3>
            <p class="text-sm text-slate-600">Ser el marketplace de referencia para comunidades locales, donde cualquier persona pueda emprender sin barreras.</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-6">
            <h3 class="font-bold text-slate-800 mb-2">Nuestros valores</h3>
            <p class="text-sm text-slate-600">Confianza, cercanía y apoyo a los emprendimientos locales que hacen crecer nuestra comunidad.</p>
        </div>
    </section>

    <section class="bg-white border-y border-slate-200">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 py-16">
            <h2 class="text-2xl font-bold text-slate-800 text-center mb-8">¿Qué puedes hacer en Mercova?</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                <div class="text-center">
                    <div class="mx-auto mb-3 h-12 w-12 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center text-xl font-bold">1</div>
                    <p class="font-semibold text-slate-800">Comprar</p>
                    <p class="text-sm text-slate-500 mt-1">Encuentra productos de vendedores y emprendimientos verificados.</p>
                </div>
                <div class="text-center">
                    <div class="mx-auto mb-3 h-12 w-12 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center text-xl font-bold">2</div>
                    <p class="font-semibold text-slate-800">Alquilar</p>
                    <p class="text-sm text-slate-500 mt-1">Arrienda productos y reserva servicios por el tiempo que necesites.</p>
                </div>
                <div class="text-center">
                    <div class="mx-auto mb-3 h-12 w-12 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center text-xl font-bold">3</div>
                    <p class="font-semibold text-slate-800">Vender</p>
                    <p class="text-sm text-slate-500 mt-1">Publica tus productos y servicios, o registra tu emprendimiento con una membresía.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="max-w-4xl mx-auto px-4 sm:px-6 py-16 text-center">
        <h2 class="text-2xl font-bold text-slate-800 mb-3">¿Listo para empezar?</h2>
        <div class="flex flex-wrap items-center justify-center gap-3">
            <a href="{{ url('/registro') }}" class="px-6 py-3 rounded-lg bg-emerald-600 text-white font-semibold hover:bg-emerald-700 transition">Crear cuenta</a>
            <a href="{{ url('/emprendimientos') }}" class="px-6 py-3 rounded-lg bg-white border border-slate-300 text-slate-700 font-semibold hover:border-emerald-500 hover:text-emerald-600 transition">Ver membresías</a>
        </div>
    </section>
@endsection
