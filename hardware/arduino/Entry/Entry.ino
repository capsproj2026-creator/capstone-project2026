/**
 * Capstone Smart Campus VMS — ESP32 Entry Gate
 *
 * Flash THIS sketch to the ESP32 that has:
 *   - RC522 RFID reader (Entry lane)
 *   - Servo boom on GPIO 14 (the ONLY servo — shared for Entry + Exit)
 *
 * Network: set WIFI_SSID / WIFI_PASSWORD / API_HOST in rfid_gate_config.h, then flash.
 *
 * The second ESP32 uses Exit.ino (RFID only, no servo).
 * Exit grants still open THIS servo via Laravel heartbeat.
 *
 * Arduino IDE: File → Open → OneDrive\Documents\Arduino\Entry\Entry.ino
 * (run sync-arduino.bat from the project if this folder looks old)
 */

#define GATE_ID "GATE-IN-1"
#define DIRECTION "Entry"

#include "rfid_gate_common.h"

void setup() {
  Serial.begin(115200);
  delay(200);
  Serial.println();
  Serial.println("=== Capstone Entry gate (GATE-IN-1) ===");
  setupGateHardware();
}

void loop() {
  loopGateClient();
}
