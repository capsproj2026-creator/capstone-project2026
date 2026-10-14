<?php

namespace App\Http\Controllers\Vpaf;

use App\Http\Controllers\Controller;
use App\Models\SanctionEndorsement;
use App\Services\ViolationEnforcementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class EndorsementController extends Controller
{
    public function index(): View
    {
        return view('vpaf.endorsements', [
            'pending' => SanctionEndorsement::query()
                ->with('user')
                ->where('status', SanctionEndorsement::STATUS_PENDING_VPAF)
                ->orderBy('created_at')
                ->get(),
            'recent' => SanctionEndorsement::query()
                ->with('user')
                ->where('offense_level', '>=', 3)
                ->whereIn('status', [
                    SanctionEndorsement::STATUS_APPROVED,
                    SanctionEndorsement::STATUS_REJECTED,
                ])
                ->orderByDesc('closed_at')
                ->limit(15)
                ->get(),
        ]);
    }

    public function decide(Request $request, int $id, ViolationEnforcementService $enforcement): RedirectResponse
    {
        $validated = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $endorsement = SanctionEndorsement::query()->with('user')->findOrFail($id);

        try {
            $enforcement->vpafDecideThirdOffense(
                $endorsement,
                $validated['decision'] === 'approve',
                (int) $request->user()->id,
                filled($validated['note'] ?? null) ? trim($validated['note']) : null,
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $word = $validated['decision'] === 'approve' ? 'approved' : 'rejected';

        return redirect()
            ->route('vpaf.endorsements')
            ->with('success', "Endorsement {$word}.");
    }
}
