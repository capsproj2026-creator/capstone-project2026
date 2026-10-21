<?php

namespace App\Http\Controllers;

use App\Models\CameraClip;
use App\Services\AiCameraRegistry;
use App\Services\IncidentClipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CameraClipController extends Controller
{
    public function index(Request $request, AiCameraRegistry $registry): View
    {
        $isGuard = str_contains((string) $request->route()?->getName(), 'guard.');
        $locations = [];
        foreach ($registry->cameras() as $camera) {
            $id = strtoupper((string) ($camera['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $locations[$id] = (string) ($camera['location'] ?? $camera['name'] ?? $id);
        }

        return view('cameras.clips', [
            'layout' => $isGuard ? 'layouts.guard' : 'layouts.portal',
            'clips' => CameraClip::query()->orderByDesc('created_at')->paginate(20),
            'playRoute' => $isGuard ? 'guard.clips.play' : 'admin.clips.play',
            'saveRoute' => 'guard.clips.save',
            'canSave' => $isGuard,
            'cameras' => $isGuard ? $registry->cameras() : [],
            'cameraLocations' => $locations,
        ]);
    }

    public function save(Request $request, IncidentClipService $clips): RedirectResponse
    {
        abort_unless(
            str_contains((string) $request->route()?->getName(), 'guard.'),
            403,
            'Only guards can save incident clips.'
        );

        $validated = $request->validate([
            'camera_id' => ['required', 'string', 'max:64'],
        ]);

        $clip = $clips->capture(
            $validated['camera_id'],
            null,
            'manual',
            (int) $request->user()->id,
        );

        if (! $clip) {
            return redirect()
                ->route('guard.clips')
                ->with('error', $clips->failureMessage() ?? 'No clip was saved. The camera needs a live picture from the last minute.');
        }

        return redirect()
            ->route('guard.clips')
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
