# ESP32 RFID Boom Gate

Physical gate control runs on the **ESP32**, not Laravel. Laravel validates RFID and returns `{ granted, status, code }`; the ESP32 opens or denies locally.

## Flow

```
RFID tap → ESP32 → POST /api/rfid/scan → RfidAccessService → JSON response → relay/servo
```

## Setup

1. Install Arduino libraries: **MFRC522**, **ArduinoJson**, **ESP32Servo** (servo mode only).
2. Copy `rfid_gate_config.example.h` → `rfid_gate_config.h` and set WiFi, `API_BASE`, `RFID_API_TOKEN`.
3. Match `RFID_API_TOKEN` with Laravel `.env` `RFID_API_TOKEN`.
4. Flash **entry**: `esp32_rfid_gate/esp32_rfid_gate.ino`  
   Flash **exit**: `esp32_rfid_gate_exit/esp32_rfid_gate_exit.ino`
5. Run Laravel on all interfaces: `php artisan serve --host=0.0.0.0 --port=8000`

## Wiring

| Signal | GPIO | Notes |
|--------|------|-------|
| RC522 SDA | 5 | SPI |
| RC522 RST | 22 | |
| RC522 SCK/MOSI/MISO | 18/23/19 | |
| GREEN LED | 25 | Grant |
| RED LED | 26 | Deny |
| BUZZER | 27 | Deny pattern |
| GATE (relay/servo) | 14 | See actuator modes |

## Actuator modes (`rfid_gate_config.h`)

| Mode | Use when |
|------|----------|
| `ACTUATOR_RELAY` (default) | Relay module drives boom barrier (HIGH = open) |
| `ACTUATOR_SERVO` | PWM servo arm — use **external 5V** supply, common GND |

Timing:

- `GATE_OPEN_MS` — open duration (default 3000 ms)
- `GATE_COOLDOWN_MS` — ignore re-scans after read (2500 ms)
- `SCAN_BLOCK_MS` — block duplicate opens during active cycle (3500 ms)

## API responses

| `granted` | `code` | Hardware |
|-----------|--------|----------|
| true | `access_granted` | Green LED + open gate |
| false | `access_denied` | Red + 3 buzz pulses |
| false | `already_inside` / `already_outside` | Red + 2 pulses |
| false | `card_not_registered` | Red + 5 pulses |

Network/parse errors fail **closed** (deny, no open).

## Safety

- Gate opens only when `granted: true`.
- Duplicate scans during an open cycle do not re-trigger the actuator.
- Non-blocking gate close — RFID loop keeps running during open window.
- Heartbeat every `HEARTBEAT_MS` (default 2.5s) marks the gate **Online** on Live Gate Monitor.
- Guard **Emergency Open** queues a one-shot command; the ESP32 opens on the next heartbeat (no extra hardware).

## Guard emergency open

1. Open **Live Gate Monitor**.
2. Confirm the gate shows **Online** (ESP32 must be flashed with this firmware).
3. Click **Open Entry** / **Open Exit**, enter a reason, send.
4. The boom opens once; the action is stored in Access Logs as `Override`.
