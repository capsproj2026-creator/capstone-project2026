# ESP32 RFID Boom Gate

Physical gate control runs on the **ESP32**, not Laravel. Laravel validates RFID and returns `{ granted, status, code }`; the ESP32 opens or denies locally.

## Flow

```
RFID tap → RC522 → ESP32 → POST /api/rfid/scan → Laravel
                         ↓
              granted → GPIO 14 servo/relay open
              denied  → red LED + buzzer
```

## Setup (what code to flash)

1. Arduino libraries: **MFRC522**, **ArduinoJson**, **ESP32Servo** (servo mode).
2. Copy `rfid_gate_config.example.h` → `rfid_gate_config.h` and set WiFi, `API_BASE`, token, actuator mode.
3. Match `RFID_API_TOKEN` with Laravel `.env`.
4. In Arduino IDE: **File → Open** → `esp32_rfid_gate.ino` from **this folder** (must keep `.ino` + `.h` files together).
5. Flash **Entry** from this folder. For Exit, open `../esp32_rfid_gate_exit/` (or copy all three files into a new sketch folder).
6. Laravel must listen on the LAN: `.\start.ps1` (uses `--host=0.0.0.0`).

**Do not paste `rfid_gate_config.h` into the `.ino` file.** That removes `setup()` / `loop()` and causes:
`undefined reference to setup()` / `undefined reference to loop()`.

The `.ino` must stay small and `#include "rfid_gate_common.h"`. Config stays in `rfid_gate_config.h`.

## Wiring overview

### RC522 → ESP32

| RC522 | ESP32 GPIO | Notes |
|-------|------------|-------|
| SDA   | 5          | SPI SS |
| SCK   | 18         | |
| MOSI  | 23         | |
| MISO  | 19         | |
| RST   | 22         | |
| 3.3V  | 3.3V       | **Not 5V** |
| GND   | GND        | |

### Feedback

| Device | GPIO |
|--------|------|
| Green LED | 25 |
| Red LED | 26 |
| Buzzer | 27 |

### Servo (demo boom arm)

| Servo | Connection |
|-------|------------|
| Signal | GPIO **14** |
| VCC | **External 5V** (not ESP32 3.3V) |
| GND | External GND **+** ESP32 GND (common ground) |

In `rfid_gate_config.h`:

```cpp
#define ACTUATOR_MODE  ACTUATOR_SERVO
#define SERVO_OPEN_ANGLE  90
#define SERVO_CLOSE_ANGLE 0
```

### Relay (barrier)

| Relay IN | GPIO **14** (HIGH = open) |
| GND / VCC | Per module; common GND with ESP32 |

```cpp
#define ACTUATOR_MODE  ACTUATOR_RELAY
```

## Shared boom (one servo for Entry + Exit)

Wire the servo **only** to the Entry ESP32 (GPIO 14 + external 5V).

- Entry grant → Entry opens servo locally
- Exit grant → Laravel queues open → Entry heartbeat opens the same servo
- Guard emergency open (Entry or Exit) → same shared boom on Entry

Exit firmware uses `ACTUATOR_NONE` (no local servo).

```env
RFID_SHARED_BOOM_GATE_ID=GATE-IN-1
```

## Timing (`rfid_gate_config.h`)

- `GATE_OPEN_MS` — open duration (default 3000)
- `GATE_COOLDOWN_MS` — ignore re-scans (2500)
- `SCAN_BLOCK_MS` — block duplicate opens (3500)
- `HEARTBEAT_MS` — online ping + emergency open poll (2500)

## API responses

| `granted` | `code` | Hardware |
|-----------|--------|----------|
| true | `access_granted` | Green + open |
| false | `access_denied` | Red + 3 buzz |
| false | `already_inside` / `already_outside` | Red + 2 buzz |
| false | `card_not_registered` | Red + 5 buzz |

Network errors fail **closed** (no open).

## Guard emergency open

1. Live Gate Monitor → gate shows **Online**.
2. **Open Entry** / **Open Exit** + reason.
3. ESP32 opens on next heartbeat; logged as `Override`.
