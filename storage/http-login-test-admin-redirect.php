<?php

/**
 * After POST /login, calls GET /admin without following redirects and prints status + Location.
 *
 * Usage:
 *   php storage/http-login-test-admin-redirect.php admin@cspc.edu admin123
 */

$email = $argv[1] ?? 'admin@cspc.edu';
$password = $argv[2] ?? 'admin123';

$base = 'http://127.0.0.1:8000';
$cookieJar = tempnam(sys_get_temp_dir(), 'scvms_cookie_');

function get_csrf(string $cookieJar, string $base): string
{
    $ch = curl_init($base.'/login');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR => $cookieJar,
        CURLOPT_COOKIEFILE => $cookieJar,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    $body = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        throw new RuntimeException("GET /login error: ".$err);
    }
    if (! preg_match('/name="_token"\s+value="([^"]+)"/', $body, $m)) {
        throw new RuntimeException("Could not find CSRF token.");
    }
    return $m[1];
}

function http_post(string $url, array $data, string $cookieJar): array
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
    $err = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['status' => $status, 'error' => $err, 'resp' => $resp ?: ''];
}

function http_get_no_follow(string $url, string $cookieJar): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEFILE => $cookieJar,
        CURLOPT_COOKIEJAR => $cookieJar,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HEADER => true,
    ]);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Parse first response headers block
    $lines = preg_split("/\r?\n/", $resp ?: '');
    $location = null;
    foreach ($lines as $line) {
        if (stripos($line, 'Location:') === 0) {
            $location = trim(substr($line, strlen('Location:')));
            break;
        }
    }
    return ['status' => $status, 'error' => $err, 'location' => $location];
}

$token = get_csrf($cookieJar, $base);

$post = http_post($base.'/login', [
    'email' => $email,
    'password' => $password,
    '_token' => $token,
], $cookieJar);

$admin = http_get_no_follow($base.'/admin', $cookieJar);

echo "POST /login status={$post['status']} error={$post['error']}\n";
echo "GET /admin status={$admin['status']} error={$admin['error']}\n";
echo "GET /admin Location={$admin['location']}\n";

@unlink($cookieJar);

