/**
 * Capstone Smart Campus VMS — ESP32 + RC522 RFID EXIT Gate Client
 *
 * Same wiring as Entry:
 *   RC522  → ESP32
 *   SDA    → GPIO 5
 *   SCK    → GPIO 18
 *   MOSI   → GPIO 23
 *   MISO   → GPIO 19
 *   RST    → GPIO 22
 *   3.3V   → 3.3V
 *   GND    → GND
 *
 *   GREEN_LED → GPIO 25
 *   RED_LED   → GPIO 26
 *   BUZZER    → GPIO 27
 *   GATE_RELAY→ GPIO 14  (HIGH = open)
 *
 * Libraries:
 *   - MFRC522
 *   - ArduinoJson
 *
 * This sketch sends direction = "Exit"
 */

#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <SPI.h>
#include <MFRC522.h>

// ========== CONFIGURE THESE ==========
const char* WIFI_SSID     = "YOUR_WIFI_SSID";
const char* WIFI_PASSWORD = "YOUR_WIFI_PASSWORD";

// Must be THIS PC's Wi‑Fi IPv4 (ipconfig). Not localhost. No trailing slash.
// Laravel must listen on all interfaces: php artisan serve --host=0.0.0.0 --port=8000
const char* API_BASE      = "http://192.168.1.104:8000";
const char* RFID_API_TOKEN = "capstone-rfid-dev-token-change-me";

// EXIT gate settings
const char* GATE_ID   = "GATE-OUT-1";
const char* DIRECTION = "Exit";
// =====================================

#define SS_PIN   5
#define RST_PIN  22
#define PIN_GREEN 25
#define PIN_RED   26
#define PIN_BUZZER 27
#define PIN_GATE  14

const unsigned long GATE_OPEN_MS = 3000;
const unsigned long COOLDOWN_MS  = 2500;
const uint16_t HTTP_CONNECT_MS = 8000;
const uint16_t HTTP_READ_MS    = 20000;

MFRC522 mfrc522(SS_PIN, RST_PIN);
unsigned long lastScanMs = 0;

void setup() {
  Serial.begin(115200);

  pinMode(PIN_GREEN, OUTPUT);
  pinMode(PIN_RED, OUTPUT);
  pinMode(PIN_BUZZER, OUTPUT);
  pinMode(PIN_GATE, OUTPUT);
  digitalWrite(PIN_GREEN, LOW);
  digitalWrite(PIN_RED, LOW);
  digitalWrite(PIN_BUZZER, LOW);
  digitalWrite(PIN_GATE, LOW);

  SPI.begin();
  mfrc522.PCD_Init();

  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  Serial.print("Connecting WiFi");
  while (WiFi.status() != WL_CONNECTED) {
    delay(400);
    Serial.print(".");
  }
  Serial.println();
  Serial.print("IP: ");
  Serial.println(WiFi.localIP());
  Serial.println("RFID EXIT gate ready.");
}

void loop() {
  if (WiFi.status() != WL_CONNECTED) {
    WiFi.reconnect();
    delay(500);
    return;
  }

  if (!mfrc522.PICC_IsNewCardPresent() || !mfrc522.PICC_ReadCardSerial()) {
    return;
  }

  if (millis() - lastScanMs < COOLDOWN_MS) {
    mfrc522.PICC_HaltA();
    return;
  }
  lastScanMs = millis();

  String uid = uidToHex(mfrc522.uid);
  Serial.print("UID: ");
  Serial.println(uid);

  String status = postScan(uid);
  handleResult(status);

  mfrc522.PICC_HaltA();
  mfrc522.PCD_StopCrypto1();
}

String uidToHex(MFRC522::Uid &uid) {
  String out = "";
  for (byte i = 0; i < uid.size; i++) {
    if (uid.uidByte[i] < 0x10) out += "0";
    out += String(uid.uidByte[i], HEX);
  }
  out.toUpperCase();
  return out;
}

String postScan(const String &uid) {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("HTTP skipped: WiFi is disconnected");
    return "Access Denied";
  }

  WiFiClient client;
  client.setTimeout(HTTP_READ_MS / 1000);

  HTTPClient http;
  http.setReuse(false);
  String url = String(API_BASE) + "/api/rfid/scan";
  Serial.print("POST ");
  Serial.println(url);

  if (!http.begin(client, url)) {
    Serial.println("HTTP error: unable to initialize connection");
    return "Access Denied";
  }

  http.addHeader("Content-Type", "application/json");
  http.addHeader("Connection", "close");
  http.addHeader("X-RFID-TOKEN", RFID_API_TOKEN);
  http.setConnectTimeout(HTTP_CONNECT_MS);
  http.setTimeout(HTTP_READ_MS);

  StaticJsonDocument<256> body;
  body["uid"] = uid;
  body["gate_id"] = GATE_ID;
  body["direction"] = DIRECTION;

  String payload;
  serializeJson(body, payload);

  int code = http.POST(payload);

  if (code <= 0) {
    Serial.printf(
      "HTTP connection failed (%d): %s\n",
      code,
      HTTPClient::errorToString(code).c_str()
    );
    Serial.println("Check: php artisan serve --host=0.0.0.0 --port=8000  + Windows Firewall port 8000.");
    http.end();
    return "Access Denied";
  }

  String response = http.getString();
  http.end();

  Serial.printf("HTTP %d: %s\n", code, response.c_str());

  StaticJsonDocument<768> doc;
  DeserializationError err = deserializeJson(doc, response);
  if (err) {
    Serial.println("JSON parse error");
    return "Access Denied";
  }

  if (doc.containsKey("granted")) {
    return doc["granted"].as<bool>() ? String("Access Granted") : String("Access Denied");
  }

  const char* status = doc["status"] | "Access Denied";
  return String(status);
}

void handleResult(const String &status) {
  if (status == "Access Granted") {
    grantAccess();
  } else {
    denyAccess();
  }
}

void grantAccess() {
  digitalWrite(PIN_RED, LOW);
  digitalWrite(PIN_GREEN, HIGH);
  digitalWrite(PIN_GATE, HIGH);
  Serial.println("Access Granted — EXIT gate open");
  delay(GATE_OPEN_MS);
  digitalWrite(PIN_GATE, LOW);
  digitalWrite(PIN_GREEN, LOW);
}

void denyAccess() {
  digitalWrite(PIN_GREEN, LOW);
  digitalWrite(PIN_GATE, LOW);
  digitalWrite(PIN_RED, HIGH);
  for (int i = 0; i < 3; i++) {
    digitalWrite(PIN_BUZZER, HIGH);
    delay(120);
    digitalWrite(PIN_BUZZER, LOW);
    delay(80);
  }
  delay(600);
  digitalWrite(PIN_RED, LOW);
  Serial.println("Denied — red LED + buzzer");
}
