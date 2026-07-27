@extends('layouts.guard')

@section('title', 'Updates')

@section('content')
    @php
        /** @var \Illuminate\Pagination\LengthAwarePaginator $notifications */
    @endphp

    @include('partials.shell.page-header', [
        'title' => 'Campus Updates',
        'subtitle' => 'System alerts and announcements for security staff',
    ])

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-gray-500">
            @if (($unreadCount ?? 0) > 0)
                <span class="font-medium text-blue-700">{{ $unreadCount }} unread</span>
            @else
                You're all caught up.
            @endif
        </p>
        <div class="flex flex-wrap gap-3">
            <form method="POST" action="{{ route('guard.notifications.action', 'mark_all_read') }}" data-notification-action>
                @csrf
                <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-100" @disabled(($unreadCount ?? 0) < 1)>
                    <i data-lucide="check-check" class="h-4 w-4"></i>
                    Mark all as read
                </button>
            </form>
            <form method="POST" action="{{ route('guard.notifications.action', 'clear_all') }}" onsubmit="return confirm('Clear all updates?')">
                @csrf
                <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-100">
                    <i data-lucide="trash-2" class="h-4 w-4"></i>
                    Clear all
                </button>
            </form>
        </div>
    </div>

    @include('partials.notifications.filters', [
        'clearRoute' => route('guard.notifications'),
        'types' => ['all', 'System', 'General', 'Parking', 'Update'],
    ])

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
                            <form method="POST" action="{{ route('guard.notifications.action', 'mark_read') }}" data-notification-action>
                                @csrf
                                <input type="hidden" name="id" value="{{ $notif->id }}">
                                <button type="submit" class="text-xs font-semibold text-blue-600 hover:underline">Mark as read</button>
                            </form>
                        @endunless
                    </div>
                </div>
            </div>
        @empty
            <div class="px-6 py-12 text-center">
                <i data-lucide="bell-off" class="mx-auto mb-3 h-8 w-8 text-gray-300"></i>
                <p class="text-gray-500">No campus updates yet.</p>
                <p class="mt-1 text-xs text-gray-400">System alerts and announcements will appear here.</p>
            </div>
        @endforelse
    </div>

    @if ($notifications->hasPages())
        <div class="mt-4">{{ $notifications->links() }}</div>
    @endif
@endsection

@push('scripts')
    @include('partials.notifications.mark-read-script')
@endpush
