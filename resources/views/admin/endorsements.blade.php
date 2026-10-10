@extends('layouts.admin')

@section('title', 'Offense Endorsements')

@section('content')
<div class="min-w-0 max-w-full">
    @include('partials.shell.page-header', [
        'title' => 'Offense Endorsements',
        'subtitle' => '2nd offenses go to the GSU. 3rd offenses are verified by the GSU and endorsed to the VPAF.',
    ])

    @if (session('success'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif
    @if (! $endorsementsEnabled)
        <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            New endorsements are turned off. Turn on "Endorse 2nd and 3rd Offenses to the GSU" in Settings to create them again.
        </div>
    @endif

    <div class="mb-5 rounded-xl border border-gray-200 bg-white p-4 text-sm text-gray-600 shadow-sm">
        <p class="font-semibold text-gray-900">How the offense ladder works</p>
        <ul class="mt-2 space-y-1">
            <li><strong>1st offense:</strong> warning ticket only. No endorsement is created.</li>
            <li><strong>2nd offense:</strong> Security endorses it to the GSU. If the GSU approves, the parking permit is suspended for six months.</li>
            <li><strong>3rd offense:</strong> the GSU verifies it and endorses it to the VPAF. When the VPAF approves, record it here and parking privileges are revoked.</li>
        </ul>
        <p class="mt-2 text-xs text-gray-500">A suspension or revocation blocks the vehicle at the entry gate. The owner can still sign in and exit campus.</p>
    </div>

    @php
        $tabs = [
            'gsu' => ['label' => 'Awaiting GSU', 'icon' => 'clipboard-check'],
            'vpaf' => ['label' => 'Awaiting VPAF', 'icon' => 'scale'],
            'decided' => ['label' => 'Decided', 'icon' => 'archive'],
        ];
    @endphp
    <div class="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
        @foreach ($tabs as $key => $meta)
            <a href="{{ route('admin.endorsements', ['tab' => $key]) }}"
               @class([
                   'flex items-center justify-between rounded-xl border bg-white p-4 shadow-sm transition hover:border-gray-300',
                   'border-blue-300 ring-2 ring-blue-100' => $tab === $key,
                   'border-gray-200' => $tab !== $key,
               ])>
                <div>
                    <p class="text-sm text-gray-500">{{ $meta['label'] }}</p>
                    <p class="mt-1 text-2xl font-bold tracking-tight text-gray-900">{{ $counts[$key] }}</p>
                </div>
                <i data-lucide="{{ $meta['icon'] }}" class="h-6 w-6 text-gray-400"></i>
            </a>
        @endforeach
    </div>

    <div class="space-y-4">
        @forelse ($endorsements as $endorsement)
            @php
                $owner = $endorsement->user;
                $ownerCitations = $citations->get((int) $endorsement->user_id, collect());
                $isThird = (int) $endorsement->offense_level >= 3;
                $statusTone = match ($endorsement->status) {
                    \App\Models\SanctionEndorsement::STATUS_APPROVED => 'bg-red-50 text-red-700 border-red-200',
                    \App\Models\SanctionEndorsement::STATUS_REJECTED, \App\Models\SanctionEndorsement::STATUS_WITHDRAWN => 'bg-gray-50 text-gray-700 border-gray-200',
                    default => 'bg-orange-50 text-orange-700 border-orange-200',
                };
            @endphp
            <article class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-base font-semibold text-gray-900">{{ $owner?->displayName() ?? 'Unknown owner' }}</h3>
                            <span class="rounded-md px-2 py-0.5 text-xs font-semibold {{ $isThird ? 'bg-red-100 text-red-700' : 'bg-orange-100 text-orange-700' }}">{{ $endorsement->offenseLabel() }}</span>
                            <span class="rounded-full border px-2 py-0.5 text-xs font-medium {{ $statusTone }}">{{ $endorsement->statusLabel() }}</span>
                        </div>
                        <p class="mt-1 text-sm text-gray-600">Proposed sanction: {{ $endorsement->sanctionLabel() }}</p>
                        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500">
                            <span class="inline-flex items-center gap-1.5"><i data-lucide="car" class="h-3.5 w-3.5"></i><code class="text-gray-700">{{ $endorsement->plate_number ?: '—' }}</code></span>
                            <span class="inline-flex items-center gap-1.5"><i data-lucide="calendar" class="h-3.5 w-3.5"></i>Endorsed {{ ph_datetime($endorsement->created_at, 'M j, Y g:i A') }}</span>
                            <span class="inline-flex items-center gap-1.5"><i data-lucide="user" class="h-3.5 w-3.5"></i>By {{ str_starts_with((string) $endorsement->endorsed_by, 'AI-') ? 'AI camera' : (filled($endorsement->endorsed_by) ? 'Security' : 'System') }}</span>
                            @if ($owner)
                                <span class="inline-flex items-center gap-1.5"><i data-lucide="alert-triangle" class="h-3.5 w-3.5"></i>{{ (int) ($owner->strike_count ?? 0) }} citation(s) on record</span>
                            @endif
                        </div>
                    </div>
                    @if ($owner)
                        <a href="{{ route('admin.violations', ['q' => $owner->displayName()]) }}" class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                            <i data-lucide="eye" class="h-4 w-4"></i>
                            View citations
                        </a>
                    @endif
                </div>

                @if ($ownerCitations->isNotEmpty())
                    <ol class="mt-3 space-y-1 rounded-lg bg-gray-50 px-4 py-3 text-xs text-gray-600">
                        @foreach ($ownerCitations as $i => $citation)
                            <li><strong>{{ $i + 1 }}.</strong> {{ $citation->violation_type }} — {{ ph_datetime($citation->created_at, 'M j, Y g:i A') }}</li>
                        @endforeach
                    </ol>
                @endif

                @if ($endorsement->gsu_reviewed_at || $endorsement->vpaf_reviewed_at)
                    <div class="mt-3 space-y-1 text-xs text-gray-600">
                        @if ($endorsement->gsu_reviewed_at)
                            <p>GSU review: {{ $reviewers[(int) $endorsement->gsu_reviewed_by] ?? 'Admin' }}, {{ ph_datetime($endorsement->gsu_reviewed_at, 'M j, Y g:i A') }}@if ($endorsement->gsu_remarks) — “{{ $endorsement->gsu_remarks }}”@endif</p>
                        @endif
                        @if ($endorsement->vpaf_reviewed_at)
                            <p>VPAF decision recorded by {{ $reviewers[(int) $endorsement->vpaf_reviewed_by] ?? 'Admin' }}, {{ ph_datetime($endorsement->vpaf_reviewed_at, 'M j, Y g:i A') }}@if ($endorsement->vpaf_remarks) — “{{ $endorsement->vpaf_remarks }}”@endif</p>
                        @endif
                        @if ($endorsement->effective_until)
                            <p class="font-medium text-red-700">Suspended until {{ ph_date($endorsement->effective_until, 'M j, Y') }}</p>
                        @endif
                    </div>
                @endif

                @if ($endorsement->isOpen())
                    @php
                        $vpafStage = $endorsement->status === \App\Models\SanctionEndorsement::STATUS_PENDING_VPAF;
                        $approveValue = $vpafStage ? 'vpaf_approve' : 'approve';
                        $rejectValue = $vpafStage ? 'vpaf_reject' : 'reject';
                        $approveLabel = $vpafStage ? 'VPAF approved: revoke privileges' : ($isThird ? 'Verify and endorse to VPAF' : 'Approve 6-month suspension');
                        $rejectLabel = $vpafStage ? 'VPAF did not approve' : ($isThird ? 'Do not endorse' : 'Do not approve');
                    @endphp
                    <form method="POST" action="{{ route('admin.endorsements.decide', $endorsement->id) }}" class="mt-4 border-t border-gray-100 pt-4">
                        @csrf
                        <label class="block text-xs font-medium text-gray-700" for="remarks-{{ $endorsement->id }}">Remarks (optional)</label>
                        <input id="remarks-{{ $endorsement->id }}" type="text" name="remarks" maxlength="500"
                               placeholder="{{ $vpafStage ? 'e.g. VPAF memo reference' : 'Reason for the decision' }}"
                               class="mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm shadow-sm">
                        <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                            <button type="submit" name="decision" value="{{ $approveValue }}"
                                    onclick="return confirm(@js($approveLabel.'?'))"
                                    class="inline-flex items-center justify-center gap-1.5 rounded-lg px-4 py-2 text-sm font-semibold text-white"
                                    style="background-color: {{ $isThird && $vpafStage ? '#dc2626' : '#2563eb' }};">
                                <i data-lucide="check" class="h-4 w-4"></i>
                                {{ $approveLabel }}
                            </button>
                            <button type="submit" name="decision" value="{{ $rejectValue }}"
                                    onclick="return confirm(@js($rejectLabel.'?'))"
                                    class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                                <i data-lucide="x" class="h-4 w-4"></i>
                                {{ $rejectLabel }}
                            </button>
                        </div>
                    </form>
                @endif
            </article>
        @empty
            <div class="rounded-xl border border-dashed border-gray-300 bg-white px-6 py-16 text-center text-sm text-gray-500">
                {{ $tab === 'decided' ? 'No decided endorsements yet.' : 'Nothing is waiting for review.' }}
            </div>
        @endforelse

        @if ($endorsements->hasPages())
            <div class="rounded-xl border border-gray-200 bg-white px-4 py-3">{{ $endorsements->links() }}</div>
        @endif
    </div>
</div>
@endsection
