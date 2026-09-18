<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Real-time AI parking pipeline events for the guard monitor (Reverb / Echo).
 *
 * Payload shape:
 * {
 *   "event": "violation_created",
 *   "timestamp": "2026-09-18T04:10:39Z",
 *   "data": { ... }
 * }
 */
class AiParkingRealtime implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public const EVENT_VEHICLE_DETECTED = 'vehicle_detected';

    public const EVENT_VEHICLE_MOVING = 'vehicle_moving';

    public const EVENT_VEHICLE_STATIONARY = 'vehicle_stationary';

    public const EVENT_PLATE_SCAN_STARTED = 'plate_scan_started';

    public const EVENT_PLATE_SCAN_RESULT = 'plate_scan_result';

    public const EVENT_PLATE_SCAN_FAILED = 'plate_scan_failed';

    public const EVENT_PLATE_NOT_READ = 'plate_not_read';

    public const EVENT_PLATE_MANUAL_ENTRY = 'plate_manual_entry';

    public const EVENT_VEHICLE_IDENTIFIED = 'vehicle_identified';

    public const EVENT_VIOLATION_DETECTED = 'violation_detected';

    public const EVENT_VIOLATION_CREATED = 'violation_created';

    public const EVENT_VIOLATION_NOTIFICATION_SENT = 'violation_notification_sent';

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public string $eventName,
        public array $data = [],
        public ?string $timestamp = null,
    ) {
        $this->timestamp = $timestamp ?? Carbon::now('UTC')->toIso8601String();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function emit(string $eventName, array $data = []): void
    {
        try {
            event(new self($eventName, $data));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('ai.parking'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'AiParkingRealtime';
    }

    /**
     * @return array{event: string, timestamp: string, data: array<string, mixed>}
     */
    public function broadcastWith(): array
    {
        return [
            'event' => $this->eventName,
            'timestamp' => (string) $this->timestamp,
            'data' => $this->data,
        ];
    }
}
