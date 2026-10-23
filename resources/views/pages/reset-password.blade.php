@extends('layouts.app', ['page' => 'reset-password'])

@section('title', 'Restablecer contraseña')

@section('content')
    <section class="max-w-md mx-auto px-4 sm:px-6 py-16">
        <div class="bg-white rounded-2xl border border-slate-200 p-8">
            <h1 class="text-2xl font-bold text-slate-800 mb-1">Crea una nueva contraseña</h1>
            <p class="text-sm text-slate-500 mb-6">Usa el enlace que recibiste para definir tu nueva contraseña.</p>

            <form id="reset-password-form" class="space-y-4">
                <input type="hidden" name="token">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Correo electrónico</label>
                    <input type="email" name="email" required class="w-full rounded-lg border-slate-300 focus:border-emerald-500 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Nueva contraseña</label>
                    <input type="password" name="password" required minlength="8" class="w-full rounded-lg border-slate-300 focus:border-emerald-500 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Confirmar contraseña</label>
                    <input type="password" name="password_confirmation" required minlength="8" class="w-full rounded-lg border-slate-300 focus:border-emerald-500 focus:ring-emerald-500">
                </div>
                <button type="submit" class="w-full bg-emerald-600 text-white font-semibold rounded-lg py-2.5 hover:bg-emerald-700 transition">Restablecer contraseña</button>
            </form>
        </div>
    </section>
@endsection
