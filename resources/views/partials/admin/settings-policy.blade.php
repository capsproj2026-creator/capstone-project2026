@php
    use App\Support\CampusParkingPolicy;

    $staticSections = $policyStaticSections ?? CampusParkingPolicy::staticSections();
@endphp

<div class="mb-6 overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
    <div class="mb-2">
        <h3 class="text-lg font-semibold text-gray-900">Policy</h3>
        <p class="mt-1 text-sm text-gray-500">
            {{ CampusParkingPolicy::REFERENCE }} · Official titles only (no section numbers). These clauses are fixed text shown on the user Policy page.
        </p>
    </div>

    <div class="mt-4 rounded-xl border border-blue-100 bg-blue-50/60 px-4 py-3 text-sm text-blue-950">
        <p class="font-semibold">Managed in other Settings tabs</p>
        <ul class="mt-2 list-disc space-y-1 pl-5 text-blue-900/90">
            <li><span class="font-medium">General Information</span> → Notifications</li>
            <li><span class="font-medium">Stalled Vehicles</span> → Access Rules</li>
            <li><span class="font-medium">Parking and Traffic Violation</span> → Violations</li>
        </ul>
    </div>
</div>

<div class="space-y-4" data-policy-admin-accordions>
    @foreach ($staticSections as $index => $policySection)
        <details
            class="group overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm"
        >
            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-4 select-none hover:bg-gray-50/80 [&::-webkit-details-marker]:hidden">
                <h4 class="font-semibold text-gray-900">{{ $policySection['title'] }}</h4>
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-500 group-open:bg-blue-50 group-open:text-blue-700">
                    <i data-lucide="chevron-down" class="h-4 w-4 transition-transform group-open:rotate-180"></i>
                </span>
            </summary>
            <div class="space-y-3 border-t border-gray-100 p-5">
                @if (! empty($policySection['intro']))
                    <p class="text-sm leading-relaxed text-gray-700">{{ $policySection['intro'] }}</p>
                @endif

                @foreach ($policySection['paragraphs'] ?? [] as $paragraph)
                    <p class="text-sm leading-relaxed text-gray-700">{{ $paragraph }}</p>
                @endforeach

                @if (! empty($policySection['items']))
                    <div class="divide-y divide-gray-100 rounded-xl border border-gray-100">
                        @foreach ($policySection['items'] as $itemIndex => $item)
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
