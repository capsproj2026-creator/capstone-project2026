<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\RegisteredPlateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RegisteredPlatesController extends Controller
{
    public function index(Request $request, RegisteredPlateService $plates): View
    {
        $search = trim((string) $request->query('q', ''));

        return view('admin.registered-plates', [
            'plates' => $plates->allForMonitor($search !== '' ? $search : null),
            'search' => $search,
        ]);
    }

    public function rebuild(RegisteredPlateService $plates): RedirectResponse
    {
        $count = $plates->rebuild();

        return back()->with('success', "Rebuilt scan table with {$count} plate(s).");
    }
}
