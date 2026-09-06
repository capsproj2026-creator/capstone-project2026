<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class AiParkingHealthService
{
    /**
     * URL for browser MJPEG tags.
     *
     * @param  bool  $withAiOverlay  false = clean live feed; true = YOLO boxes + plates
     */
    public function streamBrowserUrl(?string $cameraId = null, bool $withAiOverlay = true): ?string
    {
        if ($cameraId) {
            $camera = app(AiCameraRegistry::class)->find($cameraId);
            if ($camera) {
                if ($withAiOverlay && ! empty($camera['ai_stream_url'])) {
                    return (string) $camera['ai_stream_url'];
                }
                if (! empty($camera['stream_url'])) {
                    return (string) $camera['stream_url'];
                }
            }
        }

        if ($withAiOverlay) {
            $browser = trim((string) config('services.ai_parking.stream_browser_url', ''));
            if ($browser !== '') {
                return $browser;
            }
        }

        return $this->upstreamStreamUrl($cameraId, $withAiOverlay);
    }

    public function upstreamStreamUrl(?string $cameraId = null, bool $withAiOverlay = false): ?string
    {
        if ($cameraId) {
            $camera = app(AiCameraRegistry::class)->find($cameraId);
            if ($camera) {
                if ($withAiOverlay && ! empty($camera['ai_stream_url'])) {
                    return (string) $camera['ai_stream_url'];
                }
                if (! empty($camera['stream_url'])) {
                    return (string) $camera['stream_url'];
                }
            }
        }

        $url = trim((string) config('services.ai_parking.stream_url', ''));

        return $url !== '' ? $url : null;
    }

    /** @deprecated use streamBrowserUrl($id, false) */
    public function upstreamStreamUrlLegacy(?string $cameraId = null): ?string
    {
        return $this->upstreamStreamUrl($cameraId, false);
    }

    public function streamProxyRouteName(bool $isGuard): string
    {
        return $isGuard ? 'guard.ai-parking.stream' : 'admin.ai-parking.stream';
    }

    public function streamProxyUrl(bool $isGuard = true, ?string $cameraId = null, bool $withAiOverlay = true): ?string
    {
        if ($this->upstreamStreamUrl($cameraId, $withAiOverlay) === null) {
            return null;
        }

        $params = $cameraId ? ['camera' => $cameraId] : [];

        return route($this->streamProxyRouteName($isGuard), $params);
    }

    /**
     * Fast JSON health URL for the Python MJPEG service (never hit endless .mjpg).
     */
    public function serviceHealthUrl(): ?string
    {
        $base = trim((string) config('services.ai_parking.stream_base', ''));
        if ($base === '') {
            $stream = $this->upstreamStreamUrl();
            if ($stream === null) {
                return null;
            }
            $parts = parse_url($stream);
            if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
                return null;
            }
            $port = isset($parts['port']) ? ':'.$parts['port'] : '';
            $base = $parts['scheme'].'://'.$parts['host'].$port;
        }

        return rtrim($base, '/').'/health';
    }

    /**
     * Local Python MJPEG/OCR service origin (127.0.0.1 only).
     */
    public function pythonServiceBaseUrl(): ?string
    {
        $health = $this->serviceHealthUrl();
        if ($health === null) {
            return null;
        }

        $parts = parse_url($health);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (! in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
            return null;
        }

        return rtrim(str_replace('/health', '', $health), '/');
    }

    public function isStreamReachable(?string $url = null): bool
    {
        // Prefer the lightweight /health JSON endpoint. Opening MJPEG with Http::get()
        // can hang until PHP max_execution_time because the multipart stream never ends.
        $healthUrl = $this->serviceHealthUrl();
        $probeUrl = $healthUrl ?? $url ?? $this->upstreamStreamUrl();
        if ($probeUrl === null) {
            return false;
        }

        $cacheKey = 'ai_parking:stream_reachable:'.md5($probeUrl);

        return (bool) Cache::remember($cacheKey, now()->addSeconds(45), function () use ($probeUrl, $healthUrl) {
            try {
                if ($healthUrl !== null && $probeUrl === $healthUrl) {
                    $payload = $this->serviceHealthPayload();

                    return is_array($payload) && (($payload['ok'] ?? false) === true || isset($payload['camera_status']));
                }

                // Fallback: tiny ranged GET — still keep timeouts aggressive.
                $response = Http::connectTimeout(1)
                    ->timeout(1.5)
                    ->withHeaders(['Range' => 'bytes=0-63'])
                    ->get($probeUrl);

                return $response->status() < 500;
            } catch (\Throwable) {
                return false;
            }
        });
    }

    /**
     * Cached JSON body from the Python MJPEG service /health endpoint.
     *
     * @return array<string, mixed>|null
     */
    public function serviceHealthPayload(): ?array
    {
        $healthUrl = $this->serviceHealthUrl();
        if ($healthUrl === null) {
            return null;
        }

        $cacheKey = 'ai_parking:service_health_json:'.md5($healthUrl);

        $cached = Cache::remember($cacheKey, now()->addSeconds(5), function () use ($healthUrl) {
            try {
                $response = Http::connectTimeout(1)
                    ->timeout(1.5)
                    ->acceptJson()
                    ->get($healthUrl);

                if (! $response->successful()) {
                    return false;
                }

                $json = $response->json();

                return is_array($json) ? $json : false;
            } catch (\Throwable) {
                return false;
            }
        });

        return is_array($cached) ? $cached : null;
    }

    /**
     * True only when the AI service reports this camera's RTSP feed is live.
     * Configured stream URLs / blank placeholder MJPEG frames do not count.
     */
    public function isCameraOnline(?string $cameraId = null): bool
    {
        $cameraId = $cameraId ?: app(AiCameraRegistry::class)->primaryCameraId();
        $payload = $this->serviceHealthPayload();
        if ($payload === null) {
            return false;
        }

        $statusMap = $payload['camera_status'] ?? null;
        if (! is_array($statusMap) || $statusMap === []) {
            // Older health payload without per-camera detail.
            return (bool) ($payload['any_online'] ?? $payload['ok'] ?? false);
        }

        $row = $statusMap[$cameraId] ?? null;
        if (! is_array($row)) {
            foreach ($statusMap as $id => $candidate) {
                if (strcasecmp((string) $id, (string) $cameraId) === 0 && is_array($candidate)) {
                    $row = $candidate;
                    break;
                }
            }
        }

        if (! is_array($row)) {
            return false;
        }

        return (bool) ($row['online'] ?? false);
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
     * Page-load safe status: uses cached /health (no MJPEG probe).
     *
     * @return array<string, mixed>
     */
    public function statusFast(bool $isGuard = true, ?string $cameraId = null): array
    {
        $upstream = $this->upstreamStreamUrl($cameraId);
        $snapshot = app(AiParkingOccupancyService::class)->latestSnapshot($cameraId);
        $ingestActive = $this->isIngestActive($cameraId);
        $cameraOnline = $upstream !== null && $this->isCameraOnline($cameraId);

        return [
            'camera_id' => $cameraId ?? app(AiCameraRegistry::class)->primaryCameraId(),
            'configured' => $upstream !== null,
            'stream_reachable' => $cameraOnline,
            'ingest_active' => $ingestActive,
            'connected' => $cameraOnline,
            'upstream_stream_url' => $upstream,
            'stream_proxy_url' => $this->streamProxyUrl($isGuard, $cameraId, true),
            'stream_browser_url' => $this->streamBrowserUrl($cameraId, true),
            'live_stream_url' => $this->streamBrowserUrl($cameraId, false),
            'ai_stream_url' => $this->streamBrowserUrl($cameraId, true),
            'last_update' => is_array($snapshot) ? ($snapshot['updated_at'] ?? null) : null,
            'last_update_label' => is_array($snapshot) ? ($snapshot['updated_at_label'] ?? null) : null,
            'vehicle_count' => is_array($snapshot) ? ($snapshot['vehicle_count'] ?? null) : null,
            'occupied' => is_array($snapshot) ? ($snapshot['occupied'] ?? null) : null,
            'available' => is_array($snapshot) ? ($snapshot['available'] ?? null) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function status(bool $isGuard = true, ?string $cameraId = null, bool $probeStream = true): array
    {
        if (! $probeStream) {
            return $this->statusFast($isGuard, $cameraId);
        }

        $upstream = $this->upstreamStreamUrl($cameraId);
        $snapshot = app(AiParkingOccupancyService::class)->latestSnapshot($cameraId);
        $ingestActive = $this->isIngestActive($cameraId);
        $cameraOnline = $upstream !== null && $this->isCameraOnline($cameraId);

        return [
            'camera_id' => $cameraId ?? app(AiCameraRegistry::class)->primaryCameraId(),
            'configured' => $upstream !== null,
            'stream_reachable' => $cameraOnline,
            'ingest_active' => $ingestActive,
            'connected' => $cameraOnline,
            'upstream_stream_url' => $upstream,
            'stream_proxy_url' => $this->streamProxyUrl($isGuard, $cameraId, true),
            'stream_browser_url' => $this->streamBrowserUrl($cameraId, true),
            'live_stream_url' => $this->streamBrowserUrl($cameraId, false),
            'ai_stream_url' => $this->streamBrowserUrl($cameraId, true),
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
    public function statusAll(bool $isGuard = true, bool $probeStream = true): array
    {
        $out = [];
        foreach (app(AiCameraRegistry::class)->cameras() as $camera) {
            $out[$camera['id']] = $this->status($isGuard, $camera['id'], $probeStream);
        }

        return $out;
    }
}
