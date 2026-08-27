@php
    $logoPath = public_path('images/cspc-logo.png');
    $canEmbedLogo = extension_loaded('gd') && is_file($logoPath);
    $logoSrc = $canEmbedLogo
        ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath))
        : null;
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 118px 36px 68px 36px;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10.5px;
            color: #1e293b;
            line-height: 1.35;
        }
        header {
            position: fixed;
            top: -98px;
            left: 0;
            right: 0;
            height: 90px;
        }
        footer {
            position: fixed;
            bottom: -52px;
            left: 0;
            right: 0;
            height: 42px;
        }
        .lh {
            width: 100%;
            border-collapse: collapse;
        }
        .lh td {
            border: none;
            vertical-align: middle;
            padding: 0;
        }
        .lh-logo {
            width: 68px;
        }
        .lh-logo img {
            width: 58px;
            height: 58px;
        }
        .seal {
            width: 54px;
            height: 54px;
            border: 2px solid #c9a227;
            color: #0f274f;
            font-size: 11px;
            font-weight: 700;
            text-align: center;
            line-height: 50px;
        }
        .lh-center {
            text-align: center;
            padding: 0 8px;
        }
        .lh-iso {
            width: 78px;
            text-align: right;
        }
        .rep {
            font-size: 9px;
            letter-spacing: 0.4px;
            color: #334155;
            margin: 0;
        }
        .campus {
            font-size: 13px;
            font-weight: 700;
            color: #0f274f;
            letter-spacing: 0.4px;
            margin: 1px 0 2px;
            text-transform: uppercase;
        }
        .addr, .tel {
            font-size: 8px;
            color: #475569;
            margin: 0;
        }
        .iso-box {
            display: inline-block;
            border: 1.5px solid #c9a227;
            color: #0f274f;
            font-size: 7px;
            font-weight: 700;
            text-align: center;
            line-height: 1.25;
            padding: 5px 4px;
            width: 68px;
        }
        .rule-navy {
            height: 2.5px;
            background: #0f274f;
            margin-top: 6px;
        }
        .rule-gold {
            height: 1.5px;
            background: #c9a227;
            margin-top: 1.5px;
        }
        .doc-title {
            text-align: center;
            margin: 2px 0 8px;
        }
        .doc-title h1 {
            font-size: 13px;
            color: #0f274f;
            margin: 6px 0 2px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .doc-title .system {
            font-size: 9px;
            color: #334155;
            margin: 0 0 4px;
        }
        .info {
            font-size: 10px;
            color: #334155;
            text-align: justify;
            margin: 0 0 8px;
            line-height: 1.45;
        }
        .remarks {
            font-size: 9px;
            color: #475569;
            text-align: justify;
            margin: 20px 0 0;
            line-height: 1.4;
        }
        .section-label {
            font-size: 11px;
            font-weight: 700;
            color: #0f274f;
            margin: 12px 0 10px;
            padding: 2px 0 2px 6px;
            border-left: 3px solid #c9a227;
        }
        table.block {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 28px;
        }
        table.block td.cell {
            border: none;
            padding: 0;
            vertical-align: top;
        }
        table.together {
            page-break-inside: avoid;
        }
        table.data {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }
        table.data th,
        table.data td {
            text-align: left;
            padding: 5px 7px;
            border: 1px solid #dbe3ee;
            vertical-align: top;
        }
        table.data th {
            background: #0f274f;
            color: #ffffff;
            font-weight: 700;
            font-size: 8.5px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        table.data thead {
            display: table-header-group;
        }
        table.data thead tr {
            page-break-inside: avoid;
            page-break-after: avoid;
        }
        table.data tr {
            page-break-inside: avoid;
        }
        table.data tr:nth-child(even) td {
            background: #f8fafc;
        }
        th.banner {
            background: #ffffff;
            color: #0f274f;
            text-align: left;
            text-transform: none;
            font-size: 11.5px;
            letter-spacing: 0;
            border: none;
            border-bottom: 1px solid #e2e8f0;
            border-left: 3px solid #c9a227;
            padding: 8px 6px 8px 8px;
        }
        .num {
            text-align: right;
            white-space: nowrap;
        }
        .stat-row td:first-child {
            font-weight: 700;
            width: 62%;
            color: #0f274f;
        }
        .page-break {
            page-break-before: always;
        }
        .ft {
            width: 100%;
            border-collapse: collapse;
        }
        .ft td {
            border: none;
            padding: 5px 0 0;
            font-size: 7.5px;
            color: #334155;
            vertical-align: top;
        }
        .ft .left { text-align: left; width: 38%; }
        .ft .mid { text-align: center; width: 24%; }
        .ft .right { text-align: right; width: 38%; }
        .ft-rule-gold {
            height: 1.5px;
            background: #c9a227;
        }
        .ft-rule-navy {
            height: 2px;
            background: #0f274f;
            margin-top: 1.5px;
        }
    </style>
</head>
<body>
    <header>
        <table class="lh">
            <tr>
                <td class="lh-logo">
                    @if ($logoSrc)
                        <img src="{{ $logoSrc }}" alt="CSPC">
                    @else
                        <div class="seal">CSPC</div>
                    @endif
                </td>
                <td class="lh-center">
                    <p class="rep">Republic of the Philippines</p>
                    <p class="campus">Camarines Sur Polytechnic Colleges</p>
                    <p class="addr">Nabua, Camarines Sur</p>
                    <p class="tel">Tel. No. (054) 288-4425</p>
                </td>
                <td class="lh-iso">
                    <div class="iso-box">ISO 9001:2015<br>CERTIFIED</div>
                </td>
            </tr>
        </table>
        <div class="rule-navy"></div>
        <div class="rule-gold"></div>
    </header>

    <footer>
        <div class="ft-rule-gold"></div>
        <div class="ft-rule-navy"></div>
        <table class="ft">
            <tr>
                <td class="left">
                    General Services Unit<br>
                    Smart Campus Vehicle Management System
                </td>
                <td class="mid">&nbsp;</td>
                <td class="right">
                    {{ $title }}<br>
                    Generated {{ ph_datetime($generated_at, 'F j, Y g:i A') }}
                </td>
            </tr>
        </table>
    </footer>

    <script type="text/php">
        if (isset($pdf)) {
            $font = $fontMetrics->get_font('DejaVu Sans', 'normal');
            $size = 8;
            $color = array(0.06, 0.15, 0.31);
            $pdf->page_text(248, 798, 'Page {PAGE_NUM} of {PAGE_COUNT}', $font, $size, $color);
        }
    </script>

    <div class="doc-title">
        <h1>{{ $title }}</h1>
        <p class="system">Smart Campus Vehicle Management System</p>
    </div>

    <p class="info">
        This report is prepared by the General Services Unit of Camarines Sur Polytechnic Colleges
        through the Smart Campus Vehicle Management System. It is issued for administrative and
        operational reference in managing campus vehicle registration, RFID gate access, parking
        occupancy, and violation records, in line with the Internal Policy and Guidelines for the
        Use of Parking Spaces and Vehicle Stickers.
    </p>
    <p class="info">
        Coverage period: <strong>{{ $from }}</strong> to <strong>{{ $to }}</strong>.
        Figures below are taken from official system records as of
        <strong>{{ ph_datetime($generated_at, 'F j, Y g:i A') }}</strong>.
    </p>

    <div class="section-label">Summary of Records</div>

    <table class="block together">
        <tr><td class="cell">
    @if ($type === 'parking')
        <table class="data">
            <thead>
                <tr>
                    <th>Parking Area</th>
                    <th class="num">Capacity</th>
                    <th class="num">Occupied</th>
                    <th class="num">Available</th>
                    <th class="num">Utilization</th>
                </tr>
            </thead>
            <tbody>
                @forelse (($parkingAreas ?? []) as $area)
                    <tr>
                        <td>{{ $area['name'] }}</td>
                        <td class="num">{{ $area['capacity'] }}</td>
                        <td class="num">{{ $area['occupied'] }}</td>
                        <td class="num">{{ $area['available'] }}</td>
                        <td class="num">{{ $area['utilization'] }}%</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No parking area records.</td></tr>
                @endforelse
                <tr class="stat-row">
                    <td>Campus Total</td>
                    <td class="num">{{ $totalSlots ?? $summary['totalSlots'] ?? 0 }}</td>
                    <td class="num">{{ $occupiedSlots ?? $summary['occupiedSlots'] ?? 0 }}</td>
                    <td class="num">{{ ($totalSlots ?? $summary['totalSlots'] ?? 0) - ($occupiedSlots ?? $summary['occupiedSlots'] ?? 0) }}</td>
                    <td class="num">{{ $parkingUtilization ?? $summary['parkingUtilization'] ?? 0 }}%</td>
                </tr>
            </tbody>
        </table>
    @elseif ($type === 'violations')
        <table class="data">
            <thead>
                <tr>
                    <th>Particular</th>
                    <th class="num">Count</th>
                </tr>
            </thead>
            <tbody>
                <tr class="stat-row"><td>Total Violations</td><td class="num">{{ $summary['totalViolations'] }}</td></tr>
                <tr class="stat-row"><td>Active Violations</td><td class="num">{{ $summary['activeViolations'] }}</td></tr>
                @forelse (($violationsByType ?? []) as $vType => $count)
                    <tr><td>{{ $vType }}</td><td class="num">{{ $count }}</td></tr>
                @empty
                    <tr><td class="muted" colspan="2">No violation types recorded in this period.</td></tr>
                @endforelse
            </tbody>
        </table>
    @elseif ($type === 'access')
        <table class="data">
            <thead>
                <tr>
                    <th>Particular</th>
                    <th class="num">Count</th>
                </tr>
            </thead>
            <tbody>
                <tr class="stat-row"><td>Entries Today</td><td class="num">{{ $todayEntries ?? $summary['todayEntries'] ?? 0 }}</td></tr>
                <tr class="stat-row"><td>Exits Today</td><td class="num">{{ $todayExits ?? $summary['todayExits'] ?? 0 }}</td></tr>
                <tr class="stat-row"><td>Access Logs Today</td><td class="num">{{ $todayAccessLogs ?? $summary['todayAccessLogs'] ?? 0 }}</td></tr>
                @foreach (($entriesByDay ?? []) as $day)
                    <tr>
                        <td>Entries — {{ $day['label'] }} ({{ $day['date'] }})</td>
                        <td class="num">{{ $day['count'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th>Particular</th>
                    <th class="num">Count / Value</th>
                </tr>
            </thead>
            <tbody>
                <tr class="stat-row"><td>Total Users</td><td class="num">{{ $summary['totalUsers'] }}</td></tr>
                <tr class="stat-row"><td>Granted Users</td><td class="num">{{ $summary['grantedUsers'] }}</td></tr>
                <tr class="stat-row"><td>Pending Users</td><td class="num">{{ $summary['pendingUsers'] }}</td></tr>
                <tr class="stat-row"><td>Locked Users</td><td class="num">{{ $summary['lockedUsers'] }}</td></tr>
                <tr class="stat-row"><td>Total Violations</td><td class="num">{{ $summary['totalViolations'] }}</td></tr>
                <tr class="stat-row"><td>Active Violations</td><td class="num">{{ $summary['activeViolations'] }}</td></tr>
                <tr class="stat-row"><td>Access Logs Today</td><td class="num">{{ $summary['todayAccessLogs'] }}</td></tr>
                <tr class="stat-row"><td>Parking Occupied / Total Slots</td><td class="num">{{ $summary['occupiedSlots'] }} / {{ $summary['totalSlots'] }}</td></tr>
                <tr class="stat-row"><td>Parking Utilization</td><td class="num">{{ $summary['parkingUtilization'] }}%</td></tr>
            </tbody>
        </table>
    @endif
        </td></tr>
    </table>

    <p class="remarks">
        <strong>Remarks.</strong> This page is the official summary. The full report with supporting
        tables and transaction records continues on the following pages. This document is generated
        automatically from the Smart Campus Vehicle Management System for the General Services Unit
        and authorized CSPC administrators. This report shall not be altered after generation.
    </p>

    <div class="page-break">
        <div class="doc-title">
            <h1>{{ $title }} — Full Report</h1>
            <p class="system">Detailed records for {{ $from }} to {{ $to }}</p>
        </div>
        <p class="info">
            The tables below present the complete supporting data for this report. Summary totals
            on page 1 are derived from these records.
        </p>

        @php
            $usersList = collect($users ?? []);
            $violationsList = collect($violations ?? []);
            $logsList = collect($accessLogs ?? []);
        @endphp

        @if (in_array($type, ['all', 'overview'], true))
            <table class="block together"><tr><td class="cell">
                <table class="data">
                    <thead>
                        <tr><th class="banner" colspan="2">User Distribution</th></tr>
                        <tr><th>Role</th><th class="num">Count</th></tr>
                    </thead>
                    <tbody>
                        @foreach (($summary['userDistribution']['labels'] ?? []) as $i => $label)
                            <tr>
                                <td>{{ $label }}</td>
                                <td class="num">{{ $summary['userDistribution']['values'][$i] ?? 0 }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </td></tr></table>

            <table class="block {{ $usersList->count() <= 12 ? 'together' : '' }}"><tr><td class="cell">
                <table class="data">
                    <thead>
                        <tr><th class="banner" colspan="5">Registered Users</th></tr>
                        <tr><th>Name</th><th>Role</th><th>ID</th><th>Status</th><th class="num">Strikes</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($usersList as $user)
                            <tr>
                                <td>{{ $user->fullname }}</td>
                                <td>{{ $user->role?->role_name }}</td>
                                <td>{{ $user->id_number }}</td>
                                <td>{{ $user->isLocked() ? 'Locked' : $user->status }}</td>
                                <td class="num">{{ $user->strike_count }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="muted">No users to list.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </td></tr></table>
        @endif

        @if (in_array($type, ['all', 'violations'], true))
            <table class="block together"><tr><td class="cell">
                <table class="data">
                    <thead>
                        <tr><th class="banner" colspan="2">Violations by Location</th></tr>
                        <tr><th>Location</th><th class="num">Count</th></tr>
                    </thead>
                    <tbody>
                        @forelse (($violationsByLocation['labels'] ?? []) as $i => $label)
                            <tr><td>{{ $label }}</td><td class="num">{{ $violationsByLocation['values'][$i] ?? 0 }}</td></tr>
                        @empty
                            <tr><td colspan="2" class="muted">No location data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </td></tr></table>

            <table class="block together"><tr><td class="cell">
                <table class="data">
                    <thead>
                        <tr><th class="banner" colspan="5">Repeat Offenders</th></tr>
                        <tr><th>Rank</th><th>Name</th><th>ID</th><th>Type</th><th class="num">Violations</th></tr>
                    </thead>
                    <tbody>
                        @forelse (($repeatOffenders ?? []) as $row)
                            <tr>
                                <td>#{{ $row['rank'] }}</td>
                                <td>{{ $row['name'] }}</td>
                                <td>{{ $row['id_number'] }}</td>
                                <td>{{ $row['user_type'] }}</td>
                                <td class="num">{{ $row['violations'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="muted">No repeat offenders.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </td></tr></table>

            <table class="block {{ $violationsList->count() <= 12 ? 'together' : '' }}"><tr><td class="cell">
                <table class="data">
                    <thead>
                        <tr><th class="banner" colspan="5">Violation Records</th></tr>
                        <tr><th>Date</th><th>Violator</th><th>Plate</th><th>Type</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($violationsList as $v)
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
            </td></tr></table>
        @endif

        @if (in_array($type, ['all', 'parking'], true))
            <table class="block together"><tr><td class="cell">
                <table class="data">
                    <thead>
                        <tr><th class="banner" colspan="5">Parking Areas</th></tr>
                        <tr>
                            <th>Area</th>
                            <th class="num">Capacity</th>
                            <th class="num">Occupied</th>
                            <th class="num">Available</th>
                            <th class="num">Util %</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse (($parkingAreas ?? []) as $area)
                            <tr>
                                <td>{{ $area['name'] }}</td>
                                <td class="num">{{ $area['capacity'] }}</td>
                                <td class="num">{{ $area['occupied'] }}</td>
                                <td class="num">{{ $area['available'] }}</td>
                                <td class="num">{{ $area['utilization'] }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="muted">No parking area records.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </td></tr></table>

            <table class="block together"><tr><td class="cell">
                <table class="data">
                    <thead>
                        <tr><th class="banner" colspan="2">Daily Pattern (Estimated Occupancy)</th></tr>
                        <tr><th>Hour</th><th class="num">Occupancy</th></tr>
                    </thead>
                    <tbody>
                        @foreach (($parkingDailyPattern['labels'] ?? []) as $i => $label)
                            <tr>
                                <td>{{ $label }}</td>
                                <td class="num">{{ $parkingDailyPattern['values'][$i] ?? 0 }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </td></tr></table>
        @endif

        @if (in_array($type, ['all', 'access'], true))
            <table class="block together"><tr><td class="cell">
                <table class="data">
                    <thead>
                        <tr><th class="banner" colspan="3">Exits (Last 7 Days)</th></tr>
                        <tr><th>Date</th><th>Day</th><th class="num">Count</th></tr>
                    </thead>
                    <tbody>
                        @foreach (($exitsByDay ?? []) as $day)
                            <tr><td>{{ $day['date'] }}</td><td>{{ $day['label'] }}</td><td class="num">{{ $day['count'] }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </td></tr></table>

            <table class="block together"><tr><td class="cell">
                <table class="data">
                    <thead>
                        <tr><th class="banner" colspan="2">Peak Entry/Exit Hours</th></tr>
                        <tr><th>Hour</th><th class="num">Count</th></tr>
                    </thead>
                    <tbody>
                        @foreach (($peakEntryExitHours['labels'] ?? []) as $i => $label)
                            <tr>
                                <td>{{ $label }}</td>
                                <td class="num">{{ $peakEntryExitHours['values'][$i] ?? 0 }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </td></tr></table>

            <table class="block {{ $logsList->count() <= 12 ? 'together' : '' }}"><tr><td class="cell">
                <table class="data">
                    <thead>
                        <tr><th class="banner" colspan="5">Access Logs</th></tr>
                        <tr><th>Time</th><th>Action</th><th>Gate</th><th>Result</th><th>User</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($logsList as $log)
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
            </td></tr></table>
        @endif
    </div>
</body>
</html>
