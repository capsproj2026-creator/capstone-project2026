<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        h2 { font-size: 13px; color: #334155; margin: 18px 0 6px; border-bottom: 1px solid #e2e8f0; padding-bottom: 3px; }
        .subtitle { font-size: 10px; color: #64748b; margin: 0 0 14px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        th, td { text-align: left; padding: 5px 8px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        th { background: #f8fafc; font-weight: 600; font-size: 10px; color: #475569; text-transform: uppercase; }
        .stat-row td:first-child { font-weight: 600; }
        .stat-row td:last-child { text-align: right; }
        .muted { color: #64748b; }
    </style>
</head>
<body>
    <h1>Smart Campus VMS — {{ $title }}</h1>
    <p class="subtitle">
        Generated {{ ph_datetime($generated_at, 'F j, Y g:i A') }}
        &nbsp;|&nbsp; Range {{ $from }} to {{ $to }}
    </p>

    @if (in_array($type, ['all', 'overview'], true))
        <h2>Summary</h2>
        <table>
            <tr class="stat-row"><td>Total Users</td><td>{{ $summary['totalUsers'] }}</td></tr>
            <tr class="stat-row"><td>Granted</td><td>{{ $summary['grantedUsers'] }}</td></tr>
            <tr class="stat-row"><td>Pending</td><td>{{ $summary['pendingUsers'] }}</td></tr>
            <tr class="stat-row"><td>Locked</td><td>{{ $summary['lockedUsers'] }}</td></tr>
            <tr class="stat-row"><td>Total Violations</td><td>{{ $summary['totalViolations'] }}</td></tr>
            <tr class="stat-row"><td>Active Violations</td><td>{{ $summary['activeViolations'] }}</td></tr>
            <tr class="stat-row"><td>Access Logs Today</td><td>{{ $summary['todayAccessLogs'] }}</td></tr>
            <tr class="stat-row"><td>Parking Utilization</td><td>{{ $summary['parkingUtilization'] }}%</td></tr>
        </table>

        <h2>User Distribution</h2>
        <table>
            <thead><tr><th>Role</th><th style="text-align:right">Count</th></tr></thead>
            <tbody>
                @foreach ($summary['userDistribution']['labels'] as $i => $label)
                    <tr>
                        <td>{{ $label }}</td>
                        <td style="text-align:right">{{ $summary['userDistribution']['values'][$i] ?? 0 }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if (($users ?? null) && $type === 'overview')
            <h2>Users</h2>
            <table>
                <thead><tr><th>Name</th><th>Role</th><th>ID</th><th>Status</th><th>Strikes</th></tr></thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr>
                            <td>{{ $user->fullname }}</td>
                            <td>{{ $user->role?->role_name }}</td>
                            <td>{{ $user->id_number }}</td>
                            <td>{{ $user->isLocked() ? 'Locked' : $user->status }}</td>
                            <td>{{ $user->strike_count }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endif

    @if (in_array($type, ['all', 'violations'], true))
        <h2>Violations Summary</h2>
        <table>
            <tr class="stat-row"><td>Total Violations</td><td>{{ $summary['totalViolations'] }}</td></tr>
            <tr class="stat-row"><td>Active Violations</td><td>{{ $summary['activeViolations'] }}</td></tr>
        </table>

        <h2>Violations by Type</h2>
        <table>
            <thead><tr><th>Type</th><th style="text-align:right">Count</th></tr></thead>
            <tbody>
                @forelse ($violationsByType as $vType => $count)
                    <tr><td>{{ $vType }}</td><td style="text-align:right">{{ $count }}</td></tr>
                @empty
                    <tr><td colspan="2" class="muted">No violations recorded.</td></tr>
                @endforelse
            </tbody>
        </table>

        <h2>Violations by Location</h2>
        <table>
            <thead><tr><th>Location</th><th style="text-align:right">Count</th></tr></thead>
            <tbody>
                @forelse (($violationsByLocation['labels'] ?? []) as $i => $label)
                    <tr><td>{{ $label }}</td><td style="text-align:right">{{ $violationsByLocation['values'][$i] ?? 0 }}</td></tr>
                @empty
                    <tr><td colspan="2" class="muted">No location data.</td></tr>
                @endforelse
            </tbody>
        </table>

        <h2>Repeat Offenders</h2>
        <table>
            <thead><tr><th>Rank</th><th>Name</th><th>ID</th><th>Type</th><th style="text-align:right">Violations</th></tr></thead>
            <tbody>
                @forelse ($repeatOffenders as $row)
                    <tr>
                        <td>#{{ $row['rank'] }}</td>
                        <td>{{ $row['name'] }}</td>
                        <td>{{ $row['id_number'] }}</td>
                        <td>{{ $row['user_type'] }}</td>
                        <td style="text-align:right">{{ $row['violations'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No repeat offenders.</td></tr>
                @endforelse
            </tbody>
        </table>

        <h2>Violation Records</h2>
        <table>
            <thead><tr><th>Date</th><th>Violator</th><th>Plate</th><th>Type</th><th>Status</th></tr></thead>
            <tbody>
                @forelse ($violations as $v)
                    <tr>
                        <td>{{ ph_datetime($v->created_at, 'Y-m-d H:i') }}</td>
                        <td>{{ $v->violator_name }}</td>
                        <td>{{ $v->plate_number }}</td>
                        <td>{{ $v->violation_type }}</td>
                        <td>{{ $v->status }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No violations in range.</td></tr>
                @endforelse
            </tbody>
        </table>
    @endif

    @if (in_array($type, ['all', 'parking'], true))
        <h2>Parking Summary</h2>
        <table>
            <tr class="stat-row"><td>Utilization</td><td>{{ $parkingUtilization }}%</td></tr>
            <tr class="stat-row"><td>Occupied Slots</td><td>{{ $occupiedSlots }}</td></tr>
            <tr class="stat-row"><td>Total Slots</td><td>{{ $totalSlots }}</td></tr>
        </table>

        <h2>Parking Areas</h2>
        <table>
            <thead>
                <tr>
                    <th>Area</th>
                    <th style="text-align:right">Capacity</th>
                    <th style="text-align:right">Occupied</th>
                    <th style="text-align:right">Available</th>
                    <th style="text-align:right">Util %</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($parkingAreas as $area)
                    <tr>
                        <td>{{ $area['name'] }}</td>
                        <td style="text-align:right">{{ $area['capacity'] }}</td>
                        <td style="text-align:right">{{ $area['occupied'] }}</td>
                        <td style="text-align:right">{{ $area['available'] }}</td>
                        <td style="text-align:right">{{ $area['utilization'] }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <h2>Daily Pattern (Estimated Occupancy)</h2>
        <table>
            <thead><tr><th>Hour</th><th style="text-align:right">Occupancy</th></tr></thead>
            <tbody>
                @foreach ($parkingDailyPattern['labels'] as $i => $label)
                    <tr>
                        <td>{{ $label }}</td>
                        <td style="text-align:right">{{ $parkingDailyPattern['values'][$i] ?? 0 }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if (in_array($type, ['all', 'access'], true))
        <h2>Access Summary</h2>
        <table>
            <tr class="stat-row"><td>Entries Today</td><td>{{ $todayEntries }}</td></tr>
            <tr class="stat-row"><td>Exits Today</td><td>{{ $todayExits }}</td></tr>
            <tr class="stat-row"><td>Access Logs Today</td><td>{{ $todayAccessLogs }}</td></tr>
        </table>

        <h2>Entries (Last 7 Days)</h2>
        <table>
            <thead><tr><th>Date</th><th>Day</th><th style="text-align:right">Count</th></tr></thead>
            <tbody>
                @foreach ($entriesByDay as $day)
                    <tr><td>{{ $day['date'] }}</td><td>{{ $day['label'] }}</td><td style="text-align:right">{{ $day['count'] }}</td></tr>
                @endforeach
            </tbody>
        </table>

        <h2>Exits (Last 7 Days)</h2>
        <table>
            <thead><tr><th>Date</th><th>Day</th><th style="text-align:right">Count</th></tr></thead>
            <tbody>
                @foreach ($exitsByDay as $day)
                    <tr><td>{{ $day['date'] }}</td><td>{{ $day['label'] }}</td><td style="text-align:right">{{ $day['count'] }}</td></tr>
                @endforeach
            </tbody>
        </table>

        <h2>Peak Entry/Exit Hours</h2>
        <table>
            <thead><tr><th>Hour</th><th style="text-align:right">Count</th></tr></thead>
            <tbody>
                @foreach ($peakEntryExitHours['labels'] as $i => $label)
                    <tr>
                        <td>{{ $label }}</td>
                        <td style="text-align:right">{{ $peakEntryExitHours['values'][$i] ?? 0 }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <h2>Access Logs</h2>
        <table>
            <thead><tr><th>Time</th><th>Action</th><th>Gate</th><th>Result</th><th>User</th></tr></thead>
            <tbody>
                @forelse ($accessLogs as $log)
                    <tr>
                        <td>{{ ph_datetime($log->timestamp, 'Y-m-d H:i') }}</td>
                        <td>{{ $log->action }}</td>
                        <td>{{ $log->gate_id }}</td>
                        <td>{{ $log->result }}</td>
                        <td>{{ $log->user?->fullname ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No access logs in range.</td></tr>
                @endforelse
            </tbody>
        </table>
    @endif
</body>
</html>
