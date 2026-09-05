<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Support\CampusParkingPolicy;
use Illuminate\View\View;

class PolicyController extends Controller
{
    public function index(): View
    {
        return view('user.policy', [
            'reference' => CampusParkingPolicy::REFERENCE,
            'policyTitle' => CampusParkingPolicy::TITLE,
            'sections' => CampusParkingPolicy::sections(),
        ]);
    }
}
