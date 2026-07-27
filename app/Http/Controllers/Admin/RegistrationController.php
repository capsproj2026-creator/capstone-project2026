<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\RegistrationStatusMail;
use App\Models\Notification;
use App\Models\User;
use App\Services\NavigationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class RegistrationController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status', User::STATUS_PENDING);
        $allowedStatuses = [User::STATUS_PENDING, User::STATUS_GRANTED, User::STATUS_DENIED];

        if (! in_array($status, $allowedStatuses, true)) {
            $status = User::STATUS_PENDING;
        }

        return view('admin.registrations', [
            'statusFilter' => $status,
            'pendingCount' => User::query()->where('status', User::STATUS_PENDING)->count(),
            'approvedCount' => User::query()->where('status', User::STATUS_GRANTED)->count(),
            'declinedCount' => User::query()->where('status', User::STATUS_DENIED)->count(),
            'requests' => User::query()
                ->with('role')
                ->where('status', $status)
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    public function approve(int $id): RedirectResponse
    {
        $user = User::query()->with('role')->findOrFail($id);

        if ($user->status !== User::STATUS_PENDING) {
            return redirect()
                ->route('admin.registrations', ['status' => User::STATUS_PENDING])
                ->with('error', 'This registration is no longer pending.');
        }

        $updates = ['status' => User::STATUS_GRANTED];

        // Admins and guards receive portal + gate access immediately.
        if (in_array((int) $user->user_role_id, [
            NavigationService::ROLE_ADMIN,
            NavigationService::ROLE_GUARD,
        ], true)) {
            $updates['Gate_access'] = User::GATE_ACCESS_GRANTED;
        }

        $user->update($updates);

        // Notify the vehicle owner by email (Approved).
        $this->updateRegistrationStatus($user, 'Approved');

        $roleName = $user->role?->role_name ?? 'User';

        Notification::query()->create([
            'user_id' => $user->id,
            'sender_id' => auth()->id(),
            'title' => 'Account Approved',
            'message' => "Your account registration as {$roleName} has been approved. You now have campus access.",
            'type' => 'System',
            'is_read' => false,
            'created_at' => now(),
        ]);

        return redirect()
            ->route('admin.registrations', ['status' => User::STATUS_PENDING])
            ->with('success', 'User approved.');
    }

    public function decline(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $user = User::query()->with('role')->findOrFail($id);

        if ($user->status !== User::STATUS_PENDING) {
            return redirect()
                ->route('admin.registrations', ['status' => User::STATUS_PENDING])
                ->with('error', 'This registration is no longer pending.');
        }

        $user->update(['status' => User::STATUS_DENIED]);

        $roleName = $user->role?->role_name ?? 'User';

        // Notify the vehicle owner by email (Declined), including optional admin remarks.
        $this->updateRegistrationStatus(
            $user,
            'Declined',
            $validated['remarks'] ?? null
        );

        Notification::query()->create([
            'user_id' => $user->id,
            'sender_id' => auth()->id(),
            'title' => 'Account Declined',
            'message' => "Your registration as {$roleName} has been declined. Please check your details and try again.",
            'type' => 'System',
            'is_read' => false,
            'created_at' => now(),
        ]);

        return redirect()
            ->route('admin.registrations', ['status' => User::STATUS_PENDING])
            ->with('success', 'User declined.');
    }

    /**
     * Sends the registration approval/decline email to the vehicle owner.
     *
     * Mail::to()   = recipient email address
     * ->send()     = dispatch the email immediately through the configured mailer (Brevo SMTP)
     * new Mailable = builds the subject + Blade view with the data we pass in
     */
    private function updateRegistrationStatus(User $user, string $status, ?string $remarks = null): void
    {
        try {
            Mail::to($user->email)->send(new RegistrationStatusMail(
                ownerName: $user->fullname,
                status: $status,
                remarks: $remarks,
            ));
        } catch (\Throwable $e) {
            // Registration still succeeds even if email fails — error is logged for admins.
            report($e);
        }
    }
}
