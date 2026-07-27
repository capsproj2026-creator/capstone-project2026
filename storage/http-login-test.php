<?php

/**
 * Minimal login test using cURL cookie jar + CSRF token.
 * Usage:
 *   php storage/http-login-test.php admin@cspc.edu admin123
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
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    return ['status' => $status, 'body' => $body ?: '', 'error' => $err];
}

function http_post(string $url, array $data, string $cookieJar): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_COOKIEJAR => $cookieJar,
        CURLOPT_COOKIEFILE => $cookieJar,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    return ['status' => $status, 'body' => $body ?: '', 'error' => $err];
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

$post = http_post($base.'/login', [
    'email' => $email,
    'password' => $password,
    '_token' => $token,
], $cookieJar);

// After POST, we might be on /admin or back to /login.
$afterAdmin = http_get($base.'/admin', $cookieJar);

echo "GET /login status={$get['status']}\n";
echo "POST /login status={$post['status']} error={$post['error']}\n";
echo "GET /admin status={$afterAdmin['status']} error={$afterAdmin['error']}\n";

$isLogin = str_contains($afterAdmin['body'], 'Login - Smart Campus VMS');
echo "GET /admin shows login page? ".($isLogin ? 'YES' : 'NO')."\n";

// Print a small hint (first banner/error line if any).
if ($isLogin) {
    if (preg_match('/<div class="error-alert">([^<]+)<\\/div>/', $afterAdmin['body'], $m2)) {
        echo "Visible login error: ".$m2[1]."\n";
    } else {
        echo "Visible login error: (none parsed)\n";
    }
}

@unlink($cookieJar);

