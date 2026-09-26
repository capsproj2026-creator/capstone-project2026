<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Admin-facing system health probes for ISCVMS turnover.
 *
 * Only reports Online when a live check succeeds — never because a URL is configured.
 * Does not expose tokens, passwords, or full database DSNs.
 */
class SystemHealthService
{
    public function __construct(
        private readonly AiParkingHealthService $aiHealth,
        private readonly AiCameraRegistry $cameras,
    ) {}

    /**
     * @return array{
     *   checked_at: string,
     *   components: list<array{id: string, label: string, status: string, detail: string}>,
     *   cameras: list<array{id: string, name: string, status: string, detail: string}>
     * }
     */
    public function snapshot(): array
    {
        $components = [
            $this->probeWeb(),
            $this->probeDatabase(),
            $this->probeStorage(),
            $this->probeReverb(),
            $this->probeAiService(),
        ];

        return [
            'checked_at' => now()->toIso8601String(),
            'components' => $components,
            'cameras' => $this->probeCameras(),
        ];
    }

    /**
     * @return array{id: string, label: string, status: string, detail: string}
     */
    private function probeWeb(): array
    {
        return [
            'id' => 'web',
            'label' => 'Web application',
            'status' => 'online',
            'detail' => 'Laravel request handler is running (this page loaded).',
        ];
    }

    /**
     * @return array{id: string, label: string, status: string, detail: string}
     */
    private function probeDatabase(): array
    {
        try {
            $connection = (string) config('database.default', 'mongodb');
            DB::connection($connection)->getMongoClient()->listDatabases();

            return [
                'id' => 'database',
                'label' => 'Database (MongoDB)',
                'status' => 'online',
                'detail' => 'Connected via configured connection "'.$connection.'" (DSN not shown).',
            ];
        } catch (\Throwable $e) {
            // Fallback: simple collection ping used by some Mongo drivers
            try {
                DB::connection()->table('users')->limit(1)->get();

                return [
                    'id' => 'database',
                    'label' => 'Database (MongoDB)',
                    'status' => 'online',
                    'detail' => 'Query probe succeeded.',
                ];
            } catch (\Throwable $inner) {
                return [
                    'id' => 'database',
                    'label' => 'Database (MongoDB)',
                    'status' => 'offline',
                    'detail' => 'Unreachable: '.$this->safeMessage($inner->getMessage()),
                ];
            }
        }
    }

    /**
     * @return array{id: string, label: string, status: string, detail: string}
     */
    private function probeStorage(): array
    {
        $path = storage_path('app');
        try {
            if (! is_dir($path)) {
                return [
                    'id' => 'storage',
                    'label' => 'Storage',
                    'status' => 'offline',
                    'detail' => 'storage/app missing.',
                ];
            }
            $probe = $path.DIRECTORY_SEPARATOR.'.health-'.bin2hex(random_bytes(4));
            if (@file_put_contents($probe, 'ok') === false) {
                return [
                    'id' => 'storage',
                    'label' => 'Storage',
                    'status' => 'offline',
                    'detail' => 'storage/app is not writable.',
                ];
            }
            @unlink($probe);

            return [
                'id' => 'storage',
                'label' => 'Storage',
                'status' => 'online',
                'detail' => 'storage/app is writable.',
            ];
        } catch (\Throwable $e) {
            return [
                'id' => 'storage',
                'label' => 'Storage',
                'status' => 'offline',
                'detail' => $this->safeMessage($e->getMessage()),
            ];
        }
    }

    /**
     * @return array{id: string, label: string, status: string, detail: string}
     */
    private function probeReverb(): array
    {
        $host = (string) config('reverb.servers.reverb.host', '127.0.0.1');
        $port = (int) config('reverb.servers.reverb.port', 8080);
        if ($host === '0.0.0.0') {
            $host = '127.0.0.1';
        }

        $errno = 0;
        $errstr = '';
        $fp = @fsockopen($host, $port, $errno, $errstr, 1.0);
        if (is_resource($fp)) {
            fclose($fp);

            return [
                'id' => 'reverb',
                'label' => 'Realtime (Reverb)',
                'status' => 'online',
                'detail' => "TCP reachable on {$host}:{$port}.",
            ];
        }

        return [
            'id' => 'reverb',
            'label' => 'Realtime (Reverb)',
            'status' => 'offline',
            'detail' => "Not reachable on {$host}:{$port}. Gate/AI live updates may fall back to polling.",
        ];
    }

    /**
     * @return array{id: string, label: string, status: string, detail: string}
     */
    private function probeAiService(): array
    {
        $healthUrl = $this->aiHealth->serviceHealthUrl();
        if ($healthUrl === null) {
            return [
                'id' => 'ai',
                'label' => 'AI parking service',
                'status' => 'unknown',
                'detail' => 'AI stream base URL is not configured.',
            ];
        }

        $payload = $this->aiHealth->serviceHealthPayload();
        if (! is_array($payload)) {
            return [
                'id' => 'ai',
                'label' => 'AI parking service',
                'status' => 'offline',
                'detail' => 'Python /health probe failed. Web app remains usable without AI.',
            ];
        }

        $camCount = is_array($payload['cameras'] ?? null) ? count($payload['cameras']) : 0;
        $anyOnline = (bool) ($payload['any_online'] ?? false);

        return [
            'id' => 'ai',
            'label' => 'AI parking service',
            'status' => 'online',
            'detail' => "Python /health OK. Configured cameras in payload: {$camCount}. any_online=".($anyOnline ? 'true' : 'false').'. YOLO/OCR status is not separately reported by /health.',
        ];
    }

    /**
     * @return list<array{id: string, name: string, status: string, detail: string}>
     */
    private function probeCameras(): array
    {
        $rows = [];
        foreach ($this->cameras->cameras() as $cam) {
            $id = (string) ($cam['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $name = (string) ($cam['name'] ?? $id);
            $enabled = ! empty($cam['enabled']);
            if (! $enabled) {
                $rows[] = [
                    'id' => $id,
                    'name' => $name,
                    'status' => 'disabled',
                    'detail' => 'Disabled in configuration.',
                ];

                continue;
            }

            $online = $this->aiHealth->isCameraOnline($id);
            $streamOk = $this->aiHealth->isStreamReachable(
                $this->aiHealth->upstreamStreamUrl($id, false)
            );

            $status = $online ? 'online' : 'offline';
            $detail = $online
                ? 'Reported online by AI /health (or recent ingest).'
                : 'Not online. Check RTSP, AI service, and camera power.';
            if (! $streamOk && $online) {
                $detail .= ' Stream probe inconclusive.';
            }

            $rows[] = [
                'id' => $id,
                'name' => $name,
                'status' => $status,
                'detail' => $detail,
            ];
        }

        return $rows;
    }

    private function safeMessage(string $message): string
    {
        $message = preg_replace('/mongodb(\+srv)?:\/\/[^\s]+/i', 'mongodb://***', $message) ?? $message;
        $message = preg_replace('/password[=:]\S+/i', 'password=***', $message) ?? $message;

        return mb_substr($message, 0, 180);
    }
}
