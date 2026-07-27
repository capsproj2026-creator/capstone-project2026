<?php

namespace App\Http\Controllers\Guard;

use App\Http\Controllers\Controller;
use App\Services\DashboardStatsService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(DashboardStatsService $stats): View
    {
        return view('guard.dashboard', $stats->guardStats());
    }
}
