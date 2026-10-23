@extends('layouts.admin', ['page' => 'admin-products'])

@section('title', 'Publicaciones')

@section('content')
    <h1 class="text-2xl font-bold text-slate-800 mb-1">Publicaciones</h1>
    <p class="text-slate-500 mb-6">Todos los productos y servicios publicados en Mercova.</p>

    <div class="bg-white rounded-xl border border-slate-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-200">
                    <th class="px-4 py-3 font-medium">Título</th>
                    <th class="px-4 py-3 font-medium">Publicado por</th>
                    <th class="px-4 py-3 font-medium">Tipo</th>
                    <th class="px-4 py-3 font-medium">Precio</th>
                    <th class="px-4 py-3 font-medium">Estado</th>
                    <th class="px-4 py-3 font-medium">Acciones</th>
                </tr>
            </thead>
            <tbody id="listings-table-body">
                <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">Cargando...</td></tr>
            </tbody>
        </table>
    </div>
@endsection
