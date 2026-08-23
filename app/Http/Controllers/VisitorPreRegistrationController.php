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
    public function show(): RedirectResponse|View
    {
        if ($googleFormUrl = VisitorPreRegister::googleFormUrl()) {
            return redirect()->away($googleFormUrl);
        }

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
        $signedCode = VisitorPreRegister::confirmationCodeFromSignedRequest($request);
        if ($signedCode !== null) {
            return view('visitors.pre-register-success', [
                'confirmationCode' => $signedCode,
            ]);
        }

        $code = session('pre_register_code');
        $visitorId = session('pre_register_visitor_id');

        if (! is_string($code) || $code === '' || ! is_numeric($visitorId)) {
            return redirect()->route('visitor.pre-register');
        }

        return view('visitors.pre-register-success', [
            'confirmationCode' => $code,
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
