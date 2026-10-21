@extends('layouts.admin')

@section('title', 'Overnight Permits')

@section('content')
    @include('partials.shell.page-header', [
        'title' => 'Overnight parking',
        'subtitle' => 'Approve a stay only when the uploaded proof shows it is allowed',
    ])

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-gray-500">
                    <tr>
                        <th class="px-5 py-3 font-medium">Plate</th>
                        <th class="px-5 py-3 font-medium">Window</th>
                        <th class="px-5 py-3 font-medium">Reason</th>
                        <th class="px-5 py-3 font-medium">Proof</th>
                        <th class="px-5 py-3 font-medium">Decision</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($permits as $permit)
                        <tr>
                            <td class="px-5 py-4">
                                <p class="font-semibold text-gray-900">{{ $permit->plate_number }}</p>
                                <p class="text-xs text-gray-500">{{ $permit->user?->fullname }}</p>
                            </td>
                            <td class="px-5 py-4 text-gray-700">
                                {{ ph_datetime($permit->starts_at) }} – {{ ph_datetime($permit->ends_at) }}
                            </td>
                            <td class="px-5 py-4 text-gray-700">{{ $permit->reason }}</td>
                            <td class="px-5 py-4">
                                <a href="{{ route('admin.overnight.proof', $permit->id) }}" class="text-sm font-semibold text-blue-600 hover:underline">Open proof</a>
                            </td>
                            <td class="px-5 py-4">
                                @if ($permit->status === 'pending')
                                    <form method="POST" action="{{ route('admin.overnight.decide', $permit->id) }}" class="flex flex-col gap-2">
                                        @csrf
                                        <input type="text" name="review_note" maxlength="500" placeholder="Note" class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs">
                                        <div class="flex gap-2">
                                            <button type="submit" name="decision" value="approve" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Approve</button>
                                            <button type="submit" name="decision" value="reject" class="rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-rose-700">Reject</button>
                                        </div>
                                    </form>
                                @else
                                    <span @class([
                                        'inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                        'bg-emerald-50 text-emerald-700' => $permit->status === 'approved',
                                        'bg-rose-50 text-rose-700' => $permit->status === 'rejected',
                                    ])>{{ ucfirst($permit->status) }}</span>
                                    @if (filled($permit->review_note))
                                        <p class="mt-1 text-xs text-gray-500">{{ $permit->review_note }}</p>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-sm text-gray-500">No overnight requests yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($permits->hasPages())
        <div class="mt-4">{{ $permits->links() }}</div>
    @endif
@endsection
