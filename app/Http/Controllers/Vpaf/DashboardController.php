<?php

namespace App\Http\Controllers\Vpaf;

use App\Http\Controllers\Controller;
use App\Models\SanctionEndorsement;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $pending = SanctionEndorsement::query()
            ->with('user')
            ->where('status', SanctionEndorsement::STATUS_PENDING_VPAF)
            ->orderBy('created_at')
            ->get();

        $decided = SanctionEndorsement::query()
            ->where('offense_level', '>=', 3)
            ->whereIn('status', [
                SanctionEndorsement::STATUS_APPROVED,
                SanctionEndorsement::STATUS_REJECTED,
            ])
            ->count();

        return view('vpaf.dashboard', [
            'pending' => $pending,
            'pendingCount' => $pending->count(),
            'decidedCount' => $decided,
        ]);
    }
}
