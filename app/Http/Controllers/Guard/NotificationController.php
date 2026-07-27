<?php

namespace App\Http\Controllers\Guard;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Support\SearchHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class NotificationController extends Controller
{
    /** Notification types shown to guards (campus updates, not user citations). */
    private const GUARD_TYPES = ['System', 'General', 'Parking', 'Update'];

    public function index(Request $request): View
    {
        $guardId = Auth::id();

        $query = Notification::query()
            ->where('user_id', $guardId)
            ->whereIn('type', self::GUARD_TYPES)
            ->orderByDesc('created_at');

        $type = trim((string) $request->query('type', 'all'));
        $status = trim((string) $request->query('status', 'all'));
        $allowedStatuses = ['all', 'read', 'unread'];

        if (! in_array($type, array_merge(['all'], self::GUARD_TYPES), true)) {
            $type = 'all';
        }

        if (! in_array($status, $allowedStatuses, true)) {
            $status = 'all';
        }

        if ($type !== 'all') {
            $query->where('type', $type);
        }

        if ($status !== 'all') {
            if ($status === 'read') {
                $query->where('is_read', true);
            } elseif ($status === 'unread') {
                $query->unread();
            }
        }

        if ($search = trim((string) $request->query('q'))) {
            $term = SearchHelper::escapeLike($search);
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', "%{$term}%")
                    ->orWhere('message', 'like', "%{$term}%");
            });
        }

        if ($from = $request->date('date_from')) {
            $query->where('created_at', '>=', $from->startOfDay());
        }

        if ($to = $request->date('date_to')) {
            $query->where('created_at', '<=', $to->endOfDay());
        }

        $unreadCount = Notification::query()
            ->where('user_id', $guardId)
            ->whereIn('type', self::GUARD_TYPES)
            ->unread()
            ->count();

        return view('guard.notifications', [
            'notifications' => $query->paginate(25)->withQueryString(),
            'search' => $search ?? '',
            'typeFilter' => $type ?? 'all',
            'statusFilter' => $status ?? 'all',
            'dateFrom' => $request->query('date_from', ''),
            'dateTo' => $request->query('date_to', ''),
            'unreadCount' => $unreadCount,
        ]);
    }

    public function action(Request $request, string $action): RedirectResponse|JsonResponse
    {
        if (! in_array($action, ['mark_all_read', 'clear_all', 'mark_read'], true)) {
            abort(404);
        }

        $guardId = Auth::id();

        if ($action === 'mark_read') {
            $validated = $request->validate([
                'id' => ['required', 'integer'],
            ]);

            Notification::query()
                ->where('user_id', $guardId)
                ->whereIn('type', self::GUARD_TYPES)
                ->where('id', $validated['id'])
                ->update(['is_read' => true]);
        } elseif ($action === 'mark_all_read') {
            Notification::query()
                ->where('user_id', $guardId)
                ->whereIn('type', self::GUARD_TYPES)
                ->unread()
                ->update(['is_read' => true]);
        } elseif ($action === 'clear_all') {
            Notification::query()
                ->where('user_id', $guardId)
                ->whereIn('type', self::GUARD_TYPES)
                ->delete();
        }

        $unreadCount = Notification::query()
            ->where('user_id', $guardId)
            ->whereIn('type', self::GUARD_TYPES)
            ->unread()
            ->count();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'unread_count' => $unreadCount,
            ]);
        }

        return redirect()
            ->route('guard.notifications')
            ->with('success', $action === 'clear_all' ? 'All updates cleared.' : 'Updates marked as read.');
    }
}
