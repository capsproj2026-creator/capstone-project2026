<?php

namespace App\Http\Controllers\User;

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
    public function index(Request $request): View
    {
        $user = Auth::user();

        $query = Notification::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at');

        $type = trim((string) $request->query('type', 'all'));
        $status = trim((string) $request->query('status', 'all'));
        $allowedTypes = ['all', 'System', 'General', 'Parking', 'Update', 'Violation'];
        $allowedStatuses = ['all', 'read', 'unread'];

        if (! in_array($type, $allowedTypes, true)) {
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

        return view('user.notifications', [
            'user' => $user->load('role'),
            'notifications' => $query->paginate(20)->withQueryString(),
            'search' => $search ?? '',
            'typeFilter' => $type ?? 'all',
            'statusFilter' => $status ?? 'all',
            'unreadCount' => Notification::query()->where('user_id', $user->id)->unread()->count(),
        ]);
    }

    public function action(Request $request, string $action): RedirectResponse|JsonResponse
    {
        if (! in_array($action, ['mark_all_read', 'clear_all', 'mark_read'], true)) {
            abort(404);
        }

        $userId = Auth::id();

        if ($action === 'mark_read') {
            $validated = $request->validate([
                'id' => ['required', 'integer'],
            ]);

            Notification::query()
                ->where('user_id', $userId)
                ->where('id', $validated['id'])
                ->update(['is_read' => true]);
        } elseif ($action === 'mark_all_read') {
            Notification::query()
                ->where('user_id', $userId)
                ->unread()
                ->update(['is_read' => true]);
        } elseif ($action === 'clear_all') {
            Notification::query()->where('user_id', $userId)->delete();
        }

        $unreadCount = Notification::query()->where('user_id', $userId)->unread()->count();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'unread_count' => $unreadCount,
            ]);
        }

        return redirect()
            ->route('user.notifications')
            ->with('success', $action === 'clear_all' ? 'All notifications cleared.' : 'Notifications marked as read.');
    }
}
