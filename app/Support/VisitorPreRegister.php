<?php

namespace App\Support;

use App\Models\Vehicle;
use App\Models\Visitor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class VisitorPreRegister
{
    public static function usesGoogleForm(): bool
    {
        return filled(self::googleFormUrl());
    }

    public static function googleFormUrl(): ?string
    {
        $url = trim((string) config('services.visitor_pre_register.google_form_url', ''));

        return $url !== '' ? $url : null;
    }

    public static function preRegisterUrl(): string
    {
        return self::googleFormUrl() ?? route('visitor.pre-register');
    }

    public static function webhookToken(): string
    {
        return trim((string) config('services.visitor_pre_register.webhook_token', ''));
    }

    /**
     * @return array<string, mixed>
     */
    public static function validationRules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'middle_name' => ['nullable', 'string', 'max:80'],
            'contact_number' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:120'],
            'purpose' => ['required', 'string', 'max:255'],
            'office_to_visit' => ['required', 'string', 'max:255'],
            'expected_exit_at' => ['required', 'date', 'after:now'],
            'plate_number' => ['required', 'string', 'max:20'],
            'vehicle_id' => ['nullable', 'integer', Rule::exists(Vehicle::class, 'id')],
            'vehicle_name' => ['nullable', 'string', 'max:80'],
            'vehicle_color' => ['required', 'string', 'max:40'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function resolveVehicleId(array $validated): int
    {
        if (filled($validated['vehicle_id'] ?? null)) {
            return (int) $validated['vehicle_id'];
        }

        $name = trim((string) ($validated['vehicle_name'] ?? ''));
        if ($name === '') {
            throw ValidationException::withMessages([
                'vehicle_id' => 'Provide vehicle_id or vehicle_name.',
            ]);
        }

        $vehicle = Vehicle::query()
            ->where('vehicle_name', $name)
            ->first();

        if (! $vehicle) {
            throw ValidationException::withMessages([
                'vehicle_name' => 'Unknown vehicle type: '.$name,
            ]);
        }

        return (int) $vehicle->id;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public static function payloadForService(array $validated): array
    {
        $payload = $validated;
        $payload['vehicle_id'] = self::resolveVehicleId($validated);
        unset($payload['vehicle_name']);

        return $payload;
    }

    public static function signedSuccessUrl(int $visitorId): string
    {
        return URL::temporarySignedRoute(
            'visitor.pre-register.success',
            now()->addHours(48),
            ['visitor' => $visitorId]
        );
    }

    public static function confirmationCodeFromSignedRequest(Request $request): ?string
    {
        if (! $request->hasValidSignature()) {
            return null;
        }

        $visitorId = $request->integer('visitor');
        if ($visitorId <= 0) {
            return null;
        }

        $visitor = Visitor::query()->find($visitorId);
        $code = $visitor?->confirmation_code;

        return is_string($code) && $code !== '' ? $code : null;
    }
}
