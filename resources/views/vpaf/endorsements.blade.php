@extends('layouts.vpaf')

@section('title', 'Endorsements')

@section('content')
    @include('partials.shell.page-header', [
        'title' => 'Pending endorsements',
        'subtitle' => 'Approve or reject a GSU endorsement. Rejection keeps the citation and does not apply the sanction.',
    ])

    <div class="space-y-4">
        @forelse ($pending as $endorsement)
            <article class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">{{ $endorsement->user?->fullname ?? 'Unknown' }}</h2>
                        <p class="mt-1 text-sm text-gray-600">{{ $endorsement->sanctionLabel() }} · {{ $endorsement->plate_number }}</p>
                        <p class="mt-2 text-sm text-gray-700">{{ $endorsement->gsu_remarks ?: 'GSU endorsed this third offense for your decision.' }}</p>
                    </div>
                    <span class="rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700">Waiting for VPAF</span>
                </div>
                <form method="POST" action="{{ route('vpaf.endorsements.decide', $endorsement->id) }}" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                    @csrf
                    <div class="min-w-0 flex-1">
                        <label class="mb-1 block text-xs font-medium text-gray-500">Note</label>
                        <input type="text" name="note" maxlength="500" placeholder="Optional note"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                    </div>
                    <button type="submit" name="decision" value="approve" class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Approve</button>
                    <button type="submit" name="decision" value="reject" class="inline-flex items-center justify-center rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700">Reject</button>
                </form>
            </article>
        @empty
            <div class="rounded-xl border border-gray-200 bg-white px-6 py-12 text-center text-sm text-gray-500">
                Nothing is waiting for your decision.
            </div>
        @endforelse
    </div>

    @if ($recent->isNotEmpty())
        <h2 class="mb-3 mt-8 text-sm font-semibold text-gray-900">Recent decisions</h2>
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-gray-500">
                    <tr>
                        <th class="px-5 py-3 font-medium">Person</th>
                        <th class="px-5 py-3 font-medium">Sanction</th>
                        <th class="px-5 py-3 font-medium">Decision</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($recent as $endorsement)
                        <tr>
                            <td class="px-5 py-3">{{ $endorsement->user?->fullname ?? 'Unknown' }}</td>
                            <td class="px-5 py-3">{{ $endorsement->sanctionLabel() }}</td>
                            <td class="px-5 py-3">{{ $endorsement->statusLabel() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
