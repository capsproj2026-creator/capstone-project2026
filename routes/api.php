<?php

use App\Http\Controllers\Api\AiParkingController;
use App\Http\Controllers\Api\RfidGateController;
use App\Http\Middleware\VerifyAiParkingApiToken;
use App\Http\Middleware\VerifyRfidApiToken;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Hardware APIs (ESP32 RFID + YOLO AI Parking)
|--------------------------------------------------------------------------
*/

Route::middleware([VerifyRfidApiToken::class, 'throttle:30,1'])->group(function () {
    Route::post('/rfid/scan', [RfidGateController::class, 'scan'])->name('api.rfid.scan');
});

Route::middleware([VerifyRfidApiToken::class, 'throttle:120,1'])->group(function () {
    Route::post('/rfid/heartbeat', [RfidGateController::class, 'heartbeat'])->name('api.rfid.heartbeat');
});

Route::middleware([VerifyAiParkingApiToken::class, 'throttle:30,1'])->group(function () {
    Route::post('/ai-parking/occupancy', [AiParkingController::class, 'occupancy'])->name('api.ai-parking.occupancy');
    Route::post('/ai-parking/events', [AiParkingController::class, 'events'])->name('api.ai-parking.events');
});

Route::middleware([VerifyAiParkingApiToken::class, 'throttle:20,1'])->group(function () {
    Route::post('/ai-parking/plate-lookup', [AiParkingController::class, 'plateLookup'])->name('api.ai-parking.plate-lookup');
});
