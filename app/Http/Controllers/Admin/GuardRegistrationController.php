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
use Illuminate\View\View;

class GuardRegistrationController extends Controller
{
    public function create(): View
    {
        return view('admin.create-guard');
    }

    public function store(Request $request): RedirectResponse
    {
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
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address with a working domain (format and DNS check).',
            'email.unique' => 'This email address is already registered.',
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
