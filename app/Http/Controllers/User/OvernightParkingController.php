<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\OvernightParkingRequest;
use App\Models\ParkingArea;
use App\Models\User;
use App\Services\NavigationService;
use App\Services\OvernightParkingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Employees ask the GSU for permission to leave a vehicle parked overnight.
 */
class OvernightParkingController extends Controller
{
    private const MAX_NIGHTS = 14;

    public function index(Request $request, OvernightParkingService $overnight): View
    {
        $user = $this->staffOrAbort($request);

        return view('user.overnight-parking', [
            'requests' => OvernightParkingRequest::query()
                ->where('user_id', $user->id)
                ->orderByDesc('created_at')
                ->limit(30)
                ->get(),
            'areas' => ParkingArea::visibleToRole('Staff'),
            'plate' => (string) ($user->plate_number ?? ''),
            'tonight' => $overnight->currentNight(),
            'maxNights' => self::MAX_NIGHTS,
        ]);
    }

    public function store(Request $request, OvernightParkingService $overnight): RedirectResponse
    {
        $user = $this->staffOrAbort($request);

        $data = $request->validate([
            'first_night' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$overnight->currentNight()],
            'last_night' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:first_night'],
            'area_id' => ['nullable', 'integer'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'first_night.after_or_equal' => 'The first night cannot be in the past.',
        ]);

        $plate = trim((string) ($user->plate_number ?? ''));
        if ($plate === '') {
            return back()->withInput()->withErrors(['first_night' => 'Your account has no registered plate number.']);
        }

        $first = $data['first_night'];
        $last = $data['last_night'] ?? $first;
        if (\Carbon\Carbon::parse($first)->diffInDays(\Carbon\Carbon::parse($last)) + 1 > self::MAX_NIGHTS) {
            return back()->withInput()->withErrors(['last_night' => 'A request can cover at most '.self::MAX_NIGHTS.' nights.']);
        }

        $overlaps = OvernightParkingRequest::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [OvernightParkingRequest::STATUS_PENDING, OvernightParkingRequest::STATUS_APPROVED])
            ->where('first_night', '<=', $last)
            ->where('last_night', '>=', $first)
            ->exists();
        if ($overlaps) {
            return back()->withInput()->withErrors(['first_night' => 'You already have a pending or approved request for one of these nights.']);
        }

        $area = filled($data['area_id'] ?? null)
            ? ParkingArea::visibleToRole('Staff')->firstWhere('id', (int) $data['area_id'])
            : null;

        OvernightParkingRequest::query()->create([
            'user_id' => (int) $user->id,
            'plate_number' => $plate,
            'area_id' => $area ? (int) $area->id : null,
            'area_name' => $area?->area_name,
            'first_night' => $first,
            'last_night' => $last,
            'reason' => trim($data['reason']),
            'status' => OvernightParkingRequest::STATUS_PENDING,
            'created_at' => now(),
        ]);

        return redirect()->route('user.overnight-parking')
            ->with('success', 'Request sent to the GSU. You will be notified once it is reviewed.');
    }

    public function cancel(Request $request, int $id): RedirectResponse
    {
        $user = $this->staffOrAbort($request);

        $overnightRequest = OvernightParkingRequest::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        if (! in_array($overnightRequest->status, [OvernightParkingRequest::STATUS_PENDING, OvernightParkingRequest::STATUS_APPROVED], true)) {
            return back()->with('error', 'This request can no longer be cancelled.');
        }

        $overnightRequest->update(['status' => OvernightParkingRequest::STATUS_CANCELLED]);

        return back()->with('success', 'Overnight parking request cancelled.');
    }

    private function staffOrAbort(Request $request): User
    {
        $user = $request->user();
        abort_unless((int) $user->user_role_id === NavigationService::ROLE_STAFF, 403, 'Overnight parking requests are for employees only.');

        return $user;
    }
}
