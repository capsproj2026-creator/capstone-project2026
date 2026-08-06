<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\ViolationLog;
use App\Support\PrivateEvidence;
use App\Support\ViolationEvidence;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ViolationController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();

        $logs = ViolationLog::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('user.violations', [
            'user' => $user->load('role'),
            'logs' => $logs,
            'strikeCount' => (int) ($user->strike_count ?? 0),
            'maxStrikes' => \App\Models\User::MAX_STRIKES,
        ]);
    }

    public function evidence(string $id, int $index = 0): \Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\Response
    {
        $log = ViolationEvidence::findAuthorized($id);
        $user = Auth::user();

        if ((int) $log->user_id !== (int) $user->id) {
            abort(403);
        }

        return PrivateEvidence::response(ViolationEvidence::pathAt($log, $index));
    }
}
