@extends('layouts.admin', ['page' => 'admin-plans'])

@section('title', 'Planes de membresía')

@section('content')
    <h1 class="text-2xl font-bold text-slate-800 mb-1">Planes de membresía</h1>
    <p class="text-slate-500 mb-6">Define el precio y los límites de cada plan. Los cambios se reflejan de inmediato en /emprendimientos.</p>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 border-b border-slate-200">
                        <th class="px-4 py-3 font-medium">Plan</th>
                        <th class="px-4 py-3 font-medium">Precio</th>
                        <th class="px-4 py-3 font-medium">Límite</th>
                        <th class="px-4 py-3 font-medium">Emprendimientos</th>
                        <th class="px-4 py-3 font-medium">Estado</th>
                        <th class="px-4 py-3 font-medium">Acciones</th>
                    </tr>
                </thead>
                <tbody id="plans-table-body">
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">Cargando...</td></tr>
                </tbody>
            </table>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-6">
            <h2 id="plan-form-title" class="font-bold text-slate-800 mb-3">Nuevo plan</h2>
            <form id="plan-form" class="space-y-3">
                <input type="hidden" name="id">
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Nombre</label>
                    <input type="text" name="name" required class="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">Precio (COP)</label>
                        <input type="number" name="price" min="0" step="100" required class="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">Periodo</label>
                        <select name="billing_period" class="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                            <option value="mensual">Mensual</option>
                            <option value="anual">Anual</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Límite de publicaciones (vacío = ilimitado)</label>
                    <input type="number" name="max_listings" min="1" class="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Descripción</label>
                    <textarea name="description" rows="2" class="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Características (una por línea)</label>
                    <textarea name="features" rows="3" placeholder="Hasta 30 publicaciones activas&#10;Soporte prioritario" class="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500"></textarea>
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="is_active" checked class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                    Plan activo (visible en /emprendimientos)
                </label>
                <div class="flex gap-2">
                    <button type="submit" class="flex-1 bg-emerald-600 text-white text-sm font-semibold rounded-lg py-2 hover:bg-emerald-700 transition">Guardar</button>
                    <button type="button" id="plan-form-cancel" class="hidden px-3 text-sm font-medium text-slate-500 hover:text-slate-700">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
@endsection
