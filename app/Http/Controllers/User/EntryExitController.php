<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\GateLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class EntryExitController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();

        return view('user.entry-exit', [
            'logs' => GateLog::query()
                ->where('user_id', $user->id)
                ->orderByDesc('timestamp')
                ->paginate(20),
        ]);
    }
}
