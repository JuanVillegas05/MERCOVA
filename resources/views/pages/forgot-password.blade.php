@extends('layouts.app', ['page' => 'forgot-password'])

@section('title', 'Recuperar contraseña')

@section('content')
    <section class="max-w-md mx-auto px-4 sm:px-6 py-16">
        <div class="bg-white rounded-2xl border border-slate-200 p-8">
            <h1 class="text-2xl font-bold text-slate-800 mb-1">Recupera tu contraseña</h1>
            <p class="text-sm text-slate-500 mb-6">Te enviaremos un enlace para crear una nueva contraseña.</p>

            <form id="forgot-password-form" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Correo electrónico</label>
                    <input type="email" name="email" required class="w-full rounded-lg border-slate-300 focus:border-emerald-500 focus:ring-emerald-500">
                </div>
                <button type="submit" class="w-full bg-emerald-600 text-white font-semibold rounded-lg py-2.5 hover:bg-emerald-700 transition">Enviar enlace</button>
            </form>

            <p class="text-sm text-slate-500 mt-6 text-center">
                <a href="{{ url('/login') }}" class="text-emerald-600 font-semibold hover:underline">Volver a iniciar sesión</a>
            </p>
        </div>
    </section>
@endsection
