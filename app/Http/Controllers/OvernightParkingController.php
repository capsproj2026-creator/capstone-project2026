<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\OvernightParkingRequest;
use App\Services\OvernightParkingService;
use App\Services\SystemSettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * GSU approval queue (admin) and the overnight check of vehicles still inside campus (admin and guard).
 */
class OvernightParkingController extends Controller
{
    public function index(Request $request, OvernightParkingService $overnight): View
    {
        $isAdmin = $request->routeIs('admin.*');
        $tab = $isAdmin && $request->query('tab') === 'decided' ? 'decided' : 'pending';

        $requests = null;
        $pendingCount = 0;
        if ($isAdmin) {
            $pendingCount = OvernightParkingRequest::query()
                ->where('status', OvernightParkingRequest::STATUS_PENDING)
                ->count();

            $requests = OvernightParkingRequest::query()
                ->with('user')
                ->when(
                    $tab === 'pending',
                    fn ($q) => $q->where('status', OvernightParkingRequest::STATUS_PENDING)->orderBy('first_night'),
                    fn ($q) => $q->where('status', '!=', OvernightParkingRequest::STATUS_PENDING)->orderByDesc('created_at')
                )
                ->paginate(20)
                ->withQueryString();
        }

        return view('overnight.index', [
            'layout' => $isAdmin ? 'layouts.admin' : 'layouts.guard',
            'isAdmin' => $isAdmin,
            'tab' => $tab,
            'requests' => $requests,
            'pendingCount' => $pendingCount,
            'check' => $overnight->overnightCheck(),
        ]);
    }

    public function decide(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approve,deny'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $overnightRequest = OvernightParkingRequest::query()->findOrFail($id);
        if ($overnightRequest->status !== OvernightParkingRequest::STATUS_PENDING) {
            return back()->with('error', 'This request has already been decided.');
        }

        $approve = $data['decision'] === 'approve';
        $remarks = filled($data['remarks'] ?? null) ? trim($data['remarks']) : null;

        $overnightRequest->update([
            'status' => $approve ? OvernightParkingRequest::STATUS_APPROVED : OvernightParkingRequest::STATUS_DENIED,
            'reviewed_by' => (int) $request->user()->id,
            'reviewed_at' => now(),
            'review_remarks' => $remarks,
        ]);

        if ($overnightRequest->user_id && app(SystemSettingService::class)->bool('send_violation_notifications', true)) {
            Notification::query()->create([
                'user_id' => (int) $overnightRequest->user_id,
                'sender_id' => (int) $request->user()->id,
                'title' => $approve ? 'Overnight Parking Approved' : 'Overnight Parking Not Approved',
                'message' => ($approve
                    ? 'The GSU approved overnight parking for '.$overnightRequest->plate_number.' ('.$overnightRequest->nightsLabel().').'
                    : 'The GSU did not approve overnight parking for '.$overnightRequest->plate_number.' ('.$overnightRequest->nightsLabel().').')
                    .($remarks ? ' Remarks: '.$remarks : ''),
                'type' => 'System',
                'is_read' => false,
                'created_at' => now(),
            ]);
        }

        return back()->with('success', $approve ? 'Overnight parking approved.' : 'Overnight parking request denied.');
    }
}
