@php
    $current = $active ?? '';
@endphp
<nav class="mb-6 flex flex-wrap gap-2 border-b border-gray-200 pb-4">
    <a href="{{ route('admin.parking.zone-access') }}"
       @class([
           'inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-medium transition-colors',
           'bg-blue-600 text-white' => $current === 'zone-access',
           'bg-gray-100 text-gray-700 hover:bg-gray-200' => $current !== 'zone-access',
       ])>
        <i data-lucide="shield-check" class="h-4 w-4"></i>
        Zone Access
    </a>
    <a href="{{ route('admin.parking') }}"
       @class([
           'inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-medium transition-colors',
           'bg-blue-600 text-white' => $current === 'overview',
           'bg-gray-100 text-gray-700 hover:bg-gray-200' => $current !== 'overview',
       ])>
        <i data-lucide="layout-grid" class="h-4 w-4"></i>
        Slot Overview
    </a>
    <a href="{{ route('admin.parking.layout') }}"
       @class([
           'inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-medium transition-colors',
           'bg-blue-600 text-white' => $current === 'layout',
           'bg-gray-100 text-gray-700 hover:bg-gray-200' => $current !== 'layout',
       ])>
        <i data-lucide="map-pin" class="h-4 w-4"></i>
        Zones & Spaces
    </a>
    <a href="{{ route('admin.settings', ['section' => 'parking']) }}"
       class="inline-flex items-center gap-1.5 rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-200">
        <i data-lucide="file-text" class="h-4 w-4"></i>
        Parking Rules
    </a>
</nav>
