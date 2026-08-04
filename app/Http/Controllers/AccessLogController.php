<?php

namespace App\Http\Controllers;

use App\Models\GateLog;
use App\Models\User;
use App\Support\SearchHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccessLogController extends Controller
{
    public function index(Request $request): View
    {
        $isGuard = str_contains($request->route()->getName(), 'guard.');

        $search = trim((string) $request->query('q', ''));
        $typeFilter = trim((string) $request->query('type', 'all'));
        $direction = trim((string) $request->query('direction', $request->query('action', 'all')));
        $resultFilter = trim((string) $request->query('result', 'all'));

        $allowedTypes = ['all', 'Student', 'Staff', 'Visitor'];
        if (! in_array($typeFilter, $allowedTypes, true)) {
            $typeFilter = 'all';
        }

        $allowedDirections = ['all', 'Entry', 'Exit'];
        if (! in_array($direction, $allowedDirections, true)) {
            $direction = 'all';
        }

        $allowedResults = ['all', 'Granted', 'Denied'];
        if (! in_array($resultFilter, $allowedResults, true)) {
            $resultFilter = 'all';
        }

        $baseStats = GateLog::query();
        $this->applyFilters(
            $baseStats,
            $search,
            $typeFilter,
            $direction,
            $resultFilter,
            $request->date('date_from'),
            $request->date('date_to')
        );

        $grantedScope = function (Builder $q) {
            $q->whereNull('result')
                ->orWhere('result', '')
                ->orWhereIn('result', ['Access Granted', 'Granted']);
        };

        $deniedScope = function (Builder $q) {
            $q->whereNotNull('result')
                ->where('result', '!=', '')
                ->whereNotIn('result', ['Access Granted', 'Granted']);
        };

        $stats = [
            'total' => (clone $baseStats)->count(),
            'entries_granted' => (clone $baseStats)->where('action', 'Entry')->where($grantedScope)->count(),
            'exits_granted' => (clone $baseStats)->where('action', 'Exit')->where($grantedScope)->count(),
            'access_denied' => (clone $baseStats)->where($deniedScope)->count(),
        ];

        $logs = (clone $baseStats)
            ->with(['user.role'])
            ->orderByDesc('timestamp')
            ->paginate(30)
            ->withQueryString();

        $recentDenied = GateLog::query()
            ->with(['user.role'])
            ->where($deniedScope)
            ->orderByDesc('timestamp')
            ->limit(5)
            ->get();

        return view($isGuard ? 'guard.access-logs' : 'admin.access-logs', [
            'logs' => $logs,
            'stats' => $stats,
            'recentDenied' => $recentDenied,
            'search' => $search,
            'typeFilter' => $typeFilter,
            'actionFilter' => $direction,
            'directionFilter' => $direction,
            'resultFilter' => $resultFilter,
            'dateFrom' => $request->query('date_from', ''),
            'dateTo' => $request->query('date_to', ''),
            'clearRoute' => $isGuard ? route('guard.access-logs') : route('admin.access-logs'),
            'eventsRoute' => $isGuard ? route('guard.access-logs.events') : route('admin.access-logs.events'),
        ]);
    }

        /**
     * AJAX search/filter for Access Logs (not continuous polling).
     */
    public function events(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('q', ''));
        $typeFilter = trim((string) $request->query('type', 'all'));
        $direction = trim((string) $request->query('direction', $request->query('action', 'all')));
        $resultFilter = trim((string) $request->query('result', 'all'));

        $allowedTypes = ['all', 'Student', 'Staff', 'Visitor'];
        if (! in_array($typeFilter, $allowedTypes, true)) {
            $typeFilter = 'all';
        }

        $allowedDirections = ['all', 'Entry', 'Exit'];
        if (! in_array($direction, $allowedDirections, true)) {
            $direction = 'all';
        }

        $allowedResults = ['all', 'Granted', 'Denied'];
        if (! in_array($resultFilter, $allowedResults, true)) {
            $resultFilter = 'all';
        }

        $baseStats = GateLog::query();
        $this->applyFilters(
            $baseStats,
            $search,
            $typeFilter,
            $direction,
            $resultFilter,
            $request->date('date_from'),
            $request->date('date_to')
        );

        $grantedScope = function (Builder $q) {
            $q->whereNull('result')
                ->orWhere('result', '')
                ->orWhereIn('result', ['Access Granted', 'Granted']);
        };

        $deniedScope = function (Builder $q) {
            $q->whereNotNull('result')
                ->where('result', '!=', '')
                ->whereNotIn('result', ['Access Granted', 'Granted']);
        };

        $total = (clone $baseStats)->count();
        $stats = [
            'total' => $total,
            'entries_granted' => (clone $baseStats)->where('action', 'Entry')->where($grantedScope)->count(),
            'exits_granted' => (clone $baseStats)->where('action', 'Exit')->where($grantedScope)->count(),
            'access_denied' => (clone $baseStats)->where($deniedScope)->count(),
        ];

        $logs = (clone $baseStats)
            ->with(['user.role'])
            ->orderByDesc('timestamp')
            ->limit(30)
            ->get()
            ->map(fn (GateLog $log) => $this->serializeAccessLog($log))
            ->values();

        $newest = $logs->first();

        $recentDenied = GateLog::query()
            ->with(['user.role'])
            ->where($deniedScope)
            ->orderByDesc('timestamp')
            ->limit(5)
            ->get()
            ->map(fn (GateLog $log) => $this->serializeAccessLog($log))
            ->values();

        return response()->json([
            'newest_id' => $newest['id'] ?? null,
            'total' => $total,
            'stats' => $stats,
            'logs' => $logs,
            'recent_denied' => $recentDenied,
            'updated_at' => now()->format('h:i:s A'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeAccessLog(GateLog $log): array
    {
        $user = $log->user;
        $granted = $log->accessGranted();

        return [
            'id' => (string) $log->getKey(),
            'timestamp' => $log->timestamp ? ph_datetime($log->timestamp, 'n/j/Y, g:i:s A') : '—',
            'name' => $user?->displayName() ?? 'Unknown',
            'id_number' => $user?->id_number ?? ($user?->id ?? '—'),
            'role' => $user?->roleName() ?? '—',
            'rfid_uid' => $log->displayRfid(),
            'action' => $log->action,
            'gate' => $log->displayGate(),
            'granted' => $granted,
            'result_label' => $log->displayResultLabel(),
            'reason' => $log->displayReason(),
        ];
    }

    private function applyFilters(
        Builder $query,
        string $search,
        string $typeFilter,
        string $direction,
        string $resultFilter,
        mixed $from,
        mixed $to
    ): void {
        if ($direction !== 'all') {
            $query->where('action', $direction);
        }

        if ($resultFilter === 'Granted') {
            $query->where(function (Builder $q) {
                $q->whereNull('result')
                    ->orWhere('result', '')
                    ->orWhereIn('result', ['Access Granted', 'Granted']);
            });
        } elseif ($resultFilter === 'Denied') {
            $query->whereNotNull('result')
                ->where('result', '!=', '')
                ->whereNotIn('result', ['Access Granted', 'Granted']);
        }

        if ($typeFilter !== 'all') {
            $query->whereHas('user.role', function (Builder $q) use ($typeFilter) {
                $q->where('role_name', $typeFilter);
            });
        }

        if ($search !== '') {
            $term = SearchHelper::escapeLike($search);
            // Case-insensitive partial match against DB user name field(s)
            $nameRegex = new \MongoDB\BSON\Regex(preg_quote($search, '/'), 'i');

            // Resolve matching users first so name search is based on the users collection
            $matchedUserIds = User::query()
                ->where(function (Builder $userQuery) use ($term, $nameRegex) {
                    $userQuery->where('fullname', 'regex', $nameRegex)
                        ->orWhere('Name', 'regex', $nameRegex)
                        ->orWhere('name', 'regex', $nameRegex)
                        ->orWhere('plate_number', 'like', "%{$term}%")
                        ->orWhere('id_number', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")
                        ->orWhere('rfid_uid', 'like', "%{$term}%");
                })
                ->pluck('id')
                ->all();

            $query->where(function (Builder $q) use ($term, $matchedUserIds) {
                $q->where('rfid_uid', 'like', "%{$term}%")
                    ->orWhere('gate_id', 'like', "%{$term}%");

                if ($matchedUserIds !== []) {
                    $q->orWhereIn('user_id', $matchedUserIds);
                }

                $q->orWhereHas('user.role', function (Builder $roleQuery) use ($term) {
                    $roleQuery->where('role_name', 'like', "%{$term}%");
                });
            });
        }

        if ($from) {
            $query->where('timestamp', '>=', $from->startOfDay());
        }

        if ($to) {
            $query->where('timestamp', '<=', $to->endOfDay());
        }
    }
}
