/**
 * Capstone Smart Campus VMS — ESP32 Entry Gate (canonical folder)
 *
 * Prefer flashing from Arduino IDE:
 *   OneDrive\Documents\Arduino\Entry\Entry.ino
 * after running sync-arduino.bat
 *
 * Flash THIS board with RC522 + servo on GPIO 14.
 * Exit board uses hardware/arduino/Exit/Exit.ino (RFID only).
 *
 * WiFi: WiFiManager portal AP "Gate-Setup" / "capstone123"
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
