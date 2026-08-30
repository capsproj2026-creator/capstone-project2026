<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\User;
use App\Services\NavigationService;
use App\Support\SafeUpload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegistrationResubmitController extends Controller
{
    public function show(): View|RedirectResponse
    {
        $user = Auth::user();

        if (! $user->isRemedialDeclined()) {
            return redirect()->route('user.dashboard');
        }

        return view('user.registration-resubmit', [
            'user' => $user,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();

        if (! $user->isRemedialDeclined()) {
            return redirect()->route('user.dashboard');
        }

        $validated = $request->validate([
            'driver_license' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'lto_or_photo' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'lto_cr_photo' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'id_document' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ], [
            'driver_license.required' => 'Please upload your driver\'s license.',
            'lto_or_photo.required' => 'Please upload your LTO Official Receipt (OR).',
            'lto_cr_photo.required' => 'Please upload your LTO Certificate of Registration (CR).',
            'id_document.required' => 'Please upload a valid ID document.',
        ]);

        $licenseFilename = SafeUpload::store(
            $request->file('driver_license'),
            'uploads/documents/license',
            'LIC',
            'local'
        );
        $orFilename = SafeUpload::store(
            $request->file('lto_or_photo'),
            'uploads/documents/orcr',
            'OR',
            'local'
        );
        $crFilename = SafeUpload::store(
            $request->file('lto_cr_photo'),
            'uploads/documents/cr',
            'CR',
            'local'
        );
        $idDocFilename = SafeUpload::store(
            $request->file('id_document'),
            'uploads/documents/id',
            'IDDOC',
            'local'
        );

        $user->update([
            'driver_license' => $licenseFilename,
            'lto_or_photo' => $orFilename,
            'lto_cr_photo' => $crFilename,
            'or_cr_photo' => $orFilename,
            'id_document' => $idDocFilename,
            'status' => User::STATUS_PENDING,
            'registration_state' => User::REGISTRATION_PENDING,
            'last_decline_remarks' => trim((string) ($user->decline_remarks ?? '')) ?: 'Document correction resubmitted',
            'resubmitted_at' => now(),
            'document_resubmit_count' => (int) ($user->document_resubmit_count ?? 0) + 1,
            'remedial_gate_enabled' => false,
            'remedial_expires_at' => null,
            'Gate_access' => User::GATE_ACCESS_PENDING,
        ]);

        $this->notifyAdmins($user);

        Notification::query()->create([
            'user_id' => $user->id,
            'sender_id' => null,
            'title' => 'Documents Resubmitted',
            'message' => 'Your corrected documents were submitted and are awaiting admin review. Gate access is paused until approval.',
            'type' => 'System',
            'is_read' => false,
            'created_at' => now(),
        ]);

        return redirect()
            ->route('user.dashboard')
            ->with('success', 'Documents submitted. Your registration is back under admin review.');
    }

    private function notifyAdmins(User $applicant): void
    {
        $admins = User::query()
            ->where('user_role_id', NavigationService::ROLE_ADMIN)
            ->where('status', User::STATUS_GRANTED)
            ->get();

        foreach ($admins as $admin) {
            Notification::query()->create([
                'user_id' => $admin->id,
                'sender_id' => $applicant->id,
                'title' => 'Registration Resubmitted',
                'message' => "{$applicant->displayName()} resubmitted corrected documents after a remedial decline. Please review in Registration Management.",
                'type' => 'System',
                'is_read' => false,
                'created_at' => now(),
            ]);
        }
    }
}
