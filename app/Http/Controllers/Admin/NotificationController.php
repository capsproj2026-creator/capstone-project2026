<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Services\NavigationService;
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
        return $this->render($request, $this->routeName());
    }

    public function action(Request $request, string $action): RedirectResponse|JsonResponse
    {
        if (! in_array($action, ['mark_all_read', 'clear_all', 'mark_read'], true)) {
            abort(404);
        }

        $userId = (int) Auth::id();
        $query = Notification::query()->where('user_id', $userId);

        if ($action === 'mark_read') {
            $validated = $request->validate([
                'id' => ['required', 'integer'],
            ]);
            (clone $query)->where('id', (int) $validated['id'])->update(['is_read' => true]);
        } elseif ($action === 'mark_all_read') {
            (clone $query)->unread()->update(['is_read' => true]);
        } else {
            $query->delete();
        }

        $unreadCount = Notification::query()->where('user_id', $userId)->unread()->count();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'unread_count' => $unreadCount,
            ]);
        }

        return redirect()
            ->route($this->routeName())
            ->with('success', $action === 'clear_all' ? 'Notifications cleared.' : 'Notifications marked as read.');
    }

    private function render(Request $request, string $routeName): View
    {
        $userId = Auth::id();
        $query = Notification::query()
            ->where('user_id', $userId)
            ->orderByDesc('created_at');

        $status = trim((string) $request->query('status', 'all'));
        if (! in_array($status, ['all', 'read', 'unread'], true)) {
            $status = 'all';
        }

        if ($status === 'read') {
            $query->where('is_read', true);
        } elseif ($status === 'unread') {
            $query->unread();
        }

        if ($search = trim((string) $request->query('q'))) {
            $term = SearchHelper::escapeLike($search);
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', "%{$term}%")
                    ->orWhere('message', 'like', "%{$term}%");
            });
        }

        $view = Auth::user()?->user_role_id === NavigationService::ROLE_VPAF
            ? 'vpaf.notifications'
            : 'admin.notifications';

        return view($view, [
            'notifications' => $query->paginate(25)->withQueryString(),
            'search' => $search ?? '',
            'statusFilter' => $status,
            'unreadCount' => Notification::query()->where('user_id', $userId)->unread()->count(),
            'actionRoute' => $routeName.'.action',
        ]);
    }

    private function routeName(): string
    {
        return Auth::user()?->user_role_id === NavigationService::ROLE_VPAF
            ? 'vpaf.notifications'
            : 'admin.notifications';
    }
}
