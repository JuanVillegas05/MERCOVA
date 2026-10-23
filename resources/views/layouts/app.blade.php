<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Mercova') · Mercova</title>
    <meta name="description" content="Mercova: compra, vende y alquilla productos y servicios. Espacio para emprendimientos con membresías.">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body data-page="{{ $page ?? '' }}" class="min-h-screen bg-slate-50 text-slate-800 flex flex-col">

    <header class="bg-white border-b border-slate-200 sticky top-0 z-40">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 flex items-center justify-between h-16">
            <a href="{{ url('/') }}" class="flex items-center gap-2 font-bold text-xl text-emerald-600">
                <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-600 text-white">M</span>
                Mercova
            </a>

            <nav class="hidden md:flex items-center gap-6 text-sm font-medium text-slate-600">
                <a href="{{ url('/') }}" class="hover:text-emerald-600 transition">Inicio</a>
                <a href="{{ url('/marketplace') }}" class="hover:text-emerald-600 transition">Marketplace</a>
                <a href="{{ url('/emprendimientos') }}" class="hover:text-emerald-600 transition">Emprendimientos</a>
                <a href="{{ url('/nosotros') }}" class="hover:text-emerald-600 transition">Nosotros</a>
            </nav>

            <form action="{{ url('/marketplace') }}" method="get" class="hidden lg:flex items-center flex-1 max-w-xs mx-4">
                <input type="search" name="search" placeholder="Buscar productos..."
                    class="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
            </form>

            <div class="flex items-center gap-3">
                <div data-auth="user" class="hidden relative">
                    <button type="button" id="notif-bell-btn" class="relative p-2 text-slate-500 hover:text-emerald-600 transition" aria-label="Notificaciones">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                        <span id="notif-badge" class="hidden absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 rounded-full bg-red-500 text-white text-[10px] font-bold flex items-center justify-center">0</span>
                    </button>
                    <div id="notif-dropdown" class="hidden absolute right-0 mt-2 w-80 max-w-[90vw] bg-white text-slate-800 rounded-xl shadow-xl border border-slate-200 overflow-hidden z-50">
                        <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100">
                            <p class="font-semibold text-sm">Notificaciones</p>
                            <button type="button" id="notif-mark-all" class="text-xs font-medium text-emerald-600 hover:underline">Marcar todas leídas</button>
                        </div>
                        <div id="notif-list" class="max-h-96 overflow-y-auto"></div>
                    </div>
                </div>
                <a href="{{ url('/favoritos') }}" data-auth="user" class="hidden p-2 text-slate-500 hover:text-red-500 transition" aria-label="Favoritos">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21s-7-4.35-9.5-8.5C.5 9 2 5 5.5 5c2 0 3.5 1.2 4.5 2.8C11 6.2 12.5 5 14.5 5 18 5 19.5 9 19.5 12.5 17 16.65 12 21 12 21z" />
                    </svg>
                </a>
                <a href="{{ url('/carrito') }}" class="relative p-2 text-slate-500 hover:text-emerald-600 transition" aria-label="Carrito">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 1.936-4.709 2.436-7.182.075-.371-.223-.718-.602-.718H5.106M7.5 14.25L5.106 5.25M7.5 14.25L5.25 18.75m14.25-3a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span data-cart-count class="hidden absolute -top-1 -right-1 inline-flex items-center justify-center h-5 w-5 rounded-full bg-emerald-600 text-white text-xs font-bold">0</span>
                </a>

                <div data-auth="guest" class="flex items-center gap-2">
                    <a href="{{ url('/login') }}" class="px-4 py-2 text-sm font-medium text-slate-700 hover:text-emerald-600 transition">Iniciar sesión</a>
                    <a href="{{ url('/registro') }}" class="px-4 py-2 text-sm font-semibold text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition">Registrarse</a>
                </div>
                <div data-auth="user" class="hidden items-center gap-3">
                    <a href="{{ url('/panel') }}" data-panel-link class="text-sm text-right leading-tight hover:text-emerald-600">
                        <span class="block font-semibold" data-user-name>&nbsp;</span>
                        <span class="block text-xs text-slate-400" data-user-role>&nbsp;</span>
                    </a>
                    <a href="{{ url('/panel') }}" data-panel-link class="px-4 py-2 text-sm font-semibold text-emerald-700 bg-emerald-50 rounded-lg hover:bg-emerald-100 transition">Mi panel</a>
                    <button type="button" data-action="logout" class="px-3 py-2 text-sm font-medium text-slate-500 hover:text-red-600 transition">Salir</button>
                </div>
            </div>
        </div>
        <nav class="md:hidden flex items-center gap-4 text-sm font-medium text-slate-600 px-4 pb-3 overflow-x-auto">
            <a href="{{ url('/') }}" class="whitespace-nowrap hover:text-emerald-600">Inicio</a>
            <a href="{{ url('/marketplace') }}" class="whitespace-nowrap hover:text-emerald-600">Marketplace</a>
            <a href="{{ url('/emprendimientos') }}" class="whitespace-nowrap hover:text-emerald-600">Emprendimientos</a>
            <a href="{{ url('/nosotros') }}" class="whitespace-nowrap hover:text-emerald-600">Nosotros</a>
        </nav>
    </header>

    <div id="toast-container" class="fixed top-20 right-4 z-50 flex flex-col gap-2 w-80 max-w-[90vw]"></div>

    <main class="flex-1">
        @yield('content')
    </main>

    <footer class="bg-white border-t border-slate-200 mt-16">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-10 grid grid-cols-1 sm:grid-cols-3 gap-8 text-sm text-slate-500">
            <div>
                <p class="flex items-center gap-2 font-bold text-lg text-slate-800 mb-2">
                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-md bg-emerald-600 text-white text-sm">M</span>
                    Mercova
                </p>
                <p>Marketplace para comprar, vender y alquilar productos y servicios, con espacio para emprendimientos locales.</p>
            </div>
            <div>
                <p class="font-semibold text-slate-700 mb-2">Navegación</p>
                <ul class="space-y-1">
                    <li><a href="{{ url('/marketplace') }}" class="hover:text-emerald-600">Marketplace</a></li>
                    <li><a href="{{ url('/emprendimientos') }}" class="hover:text-emerald-600">Emprendimientos</a></li>
                    <li><a href="{{ url('/nosotros') }}" class="hover:text-emerald-600">Nosotros</a></li>
                </ul>
            </div>
            <div>
                <p class="font-semibold text-slate-700 mb-2">Cuenta</p>
                <ul class="space-y-1">
                    <li><a href="{{ url('/login') }}" class="hover:text-emerald-600">Iniciar sesión</a></li>
                    <li><a href="{{ url('/registro') }}" class="hover:text-emerald-600">Registrarse</a></li>
                </ul>
            </div>
        </div>
        <p class="text-center text-xs text-slate-400 pb-6">&copy; {{ date('Y') }} Mercova. Todos los derechos reservados.</p>
    </footer>
</body>
</html>
