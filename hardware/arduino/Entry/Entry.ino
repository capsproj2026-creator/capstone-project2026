/**
 * Capstone Smart Campus VMS — ESP32 Entry Gate
 *
 * Flash THIS sketch to the ESP32 that has:
 *   - RC522 RFID reader (Entry lane)
 *   - Servo boom on GPIO 14 (the ONLY servo — shared for Entry + Exit)
 *
 * The second ESP32 uses Exit.ino (RFID only, no servo).
 * Exit grants still open THIS servo via Laravel heartbeat.
 *
 * Arduino IDE: File → Open → hardware/arduino/Entry/Entry.ino
 */

#define GATE_ID "GATE-IN-1"
#define DIRECTION "Entry"

#include "rfid_gate_common.h"

void setup() {
  Serial.begin(115200);
  setupGateHardware();
}

void loop() {
  loopGateClient();
}
