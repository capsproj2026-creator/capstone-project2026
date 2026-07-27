<?php

namespace App\Http\Controllers\Guard;

use App\Http\Controllers\Controller;
use App\Models\GateLog;
use App\Services\GateLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use InvalidArgumentException;

class GateMonitorController extends Controller
{
    public function index(Request $request): View
    {
        $action = $this->actionFromRequest($request);
        $logs = $this->filteredLogs($action);

        return view('guard.gate-monitor', [
            'recentLogs' => $logs,
            'latestLog' => $logs->first(),
            'todayEntries' => app(GateLogService::class)->todayCount('Entry'),
            'todayExits' => app(GateLogService::class)->todayCount('Exit'),
            'filterAction' => $action,
        ]);
    }

    public function events(Request $request, GateLogService $gateLogs): JsonResponse
    {
        $action = $this->actionFromRequest($request);

        $latestLog = $this->filteredLogs($action, 1)->first();

        $logs = $this->filteredLogs($action)
            ->map(fn (GateLog $log): array => $this->serializeLog($log))
            ->values();

        return response()->json([
            'logs' => $logs,
            'latest' => $latestLog ? $this->serializeLog($latestLog) : null,
            'newest_id' => $latestLog ? (string) $latestLog->getKey() : null,
            'filters' => [
                'action' => $action,
            ],
            'today_entries' => $gateLogs->todayCount('Entry'),
            'today_exits' => $gateLogs->todayCount('Exit'),
            'updated_at' => now()->format('h:i:s A'),
            'server_time' => now()->format('g:i:s A'),
        ]);
    }

    public function scan(Request $request, GateLogService $gateLogs): RedirectResponse
    {
        $validated = $request->validate([
            'plate_number' => ['required', 'string', 'max:32'],
            'action' => ['nullable', 'in:Entry,Exit'],
            'return_action' => ['nullable', 'in:Entry,Exit'],
        ]);

        $returnQuery = array_filter([
            'action' => $validated['return_action'] ?? null,
        ], fn ($value) => filled($value));

        try {
            $result = $gateLogs->recordByPlate(
                $validated['plate_number'],
                $validated['action'] ?? null
            );
        } catch (InvalidArgumentException $e) {
            report($e);

            return redirect()
                ->route('guard.gate', $returnQuery)
                ->with('error', 'Unable to record gate scan. Please verify the plate number and try again.')
                ->withInput();
        }

        $user = $result['user'];

        return redirect()
            ->route('guard.gate', $returnQuery)
            ->with(
                'success',
                "{$result['action']} recorded for {$user->displayName()} ({$user->plate_number})."
            );
    }

    private function actionFromRequest(Request $request): string
    {
        $action = trim((string) $request->query('action', ''));

        return in_array($action, ['Entry', 'Exit'], true) ? $action : '';
    }

    /**
     * @return Collection<int, GateLog>
     */
    private function filteredLogs(string $action, int $limit = 50): Collection
    {
        $query = GateLog::query()
            ->with(['user.role'])
            ->orderByDesc('timestamp');

        if ($action !== '') {
            $query->where('action', $action);
        }

        return $query->limit($limit)->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeLog(GateLog $log): array
    {
        $uid = trim((string) ($log->rfid_uid ?? ''));
        $user = $log->user;
        $name = $user?->displayName() ?? 'Unknown card';
        $granted = $log->accessGranted();
        $strikes = (int) ($user?->strike_count ?? 0);

        $initials = strtoupper(
            collect(explode(' ', $name))
                ->filter()
                ->map(fn ($w) => mb_substr($w, 0, 1))
                ->take(2)
                ->join('') ?: 'U'
        );

        $statusLabel = $granted
            ? ($strikes > 0 ? "{$strikes} Strike".($strikes === 1 ? '' : 's') : 'No Violations')
            : ($log->result ?: 'Access Denied');

        return [
            'id' => (string) $log->getKey(),
            'log_number' => $log->daily_log_id ?? (string) $log->getKey(),
            'name' => $name,
            'initials' => $initials,
            'profile_picture_url' => $user
                ? $user->profilePictureUrl()
                : 'https://ui-avatars.com/api/?name='.urlencode($name).'&background=64748b&color=fff&size=256',
            'role' => $user?->roleName() ?? 'Unknown',
            'id_number' => $user?->id_number,
            'plate_number' => $user?->plate_number,
            'action' => $log->action,
            'result' => $log->result ?? 'Access Granted',
            'granted' => $granted,
            'gate_id' => $log->gate_id,
            'gate_label' => $log->displayGate(),
            'rfid_uid' => $uid === '' ? null : '••••'.substr($uid, -4),
            'rfid_uid_full' => $log->displayRfid(),
            'reason' => $log->displayReason(),
            'status_label' => $statusLabel,
            'strike_count' => $strikes,
            'time' => $log->timestamp?->timezone(config('app.timezone'))->format('h:i A'),
            'scanned_at' => $log->timestamp
                ? $log->timestamp->timezone(config('app.timezone'))->toIso8601String()
                : null,
            'timestamp' => $log->timestamp
                ? ph_datetime($log->timestamp, 'M j, Y · g:i:s A')
                : null,
        ];
    }
}
