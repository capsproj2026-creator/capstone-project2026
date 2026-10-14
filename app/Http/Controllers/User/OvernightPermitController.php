<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\OvernightPermit;
use App\Services\RegistrationAlertService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class OvernightPermitController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();

        return view('user.overnight', [
            'permits' => OvernightPermit::query()
                ->where('user_id', $user->id)
                ->orderByDesc('id')
                ->get(),
            'plate' => $user->plate_number,
        ]);
    }

    public function store(Request $request, RegistrationAlertService $alerts): RedirectResponse
    {
        $validated = $request->validate([
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ], [
            'proof.required' => 'Upload proof that the overnight stay is valid, such as a travel order.',
        ]);

        $user = $request->user();
        $path = $request->file('proof')->store('overnight-proof', 'private');

        OvernightPermit::query()->create([
            'user_id' => $user->id,
            'plate_number' => strtoupper(trim((string) $user->plate_number)),
            'starts_at' => $validated['starts_at'],
            'ends_at' => $validated['ends_at'],
            'reason' => $validated['reason'],
            'proof_path' => $path,
            'status' => OvernightPermit::STATUS_PENDING,
            'created_at' => now(),
        ]);

        $alerts->notifyAdmins(
            $user,
            'Overnight parking request',
            "{$user->displayName()} requested overnight parking for plate {$user->plate_number} and uploaded proof.",
        );

        return redirect()
            ->route('user.overnight')
            ->with('success', 'Request submitted. A guard can confirm it only after GSU approves the proof.');
    }
}
