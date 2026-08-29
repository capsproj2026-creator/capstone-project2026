<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GateLog;
use App\Models\User;
use App\Support\SearchHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class UserManagementController extends Controller
{
    public function index(Request $request): View
    {
        $typeFilter = $request->query('type', 'All');
        $allowedTypes = ['All', 'Student', 'Staff', 'Guard'];
        if (! in_array($typeFilter, $allowedTypes, true)) {
            $typeFilter = 'All';
        }

        $statusFilter = $request->query('status', 'All');
        $allowedStatuses = ['All', User::STATUS_GRANTED, User::STATUS_PENDING, User::STATUS_DENIED, 'Locked'];
        if (! in_array($statusFilter, $allowedStatuses, true)) {
            $statusFilter = 'All';
        }

        $sort = $request->query('sort', 'fullname');
        $allowedSorts = ['fullname', 'id_number', 'strike_count', 'status'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'fullname';
        }

        $direction = strtolower($request->query('direction', 'asc')) === 'desc' ? 'desc' : 'asc';
        $search = trim($request->query('search', ''));

        $query = User::query()
            ->with('role')
            ->whereHas('role', fn ($q) => $q->where('role_name', '!=', 'Admin'));

        if ($typeFilter !== 'All') {
            $query->whereHas('role', fn ($q) => $q->where('role_name', $typeFilter));
        }

        if ($statusFilter === 'Locked') {
            $query->where(function ($q) {
                $q->where('status', User::STATUS_LOCKED)
                    ->orWhere('strike_count', '>=', User::MAX_STRIKES);
            });
        } elseif ($statusFilter !== 'All') {
            $query->where('status', $statusFilter);
        }

        if ($search !== '') {
            $term = SearchHelper::escapeLike($search);
            $query->where(function ($q) use ($term) {
                $q->where('fullname', 'like', "%{$term}%")
                    ->orWhere('id_number', 'like', "%{$term}%")
                    ->orWhere('plate_number', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%");
            });
        }

        $users = $query->orderBy($sort, $direction)->paginate(15)->withQueryString();

        return view('admin.user-management', [
            'users' => $users,
            'typeFilter' => $typeFilter,
            'statusFilter' => $statusFilter,
            'sort' => $sort,
            'direction' => $direction,
            'search' => $search,
            'totalUsers' => User::query()->whereHas('role', fn ($q) => $q->where('role_name', '!=', 'Admin'))->count(),
            'studentCount' => User::query()
                ->whereHas('role', fn ($q) => $q->where('role_name', 'Student'))
                ->where(function ($q) {
                    $q->whereNull('account_type')
                        ->orWhere('account_type', '!=', \App\Services\TemporaryRfidService::ACCOUNT_TEMPORARY);
                })
                ->count(),
            'staffCount' => User::query()->whereHas('role', fn ($q) => $q->where('role_name', 'Staff'))->count(),
            'guardCount' => User::query()->whereHas('role', fn ($q) => $q->where('role_name', 'Guard'))->count(),
        ]);
    }

    public function show(Request $request, int $id): View
    {
        $from = $request->query('from', 'users');
        $backUrl = $from === 'registrations'
            ? route('admin.registrations', ['status' => User::STATUS_PENDING])
            : route('admin.users');
        $backLabel = $from === 'registrations'
            ? 'Back to Registrations'
            : 'Back to User Management';

        $user = User::query()->with(['role', 'department', 'vehicleType'])->findOrFail($id);

        return view('admin.view-user', [
            'user' => $user,
            'recentGateLogs' => GateLog::query()
                ->where('user_id', $user->id)
                ->orderByDesc('timestamp')
                ->limit(10)
                ->get(),
            'backUrl' => $backUrl,
            'backLabel' => $backLabel,
        ]);
    }

    /**
     * Stream a registration document only to authenticated admins (not publicly linkable).
     */
    public function document(int $id, string $doc): BinaryFileResponse
    {
        abort_unless(in_array($doc, ['license', 'orcr', 'or', 'cr', 'id'], true), 404);

        $user = User::query()->findOrFail($id);

        [$field, $directory] = match ($doc) {
            'orcr' => ['or_cr_photo', 'uploads/documents/orcr'],
            'or' => ['lto_or_photo', 'uploads/documents/orcr'],
            'cr' => ['lto_cr_photo', 'uploads/documents/cr'],
            'id' => ['id_document', 'uploads/documents/id'],
            default => ['driver_license', 'uploads/documents/license'],
        };

        $absolute = $user->resolveDocumentAbsolutePath($field, $directory);
        if ($absolute === null && $doc === 'or') {
            $absolute = $user->resolveDocumentAbsolutePath('or_cr_photo', 'uploads/documents/orcr');
        }

        abort_if($absolute === null || ! is_file($absolute), 404);

        return Response::file($absolute, [
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
