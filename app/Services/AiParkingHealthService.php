<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class AiParkingHealthService
{
    public function upstreamStreamUrl(): ?string
    {
        $url = trim((string) config('services.ai_parking.stream_url', ''));

        return $url !== '' ? $url : null;
    }

    public function streamProxyRouteName(bool $isGuard): string
    {
        return $isGuard ? 'guard.ai-parking.stream' : 'admin.ai-parking.stream';
    }

    public function streamProxyUrl(bool $isGuard = true): ?string
    {
        if ($this->upstreamStreamUrl() === null) {
            return null;
        }

        return route($this->streamProxyRouteName($isGuard));
    }

    /**
     * URL for browser <img> MJPEG tags. Uses direct Python stream by default because
     * proxying through php artisan serve blocks other requests and freezes the feed.
     */
    public function streamBrowserUrl(): ?string
    {
        $browser = trim((string) config('services.ai_parking.stream_browser_url', ''));
        if ($browser !== '') {
            return $browser;
        }

        return $this->upstreamStreamUrl();
    }

    public function isStreamReachable(?string $url = null): bool
    {
        $url ??= $this->upstreamStreamUrl();
        if ($url === null) {
            return false;
        }

        $cacheKey = 'ai_parking:stream_reachable:'.md5($url);

        return (bool) Cache::remember($cacheKey, now()->addSeconds(10), function () use ($url) {
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

    public function isIngestActive(?int $maxAgeSeconds = null): bool
    {
        $maxAgeSeconds ??= (int) config('services.ai_parking.ingest_stale_seconds', 45);
        $snapshot = app(AiParkingOccupancyService::class)->latestSnapshot();
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
     * @return array{
     *   configured: bool,
     *   stream_reachable: bool,
     *   ingest_active: bool,
     *   connected: bool,
     *   upstream_stream_url: string|null,
     *   stream_proxy_url: string|null,
     *   last_update: string|null,
     *   last_update_label: string|null
     * }
     */
    public function status(bool $isGuard = true): array
    {
        $upstream = $this->upstreamStreamUrl();
        $snapshot = app(AiParkingOccupancyService::class)->latestSnapshot();
        $streamReachable = $upstream !== null && $this->isStreamReachable($upstream);
        $ingestActive = $this->isIngestActive();

        return [
            'configured' => $upstream !== null,
            'stream_reachable' => $streamReachable,
            'ingest_active' => $ingestActive,
            'connected' => $streamReachable && $ingestActive,
            'upstream_stream_url' => $upstream,
            'stream_proxy_url' => $this->streamProxyUrl($isGuard),
            'stream_browser_url' => $this->streamBrowserUrl(),
            'last_update' => is_array($snapshot) ? ($snapshot['updated_at'] ?? null) : null,
            'last_update_label' => is_array($snapshot) ? ($snapshot['updated_at_label'] ?? null) : null,
        ];
    }
}
