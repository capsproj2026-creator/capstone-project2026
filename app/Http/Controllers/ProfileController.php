<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\SafeUpload;
use App\Services\NavigationService;
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
        return view('profile.edit', [
            'user' => Auth::user(),
            'dashboardRoute' => NavigationService::dashboardRouteFor(Auth::user()),
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
}
