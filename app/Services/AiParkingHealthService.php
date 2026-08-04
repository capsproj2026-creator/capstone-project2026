<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class AiParkingHealthService
{
    public function upstreamStreamUrl(?string $cameraId = null): ?string
    {
        if ($cameraId) {
            $camera = app(AiCameraRegistry::class)->find($cameraId);
            if ($camera && ! empty($camera['stream_url'])) {
                return (string) $camera['stream_url'];
            }
        }

        $url = trim((string) config('services.ai_parking.stream_url', ''));

        return $url !== '' ? $url : null;
    }

    public function streamProxyRouteName(bool $isGuard): string
    {
        return $isGuard ? 'guard.ai-parking.stream' : 'admin.ai-parking.stream';
    }

    public function streamProxyUrl(bool $isGuard = true, ?string $cameraId = null): ?string
    {
        if ($this->upstreamStreamUrl($cameraId) === null) {
            return null;
        }

        $params = $cameraId ? ['camera' => $cameraId] : [];

        return route($this->streamProxyRouteName($isGuard), $params);
    }

    /**
     * URL for browser <img> MJPEG tags. Uses direct Python stream by default because
     * proxying through php artisan serve blocks other requests and freezes the feed.
     */
    public function streamBrowserUrl(?string $cameraId = null): ?string
    {
        if ($cameraId) {
            $camera = app(AiCameraRegistry::class)->find($cameraId);
            if ($camera && ! empty($camera['stream_url'])) {
                return (string) $camera['stream_url'];
            }
        }

        $browser = trim((string) config('services.ai_parking.stream_browser_url', ''));
        if ($browser !== '') {
            return $browser;
        }

        return $this->upstreamStreamUrl($cameraId);
    }

    public function isStreamReachable(?string $url = null): bool
    {
        $url ??= $this->upstreamStreamUrl();
        if ($url === null) {
            return false;
        }

        $cacheKey = 'ai_parking:stream_reachable:'.md5($url);

        return (bool) Cache::remember($cacheKey, now()->addSeconds(45), function () use ($url) {
            try {
                $response = Http::timeout(3)
                    ->withOptions(['stream' => true, 'read_timeout' => 3])
                    ->get($url);

                if (! $response->successful()) {
                    return false;
                }

                $body = $response->toPsrResponse()->getBody();
                $chunk = $body->read(512);
                $body->close();

                return $chunk !== '';
            } catch (\Throwable) {
                return false;
            }
        });
    }

    public function isIngestActive(?string $cameraId = null, ?int $maxAgeSeconds = null): bool
    {
        $maxAgeSeconds ??= (int) config('services.ai_parking.ingest_stale_seconds', 45);
        $snapshot = app(AiParkingOccupancyService::class)->latestSnapshot($cameraId);
        if (! is_array($snapshot) || empty($snapshot['updated_at'])) {
            return false;
        }

        try {
            $updatedAt = Carbon::parse((string) $snapshot['updated_at']);
        } catch (\Throwable) {
            return false;
        }

        return $updatedAt->greaterThanOrEqualTo(now()->subSeconds($maxAgeSeconds));
    }

    /**
     * @return array<string, mixed>
     */
    public function status(bool $isGuard = true, ?string $cameraId = null): array
    {
        $upstream = $this->upstreamStreamUrl($cameraId);
        $snapshot = app(AiParkingOccupancyService::class)->latestSnapshot($cameraId);
        $streamReachable = $upstream !== null && $this->isStreamReachable($upstream);
        $ingestActive = $this->isIngestActive($cameraId);

        return [
            'camera_id' => $cameraId ?? app(AiCameraRegistry::class)->primaryCameraId(),
            'configured' => $upstream !== null,
            'stream_reachable' => $streamReachable,
            'ingest_active' => $ingestActive,
            'connected' => $streamReachable || $ingestActive,
            'upstream_stream_url' => $upstream,
            'stream_proxy_url' => $this->streamProxyUrl($isGuard, $cameraId),
            'stream_browser_url' => $this->streamBrowserUrl($cameraId),
            'last_update' => is_array($snapshot) ? ($snapshot['updated_at'] ?? null) : null,
            'last_update_label' => is_array($snapshot) ? ($snapshot['updated_at_label'] ?? null) : null,
            'vehicle_count' => is_array($snapshot) ? ($snapshot['vehicle_count'] ?? null) : null,
            'occupied' => is_array($snapshot) ? ($snapshot['occupied'] ?? null) : null,
            'available' => is_array($snapshot) ? ($snapshot['available'] ?? null) : null,
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function statusAll(bool $isGuard = true): array
    {
        $out = [];
        foreach (app(AiCameraRegistry::class)->cameras() as $camera) {
            $out[$camera['id']] = $this->status($isGuard, $camera['id']);
        }

        return $out;
    }
}
