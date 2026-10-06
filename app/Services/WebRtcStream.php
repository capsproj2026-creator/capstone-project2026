<?php

namespace App\Services;

/**
 * Public WebRTC (WHEP) addresses for the Live Cameras page.
 *
 * The browser receives only this URL. RTSP usernames and passwords stay in
 * .env for the Python AI service and the local MediaMTX config writer.
 */
class WebRtcStream
{
    public function __construct(private readonly string $whepBase = '') {}

    public static function fromConfig(): self
    {
        return new self(trim((string) config('services.ai_parking.whep_base', '')));
    }

    public function enabled(): bool
    {
        return $this->whepBase !== '';
    }

    /**
     * MediaMTX path name. Must match scripts/write_mediamtx_config.py.
     */
    public function pathFor(string $cameraId): string
    {
        $slug = strtolower(trim($cameraId));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';

        return trim($slug, '-');
    }

    public function whepUrl(string $cameraId): ?string
    {
        if (! $this->enabled()) {
            return null;
        }

        $path = $this->pathFor($cameraId);
        if ($path === '') {
            return null;
        }

        return rtrim($this->whepBase, '/').'/'.$path.'/whep';
    }
}
