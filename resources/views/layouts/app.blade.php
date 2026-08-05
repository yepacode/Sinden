<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}"/>
    <title>{{ config('app.name', 'SINDEN') }}</title>

    {{-- Theme bootstrap (anti-FOUC) - DEBE ir antes de cualquier <link> CSS --}}
    <script>
        (function () {
            var serverTheme = @json(Auth::user()?->theme);
            var stored = localStorage.getItem('sinden-theme');
            var preference = stored || (serverTheme ? serverTheme : 'auto');
            var resolved = preference;
            if (preference === 'auto') {
                resolved = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            }
            // Sembrar localStorage si solo viene del server (mantiene consistencia)
            if (!stored && serverTheme) localStorage.setItem('sinden-theme', serverTheme);
            var html = document.documentElement;
            html.setAttribute('data-theme', resolved);
            html.setAttribute('data-bs-theme', resolved);
            if (resolved === 'dark') html.classList.add('dark');
        })();
    </script>

    {{-- Fuentes --}}
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    {{-- Font Awesome 6 --}}
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    {{-- Bootstrap Icons --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    {{-- Bootstrap 5 CSS --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    {{-- Override Bootstrap success (verde → gris acero) --}}
    <style>
        :root {
            --bs-success: #64748b;
            --bs-success-rgb: 100, 116, 139;
        }
        .btn-success {
            --bs-btn-bg: #64748b;
            --bs-btn-border-color: #64748b;
            --bs-btn-hover-bg: #475569;
            --bs-btn-hover-border-color: #475569;
            --bs-btn-active-bg: #334155;
            --bs-btn-active-border-color: #334155;
            --bs-btn-disabled-bg: #64748b;
            --bs-btn-disabled-border-color: #64748b;
        }
        .btn-outline-success {
            --bs-btn-color: #64748b;
            --bs-btn-border-color: #64748b;
            --bs-btn-hover-bg: #64748b;
            --bs-btn-hover-border-color: #64748b;
            --bs-btn-active-bg: #475569;
            --bs-btn-active-border-color: #475569;
        }
        .text-success { color: #64748b !important; }
        .bg-success { background-color: #64748b !important; }
        .bg-success-subtle { background-color: #f1f5f9 !important; }
        .badge.bg-success { background-color: #64748b !important; }
        .table-success {
            --bs-table-bg: #f1f5f9;
            --bs-table-border-color: #e2e8f0;
            --bs-table-striped-bg: #f1f5f9;
        }
        .alert-success {
            --bs-alert-bg: #f8fafc;
            --bs-alert-border-color: #e2e8f0;
            --bs-alert-color: #475569;
        }
        [data-bs-theme="dark"] .alert-success {
            --bs-alert-bg: #1e293b;
            --bs-alert-border-color: #334155;
            --bs-alert-color: #cbd5e1;
        }
        [data-bs-theme="dark"] .table-success {
            --bs-table-bg: #1e293b;
            --bs-table-border-color: #334155;
            --bs-table-striped-bg: #1e293b;
            --bs-table-color: #cbd5e1;
        }
        [data-bs-theme="dark"] .bg-success-subtle {
            background-color: #1e293b !important;
        }
    </style>

    {{-- Tailwind CSS (para componentes Blade - con preflight deshabilitado) --}}
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            corePlugins: {
                preflight: false,
            }
        }
    </script>

    {{-- SINDEN CSS (con cache-busting por filemtime para que el navegador tome siempre la ultima version) --}}
    <link href="{{ asset('css/gva-global.css') }}?v={{ filemtime(public_path('css/gva-global.css')) }}" rel="stylesheet">
    <link href="{{ asset('css/gva-dashboard.css') }}?v={{ filemtime(public_path('css/gva-dashboard.css')) }}" rel="stylesheet">
    <link href="{{ asset('css/gva-components.css') }}?v={{ filemtime(public_path('css/gva-components.css')) }}" rel="stylesheet">
    <link href="{{ asset('css/sinden-components.css') }}?v={{ filemtime(public_path('css/sinden-components.css')) }}" rel="stylesheet">

    {{-- Conexion Handler CSS --}}
    <link href="{{ asset('css/conexion-handler.css') }}?v={{ filemtime(public_path('css/conexion-handler.css')) }}" rel="stylesheet">

    {{-- DataTables CSS --}}
    <link href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css" rel="stylesheet">

    {{-- Flatpickr CSS (date picker unificado) --}}
    <link href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css" rel="stylesheet">
    <link id="flatpickrDarkTheme" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/themes/dark.css" rel="stylesheet" disabled>

    @stack('styles')
</head>
<body>
    {{-- Banner Offline (oculto por defecto, se muestra via JS) --}}
    <div id="sindenOfflineBanner" class="sinden-offline-banner">
        <i class="bi bi-wifi-off me-2"></i>
        <span>Sin conexion a internet. Los cambios se guardaran localmente.</span>
    </div>

    {{-- Header --}}
    <header class="dashboard-header">
        <div class="header-container" style="position: relative;">
            <div class="header-left">
                <button class="sidebar-toggle" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
            </div>

            <div class="nav-logo" style="position: absolute; left: 50%; transform: translateX(-50%); display: flex; align-items: center;">
                <img src="{{ asset('images/logo.png') }}" alt="Logo" style="height: 32px; margin-right: 8px;"> {{ config('app.name', 'SINDEN') }}
            </div>

            <div class="header-right">
                {{-- Toggle modo oscuro --}}
                <button type="button" class="theme-toggle-btn" id="themeToggleBtn" title="Cambiar tema">
                    <i class="bi bi-moon-fill"></i>
                    <i class="bi bi-sun-fill"></i>
                </button>

                {{-- Indicador de conexion --}}
                <span class="sinden-conexion-dot online" id="conexionDot" title="Conectado"></span>

                {{-- Campana de notificaciones --}}
                <div class="notif-bell-wrapper" id="notifBellWrapper">
                    <button class="notif-bell-btn" id="notifBellBtn" title="Notificaciones">
                        <i class="bi bi-bell"></i>
                        <span class="notif-badge" id="notifBadge" style="display:none;">0</span>
                    </button>
                    <div class="notif-panel" id="notifPanel" style="display:none;">
                        <div class="notif-panel-header">
                            <span class="notif-panel-title">Notificaciones</span>
                            <button class="notif-panel-close" id="notifPanelClose"><i class="bi bi-x-lg"></i></button>
                        </div>
                        <div class="notif-panel-body" id="notifPanelBody">
                            <div class="notif-empty">Sin notificaciones</div>
                        </div>
                        <div class="notif-panel-footer">
                            <button class="notif-mark-all" id="notifMarkAll">Marcar todas como leidas</button>
                            <button class="notif-delete-all" id="notifDeleteAll">Eliminar todas</button>
                        </div>
                    </div>
                </div>

                <a href="{{ route('profile.edit') }}" class="user-menu" style="text-decoration: none; color: inherit;" title="Ir a Mi Perfil">
                    <div class="user-avatar">
                        @if(Auth::user()->hasProfilePhoto())
                            <img src="{{ Auth::user()->profile_photo_url }}"
                                 alt="Foto de perfil"
                                 style="width: 36px; height: 36px; border-radius: 50%; object-fit: cover;">
                        @else
                            <i class="fas fa-user"></i>
                        @endif
                    </div>
                    <div class="user-info">
                        <span class="user-name">{{ Auth::user()->name }}</span>
                        <span class="user-role">{{ Auth::user()->roles->first()->name ?? 'Usuario' }}</span>
                    </div>
                </a>
            </div>
        </div>
    </header>

    {{-- Sidebar --}}
    <aside class="sidebar" id="sidebar">
        @include('layouts.navigation-vertical')
    </aside>

    {{-- Contenido Principal --}}
    <main class="main-content" id="mainContent">
        {{-- Flash Messages --}}
        @if(session('success'))
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i>
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if(session('warning'))
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <span>{{ session('warning') }}</span>
            </div>
        @endif

        {{-- Soporte para @yield('content') y {{ $slot }} --}}
        @hasSection('content')
            @yield('content')
        @else
            {{ $slot ?? '' }}
        @endif
    </main>

    {{-- JavaScript --}}
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    {{-- Theme toggle (dark mode) --}}
    <script src="{{ asset('js/theme-toggle.js') }}?v={{ filemtime(public_path('js/theme-toggle.js')) }}"></script>

    {{-- Bootstrap 5 JS (para modales) --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    {{-- DataTables --}}
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.colVis.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>

    {{-- SweetAlert2 --}}
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    {{-- Alpine.js --}}
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    {{-- Fabric.js v5 (Canvas profesional para dibujo de bosquejos) --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js"></script>

    {{-- Flatpickr (date picker unificado) --}}
    <script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/es.js"></script>
    <script src="{{ asset('js/flatpickr-init.js') }}?v={{ filemtime(public_path('js/flatpickr-init.js')) }}"></script>

    {{-- Main JS --}}
    <script src="{{ asset('js/gva-main.js') }}?v={{ filemtime(public_path('js/gva-main.js')) }}"></script>

    {{-- Notificaciones --}}
    <div id="notifToastContainer"></div>
    <script src="{{ asset('js/notificaciones.js') }}?v={{ filemtime(public_path('js/notificaciones.js')) }}"></script>

    {{-- Conexion Handler (debe ir despues de jQuery y SweetAlert2) --}}
    <script src="{{ asset('js/conexion-handler.js') }}?v={{ filemtime(public_path('js/conexion-handler.js')) }}"></script>

    @stack('scripts')
</body>
</html>
