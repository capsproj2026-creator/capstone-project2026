<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetMail;
use App\Models\PasswordResetToken;
use App\Models\User;
use App\Support\PasswordRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    private const EXPIRE_MINUTES = 60;

    private const THROTTLE_SECONDS = 60;

    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = $validated['email'];

        try {
            $user = User::query()->where('email', $email)->first();
        } catch (\Throwable $e) {
            report($e);

            return back()
                ->with('error', 'Database connection is not available. Please try again.')
                ->onlyInput('email');
        }

        if ($user && ! $this->recentlySent($email)) {
            PasswordResetToken::query()->where('email', $email)->delete();
            $plain = Str::random(64);
            PasswordResetToken::query()->create([
                'email' => $email,
                'token' => hash('sha256', $plain),
                'created_at' => now(),
            ]);

            try {
                Mail::to($user->email)->send(new PasswordResetMail(
                    ownerName: trim((string) ($user->fullname ?? '')) !== '' ? (string) $user->fullname : 'there',
                    resetUrl: route('password.reset', ['token' => $plain, 'email' => $user->email]),
                    expireMinutes: self::EXPIRE_MINUTES,
                ));
            } catch (\Throwable $e) {
                report($e);
                PasswordResetToken::query()->where('email', $email)->delete();

                return back()
                    ->with('error', 'We could not send the reset email. Please try again in a minute.')
                    ->onlyInput('email');
            }
        }

        return back()->with('success', 'If that email is registered, we sent a password reset link. It expires in 60 minutes.');
    }

    public function edit(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => PasswordRules::required(),
        ]);

        $email = $validated['email'];
        $record = PasswordResetToken::query()->where('email', $email)->first();
        $expiresAt = $record?->created_at?->copy()->addMinutes(self::EXPIRE_MINUTES);
        $matches = $record && hash_equals((string) $record->token, hash('sha256', $validated['token']));

        if (! $record || ! $matches || $expiresAt === null || $expiresAt->isPast()) {
            return back()
                ->with('error', 'This reset link is invalid or has expired. Request a new one.')
                ->onlyInput('email');
        }

        $user = User::query()->where('email', $email)->first();
        if (! $user) {
            PasswordResetToken::query()->where('email', $email)->delete();

            return back()
                ->with('error', 'This reset link is invalid or has expired. Request a new one.')
                ->onlyInput('email');
        }

        $user->password = Hash::make($validated['password']);
        $user->save();
        PasswordResetToken::query()->where('email', $email)->delete();

        return redirect()
            ->route('login')
            ->with('success', 'Your password has been reset. Sign in with the new password.');
    }

    private function recentlySent(string $email): bool
    {
        $latest = PasswordResetToken::query()->where('email', $email)->first();

        return $latest?->created_at !== null
            && $latest->created_at->greaterThan(now()->subSeconds(self::THROTTLE_SECONDS));
    }
}
