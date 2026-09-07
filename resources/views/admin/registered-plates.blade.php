@extends('layouts.portal')

@section('title', 'Registered Plates')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Registered Plates</h1>
            <p class="mt-1 text-sm text-gray-500">One row per plate for AI / gate scanning. Users may own multiple vehicles.</p>
        </div>
        <form method="POST" action="{{ route('admin.plates.rebuild') }}">
            @csrf
            <button type="submit" class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                Rebuild scan table
            </button>
        </form>
    </div>

    @if (session('success'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif

    <form method="GET" class="mb-4">
        <input
            type="search"
            name="q"
            value="{{ $search }}"
            placeholder="Search plate, owner, role, model…"
            class="w-full max-w-md rounded-xl border border-gray-200 px-4 py-2.5 text-sm"
        >
    </form>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Plate</th>
                        <th class="px-4 py-3">Owner</th>
                        <th class="px-4 py-3">Role</th>
                        <th class="px-4 py-3">Vehicle</th>
                        <th class="px-4 py-3">Primary</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($plates as $row)
                        <tr class="hover:bg-gray-50/80">
                            <td class="px-4 py-3 font-mono text-base font-bold tracking-wide text-indigo-800">{{ $row->plate_display ?: $row->plate_number }}</td>
                            <td class="px-4 py-3">
                                <div class="font-semibold text-gray-900">{{ $row->owner_name ?: '—' }}</div>
                                @if ($row->id_number)
                                    <div class="text-xs text-gray-500">{{ $row->id_number }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-700">{{ $row->owner_role ?: '—' }}</td>
                            <td class="px-4 py-3 text-gray-700">
                                {{ $row->vehicle_type_name ?: '—' }}
                                @if ($row->vehicle_model || $row->vehicle_color)
                                    <div class="text-xs text-gray-500">{{ collect([$row->vehicle_model, $row->vehicle_color])->filter()->implode(' · ') }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $row->is_primary ? 'Yes' : '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">{{ $row->status }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-gray-500">No registered plates yet. Click “Rebuild scan table” or add vehicles in Account Settings.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
