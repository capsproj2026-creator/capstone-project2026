<?php

namespace App\Http\Controllers\Guard;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\SearchHelper;
use App\Services\NavigationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserMonitorController extends Controller
{
    public function index(Request $request): View
    {
        $query = User::query()
            ->with('role')
            ->whereIn('user_role_id', [
                NavigationService::ROLE_STUDENT,
                NavigationService::ROLE_STAFF,
            ]);

        if ($search = trim((string) $request->query('q'))) {
            $term = SearchHelper::escapeLike($search);
            $query->where(function ($q) use ($term) {
                $q->where('fullname', 'like', "%{$term}%")
                    ->orWhere('plate_number', 'like', "%{$term}%")
                    ->orWhere('id_number', 'like', "%{$term}%");
            });
        }

        if (($access = $request->query('access')) && $access !== 'all') {
            $allowedAccess = ['Granted', 'Denied', 'Pending', User::GATE_ACCESS_LEGACY];
            if (in_array($access, $allowedAccess, true)) {
                if ($access === 'Granted') {
                    $query->whereIn('Gate_access', [User::GATE_ACCESS_GRANTED, User::GATE_ACCESS_LEGACY]);
                } else {
                    $query->where('Gate_access', $access);
                }
            }
        }

        return view('guard.user-monitor', [
            'users' => $query->orderBy('fullname')->paginate(25)->withQueryString(),
            'search' => $search ?? '',
            'accessFilter' => $access ?? 'all',
        ]);
    }
}
