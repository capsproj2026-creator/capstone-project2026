<?php

namespace App\Http\Controllers\Guard;

use App\Http\Controllers\Controller;
use App\Models\GateLog;
use App\Services\GateLogService;
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
}
