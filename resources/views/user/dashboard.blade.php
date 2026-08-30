@extends('layouts.user')

@section('title', 'User Dashboard')

@section('content')
    @php
        $vehicleType = $user->vehicleType->vehicle_name ?? null;
        $hasVehicle = filled($user->plate_number);
        $strikeRatio = $maxStrikes > 0 ? min(1, $strikeCount / $maxStrikes) : 0;
        $gateLabel = $user->isRemedialDeclined()
            ? (($user->remedial_gate_enabled && ! $user->remedialAccessExpired()) ? 'Temporary (Remedial)' : 'Not Approved')
            : ($gateAccess ?: 'Pending');
        $gateTone = match (strtolower(trim((string) $gateLabel))) {
            'granted', 'allow', 'allowed' => 'emerald',
            'denied', 'blocked', 'revoked', 'not approved' => 'rose',
            default => 'amber',
        };
    @endphp

    @include('partials.shell.page-header', [
        'title' => 'Dashboard',
        'subtitle' => 'Welcome back, '.$user->fullname,
    ])

    @if ($user->hasPendingResubmission())
        <div class="mb-6 rounded-2xl border border-blue-200 bg-blue-50 px-5 py-4 text-sm text-blue-950">
            <p class="font-semibold">Documents resubmitted — awaiting admin review</p>
            <p class="mt-1 text-blue-800">Gate access stays paused until your registration is approved.</p>
        </div>
    @elseif ($user->isRemedialDeclined())
        <div class="mb-6 rounded-2xl border border-orange-200 bg-orange-50 px-5 py-4 text-sm text-orange-950">
            <p class="font-semibold">Registration needs document correction</p>
            @if ($user->declineCategoryLabel())
                <p class="mt-1 text-orange-800">Issue: {{ $user->declineCategoryLabel() }}</p>
            @endif
            @if (filled($user->decline_remarks))
                <p class="mt-1 text-orange-800">{{ $user->decline_remarks }}</p>
            @endif
            @if ($user->remedial_expires_at && ! $user->remedialAccessExpired() && $user->remedial_gate_enabled)
                <p class="mt-2 text-xs text-orange-700">
                    Temporary gate access until {{ $user->remedial_expires_at->timezone(config('app.timezone'))->format('M j, Y g:i A') }}.
                    Full campus access is not approved yet.
                </p>
            @else
                <p class="mt-2 text-xs text-orange-700">Upload corrected documents and resubmit for admin review.</p>
            @endif
            <a href="{{ route('user.registration.fix') }}" class="mt-3 inline-flex items-center gap-2 rounded-lg bg-orange-700 px-3 py-2 text-xs font-semibold text-white hover:bg-orange-800">
                Fix Documents &amp; Resubmit
            </a>
        </div>
    @endif

    @if ($user->isTemporaryAccount())
        <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-950">
            <p class="font-semibold">Unregistered student/faculty — complete vehicle registration within {{ (int) config('services.rfid.temp_access_hours', 5) }} hours</p>
            <p class="mt-1 text-amber-800">
                @if ($user->temporary_expires_at)
                    Deadline: {{ $user->temporary_expires_at->timezone(config('app.timezone'))->format('M j, Y g:i A') }}.
                @endif
            </p>
            @if ($user->temp_conversion_token)
                <a href="{{ route('register', ['temp' => $user->temp_conversion_token]) }}" class="mt-3 inline-flex items-center gap-2 rounded-lg bg-amber-700 px-3 py-2 text-xs font-semibold text-white hover:bg-amber-800">
                    Complete Registration
                </a>
            @endif
        </div>
    @endif

    {{-- Account overview --}}
    <section class="mb-6 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 bg-gradient-to-r from-blue-50 via-white to-slate-50 px-5 py-5 sm:px-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4">
                    <x-portal.avatar :user="$user" size="xl" class="ring-2 ring-white shadow-sm" />
                    <div class="min-w-0">
                        <h2 class="truncate text-lg font-semibold text-gray-900 sm:text-xl">{{ $user->fullname }}</h2>
                        <p class="mt-0.5 text-sm text-gray-500">{{ $user->displayRoleLabel() }} · ID {{ $user->id_number ?: '—' }}</p>
                        <p class="mt-1 truncate text-sm text-gray-500">{{ $user->email }}</p>
                    </div>
                </div>
                @if ($user->isRemedialDeclined())
                    <a href="{{ route('user.registration.fix') }}" class="inline-flex items-center justify-center gap-2 rounded-lg border border-orange-200 bg-orange-50 px-3 py-2 text-sm font-semibold text-orange-900 hover:bg-orange-100">
                        <i data-lucide="file-warning" class="h-4 w-4"></i>
                        Fix Documents
                    </a>
                @else
                    <a href="{{ route('profile.edit') }}" class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                        <i data-lucide="settings" class="h-4 w-4"></i>
                        Account Settings
                    </a>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-1 gap-3 p-5 sm:grid-cols-2 lg:grid-cols-4 sm:p-6">
            <div class="rounded-xl border border-gray-100 bg-gray-50/80 p-4">
                <div class="mb-2 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <i data-lucide="car" class="h-3.5 w-3.5 text-blue-600"></i>
                    Vehicle
                </div>
                <p class="text-base font-semibold text-gray-900">{{ $hasVehicle ? $user->plate_number : 'Not registered' }}</p>
                <p class="mt-0.5 text-xs text-gray-500">{{ $vehicleType ?: 'Add a vehicle in Account Settings' }}</p>
            </div>

            <div class="rounded-xl border border-gray-100 bg-gray-50/80 p-4">
                <div class="mb-2 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <i data-lucide="shield-check" class="h-3.5 w-3.5 text-blue-600"></i>
                    Gate Access
                </div>
                <p @class([
                    'inline-flex rounded-full px-2.5 py-0.5 text-sm font-semibold',
                    'bg-emerald-100 text-emerald-800' => $gateTone === 'emerald',
                    'bg-rose-100 text-rose-800' => $gateTone === 'rose',
                    'bg-amber-100 text-amber-800' => $gateTone === 'amber',
                ])>{{ $gateLabel }}</p>
            </div>

            <div class="rounded-xl border border-gray-100 bg-gray-50/80 p-4">
                <div class="mb-2 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <i data-lucide="alert-triangle" class="h-3.5 w-3.5 text-orange-500"></i>
                    Violations
                </div>
                <p class="text-base font-semibold text-gray-900">{{ $strikeCount }} / {{ $maxStrikes }}</p>
                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-gray-200">
                    <div
                        class="h-full rounded-full {{ $strikeRatio >= 1 ? 'bg-rose-500' : ($strikeRatio >= 0.66 ? 'bg-orange-500' : 'bg-blue-500') }}"
                        style="width: {{ round($strikeRatio * 100) }}%"
                    ></div>
                </div>
            </div>

            <div class="rounded-xl border border-gray-100 bg-gray-50/80 p-4">
                <div class="mb-2 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <i data-lucide="parking-square" class="h-3.5 w-3.5 text-blue-600"></i>
                    Parking
                </div>
                <p class="text-base font-semibold text-gray-900">Campus lots</p>
                <p class="mt-0.5 text-xs text-gray-500">Follow posted rules below</p>
            </div>
        </div>
    </section>

    {{-- Stacked accordions: each panel sizes on its own so opening one never stretches the other --}}
    <div class="mb-6 flex flex-col gap-4" data-dashboard-accordions>
        <details class="dashboard-accordion group overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm transition-[box-shadow] duration-200 open:border-blue-200 open:shadow-md">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-4 select-none hover:bg-gray-50/80 sm:px-6 [&::-webkit-details-marker]:hidden">
                <div class="flex min-w-0 items-center gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-700">
                        <i data-lucide="info" class="h-5 w-5"></i>
                    </div>
                    <div class="min-w-0">
                        <h3 class="font-semibold text-gray-900">General Information</h3>
                        <p class="text-xs text-gray-500">Account and campus notices</p>
                    </div>
                </div>
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-500 transition-colors duration-200 group-open:bg-blue-50 group-open:text-blue-700">
                    <i data-lucide="chevron-down" class="h-4 w-4 transition-transform duration-200 group-open:rotate-180"></i>
                </span>
            </summary>
            <div class="max-h-72 space-y-3 overflow-y-auto border-t border-gray-100 p-5 sm:max-h-80 sm:p-6">
                @forelse ($generalInfo as $info)
                    <div class="flex gap-3 rounded-xl border border-emerald-100 bg-emerald-50/70 px-4 py-3 text-sm text-emerald-950">
                        <i data-lucide="circle-check" class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600"></i>
                        <span class="leading-relaxed">{{ $info->description }}</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No general information available.</p>
                @endforelse
            </div>
        </details>

        <details class="dashboard-accordion group overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm transition-[box-shadow] duration-200 open:border-blue-200 open:shadow-md">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-4 select-none hover:bg-gray-50/80 sm:px-6 [&::-webkit-details-marker]:hidden">
                <div class="flex min-w-0 items-center gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-700">
                        <i data-lucide="clipboard-list" class="h-5 w-5"></i>
                    </div>
                    <div class="min-w-0">
                        <h3 class="font-semibold text-gray-900">Official Parking Rules</h3>
                        <p class="text-xs text-gray-500">Campus policies you must follow</p>
                    </div>
                </div>
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-500 transition-colors duration-200 group-open:bg-blue-50 group-open:text-blue-700">
                    <i data-lucide="chevron-down" class="h-4 w-4 transition-transform duration-200 group-open:rotate-180"></i>
                </span>
            </summary>
            <div class="max-h-72 divide-y divide-gray-100 overflow-y-auto border-t border-gray-100 sm:max-h-80">
                @forelse ($parkingRules as $index => $rule)
                    <div class="flex gap-3 px-5 py-3.5 sm:px-6">
                        <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-blue-50 text-xs font-bold text-blue-700">{{ $index + 1 }}</span>
                        <p class="text-sm leading-relaxed text-gray-700">{{ $rule->description }}</p>
                    </div>
                @empty
                    <p class="px-5 py-8 text-sm text-gray-500 sm:px-6">No rules posted yet.</p>
                @endforelse
            </div>
        </details>
    </div>

    @push('scripts')
        <script>
            (() => {
                const root = document.querySelector('[data-dashboard-accordions]');
                if (!root) return;

                const panels = Array.from(root.querySelectorAll('details.dashboard-accordion'));
                panels.forEach((panel) => {
                    panel.addEventListener('toggle', () => {
                        if (!panel.open) return;
                        panels.forEach((other) => {
                            if (other !== panel && other.open) {
                                other.open = false;
                            }
                        });
                    });
                });
            })();
        </script>
    @endpush

    {{-- Recent Entry / Exit --}}
    <section class="mb-6 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 sm:px-6">
            <div>
                <h3 class="font-semibold text-gray-900">Recent Entry / Exit</h3>
                <p class="text-xs text-gray-500">Latest gate access activity</p>
            </div>
            <a href="{{ route('user.entry-exit') }}" class="text-sm font-semibold text-blue-700 hover:underline">View all</a>
        </div>

        @if ($recentGateLogs->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="min-w-[640px] w-full text-left text-sm">
                    <thead class="border-b border-gray-100 bg-gray-50/80 text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-5 py-3 sm:px-6">Direction</th>
                            <th class="px-5 py-3 sm:px-6">Gate</th>
                            <th class="px-5 py-3 sm:px-6">Status</th>
                            <th class="px-5 py-3 sm:px-6">Date</th>
                            <th class="px-5 py-3 sm:px-6">Time</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($recentGateLogs as $log)
                            @php
                                $granted = $log->accessGranted();
                                $isEntry = ($log->action ?? '') === 'Entry';
                            @endphp
                            <tr class="hover:bg-gray-50/70">
                                <td class="whitespace-nowrap px-5 py-3 sm:px-6">
                                    @if ($isEntry)
                                        <span class="inline-flex items-center gap-1.5 font-medium text-blue-700">
                                            <i data-lucide="log-in" class="h-4 w-4"></i> Entry
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 font-medium text-violet-700">
                                            <i data-lucide="log-out" class="h-4 w-4"></i> {{ $log->action ?: 'Exit' }}
                                        </span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 text-gray-700 sm:px-6">{{ $log->displayGate() }}</td>
                                <td class="px-5 py-3 sm:px-6">
                                    @if ($granted)
                                        <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">Granted</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-semibold text-rose-800">Denied</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 text-gray-700 sm:px-6">{{ ph_date($log->timestamp) }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-gray-700 sm:px-6">{{ ph_time($log->timestamp) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="px-5 py-10 text-center text-sm text-gray-500 sm:px-6">No recent entry or exit records yet.</p>
        @endif
    </section>

    @if (($recentViolations ?? collect())->isNotEmpty())
        <section class="mb-6 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 sm:px-6">
                <div>
                    <h3 class="font-semibold text-gray-900">Recent Violations</h3>
                    <p class="text-xs text-gray-500">Latest recorded parking / access issues</p>
                </div>
                <a href="{{ route('user.violations') }}" class="text-sm font-semibold text-blue-700 hover:underline">View all</a>
            </div>
            <div class="divide-y divide-gray-100">
                @foreach ($recentViolations as $violation)
                    <div class="px-5 py-4 sm:px-6">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-semibold text-gray-900">{{ $violation->violation_type }}</p>
                                <p class="mt-1 text-sm text-gray-600">{{ $violation->description ?: 'No description provided.' }}</p>
                                <p class="mt-1 text-xs text-gray-400">{{ ph_datetime($violation->created_at) }}</p>
                            </div>
                            <x-violation.evidence-panel
                                :log="$violation"
                                route-name="user.violations.evidence"
                                compact
                            />
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <div class="rounded-2xl border border-blue-100 bg-blue-50/80 p-5 sm:p-6">
        <h4 class="mb-2 flex items-center gap-2 font-semibold text-blue-950">
            <i data-lucide="megaphone" class="h-4 w-4"></i>
            Important Notice
        </h4>
        <p class="text-sm leading-relaxed text-blue-900/90">
        Failure to comply with campus parking policies may result in appropriate disciplinary action or loss of parking privileges.
        Always park only in designated parking areas, observe the 15 kph speed limit, and follow all campus parking and traffic regulations.
        </p>
    </div>
@endsection
