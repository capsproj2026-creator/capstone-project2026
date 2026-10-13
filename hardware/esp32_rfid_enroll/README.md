# Desk UID reader

Flash this sketch on a **separate** ESP32. A tap saves the card UID for RFID assignment and visitor registration. It does not open the gate and it does not appear on the live gate monitor.

## Flash

1. Copy `rfid_enroll_config.example.h` to `rfid_enroll_config.h`.
2. Put in the same Wi-Fi, `API_HOST`, `API_PORT`, and `RFID_API_TOKEN` used by the gate ESP32.
3. Arduino IDE: board **ESP32 Dev Module**. Libraries: **MFRC522**, **ArduinoJson**.
4. Open `esp32_rfid_enroll.ino` and upload.

## Wiring

| RC522 | ESP32 |
|-------|-------|
| SDA   | GPIO 5 |
| SCK   | GPIO 18 |
| MOSI  | GPIO 23 |
| MISO  | GPIO 19 |
| RST   | GPIO 22 |
| 3.3V  | 3.3V |
| GND   | GND |

Optional feedback: green LED GPIO 25, red LED GPIO 26, buzzer GPIO 27. No servo.

## Use

Open **Assign RFID** or visitor registration, then tap the card on this reader. A green blink means the UID was saved. The Entry and Exit boards are unchanged.
