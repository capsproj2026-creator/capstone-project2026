<?php

/**
 * Sub-millisecond RFID heartbeat — no Laravel/Mongo boot.
 * Expects POST /api/rfid/heartbeat with JSON {"gate_id":"GATE-IN-1"}
 * and X-RFID-TOKEN / Authorization: Bearer matching RFID_API_TOKEN in .env.
 */

declare(strict_types=1);

require __DIR__.'/gate_hardware_store.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function rfid_fast_env(string $key): string
{
    static $map = null;
    if ($map === null) {
        $map = [];
        $path = dirname(__DIR__).DIRECTORY_SEPARATOR.'.env';
        if (is_file($path)) {
            $lines = @file($path, FILE_IGNORE_NEW_LINES) ?: [];
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
                    continue;
                }
                [$k, $v] = explode('=', $line, 2);
                $k = trim($k);
                $v = trim($v);
                if (
                    (str_starts_with($v, '"') && str_ends_with($v, '"'))
                    || (str_starts_with($v, "'") && str_ends_with($v, "'"))
                ) {
                    $v = substr($v, 1, -1);
                }
                $map[$k] = $v;
            }
        }
    }

    return (string) ($map[$key] ?? '');
}

function rfid_fast_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    rfid_fast_json([
        'ok' => false,
        'gate_id' => '',
        'open' => false,
        'command' => null,
        'message' => 'Method not allowed.',
    ], 405);
    return;
}

$expected = rfid_fast_env('RFID_API_TOKEN');
if ($expected === '') {
    rfid_fast_json([
        'status' => 'Access Denied',
        'code' => 'misconfigured',
        'granted' => false,
        'message' => 'RFID_API_TOKEN is not configured on the server.',
    ], 503);
    return;
}

$provided = (string) (
    $_SERVER['HTTP_X_RFID_TOKEN']
    ?? ''
);
if ($provided === '' && ! empty($_SERVER['HTTP_AUTHORIZATION'])) {
    if (preg_match('/^\s*Bearer\s+(\S+)/i', (string) $_SERVER['HTTP_AUTHORIZATION'], $m)) {
        $provided = $m[1];
    }
}

if (! hash_equals($expected, $provided)) {
    rfid_fast_json([
        'status' => 'Access Denied',
        'code' => 'unauthorized',
        'granted' => false,
        'message' => 'Invalid RFID API token.',
    ], 401);
    return;
}

$raw = file_get_contents('php://input') ?: '';
$body = json_decode($raw, true);
if (! is_array($body)) {
    // ESP32 may also send form-encoded; accept gate_id from $_POST.
    $body = $_POST;
}

$gateId = isset($body['gate_id']) ? (string) $body['gate_id'] : '';
if (trim($gateId) === '') {
    rfid_fast_json([
        'ok' => false,
        'gate_id' => '',
        'open' => false,
        'command' => null,
        'message' => 'gate_id is required.',
    ], 422);
    return;
}

$result = gate_hw_heartbeat($gateId);
rfid_fast_json($result, $result['ok'] ? 200 : 422);
