<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Services\VisitorService;
use App\Support\VisitorPreRegisterQr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class VisitorPreRegistrationController extends Controller
{
    public function show(): View
    {
        return view('visitors.pre-register', [
            'vehicles' => Vehicle::query()->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request, VisitorService $visitors): RedirectResponse
    {
        if (filled($request->input('website'))) {
            abort(422, 'Invalid submission.');
        }

        $validated = $this->validatedFields($request);

        $visitor = $visitors->preRegister($validated);

        return redirect()
            ->route('visitor.pre-register.success')
            ->with('pre_register_visitor_id', $visitor->id)
            ->with('pre_register_code', $visitor->confirmation_code);
    }

    public function success(Request $request): View|RedirectResponse
    {
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

    /**
     * @return array<string, mixed>
     */
    private function validatedFields(Request $request): array
    {
        return $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'middle_name' => ['nullable', 'string', 'max:80'],
            'contact_number' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:120'],
            'purpose' => ['required', 'string', 'max:255'],
            'office_to_visit' => ['required', 'string', 'max:255'],
            'expected_exit_at' => ['required', 'date', 'after:now'],
            'plate_number' => ['required', 'string', 'max:20'],
            'vehicle_id' => ['required', 'integer'],
            'vehicle_color' => ['required', 'string', 'max:40'],
        ]);
    }
}
