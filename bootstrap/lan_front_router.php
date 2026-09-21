<?php

/**
 * LAN front door on :8000
 *
 * - POST /api/rfid/heartbeat → answered here instantly (no Laravel)
 * - Browser on localhost (non-API) → redirect to Laravel :8001 (avoids slow curl proxy)
 * - LAN devices / ESP32 / API clients → proxied to Laravel on 127.0.0.1:8001
 *
 * Start with:
 *   php -S 0.0.0.0:8000 bootstrap/lan_front_router.php
 * while Laravel listens on 127.0.0.1:8001.
 */

declare(strict_types=1);

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$hostHeader = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$query = (string) ($_SERVER['QUERY_STRING'] ?? '');

if ($method === 'POST' && $uri === '/api/rfid/heartbeat') {
    require __DIR__.'/rfid_heartbeat_fast.php';

    return true;
}

// Local browser traffic should hit Laravel directly — the curl proxy makes every
// page feel slow (and php -S is single-threaded while waiting on :8001).
// Keep /api/* on the proxy so AI/RFID clients are not broken by 307 redirects.
$isLoopbackHost = str_starts_with($hostHeader, '127.0.0.1')
    || str_starts_with($hostHeader, 'localhost');
$isApi = str_starts_with($uri, '/api/');
if ($isLoopbackHost && ! $isApi) {
    $target = 'http://127.0.0.1:8001'.$uri;
    if ($query !== '') {
        $target .= '?'.$query;
    }
    header('Location: '.$target, true, 307);
    header('Cache-Control: no-store');

    return true;
}

$publicRoot = dirname(__DIR__).DIRECTORY_SEPARATOR.'public';
$staticPath = $publicRoot.str_replace('/', DIRECTORY_SEPARATOR, $uri);
if ($uri !== '/' && is_file($staticPath) && str_starts_with(realpath($staticPath) ?: '', realpath($publicRoot) ?: '???')) {
    return false;
}

$backend = 'http://127.0.0.1:8001'.$uri;
if ($query !== '') {
    $backend .= '?'.$query;
}

$body = file_get_contents('php://input');
if ($body === false) {
    $body = '';
}

$headers = [];
foreach ($_SERVER as $key => $value) {
    if (! str_starts_with($key, 'HTTP_')) {
        continue;
    }
    if (in_array($key, ['HTTP_HOST', 'HTTP_CONTENT_LENGTH', 'HTTP_CONNECTION'], true)) {
        continue;
    }
    $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
    $headers[] = $name.': '.$value;
}

if (isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] !== '') {
    $headers[] = 'Content-Type: '.$_SERVER['CONTENT_TYPE'];
}

$headers[] = 'Host: 127.0.0.1:8001';
$headers[] = 'Connection: close';
$headers[] = 'X-Forwarded-For: '.($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
$headers[] = 'X-Forwarded-Proto: http';

$ch = curl_init($backend);
curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST => $method,
    CURLOPT_POSTFIELDS => in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true) ? $body : null,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
    CURLOPT_TIMEOUT => 120,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_FOLLOWLOCATION => false,
]);

$response = curl_exec($ch);
if ($response === false) {
    http_response_code(502);
    header('Content-Type: application/json');
    echo json_encode([
        'message' => 'Laravel backend unavailable on 127.0.0.1:8001. Keep the Laravel window open.',
        'error' => curl_error($ch),
    ]);
    curl_close($ch);

    return true;
}

$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
curl_close($ch);

$rawHeaders = substr($response, 0, $headerSize);
$rawBody = substr($response, $headerSize);

http_response_code($status > 0 ? $status : 502);

$skip = ['transfer-encoding', 'connection', 'keep-alive', 'proxy-connection', 'content-length'];
foreach (explode("\r\n", $rawHeaders) as $line) {
    if ($line === '' || str_starts_with($line, 'HTTP/')) {
        continue;
    }
    $pos = strpos($line, ':');
    if ($pos === false) {
        continue;
    }
    $name = substr($line, 0, $pos);
    if (in_array(strtolower($name), $skip, true)) {
        continue;
    }
    header($line, false);
}

echo $rawBody;

return true;
