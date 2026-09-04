<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Models\Visitor;
use App\Services\VisitorService;
use App\Support\VisitorPreRegister;
use App\Support\VisitorPreRegisterQr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VisitorController extends Controller
{
    public function register(Request $request): View
    {
        $preRegisterUrl = VisitorPreRegisterQr::preRegisterUrl();

        return view('visitors.register', [
            'vehicles' => Vehicle::query()->orderBy('id')->get(),
            'routePrefix' => $this->routePrefix($request),
            'preRegisterUrl' => $preRegisterUrl,
            'preRegisterUsesGoogleForm' => VisitorPreRegister::usesGoogleForm(),
            'preRegisterQrUrl' => route('visitor.pre-register.qr'),
            'preRegisterQrSvg' => VisitorPreRegisterQr::svg($preRegisterUrl),
        ]);
    }

    public function store(Request $request, VisitorService $visitors): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'middle_name' => ['nullable', 'string', 'max:80'],
            'contact_number' => ['required', 'string', 'max:40'],
            'email' => ['required', 'email', 'max:120'],
            'purpose' => ['required', 'string', 'max:255'],
            'office_to_visit' => ['required', 'string', 'max:255'],
            'expected_exit_at' => ['required', 'date', 'after:now'],
            'plate_number' => ['required', 'string', 'max:20'],
            'vehicle_id' => ['required', 'integer'],
            'vehicle_color' => ['required', 'string', 'max:40'],
            'rfid_uid' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'email.required' => 'Email is required for visitor registration.',
            'email.email' => 'Please enter a valid email address.',
        ]);

        $visitor = $visitors->register($validated, $request->user());

        return redirect()
            ->route($this->routePrefix($request).'.visitors.active')
            ->with('success', "Visitor {$visitor->displayName()} registered".($visitor->rfid_uid ? ' with temporary RFID.' : '.'));
    }

    public function active(Request $request, VisitorService $visitors): View
    {
        $status = $request->query('status');
        $search = $request->query('search');
        $routePrefix = $this->routePrefix($request);
        $canManage = $routePrefix === 'guard';

        return view('visitors.active', [
            'visitors' => $visitors->activeVisitors($search, is_string($status) ? $status : null),
            'statusFilter' => is_string($status) ? $status : 'All',
            'search' => is_string($search) ? $search : '',
            'routePrefix' => $routePrefix,
            'canManage' => $canManage,
            'pageTitle' => $canManage ? 'Active Visitors' : 'Registered Visitors',
            'pageSubtitle' => $canManage
                ? 'Visitors currently registered, on campus, or overdue'
                : 'View registered visitors currently on campus or awaiting exit',
        ]);
    }

    public function history(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        $query = Visitor::query()
            ->with(['vehicleType', 'rfidCard'])
            ->where('status', Visitor::STATUS_COMPLETED)
            ->orderByDesc('time_out')
            ->orderByDesc('id');

        $all = $query->get();

        if ($search !== '') {
            $q = strtolower($search);
            $all = $all->filter(function (Visitor $v) use ($q) {
                $blob = strtolower(implode(' ', [
                    $v->displayName(),
                    $v->plate_number,
                    $v->rfid_uid,
                    $v->purpose,
                    $v->office_to_visit,
                ]));

                return str_contains($blob, $q);
            })->values();
        }

        $page = max(1, (int) $request->query('page', 1));
        $perPage = 15;
        $total = $all->count();
        $items = $all->slice(($page - 1) * $perPage, $perPage)->values();

        return view('visitors.history', [
            'visitors' => $items,
            'search' => $search,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'lastPage' => max(1, (int) ceil($total / $perPage)),
            'routePrefix' => $this->routePrefix($request),
        ]);
    }

    public function update(Request $request, int $id, VisitorService $visitors): RedirectResponse
    {
        $visitor = Visitor::query()->findOrFail($id);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'middle_name' => ['nullable', 'string', 'max:80'],
            'contact_number' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:120'],
            'purpose' => ['required', 'string', 'max:255'],
            'office_to_visit' => ['required', 'string', 'max:255'],
            'expected_exit_at' => ['required', 'date'],
            'plate_number' => ['required', 'string', 'max:20'],
            'vehicle_id' => ['required', 'integer'],
            'vehicle_color' => ['required', 'string', 'max:40'],
        ]);

        $visitors->update($visitor, $validated);

        return back()->with('success', 'Visitor details updated.');
    }

    public function assignRfid(Request $request, int $id, VisitorService $visitors): RedirectResponse
    {
        $visitor = Visitor::query()->findOrFail($id);

        $validated = $request->validate([
            'rfid_uid' => ['required', 'string', 'max:64'],
        ]);

        $visitors->assignRfid($visitor, $validated['rfid_uid'], $request->user());

        return back()->with('success', 'Temporary RFID assigned.');
    }

    public function returnRfid(Request $request, int $id, VisitorService $visitors): RedirectResponse
    {
        $visitor = Visitor::query()->findOrFail($id);
        $visitors->returnRfid($visitor, markCompleted: $visitor->status !== Visitor::STATUS_COMPLETED);

        return back()->with('success', 'Temporary RFID returned.');
    }

    public function markExited(Request $request, int $id, VisitorService $visitors): RedirectResponse
    {
        $visitor = Visitor::query()->findOrFail($id);

        if ($visitor->status === Visitor::STATUS_COMPLETED) {
            return back()->with('error', 'This visitor has already been marked as exited.');
        }

        $visitors->completeOnExit($visitor);

        return back()->with('success', "Visitor {$visitor->displayName()} marked as exited. Visit moved to history.");
    }

    private function routePrefix(Request $request): string
    {
        $name = (string) $request->route()?->getName();

        return str_starts_with($name, 'guard.') ? 'guard' : 'admin';
    }
}
