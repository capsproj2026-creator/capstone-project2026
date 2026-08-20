# ESP32 Entry / Exit — Arduino IDE folders

Self-contained sketches ready to open in **Arduino IDE 2.x**.

| Folder | Sketch | Board role |
|--------|--------|------------|
| [Entry/](Entry/) | `Entry.ino` | `GATE-IN-1` — RC522 + **servo GPIO 14** |
| [Exit/](Exit/) | `Exit.ino` | `GATE-OUT-1` — RC522 only, **no servo** |

Each folder contains:

- `*.ino` — role-specific sketch
- `rfid_gate_common.h` — shared firmware (synced from `../esp32_rfid_gate/`)
- `rfid_gate_config.h` — Wi-Fi + Laravel API (edit before flashing)
- `rfid_gate_config.example.h` — template

## Quick start

1. Copy `rfid_gate_config.example.h` → `rfid_gate_config.h` in **both** folders (or run `sync-arduino.bat` from project root).
2. Set `WIFI_SSID`, `WIFI_PASSWORD`, `API_HOST` (PC IP on same network), `RFID_API_TOKEN` (match Laravel `.env`).
3. Install libraries: **MFRC522**, **ArduinoJson**, **ESP32Servo** (Entry only).
4. Flash **Entry.ino** to the ESP32 with the servo; flash **Exit.ino** to the other ESP32.

## Sync to OneDrive (Arduino sketchbook)

From project root:

```bat
sync-arduino.bat
```

Deploys to:

- `%USERPROFILE%\OneDrive\Documents\Arduino\Entry`
- `%USERPROFILE%\OneDrive\Documents\Arduino\Exit`

## Verify compile (CI / local)

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\verify-arduino-gate.ps1
```

Uses portable `tools/arduino-cli/` and ESP32 core 3.3.7 from `%LOCALAPPDATA%\Arduino15`.

## Source of truth

- Shared logic: `../esp32_rfid_gate/rfid_gate_common.h`
- Legacy dev paths: `../esp32_rfid_gate/esp32_rfid_gate.ino`, `../esp32_rfid_gate_exit/esp32_rfid_gate_exit.ino`
