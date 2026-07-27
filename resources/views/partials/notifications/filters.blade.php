<form method="GET" class="mb-6 flex flex-wrap items-end gap-3">
    <div class="min-w-[180px] flex-1">
        <label class="mb-1 block text-xs font-medium text-gray-500">Search</label>
        <input type="search" name="q" value="{{ $search ?? '' }}" placeholder="Title or message..."
            class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm">
    </div>
    <div>
        <label class="mb-1 block text-xs font-medium text-gray-500">Type</label>
        <select name="type" class="rounded-lg border border-gray-300 px-4 py-2 text-sm">
            @foreach ($types as $t)
                <option value="{{ $t }}" @selected(($typeFilter ?? 'all') === $t)>{{ $t === 'all' ? 'All types' : $t }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="mb-1 block text-xs font-medium text-gray-500">Status</label>
        <select name="status" class="rounded-lg border border-gray-300 px-4 py-2 text-sm">
            <option value="all" @selected(($statusFilter ?? 'all') === 'all')>All</option>
            <option value="unread" @selected(($statusFilter ?? '') === 'unread')>Unread</option>
            <option value="read" @selected(($statusFilter ?? '') === 'read')>Read</option>
        </select>
    </div>
    @isset($dateFrom)
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-500">From</label>
            <input type="date" name="date_from" value="{{ $dateFrom }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm">
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-500">To</label>
            <input type="date" name="date_to" value="{{ $dateTo ?? '' }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm">
        </div>
    @endisset
    <button type="submit" class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">Filter</button>
    @if (($search ?? '') !== '' || ($typeFilter ?? 'all') !== 'all' || ($statusFilter ?? 'all') !== 'all' || ($dateFrom ?? '') !== '' || ($dateTo ?? '') !== '')
        <a href="{{ $clearRoute }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Clear</a>
    @endif
</form>
