<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Vehicle;
use App\Support\SafeUpload;
use App\Services\NavigationService;
use App\Support\PlateLookup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Support\PasswordRules;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(): View
    {
        $user = Auth::user();
        $user?->load('vehicleType');

        return view('profile.edit', [
            'user' => $user,
            'vehicles' => Vehicle::query()->orderBy('id')->get(),
            'dashboardRoute' => NavigationService::dashboardRouteFor($user),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = Auth::user();

        if ($request->has('update_profile')) {
            $validated = $request->validate([
                'fullname' => ['required', 'string', 'max:100'],
                'phone_number' => ['required', 'string', 'max:20'],
                'email' => [
                    'required',
                    'email:rfc,dns',
                    'max:100',
                    function ($attribute, $value, $fail) use ($user) {
                        $exists = User::query()
                            ->where('email', $value)
                            ->where('id', '<>', $user->id)
                            ->exists();

                        if ($exists) {
                            $fail('This email address is already registered.');
                        }
                    },
                ],
                'profile_pic' => ['nullable', 'image', 'max:5120'],
            ], [
                'email.required' => 'Please enter your email address.',
                'email.email' => 'Please enter a valid email address with a working domain (format and DNS check).',
            ]);

            $emailChanged = strcasecmp((string) $user->email, (string) $validated['email']) !== 0;

            $data = [
                'fullname' => $validated['fullname'],
                'phone_number' => $validated['phone_number'],
                'email' => $validated['email'],
            ];

            if ($request->hasFile('profile_pic')) {
                $oldPic = (string) ($user->profile_pic ?? '');
                $data['profile_pic'] = SafeUpload::store(
                    $request->file('profile_pic'),
                    'uploads/profile',
                    'PROF',
                    'public'
                );
                if ($oldPic !== '' && ! in_array($oldPic, ['default_avatar.png', 'N/A'], true)) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete('uploads/profile/'.$oldPic);
                }
            }

            if ($emailChanged) {
                $data['email_verified_at'] = null;
            }

            $user->update($data);

            if ($emailChanged) {
                $user->sendEmailVerificationNotification();

                return redirect()
                    ->route('verification.notice')
                    ->with('success', 'Profile updated. Please verify your new email address before continuing.');
            }

            return back()->with('success', 'Profile updated successfully!');
        }

        if ($request->has('update_vehicle')) {
            return $this->updateVehicle($request, $user);
        }

        if ($request->has('remove_vehicle')) {
            return $this->removeVehicle($user);
        }

        if ($request->has('change_password')) {
            $validated = $request->validate([
                'current_password' => ['required'],
                'new_password' => array_merge(PasswordRules::requiredWithoutConfirmed(), ['confirmed']),
            ], [
                'new_password.confirmed' => 'Password confirmation does not match.',
            ]);

            if (! password_verify($validated['current_password'], $user->password)) {
                return back()->with('error', 'Incorrect current password.');
            }

            if (password_verify($validated['new_password'], $user->password)) {
                throw ValidationException::withMessages([
                    'new_password' => 'New password must be different from your current password.',
                ]);
            }

            $user->update(['password' => Hash::make($validated['new_password'])]);

            return back()->with('success', 'Password updated successfully!');
        }

        return back();
    }

    private function updateVehicle(Request $request, User $user): RedirectResponse
    {
        $vehicleIds = Vehicle::query()->pluck('id')->map(fn ($id) => (int) $id)->all();

        $validated = $request->validate([
            'vehicle_id' => ['required', 'integer', Rule::in($vehicleIds)],
            'plate_number' => ['required', 'string', 'min:2', 'max:20', 'regex:/^[A-Za-z0-9\-\s]+$/'],
        ], [
            'vehicle_id.required' => 'Please select a vehicle type.',
            'vehicle_id.in' => 'Please select a valid vehicle type.',
            'plate_number.required' => 'Please enter your plate number.',
            'plate_number.min' => 'Please enter a valid plate number.',
            'plate_number.regex' => 'Plate number may only contain letters, numbers, spaces, and hyphens.',
        ]);

        $plate = PlateLookup::normalize($validated['plate_number']);
        if ($plate === '') {
            throw ValidationException::withMessages([
                'plate_number' => 'Please enter a valid plate number.',
            ]);
        }

        $duplicate = User::query()
            ->where('id', '<>', $user->id)
            ->whereNotNull('plate_number')
            ->where('plate_number', '!=', '')
            ->get()
            ->first(fn (User $other) => PlateLookup::normalize((string) $other->plate_number) === $plate);

        if ($duplicate) {
            throw ValidationException::withMessages([
                'plate_number' => 'This plate number is already registered to another account.',
            ]);
        }

        $user->update([
            'vehicle_id' => (int) $validated['vehicle_id'],
            'plate_number' => $plate,
        ]);

        PlateLookup::forgetIndex();

        return back()->with('success', 'Vehicle saved successfully!');
    }

    private function removeVehicle(User $user): RedirectResponse
    {
        $user->update([
            'vehicle_id' => null,
            'plate_number' => null,
        ]);

        PlateLookup::forgetIndex();

        return back()->with('success', 'Vehicle removed from your account.');
    }
}
