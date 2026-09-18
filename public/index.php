<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// ESP32 heartbeat must never wait on Laravel/Mongo boot (Windows php -S is
// single-threaded; a slow page load otherwise causes HTTP error -11).
$rfidPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && ($rfidPath === '/api/rfid/heartbeat' || str_ends_with($rfidPath, '/api/rfid/heartbeat'))
) {
    require __DIR__.'/../bootstrap/rfid_heartbeat_fast.php';
    return;
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
