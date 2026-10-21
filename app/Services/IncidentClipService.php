<?php

namespace App\Services;

use App\Models\CameraClip;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class IncidentClipService
{
    private ?string $failureMessage = null;

    public function failureMessage(): ?string
    {
        return $this->failureMessage;
    }

    public function capture(string $cameraId, ?string $violationLogId = null, string $source = 'manual', ?int $savedBy = null): ?CameraClip
    {
        $this->failureMessage = null;
        $cameraId = trim($cameraId);
        if ($cameraId === '') {
            $this->failureMessage = 'No camera was selected.';

            return null;
        }

        $filename = 'clip-'.now()->format('YmdHis').'-'.Str::lower(Str::random(8)).'.mp4';
        $directory = storage_path('app/private/incident-clips');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $base = app(AiParkingHealthService::class)->pythonServiceBaseUrl();
        if ($base === null) {
            $this->failureMessage = 'AI parking service is not configured on this server.';
            Log::warning('Incident clip skipped: AI parking service is not configured.');

            return null;
        }

        $token = trim((string) config('services.ai_parking.api_token', ''));
        $headers = $token !== '' ? ['X-AI-TOKEN' => $token] : [];

        try {
            $response = Http::connectTimeout(3)
                ->timeout(45)
                ->acceptJson()
                ->withHeaders($headers)
                ->post($base.'/save-clip', [
                    'camera_id' => $cameraId,
                    'filename' => $filename,
                ]);
        } catch (\Throwable $e) {
            $this->failureMessage = 'Could not reach the AI camera service to save a clip.';
            Log::warning('Incident clip request failed: '.$e->getMessage());

            return null;
        }

        if (! $response->ok() || ! $response->json('ok')) {
            $aiMessage = trim((string) ($response->json('message') ?? ''));
            $this->failureMessage = $aiMessage !== ''
                ? $aiMessage
                : 'No clip was saved. The camera needs a live picture from the last minute.';
            Log::warning('Incident clip was not saved.', [
                'camera' => $cameraId,
                'body' => $response->json(),
            ]);

            return null;
        }

        $storedName = basename((string) $response->json('filename', $filename));
        if ($storedName === '' || ! str_ends_with(strtolower($storedName), '.mp4')) {
            $this->failureMessage = 'AI returned an invalid clip filename.';

            return null;
        }

        $relative = 'incident-clips/'.$storedName;
        $localPath = storage_path('app/private/'.$relative);

        // Same machine: AI already wrote into Laravel storage. Split deploy
        // (iscvms.com ↔ campus AI): pull the MP4 over the AI HTTP API.
        if (! is_file($localPath) || filesize($localPath) < 32) {
            if (! $this->pullClipFromAi($base, $storedName, $localPath, $headers)) {
                return null;
            }
        }

        if (! is_file($localPath) || filesize($localPath) < 32) {
            $this->failureMessage = 'Clip was created by the AI service but could not be stored on this server.';

            return null;
        }

        return CameraClip::query()->create([
            'camera_id' => (string) ($response->json('camera_id') ?: $cameraId),
            'violation_log_id' => $violationLogId,
            'file_path' => $relative,
            'frame_count' => (int) $response->json('frames', 0),
            'source' => $source,
            'saved_by' => $savedBy,
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function pullClipFromAi(string $base, string $storedName, string $localPath, array $headers): bool
    {
        try {
            $file = Http::connectTimeout(3)
                ->timeout(60)
                ->withHeaders($headers)
                ->get($base.'/incident-clip/'.$storedName);
        } catch (\Throwable $e) {
            $this->failureMessage = 'Clip was created on the camera PC but could not be downloaded to this server.';
            Log::warning('Incident clip download failed: '.$e->getMessage());

            return false;
        }

        if (! $file->ok() || strlen($file->body()) < 32) {
            $this->failureMessage = 'Clip was created on the camera PC but could not be downloaded to this server.';
            Log::warning('Incident clip download rejected.', [
                'status' => $file->status(),
                'bytes' => strlen($file->body()),
            ]);

            return false;
        }

        $dir = dirname($localPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (file_put_contents($localPath, $file->body()) === false) {
            $this->failureMessage = 'Could not write the incident clip on this server.';

            return false;
        }

        return true;
    }
}
