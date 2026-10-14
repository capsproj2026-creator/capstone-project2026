<?php

namespace App\Http\Controllers;

use App\Models\CameraClip;
use App\Services\IncidentClipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CameraClipController extends Controller
{
    public function index(Request $request): View
    {
        $isGuard = str_contains((string) $request->route()?->getName(), 'guard.');

        return view('cameras.clips', [
            'clips' => CameraClip::query()->orderByDesc('created_at')->paginate(20),
            'playRoute' => $isGuard ? 'guard.clips.play' : 'admin.clips.play',
        ]);
    }

    public function save(Request $request, IncidentClipService $clips): RedirectResponse
    {
        $validated = $request->validate([
            'camera_id' => ['required', 'string', 'max:64'],
        ]);

        $clip = $clips->capture(
            $validated['camera_id'],
            null,
            'manual',
            (int) $request->user()->id,
        );

        $back = str_contains((string) $request->route()?->getName(), 'guard.')
            ? 'guard.clips'
            : 'admin.clips';

        if (! $clip) {
            return redirect()
                ->route($back)
                ->with('error', 'No clip was saved. The camera needs a live picture from the last minute.');
        }

        return redirect()
            ->route($back)
            ->with('success', 'Incident clip saved.');
    }

    public function play(int $id): StreamedResponse
    {
        $clip = CameraClip::query()->findOrFail($id);
        $path = (string) $clip->file_path;
        if ($path === '' || str_contains($path, '..') || ! str_starts_with($path, 'incident-clips/') || ! Storage::disk('private')->exists($path)) {
            abort(404);
        }

        return Storage::disk('private')->response($path, basename($path), [
            'Content-Type' => 'video/mp4',
        ]);
    }
}
