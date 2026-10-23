@extends('layouts.admin', ['page' => 'admin-categories'])

@section('title', 'Categorías')

@section('content')
    <div class="flex items-center justify-between mb-1">
        <h1 class="text-2xl font-bold text-slate-800">Categorías</h1>
    </div>
    <p class="text-slate-500 mb-6">Administra las categorías y subcategorías del marketplace.</p>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 border-b border-slate-200">
                        <th class="px-4 py-3 font-medium">Nombre</th>
                        <th class="px-4 py-3 font-medium">Categoría padre</th>
                        <th class="px-4 py-3 font-medium">Publicaciones</th>
                        <th class="px-4 py-3 font-medium">Acciones</th>
                    </tr>
                </thead>
                <tbody id="categories-table-body">
                    <tr><td colspan="4" class="px-4 py-8 text-center text-slate-400">Cargando...</td></tr>
                </tbody>
            </table>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-6">
            <h2 id="category-form-title" class="font-bold text-slate-800 mb-3">Nueva categoría</h2>
            <form id="category-form" class="space-y-3">
                <input type="hidden" name="id">
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Nombre</label>
                    <input type="text" name="name" required class="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Categoría padre (opcional)</label>
                    <select name="parent_id" class="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                        <option value="">Ninguna (categoría principal)</option>
                    </select>
                    <p class="text-[11px] text-slate-400 mt-1">Si eliges una categoría padre, esta se vuelve una subcategoría.</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Ícono (opcional)</label>
                    <input type="text" name="icon" placeholder="Ej: home, smartphone" class="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Descripción (opcional)</label>
                    <textarea name="description" rows="2" class="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500"></textarea>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="flex-1 bg-emerald-600 text-white text-sm font-semibold rounded-lg py-2 hover:bg-emerald-700 transition">Guardar</button>
                    <button type="button" id="category-form-cancel" class="hidden px-3 text-sm font-medium text-slate-500 hover:text-slate-700">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
@endsection
