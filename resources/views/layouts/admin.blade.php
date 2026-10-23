<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Panel admin') · Mercova</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body data-page="{{ $page ?? '' }}" class="min-h-screen bg-slate-100 text-slate-800 flex flex-col">

    <header class="bg-slate-900 text-white sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 flex items-center justify-between h-16">
            <div class="flex items-center gap-8">
                <a href="{{ url('/admin') }}" class="flex items-center gap-2 font-bold text-lg">
                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-500 text-white">M</span>
                    Mercova <span class="text-xs font-medium text-slate-400 ml-1">Admin</span>
                </a>
                <nav class="hidden md:flex items-center gap-5 text-sm font-medium text-slate-300">
                    <a href="{{ url('/admin') }}" class="hover:text-white transition">Resumen</a>
                    <a href="{{ url('/admin/pedidos') }}" class="hover:text-white transition">Pedidos</a>
                    <a href="{{ url('/admin/productos') }}" class="hover:text-white transition">Publicaciones</a>
                    <a href="{{ url('/admin/emprendimientos') }}" class="hover:text-white transition">Emprendimientos</a>
                    <a href="{{ url('/admin/categorias') }}" class="hover:text-white transition">Categorías</a>
                    <a href="{{ url('/admin/planes') }}" class="hover:text-white transition">Planes</a>
                    <a href="{{ url('/admin/usuarios') }}" class="hover:text-white transition">Usuarios</a>
                </nav>
            </div>

            <div class="flex items-center gap-4">
                <div class="relative">
                    <button type="button" id="notif-bell-btn" class="relative p-2 rounded-lg hover:bg-slate-800 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                        <span id="notif-badge" class="hidden absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 rounded-full bg-red-500 text-white text-[10px] font-bold flex items-center justify-center">0</span>
                    </button>
                    <div id="notif-dropdown" class="hidden absolute right-0 mt-2 w-80 max-w-[90vw] bg-white text-slate-800 rounded-xl shadow-xl border border-slate-200 overflow-hidden">
                        <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100">
                            <p class="font-semibold text-sm">Notificaciones</p>
                            <button type="button" id="notif-mark-all" class="text-xs font-medium text-emerald-600 hover:underline">Marcar todas leídas</button>
                        </div>
                        <div id="notif-list" class="max-h-96 overflow-y-auto"></div>
                    </div>
                </div>

                <a href="{{ url('/') }}" class="hidden sm:inline text-sm text-slate-300 hover:text-white transition">Volver al sitio</a>

                <div class="text-sm text-right leading-tight hidden sm:block">
                    <span class="block font-semibold" data-user-name>&nbsp;</span>
                    <span class="block text-xs text-slate-400" data-user-role>&nbsp;</span>
                </div>
                <button type="button" data-action="logout" class="px-3 py-2 text-sm font-medium text-slate-300 hover:text-red-400 transition">Salir</button>
            </div>
        </div>
        <nav class="md:hidden flex items-center gap-4 text-sm font-medium text-slate-300 px-4 pb-3 overflow-x-auto">
            <a href="{{ url('/admin') }}" class="whitespace-nowrap hover:text-white">Resumen</a>
            <a href="{{ url('/admin/pedidos') }}" class="whitespace-nowrap hover:text-white">Pedidos</a>
            <a href="{{ url('/admin/productos') }}" class="whitespace-nowrap hover:text-white">Publicaciones</a>
            <a href="{{ url('/admin/emprendimientos') }}" class="whitespace-nowrap hover:text-white">Emprendimientos</a>
            <a href="{{ url('/admin/categorias') }}" class="whitespace-nowrap hover:text-white">Categorías</a>
            <a href="{{ url('/admin/planes') }}" class="whitespace-nowrap hover:text-white">Planes</a>
            <a href="{{ url('/admin/usuarios') }}" class="whitespace-nowrap hover:text-white">Usuarios</a>
        </nav>
    </header>

    <div id="toast-container" class="fixed top-20 right-4 z-50 flex flex-col gap-2 w-80 max-w-[90vw]"></div>

    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 py-8">
        @yield('content')
    </main>
</body>
</html>
