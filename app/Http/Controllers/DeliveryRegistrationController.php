<?php

namespace App\Http\Controllers;

use App\Models\Visitor;
use App\Services\VisitorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeliveryRegistrationController extends Controller
{
    public function show(): View
    {
        return view('visitors.delivery', [
            'companies' => ['Grab', 'Foodpanda', 'Lalamove', 'Other'],
        ]);
    }

    public function store(Request $request, VisitorService $visitors): RedirectResponse
    {
        if (filled($request->input('website'))) {
            abort(422, 'Invalid submission.');
        }

        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:120'],
            'contact_number' => ['required', 'string', 'max:40'],
            'company' => ['required', 'string', 'in:Grab,Foodpanda,Lalamove,Other'],
            'company_other' => ['nullable', 'required_if:company,Other', 'string', 'max:80'],
            'plate_number' => ['required', 'string', 'max:20'],
            'recipient' => ['required', 'string', 'max:255'],
        ]);

        $company = $validated['company'] === 'Other'
            ? trim((string) $validated['company_other'])
            : $validated['company'];

        $visitor = $visitors->registerDelivery([
            'full_name' => $validated['full_name'],
            'contact_number' => $validated['contact_number'],
            'company' => $company,
            'plate_number' => $validated['plate_number'],
            'recipient' => $validated['recipient'],
        ]);

        return $this->toSuccess($visitor);
    }

    public function returning(Request $request, VisitorService $visitors): RedirectResponse
    {
        $validated = $request->validate([
            'plate_number' => ['required', 'string', 'max:20'],
        ]);

        $visitor = $visitors->checkInReturningDelivery($validated['plate_number']);

        return $this->toSuccess($visitor);
    }

    public function success(Request $request): View|RedirectResponse
    {
        $visitorId = session('delivery_visitor_id');
        $code = session('delivery_code');

        if (! is_numeric($visitorId) || ! is_string($code) || $code === '') {
            return redirect()->route('delivery.show');
        }

        $visitor = Visitor::query()->find((int) $visitorId);
        if (! $visitor || (string) $visitor->confirmation_code !== $code || ! $visitor->isDelivery()) {
            return redirect()->route('delivery.show');
        }

        return view('visitors.delivery-success', [
            'visitor' => $visitor,
        ]);
    }

    private function toSuccess(Visitor $visitor): RedirectResponse
    {
        return redirect()
            ->route('delivery.success')
            ->with('delivery_visitor_id', $visitor->id)
            ->with('delivery_code', $visitor->confirmation_code);
    }
}
