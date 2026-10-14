<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\OvernightPermit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OvernightPermitController extends Controller
{
    public function index(): View
    {
        return view('admin.overnight', [
            'permits' => OvernightPermit::query()->with('user')->orderByDesc('id')->paginate(20),
        ]);
    }

    public function decide(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'review_note' => ['nullable', 'string', 'max:500'],
        ]);

        $permit = OvernightPermit::query()->with('user')->findOrFail($id);
        if ($permit->status !== OvernightPermit::STATUS_PENDING) {
            return back()->with('error', 'This request is no longer pending.');
        }

        $approved = $validated['decision'] === 'approve';
        $permit->update([
            'status' => $approved ? OvernightPermit::STATUS_APPROVED : OvernightPermit::STATUS_REJECTED,
            'reviewed_by' => $request->user()->id,
            'review_note' => $validated['review_note'] ?? null,
        ]);

        if ($permit->user) {
            Notification::query()->create([
                'user_id' => $permit->user_id,
                'sender_id' => $request->user()->id,
                'title' => $approved ? 'Overnight parking approved' : 'Overnight parking rejected',
                'message' => $approved
                    ? 'Your overnight parking request was approved. Keep the proof available for the guard.'
                    : 'Your overnight parking request was rejected.'.(filled($validated['review_note'] ?? null) ? ' Note: '.$validated['review_note'] : ''),
                'type' => 'Parking',
                'is_read' => false,
                'created_at' => now(),
            ]);
        }

        return back()->with('success', $approved ? 'Overnight permit approved.' : 'Overnight permit rejected.');
    }

    public function proof(int $id): StreamedResponse
    {
        $permit = OvernightPermit::query()->findOrFail($id);
        $path = (string) $permit->proof_path;
        if ($path === '' || str_contains($path, '..') || ! Storage::disk('private')->exists($path)) {
            abort(404);
        }

        return Storage::disk('private')->response($path);
    }
}
