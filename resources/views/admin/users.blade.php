@extends('layouts.admin', ['page' => 'admin-users'])

@section('title', 'Usuarios')

@section('content')
    <h1 class="text-2xl font-bold text-slate-800 mb-1">Usuarios</h1>
    <p class="text-slate-500 mb-6">Todos los usuarios registrados en Mercova.</p>

    <div class="mb-4">
        <input type="search" id="user-search" placeholder="Buscar por nombre o correo..." class="w-full max-w-sm rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
    </div>

    <div class="bg-white rounded-xl border border-slate-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-200">
                    <th class="px-4 py-3 font-medium">Nombre</th>
                    <th class="px-4 py-3 font-medium">Correo</th>
                    <th class="px-4 py-3 font-medium">Rol</th>
                    <th class="px-4 py-3 font-medium">Publicaciones</th>
                    <th class="px-4 py-3 font-medium">Pedidos</th>
                    <th class="px-4 py-3 font-medium">Estado</th>
                    <th class="px-4 py-3 font-medium">Acción</th>
                </tr>
            </thead>
            <tbody id="users-table-body">
                <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">Cargando...</td></tr>
            </tbody>
        </table>
    </div>
@endsection
