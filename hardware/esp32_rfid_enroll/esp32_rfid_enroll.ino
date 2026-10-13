/**
 * Capstone Smart Campus VMS — desk UID reader.
 *
 * Flash this on a separate ESP32 with an RC522. A tap is posted to
 * POST /api/rfid/enroll and filled into RFID assignment. It does not
 * call /api/rfid/scan, so the live gate monitor stays unchanged.
 *
 * Arduino IDE: ESP32 Dev Module, libraries MFRC522 and ArduinoJson.
 * Copy rfid_enroll_config.example.h to rfid_enroll_config.h first.
 *
 * RC522: SDA 5, SCK 18, MOSI 23, MISO 19, RST 22, 3.3V, GND.
 * Optional: green LED GPIO 25, red LED GPIO 26, buzzer GPIO 27.
 */

#include <WiFi.h>
#include <HTTPClient.h>
#include <WiFiClientSecure.h>
#include <SPI.h>
#include <MFRC522.h>
#include <ArduinoJson.h>
#include "rfid_enroll_config.h"

#define SS_PIN 5
#define RST_PIN 22
#define PIN_GREEN 25
#define PIN_RED 26
#define PIN_BUZZER 27

MFRC522 mfrc522(SS_PIN, RST_PIN);

String lastUid = "";
unsigned long lastPostMs = 0;
bool wifiOk = false;

bool usesTls() {
  return API_PORT == 443;
}

void setLed(int pin, bool on) {
  digitalWrite(pin, on ? HIGH : LOW);
}

void beep(int times, int ms) {
  for (int i = 0; i < times; i++) {
    digitalWrite(PIN_BUZZER, HIGH);
    delay(ms);
    digitalWrite(PIN_BUZZER, LOW);
    if (i + 1 < times) delay(80);
  }
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

void connectWifi() {
  if (WiFi.status() == WL_CONNECTED) {
    wifiOk = true;
    return;
  }

  Serial.printf("WiFi connecting to %s\n", WIFI_SSID);
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

  unsigned long started = millis();
  while (WiFi.status() != WL_CONNECTED && millis() - started < 20000UL) {
    delay(250);
    Serial.print(".");
  }
  Serial.println();

  wifiOk = WiFi.status() == WL_CONNECTED;
  if (wifiOk) {
    Serial.print("WiFi OK ");
    Serial.println(WiFi.localIP());
    setLed(PIN_GREEN, true);
    delay(120);
    setLed(PIN_GREEN, false);
  } else {
    Serial.println("WiFi failed. Check WIFI_SSID in rfid_enroll_config.h.");
    setLed(PIN_RED, true);
    delay(200);
    setLed(PIN_RED, false);
  }
}

void preparePost(HTTPClient &http) {
  http.setReuse(false);
  http.addHeader("Content-Type", "application/json");
  http.addHeader("Connection", "close");
  http.addHeader("X-RFID-TOKEN", RFID_API_TOKEN);
  http.setConnectTimeout(12000);
  http.setTimeout(12000);
}

int postWithClient(bool tls, const String &payload, String &response) {
  HTTPClient http;

  if (tls) {
    WiFiClientSecure client;
    client.setInsecure();
    client.setHandshakeTimeout(12);
    if (!http.begin(client, API_HOST, API_PORT, "/api/rfid/enroll", true)) {
      Serial.println("HTTPS begin failed");
      return -1;
    }
    preparePost(http);
    int code = http.POST(payload);
    if (code > 0) response = http.getString();
    http.end();
    return code;
  }

  WiFiClient client;
  if (!http.begin(client, API_HOST, API_PORT, "/api/rfid/enroll", false)) {
    Serial.println("HTTP begin failed");
    return -1;
  }
  preparePost(http);
  int code = http.POST(payload);
  if (code > 0) response = http.getString();
  http.end();
  return code;
}

bool postEnrollment(const String &uid) {
  if (WiFi.status() != WL_CONNECTED) {
    wifiOk = false;
    return false;
  }

  StaticJsonDocument<96> body;
  body["uid"] = uid;
  String payload;
  serializeJson(body, payload);

  Serial.printf("POST %s://%s:%d/api/rfid/enroll uid=%s\n",
                usesTls() ? "https" : "http", API_HOST, API_PORT, uid.c_str());

  int code = -1;
  String response;
  if (usesTls()) {
    code = postWithClient(true, payload, response);
  } else {
    code = postWithClient(false, payload, response);
  }

  Serial.printf("HTTP %d: %s\n", code, response.c_str());
  if (code != 200) return false;

  StaticJsonDocument<256> doc;
  if (deserializeJson(doc, response)) return false;
  return doc["ok"] | false;
}

void setup() {
  Serial.begin(115200);
  delay(200);
  Serial.println();
  Serial.println("=== Desk UID reader (not a gate) ===");

  pinMode(PIN_GREEN, OUTPUT);
  pinMode(PIN_RED, OUTPUT);
  pinMode(PIN_BUZZER, OUTPUT);
  setLed(PIN_GREEN, false);
  setLed(PIN_RED, false);
  digitalWrite(PIN_BUZZER, LOW);

  SPI.begin();
  mfrc522.PCD_Init();
  connectWifi();
}

void loop() {
  if (WiFi.status() != WL_CONNECTED) {
    wifiOk = false;
    if (millis() - lastPostMs > 8000UL) {
      lastPostMs = millis();
      connectWifi();
    }
    delay(50);
    return;
  }
  wifiOk = true;

  if (!mfrc522.PICC_IsNewCardPresent() || !mfrc522.PICC_ReadCardSerial()) {
    delay(40);
    return;
  }

  String uid = uidToHex(mfrc522.uid);
  mfrc522.PICC_HaltA();
  mfrc522.PCD_StopCrypto1();

  if (uid.length() < 6) {
    Serial.println("Ignored short UID");
    return;
  }

  if (uid == lastUid && millis() - lastPostMs < SAME_UID_COOLDOWN_MS) {
    return;
  }

  Serial.print("UID: ");
  Serial.println(uid);

  bool saved = postEnrollment(uid);
  lastUid = uid;
  lastPostMs = millis();

  if (saved) {
    setLed(PIN_GREEN, true);
    beep(1, 60);
    delay(180);
    setLed(PIN_GREEN, false);
    Serial.println("Saved for assignment. Live gate monitor was not updated.");
  } else {
    setLed(PIN_RED, true);
    beep(2, 90);
    delay(200);
    setLed(PIN_RED, false);
    Serial.println("UID was read, but the server did not save it.");
  }
}
