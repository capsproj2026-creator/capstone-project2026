@if (session('success'))
    <div class="mb-4 flex gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
        <i data-lucide="circle-check" class="h-4 w-4 shrink-0"></i>
        {{ session('success') }}
    </div>
@endif
@if (session('error'))
    <div class="mb-4 flex gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        <i data-lucide="circle-alert" class="h-4 w-4 shrink-0"></i>
        {{ session('error') }}
    </div>
@endif
