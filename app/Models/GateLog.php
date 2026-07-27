<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GateLog extends MongoModel
{
    protected $collection = 'gate_logs';

    public $timestamps = false;

    const CREATED_AT = 'timestamp';

    protected $fillable = [
        'daily_log_id',
        'user_id',
        'action',
        'gate_id',
        'rfid_uid',
        'result',
        'reason',
        'log_date',
        'timestamp',
    ];

    protected function casts(): array
    {
        return [
            'daily_log_id' => 'integer',
            'user_id' => 'integer',
            'log_date' => 'date',
            'timestamp' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function accessGranted(): bool
    {
        $result = strtolower(trim((string) ($this->result ?? '')));

        return $result === '' || in_array($result, ['access granted', 'granted'], true);
    }

    public function displayResultLabel(): string
    {
        return $this->accessGranted() ? 'Granted' : 'Denied';
    }

    public function displayGate(): string
    {
        $gate = trim((string) ($this->gate_id ?? ''));

        if ($gate === '') {
            return '—';
        }

        return (string) str_replace(['_', '-'], ' ', $gate);
    }

    public function displayRfid(): string
    {
        $uid = trim((string) ($this->rfid_uid ?? ''));

        if ($uid === '') {
            $uid = trim((string) ($this->user?->rfid_uid ?? ''));
        }

        return $uid !== '' ? $uid : '—';
    }

    public function displayReason(): string
    {
        $stored = trim((string) ($this->reason ?? ''));
        if ($stored !== '') {
            return $stored;
        }

        if ($this->accessGranted()) {
            return '';
        }

        return match (strtolower(trim((string) ($this->result ?? '')))) {
            'card not registered' => 'RFID card is not registered in the system.',
            'already inside' => 'Vehicle is already inside campus.',
            'already outside' => 'Vehicle is already outside campus.',
            'access denied', 'denied' => 'Access was denied at the gate.',
            default => trim((string) ($this->result ?? '')) ?: 'Access was denied at the gate.',
        };
    }
}
