# Entry ESP32 — Arduino IDE sketch folder

Open **`Entry.ino`** in Arduino IDE. All files in this folder must stay together.

## Board

| Setting | Value |
|---------|--------|
| Board | ESP32 Dev Module |
| Port | COM port of **Entry** ESP32 (servo + RC522) |
| ESP32 core | 3.3.x (tested 3.3.7) |

## Required libraries (Library Manager)

| Library | Version |
|---------|---------|
| MFRC522 | latest |
| ArduinoJson | 6.x or 7.x |
| ESP32Servo | **3.0.x** (required with ESP32 core 3.x) |

## Files

| File | Purpose |
|------|---------|
| `Entry.ino` | Entry sketch (`GATE-IN-1`, servo enabled) |
| `rfid_gate_common.h` | Shared firmware logic |
| `rfid_gate_config.h` | Wi-Fi, API host, token (copy from `rfid_gate_config.example.h`) |

## Boot log (success)

```
ROLE: ENTRY — RC522 + servo GPIO 14
Actuator: SERVO on GPIO 14 (channel=N)
WiFi OK IP: 192.168.x.x
API online — heartbeats OK
```

## Deploy to OneDrive (optional)

From project root:

```bat
sync-arduino.bat
```

Copies this folder to `%USERPROFILE%\OneDrive\Documents\Arduino\Entry`.
