/**
 * Copy to rfid_gate_config.h and edit before flashing.
 * rfid_gate_config.h is gitignored — never commit WiFi passwords or tokens.
 */
#pragma once

// ========== NETWORK ==========
#define WIFI_SSID     "YOUR_WIFI_SSID"
#define WIFI_PASSWORD "YOUR_WIFI_PASSWORD"
// PC/server IPv4 (ipconfig). Laravel LAN front: port 8000
#define API_HOST       "192.168.1.100"
#define API_PORT       8000
#define API_BASE       "http://192.168.1.100:8000"
#define RFID_API_TOKEN "CHANGE_ME_MATCH_DOTENV_RFID_API_TOKEN"

#define USE_WIFI_MANAGER 0

#define GATE_OPEN_MS         5000UL
#define GATE_COOLDOWN_MS      400UL
#define SAME_UID_COOLDOWN_MS  900UL
#define SCAN_BLOCK_MS        1000UL
#define HEARTBEAT_MS         1500UL
#define SERVO_TEST_ON_BOOT       0

#define SERVO_OPEN_ANGLE      90
#define SERVO_CLOSE_ANGLE      0
#define SERVO_CLOSE_DELAY_MS 800UL
