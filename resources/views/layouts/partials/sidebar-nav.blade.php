@php
    $navItems = \App\Services\NavigationService::routesForRole(auth()->user()?->roleName());
    $activeClass = \App\Services\NavigationService::navActiveClassForRole(auth()->user()?->roleName());
@endphp

@foreach ($navItems as $item)
    @php
        $routePattern = str_ends_with($item['route'], '.dashboard')
            ? $item['route']
            : $item['route'].'*';
    @endphp
    <x-portal.nav-item
        href="{{ route($item['route']) }}"
        :icon="$item['icon']"
        :label="$item['label']"
        :active="request()->routeIs($routePattern)"
        :active-class="$activeClass"
    />
@endforeach
