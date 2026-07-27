<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1e293b; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        h2 { font-size: 13px; color: #334155; margin-top: 20px; margin-bottom: 6px; border-bottom: 1px solid #e2e8f0; padding-bottom: 3px; }
        .subtitle { font-size: 10px; color: #64748b; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        th, td { text-align: left; padding: 5px 8px; border-bottom: 1px solid #e2e8f0; }
        th { background: #f8fafc; font-weight: 600; font-size: 10px; color: #475569; text-transform: uppercase; }
        .stat-row td:first-child { font-weight: 600; }
        .stat-row td:last-child { text-align: right; }
    </style>
</head>
<body>
    <h1>Smart Campus VMS &mdash; System Report</h1>
    <p class="subtitle">Generated {{ ph_datetime(now(), 'F j, Y g:i A') }}</p>

    <h2>User Statistics</h2>
    <table>
        <tr class="stat-row"><td>Total Users (non-admin)</td><td>{{ $totalUsers }}</td></tr>
        <tr class="stat-row"><td>Granted</td><td>{{ $grantedUsers }}</td></tr>
        <tr class="stat-row"><td>Pending</td><td>{{ $pendingUsers }}</td></tr>
        <tr class="stat-row"><td>Locked</td><td>{{ $lockedUsers }}</td></tr>
    </table>

    <h2>Gate Activity (Today)</h2>
    <table>
        <tr class="stat-row"><td>Entries</td><td>{{ $todayEntries }}</td></tr>
        <tr class="stat-row"><td>Exits</td><td>{{ $todayExits }}</td></tr>
        <tr class="stat-row"><td>Access Logs</td><td>{{ $todayAccessLogs ?? 0 }}</td></tr>
        <tr class="stat-row"><td>Parking Utilization</td><td>{{ $parkingUtilization ?? 0 }}%</td></tr>
    </table>

    <h2>Violations</h2>
    <table>
        <tr class="stat-row"><td>Total</td><td>{{ $totalViolations }}</td></tr>
        <tr class="stat-row"><td>Active</td><td>{{ $activeViolations }}</td></tr>
    </table>

    <h2>Entries by Day (Last 7 Days)</h2>
    <table>
        <thead><tr><th>Date</th><th>Day</th><th style="text-align:right">Count</th></tr></thead>
        <tbody>
            @foreach ($entriesByDay as $day)
                <tr><td>{{ $day['date'] }}</td><td>{{ $day['label'] }}</td><td style="text-align:right">{{ $day['count'] }}</td></tr>
            @endforeach
        </tbody>
    </table>

    <h2>Exits by Day (Last 7 Days)</h2>
    <table>
        <thead><tr><th>Date</th><th>Day</th><th style="text-align:right">Count</th></tr></thead>
        <tbody>
            @foreach ($exitsByDay as $day)
                <tr><td>{{ $day['date'] }}</td><td>{{ $day['label'] }}</td><td style="text-align:right">{{ $day['count'] }}</td></tr>
            @endforeach
        </tbody>
    </table>

    <h2>Violations by Type</h2>
    <table>
        <thead><tr><th>Type</th><th style="text-align:right">Count</th></tr></thead>
        <tbody>
            @forelse ($violationsByType as $type => $count)
                <tr><td>{{ $type }}</td><td style="text-align:right">{{ $count }}</td></tr>
            @empty
                <tr><td colspan="2">No violations recorded.</td></tr>
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
                <tr><td colspan="2">No location data.</td></tr>
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
                <tr><td colspan="5">No repeat offenders.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Recent Violations (last 50)</h2>
    <table>
        <thead><tr><th>Date</th><th>Violator</th><th>Plate</th><th>Type</th><th>Status</th></tr></thead>
        <tbody>
            @foreach ($recentViolations as $v)
                <tr>
                    <td>{{ ph_datetime($v->created_at, 'Y-m-d H:i') }}</td>
                    <td>{{ $v->violator_name ?? '' }}</td>
                    <td>{{ $v->plate_number ?? '' }}</td>
                    <td>{{ $v->violation_type ?? '' }}</td>
                    <td>{{ $v->status ?? '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
