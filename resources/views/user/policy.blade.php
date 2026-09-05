@extends('layouts.user')

@section('title', 'Policy')

@section('content')
    @include('partials.shell.page-header', [
        'title' => 'Policy',
        'subtitle' => $policyTitle,
    ])

    <p class="portal-muted mb-6 text-sm">
        {{ $reference }} · General Services Unit, Camarines Sur Polytechnic Colleges
    </p>

    <div class="flex flex-col gap-4" data-policy-accordions>
        @foreach ($sections as $index => $section)
            <details
                class="dashboard-accordion group overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm transition-[box-shadow] duration-200 open:border-blue-200 open:shadow-md"
            >
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-4 select-none hover:bg-gray-50/80 sm:px-6 [&::-webkit-details-marker]:hidden">
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-700">
                            <i data-lucide="book-open" class="h-5 w-5"></i>
                        </div>
                        <h2 class="font-semibold text-gray-900">{{ $section['title'] }}</h2>
                    </div>
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-500 transition-colors duration-200 group-open:bg-blue-50 group-open:text-blue-700">
                        <i data-lucide="chevron-down" class="h-4 w-4 transition-transform duration-200 group-open:rotate-180"></i>
                    </span>
                </summary>

                <div class="space-y-3 border-t border-gray-100 p-5 sm:p-6">
                    @if (! empty($section['intro']))
                        <p class="text-sm leading-relaxed text-gray-700">{{ $section['intro'] }}</p>
                    @endif

                    @foreach ($section['paragraphs'] ?? [] as $paragraph)
                        <p class="text-sm leading-relaxed text-gray-700">{{ $paragraph }}</p>
                    @endforeach

                    @if (! empty($section['items']))
                        <div class="divide-y divide-gray-100 rounded-xl border border-gray-100">
                            @foreach ($section['items'] as $itemIndex => $item)
                                <div class="flex gap-3 px-4 py-3.5">
                                    <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-blue-50 text-xs font-bold text-blue-700">{{ $itemIndex + 1 }}</span>
                                    <div class="min-w-0 flex-1">
                                        @if (is_array($item))
                                            <p class="text-sm leading-relaxed text-gray-700">{{ $item['text'] }}</p>
                                            @if (! empty($item['children']))
                                                <div class="mt-3 divide-y divide-gray-100 rounded-xl border border-gray-100">
                                                    @foreach ($item['children'] as $childIndex => $child)
                                                        <div class="flex gap-3 px-3 py-2.5">
                                                            <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-blue-50 text-xs font-bold text-blue-700">{{ $childIndex + 1 }}</span>
                                                            <p class="text-sm leading-relaxed text-gray-700">{{ $child }}</p>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                        @else
                                            <p class="text-sm leading-relaxed text-gray-700">{{ $item }}</p>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </details>
        @endforeach
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const root = document.querySelector('[data-policy-accordions]');
            if (!root) return;

            const panels = Array.from(root.querySelectorAll('details.dashboard-accordion'));
            panels.forEach((panel) => {
                panel.addEventListener('toggle', () => {
                    if (!panel.open) return;
                    panels.forEach((other) => {
                        if (other !== panel && other.open) {
                            other.open = false;
                        }
                    });
                });
            });
        })();
    </script>
@endpush
