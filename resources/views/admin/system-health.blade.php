@extends('layouts.admin')

@section('title', 'System Health')

@section('content')
    @include('partials.shell.page-header', [
        'title' => 'System Health',
        'subtitle' => 'Live probes for web, database, AI, cameras, and storage (Admin only)',
    ])

    @php
        $snapshot = $snapshot ?? ['checked_at' => null, 'components' => [], 'cameras' => []];
        $statusClass = [
            'online' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
            'offline' => 'bg-red-100 text-red-800 border-red-200',
            'disabled' => 'bg-gray-100 text-gray-600 border-gray-200',
            'unknown' => 'bg-amber-100 text-amber-800 border-amber-200',
            'degraded' => 'bg-amber-100 text-amber-800 border-amber-200',
        ];
    @endphp

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-gray-500">
            Checked at: <span class="font-medium text-gray-800">{{ $snapshot['checked_at'] ?? '—' }}</span>
        </p>
        <div class="flex gap-2">
            <a href="{{ route('admin.system-health') }}" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Refresh</a>
            <a href="{{ route('admin.system-health.status') }}" class="rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-100">JSON</a>
        </div>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($snapshot['components'] ?? [] as $row)
            @php $cls = $statusClass[$row['status'] ?? 'unknown'] ?? $statusClass['unknown']; @endphp
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <h3 class="font-semibold text-gray-900">{{ $row['label'] ?? $row['id'] }}</h3>
                    <span class="rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase {{ $cls }}">{{ $row['status'] ?? 'unknown' }}</span>
                </div>
                <p class="mt-2 text-sm text-gray-600">{{ $row['detail'] ?? '' }}</p>
            </div>
        @endforeach
    </div>

    <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-4 py-3">
            <h3 class="font-semibold text-gray-900">Cameras</h3>
            <p class="text-xs text-gray-500">Status comes from live AI health / ingest — not from config alone.</p>
        </div>
        <ul class="divide-y divide-gray-100">
            @forelse ($snapshot['cameras'] ?? [] as $cam)
                @php $cls = $statusClass[$cam['status'] ?? 'unknown'] ?? $statusClass['unknown']; @endphp
                <li class="flex flex-wrap items-start justify-between gap-3 px-4 py-3">
                    <div>
                        <p class="font-medium text-gray-900">{{ $cam['name'] ?? $cam['id'] }} <span class="text-xs text-gray-400">({{ $cam['id'] }})</span></p>
                        <p class="mt-0.5 text-sm text-gray-600">{{ $cam['detail'] ?? '' }}</p>
                    </div>
                    <span class="rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase {{ $cls }}">{{ $cam['status'] ?? 'unknown' }}</span>
                </li>
            @empty
                <li class="px-4 py-8 text-center text-sm text-gray-500">No cameras configured in .env / services.ai_parking.</li>
            @endforelse
        </ul>
    </div>

    <p class="mt-6 text-xs text-gray-400">
        Tokens, camera passwords, and database URIs are intentionally not shown on this page.
        Ops scripts: <code class="rounded bg-gray-100 px-1">scripts\status-system.ps1</code>
    </p>
@endsection
