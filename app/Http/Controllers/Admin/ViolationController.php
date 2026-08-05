<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\ViolationLog;
use App\Models\ViolationType;
use App\Support\SearchHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ViolationController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $statusFilter = (string) $request->query('status', 'all');
        $typeFilter = trim((string) $request->query('type', 'all'));
        $riskFilter = trim((string) $request->query('risk', 'all'));

        $query = ViolationLog::query()
            ->with('user')
            ->orderByDesc('created_at');

        if ($search !== '') {
            $term = SearchHelper::escapeLike($search);
            $query->where(function ($q) use ($term) {
                $q->where('plate_number', 'like', "%{$term}%")
                    ->orWhere('violator_name', 'like', "%{$term}%")
                    ->orWhere('violation_type', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%")
                    ->orWhere('id_number', 'like', "%{$term}%");
            });
        }

        if (in_array($statusFilter, ['Active', 'Resolved'], true)) {
            $query->where('status', $statusFilter);
        } else {
            $statusFilter = 'all';
        }

        $violationTypes = ViolationType::query()
            ->where('status', 'Active')
            ->orderBy('id')
            ->pluck('violation_name');

        if ($typeFilter !== 'all' && $typeFilter !== '') {
            $query->where('violation_type', $typeFilter);
        } else {
            $typeFilter = 'all';
        }

        if ($riskFilter === 'second') {
            $userIds = User::query()
                ->where('strike_count', 2)
                ->whereIn('user_role_id', [3, 4])
                ->pluck('id')
                ->all();
            $query->whereIn('user_id', $userIds !== [] ? $userIds : [-1]);
        } elseif ($riskFilter === 'suspended') {
            $userIds = User::query()
                ->whereIn('user_role_id', [3, 4])
                ->where(function ($q) {
                    $q->where('status', User::STATUS_LOCKED)
                        ->orWhere('strike_count', '>=', User::MAX_STRIKES);
                })
                ->pluck('id')
                ->all();
            $query->whereIn('user_id', $userIds !== [] ? $userIds : [-1]);
        } else {
            $riskFilter = 'all';
        }

        $logs = $query->paginate(25)->withQueryString();

        $guardNames = $this->resolveGuardNames($logs->getCollection());

        $totalViolations = ViolationLog::query()->count();
        $usersAtSecondStrike = User::query()
            ->where('strike_count', 2)
            ->whereIn('user_role_id', [3, 4])
            ->count();
        $suspendedUsers = User::query()
            ->whereIn('user_role_id', [3, 4])
            ->where(function ($q) {
                $q->where('status', User::STATUS_LOCKED)
                    ->orWhere('strike_count', '>=', User::MAX_STRIKES);
            })
            ->count();

        $strikeOverview = User::query()
            ->whereIn('user_role_id', [3, 4])
            ->where('strike_count', '>=', 1)
            ->orderByDesc('strike_count')
            ->orderBy('fullname')
            ->limit(12)
            ->get(['id', 'fullname', 'strike_count', 'status', 'plate_number', 'id_number']);

        $typeCounts = collect();
        foreach ($violationTypes as $typeName) {
            $typeCounts[$typeName] = ViolationLog::query()
                ->where('violation_type', $typeName)
                ->count();
        }
        $typeCounts = $typeCounts->filter(fn ($count) => $count > 0)->sortDesc();

        return view('admin.violations', [
            'logs' => $logs,
            'search' => $search,
            'statusFilter' => $statusFilter,
            'typeFilter' => $typeFilter,
            'riskFilter' => $riskFilter,
            'violationTypes' => $violationTypes,
            'guardNames' => $guardNames,
            'stats' => [
                'total' => $totalViolations,
                'second_strike' => $usersAtSecondStrike,
                'suspended' => $suspendedUsers,
            ],
            'strikeOverview' => $strikeOverview,
            'typeCounts' => $typeCounts,
        ]);
    }

    /**
     * @param  Collection<int, ViolationLog>  $logs
     * @return array<string, string>
     */
    private function resolveGuardNames(Collection $logs): array
    {
        $ids = $logs
            ->pluck('guard_id')
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return [];
        }

        return User::query()
            ->whereIn('id', $ids)
            ->get(['id', 'fullname'])
            ->mapWithKeys(fn (User $user) => [(string) $user->id => $user->fullname])
            ->all();
    }

    public function evidence(string $id): \Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\Response
    {
        $log = ViolationLog::query()->findOrFail($id);

        return \App\Support\PrivateEvidence::response($log->evidence_photo ?? null);
    }
}
