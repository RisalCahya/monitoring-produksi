<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f7f5">
    <title>@yield('title', 'Dashboard') · Monitoring Produksi V2</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=dm-sans:400,500,600,700&family=manrope:400,500,600,700,800" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="app-shell">
        <aside class="sidebar">
            <a href="{{ route('dashboard') }}" class="brand">
                <span class="brand-icon"><x-icon name="factory" /></span>
                <span class="brand-copy">
                    <strong>AKE WATER</strong>
                    <small>PRODUCTION MONITOR</small>
                </span>
            </a>

            <div class="sidebar-section-label">WORKSPACE</div>
            <nav class="sidebar-nav" aria-label="Main navigation">
                <a class="nav-link {{ request()->routeIs('dashboard') ? 'is-active' : '' }}" href="{{ route('dashboard') }}">
                    <x-icon name="dashboard" />
                    <span>Dashboard</span>
                </a>
                <a class="nav-link" href="{{ route('dashboard') }}#machine-monitoring">
                    <x-icon name="machine" />
                    <span>Machine Monitoring</span>
                </a>
                <a class="nav-link {{ request()->routeIs('production-history.*') ? 'is-active' : '' }}" href="{{ route('production-history.index') }}">
                    <x-icon name="history" />
                    <span>Production History</span>
                </a>
                <span class="nav-link nav-link-disabled" aria-disabled="true" title="Settings page is not available yet">
                    <x-icon name="settings" />
                    <span>Settings</span>
                    <small>SOON</small>
                </span>
            </nav>

            <div class="sidebar-bottom">
                <div class="sidebar-factory-card">
                    <span class="factory-card-icon"><x-icon name="factory" /></span>
                    <div>
                        <strong>Plant Operations</strong>
                        <small>Production line overview</small>
                    </div>
                </div>
                <div class="sidebar-footer">Monitoring Alur Produksi <span>V2.0</span></div>
            </div>
        </aside>

        <main class="main-content">
            <div class="mobile-topline">
                <a href="{{ route('dashboard') }}" class="brand mobile-brand">
                    <span class="brand-icon"><x-icon name="factory" /></span>
                    <span class="brand-copy"><strong>AKE WATER</strong><small>PRODUCTION MONITOR</small></span>
                </a>
            </div>
            <div class="page-content">
                @yield('content')
            </div>
        </main>
    </div>
</body>
</html>
