/**
 * Copy to rfid_gate_config.h and edit before flashing.
 * rfid_gate_config.h is gitignored — never commit WiFi passwords or tokens.
 */
#pragma once

// ========== NETWORK ==========
#define WIFI_SSID     "Redmi Note 14 5G"
#define WIFI_PASSWORD "sabater123"
// PC/server IPv4 (ipconfig). Laravel: php artisan serve --host=0.0.0.0 --port=8000
#define API_HOST       "192.168.1.103"
#define API_PORT       8000
#define API_BASE       "http://192.168.1.103:8000"
#define RFID_API_TOKEN "capstone-rfid-dev-token-change-me"

#ifndef GATE_ID
#define GATE_ID   "GATE-IN-1"
#endif
#ifndef DIRECTION
#define DIRECTION "Entry"
#endif

// Wire ONE servo to Entry ESP32 only. Exit uses ACTUATOR_NONE (set in exit .ino).
#ifndef ACTUATOR_NONE
#define ACTUATOR_NONE 0
#endif
#ifndef ACTUATOR_RELAY
#define ACTUATOR_RELAY 1
#endif
#ifndef ACTUATOR_SERVO
#define ACTUATOR_SERVO 2
#endif
#ifndef ACTUATOR_MODE
#define ACTUATOR_MODE  ACTUATOR_SERVO
#endif

#define GATE_OPEN_MS   8000UL
#define GATE_COOLDOWN_MS 2500UL
#define SCAN_BLOCK_MS  8500UL
#define HEARTBEAT_MS   2500UL
#define SERVO_TEST_ON_BOOT 1

#define SERVO_OPEN_ANGLE  90
#define SERVO_CLOSE_ANGLE 0
#define SERVO_CLOSE_DELAY_MS 800UL
