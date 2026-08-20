# Exit ESP32 — Arduino IDE sketch folder

Open **`Exit.ino`** in Arduino IDE. Flash to the **second** ESP32 (Exit RC522 only — **no servo**).

## Board

| Setting | Value |
|---------|--------|
| Board | ESP32 Dev Module |
| Port | COM port of **Exit** ESP32 (different from Entry) |
| ESP32 core | 3.3.x (tested 3.3.7) |

## Required libraries (Library Manager)

| Library | Version |
|---------|---------|
| MFRC522 | latest |
| ArduinoJson | 6.x or 7.x |

**Do not** need ESP32Servo on Exit (`ACTUATOR_NONE`).

## Files

| File | Purpose |
|------|---------|
| `Exit.ino` | Exit sketch (`GATE-OUT-1`, no servo) |
| `rfid_gate_common.h` | Shared firmware logic |
| `rfid_gate_config.h` | Same Wi-Fi/API as Entry (copy from `rfid_gate_config.example.h`) |

## Boot log (success)

```
ROLE: EXIT — RC522 only. No servo.
Actuator: NONE (RFID only — shared boom is on Entry ESP32)
WiFi OK IP: 192.168.x.x
API online — heartbeats OK
```

Exit grants show `shared_boom=1` in Serial; the **Entry** board opens the servo via Laravel heartbeat (~1–2 s).

## Deploy to OneDrive (optional)

From project root:

```bat
sync-arduino.bat
```

Copies this folder to `%USERPROFILE%\OneDrive\Documents\Arduino\Exit`.
