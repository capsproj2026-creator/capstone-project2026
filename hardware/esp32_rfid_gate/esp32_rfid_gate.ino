/**
 * Capstone Smart Campus VMS — ESP32 Entry Gate (RFID → Laravel → relay/servo)
 *
 * See README.md for wiring. Copy rfid_gate_config.example.h → rfid_gate_config.h
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
