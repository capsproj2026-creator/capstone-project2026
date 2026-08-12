/**
 * Capstone Smart Campus VMS — ESP32 Exit Gate (RFID → Laravel → relay/servo)
 *
 * Uses shared logic from ../esp32_rfid_gate/rfid_gate_common.h
 * Copy rfid_gate_config.example.h → rfid_gate_config.h in esp32_rfid_gate folder,
 * or copy config into this folder as rfid_gate_config.h.
 */

#define GATE_ID "GATE-OUT-1"
#define DIRECTION "Exit"

#include "../esp32_rfid_gate/rfid_gate_common.h"

void setup() {
  Serial.begin(115200);
  setupGateHardware();
}

void loop() {
  loopGateClient();
}
