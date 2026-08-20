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
 * Arduino IDE: File → Open → hardware/arduino/Exit/Exit.ino
 * (keep Exit.ino + rfid_gate_common.h + rfid_gate_config.h in this folder)
 */

#define GATE_ID "GATE-OUT-1"
#define DIRECTION "Exit"

// No local servo — shared boom is on the Entry board.
#define ACTUATOR_NONE 0
#define ACTUATOR_MODE ACTUATOR_NONE

#include "rfid_gate_common.h"

void setup() {
  Serial.begin(115200);
  setupGateHardware();
}

void loop() {
  loopGateClient();
}
