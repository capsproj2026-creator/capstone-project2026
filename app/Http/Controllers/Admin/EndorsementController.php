<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SanctionEndorsement;
use App\Models\User;
use App\Models\ViolationLog;
use App\Services\ViolationEnforcementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class EndorsementController extends Controller
{
    private const TABS = ['gsu', 'vpaf', 'decided'];

    public function index(Request $request): View
    {
        $tab = (string) $request->query('tab', 'gsu');
        if (! in_array($tab, self::TABS, true)) {
            $tab = 'gsu';
        }

        $statusesFor = [
            'gsu' => [SanctionEndorsement::STATUS_PENDING_GSU],
            'vpaf' => [SanctionEndorsement::STATUS_PENDING_VPAF],
            'decided' => [
                SanctionEndorsement::STATUS_APPROVED,
                SanctionEndorsement::STATUS_REJECTED,
                SanctionEndorsement::STATUS_WITHDRAWN,
            ],
        ];

        $counts = [];
        foreach ($statusesFor as $key => $statuses) {
            $counts[$key] = SanctionEndorsement::query()->whereIn('status', $statuses)->count();
        }

        $endorsements = SanctionEndorsement::query()
            ->with('user')
            ->whereIn('status', $statusesFor[$tab])
            ->orderBy('created_at', $tab === 'decided' ? 'desc' : 'asc')
            ->paginate(20)
            ->withQueryString();

        $userIds = $endorsements->getCollection()->pluck('user_id')->filter()->unique()->values()->all();
        $citations = $userIds === []
            ? collect()
            : ViolationLog::query()
                ->whereIn('user_id', $userIds)
                ->orderBy('created_at')
                ->get(['user_id', 'violation_type', 'created_at', 'plate_number'])
                ->groupBy(fn (ViolationLog $log) => (int) $log->user_id);

        $reviewerIds = $endorsements->getCollection()
            ->flatMap(fn (SanctionEndorsement $e) => [$e->gsu_reviewed_by, $e->vpaf_reviewed_by])
            ->filter()
            ->unique()
            ->values()
            ->all();
        $reviewers = $reviewerIds === []
            ? []
            : User::query()->whereIn('id', $reviewerIds)->get(['id', 'fullname'])
                ->mapWithKeys(fn (User $u) => [(int) $u->id => $u->fullname])
                ->all();

        return view('admin.endorsements', [
            'tab' => $tab,
            'counts' => $counts,
            'endorsements' => $endorsements,
            'citations' => $citations,
            'reviewers' => $reviewers,
            'endorsementsEnabled' => app(ViolationEnforcementService::class)->endorsementsEnabled(),
        ]);
    }

    public function decide(Request $request, int $id, ViolationEnforcementService $enforcement): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject,vpaf_approve,vpaf_reject'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $endorsement = SanctionEndorsement::query()->findOrFail($id);
        $reviewerId = (int) $request->user()->id;
        $remarks = filled($data['remarks'] ?? null) ? trim($data['remarks']) : null;
        $level = (int) $endorsement->offense_level;

        try {
            $message = match (true) {
                $level === 2 && in_array($data['decision'], ['approve', 'reject'], true) => $this->secondOffense($enforcement, $endorsement, $data['decision'] === 'approve', $reviewerId, $remarks),
                $level === 3 && in_array($data['decision'], ['approve', 'reject'], true) => $this->thirdOffenseGsu($enforcement, $endorsement, $data['decision'] === 'approve', $reviewerId, $remarks),
                $level === 3 => $this->thirdOffenseVpaf($enforcement, $endorsement, $data['decision'] === 'vpaf_approve', $reviewerId, $remarks),
                default => throw new InvalidArgumentException('That decision does not apply to this endorsement.'),
            };
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $message);
    }

    private function secondOffense(ViolationEnforcementService $s, SanctionEndorsement $e, bool $approve, int $reviewerId, ?string $remarks): string
    {
        $s->gsuDecideSecondOffense($e, $approve, $reviewerId, $remarks);

        return $approve
            ? 'Approved. The parking permit is suspended for six months.'
            : 'Marked as not approved. No suspension was applied.';
    }

    private function thirdOffenseGsu(ViolationEnforcementService $s, SanctionEndorsement $e, bool $approve, int $reviewerId, ?string $remarks): string
    {
        $s->gsuDecideThirdOffense($e, $approve, $reviewerId, $remarks);

        return $approve
            ? 'Verified and endorsed to the VPAF. Record the VPAF decision under "Awaiting VPAF".'
            : 'Marked as not endorsed. Parking privileges stay in place.';
    }

    private function thirdOffenseVpaf(ViolationEnforcementService $s, SanctionEndorsement $e, bool $approve, int $reviewerId, ?string $remarks): string
    {
        $s->vpafDecideThirdOffense($e, $approve, $reviewerId, $remarks);

        return $approve
            ? 'VPAF approval recorded. Parking privileges are revoked.'
            : 'VPAF decision recorded. Revocation was not approved.';
    }
}
