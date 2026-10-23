@extends('layouts.app', ['page' => 'payment-return'])

@section('title', 'Resultado del pago')

@section('content')
    <section class="max-w-md mx-auto px-4 sm:px-6 py-20 text-center">
        <div class="bg-white rounded-2xl border border-slate-200 p-8">
            <h1 class="text-2xl font-extrabold text-slate-900 mb-2">Gracias por tu pago</h1>
            <p class="text-slate-500 mb-6">
                Estamos confirmando tu pago con la pasarela. Puede tardar unos segundos en
                reflejarse en tu pedido; no necesitas intentarlo de nuevo.
            </p>
            <a href="{{ url('/panel') }}" class="inline-block bg-emerald-600 text-white font-semibold rounded-lg py-2.5 px-6 hover:bg-emerald-700 transition">
                Ir a mi panel
            </a>
        </div>
    </section>
@endsection
