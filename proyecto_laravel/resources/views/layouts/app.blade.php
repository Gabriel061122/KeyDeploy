<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'KeyDeploy — Infraestructura Cloud')</title>

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'system-ui', '-apple-system', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#eef2ff',
                            100: '#e0e7ff',
                            200: '#c7d2fe',
                            500: '#6366f1',
                            600: '#4f46e5',
                            700: '#4338ca',
                            800: '#3730a3',
                            900: '#312e81',
                        }
                    },
                    boxShadow: {
                        'glow-indigo': '0 0 20px -3px rgba(99, 102, 241, 0.35)',
                        'glow-purple': '0 0 20px -3px rgba(168, 85, 247, 0.35)',
                    }
                }
            }
        }
    </script>

    <style>
        :root { --kdd: #4f46e5; --kdd-text: #0f172a; --kdd-muted: #64748b; --kdd-bg: #f8fafc; }
        * { box-sizing: border-box; }
        body { font-family: 'Inter', system-ui, -apple-system, sans-serif; background: #f8fafc; color: #0f172a; margin: 0; min-height: 100vh; display: flex; flex-direction: column; }
        .container { max-width: 68rem; margin: 0 auto; padding: 2rem 1.5rem; width: 100%; flex: 1; }
        .card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05); }
        h1 { font-size: 1.65rem; font-weight: 700; color: #0f172a; margin: 0 0 1.25rem; letter-spacing: -0.02em; }
        h2 { font-size: 1.25rem; font-weight: 600; color: #1e293b; margin: 0 0 0.75rem; letter-spacing: -0.01em; }
        h3 { font-size: 1.05rem; font-weight: 600; color: #334155; }
        table { width: 100%; border-collapse: separate; border-spacing: 0; }
        th, td { text-align: left; padding: 0.75rem 1rem; border-bottom: 1px solid #f1f5f9; font-size: 0.92rem; }
        th { color: #64748b; font-weight: 600; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; background: #f8fafc; }
        tr:last-child td { border-bottom: none; }
        label { display: block; font-size: 0.875rem; font-weight: 600; color: #334155; margin: 1rem 0 0.35rem; }
        input[type="text"], input[type="number"], input[type="email"], input[type="password"], select, textarea {
            width: 100%; padding: 0.625rem 0.875rem; border: 1px solid #cbd5e1; border-radius: 0.5rem; font: inherit; background: #fff; color: #0f172a; transition: border-color 0.15s, box-shadow 0.15s;
        }
        input:focus, select:focus, textarea:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15); }
        textarea { min-height: 5rem; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; border: 1px solid transparent; border-radius: 0.5rem; padding: 0.55rem 1.1rem; font-weight: 600; cursor: pointer; font: inherit; font-size: 0.875rem; background: #4f46e5; color: #fff; text-decoration: none; transition: all 0.15s; }
        .btn:hover { background: #4338ca; transform: translateY(-1px); }
        .btn-secondary { background: #ffffff; color: #334155; border-color: #cbd5e1; }
        .btn-secondary:hover { background: #f8fafc; border-color: #94a3b8; color: #0f172a; }
        .btn-danger { background: #e11d48; }
        .btn-danger:hover { background: #be123c; }
        .btn-sm { padding: 0.35rem 0.75rem; font-size: 0.8rem; }
        .field-error { color: #e11d48; font-size: 0.82rem; margin-top: 0.35rem; font-weight: 500; }
        .muted { color: #64748b; font-size: 0.875rem; }
        .badge { display: inline-flex; align-items: center; background: #eef2ff; border: 1px solid #c7d2fe; color: #4338ca; border-radius: 9999px; padding: 0.15rem 0.65rem; font-size: 0.75rem; font-weight: 600; }
        .actions { display: flex; gap: 0.75rem; flex-wrap: wrap; margin-top: 1.5rem; }
        .empty { color: #64748b; font-style: italic; padding: 1.5rem 0; text-align: center; }
        .toolbar { display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
        dl dl, .detail { display: grid; grid-template-columns: 14rem 1fr; row-gap: 0.75rem; }
        .detail dt { color: #64748b; font-weight: 600; font-size: 0.875rem; }
        .detail dd { margin: 0; color: #0f172a; font-size: 0.92rem; }
        fieldset.slot { border: 1px dashed #cbd5e1; border-radius: 0.625rem; padding: 1rem; margin-bottom: 1rem; background: #fafafa; }
        fieldset.slot legend { font-weight: 700; font-size: 0.875rem; padding: 0 0.5rem; color: #4338ca; }
        pre.config { background: #0f172a; color: #e2e8f0; padding: 0.75rem 1rem; border-radius: 0.5rem; font-size: 0.82rem; overflow-x: auto; font-family: ui-monospace, monospace; }
        code.inline { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; border-radius: 0.375rem; padding: 0.1rem 0.4rem; font-size: 0.85em; font-family: ui-monospace, monospace; }
    </style>
</head>
<body class="bg-slate-50 text-slate-900 antialiased">

<!-- Rainbow / Neon Top Accent Bar -->
<div class="h-1 bg-gradient-to-r from-sky-400 via-indigo-500 via-purple-500 via-pink-500 to-amber-400"></div>

<!-- Modern Glassmorphism Tailwind Header -->
<header class="bg-white/95 backdrop-blur-md border-b border-slate-200/80 sticky top-0 z-50 shadow-xs">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16 gap-4">

            <!-- Logo y Marca -->
            <div class="flex items-center gap-6">
                <a href="{{ route('projects.index') }}" class="flex items-center gap-3 group text-decoration-none focus:outline-none">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-indigo-600 via-purple-600 to-pink-500 flex items-center justify-center text-white shadow-md shadow-indigo-500/25 group-hover:scale-105 group-hover:shadow-glow-purple transition-all duration-200">
                        <!-- Cloud + Shield Icon -->
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                        </svg>
                    </div>
                    <div class="flex flex-col">
                        <div class="flex items-center gap-2">
                            <span class="text-xl font-extrabold bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600 bg-clip-text text-transparent tracking-tight">KeyDeploy</span>
                            <span class="text-[10px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded bg-gradient-to-r from-indigo-50 to-purple-50 text-indigo-700 border border-indigo-200/80">Cloud</span>
                        </div>
                        <span class="text-[11px] text-slate-400 font-medium -mt-1 hidden sm:block">Despliegue y Arquitecturas</span>
                    </div>
                </a>

                <!-- Navegación de Escritorio -->
                @auth
                <nav class="hidden md:flex items-center gap-1.5 ml-4 pl-4 border-l border-slate-200">
                    <!-- Enlace: Mis proyectos -->
                    <a href="{{ route('projects.index') }}"
                       class="flex items-center gap-2 px-3.5 py-2 rounded-lg text-sm font-semibold transition-all duration-150 {{ (request()->routeIs('projects.*') && !request()->routeIs('projects.trash')) ? 'bg-indigo-50 text-indigo-700 shadow-xs border border-indigo-100 font-bold' : 'text-slate-600 hover:text-indigo-600 hover:bg-slate-50' }}">
                        <svg class="w-4 h-4 {{ (request()->routeIs('projects.*') && !request()->routeIs('projects.trash')) ? 'text-indigo-600' : 'text-slate-400' }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                        </svg>
                        <span>Mis proyectos</span>
                    </a>

                    <!-- Enlace: Carrito -->
                    <a href="{{ route('cart.index') }}"
                       class="flex items-center gap-2 px-3.5 py-2 rounded-lg text-sm font-semibold transition-all duration-150 {{ request()->routeIs('cart.*') ? 'bg-emerald-50 text-emerald-700 shadow-xs border border-emerald-100 font-bold' : 'text-slate-600 hover:text-emerald-600 hover:bg-emerald-50/50' }}">
                        <svg class="w-4 h-4 {{ request()->routeIs('cart.*') ? 'text-emerald-600' : 'text-slate-400' }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z" />
                        </svg>
                        <span>Carrito</span>
                    </a>

                    <!-- Enlace: Papelera -->
                    <a href="{{ route('projects.trash') }}"
                       class="flex items-center gap-2 px-3.5 py-2 rounded-lg text-sm font-semibold transition-all duration-150 {{ request()->routeIs('projects.trash') ? 'bg-amber-50 text-amber-800 shadow-xs border border-amber-200 font-bold' : 'text-slate-600 hover:text-amber-600 hover:bg-amber-50/50' }}">
                        <svg class="w-4 h-4 {{ request()->routeIs('projects.trash') ? 'text-amber-600' : 'text-slate-400' }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        <span>Papelera</span>
                    </a>
                </nav>
                @endauth
            </div>

            <!-- Acciones y Perfil de Usuario -->
            <div class="flex items-center gap-3">
                @auth
                    <!-- Botón Rápido + Nuevo Proyecto (CTA colorido) -->
                    <a href="{{ route('projects.create') }}" 
                       class="hidden sm:inline-flex items-center gap-2 px-3.5 py-2 rounded-lg text-xs font-bold uppercase tracking-wider text-white bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-500 hover:from-indigo-700 hover:via-purple-700 hover:to-pink-600 shadow-sm shadow-indigo-500/25 hover:shadow-indigo-500/40 hover:-translate-y-0.5 transition-all duration-150">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>Nuevo proyecto</span>
                    </a>

                    <!-- Chip de Usuario con Avatar e Indicador -->
                    <div class="flex items-center gap-2.5 pl-3 py-1.5 pr-2 rounded-xl bg-slate-50/90 border border-slate-200/80 shadow-xs">
                        <!-- Avatar con Degradado -->
                        <div class="w-8 h-8 rounded-lg bg-gradient-to-tr from-emerald-400 via-teal-500 to-cyan-500 text-white font-bold text-xs flex items-center justify-center shadow-xs">
                            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                        </div>

                        <!-- Info del Usuario -->
                        <div class="hidden sm:flex flex-col text-left leading-tight pr-1">
                            <span class="text-xs font-bold text-slate-800">{{ auth()->user()->name }}</span>
                            <div class="flex items-center gap-1.5 mt-0.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                <span class="text-[10px] font-semibold text-emerald-700 uppercase tracking-wider bg-emerald-50 px-1.5 py-0.2 rounded border border-emerald-200">
                                    {{ auth()->user()->role ?? 'Cliente' }}
                                </span>
                            </div>
                        </div>

                        <!-- Botón de Salir -->
                        <form method="POST" action="{{ route('logout') }}" class="m-0">
                            @csrf
                            <button type="submit" 
                                    title="Cerrar sesión" 
                                    class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors duration-150 flex items-center focus:outline-none"
                                    aria-label="Cerrar sesión">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                </svg>
                            </button>
                        </form>
                    </div>
                @endauth

                @guest
                    <!-- Botones de Invitado -->
                    <a href="{{ route('login') }}" 
                       class="px-4 py-2 rounded-lg text-sm font-semibold text-slate-700 hover:text-indigo-600 hover:bg-indigo-50/60 transition-colors">
                        Acceso
                    </a>
                    <a href="{{ route('register') }}" 
                       class="px-4 py-2 rounded-lg text-sm font-bold text-white bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-500 hover:from-indigo-700 hover:via-purple-700 hover:to-pink-600 shadow-sm shadow-indigo-500/25 hover:shadow-indigo-500/40 hover:-translate-y-0.5 transition-all">
                        Registrarse
                    </a>
                @endguest
            </div>

        </div>
    </div>

    <!-- Menú inferior responsive para móviles -->
    @auth
    <div class="md:hidden flex items-center justify-around py-2 px-4 bg-slate-50 border-t border-slate-200/80 text-xs">
        <a href="{{ route('projects.index') }}" 
           class="flex items-center gap-1.5 py-1 px-3 rounded-md {{ (request()->routeIs('projects.*') && !request()->routeIs('projects.trash')) ? 'text-indigo-700 bg-indigo-50 font-bold' : 'text-slate-600' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" /></svg>
            <span>Mis proyectos</span>
        </a>
        <a href="{{ route('projects.trash') }}" 
           class="flex items-center gap-1.5 py-1 px-3 rounded-md {{ request()->routeIs('projects.trash') ? 'text-amber-800 bg-amber-50 font-bold' : 'text-slate-600' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
            <span>Papelera</span>
        </a>
        <a href="{{ route('projects.create') }}" 
           class="flex items-center gap-1.5 py-1 px-3 rounded-md font-bold text-white bg-indigo-600">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
            <span>Nuevo</span>
        </a>
    </div>
    @endauth
</header>

<!-- Main Container -->
<main class="container">
    @if (session('success'))
        <div class="mb-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200/80 text-emerald-900 shadow-xs flex items-start gap-3">
            <div class="w-6 h-6 rounded-full bg-emerald-500 text-white flex items-center justify-center shrink-0 mt-0.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
            </div>
            <div class="flex-1 text-sm font-medium">
                {{ session('success') }}
            </div>
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-6 p-4 rounded-xl bg-rose-50 border border-rose-200/80 text-rose-900 shadow-xs flex items-start gap-3">
            <div class="w-6 h-6 rounded-full bg-rose-500 text-white flex items-center justify-center shrink-0 mt-0.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
            </div>
            <div class="flex-1">
                <strong class="text-sm font-bold text-rose-950">Revisa los siguientes datos:</strong>
                <ul class="mt-1.5 space-y-1 text-xs list-disc list-inside text-rose-800">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    @yield('content')
</main>

<!-- Footer sutil y elegante -->
<footer class="mt-auto border-t border-slate-200/60 bg-white/50 py-6 text-center text-xs text-slate-400">
    <div class="max-w-7xl mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-2">
        <span>&copy; {{ date('Y') }} <strong>KeyDeploy</strong> — Plataforma de Arquitecturas Cloud</span>
        <span class="inline-flex items-center gap-1.5 text-slate-500">
            <span class="w-2 h-2 rounded-full bg-emerald-500"></span> Sistema Operativo
        </span>
    </div>
</footer>

</body>
</html>
