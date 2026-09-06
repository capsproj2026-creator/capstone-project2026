/**
 * Copy to rfid_gate_config.h and edit before flashing.
 * rfid_gate_config.h is gitignored — never commit WiFi passwords or tokens.
 *
 * Preferred: install Arduino library "WiFiManager" (tzapu). After flash, join AP
 * "Gate-Setup" / password "capstone123" and enter Wi-Fi + Laravel PC IP in the portal.
 * Values are saved in flash — no reflash when switching home / hotspot / campus Wi-Fi.
 * Hold BOOT 2s at power-on (or 3s while running) to reopen the portal.
 *
 * Compile-time WIFI_SSID / API_HOST below are only defaults / fallback if WiFiManager
 * is not installed.
 */
#pragma once

// ========== NETWORK (defaults; portal/NVS overrides when WiFiManager is installed) ==========
#define WIFI_SSID     ""
#define WIFI_PASSWORD ""
// PC/server IPv4 default (ipconfig). Portal can change this without reflashing.
#define API_HOST       "192.168.1.110"
#define API_PORT       8000
#define API_BASE       "http://192.168.1.110:8000"
#define RFID_API_TOKEN "capstone-rfid-dev-token-change-me"

// 1 = use phone portal (recommended). 0 = only compile-time WIFI_SSID / WIFI_PASSWORD.
#ifndef USE_WIFI_MANAGER
#define USE_WIFI_MANAGER 1
#endif
#define WIFI_PORTAL_AP_NAME "Gate-Setup"
#define WIFI_PORTAL_AP_PASS "capstone123"

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
