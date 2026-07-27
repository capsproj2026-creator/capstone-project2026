<?php

/**
 * Logs in via POST /login then checks GET endpoints without following redirects.
 *
 * Usage:
 *   php storage/http-role-route-check.php <email> <password>
 */

$email = $argv[1] ?? '';
$password = $argv[2] ?? '';
if ($email === '' || $password === '') {
    fwrite(STDERR, "Usage: php storage/http-role-route-check.php <email> <password>\n");
    exit(1);
}

$base = 'http://127.0.0.1:8000';
$cookieJar = tempnam(sys_get_temp_dir(), 'scvms_cookie_');

function csrfFromLogin(string $cookieJar, string $base): string
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
        throw new RuntimeException("Could not find CSRF token in GET /login.");
    }
    return $m[1];
}

function postLogin(string $base, string $email, string $password, string $token, string $cookieJar): array
{
    $ch = curl_init($base.'/login');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'email' => $email,
            'password' => $password,
            '_token' => $token,
        ]),
        CURLOPT_COOKIEJAR => $cookieJar,
        CURLOPT_COOKIEFILE => $cookieJar,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HEADER => true,
    ]);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Find a Location header if any
    $location = null;
    $lines = preg_split("/\r?\n/", $resp ?: '');
    foreach ($lines as $line) {
        if (stripos($line, 'Location:') === 0) {
            $location = trim(substr($line, strlen('Location:')));
            break;
        }
    }

    return ['status' => $status, 'location' => $location, 'error' => $err];
}

function getNoFollow(string $url, string $cookieJar): array
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

    $location = null;
    $lines = preg_split("/\r?\n/", $resp ?: '');
    foreach ($lines as $line) {
        if (stripos($line, 'Location:') === 0) {
            $location = trim(substr($line, strlen('Location:')));
            break;
        }
    }

    return ['status' => $status, 'location' => $location, 'error' => $err];
}

$token = csrfFromLogin($cookieJar, $base);
$post = postLogin($base, $email, $password, $token, $cookieJar);
echo "POST /login -> status={$post['status']} location={$post['location']} error={$post['error']}\n";

$routes = ['/admin', '/guard', '/user'];
foreach ($routes as $r) {
    $res = getNoFollow($base.$r, $cookieJar);
    echo "GET {$r} -> status={$res['status']} location={$res['location']} error={$res['error']}\n";
}

@unlink($cookieJar);

