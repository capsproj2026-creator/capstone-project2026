/**
 * Capstone Smart Campus VMS — ESP32 Exit Gate
 *
 * Flash THIS sketch to the SECOND ESP32 (Exit lane):
 *   - RC522 RFID reader only
 *   - Do NOT wire a servo here
 *
 * When Exit is granted, Laravel queues open on GATE-IN-1.
 * The Entry ESP32 (with the servo) opens the boom on its next heartbeat.
 *
 * Arduino IDE: open OneDrive\Documents\Arduino\Exit\Exit.ino after sync-arduino.bat
 * (that copy includes rfid_gate_common.h from the same folder).
 */

#define GATE_ID "GATE-OUT-1"
#define DIRECTION "Exit"

// No local servo — shared boom is on the Entry board.
#define ACTUATOR_NONE 0
#define ACTUATOR_MODE ACTUATOR_NONE

// Must match Entry: Exit grant opens Entry boom for 5 seconds.
#define GATE_OPEN_MS 5000UL

#include "rfid_gate_common.h"

void setup() {
  Serial.begin(115200);
  setupGateHardware();
}

void loop() {
  loopGateClient();
}
