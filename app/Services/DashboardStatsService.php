<?php

namespace App\Services;

use App\Models\GateLog;
use App\Models\ParkingArea;
use App\Models\ParkingSlot;
use App\Models\User;
use App\Models\Visitor;
use App\Models\VisitorRfidCard;
use App\Models\ViolationLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DashboardStatsService
{
    public function adminStats(): array
    {
        $today = Carbon::today();
        $weekStart = Carbon::today()->startOfWeek(Carbon::MONDAY);

        $nonAdmin = fn () => User::query()->where('user_role_id', '!=', NavigationService::ROLE_ADMIN);

        $totalUsers = $nonAdmin()->count();
        $activeUsers = $nonAdmin()
            ->where('status', User::STATUS_GRANTED)
            ->where('strike_count', '<', User::MAX_STRIKES)
            ->count();
        $suspendedUsers = $nonAdmin()
            ->where(function ($q) {
                $q->where('status', User::STATUS_LOCKED)
                    ->orWhere('status', 'Suspended')
                    ->orWhere('strike_count', '>=', User::MAX_STRIKES);
            })
            ->count();

        $todayEntries = GateLog::query()
            ->where('action', 'Entry')
            ->where('log_date', '>=', $today)
            ->where('log_date', '<', $today->copy()->addDay())
            ->count();
        $todayExits = GateLog::query()
            ->where('action', 'Exit')
            ->where('log_date', '>=', $today)
            ->where('log_date', '<', $today->copy()->addDay())
            ->count();

        $slots = ParkingSlot::query()->get(['status']);
        $totalSlots = $slots->count();
        $occupiedSlots = $slots->where('status', 'Occupied')->count();
        $availableSlots = max(0, $totalSlots - $occupiedSlots);
        $parkingAvailablePercent = $totalSlots > 0
            ? (int) round(($availableSlots / $totalSlots) * 100)
            : 0;

        $entriesByDay = $this->gateCountsByDay('Entry', $weekStart);
        $exitsByDay = $this->gateCountsByDay('Exit', $weekStart);
        $weeklyTrends = [
            'labels' => $entriesByDay->pluck('label')->values()->all(),
            'entries' => $entriesByDay->pluck('count')->values()->all(),
            'exits' => $exitsByDay->pluck('count')->values()->all(),
            'values' => $entriesByDay->values()->map(function (array $day, int $index) use ($exitsByDay) {
                return (int) $day['count'] + (int) ($exitsByDay[$index]['count'] ?? 0);
            })->all(),
        ];

        return [
            'pending' => User::query()->where('status', User::STATUS_PENDING)->count(),
            'total' => $totalUsers,
            'totalUsers' => $totalUsers,
            'activeUsers' => $activeUsers,
            'suspendedUsers' => $suspendedUsers,
            'todayEntries' => $todayEntries,
            'todayExits' => $todayExits,
            'todayActivity' => $todayEntries + $todayExits,
            'activeViolations' => ViolationLog::query()->where('status', 'Active')->count(),
            'occupiedSlots' => $occupiedSlots,
            'totalSlots' => $totalSlots,
            'parkingAvailablePercent' => $parkingAvailablePercent,
            'weeklyTrends' => $weeklyTrends,
            'violationTypeDistribution' => $this->violationTypeDistribution(),
            'usersWithSecondStrike' => User::query()
                ->with('role')
                ->where('strike_count', 2)
                ->whereIn('user_role_id', [NavigationService::ROLE_STUDENT, NavigationService::ROLE_STAFF])
                ->orderBy('name')
                ->get(['id', 'name', 'id_number', 'user_role_id', 'strike_count']),
            'recentViolations' => ViolationLog::query()
                ->orderByDesc('created_at')
                ->limit(2)
                ->get(),
            'recentViolationLogs' => ViolationLog::query()
                ->orderByDesc('created_at')
                ->limit(2)
                ->get(),
            ...$this->safeVisitorDashboardStats(),
        ];
    }

    /**
     * @return array{labels: list<string>, values: list<int>, percents: list<int>, colors: list<string>}
     */
    private function violationTypeDistribution(): array
    {
        $palette = ['#93C5FD', '#BFDBFE', '#6EE7B7', '#5D9FD1', '#F87171'];
        $counts = ViolationLog::query()
            ->get(['violation_type'])
            ->groupBy(fn ($row) => trim((string) ($row->violation_type ?: 'Other')) ?: 'Other')
            ->map->count()
            ->sortDesc();

        $total = max(1, (int) $counts->sum());
        $top = $counts->take(4);
        $otherCount = (int) $counts->slice(4)->sum();

        $labels = $top->keys()->values()->all();
        $values = $top->values()->map(fn ($n) => (int) $n)->all();

        if ($otherCount > 0 || $labels === []) {
            $labels[] = 'Other';
            $values[] = $otherCount > 0 ? $otherCount : 0;
        }

        if (array_sum($values) === 0) {
            return [
                'labels' => ['No License Plate', 'Unauthorized Parking', 'Overstay', 'Other'],
                'values' => [0, 0, 0, 0],
                'percents' => [0, 0, 0, 0],
                'colors' => ['#93C5FD', '#BFDBFE', '#6EE7B7', '#5D9FD1'],
                'total' => 0,
            ];
        }

        $percents = array_map(
            fn (int $value) => (int) round(($value / $total) * 100),
            $values
        );

        return [
            'labels' => $labels,
            'values' => $values,
            'percents' => $percents,
            'colors' => array_slice($palette, 0, count($labels)),
            'total' => (int) $counts->sum(),
        ];
    }

    public function guardStats(): array
    {
        $today = Carbon::today();
        $slots = ParkingSlot::query()->get(['status']);

        $visitorStats = $this->safeVisitorDashboardStats();

        return [
            'pending' => User::query()->where('status', User::STATUS_PENDING)->count(),
            'totalUsers' => User::query()
                ->where('user_role_id', '!=', NavigationService::ROLE_GUARD)
                ->where('user_role_id', '!=', NavigationService::ROLE_ADMIN)
                ->count(),
            'vehiclesInside' => $slots->where('status', 'Occupied')->count(),
            'todayEntries' => GateLog::query()
                ->where('action', 'Entry')
                ->where('log_date', '>=', $today)
                ->where('log_date', '<', $today->copy()->addDay())
                ->count(),
            'activeViolations' => ViolationLog::query()->where('status', 'Active')->count(),
            'availableSlots' => $slots->where('status', 'Available')->count(),
            'totalSlots' => $slots->count(),
            'recentUsers' => User::query()
                ->with('role')
                ->whereIn('user_role_id', [
                    NavigationService::ROLE_STUDENT,
                    NavigationService::ROLE_STAFF,
                ])
                ->orderByDesc('id')
                ->limit(5)
                ->get(),
            'recentGateActivity' => GateLog::query()
                ->with(['user', 'visitor'])
                ->orderByDesc('timestamp')
                ->limit(5)
                ->get(),
            'activeVisitors' => $visitorStats['activeVisitors'],
            'waitingVisitors' => $visitorStats['waitingVisitors'],
            'rfidReturnsPending' => $visitorStats['rfidReturnsPending'],
            'expiredVisitors' => $visitorStats['expiredVisitors'],
            'recentViolationLogs' => ViolationLog::query()
                ->with('user')
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(),
        ];
    }

    /**
     * Visitor collections may be empty or momentarily unreachable (Atlas timeouts).
     *
     * @return array{activeVisitors: int, waitingVisitors: int, rfidReturnsPending: int, expiredVisitors: int, visitorsToday: int, completedVisits: int}
     */
    private function safeVisitorDashboardStats(): array
    {
        $defaults = [
            'activeVisitors' => 0,
            'waitingVisitors' => 0,
            'rfidReturnsPending' => 0,
            'expiredVisitors' => 0,
            'visitorsToday' => 0,
            'completedVisits' => 0,
        ];

        try {
            return [
                'activeVisitors' => Visitor::query()
                    ->whereIn('status', Visitor::ACTIVE_STATUSES)
                    ->count(),
                'waitingVisitors' => Visitor::query()
                    ->where('status', Visitor::STATUS_WAITING)
                    ->count(),
                'rfidReturnsPending' => VisitorRfidCard::query()
                    ->whereIn('status', [
                        VisitorRfidCard::STATUS_ASSIGNED,
                        VisitorRfidCard::STATUS_ACTIVE,
                        VisitorRfidCard::STATUS_EXPIRED,
                    ])
                    ->whereNotNull('visitor_id')
                    ->count(),
                'expiredVisitors' => Visitor::query()
                    ->where('status', Visitor::STATUS_EXPIRED)
                    ->count(),
                'visitorsToday' => Visitor::query()
                    ->where('created_at', '>=', Carbon::today())
                    ->count(),
                'completedVisits' => Visitor::query()
                    ->where('status', Visitor::STATUS_COMPLETED)
                    ->count(),
            ];
        } catch (\Throwable $e) {
            report($e);

            return $defaults;
        }
    }

    public function userStats(User $user): array
    {
        $user->loadMissing('role');

        return [
            'strikeCount' => (int) ($user->strike_count ?? 0),
            'maxStrikes' => User::MAX_STRIKES,
            'gateAccess' => $user->Gate_access ?? 'Pending',
            'recentGateLogs' => GateLog::query()
                ->where('user_id', $user->id)
                ->orderByDesc('timestamp')
                ->limit(5)
                ->get(),
            'recentViolations' => ViolationLog::query()
                ->where('user_id', $user->id)
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(),
        ];
    }

    /**
     * @param  array{from?: Carbon|null, to?: Carbon|null}  $range
     */
    public function reportSummary(array $range = []): array
    {
        $today = Carbon::today();
        $weekStart = Carbon::today()->subDays(6);
        [$from, $to] = $this->resolveReportRange($range);

        $entriesByDay = $this->gateCountsByDay('Entry', $weekStart);
        $exitsByDay = $this->gateCountsByDay('Exit', $weekStart);

        $totalUsers = User::query()->where('user_role_id', '!=', NavigationService::ROLE_ADMIN)->count();
        $totalViolations = ViolationLog::query()->count();
        $todayAccessLogs = GateLog::query()
            ->where('log_date', '>=', $today)
            ->where('log_date', '<', $today->copy()->addDay())
            ->count();

        $slots = ParkingSlot::query()->get(['status']);
        $totalSlots = max(1, $slots->count());
        $occupiedSlots = $slots->where('status', 'Occupied')->count();
        $parkingUtilization = (int) round(($occupiedSlots / $totalSlots) * 100);

        $usersThisMonth = User::query()
            ->where('user_role_id', '!=', NavigationService::ROLE_ADMIN)
            ->where('created_at', '>=', $today->copy()->startOfMonth())
            ->count();
        $usersLastMonth = User::query()
            ->where('user_role_id', '!=', NavigationService::ROLE_ADMIN)
            ->where('created_at', '>=', $today->copy()->subMonthNoOverflow()->startOfMonth())
            ->where('created_at', '<', $today->copy()->startOfMonth())
            ->count();

        $violationsThisMonth = ViolationLog::query()
            ->where('created_at', '>=', $today->copy()->startOfMonth())
            ->count();
        $violationsLastMonth = ViolationLog::query()
            ->where('created_at', '>=', $today->copy()->subMonthNoOverflow()->startOfMonth())
            ->where('created_at', '<', $today->copy()->startOfMonth())
            ->count();

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'totalUsers' => $totalUsers,
            'grantedUsers' => User::query()->where('status', User::STATUS_GRANTED)->count(),
            'pendingUsers' => User::query()->where('status', User::STATUS_PENDING)->count(),
            'lockedUsers' => User::query()->where('status', User::STATUS_LOCKED)->count(),
            'totalViolations' => $totalViolations,
            'activeViolations' => ViolationLog::query()->where('status', 'Active')->count(),
            'todayEntries' => GateLog::query()
                ->where('action', 'Entry')
                ->where('log_date', '>=', $today)
                ->where('log_date', '<', $today->copy()->addDay())
                ->count(),
            'todayExits' => GateLog::query()
                ->where('action', 'Exit')
                ->where('log_date', '>=', $today)
                ->where('log_date', '<', $today->copy()->addDay())
                ->count(),
            'todayAccessLogs' => $todayAccessLogs,
            'parkingUtilization' => $parkingUtilization,
            'occupiedSlots' => $occupiedSlots,
            'totalSlots' => $slots->count(),
            'entriesByDay' => $entriesByDay,
            'exitsByDay' => $exitsByDay,
            'violationsByType' => ViolationLog::query()
                ->get(['violation_type'])
                ->groupBy(fn ($row) => $row->violation_type ?: 'Other')
                ->map->count()
                ->sortDesc(),
            'summaryTrends' => [
                'users' => $this->percentChange($usersThisMonth, $usersLastMonth),
                'violations' => $this->percentChange($violationsThisMonth, $violationsLastMonth),
                'access' => [
                    'label' => "Today's activity",
                    'direction' => null,
                    'value' => null,
                ],
                'parking' => [
                    'label' => $occupiedSlots.' of '.$slots->count().' slots occupied',
                    'direction' => $parkingUtilization >= 70 ? 'up' : ($parkingUtilization <= 30 ? 'down' : 'flat'),
                    'value' => $parkingUtilization,
                ],
            ],
            'monthlyAccessTrends' => $this->monthlyAccessTrends(6),
            'userDistribution' => $this->userDistribution(),
            'parkingDailyPattern' => $this->parkingDailyPattern($today, $totalSlots),
            'violationsByLocation' => $this->violationsByLocation(),
            'violationTrendsByType' => $this->violationTrendsByType(6),
            'peakEntryExitHours' => $this->peakEntryExitHours($from, $to),
            'repeatOffenders' => $this->repeatOffenders(10),
        ];
    }

    /**
     * @param  array{from?: Carbon|null, to?: Carbon|null}  $range
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveReportRange(array $range): array
    {
        $to = ($range['to'] ?? null) instanceof Carbon
            ? $range['to']->copy()->endOfDay()
            : Carbon::today()->endOfDay();
        $from = ($range['from'] ?? null) instanceof Carbon
            ? $range['from']->copy()->startOfDay()
            : $to->copy()->subDays(29)->startOfDay();

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to];
    }

    /**
     * @return array{label: string, direction: 'up'|'down'|'flat'|null, value: int|null}
     */
    private function percentChange(int $current, int $previous, string $suffix = 'from last month'): array
    {
        if ($previous === 0 && $current === 0) {
            return ['label' => 'No change '.$suffix, 'direction' => 'flat', 'value' => 0];
        }

        if ($previous === 0) {
            return ['label' => 'New activity '.$suffix, 'direction' => 'up', 'value' => 100];
        }

        $pct = (int) round((($current - $previous) / $previous) * 100);
        $direction = $pct > 0 ? 'up' : ($pct < 0 ? 'down' : 'flat');

        return [
            'label' => sprintf('%d%% %s', abs($pct), $suffix),
            'direction' => $direction,
            'value' => $pct,
        ];
    }

    /**
     * @return array{labels: list<string>, entries: list<int>, exits: list<int>}
     */
    private function monthlyAccessTrends(int $months): array
    {
        $labels = [];
        $entries = [];
        $exits = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $start = Carbon::today()->subMonthsNoOverflow($i)->startOfMonth();
            $end = $start->copy()->endOfMonth();
            $labels[] = $start->format('M');
            $entries[] = GateLog::query()
                ->where('action', 'Entry')
                ->where('timestamp', '>=', $start)
                ->where('timestamp', '<=', $end)
                ->count();
            $exits[] = GateLog::query()
                ->where('action', 'Exit')
                ->where('timestamp', '>=', $start)
                ->where('timestamp', '<=', $end)
                ->count();
        }

        return compact('labels', 'entries', 'exits');
    }

    /**
     * @return array{labels: list<string>, values: list<int>, colors: list<string>}
     */
    private function userDistribution(): array
    {
        $student = User::query()->where('user_role_id', NavigationService::ROLE_STUDENT)->count();
        $staff = User::query()->where('user_role_id', NavigationService::ROLE_STAFF)->count();
        $visitor = User::query()->where('user_role_id', NavigationService::ROLE_VISITOR)->count();

        return [
            'labels' => ['Students', 'Staff', 'Visitors'],
            'values' => [$student, $staff, $visitor],
            'colors' => ['#93C5FD', '#BFDBFE', '#6EE7B7'],
        ];
    }

    /**
     * Estimated on-campus vehicle count by hour from gate Entry/Exit logs.
     *
     * @return array{labels: list<string>, values: list<int>, capacity: int}
     */
    private function parkingDailyPattern(Carbon $day, int $capacity): array
    {
        $startHour = 6;
        $endHour = 20;

        $logs = GateLog::query()
            ->where('timestamp', '>=', $day->copy()->startOfDay())
            ->where('timestamp', '<', $day->copy()->addDay())
            ->orderBy('timestamp')
            ->get(['action', 'timestamp']);

        $running = 0;
        $snapshot = [];

        foreach ($logs as $log) {
            $ts = $log->timestamp ? Carbon::parse($log->timestamp) : null;
            if (! $ts) {
                continue;
            }

            if (strcasecmp((string) $log->action, 'Entry') === 0) {
                $running++;
            } elseif (strcasecmp((string) $log->action, 'Exit') === 0) {
                $running = max(0, $running - 1);
            }

            $snapshot[(int) $ts->format('G')] = min($capacity, max(0, $running));
        }

        $labels = [];
        $values = [];
        $last = 0;

        for ($h = $startHour; $h <= $endHour; $h++) {
            if (array_key_exists($h, $snapshot)) {
                $last = $snapshot[$h];
            }
            $labels[] = Carbon::createFromTime($h)->format('g A');
            $values[] = $last;
        }

        if ($logs->isEmpty()) {
            $occupied = ParkingSlot::query()->where('status', 'Occupied')->count();
            $values = array_fill(0, count($labels), min($capacity, $occupied));
        }

        return [
            'labels' => $labels,
            'values' => $values,
            'capacity' => $capacity,
        ];
    }

    /**
     * @return array{labels: list<string>, values: list<int>}
     */
    private function violationsByLocation(): array
    {
        $areas = ParkingArea::query()->orderBy('area_name')->get(['area_name']);
        $areaNames = $areas->pluck('area_name')->filter()->values();

        $buckets = [];
        foreach (ViolationLog::query()->get(['description', 'violation_type']) as $row) {
            $text = strtolower(trim(($row->description ?? '').' '.($row->violation_type ?? '')));
            $matched = 'Campus';

            foreach ($areaNames as $name) {
                $needle = strtolower((string) $name);
                if ($needle !== '' && str_contains($text, $needle)) {
                    $matched = (string) $name;
                    break;
                }
            }

            if ($matched === 'Campus') {
                if (str_contains($text, 'gym')) {
                    $matched = $areaNames->first(fn ($n) => str_contains(strtolower((string) $n), 'gym')) ?: 'Campus';
                } elseif (str_contains($text, 'gate 2') || str_contains($text, 'gate-out')) {
                    $matched = 'Gate 2';
                } elseif (str_contains($text, 'gate 1') || str_contains($text, 'gate-in') || str_contains($text, 'gate')) {
                    $matched = 'Gate 1';
                } elseif (str_contains($text, 'park')) {
                    $matched = 'Parking Areas';
                }
            }

            $buckets[$matched] = ($buckets[$matched] ?? 0) + 1;
        }

        $gateDenied = GateLog::query()
            ->whereNotIn('result', [RfidAccessService::STATUS_GRANTED, 'Granted', 'granted'])
            ->get(['gate_id']);

        foreach ($gateDenied as $log) {
            $gate = strtoupper((string) ($log->gate_id ?? ''));
            $label = str_contains($gate, 'OUT') ? 'Gate 2' : (str_contains($gate, 'IN') ? 'Gate 1' : ($gate !== '' ? $gate : 'Gate'));
            $buckets[$label] = ($buckets[$label] ?? 0) + 1;
        }

        arsort($buckets);
        $buckets = array_slice($buckets, 0, 6, true);

        return [
            'labels' => array_keys($buckets),
            'values' => array_values($buckets),
        ];
    }

    /**
     * @return array{labels: list<string>, series: list<array{label: string, color: string, data: list<int>}>}
     */
    private function violationTrendsByType(int $months): array
    {
        $palette = ['#5D9FD1', '#F87171', '#6EE7B7', '#93C5FD', '#BFDBFE'];
        $all = ViolationLog::query()->get(['violation_type', 'created_at']);
        $topTypes = $all
            ->groupBy(fn ($row) => $row->violation_type ?: 'Other')
            ->map->count()
            ->sortDesc()
            ->keys()
            ->take(3)
            ->values();

        if ($topTypes->isEmpty()) {
            $topTypes = collect(['No Data']);
        }

        $labels = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $labels[] = Carbon::today()->subMonthsNoOverflow($i)->format('M');
        }

        $series = [];
        foreach ($topTypes as $index => $type) {
            $data = [];
            for ($i = $months - 1; $i >= 0; $i--) {
                $start = Carbon::today()->subMonthsNoOverflow($i)->startOfMonth();
                $end = $start->copy()->endOfMonth();
                $data[] = $all
                    ->filter(function ($row) use ($type, $start, $end) {
                        $created = $row->created_at ? Carbon::parse($row->created_at) : null;
                        $name = $row->violation_type ?: 'Other';

                        return $name === $type && $created && $created->between($start, $end);
                    })
                    ->count();
            }
            $series[] = [
                'label' => (string) $type,
                'color' => $palette[$index % count($palette)],
                'data' => $data,
            ];
        }

        return compact('labels', 'series');
    }

    /**
     * @return array{labels: list<string>, values: list<int>}
     */
    private function peakEntryExitHours(Carbon $from, Carbon $to): array
    {
        $hours = range(6, 17);
        $counts = array_fill_keys($hours, 0);

        $logs = GateLog::query()
            ->where('timestamp', '>=', $from)
            ->where('timestamp', '<=', $to)
            ->get(['timestamp']);

        foreach ($logs as $log) {
            $ts = $log->timestamp ? Carbon::parse($log->timestamp) : null;
            if (! $ts) {
                continue;
            }
            $h = (int) $ts->format('G');
            if (array_key_exists($h, $counts)) {
                $counts[$h]++;
            }
        }

        $labels = [];
        $values = [];
        foreach ($hours as $h) {
            $next = $h + 1;
            $labels[] = Carbon::createFromTime($h)->format('g').'-'.Carbon::createFromTime($next)->format('g A');
            $values[] = $counts[$h];
        }

        return compact('labels', 'values');
    }

    /**
     * @return Collection<int, array{rank: int, name: string, id_number: string, user_type: string, violations: int}>
     */
    private function repeatOffenders(int $limit): Collection
    {
        $counts = ViolationLog::query()
            ->get(['user_id', 'violator_name', 'id_number', 'user_type'])
            ->groupBy(function ($row) {
                if (! empty($row->user_id)) {
                    return 'u:'.$row->user_id;
                }

                return 'n:'.strtolower(trim((string) ($row->violator_name ?? 'unknown')));
            })
            ->map(function (Collection $group) {
                $first = $group->first();

                return [
                    'user_id' => $first->user_id,
                    'name' => $first->violator_name ?: 'Unknown',
                    'id_number' => $first->id_number ?: '—',
                    'user_type' => strtolower((string) ($first->user_type ?: 'user')),
                    'violations' => $group->count(),
                ];
            })
            ->sortByDesc('violations')
            ->values()
            ->take($limit);

        $userIds = $counts->pluck('user_id')->filter()->unique()->values()->all();
        $users = $userIds === []
            ? collect()
            : User::query()->with('role')->whereIn('id', $userIds)->get()->keyBy('id');

        return $counts->values()->map(function (array $row, int $index) use ($users) {
            $user = isset($row['user_id']) ? $users->get($row['user_id']) : null;

            return [
                'rank' => $index + 1,
                'name' => $user?->name ?: $row['name'],
                'id_number' => $user?->id_number ?: $row['id_number'],
                'user_type' => strtolower($user?->displayRoleLabel() ?: $row['user_type']),
                'violations' => $row['violations'],
            ];
        });
    }

    /**
     * @return Collection<int, array{date: string, label: string, count: int}>
     */
    private function gateCountsByDay(string $action, Carbon $start): Collection
    {
        $days = collect();
        for ($i = 0; $i < 7; $i++) {
            $date = $start->copy()->addDays($i);
            $days->push([
                'date' => $date->toDateString(),
                'label' => $date->format('D'),
                'count' => GateLog::query()
                    ->where('action', $action)
                    ->where('log_date', '>=', $date->copy()->startOfDay())
                    ->where('log_date', '<', $date->copy()->addDay())
                    ->count(),
            ]);
        }

        return $days;
    }
}
