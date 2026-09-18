<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Services\VisitorService;
use App\Support\VisitorPreRegister;
use App\Support\VisitorPreRegisterQr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class VisitorPreRegistrationController extends Controller
{
    public function show(): View
    {
        // Always use the built-in form so visitors see a full confirmation of their details
        // (Google Forms only shows a generic thank-you page).
        return view('visitors.pre-register', [
            'vehicles' => Vehicle::query()->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request, VisitorService $visitors): RedirectResponse
    {
        if (filled($request->input('website'))) {
            abort(422, 'Invalid submission.');
        }

        $validated = $request->validate(VisitorPreRegister::validationRules());
        $payload = VisitorPreRegister::payloadForService($validated);

        $visitor = $visitors->preRegister($payload);

        return redirect()
            ->route('visitor.pre-register.success')
            ->with('pre_register_visitor_id', $visitor->id)
            ->with('pre_register_code', $visitor->confirmation_code);
    }

    public function success(Request $request): View|RedirectResponse
    {
        $visitor = VisitorPreRegister::visitorFromSignedRequest($request);

        if (! $visitor) {
            $code = session('pre_register_code');
            $visitorId = session('pre_register_visitor_id');

            if (! is_string($code) || $code === '' || ! is_numeric($visitorId)) {
                return redirect()->route('visitor.pre-register');
            }

            $visitor = \App\Models\Visitor::query()
                ->with('vehicleType')
                ->find((int) $visitorId);

            if (! $visitor || (string) $visitor->confirmation_code !== $code) {
                return redirect()->route('visitor.pre-register');
            }
        } else {
            $visitor->loadMissing('vehicleType');
        }

        return view('visitors.pre-register-success', [
            'visitor' => $visitor,
            'confirmationCode' => $visitor->confirmation_code,
        ]);
    }

    public function qr(Request $request): Response
    {
        $url = VisitorPreRegisterQr::preRegisterUrl();
        $svg = VisitorPreRegisterQr::svg($url);

        $disposition = $request->boolean('download')
            ? 'attachment; filename="visitor-pre-register-qr.svg"'
            : 'inline';

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => $disposition,
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
