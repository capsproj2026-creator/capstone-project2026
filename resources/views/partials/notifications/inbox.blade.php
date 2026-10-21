<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <p class="text-sm text-gray-500">
        @if (($unreadCount ?? 0) > 0)
            <span class="font-medium text-blue-700">{{ $unreadCount }} unread</span>
        @else
            You're all caught up.
        @endif
    </p>
    <div class="flex flex-wrap gap-3">
        <form method="POST" action="{{ route($actionRoute, 'mark_all_read') }}" data-notification-action>
            @csrf
            <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-100" @disabled(($unreadCount ?? 0) < 1)>
                <i data-lucide="check-check" class="h-4 w-4"></i>
                Mark all as read
            </button>
        </form>
        <form
            method="POST"
            action="{{ route($actionRoute, 'clear_all') }}"
            data-confirm="Clear all notifications? This cannot be undone."
            data-confirm-title="Clear notifications"
            data-confirm-action="Clear all"
            data-confirm-tone="danger"
        >
            @csrf
            <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-100">
                <i data-lucide="trash-2" class="h-4 w-4"></i>
                Clear all
            </button>
        </form>
    </div>
</div>

<form method="GET" class="mb-6 flex flex-wrap items-end gap-3">
    <div class="min-w-[180px] flex-1">
        <label class="mb-1 block text-xs font-medium text-gray-500">Search</label>
        <input type="search" name="q" value="{{ $search ?? '' }}" placeholder="Title or message..."
            class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm">
    </div>
    <div>
        <label class="mb-1 block text-xs font-medium text-gray-500">Status</label>
        <select name="status" class="rounded-lg border border-gray-300 px-4 py-2 text-sm">
            <option value="all" @selected(($statusFilter ?? 'all') === 'all')>All</option>
            <option value="unread" @selected(($statusFilter ?? '') === 'unread')>Unread</option>
            <option value="read" @selected(($statusFilter ?? '') === 'read')>Read</option>
        </select>
    </div>
    <button type="submit" class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">Filter</button>
</form>

<div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
    @forelse ($notifications as $notif)
        <div @class([
            'border-b border-gray-100 p-5 last:border-0',
            'border-l-4 border-l-blue-600 bg-blue-50/50' => ! $notif->is_read,
        ]) data-notification-row data-notification-id="{{ $notif->id }}">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">{{ $notif->type }}</span>
                    <h3 class="mt-1 font-semibold text-gray-900">{{ $notif->title }}</h3>
                    <p class="mt-2 text-sm text-gray-600">{{ $notif->message }}</p>
                </div>
                <div class="flex flex-col items-end gap-2">
                    <time class="text-xs text-gray-500">{{ $notif->created_at?->diffForHumans() }}</time>
                    @unless ($notif->is_read)
                        <form method="POST" action="{{ route($actionRoute, 'mark_read') }}" data-notification-action>
                            @csrf
                            <input type="hidden" name="id" value="{{ $notif->id }}">
                            <button type="submit" class="text-xs font-semibold text-blue-600 hover:underline">Mark as read</button>
                        </form>
                    @endunless
                </div>
            </div>
        </div>
    @empty
        <p class="px-6 py-12 text-center text-gray-500">No notifications yet.</p>
    @endforelse
</div>

@if ($notifications->hasPages())
    <div class="mt-4">{{ $notifications->links() }}</div>
@endif
