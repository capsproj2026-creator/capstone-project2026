/**
 * Copy to rfid_gate_config.h in this folder before flashing the EXIT board.
 * rfid_gate_config.h is gitignored — never commit WiFi passwords or tokens.
 */
#pragma once

#define WIFI_SSID     "YOUR_WIFI_SSID"
#define WIFI_PASSWORD "YOUR_WIFI_PASSWORD"

#define API_BASE       "http://192.168.1.105:8000"
#define RFID_API_TOKEN "capstone-rfid-dev-token-change-me"

#ifndef GATE_ID
#define GATE_ID   "GATE-OUT-1"
#endif
#ifndef DIRECTION
#define DIRECTION "Exit"
#endif

#define ACTUATOR_RELAY 1
#define ACTUATOR_SERVO 2
#define ACTUATOR_MODE  ACTUATOR_SERVO

#ifndef PIN_GATE
#define PIN_GATE 14
#endif

#define GATE_OPEN_MS     4000UL
#define GATE_COOLDOWN_MS 2500UL
#define SCAN_BLOCK_MS    4500UL
#define HEARTBEAT_MS     2500UL

// Swap OPEN/CLOSE if the exit boom moves the opposite direction of entry.
#define SERVO_OPEN_ANGLE     90
#define SERVO_CLOSE_ANGLE    0
#define SERVO_CLOSE_DELAY_MS 800UL
#define SERVO_MIN_US         500
#define SERVO_MAX_US         2400
