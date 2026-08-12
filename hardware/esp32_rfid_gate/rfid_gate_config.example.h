/**
 * Copy to rfid_gate_config.h and edit before flashing.
 * rfid_gate_config.h is gitignored — never commit WiFi passwords or tokens.
 */
#pragma once

// ========== NETWORK ==========
#define WIFI_SSID     "YOUR_WIFI_SSID"
#define WIFI_PASSWORD "YOUR_WIFI_PASSWORD"

// PC/server IPv4 (ipconfig). Laravel: php artisan serve --host=0.0.0.0 --port=8000
#define API_BASE       "http://192.168.1.104:8000"
#define RFID_API_TOKEN "capstone-rfid-dev-token-change-me"

// ========== GATE IDENTITY (override in sketch if needed) ==========
#ifndef GATE_ID
#define GATE_ID   "GATE-IN-1"
#endif
#ifndef DIRECTION
#define DIRECTION "Entry"   // "Entry" or "Exit"
#endif

// ========== ACTUATOR ==========
// ACTUATOR_RELAY — relay module on PIN_GATE (HIGH = open, typical boom barrier)
// ACTUATOR_SERVO — PWM servo (requires ESP32Servo library + external 5V supply)
#define ACTUATOR_RELAY 1
#define ACTUATOR_SERVO 2
#define ACTUATOR_MODE  ACTUATOR_RELAY

// Relay / shared timing
#define GATE_OPEN_MS   3000UL   // How long gate stays open (ms)
#define GATE_COOLDOWN_MS 2500UL // Ignore re-scans after a read (ms)
#define SCAN_BLOCK_MS  3500UL   // Block new opens while gate cycle is active (ms)
#define HEARTBEAT_MS   2500UL   // ESP32 online ping + emergency-open poll

// Servo angles (only used when ACTUATOR_MODE == ACTUATOR_SERVO)
#define SERVO_OPEN_ANGLE  90
#define SERVO_CLOSE_ANGLE 0
#define SERVO_CLOSE_DELAY_MS 800UL  // Time for servo to reach close position
