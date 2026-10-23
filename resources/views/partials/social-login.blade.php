@if(config('mercova.social_login.google') || config('mercova.social_login.apple'))
    <div class="my-5 flex items-center gap-3">
        <div class="flex-1 h-px bg-slate-200"></div>
        <span class="text-xs text-slate-400">o continúa con</span>
        <div class="flex-1 h-px bg-slate-200"></div>
    </div>

    <div class="space-y-2">
        @if(config('mercova.social_login.google'))
            <a href="{{ route('social.redirect', 'google') }}" class="w-full flex items-center justify-center gap-2 border border-slate-300 rounded-lg py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 transition">
                <svg class="w-4 h-4" viewBox="0 0 24 24" aria-hidden="true">
                    <path fill="#4285F4" d="M23.52 12.27c0-.85-.08-1.67-.22-2.45H12v4.64h6.47c-.28 1.5-1.13 2.78-2.4 3.63v3.02h3.88c2.27-2.09 3.57-5.17 3.57-8.84z"/>
                    <path fill="#34A853" d="M12 24c3.24 0 5.96-1.07 7.95-2.9l-3.88-3.02c-1.08.72-2.45 1.15-4.07 1.15-3.13 0-5.78-2.11-6.73-4.95H1.27v3.11C3.25 21.3 7.31 24 12 24z"/>
                    <path fill="#FBBC05" d="M5.27 14.28A7.2 7.2 0 0 1 4.88 12c0-.79.14-1.56.39-2.28V6.6H1.27A11.98 11.98 0 0 0 0 12c0 1.93.46 3.76 1.27 5.4l4-3.12z"/>
                    <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.6 4.6 1.8l3.44-3.44C17.95 1.19 15.24 0 12 0 7.31 0 3.25 2.7 1.27 6.6l4 3.12c.95-2.84 3.6-4.97 6.73-4.97z"/>
                </svg>
                Continuar con Google
            </a>
        @endif

        @if(config('mercova.social_login.apple'))
            <a href="{{ route('social.redirect', 'apple') }}" class="w-full flex items-center justify-center gap-2 bg-black rounded-lg py-2.5 text-sm font-medium text-white hover:bg-slate-800 transition">
                <svg class="w-4 h-4" viewBox="0 0 384 512" fill="currentColor" aria-hidden="true">
                    <path d="M318.7 268.7c-.2-36.7 16.4-64.4 50-84.8-18.8-26.9-47.2-41.7-84.7-44.6-35.5-2.8-74.3 20.7-88.5 20.7-15 0-49.4-19.7-76.4-19.7C63.3 141 0 184.5 0 273.4c0 26.2 4.8 53.3 14.4 81.2 12.8 36.7 59 126.7 107.2 125.2 25.2-.6 43-17.9 75.8-17.9 31.8 0 48.3 17.9 76.4 17.9 48.6-.7 90.4-82.5 102.6-119.3-65.2-30.7-57.7-90-57.7-91.8zm-56.6-164.2c27-32.1 24.5-61.2 23.7-71.8-23.8 1.4-51.3 16.4-67 34.9-17.3 19.8-27.5 44.3-25.3 70.4 24.9 1.9 47.6-10.7 68.6-33.5z"/>
                </svg>
                Continuar con Apple
            </a>
        @endif
    </div>
@endif
