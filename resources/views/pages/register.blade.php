@extends('layouts.app', ['page' => 'register'])

@section('title', 'Registrarse')

@section('content')
    <section class="max-w-md mx-auto px-4 sm:px-6 py-16">
        <div class="bg-white rounded-2xl border border-slate-200 p-8">
            <h1 class="text-2xl font-bold text-slate-800 mb-1">Crea tu cuenta</h1>
            <p class="text-sm text-slate-500 mb-6">Regístrate para comprar, vender o alquilar productos y servicios.</p>

            <form id="register-form" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Nombre completo</label>
                    <input type="text" name="name" required class="w-full rounded-lg border-slate-300 focus:border-emerald-500 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Correo electrónico</label>
                    <input type="email" name="email" required class="w-full rounded-lg border-slate-300 focus:border-emerald-500 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Teléfono (opcional)</label>
                    <input type="tel" name="phone" class="w-full rounded-lg border-slate-300 focus:border-emerald-500 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Contraseña</label>
                    <input type="password" name="password" required minlength="8" class="w-full rounded-lg border-slate-300 focus:border-emerald-500 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Confirmar contraseña</label>
                    <input type="password" name="password_confirmation" required minlength="8" class="w-full rounded-lg border-slate-300 focus:border-emerald-500 focus:ring-emerald-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">¿Qué quieres hacer en Mercova?</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <label class="flex items-start gap-2 border border-slate-200 rounded-lg p-3 cursor-pointer has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50">
                            <input type="radio" name="role" value="customer" checked class="mt-1">
                            <span>
                                <span class="block text-sm font-semibold text-slate-800">Comprar y alquilar</span>
                                <span class="block text-xs text-slate-500">Quiero explorar y reservar productos y servicios.</span>
                            </span>
                        </label>
                        <label class="flex items-start gap-2 border border-slate-200 rounded-lg p-3 cursor-pointer has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50">
                            <input type="radio" name="role" value="seller" class="mt-1">
                            <span>
                                <span class="block text-sm font-semibold text-slate-800">Vender y alquilar</span>
                                <span class="block text-xs text-slate-500">Quiero publicar mis propios productos o servicios.</span>
                            </span>
                        </label>
                    </div>
                </div>

                <button type="submit" class="w-full bg-emerald-600 text-white font-semibold rounded-lg py-2.5 hover:bg-emerald-700 transition">Crear cuenta</button>
            </form>

            @include('partials.social-login')

            <p class="text-sm text-slate-500 mt-6 text-center">
                ¿Ya tienes cuenta? <a href="{{ url('/login') }}" class="text-emerald-600 font-semibold hover:underline">Inicia sesión</a>
            </p>
            <p class="text-xs text-slate-400 mt-3 text-center">
                ¿Tienes un emprendimiento? Regístrate y luego visita
                <a href="{{ url('/emprendimientos') }}" class="text-emerald-600 hover:underline">Emprendimientos</a> para activar tu membresía.
            </p>
        </div>
    </section>
@endsection
