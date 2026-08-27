<?php

namespace App\Services;

use App\Models\GateLog;
use App\Models\ParkingArea;
use App\Models\ParkingSlot;
use App\Models\User;
use App\Models\ViolationLog;
use App\Support\SimpleXlsxWriter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ReportExportService
{
    public const TYPE_ALL = 'all';

    public const TYPE_OVERVIEW = 'overview';

    public const TYPE_VIOLATIONS = 'violations';

    public const TYPE_PARKING = 'parking';

    public const TYPE_ACCESS = 'access';

    /** @var array<string, string> */
    public const LABELS = [
        self::TYPE_ALL => 'All Reports',
        self::TYPE_OVERVIEW => 'Overview Report',
        self::TYPE_VIOLATIONS => 'Violations Report',
        self::TYPE_PARKING => 'Parking Report',
        self::TYPE_ACCESS => 'Access Report',
    ];

    /** @var array<string, string> */
    public const FILE_SLUGS = [
        self::TYPE_ALL => 'all_reports',
        self::TYPE_OVERVIEW => 'overview_report',
        self::TYPE_VIOLATIONS => 'violations_report',
        self::TYPE_PARKING => 'parking_report',
        self::TYPE_ACCESS => 'access_report',
    ];

    public function __construct(
        private readonly DashboardStatsService $stats,
    ) {}

    public function normalizeType(?string $type): string
    {
        $type = strtolower(trim((string) $type));

        return array_key_exists($type, self::LABELS) ? $type : self::TYPE_ALL;
    }

    public function label(string $type): string
    {
        return self::LABELS[$this->normalizeType($type)];
    }

    public function fileSlug(string $type, bool $withTimestamp = true): string
    {
        $slug = self::FILE_SLUGS[$this->normalizeType($type)];

        if (! $withTimestamp) {
            return $slug;
        }

        return $slug.'_'.now()->format('Y-m-d_His');
    }

    /**
     * @param  array{from?: Carbon|null, to?: Carbon|null}  $range
     * @return array<string, mixed>
     */
    public function build(string $type, array $range = []): array
    {
        $type = $this->normalizeType($type);
        $summary = $this->stats->reportSummary($range);
        $from = Carbon::parse($summary['from'])->startOfDay();
        $to = Carbon::parse($summary['to'])->endOfDay();

        $payload = [
            'type' => $type,
            'title' => $this->label($type),
            'generated_at' => now(),
            'from' => $summary['from'],
            'to' => $summary['to'],
            'summary' => $summary,
        ];

        if (in_array($type, [self::TYPE_ALL, self::TYPE_OVERVIEW], true)) {
            $payload['users'] = User::query()
                ->with('role')
                ->where('user_role_id', '!=', NavigationService::ROLE_ADMIN)
                ->orderBy('fullname')
                ->get();
        }

        if (in_array($type, [self::TYPE_ALL, self::TYPE_VIOLATIONS], true)) {
            $payload['violations'] = ViolationLog::query()
                ->where('created_at', '>=', $from)
                ->where('created_at', '<=', $to)
                ->orderByDesc('created_at')
                ->limit(500)
                ->get();
            $payload['repeatOffenders'] = $summary['repeatOffenders'];
            $payload['violationsByType'] = $summary['violationsByType'];
            $payload['violationsByLocation'] = $summary['violationsByLocation'];
        }

        if (in_array($type, [self::TYPE_ALL, self::TYPE_PARKING], true)) {
            $payload['parkingAreas'] = $this->parkingAreaSnapshot();
            $payload['parkingDailyPattern'] = $summary['parkingDailyPattern'];
            $payload['parkingUtilization'] = $summary['parkingUtilization'];
            $payload['occupiedSlots'] = $summary['occupiedSlots'];
            $payload['totalSlots'] = $summary['totalSlots'];
        }

        if (in_array($type, [self::TYPE_ALL, self::TYPE_ACCESS], true)) {
            $payload['accessLogs'] = GateLog::query()
                ->with('user')
                ->where('timestamp', '>=', $from)
                ->where('timestamp', '<=', $to)
                ->orderByDesc('timestamp')
                ->limit(500)
                ->get();
            $payload['entriesByDay'] = $summary['entriesByDay'];
            $payload['exitsByDay'] = $summary['exitsByDay'];
            $payload['peakEntryExitHours'] = $summary['peakEntryExitHours'];
            $payload['monthlyAccessTrends'] = $summary['monthlyAccessTrends'];
            $payload['todayEntries'] = $summary['todayEntries'];
            $payload['todayExits'] = $summary['todayExits'];
            $payload['todayAccessLogs'] = $summary['todayAccessLogs'];
        }

        if ($type === self::TYPE_OVERVIEW || $type === self::TYPE_ALL) {
            $payload['userDistribution'] = $summary['userDistribution'];
            $payload['totalUsers'] = $summary['totalUsers'];
            $payload['grantedUsers'] = $summary['grantedUsers'];
            $payload['pendingUsers'] = $summary['pendingUsers'];
            $payload['lockedUsers'] = $summary['lockedUsers'];
            $payload['totalViolations'] = $summary['totalViolations'];
            $payload['activeViolations'] = $summary['activeViolations'];
            $payload['parkingUtilization'] = $summary['parkingUtilization'];
            $payload['todayAccessLogs'] = $summary['todayAccessLogs'];
        }

        return $payload;
    }

    /**
     * @return list<array{name: string, rows: list<list<scalar|null>>}>
     */
    public function excelSheets(array $payload): array
    {
        $type = $payload['type'];
        $sheets = [];

        $metaRows = [
            ['Report', $payload['title']],
            ['Generated', $payload['generated_at']->toDateTimeString()],
            ['Date Range', ($payload['from'] ?? '').' to '.($payload['to'] ?? '')],
        ];

        if (in_array($type, [self::TYPE_ALL, self::TYPE_OVERVIEW], true)) {
            $summary = $payload['summary'];
            $sheets[] = [
                'name' => 'Overview',
                'rows' => array_merge($metaRows, [
                    [],
                    ['Metric', 'Value'],
                    ['Total Users', $summary['totalUsers']],
                    ['Granted Users', $summary['grantedUsers']],
                    ['Pending Users', $summary['pendingUsers']],
                    ['Locked Users', $summary['lockedUsers']],
                    ['Total Violations', $summary['totalViolations']],
                    ['Active Violations', $summary['activeViolations']],
                    ['Access Logs Today', $summary['todayAccessLogs']],
                    ['Parking Utilization %', $summary['parkingUtilization']],
                    ['Occupied Slots', $summary['occupiedSlots']],
                    ['Total Slots', $summary['totalSlots']],
                ]),
            ];

            $dist = $summary['userDistribution'];
            $distRows = [['Role', 'Count']];
            foreach ($dist['labels'] as $i => $label) {
                $distRows[] = [$label, $dist['values'][$i] ?? 0];
            }
            $sheets[] = ['name' => 'User Distribution', 'rows' => $distRows];

            if ($type === self::TYPE_OVERVIEW || $type === self::TYPE_ALL) {
                $userRows = [['Name', 'Email', 'Role', 'ID Number', 'Status', 'Strikes']];
                /** @var Collection<int, User> $users */
                $users = $payload['users'] ?? collect();
                foreach ($users as $user) {
                    $userRows[] = [
                        $user->fullname,
                        $user->email,
                        $user->role?->role_name ?? '',
                        $user->id_number,
                        $user->isLocked() ? 'Locked' : $user->status,
                        (int) ($user->strike_count ?? 0),
                    ];
                }
                $sheets[] = ['name' => 'Users', 'rows' => $userRows];
            }
        }

        if (in_array($type, [self::TYPE_ALL, self::TYPE_VIOLATIONS], true)) {
            $typeRows = [['Type', 'Count']];
            foreach ($payload['violationsByType'] ?? [] as $vType => $count) {
                $typeRows[] = [$vType, $count];
            }
            $sheets[] = ['name' => 'Violations by Type', 'rows' => array_merge($metaRows, [[]], $typeRows)];

            $loc = $payload['violationsByLocation'] ?? ['labels' => [], 'values' => []];
            $locRows = [['Location', 'Count']];
            foreach ($loc['labels'] as $i => $label) {
                $locRows[] = [$label, $loc['values'][$i] ?? 0];
            }
            $sheets[] = ['name' => 'By Location', 'rows' => $locRows];

            $offenderRows = [['Rank', 'Name', 'ID Number', 'User Type', 'Violations']];
            foreach ($payload['repeatOffenders'] ?? [] as $row) {
                $offenderRows[] = [$row['rank'], $row['name'], $row['id_number'], $row['user_type'], $row['violations']];
            }
            $sheets[] = ['name' => 'Repeat Offenders', 'rows' => $offenderRows];

            $violationRows = [['Date', 'Violator', 'ID Number', 'Plate', 'Type', 'Status', 'Description']];
            foreach ($payload['violations'] ?? [] as $log) {
                $violationRows[] = [
                    $log->created_at?->toDateTimeString() ?? '',
                    $log->violator_name ?? '',
                    $log->id_number ?? '',
                    $log->plate_number ?? '',
                    $log->violation_type ?? '',
                    $log->status ?? '',
                    $log->description ?? '',
                ];
            }
            $sheets[] = ['name' => 'Violations', 'rows' => $violationRows];
        }

        if (in_array($type, [self::TYPE_ALL, self::TYPE_PARKING], true)) {
            $parkRows = array_merge($metaRows, [
                [],
                ['Metric', 'Value'],
                ['Parking Utilization %', $payload['parkingUtilization'] ?? 0],
                ['Occupied Slots', $payload['occupiedSlots'] ?? 0],
                ['Total Slots', $payload['totalSlots'] ?? 0],
            ]);
            $sheets[] = ['name' => 'Parking Summary', 'rows' => $parkRows];

            $areaRows = [['Area', 'Capacity', 'Occupied', 'Available', 'Utilization %']];
            foreach ($payload['parkingAreas'] ?? [] as $area) {
                $areaRows[] = [
                    $area['name'],
                    $area['capacity'],
                    $area['occupied'],
                    $area['available'],
                    $area['utilization'],
                ];
            }
            $sheets[] = ['name' => 'Parking Areas', 'rows' => $areaRows];

            $daily = $payload['parkingDailyPattern'] ?? ['labels' => [], 'values' => []];
            $dailyRows = [['Hour', 'Estimated Occupancy']];
            foreach ($daily['labels'] as $i => $label) {
                $dailyRows[] = [$label, $daily['values'][$i] ?? 0];
            }
            $sheets[] = ['name' => 'Daily Pattern', 'rows' => $dailyRows];
        }

        if (in_array($type, [self::TYPE_ALL, self::TYPE_ACCESS], true)) {
            $accessSummary = array_merge($metaRows, [
                [],
                ['Metric', 'Value'],
                ['Entries Today', $payload['todayEntries'] ?? 0],
                ['Exits Today', $payload['todayExits'] ?? 0],
                ['Access Logs Today', $payload['todayAccessLogs'] ?? 0],
            ]);
            $sheets[] = ['name' => 'Access Summary', 'rows' => $accessSummary];

            $entryRows = [['Date', 'Day', 'Entries']];
            foreach ($payload['entriesByDay'] ?? [] as $day) {
                $entryRows[] = [$day['date'], $day['label'], $day['count']];
            }
            $sheets[] = ['name' => 'Entries 7 Days', 'rows' => $entryRows];

            $exitRows = [['Date', 'Day', 'Exits']];
            foreach ($payload['exitsByDay'] ?? [] as $day) {
                $exitRows[] = [$day['date'], $day['label'], $day['count']];
            }
            $sheets[] = ['name' => 'Exits 7 Days', 'rows' => $exitRows];

            $peak = $payload['peakEntryExitHours'] ?? ['labels' => [], 'values' => []];
            $peakRows = [['Hour Range', 'Access Count']];
            foreach ($peak['labels'] as $i => $label) {
                $peakRows[] = [$label, $peak['values'][$i] ?? 0];
            }
            $sheets[] = ['name' => 'Peak Hours', 'rows' => $peakRows];

            $logRows = [['Timestamp', 'Action', 'Gate', 'Result', 'User', 'RFID UID']];
            foreach ($payload['accessLogs'] ?? [] as $log) {
                $logRows[] = [
                    $log->timestamp?->toDateTimeString() ?? '',
                    $log->action ?? '',
                    $log->gate_id ?? '',
                    $log->result ?? '',
                    $log->user?->fullname ?? '',
                    $log->rfid_uid ?? '',
                ];
            }
            $sheets[] = ['name' => 'Access Logs', 'rows' => $logRows];
        }

        return $sheets !== [] ? $sheets : [['name' => 'Report', 'rows' => $metaRows]];
    }

    public function toXlsxBinary(array $payload): string
    {
        return (new SimpleXlsxWriter)->build($this->excelSheets($payload));
    }

    /**
     * @return list<array{name: string, capacity: int, occupied: int, available: int, utilization: int}>
     */
    private function parkingAreaSnapshot(): array
    {
        $slots = ParkingSlot::query()->get(['area_id', 'status']);
        $areas = ParkingArea::query()->orderBy('area_name')->get(['id', 'area_name', 'capacity']);

        return $areas->map(function (ParkingArea $area) use ($slots) {
            $areaSlots = $slots->where('area_id', $area->id);
            $capacity = max(1, (int) ($area->capacity ?: $areaSlots->count() ?: 1));
            $occupied = $areaSlots->where('status', 'Occupied')->count();
            $available = $areaSlots->where('status', 'Available')->count();

            return [
                'name' => (string) $area->area_name,
                'capacity' => (int) ($area->capacity ?: $areaSlots->count()),
                'occupied' => $occupied,
                'available' => $available,
                'utilization' => (int) round(($occupied / $capacity) * 100),
            ];
        })->values()->all();
    }
}
