<?php

namespace App\Http\Controllers\Guard;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\NavigationService;
use App\Support\SearchHelper;
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

        $access = $request->query('access', 'all');
        if ($access && $access !== 'all') {
            if ($access === 'Granted') {
                $query->whereIn('Gate_access', [User::GATE_ACCESS_GRANTED, User::GATE_ACCESS_LEGACY]);
            } elseif ($access === 'Denied') {
                $query->where(function ($q) {
                    $q->where('Gate_access', User::GATE_ACCESS_DENIED)
                        ->orWhere('status', User::STATUS_DENIED);
                });
            } elseif ($access === 'Pending') {
                $query->where(function ($q) {
                    $q->where('Gate_access', User::GATE_ACCESS_PENDING)
                        ->orWhereNull('Gate_access')
                        ->orWhere('Gate_access', '');
                })->where(function ($q) {
                    $q->whereNull('status')
                        ->orWhere('status', '!=', User::STATUS_DENIED);
                })->where(function ($q) {
                    $q->whereNull('Gate_access')
                        ->orWhere('Gate_access', '!=', User::GATE_ACCESS_DENIED);
                });
            }
        }

        return view('guard.user-monitor', [
            'users' => $query->orderBy('fullname')->paginate(25)->withQueryString(),
            'search' => $search ?? '',
            'accessFilter' => $access ?? 'all',
        ]);
    }
}
