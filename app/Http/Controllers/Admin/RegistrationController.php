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
        $status = $request->query('status', 'All');
        $registrationStatuses = [User::STATUS_PENDING, User::STATUS_GRANTED, User::STATUS_DENIED];
        $allowedFilters = array_merge(['All'], $registrationStatuses);

        if (! in_array($status, $allowedFilters, true)) {
            $status = 'All';
        }

        $ownerRoles = [NavigationService::ROLE_STUDENT, NavigationService::ROLE_STAFF];

        $pendingCount = User::query()
            ->where('status', User::STATUS_PENDING)
            ->whereIn('user_role_id', $ownerRoles)
            ->count();
        $approvedCount = User::query()
            ->where('status', User::STATUS_GRANTED)
            ->whereIn('user_role_id', $ownerRoles)
            ->count();
        $declinedCount = User::query()
            ->where('status', User::STATUS_DENIED)
            ->whereIn('user_role_id', $ownerRoles)
            ->count();

        $requestsQuery = User::query()->with('role')
            ->whereIn('user_role_id', [
                NavigationService::ROLE_STUDENT,
                NavigationService::ROLE_STAFF,
            ])
            ->orderByDesc('id');

        if ($status === 'All') {
            $requestsQuery->whereIn('status', $registrationStatuses);
        } else {
            $requestsQuery->where('status', $status);
        }

        return view('admin.registrations', [
            'statusFilter' => $status,
            'allCount' => $pendingCount + $approvedCount + $declinedCount,
            'pendingCount' => $pendingCount,
            'approvedCount' => $approvedCount,
            'declinedCount' => $declinedCount,
            'requests' => $requestsQuery->get(),
        ]);
    }

    public function approve(int $id): RedirectResponse
    {
        $user = User::query()->with('role')->findOrFail($id);

        if ($user->isTemporaryAccount()) {
            return redirect()
                ->route('admin.registrations', ['status' => User::STATUS_PENDING])
                ->with('error', 'This student or faculty has not completed registration yet.');
        }

        if ($user->status !== User::STATUS_PENDING) {
            return redirect()
                ->route('admin.registrations', ['status' => 'All'])
                ->with('error', 'This registration is no longer pending.');
        }

        $updates = ['status' => User::STATUS_GRANTED];

        /*
         * TODO: Enable payment-gated approval later (do not ship a payment product yet).
         * When payment_status is not "paid", keep the account Pending and flash:
         * "Cannot approve until payment is recorded."
         * Follow-up: admin "Mark as paid" action that sets payment_status, payment_reference, paid_at.
         *
         * if (($user->payment_status ?? null) !== 'paid') {
         *     return redirect()
         *         ->route('admin.registrations', ['status' => 'All'])
         *         ->with('error', 'Cannot approve until payment is recorded.');
         * }
         */

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
        $gateGranted = ($user->fresh()?->Gate_access ?? $user->Gate_access) === User::GATE_ACCESS_GRANTED;
        $accessNote = $gateGranted
            ? 'You now have campus access.'
            : 'Campus entry is enabled after RFID / gate access is granted.';

        Notification::query()->create([
            'user_id' => $user->id,
            'sender_id' => auth()->id(),
            'title' => 'Account Approved',
            'message' => "Your account registration as {$roleName} has been approved. {$accessNote}",
            'type' => 'System',
            'is_read' => false,
            'created_at' => now(),
        ]);

        return redirect()
            ->route('admin.registrations', ['status' => 'All'])
            ->with('success', 'User approved.');
    }

    public function decline(Request $request, int $id): RedirectResponse
    {
        $request->merge([
            'remarks' => trim((string) $request->input('remarks', '')),
        ]);

        $validated = $request->validate([
            'remarks' => ['required', 'string', 'min:3', 'max:500'],
        ], [
            'remarks.required' => 'Please enter a reason before declining this registration.',
            'remarks.min' => 'Please enter a reason of at least 3 characters before declining.',
        ]);

        $user = User::query()->with('role')->findOrFail($id);

        $wasTemporary = $user->isTemporaryAccount();
        if ($wasTemporary) {
            app(\App\Services\TemporaryRfidService::class)->expireAndUnbind($user);
        }

        if ($user->status !== User::STATUS_PENDING && ! $wasTemporary) {
            return redirect()
                ->route('admin.registrations', ['status' => 'All'])
                ->with('error', 'This registration is no longer pending.');
        }

        $updates = [
            'status' => User::STATUS_DENIED,
            'Gate_access' => User::GATE_ACCESS_DENIED,
            'declined_at' => now(),
            'decline_remarks' => trim((string) $validated['remarks']),
        ];

        if ($wasTemporary) {
            $updates['account_type'] = \App\Services\TemporaryRfidService::ACCOUNT_FULL;
            $updates['temp_conversion_token'] = null;
        }

        $user->update($updates);

        $roleName = $user->role?->role_name ?? 'User';
        $remarks = trim((string) $validated['remarks']);
        $message = "Your registration as {$roleName} has been declined. You may submit a new registration after 3 days. Reason: {$remarks}";

        // Notify the vehicle owner by email (Declined), including the required admin remarks.
        $this->updateRegistrationStatus(
            $user,
            'Declined',
            $remarks
        );

        Notification::query()->create([
            'user_id' => $user->id,
            'sender_id' => auth()->id(),
            'title' => 'Account Declined',
            'message' => $message,
            'type' => 'System',
            'is_read' => false,
            'created_at' => now(),
        ]);

        return redirect()
            ->route('admin.registrations', ['status' => 'All'])
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
        $email = trim((string) ($user->email ?? ''));
        if ($email === '' || str_ends_with(strtolower($email), '.invalid')) {
            return;
        }

        try {
            Mail::to($user->email)->send(new RegistrationStatusMail(
                ownerName: $user->fullname,
                status: $status,
                remarks: $remarks,
            ));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
