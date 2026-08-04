<?php

namespace App\Events;

use App\Models\GateLog;
use App\Support\GateScanPresenter;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GateScanProcessed implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $scan
     */
    public function __construct(public array $scan) {}

    /**
     * Broadcast after the HTTP response is sent so ESP32 gate clients
     * are not blocked by Reverb / presenter work (avoids read timeouts).
     */
    public static function dispatchFromLog(GateLog $log): void
    {
        $logId = $log->getKey();

        dispatch(function () use ($logId) {
            try {
                $log = GateLog::query()->find($logId);
                if (! $log) {
                    return;
                }

                event(new self(GateScanPresenter::fromLog($log, withStats: false)));
            } catch (\Throwable $e) {
                report($e);
            }
        })->afterResponse();
    }

    /**
     * @return array<int, \Illuminate\Broadcasting\PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('gate.scans'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'GateScanProcessed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->scan;
    }
}
