<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\GateLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class EntryExitController extends Controller
{
    public function index(Request $request): View
    {
        $user = Auth::user();
        $action = trim((string) $request->query('action', 'all'));
        if (! in_array($action, ['all', 'Entry', 'Exit'], true)) {
            $action = 'all';
        }

        $query = GateLog::query()
            ->where('user_id', $user->id)
            ->orderByDesc('timestamp');

        if ($action !== 'all') {
            $query->where('action', $action);
        }

        if ($from = $request->date('date_from')) {
            $query->where('timestamp', '>=', $from->startOfDay());
        }

        if ($to = $request->date('date_to')) {
            $query->where('timestamp', '<=', $to->endOfDay());
        }

        return view('user.entry-exit', [
            'logs' => $query->paginate(20)->withQueryString(),
            'actionFilter' => $action,
            'dateFrom' => $request->query('date_from', ''),
            'dateTo' => $request->query('date_to', ''),
        ]);
    }
}
