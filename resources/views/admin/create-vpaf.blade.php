@extends('layouts.portal')

@section('title', 'VPAF Accounts')

@section('content')
    @include('partials.shell.page-header', [
        'title' => 'VPAF accounts',
        'subtitle' => 'Create the login that approves or rejects GSU endorsements',
    ])

    @include('partials.shell.flash')

    <div class="mb-5 flex max-w-3xl items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-900">
        <i data-lucide="shield-alert" class="mt-0.5 h-5 w-5 shrink-0"></i>
        <div>
            <p class="text-sm font-semibold">Admin-only action</p>
            <p class="text-xs text-amber-800">
                This account can sign in immediately and decide second-offense suspensions and third-offense revocations.
            </p>
        </div>
    </div>

    <div class="max-w-3xl rounded-xl border border-gray-200 bg-white p-6 sm:p-7">
        <form method="POST" action="{{ route('admin.vpaf.store') }}" class="space-y-6">
            @csrf

            <div class="space-y-4 rounded-xl border border-gray-200 bg-gray-50/70 p-4 sm:p-5">
                <h2 class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                    <i data-lucide="user-round" class="h-4 w-4 text-blue-600"></i>
                    VPAF profile
                </h2>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-auth.input label="Full Name" name="fullname" required placeholder="Juan Dela Cruz" />
                    <x-auth.input label="ID Number" name="id_number" required maxlength="50" placeholder="e.g. VPAF2026" />
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-auth.input label="Email Address" name="email" type="email" required placeholder="name@example.com" />
                    <x-auth.input label="Phone Number" name="phone_number" required placeholder="09XX XXX XXXX" />
                </div>
            </div>

            <div class="space-y-4 rounded-xl border border-gray-200 bg-gray-50/70 p-4 sm:p-5">
                <h2 class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                    <i data-lucide="key-round" class="h-4 w-4 text-blue-600"></i>
                    Security
                </h2>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-auth.password-input name="password" label="Password" autocomplete="new-password" />
                    <x-auth.password-input name="password_confirmation" label="Confirm Password" autocomplete="new-password" />
                </div>
            </div>

            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">
                <i data-lucide="shield-plus" class="h-4 w-4"></i>
                Create VPAF account
            </button>
        </form>
    </div>

    @if ($accounts->isNotEmpty())
        <h2 class="mb-3 mt-8 text-sm font-semibold text-gray-900">Existing VPAF accounts</h2>
        <div class="max-w-3xl overflow-hidden rounded-xl border border-gray-200 bg-white">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-gray-500">
                    <tr>
                        <th class="px-5 py-3 font-medium">Name</th>
                        <th class="px-5 py-3 font-medium">Email</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($accounts as $account)
                        <tr>
                            <td class="px-5 py-3">{{ $account->fullname }}</td>
                            <td class="px-5 py-3 text-gray-600">{{ $account->email }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
