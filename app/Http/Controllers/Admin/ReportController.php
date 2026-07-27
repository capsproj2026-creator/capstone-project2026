<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\ViolationLog;
use App\Services\DashboardStatsService;
use App\Services\ReportExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request, DashboardStatsService $stats, ReportExportService $exports): View
    {
        $data = $stats->reportSummary($this->rangeFromRequest($request));
        $data['reportType'] = $exports->normalizeType($request->query('type'));
        $data['reportTypes'] = ReportExportService::LABELS;

        return view('admin.reports', $data);
    }

    /**
     * Legacy CSV export (full summary) — kept for compatibility.
     */
    public function export(Request $request, DashboardStatsService $stats): StreamedResponse
    {
        $data = $stats->reportSummary($this->rangeFromRequest($request));
        $filename = 'smart-campus-vms-report-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($data): void {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['Smart Campus VMS — System Report']);
            fputcsv($out, ['Generated', now()->toDateTimeString()]);
            fputcsv($out, ['Range', ($data['from'] ?? '').' to '.($data['to'] ?? '')]);
            fputcsv($out, []);

            fputcsv($out, ['User Statistics']);
            fputcsv($out, ['Metric', 'Count']);
            fputcsv($out, ['Total Users (non-admin)', $data['totalUsers']]);
            fputcsv($out, ['Granted', $data['grantedUsers']]);
            fputcsv($out, ['Pending', $data['pendingUsers']]);
            fputcsv($out, ['Locked', $data['lockedUsers']]);
            fputcsv($out, []);

            fputcsv($out, ['Gate Activity (Today)']);
            fputcsv($out, ['Entries', $data['todayEntries']]);
            fputcsv($out, ['Exits', $data['todayExits']]);
            fputcsv($out, ['Access Logs Today', $data['todayAccessLogs']]);
            fputcsv($out, ['Parking Utilization %', $data['parkingUtilization']]);
            fputcsv($out, []);

            fputcsv($out, ['Violations']);
            fputcsv($out, ['Total', $data['totalViolations']]);
            fputcsv($out, ['Active', $data['activeViolations']]);
            fputcsv($out, []);

            fputcsv($out, ['Entries by Day (Last 7 Days)']);
            fputcsv($out, ['Date', 'Day', 'Count']);
            foreach ($data['entriesByDay'] as $day) {
                fputcsv($out, [$day['date'], $day['label'], $day['count']]);
            }
            fputcsv($out, []);

            fputcsv($out, ['Exits by Day (Last 7 Days)']);
            fputcsv($out, ['Date', 'Day', 'Count']);
            foreach ($data['exitsByDay'] as $day) {
                fputcsv($out, [$day['date'], $day['label'], $day['count']]);
            }
            fputcsv($out, []);

            fputcsv($out, ['Violations by Type']);
            fputcsv($out, ['Type', 'Count']);
            foreach ($data['violationsByType'] as $type => $count) {
                fputcsv($out, [$type, $count]);
            }
            fputcsv($out, []);

            fputcsv($out, ['Violations by Location']);
            fputcsv($out, ['Location', 'Count']);
            foreach ($data['violationsByLocation']['labels'] as $i => $label) {
                fputcsv($out, [$label, $data['violationsByLocation']['values'][$i] ?? 0]);
            }
            fputcsv($out, []);

            fputcsv($out, ['Repeat Offenders']);
            fputcsv($out, ['Rank', 'Name', 'ID', 'Type', 'Violations']);
            foreach ($data['repeatOffenders'] as $row) {
                fputcsv($out, [$row['rank'], $row['name'], $row['id_number'], $row['user_type'], $row['violations']]);
            }
            fputcsv($out, []);

            fputcsv($out, ['Recent Violations (last 50)']);
            fputcsv($out, ['Date', 'Violator', 'Plate', 'Type', 'Status']);
            ViolationLog::query()->orderByDesc('created_at')->limit(50)->get()->each(function ($log) use ($out): void {
                fputcsv($out, [
                    $log->created_at?->toDateTimeString() ?? '',
                    $log->violator_name ?? '',
                    $log->plate_number ?? '',
                    $log->violation_type ?? '',
                    $log->status ?? '',
                ]);
            });

            fputcsv($out, []);
            fputcsv($out, ['User Roster (non-admin)']);
            fputcsv($out, ['Name', 'Email', 'Role', 'ID Number', 'Status', 'Strikes']);
            User::query()->with('role')->whereHas('role', fn ($q) => $q->where('role_name', '!=', 'Admin'))
                ->orderBy('fullname')->get()->each(function (User $user) use ($out): void {
                    fputcsv($out, [
                        $user->fullname,
                        $user->email,
                        $user->role?->role_name ?? '',
                        $user->id_number,
                        $user->isLocked() ? 'Locked' : $user->status,
                        $user->strike_count,
                    ]);
                });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportPdf(Request $request, ReportExportService $exports): Response
    {
        $type = $exports->normalizeType($request->query('type'));
        $payload = $exports->build($type, $this->rangeFromRequest($request));

        $pdf = Pdf::loadView('admin.reports.pdf', $payload)->setPaper('a4', 'portrait');

        return $pdf->download($exports->fileSlug($type).'.pdf');
    }

    public function exportExcel(Request $request, ReportExportService $exports): Response
    {
        $type = $exports->normalizeType($request->query('type'));
        $payload = $exports->build($type, $this->rangeFromRequest($request));
        $binary = $exports->toXlsxBinary($payload);
        $filename = $exports->fileSlug($type).'.xlsx';

        return response($binary, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Content-Length' => (string) strlen($binary),
        ]);
    }

    /**
     * @return array{from: Carbon, to: Carbon}
     */
    private function rangeFromRequest(Request $request): array
    {
        $fromRaw = trim((string) $request->query('from', ''));
        $toRaw = trim((string) $request->query('to', ''));

        $to = $toRaw !== '' ? Carbon::parse($toRaw)->endOfDay() : Carbon::today()->endOfDay();
        $from = $fromRaw !== '' ? Carbon::parse($fromRaw)->startOfDay() : $to->copy()->subDays(29)->startOfDay();

        return ['from' => $from, 'to' => $to];
    }
}
