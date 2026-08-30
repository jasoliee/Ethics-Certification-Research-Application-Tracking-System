<aside class="dashboard-sidebar" id="dashboard-sidebar" aria-label="Role navigation" data-dashboard-sidebar>
    <div class="dashboard-sidebar-brand">
        <button class="dashboard-sidebar-close" type="button" aria-label="Close navigation" data-sidebar-close>
            <x-dashboard.icon name="x" />
        </button>
        <a
            class="dashboard-sidebar-logo-link"
            href="https://kld.edu.ph/profile.php"
            target="_blank"
            rel="noopener noreferrer"
            aria-label="Open the KLD profile website"
        >
            <img src="{{ Vite::asset('assets/logo-256.png') }}" alt="Kolehiyo ng Lungsod ng Dasmarinas seal">
        </a>
    </div>

    <nav class="dashboard-sidebar-nav" aria-label="Primary navigation">
        @foreach ($dashboardNavigation as $item)
            @php
                $isActive = request()->routeIs(...explode('|', $item['active']));
            @endphp
            @if (isset($item['children']))
                <details class="dashboard-nav-group" @if ($isActive) open @endif>
                    <summary class="dashboard-nav-link {{ $isActive ? 'is-active' : '' }}">
                        <x-dashboard.icon :name="$item['icon']" />
                        <span>{{ $item['label'] }}</span>
                        <x-dashboard.icon class="dashboard-nav-group-chevron" name="chevron-down" size="18" />
                    </summary>
                    <div class="dashboard-nav-submenu" aria-label="{{ $item['label'] }} navigation">
                        @foreach ($item['children'] as $child)
                            @php
                                $childActive = request()->routeIs(...explode('|', $child['active']));
                            @endphp
                            <a
                                class="dashboard-nav-link dashboard-nav-sublink {{ $childActive ? 'is-active' : '' }}"
                                href="{{ route($child['route']) }}"
                                @if ($childActive) aria-current="page" @endif
                            >
                                <x-dashboard.icon :name="$child['icon']" />
                                <span>{{ $child['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </details>
            @else
                <a
                    class="dashboard-nav-link {{ $isActive ? 'is-active' : '' }}"
                    href="{{ route($item['route']) }}"
                    @if ($isActive) aria-current="page" @endif
                >
                    <x-dashboard.icon :name="$item['icon']" />
                    <span>{{ $item['label'] }}</span>
                </a>
            @endif
        @endforeach
    </nav>

    <a
        class="dashboard-sidebar-profile {{ request()->routeIs($dashboardProfileRoute) ? 'is-active' : '' }}"
        href="{{ route($dashboardProfileRoute) }}"
        @if (request()->routeIs($dashboardProfileRoute)) aria-current="page" @endif
    >
        <span class="dashboard-avatar dashboard-avatar-light" aria-hidden="true">@if ($dashboardHasProfileImage)<img src="{{ $dashboardProfileImageUrl }}" alt="">@else{{ $dashboardUserInitials }}@endif</span>
        <span class="dashboard-sidebar-person">
            <strong>{{ auth()->user()->name }}</strong>
            <span>{{ $dashboardRoleLabel }}</span>
        </span>
    </a>
</aside>
