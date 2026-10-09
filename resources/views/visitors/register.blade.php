@extends('layouts.portal')

@section('title', 'Register Visitor')

@section('content')
    @include('partials.shell.page-header', [
        'title' => 'Register Visitor',
        'subtitle' => 'Register a campus visitor and optionally assign a temporary RFID card',
    ])

    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <ul class="list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mb-6 grid gap-6 lg:grid-cols-2">
        <div class="overflow-hidden rounded-2xl border border-amber-200 bg-white shadow-sm print:border-slate-400 print:shadow-none">
            <div class="border-b border-amber-100 bg-amber-50 px-5 py-3 sm:px-6">
                <h2 class="text-sm font-semibold tracking-wide text-amber-900 uppercase">Entrance QR · Delivery</h2>
            </div>
            <div class="grid gap-6 p-5 sm:grid-cols-[auto_minmax(0,1fr)] sm:items-center sm:p-6">
                <div class="mx-auto flex w-full max-w-[220px] flex-col items-center text-center">
                    <div class="rounded-2xl border border-amber-200 bg-white p-4 shadow-sm">
                        <div class="mx-auto aspect-square w-[180px] [&_svg]:h-full [&_svg]:w-full">
                            {!! $deliveryQrSvg !!}
                        </div>
                    </div>
                    <p class="mt-3 text-xs font-semibold tracking-wide text-amber-800 uppercase">Scan for delivery</p>
                </div>
                <div class="min-w-0 space-y-3">
                    <p class="text-base font-semibold text-slate-900">Riders use this QR</p>
                    <p class="text-sm leading-relaxed text-slate-600">Name, mobile, company, plate, and who the package is for. They show as Waiting with a Delivery tag. Leaving the gate finishes the visit.</p>
                    <p class="break-all rounded-lg bg-amber-50 px-3 py-2 font-mono text-[11px] leading-relaxed text-amber-900">{{ $deliveryUrl }}</p>
                </div>
            </div>
        </div>
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm print:border-slate-400 print:shadow-none">
        <div class="border-b border-slate-100 bg-slate-50 px-5 py-3 sm:px-6">
            <h2 class="text-sm font-semibold tracking-wide text-slate-800 uppercase">Entrance QR · Visitor pre-registration</h2>
        </div>
        <div class="grid gap-6 p-5 sm:grid-cols-[auto_minmax(0,1fr)] sm:items-center sm:gap-8 sm:p-6">
            <div class="mx-auto flex w-full max-w-[280px] flex-col items-center text-center sm:mx-0 sm:max-w-none">
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm ring-1 ring-slate-100 print:shadow-none print:ring-0">
                    <div class="mx-auto aspect-square w-[220px] sm:w-[260px] [&_svg]:h-full [&_svg]:w-full">
                        {!! $preRegisterQrSvg !!}
                    </div>
                </div>
                <p class="mt-3 text-xs font-semibold tracking-wide text-slate-500 uppercase">Scan to pre-register</p>
                <a href="{{ $preRegisterQrUrl }}" download="visitor-pre-register-qr.svg"
                    class="mt-3 inline-flex items-center gap-1.5 rounded-lg bg-slate-900 px-3.5 py-2 text-sm font-semibold text-white hover:bg-slate-800 print:hidden">
                    <i data-lucide="download" class="h-4 w-4"></i>
                    Download QR (SVG)
                </a>
            </div>
            <div class="min-w-0 space-y-3">
                <p class="text-base font-semibold text-slate-900">Post this at the gate</p>
                <p class="text-sm leading-relaxed text-slate-600">
                    @if ($preRegisterUsesGoogleForm ?? false)
                        Visitors scan the QR to open the Google Form. On submit, the system creates a <span class="font-medium text-slate-800">Waiting</span> visitor and emails them a confirmation with a reference code. Open Visitors → Active/Waiting to verify — no spreadsheet needed.
                    @else
                        Visitors scan the QR to open the registration form on this website. Submitting it saves them as Waiting and emails the reference code.
                    @endif
                </p>
                <ol class="space-y-1.5 text-sm text-slate-600">
                    <li class="flex gap-2"><span class="font-semibold text-slate-800">1.</span> Visitor scans QR and submits the form</li>
                    <li class="flex gap-2"><span class="font-semibold text-slate-800">2.</span> Guard finds them under Waiting (name, plate, or code)</li>
                    <li class="flex gap-2"><span class="font-semibold text-slate-800">3.</span> Verify ID, assign temporary RFID</li>
                </ol>
                <p class="break-all rounded-lg bg-slate-50 px-3 py-2 font-mono text-[11px] leading-relaxed text-slate-500 print:text-[10px]">{{ $preRegisterUrl }}</p>
            </div>
        </div>
    </div>
    </div>

    <form method="POST" action="{{ route($routePrefix.'.visitors.store') }}" class="space-y-6">
        @csrf

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="text-base font-semibold text-gray-900">Personal Information</h2>
            <p class="mt-0.5 text-sm text-gray-500">Visitor identity and contact details</p>
            <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700">First Name <span class="text-red-500">*</span></label>
                    <input type="text" name="first_name" value="{{ old('first_name') }}" required
                        class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700">Middle Name</label>
                    <input type="text" name="middle_name" value="{{ old('middle_name') }}"
                        class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700">Last Name <span class="text-red-500">*</span></label>
                    <input type="text" name="last_name" value="{{ old('last_name') }}" required
                        class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700">Contact Number <span class="text-red-500">*</span></label>
                    <input type="text" name="contact_number" value="{{ old('contact_number') }}" required
                        class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700">Email <span class="text-red-500">*</span></label>
                    <input type="email" name="email" value="{{ old('email') }}" required
                        class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 @error('email') border-red-400 @enderror">
                    @error('email')
                        <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="text-base font-semibold text-gray-900">Visit Information</h2>
            <p class="mt-0.5 text-sm text-gray-500">Purpose and expected departure</p>
            <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700">Purpose of Visit <span class="text-red-500">*</span></label>
                    <input type="text" name="purpose" value="{{ old('purpose') }}" required
                        class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700">Office / Person to Visit <span class="text-red-500">*</span></label>
                    <input type="text" name="office_to_visit" value="{{ old('office_to_visit') }}" required
                        class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700">Expected Exit Time <span class="text-red-500">*</span></label>
                    <input type="datetime-local" name="expected_exit_at" value="{{ old('expected_exit_at') }}" required
                        class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="text-base font-semibold text-gray-900">Vehicle Information</h2>
            <p class="mt-0.5 text-sm text-gray-500">Plate and vehicle details for gate / YOLO matching</p>
            <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700">Plate Number <span class="text-red-500">*</span></label>
                    <input type="text" name="plate_number" value="{{ old('plate_number') }}" required
                        class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm uppercase focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700">Vehicle Type <span class="text-red-500">*</span></label>
                    <select name="vehicle_id" required
                        class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                        <option value="">Select type</option>
                        @foreach ($vehicles as $vehicle)
                            <option value="{{ $vehicle->id }}" @selected((string) old('vehicle_id') === (string) $vehicle->id)>{{ $vehicle->vehicle_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700">Vehicle Color <span class="text-red-500">*</span></label>
                    <input type="text" name="vehicle_color" value="{{ old('vehicle_color') }}" required
                        class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="text-base font-semibold text-gray-900">Temporary RFID</h2>
            <p class="mt-0.5 text-sm text-gray-500">Optional. Tap a card on the desk UID reader. That scan does not appear on the live gate monitor. Leave it waiting for “No RFID Assigned”.</p>
            <div class="mt-5">
                <label class="mb-1.5 block text-sm font-semibold text-gray-900">RFID Tag UID</label>
                <input type="hidden" id="visitor-rfid-uid" name="rfid_uid" value="{{ old('rfid_uid') }}" data-no-clear>

                <div id="visitor-rfid-scan-box" tabindex="0" data-state="waiting" class="rounded-xl border-2 border-dashed border-blue-200 bg-blue-50/60 px-4 py-4 text-center transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500/30">
                    <div id="visitor-rfid-state-waiting" class="flex flex-col items-center gap-1.5">
                        <i data-lucide="radar" class="h-6 w-6 animate-pulse text-blue-500"></i>
                        <p class="text-sm font-semibold text-blue-800">Waiting for ID tap…</p>
                        <p class="text-xs text-blue-600">Tap the card on the desk UID reader. This scan stays off the live gate monitor, and the UID fills in automatically.</p>
                    </div>
                    <div id="visitor-rfid-state-detected" class="hidden flex-col items-center gap-1.5">
                        <i data-lucide="check-circle-2" class="h-6 w-6 text-emerald-600"></i>
                        <p class="text-sm font-semibold text-emerald-800">Card detected</p>
                        <p id="visitor-rfid-detected-uid" class="font-mono text-base font-bold tracking-wide text-gray-900">—</p>
                        <button type="button" id="visitor-rfid-rescan" class="mt-1 text-xs font-semibold text-blue-700 underline hover:text-blue-900">Scan a different card</button>
                    </div>
                    <div id="visitor-rfid-state-failed" class="hidden flex-col items-center gap-1.5">
                        <i data-lucide="alert-triangle" class="h-6 w-6 text-amber-600"></i>
                        <p class="text-sm font-semibold text-amber-800">No ID tap detected</p>
                        <p class="text-xs text-amber-700">Keep this page open, make sure the desk UID reader is online, then tap the card again.</p>
                        <button type="button" id="visitor-rfid-retry" class="mt-1 inline-flex items-center gap-1.5 rounded-lg border border-amber-300 bg-white px-3 py-1.5 text-xs font-semibold text-amber-800 hover:bg-amber-50">
                            <i data-lucide="rotate-cw" class="h-3.5 w-3.5"></i>
                            Try again
                        </button>
                    </div>
                </div>
                <p id="visitor-rfid-scan-hint" class="mt-1.5 text-xs text-gray-500">Listening for a tap on the desk UID reader…</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-3">
            <a href="{{ route($routePrefix.'.visitors.active') }}" class="rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancel</a>
            <button type="submit" class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">Register Visitor</button>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const latestUnregisteredUrl = @json($latestUnregisteredUrl ?? route($routePrefix.'.visitors.latest-unregistered'));
        const input = document.getElementById('visitor-rfid-uid');
        const scanHint = document.getElementById('visitor-rfid-scan-hint');
        const scanBox = document.getElementById('visitor-rfid-scan-box');
        const stateEls = {
            waiting: document.getElementById('visitor-rfid-state-waiting'),
            detected: document.getElementById('visitor-rfid-state-detected'),
            failed: document.getElementById('visitor-rfid-state-failed'),
        };
        const boxClasses = {
            waiting: ['border-blue-200', 'bg-blue-50/60'],
            detected: ['border-emerald-300', 'bg-emerald-50'],
            failed: ['border-amber-300', 'bg-amber-50'],
        };

        const normalizeUid = (raw) => String(raw || '')
            .replace(/^\s*UID\s*:\s*/i, '')
            .toUpperCase()
            .replace(/[^A-F0-9]/g, '');

        const isFullUid = (raw) => normalizeUid(raw).length >= 6;

        const USB_SCAN_GAP_MS = 400;
        let scanState = 'waiting';
        let scanBuffer = '';
        let scanTimer = null;
        let gatePollTimer = null;
        let lastGateLogId = '';
        let baselineLogId = '';
        let openedAtMs = Date.now();
        let openedAtIso = new Date(openedAtMs).toISOString();

        const setScanState = (state) => {
            scanState = state;
            if (scanBox) {
                scanBox.dataset.state = state;
                Object.values(boxClasses).flat().forEach((cls) => scanBox.classList.remove(cls));
                (boxClasses[state] || []).forEach((cls) => scanBox.classList.add(cls));
            }
            Object.entries(stateEls).forEach(([key, el]) => {
                if (!el) return;
                el.classList.toggle('hidden', key !== state);
                el.classList.toggle('flex', key === state);
            });
            if (window.lucide) window.lucide.createIcons();
        };

        const setUidValue = (raw, source) => {
            if (!input || scanState === 'detected' || !isFullUid(raw)) return false;
            const uid = normalizeUid(raw);
            input.value = uid;
            const uidEl = document.getElementById('visitor-rfid-detected-uid');
            if (uidEl) uidEl.textContent = uid;
            if (scanHint) {
                scanHint.textContent = source === 'reader'
                    ? 'UID captured from the desk reader. This tap is not shown on the live gate monitor.'
                    : 'UID captured from USB reader tap. Register the visitor, or scan a different card.';
            }
            setScanState('detected');
            return true;
        };

        const pullLatestGateUid = async () => {
            if (scanState !== 'waiting' || !latestUnregisteredUrl) return;
            try {
                const url = new URL(latestUnregisteredUrl, window.location.origin);
                if (openedAtIso) url.searchParams.set('since', openedAtIso);
                const res = await fetch(url.toString(), {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    cache: 'no-store',
                });
                if (!res.ok) return;
                const data = await res.json();
                if (!data?.uid || !isFullUid(data.uid)) return;
                const logId = String(data.log_id || '');
                if (logId && baselineLogId && logId === baselineLogId) return;
                if (logId && logId === lastGateLogId) return;
                if (data.scanned_at && openedAtMs) {
                    const scannedMs = Date.parse(data.scanned_at);
                    if (!Number.isNaN(scannedMs) && scannedMs < (openedAtMs - 2500)) return;
                }
                if (setUidValue(data.uid, 'reader')) lastGateLogId = logId || lastGateLogId;
            } catch (e) { /* ignore */ }
        };

        const stopGatePoll = () => {
            if (gatePollTimer) {
                clearInterval(gatePollTimer);
                gatePollTimer = null;
            }
        };

        const startGatePoll = () => {
            stopGatePoll();
            pullLatestGateUid();
            gatePollTimer = window.setInterval(pullLatestGateUid, 500);
        };

        const snapshotBaseline = async () => {
            if (!latestUnregisteredUrl) {
                baselineLogId = '';
                return;
            }
            try {
                const res = await fetch(latestUnregisteredUrl, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    cache: 'no-store',
                });
                if (!res.ok) return;
                const data = await res.json();
                baselineLogId = data?.log_id ? String(data.log_id) : '';
                lastGateLogId = baselineLogId;
            } catch (e) {
                baselineLogId = '';
            }
        };

        const resetToWaiting = async () => {
            if (input) input.value = '';
            lastGateLogId = '';
            scanBuffer = '';
            if (scanHint) scanHint.textContent = 'Listening for a tap on the desk UID reader…';
            setScanState('waiting');
            openedAtMs = Date.now();
            openedAtIso = new Date(openedAtMs).toISOString();
            await snapshotBaseline();
            startGatePoll();
        };

        const typingTarget = (el) => {
            if (!el) return false;
            const tag = (el.tagName || '').toLowerCase();
            return tag === 'input' || tag === 'textarea' || tag === 'select' || el.isContentEditable;
        };

        document.addEventListener('keydown', (e) => {
            if (scanState !== 'waiting') return;
            const onScanBox = scanBox && (e.target === scanBox || scanBox.contains(e.target));
            if (!onScanBox && typingTarget(e.target)) return;
            if (e.ctrlKey || e.metaKey || e.altKey) return;

            if (e.key === 'Enter') {
                if (!onScanBox && scanBuffer.length < 6) return;
                e.preventDefault();
                if (scanTimer) {
                    clearTimeout(scanTimer);
                    scanTimer = null;
                }
                const candidate = scanBuffer.length >= 6 ? scanBuffer : (input?.value || '');
                if (isFullUid(candidate)) setUidValue(candidate, 'usb');
                scanBuffer = '';
                return;
            }
            if (e.key.length !== 1) return;
            if (!onScanBox && scanBuffer === '') return;

            e.preventDefault();
            scanBuffer += e.key;
            if (scanTimer) clearTimeout(scanTimer);
            scanTimer = window.setTimeout(() => {
                if (isFullUid(scanBuffer)) setUidValue(scanBuffer, 'usb');
                if (isFullUid(scanBuffer) || scanBuffer.length === 0) scanBuffer = '';
                scanTimer = null;
            }, USB_SCAN_GAP_MS);
        }, true);

        document.getElementById('visitor-rfid-rescan')?.addEventListener('click', () => { resetToWaiting(); });
        document.getElementById('visitor-rfid-retry')?.addEventListener('click', () => { resetToWaiting(); });

        const existing = normalizeUid(input?.value || '');
        if (isFullUid(existing)) {
            input.value = existing;
            const uidEl = document.getElementById('visitor-rfid-detected-uid');
            if (uidEl) uidEl.textContent = existing;
            if (scanHint) scanHint.textContent = 'UID kept from the previous attempt. Register the visitor, or scan a different card.';
            setScanState('detected');
        } else {
            resetToWaiting();
        }
    });
</script>
@endpush
