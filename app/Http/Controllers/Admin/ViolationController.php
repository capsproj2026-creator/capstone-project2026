<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SanctionEndorsement;
use App\Models\User;
use App\Models\ViolationLog;
use App\Models\ViolationType;
use App\Support\OffenseStatus;
use App\Support\ParkingSanctions;
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

        $suspendedIds = User::query()
            ->whereIn('user_role_id', [3, 4])
            ->where(function ($q) {
                $sanctioned = ParkingSanctions::activeUserIds();
                $q->where('status', User::STATUS_LOCKED)
                    ->orWhereIn('id', $sanctioned !== [] ? $sanctioned : [-1]);
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($riskFilter === 'second') {
            $userIds = User::query()
                ->where('strike_count', 2)
                ->whereIn('user_role_id', [3, 4])
                ->pluck('id')
                ->all();
            $query->whereIn('user_id', $userIds !== [] ? $userIds : [-1]);
        } elseif ($riskFilter === 'suspended') {
            $query->whereIn('user_id', $suspendedIds !== [] ? $suspendedIds : [-1]);
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
        $suspendedUsers = count($suspendedIds);
        $pendingEndorsements = SanctionEndorsement::query()
            ->whereIn('status', SanctionEndorsement::OPEN_STATUSES)
            ->count();

        $strikeOverviewQuery = User::query()
            ->whereIn('user_role_id', [3, 4])
            ->where('strike_count', '>=', 1);

        $strikeOverviewCount = (clone $strikeOverviewQuery)->count();

        $strikeOverview = (clone $strikeOverviewQuery)
            ->orderByDesc('strike_count')
            ->orderBy('name')
            ->limit(3)
            ->get();

        $typeCounts = collect();
        foreach ($violationTypes as $typeName) {
            $typeCounts[$typeName] = ViolationLog::query()
                ->where('violation_type', $typeName)
                ->count();
        }
        $typeCounts = $typeCounts->filter(fn ($count) => $count > 0)->sortDesc();

        $offenseStatuses = OffenseStatus::forUsers(
            $logs->getCollection()->pluck('user')->filter()->concat($strikeOverview)
        );

        return view('admin.violations', [
            'offenseStatuses' => $offenseStatuses,
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
                'pending_endorsements' => $pendingEndorsements,
            ],
            'strikeOverview' => $strikeOverview,
            'strikeOverviewCount' => $strikeOverviewCount,
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

    public function evidence(string $id, int $index = 0): \Symfony\Component\HttpFoundation\Response
    {
        $log = \App\Support\ViolationEvidence::findAuthorized($id);

        return \App\Support\PrivateEvidence::response(\App\Support\ViolationEvidence::pathAt($log, $index));
    }
}
