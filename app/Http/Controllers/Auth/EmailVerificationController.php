<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\User;
use App\Services\NavigationService;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class EmailVerificationController extends Controller
{
    /**
     * Show the "verify your email" notice after registration or login.
     */
    public function notice(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user?->hasVerifiedEmail()) {
            if ($user->canAccessPortal()) {
                return redirect()->to(NavigationService::dashboardUrlFor($user));
            }

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerate(true);

            return redirect()->route('login')->with(
                'success',
                'Your email is verified. You can sign in once an administrator approves your account.'
            );
        }

        return view('auth.verify-email', [
            'email' => $user?->email,
        ]);
    }

    /**
     * Handle the signed verification link from the email.
     */
    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->fulfill();

            Notification::query()->create([
                'user_id' => $request->user()->id,
                'sender_id' => $request->user()->id,
                'title' => 'Email Verified',
                'message' => 'Your email address has been verified successfully. Please wait for admin approval.',
                'type' => 'System',
                'is_read' => false,
                'created_at' => now(),
            ]);
        }

        $user = $request->user()->fresh();

        if ($user->canAccessPortal()) {
            return redirect()
                ->to(NavigationService::dashboardUrlFor($user))
                ->with('success', 'Your email has been verified successfully.');
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerate(true);

        return redirect()->route('login')->with(
            'success',
            'Email verified successfully. You can sign in once your account is approved by an administrator.'
        );
    }

    /**
     * Resend the Laravel verification email (signed link).
     */
    public function send(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            if ($request->user()->canAccessPortal()) {
                return redirect()->to(NavigationService::dashboardUrlFor($request->user()));
            }

            return redirect()->route('login')->with(
                'success',
                'Your email is already verified. You can sign in once approved.'
            );
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('success', 'A new verification email has been sent to '.$request->user()->email.'.');
    }

    /**
     * Signed-out form: request a fresh verification link by email address.
     */
    public function resendForm(): View
    {
        return view('auth.resend-verification');
    }

    /**
     * Send a new verification link without signing in.
     * Always answers the same way so the form cannot be used to probe accounts.
     */
    public function resend(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        try {
            $user = User::query()->where('email', $validated['email'])->first();
        } catch (\Throwable $e) {
            report($e);

            return back()
                ->with('error', 'Database connection is not available. Please try again.')
                ->onlyInput('email');
        }

        if ($user && ! $user->hasVerifiedEmail() && ! $user->isTemporaryAccount()) {
            try {
                $user->sendEmailVerificationNotification();
            } catch (\Throwable $e) {
                report($e);

                return back()
                    ->with('error', 'We could not send the verification email. Please try again in a minute.')
                    ->onlyInput('email');
            }
        }

        return back()->with(
            'success',
            'If that email is registered and not yet verified, we sent a new verification link. Check your inbox and spam folder.'
        );
    }
}
