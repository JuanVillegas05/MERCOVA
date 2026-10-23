@extends('layouts.app', ['page' => 'login'])

@section('title', 'Iniciar sesión')

@section('content')
    <section class="max-w-md mx-auto px-4 sm:px-6 py-16">
        <div class="bg-white rounded-2xl border border-slate-200 p-8">
            <h1 class="text-2xl font-bold text-slate-800 mb-1">Inicia sesión</h1>
            <p class="text-sm text-slate-500 mb-6">Ingresa para comprar, vender o alquilar en Mercova.</p>

            <form id="login-form" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Correo electrónico</label>
                    <input type="email" name="email" required class="w-full rounded-lg border-slate-300 focus:border-emerald-500 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Contraseña</label>
                    <input type="password" name="password" required class="w-full rounded-lg border-slate-300 focus:border-emerald-500 focus:ring-emerald-500">
                </div>
                <button type="submit" class="w-full bg-emerald-600 text-white font-semibold rounded-lg py-2.5 hover:bg-emerald-700 transition">Iniciar sesión</button>
            </form>

            @include('partials.social-login')

            <p class="text-sm text-center mt-4">
                <a href="{{ url('/olvide-password') }}" class="text-slate-500 hover:text-emerald-600 hover:underline">¿Olvidaste tu contraseña?</a>
            </p>

            <p class="text-sm text-slate-500 mt-4 text-center">
                ¿No tienes cuenta? <a href="{{ url('/registro') }}" class="text-emerald-600 font-semibold hover:underline">Regístrate aquí</a>
            </p>

            <div class="mt-6 pt-6 border-t border-slate-100 text-xs text-slate-400">
                <p class="font-semibold mb-1">Cuentas de demostración:</p>
                <p>admin@mercova.test · seller@mercova.test · customer@mercova.test</p>
                <p>Contraseña: password123</p>
            </div>
        </div>
    </section>
@endsection
