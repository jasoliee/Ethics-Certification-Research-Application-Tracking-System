<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $pageTitle ?? 'Dashboard' }} | ECRATS</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="ecrats-dashboard-body">
    <div class="dashboard-shell" data-dashboard-shell>
        <x-dashboard.sidebar />

        <div class="dashboard-sidebar-backdrop" data-sidebar-backdrop hidden></div>

        <div class="dashboard-workspace">
            <x-dashboard.topbar :title="$pageTitle ?? 'Dashboard'" :breadcrumbs="$breadcrumbs ?? []" />

            <main class="dashboard-content" id="main-content" tabindex="-1">
                @if (session('status'))
                    <div class="dashboard-flash" role="status">
                        <x-dashboard.icon name="check" />
                        <span>{{ session('status') }}</span>
                    </div>
                @endif

                @yield('content')
            </main>

            <x-dashboard.footer />
        </div>
    </div>
</body>
</html>
