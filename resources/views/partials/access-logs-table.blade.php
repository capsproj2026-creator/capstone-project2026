<form method="GET" class="mb-6 flex flex-wrap items-end gap-3">
    <div class="min-w-[200px] flex-1">
        <label class="mb-1 block text-xs font-medium text-gray-500">Search</label>
        <input
            type="search"
            name="q"
            value="{{ $search }}"
            placeholder="Name, plate, or ID..."
            class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm"
        >
    </div>
    <div>
        <label class="mb-1 block text-xs font-medium text-gray-500">Action</label>
        <select name="action" class="rounded-lg border border-gray-300 px-4 py-2 text-sm">
            <option value="all" @selected($actionFilter === 'all')>All actions</option>
            <option value="Entry" @selected($actionFilter === 'Entry')>Entry</option>
            <option value="Exit" @selected($actionFilter === 'Exit')>Exit</option>
        </select>
    </div>
    <div>
        <label class="mb-1 block text-xs font-medium text-gray-500">From</label>
        <input type="date" name="date_from" value="{{ $dateFrom ?? '' }}"
            class="rounded-lg border border-gray-300 px-4 py-2 text-sm">
    </div>
    <div>
        <label class="mb-1 block text-xs font-medium text-gray-500">To</label>
        <input type="date" name="date_to" value="{{ $dateTo ?? '' }}"
            class="rounded-lg border border-gray-300 px-4 py-2 text-sm">
    </div>
    <button type="submit" class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">Apply</button>
    @if (($search ?? '') !== '' || ($actionFilter ?? 'all') !== 'all' || ($dateFrom ?? '') !== '' || ($dateTo ?? '') !== '')
        <a href="{{ $clearRoute ?? url()->current() }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Clear</a>
    @endif
</form>

<div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
    <table class="w-full text-left text-sm">
        <thead class="border-b border-gray-200 bg-gray-50 text-gray-500">
            <tr>
                <th class="px-6 py-3 font-medium">Log #</th>
                <th class="px-6 py-3 font-medium">User</th>
                <th class="px-6 py-3 font-medium">Plate</th>
                <th class="px-6 py-3 font-medium">Action</th>
                <th class="px-6 py-3 font-medium">Date / Time</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse ($logs as $log)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-gray-600">#{{ $log->daily_log_id ?? $log->id }}</td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            @if ($log->user)
                                <x-portal.avatar :user="$log->user" size="sm" />
                            @endif
                            <span class="font-medium text-gray-900">{{ $log->user?->fullname ?? '—' }}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4"><code class="text-xs">{{ $log->user?->plate_number ?? '—' }}</code></td>
                    <td class="px-6 py-4">
                        <span @class([
                            'inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium',
                            'bg-green-100 text-green-700' => $log->action === 'Entry',
                            'bg-blue-100 text-blue-700' => $log->action === 'Exit',
                        ])>{{ $log->action }}</span>
                    </td>
                    <td class="px-6 py-4 text-gray-600">{{ ph_datetime($log->timestamp) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-6 py-10 text-center text-gray-500">No access logs found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    @if ($logs->hasPages())
        <div class="border-t border-gray-200 px-6 py-4">{{ $logs->links() }}</div>
    @endif
</div>
