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

Route::middleware([VerifyRfidApiToken::class, 'throttle:60,1'])->group(function () {
    Route::post('/rfid/scan', [RfidGateController::class, 'scan'])->name('api.rfid.scan');
});

Route::middleware([VerifyAiParkingApiToken::class, 'throttle:60,1'])->group(function () {
    Route::post('/ai-parking/occupancy', [AiParkingController::class, 'occupancy'])->name('api.ai-parking.occupancy');
    Route::post('/ai-parking/events', [AiParkingController::class, 'events'])->name('api.ai-parking.events');
    Route::post('/ai-parking/plate-lookup', [AiParkingController::class, 'plateLookup'])->name('api.ai-parking.plate-lookup');
});
