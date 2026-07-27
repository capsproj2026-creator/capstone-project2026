<div class="mb-6 overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div class="min-w-0">
            <h3 class="text-lg font-semibold text-gray-900">Admin User Management</h3>
            <p class="mt-1 text-sm text-gray-500">Manage system administrators and security personnel</p>
        </div>
        <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row lg:flex-col lg:items-stretch">
            <button
                type="button"
                id="open-create-admin"
                class="inline-flex items-center justify-center gap-2 rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-black"
            >
                <i data-lucide="plus" class="h-4 w-4"></i>
                Create Admin
            </button>
            <button
                type="button"
                id="open-create-guard"
                class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-800 hover:bg-gray-50"
            >
                <i data-lucide="plus" class="h-4 w-4"></i>
                Create Guard
            </button>
        </div>
    </div>

    <div class="mt-6 space-y-3">
        @forelse ($staffUsers as $staff)
            @php
                $badge = $rolePermissionService->badgeLabel($staff);
                $initial = strtoupper(mb_substr($staff->name ?? 'U', 0, 1));
                $isAdminBadge = $badge === 'admin';
            @endphp
            <div class="flex flex-col gap-3 rounded-xl border border-gray-200 px-4 py-3 sm:flex-row sm:items-center">
                <div class="flex min-w-0 flex-1 items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-100 text-sm font-semibold text-blue-700">
                        {{ $initial }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold text-gray-900">{{ $staff->name }}</p>
                        <p class="truncate text-sm text-gray-500">{{ $staff->email }}</p>
                    </div>
                </div>
                <div class="flex items-center justify-between gap-3 sm:justify-end">
                    <span @class([
                        'shrink-0 rounded-full px-3 py-1 text-xs font-semibold lowercase',
                        'bg-gray-900 text-white' => $isAdminBadge,
                        'bg-gray-100 text-gray-800' => ! $isAdminBadge,
                    ])>{{ $badge }}</span>
                    <form
                        method="POST"
                        action="{{ route('admin.settings.staff.destroy', $staff->id) }}"
                        onsubmit="return confirm('Remove {{ addslashes($staff->name) }} from the system?')"
                    >
                        @csrf
                        @method('DELETE')
                        <button
                            type="submit"
                            class="rounded-lg p-2 text-gray-400 hover:bg-red-50 hover:text-red-600 disabled:cursor-not-allowed disabled:opacity-40"
                            @disabled((int) $staff->id === (int) auth()->id())
                            title="{{ (int) $staff->id === (int) auth()->id() ? 'Cannot delete your own account' : 'Delete user' }}"
                        >
                            <i data-lucide="trash-2" class="h-4 w-4"></i>
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <p class="rounded-xl border border-dashed border-gray-200 px-4 py-10 text-center text-sm text-gray-500">
                No administrators or guards found.
            </p>
        @endforelse
    </div>
</div>

{{-- Create Admin Modal --}}
<div id="create-admin-modal" class="fixed inset-0 z-50 hidden items-start justify-center overflow-y-auto bg-black/50 p-4 sm:items-center">
    <div class="my-8 w-full max-w-lg rounded-xl bg-white p-5 shadow-xl sm:my-4 sm:p-6">
        <div class="mb-4 flex items-center justify-between gap-3">
            <h3 class="text-lg font-semibold text-gray-900">Create Admin</h3>
            <button type="button" data-close-modal="create-admin-modal" class="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100">
                <i data-lucide="x" class="h-5 w-5"></i>
            </button>
        </div>
        <form method="POST" action="{{ route('admin.settings.admins.store') }}" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            @csrf
            <div class="min-w-0 sm:col-span-2">
                <label class="mb-1.5 block text-sm font-semibold text-gray-800">Full Name</label>
                <input type="text" name="name" required class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm">
            </div>
            <div class="min-w-0">
                <label class="mb-1.5 block text-sm font-semibold text-gray-800">Email</label>
                <input type="email" name="email" required class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm">
            </div>
            <div class="min-w-0">
                <label class="mb-1.5 block text-sm font-semibold text-gray-800">ID Number</label>
                <input type="text" name="id_number" required class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm">
            </div>
            <div class="min-w-0">
                <label class="mb-1.5 block text-sm font-semibold text-gray-800">Phone</label>
                <input type="text" name="phone_number" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm">
            </div>
            <div class="min-w-0">
                <label class="mb-1.5 block text-sm font-semibold text-gray-800">Title</label>
                <select name="job_title" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm">
                    <option value="Admin">Admin</option>
                    <option value="Security Head">Security Head</option>
                </select>
            </div>
            <div class="min-w-0">
                <label class="mb-1.5 block text-sm font-semibold text-gray-800">Password</label>
                <input type="password" name="password" required minlength="8" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm">
            </div>
            <div class="min-w-0">
                <label class="mb-1.5 block text-sm font-semibold text-gray-800">Confirm Password</label>
                <input type="password" name="password_confirmation" required minlength="8" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm">
            </div>
            <div class="flex flex-col-reverse justify-end gap-2 sm:col-span-2 sm:flex-row">
                <button type="button" data-close-modal="create-admin-modal" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="submit" class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-black">Create Admin</button>
            </div>
        </form>
    </div>
</div>

{{-- Create Guard Modal --}}
<div id="create-guard-modal" class="fixed inset-0 z-50 hidden items-start justify-center overflow-y-auto bg-black/50 p-4 sm:items-center">
    <div class="my-8 w-full max-w-lg rounded-xl bg-white p-5 shadow-xl sm:my-4 sm:p-6">
        <div class="mb-4 flex items-center justify-between gap-3">
            <h3 class="text-lg font-semibold text-gray-900">Create Guard</h3>
            <button type="button" data-close-modal="create-guard-modal" class="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100">
                <i data-lucide="x" class="h-5 w-5"></i>
            </button>
        </div>
        <form method="POST" action="{{ route('admin.guards.store') }}" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            @csrf
            <div class="min-w-0 sm:col-span-2">
                <label class="mb-1.5 block text-sm font-semibold text-gray-800">Full Name</label>
                <input type="text" name="name" required class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm">
            </div>
            <div class="min-w-0">
                <label class="mb-1.5 block text-sm font-semibold text-gray-800">Email</label>
                <input type="email" name="email" required class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm">
            </div>
            <div class="min-w-0">
                <label class="mb-1.5 block text-sm font-semibold text-gray-800">ID Number</label>
                <input type="text" name="id_number" required class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm">
            </div>
            <div class="min-w-0 sm:col-span-2">
                <label class="mb-1.5 block text-sm font-semibold text-gray-800">Phone</label>
                <input type="text" name="phone_number" required class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm">
            </div>
            <div class="min-w-0">
                <label class="mb-1.5 block text-sm font-semibold text-gray-800">Password</label>
                <input type="password" name="password" required minlength="8" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm">
            </div>
            <div class="min-w-0">
                <label class="mb-1.5 block text-sm font-semibold text-gray-800">Confirm Password</label>
                <input type="password" name="password_confirmation" required minlength="8" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm">
            </div>
            <div class="flex flex-col-reverse justify-end gap-2 sm:col-span-2 sm:flex-row">
                <button type="button" data-close-modal="create-guard-modal" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="submit" class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-black">Create Guard</button>
            </div>
        </form>
    </div>
</div>
