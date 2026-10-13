/**
 * Copy to rfid_enroll_config.h and edit before flashing.
 * rfid_enroll_config.h is gitignored — never commit Wi-Fi passwords or tokens.
 *
 * Use the same API host, port, and RFID_API_TOKEN as the gate ESP32.
 * This board only reads a UID. It does not open the boom.
 */
#pragma once

#define WIFI_SSID     "YOUR_WIFI_NAME"
#define WIFI_PASSWORD "YOUR_WIFI_PASSWORD"

#define API_HOST       "www.iscvms.com"
#define API_PORT       443
#define API_BASE       "https://www.iscvms.com"
#define RFID_API_TOKEN "YOUR_RFID_API_TOKEN"

// Ignore the same card while it is still sitting on the reader.
#define SAME_UID_COOLDOWN_MS 2500UL
