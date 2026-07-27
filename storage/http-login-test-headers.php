<?php

/**
 * Dumps HTTP response code + Set-Cookie headers for POST /login.
 *
 * Usage:
 *   php storage/http-login-test-headers.php admin@cspc.edu admin123
 */

$email = $argv[1] ?? 'admin@cspc.edu';
$password = $argv[2] ?? 'admin123';

$base = 'http://127.0.0.1:8000';
$cookieJar = tempnam(sys_get_temp_dir(), 'scvms_cookie_');

function http_get(string $url, string $cookieJar): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR => $cookieJar,
        CURLOPT_COOKIEFILE => $cookieJar,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    return ['status' => $status, 'body' => $body ?: '', 'error' => $err];
}

function http_post_with_headers(string $url, array $data, string $cookieJar): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR => $cookieJar,
        CURLOPT_COOKIEFILE => $cookieJar,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_HEADER => true,
    ]);
    $resp = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    // Header size isn't available after curl_close sometimes; fallback:
    if (! is_string($resp)) {
        return ['status' => $status, 'error' => $err, 'headers' => '', 'body' => ''];
    }

    // If headerSize can't be determined, just split with first blank line heuristic.
    $parts = preg_split("/\r?\n\r?\n/", $resp, 2);
    $headers = $parts[0] ?? '';
    $body = $parts[1] ?? '';

    return ['status' => $status, 'error' => $err, 'headers' => $headers, 'body' => $body];
}

$get = http_get($base.'/login', $cookieJar);
if ($get['error'] !== '') {
    fwrite(STDERR, "GET /login error: {$get['error']}\n");
    exit(1);
}
if (! preg_match('/name="_token"\s+value="([^"]+)"/', $get['body'], $m)) {
    fwrite(STDERR, "Could not find CSRF token in GET /login response.\n");
    exit(1);
}
$token = $m[1];

$post = http_post_with_headers($base.'/login', [
    'email' => $email,
    'password' => $password,
    '_token' => $token,
], $cookieJar);

echo "GET /login status={$get['status']}\n";
echo "POST /login status={$post['status']} error={$post['error']}\n";

echo "----- POST response headers (first 80 lines max) -----\n";
$lines = preg_split("/\r?\n/", (string) $post['headers']);
$limit = min(80, count($lines));
for ($i = 0; $i < $limit; $i++) {
    echo $lines[$i]."\n";
}

echo "----- End headers -----\n";

// Check if session cookie was written
$cookieData = file_get_contents($cookieJar);
$hasSessionCookie = (bool) preg_match('/laravel-session|Smart-Campus-VMS-session|session/i', $cookieData);
echo "cookieJar present_session_cookie? ".($hasSessionCookie ? 'YES' : 'NO')."\n";

@unlink($cookieJar);

