<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $pageTitle ?? 'Dashboard' }} | ECRATS</title>
    <link rel="icon" type="image/png" href="{{ Vite::asset('assets/logo-256.png') }}">

    <script>
        try {
            if (window.localStorage.getItem('ecrats:dashboard-sidebar-collapsed') === 'true') {
                document.documentElement.dataset.dashboardSidebarCollapsed = 'true';
            }
        } catch (error) {
            // The dashboard remains usable when browser storage is unavailable.
        }
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="ecrats-dashboard-body">
    <div class="dashboard-shell" data-dashboard-shell>
        <x-dashboard.sidebar />

        <button
            class="dashboard-sidebar-toggle"
            type="button"
            aria-label="Hide navigation sidebar"
            aria-controls="dashboard-sidebar"
            aria-expanded="true"
            title="Hide navigation sidebar"
            data-sidebar-toggle
        >
            <span data-sidebar-toggle-expanded-icon><x-dashboard.icon name="chevron-left" size="19" /></span>
            <span data-sidebar-toggle-collapsed-icon><x-dashboard.icon name="chevron-right" size="19" /></span>
        </button>

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

                <x-dashboard.onboarding-guide
                    :guide="$dashboardOnboardingGuide"
                    :requires-completion="$dashboardRequiresOnboarding"
                />
            </main>

            <x-dashboard.footer />
        </div>
    </div>
</body>
</html>
