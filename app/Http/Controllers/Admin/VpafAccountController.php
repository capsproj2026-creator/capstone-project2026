<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\NavigationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class VpafAccountController extends Controller
{
    public function create(): RedirectResponse
    {
        return redirect()->route('admin.settings', ['section' => 'vpaf']);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! $request->filled('fullname') && $request->filled('name')) {
            $request->merge([
                'fullname' => trim((string) $request->input('name')),
            ]);
        }

        $validated = $request->validate([
            'fullname' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc,dns', 'max:100', Rule::unique(User::class, 'email')],
            'phone_number' => ['required', 'string', 'max:20'],
            'id_number' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9]+$/', Rule::unique(User::class, 'id_number')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        User::query()->create([
            'fullname' => $validated['fullname'],
            'email' => $validated['email'],
            'phone_number' => $validated['phone_number'],
            'password' => Hash::make($validated['password']),
            'user_role_id' => NavigationService::ROLE_VPAF,
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
            ->route('admin.settings', ['section' => 'vpaf'])
            ->with('success', 'VPAF account created. That user can sign in and approve endorsements.');
    }
}
