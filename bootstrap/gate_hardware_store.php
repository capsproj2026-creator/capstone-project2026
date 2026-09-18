<?php

/**
 * File-backed gate hardware state shared by:
 * - App\Services\GateHardwareService (Laravel)
 * - bootstrap/rfid_heartbeat_fast.php (no Laravel boot)
 *
 * Keep this file free of Composer/Laravel dependencies.
 */

declare(strict_types=1);

const GATE_HW_ONLINE_AFTER_SEC = 12;
const GATE_HW_COMMAND_TTL_SEC = 60;
const GATE_HW_OPEN_DELIVERIES = 8;

/** @var array<string, string> */
const GATE_HW_GATES = [
    'GATE-IN-1' => 'Entry',
    'GATE-OUT-1' => 'Exit',
];

function gate_hw_storage_dir(): string
{
    $dir = dirname(__DIR__).DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'framework'.DIRECTORY_SEPARATOR.'gate-hw';
    if (! is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    return $dir;
}

function gate_hw_normalize_id(string $gateId): ?string
{
    $id = strtoupper(trim($gateId));

    return array_key_exists($id, GATE_HW_GATES) ? $id : null;
}

function gate_hw_path(string $gateId): string
{
    return gate_hw_storage_dir().DIRECTORY_SEPARATOR.$gateId.'.json';
}

/**
 * @return array{seen_at: int|null, open: array<string, mixed>|null}
 */
function gate_hw_read(string $gateId): array
{
    $path = gate_hw_path($gateId);
    if (! is_file($path)) {
        return ['seen_at' => null, 'open' => null];
    }

    $raw = @file_get_contents($path);
    if ($raw === false || $raw === '') {
        return ['seen_at' => null, 'open' => null];
    }

    $data = json_decode($raw, true);
    if (! is_array($data)) {
        return ['seen_at' => null, 'open' => null];
    }

    $seen = $data['seen_at'] ?? null;
    $open = $data['open'] ?? null;

    if (is_array($open)) {
        $expiresAt = (int) ($open['expires_at'] ?? 0);
        if ($expiresAt > 0 && time() > $expiresAt) {
            $open = null;
        }
    } else {
        $open = null;
    }

    return [
        'seen_at' => is_numeric($seen) ? (int) $seen : null,
        'open' => $open,
    ];
}

/**
 * @param  array{seen_at: int|null, open: array<string, mixed>|null}  $state
 */
function gate_hw_write(string $gateId, array $state): void
{
    $path = gate_hw_path($gateId);
    $payload = json_encode([
        'seen_at' => $state['seen_at'],
        'open' => $state['open'],
    ], JSON_UNESCAPED_SLASHES);

    if ($payload === false) {
        return;
    }

    $tmp = $path.'.'.getmypid().'.tmp';
    if (@file_put_contents($tmp, $payload, LOCK_EX) === false) {
        return;
    }
    @rename($tmp, $path);
}

/**
 * @return array{ok: bool, gate_id: string, open: bool, command: string|null, message?: string}
 */
function gate_hw_heartbeat(string $gateId): array
{
    $id = gate_hw_normalize_id($gateId);
    if ($id === null) {
        return [
            'ok' => false,
            'gate_id' => strtoupper(trim($gateId)),
            'open' => false,
            'command' => null,
            'message' => 'Unknown gate_id.',
        ];
    }

    $state = gate_hw_read($id);
    $state['seen_at'] = time();

    $open = false;
    if (is_array($state['open'])) {
        $remain = (int) ($state['open']['remain'] ?? 1) - 1;
        $open = true;
        if ($remain <= 0) {
            $state['open'] = null;
        } else {
            $state['open']['remain'] = $remain;
            $state['open']['expires_at'] = time() + GATE_HW_COMMAND_TTL_SEC;
        }
    }

    gate_hw_write($id, $state);

    return [
        'ok' => true,
        'gate_id' => $id,
        'open' => $open,
        'command' => $open ? 'open' : null,
    ];
}

/**
 * @param  array<string, mixed>  $payload
 */
function gate_hw_store_open(string $gateId, array $payload): bool
{
    $id = gate_hw_normalize_id($gateId);
    if ($id === null) {
        return false;
    }

    $state = gate_hw_read($id);
    $state['open'] = array_merge($payload, [
        'remain' => GATE_HW_OPEN_DELIVERIES,
        'expires_at' => time() + GATE_HW_COMMAND_TTL_SEC,
    ]);
    gate_hw_write($id, $state);

    return true;
}

function gate_hw_is_online(string $gateId): bool
{
    $id = gate_hw_normalize_id($gateId);
    if ($id === null) {
        return false;
    }

    $seen = gate_hw_read($id)['seen_at'];

    return is_int($seen) && (time() - $seen) <= GATE_HW_ONLINE_AFTER_SEC;
}

function gate_hw_has_pending_open(string $gateId): bool
{
    $id = gate_hw_normalize_id($gateId);
    if ($id === null) {
        return false;
    }

    return is_array(gate_hw_read($id)['open']);
}

function gate_hw_last_seen_at(string $gateId): ?int
{
    $id = gate_hw_normalize_id($gateId);
    if ($id === null) {
        return null;
    }

    return gate_hw_read($id)['seen_at'];
}
