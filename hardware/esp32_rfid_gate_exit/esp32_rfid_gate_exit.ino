/**
 * Capstone Smart Campus VMS — ESP32 Exit Gate
 *
 * RFID reader only on this board. The physical servo/boom is wired to the
 * Entry ESP32 (GATE-IN-1). When Exit is granted, Laravel tells Entry to open.
 */

#define GATE_ID "GATE-OUT-1"
#define DIRECTION "Exit"

// No local servo — shared boom is on the Entry board.
#define ACTUATOR_NONE 0
#define ACTUATOR_MODE ACTUATOR_NONE

#include "../esp32_rfid_gate/rfid_gate_common.h"

void setup() {
  Serial.begin(115200);
  setupGateHardware();
}

void loop() {
  loopGateClient();
}
