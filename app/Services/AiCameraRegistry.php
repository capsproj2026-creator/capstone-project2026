<?php

namespace App\Services;

use Database\Seeders\AiTestLotSeeder;

/**
 * Server-side registry of AI CCTV cameras (credentials never leave config/.env).
 */
class AiCameraRegistry
{
    /**
     * @return list<array{
     *   id: string,
     *   name: string,
     *   location: string,
     *   area_id: int,
     *   stream_path: string,
     *   stream_url: string,
     *   enabled: bool
     * }>
     */
    public function cameras(): array
    {
        $configured = config('services.ai_parking.cameras');
        if (is_array($configured) && $configured !== []) {
            return array_values(array_filter(
                array_map(fn ($row) => $this->normalizeCamera(is_array($row) ? $row : []), $configured),
                fn ($row) => $row !== null
            ));
        }

        // Backward-compatible single camera from legacy env keys.
        $legacy = $this->normalizeCamera([
            'id' => env('AI_CAMERA_ID', 'CAM-AI-1'),
            'name' => 'AI Test Lot',
            'location' => 'Parking Lot A',
            'area_id' => (int) config('services.ai_parking.area_id', AiTestLotSeeder::AREA_ID),
            'stream_path' => '/stream.mjpg',
            'stream_url' => config('services.ai_parking.stream_browser_url')
                ?: config('services.ai_parking.stream_url'),
            'enabled' => true,
        ]);

        return $legacy ? [$legacy] : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $cameraId): ?array
    {
        $needle = strtoupper(trim($cameraId));
        foreach ($this->cameras() as $camera) {
            if (strtoupper($camera['id']) === $needle) {
                return $camera;
            }
        }

        return null;
    }

    /**
     * Resolve occupancy target area from camera_id (never trust client area_id alone).
     */
    public function resolveAreaId(?string $cameraId, ?int $postedAreaId = null): int
    {
        if ($cameraId) {
            $camera = $this->find($cameraId);
            if ($camera) {
                return (int) $camera['area_id'];
            }
        }

        // Legacy posts without a registered camera_id: pin to primary lot.
        return (int) config('services.ai_parking.area_id', AiTestLotSeeder::AREA_ID);
    }

    /**
     * @return list<int>
     */
    public function monitoredAreaIds(): array
    {
        return array_values(array_unique(array_map(
            fn (array $c) => (int) $c['area_id'],
            $this->cameras()
        )));
    }

    public function isMonitoredArea(int $areaId): bool
    {
        return in_array($areaId, $this->monitoredAreaIds(), true);
    }

    public function primaryCameraId(): string
    {
        $cameras = $this->cameras();

        return $cameras[0]['id'] ?? 'CAM-AI-1';
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    private function normalizeCamera(array $row): ?array
    {
        $id = trim((string) ($row['id'] ?? ''));
        if ($id === '') {
            return null;
        }

        $enabled = filter_var($row['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN);
        if (! $enabled) {
            return null;
        }

        $base = rtrim((string) config('services.ai_parking.stream_base', 'http://127.0.0.1:8090'), '/');
        $path = trim((string) ($row['stream_path'] ?? ''));
        if ($path === '') {
            $path = '/'.$id.'/stream.mjpg';
        } elseif ($path[0] !== '/') {
            $path = '/'.$path;
        }

        $streamUrl = trim((string) ($row['stream_url'] ?? ''));
        if ($streamUrl === '') {
            $streamUrl = $base.$path;
        }

        $aiPath = trim((string) ($row['ai_stream_path'] ?? ''));
        if ($aiPath === '') {
            if (str_ends_with($path, '/stream.mjpg') && $path !== '/stream.mjpg') {
                $aiPath = substr($path, 0, -strlen('stream.mjpg')).'ai/stream.mjpg';
            } else {
                $aiPath = '/'.$id.'/ai/stream.mjpg';
            }
        } elseif ($aiPath[0] !== '/') {
            $aiPath = '/'.$aiPath;
        }
        $aiStreamUrl = trim((string) ($row['ai_stream_url'] ?? ''));
        if ($aiStreamUrl === '') {
            $aiStreamUrl = $base.$aiPath;
        }

        return [
            'id' => $id,
            'name' => trim((string) ($row['name'] ?? $id)) ?: $id,
            'location' => trim((string) ($row['location'] ?? 'Campus')) ?: 'Campus',
            'area_id' => (int) ($row['area_id'] ?? AiTestLotSeeder::AREA_ID),
            'stream_path' => $path,
            'stream_url' => $streamUrl,
            'ai_stream_path' => $aiPath,
            'ai_stream_url' => $aiStreamUrl,
            'enabled' => true,
        ];
    }
}
