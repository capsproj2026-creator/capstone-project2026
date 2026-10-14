<?php

namespace App\Services;

use App\Models\CameraClip;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class IncidentClipService
{
    public function capture(string $cameraId, ?string $violationLogId = null, string $source = 'manual', ?int $savedBy = null): ?CameraClip
    {
        $cameraId = trim($cameraId);
        if ($cameraId === '') {
            return null;
        }

        $filename = 'clip-'.now()->format('YmdHis').'-'.Str::lower(Str::random(8)).'.mp4';
        $relative = 'incident-clips/'.$filename;
        $directory = storage_path('app/private/incident-clips');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $base = app(AiParkingHealthService::class)->pythonServiceBaseUrl();
        if ($base === null) {
            Log::warning('Incident clip skipped: AI parking service is not configured.');

            return null;
        }

        $token = trim((string) config('services.ai_parking.api_token', ''));

        try {
            $response = Http::connectTimeout(2)
                ->timeout(8)
                ->acceptJson()
                ->withHeaders($token !== '' ? ['X-AI-TOKEN' => $token] : [])
                ->post($base.'/save-clip', [
                    'camera_id' => $cameraId,
                    'filename' => $filename,
                ]);
        } catch (\Throwable $e) {
            Log::warning('Incident clip request failed: '.$e->getMessage());

            return null;
        }

        if (! $response->ok() || ! $response->json('ok')) {
            Log::warning('Incident clip was not saved.', [
                'camera' => $cameraId,
                'body' => $response->json(),
            ]);

            return null;
        }

        $storedName = basename((string) $response->json('filename', $filename));
        if ($storedName === '' || ! str_ends_with(strtolower($storedName), '.mp4')) {
            return null;
        }

        $relative = 'incident-clips/'.$storedName;
        if (! is_file(storage_path('app/private/'.$relative))) {
            return null;
        }

        return CameraClip::query()->create([
            'camera_id' => $cameraId,
            'violation_log_id' => $violationLogId,
            'file_path' => $relative,
            'frame_count' => (int) $response->json('frames', 0),
            'source' => $source,
            'saved_by' => $savedBy,
            'created_at' => now(),
        ]);
    }
}
