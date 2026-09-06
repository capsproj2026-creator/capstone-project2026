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

### Arduino IDE 2.x (tested with 2.3.10)

1. **Board package:** ESP32 by Espressif — your log shows **3.3.7** (good).
   - **Tools → Board → Boards Manager** → search `esp32` → install **esp32 3.3.x**
2. **Libraries** (Library Manager):
   | Library | Notes |
   |---------|--------|
   | **WiFiManager** (tzapu) | **Required** for Gate-Setup phone portal |
   | **MFRC522** | RFID reader |
   | **ArduinoJson** | 6.x or 7.x |
   | **ESP32Servo** | Use **3.0.x** with ESP32 board **3.x** (fixes `attach=0`) |

   Or one command: `powershell -File .\scripts\install-esp32-wifimanager.ps1`
3. **Board settings:** Tools → Board → **ESP32 Dev Module** (or your exact board).
4. **Port:** Tools → Port → your ESP32 COM port.
5. Open **`Entry.ino`** from `hardware/arduino/Entry` or `OneDrive\Documents\Arduino\Entry` (after `sync-arduino.bat`).
6. **Upload.** BOOT button only if upload fails to start.

If compile says `wifi_status_t` unknown — you need the latest synced `rfid_gate_common.h` (uses `wl_status_t` for ESP32 3.3.7). Run **`sync-arduino.bat`**.

1. Arduino libraries: **MFRC522**, **ArduinoJson**, **ESP32Servo** (servo mode), **WiFiManager** by tzapu (phone setup portal).
2. Copy `rfid_gate_config.example.h` → `rfid_gate_config.h` (optional defaults for token / first API IP).
3. Match `RFID_API_TOKEN` with Laravel `.env` (or set the token in the portal).
4. In Arduino IDE: **File → Open** → `hardware/arduino/Entry/Entry.ino` or synced OneDrive copy.
5. Flash **Entry** to the servo board. Flash **Exit** from `hardware/arduino/Exit/Exit.ino` to the second ESP32.
6. Laravel must listen on the LAN: `.\start.ps1` (uses `--host=0.0.0.0`).

### Switch Wi‑Fi without reflashing (home / hotspot / campus)

1. Install **WiFiManager** (Library Manager → search `WiFiManager` → author **tzapu**).
2. After upload, if there are no saved credentials (or Wi‑Fi fails), the ESP32 opens AP **`Gate-Setup`** (password **`capstone123`**).
3. On your phone: join `Gate-Setup` → open the captive portal (or `http://192.168.4.1`).
4. Enter:
   - Current location Wi‑Fi SSID + password
   - **Laravel PC IP** from `ipconfig` on the PC (same Wi‑Fi as the ESP32)
   - Port `8000`
   - RFID API token (same as `.env`)
5. Save. Settings are stored in ESP32 flash (NVS).
6. To change network later: hold **BOOT** for **3 seconds** (or **2 seconds at power-on**) to reopen the portal — no Arduino upload needed.

Compile-time `WIFI_SSID` / `API_HOST` in `rfid_gate_config.h` are only defaults if WiFiManager is missing or disabled (`USE_WIFI_MANAGER 0`).

**Arduino IDE folders (auto-sync):** If you open sketches from  
`OneDrive\Documents\Arduino\Entry` and `Exit`, run **`sync-arduino.bat`** once, or **`watch-arduino-sync.bat`** to copy every time firmware files change.  
`start.ps1` also syncs quietly on each start.

**ESP32 firewall (Windows):** If Serial Monitor shows `HTTP error -1 connection refused` but Laravel works in the browser on the PC, double-click **`allow-laravel-firewall.bat`** in the project root once (Admin). That lets Wi‑Fi devices reach port 8000.

**Always-on / power loss:** The ESP32 starts automatically when powered — you only press **BOOT** when uploading code in Arduino IDE, not during normal use. After a blackout, plug power back in; firmware reconnects Wi‑Fi and Laravel by itself (no button press).

### Boot log troubleshooting

| Serial line | Meaning | Fix |
|-------------|---------|-----|
| `Servo attach failed` then `attached=1` | Harmless on old firmware (channel 0 is success) | Re-flash latest sketch; ignore if boom still moves |
| `attached=0` or `WARNING: Servo not attached` | Servo PWM really failed | Install **ESP32Servo 3.x** with Arduino ESP32 3.x; check GPIO 14 wiring + external 5V + common GND |
| Stuck on `connecting wifi` | Old firmware or wrong SSID | Re-upload latest sketch; set `WIFI_SSID` exactly as phone Wi‑Fi list shows (spaces matter) |
| `WiFi: SSID not found` | Wrong network name | Fix `WIFI_SSID` in `rfid_gate_config.h` — yours may be `MERCUSYS_08BA` not `MERCUSYS_08BA 2` |
| `WiFi OK IP:` then heartbeat `-1` | Wrong `API_HOST` / firewall | Open portal (BOOT 3s), set PC LAN IP; run `allow-laravel-firewall.bat` as Admin |
| Portal AP `Gate-Setup` appears | No saved Wi‑Fi or forced setup | Join AP on phone, enter Wi‑Fi + Laravel PC IP |

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
- Guard emergency open (Entry only) → Entry boom servo

Exit firmware uses `ACTUATOR_NONE` (no local servo).

```env
RFID_SHARED_BOOM_GATE_ID=GATE-IN-1
```

## Timing (`rfid_gate_config.h`)

- `GATE_OPEN_MS` — open duration (default 8000 = 8 seconds)
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

1. Live Gate Monitor → Entry gate shows **Online**.
2. **Emergency open** on Entry only + reason (drives the boom servo).
3. Entry ESP32 opens on next heartbeat; logged as `Override`.
Exit has no emergency-open button.
