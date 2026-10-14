<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\NavigationService;
use App\Services\SequenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class GuardRegistrationController extends Controller
{
    public function create(): RedirectResponse
    {
        return redirect()->route('admin.settings', ['section' => 'admins']);
    }

    public function store(Request $request): RedirectResponse
    {
        // Settings modal posts "name"; the dedicated page posts "fullname".
        if (! $request->filled('fullname') && $request->filled('name')) {
            $request->merge([
                'fullname' => trim((string) $request->input('name')),
            ]);
        }

        $validated = $request->validate([
            'fullname' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'email:rfc,dns',
                'max:100',
                Rule::unique(User::class, 'email'),
            ],
            'phone_number' => ['required', 'string', 'max:20'],
            'id_number' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9]+$/', Rule::unique(User::class, 'id_number')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'fullname.required' => 'Please enter the guard\'s full name.',
            'email.required' => 'Please enter an email address.',
            'email.email' => 'Please enter a valid email address with a working domain (format and DNS check).',
            'email.unique' => 'This email address is already registered.',
            'phone_number.required' => 'Please enter a phone number.',
            'id_number.required' => 'Please enter an ID number.',
            'id_number.unique' => 'This ID number is already registered.',
            'password.confirmed' => 'Password confirmation does not match.',
        ]);

        User::query()->create([
            'id' => SequenceService::next('users'),
            'fullname' => $validated['fullname'],
            'email' => $validated['email'],
            'phone_number' => $validated['phone_number'],
            'password' => Hash::make($validated['password']),
            'user_role_id' => NavigationService::ROLE_GUARD,
            'id_number' => $validated['id_number'],
            'plate_number' => 'N/A',
            'profile_pic' => 'default_avatar.png',
            'driver_license' => 'N/A',
            'or_cr_photo' => 'N/A',
            'status' => User::STATUS_GRANTED,
            'Gate_access' => User::GATE_ACCESS_GRANTED,
            'strike_count' => 0,
            'email_verified_at' => now(),
            'created_at' => now(),
        ]);

        return redirect()
            ->route('admin.settings', ['section' => 'admins'])
            ->with('success', 'Guard account created successfully.');
    }
}
