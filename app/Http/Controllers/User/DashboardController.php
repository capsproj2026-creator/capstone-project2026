<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\GeneralInformation;
use App\Models\ParkingRule;
use App\Services\DashboardStatsService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(DashboardStatsService $stats): View
    {
        $user = Auth::user()->load(['role', 'vehicleType']);

        return view('user.dashboard', array_merge(
            $stats->userStats($user),
            [
                'user' => $user,
                'parkingRules' => ParkingRule::query()
                    ->orderBy('id')
                    ->get()
                    ->filter(fn (ParkingRule $rule) => $rule->isActive())
                    ->values(),
                'generalInfo' => GeneralInformation::query()->orderBy('id')->get(),
            ]
        ));
    }
}
