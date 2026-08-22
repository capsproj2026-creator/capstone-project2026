/**
 * Shared RFID gate client logic — entry and exit sketches include this file.
 * Actuator control stays on ESP32; Laravel only returns grant/deny decisions.
 *
 * Shared boom: wire the servo to the Entry ESP32 only. Exit uses ACTUATOR_NONE;
 * Laravel queues an open to Entry when Exit RFID is granted.
 */
#pragma once

#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <SPI.h>
#include <MFRC522.h>

#if __has_include("rfid_gate_config.h")
#include "rfid_gate_config.h"
#else
#error "Copy rfid_gate_config.example.h to rfid_gate_config.h and configure WiFi/API/token."
#endif

#ifndef ACTUATOR_NONE
#define ACTUATOR_NONE 0
#endif
#ifndef ACTUATOR_RELAY
#define ACTUATOR_RELAY 1
#endif
#ifndef ACTUATOR_SERVO
#define ACTUATOR_SERVO 2
#endif
#ifndef ACTUATOR_MODE
#define ACTUATOR_MODE ACTUATOR_SERVO
#endif

#if ACTUATOR_MODE == ACTUATOR_SERVO
#include <ESP32Servo.h>
#endif

#ifndef GATE_OPEN_MS
#define GATE_OPEN_MS 8000UL
#endif
#ifndef GATE_COOLDOWN_MS
#define GATE_COOLDOWN_MS 2500UL
#endif
#ifndef SCAN_BLOCK_MS
#define SCAN_BLOCK_MS 3500UL
#endif
#ifndef HEARTBEAT_MS
#define HEARTBEAT_MS 1500UL
#endif
#ifndef SERVO_TEST_ON_BOOT
#define SERVO_TEST_ON_BOOT 0
#endif
#ifndef WIFI_CONNECT_TIMEOUT_MS
#define WIFI_CONNECT_TIMEOUT_MS 25000UL
#endif
#ifndef WIFI_RETRY_MAX_MS
#define WIFI_RETRY_MAX_MS 30000UL
#endif
#ifndef HEARTBEAT_FAIL_MAX_MS
#define HEARTBEAT_FAIL_MAX_MS 30000UL
#endif
#ifndef API_HOST
#define API_HOST "127.0.0.1"
#endif
#ifndef API_PORT
#define API_PORT 8000
#endif

#define SS_PIN    5
#define RST_PIN   22
#define SCK_PIN   18
#define MISO_PIN  19
#define MOSI_PIN  23
#define PIN_GREEN 25
#define PIN_RED   26
#define PIN_BUZZER 27
#define PIN_GATE  14

const uint16_t HTTP_CONNECT_MS = 5000;
const uint16_t HTTP_READ_MS    = 10000;
const uint16_t HTTP_HB_CONNECT_MS = 3000;
const uint16_t HTTP_HB_READ_MS    = 5000;

MFRC522 mfrc522(SS_PIN, RST_PIN);

#if ACTUATOR_MODE == ACTUATOR_SERVO
Servo gateServo;
#endif

struct ScanResult {
  bool granted;
  bool openSharedBoom;
  String status;
  String code;
};

unsigned long lastScanMs = 0;
unsigned long lastHeartbeatMs = 0;
unsigned long lastWifiRetryMs = 0;
unsigned long wifiRetryDelayMs = 1000UL;
unsigned long heartbeatIntervalMs = HEARTBEAT_MS;
unsigned long gateCycleEndsMs = 0;
unsigned long gateCloseAtMs = 0;
unsigned long lastRfidRecoverMs = 0;
bool gateIsOpen = false;
bool apiOnline = false;
bool rfidOk = false;
int apiFailStreak = 0;
unsigned long lastLanDiagMs = 0;

String uidToHex(MFRC522::Uid &uid);
ScanResult postScan(const String &uid);
bool pollHeartbeat();
void logLanDiagnostic();
void handleResult(const ScanResult &result, bool forceOpen = false);
void grantAccess();
void denyAccess(const ScanResult &result);
void openGateActuator();
void closeGateActuator();
void updateGateCycle();
void initGateActuator();
bool ensureWifiConnected();
void connectWifiStartup();
void printWifiFailureHelp();
bool initRfidReader();
void recoverRfidIfNeeded();

void logLanDiagnostic() {
  if (millis() - lastLanDiagMs < 15000UL) {
    return;
  }
  lastLanDiagMs = millis();

  Serial.printf("LAN check: ESP32=%s -> %s:%d\n",
                WiFi.localIP().toString().c_str(), API_HOST, API_PORT);

  WiFiClient probe;
  bool tcpOk = probe.connect(API_HOST, API_PORT, 5000);
  Serial.printf("TCP probe %s:%d = %s\n", API_HOST, API_PORT, tcpOk ? "OK" : "FAIL");
  if (tcpOk) {
    probe.stop();
    Serial.println("TCP to PC is OK. Heartbeat HTTP will retry (keep Laravel/start.ps1 open).");
  } else {
    Serial.println("PC unreachable from ESP32. On the PC run allow-laravel-firewall.bat (Admin).");
    Serial.printf("On phone (same Wi-Fi) open: http://%s:%d\n", API_HOST, API_PORT);
    Serial.println("If phone fails too: disable router AP isolation / guest Wi-Fi.");
  }
}

void printWifiFailureHelp() {
  wl_status_t st = WiFi.status();
  Serial.print("WiFi status code: ");
  Serial.println((int)st);
  switch (st) {
    case WL_NO_SSID_AVAIL:
      Serial.println("WiFi: SSID not found — check WIFI_SSID spelling (case + spaces).");
      break;
    case WL_CONNECT_FAILED:
      Serial.println("WiFi: password rejected — check WIFI_PASSWORD.");
      break;
    case WL_DISCONNECTED:
      Serial.println("WiFi: still disconnected — router may be off or out of range.");
      break;
    default:
      Serial.println("WiFi: not connected yet.");
      break;
  }

  Serial.println("Scanning nearby Wi-Fi (5s)...");
  int found = WiFi.scanNetworks(false, true);
  if (found <= 0) {
    Serial.println("  (no networks seen — check 2.4 GHz antenna / router power)");
    return;
  }
  for (int i = 0; i < found && i < 12; i++) {
    String name = WiFi.SSID(i);
    Serial.printf("  [%d] %s (%d dBm)", i + 1, name.c_str(), WiFi.RSSI(i));
    if (name == WIFI_SSID) {
      Serial.print("  <-- matches WIFI_SSID");
    }
    Serial.println();
  }
}

void connectWifiStartup() {
  WiFi.mode(WIFI_STA);
  WiFi.setAutoReconnect(true);
  WiFi.persistent(true);
  WiFi.setSleep(false);
  WiFi.disconnect(true);
  delay(200);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

  Serial.printf("Connecting WiFi SSID=\"%s\" ...", WIFI_SSID);
  unsigned long start = millis();
  while (WiFi.status() != WL_CONNECTED && millis() - start < WIFI_CONNECT_TIMEOUT_MS) {
    delay(400);
    Serial.print(".");
  }
  Serial.println();

  if (WiFi.status() == WL_CONNECTED) {
    Serial.print("WiFi OK IP: ");
    Serial.println(WiFi.localIP());
  } else {
    Serial.println("WiFi not ready yet — will keep retrying in loop (no BOOT button needed).");
    printWifiFailureHelp();
  }
}

bool ensureWifiConnected() {
  if (WiFi.status() == WL_CONNECTED) {
    wifiRetryDelayMs = 1000UL;
    return true;
  }

  if (millis() - lastWifiRetryMs < wifiRetryDelayMs) {
    return false;
  }

  lastWifiRetryMs = millis();
  Serial.println("WiFi lost — reconnecting...");
  WiFi.disconnect();
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

  if (wifiRetryDelayMs < WIFI_RETRY_MAX_MS) {
    wifiRetryDelayMs = min(wifiRetryDelayMs * 2UL, WIFI_RETRY_MAX_MS);
  }
  return false;
}

void initGateActuator() {
#if ACTUATOR_MODE == ACTUATOR_SERVO
  // Reserve LEDC timers before attach (attach=0 means no timer/channel — common on ESP32 core 3.x).
  ESP32PWM::allocateTimer(0);
  ESP32PWM::allocateTimer(1);
  ESP32PWM::allocateTimer(2);
  ESP32PWM::allocateTimer(3);

  gateServo.setPeriodHertz(50);
  int channel = gateServo.attach(PIN_GATE, 500, 2400);
  if (channel <= 0) {
    Serial.println("Servo attach failed on GPIO 14 — retrying simple attach...");
    channel = gateServo.attach(PIN_GATE);
  }
  bool ok = channel > 0;
  Serial.printf("Actuator: SERVO on GPIO %d (channel=%d) — shared boom for Entry+Exit\n", PIN_GATE, channel);
  if (!ok) {
    Serial.println("WARNING: Servo not attached. Update ESP32Servo library (3.x for Arduino ESP32 3.x).");
    Serial.println("         Check signal on GPIO 14 + external 5V + common GND.");
  }
  delay(200);
  if (ok) {
    gateServo.write(SERVO_CLOSE_ANGLE);
    Serial.printf("Servo -> close angle %d\n", SERVO_CLOSE_ANGLE);
  }
  delay(500);

#if SERVO_TEST_ON_BOOT
  Serial.println("Servo boot test: open...");
  gateServo.write(SERVO_OPEN_ANGLE);
  delay(1200);
  Serial.println("Servo boot test: close...");
  gateServo.write(SERVO_CLOSE_ANGLE);
  delay(800);
  Serial.println("Servo boot test done. If arm did not move, check 5V supply + common GND + signal on GPIO 14.");
#endif
#elif ACTUATOR_MODE == ACTUATOR_RELAY
  pinMode(PIN_GATE, OUTPUT);
  digitalWrite(PIN_GATE, LOW);
  Serial.println("Actuator: RELAY");
#else
  Serial.println("Actuator: NONE (RFID only — shared boom is on Entry ESP32)");
#endif
}

bool initRfidReader() {
  // Hard reset pulse on RST — fixes many "online but no UID" boards after bad power-up.
  pinMode(RST_PIN, OUTPUT);
  pinMode(SS_PIN, OUTPUT);
  digitalWrite(SS_PIN, HIGH);
  digitalWrite(RST_PIN, LOW);
  delay(50);
  digitalWrite(RST_PIN, HIGH);
  delay(50);

  SPI.begin(SCK_PIN, MISO_PIN, MOSI_PIN, SS_PIN);
  mfrc522.PCD_Init();
  delay(50);
  mfrc522.PCD_AntennaOn();
  mfrc522.PCD_SetAntennaGain(mfrc522.RxGain_max);

  byte v = mfrc522.PCD_ReadRegister(mfrc522.VersionReg);
  Serial.printf("RC522 VersionReg=0x%02X ", v);
  if (v == 0x00 || v == 0xFF) {
    Serial.println("- NOT DETECTED. Wiring checklist:");
    Serial.println("  3.3V->3.3V (NOT 5V)  GND->GND");
    Serial.println("  SDA/SS->GPIO5  SCK->18  MOSI->23  MISO->19  RST->22");
    rfidOk = false;
    return false;
  }

  Serial.println("- OK (reader found). Hold card ~1-2 cm over the antenna coil.");
  Serial.println("Wiring map: SS=5 RST=22 SCK=18 MOSI=23 MISO=19 | LED G=25 R=26 Buzzer=27 | Servo=14(Entry only)");
  rfidOk = true;
  return true;
}

void recoverRfidIfNeeded() {
  if (rfidOk) {
    return;
  }
  if (millis() - lastRfidRecoverMs < 8000UL) {
    return;
  }
  lastRfidRecoverMs = millis();
  Serial.println("RC522 retry init...");
  initRfidReader();
}

void setupGateHardware() {
  pinMode(PIN_GREEN, OUTPUT);
  pinMode(PIN_RED, OUTPUT);
  pinMode(PIN_BUZZER, OUTPUT);
  digitalWrite(PIN_GREEN, LOW);
  digitalWrite(PIN_RED, LOW);
  digitalWrite(PIN_BUZZER, LOW);

  initGateActuator();
  initRfidReader();
  connectWifiStartup();

  lastHeartbeatMs = millis();
  Serial.printf("Gate %s (%s) ready — power-on auto-start enabled.\n", GATE_ID, DIRECTION);
  Serial.printf("API target: %s\n", API_BASE);
#if defined(GATE_ID) && defined(DIRECTION)
  if (String(GATE_ID) == "GATE-IN-1") {
    Serial.println("ROLE: ENTRY — RC522 + servo GPIO 14. Opens boom for Entry scans and Exit grants.");
  } else if (String(GATE_ID) == "GATE-OUT-1") {
    Serial.println("ROLE: EXIT — RC522 only. No servo. Laravel tells Entry ESP32 to open the boom.");
  }
#endif
  Serial.println("No BOOT button needed for normal use; just keep USB/power connected.");
}

void loopGateClient() {
  updateGateCycle();
  recoverRfidIfNeeded();

  // Keep heartbeats only when Wi-Fi is up — still poll RFID even if Wi-Fi drops
  // so Serial shows UID for wiring tests.
  bool wifiOk = ensureWifiConnected();
  if (wifiOk && millis() - lastHeartbeatMs >= heartbeatIntervalMs) {
    lastHeartbeatMs = millis();
    if (pollHeartbeat()) {
      heartbeatIntervalMs = HEARTBEAT_MS;
      if (!apiOnline) {
        apiOnline = true;
        Serial.println("API online — heartbeats OK");
      }
    } else {
      apiOnline = false;
      heartbeatIntervalMs = min(heartbeatIntervalMs + 2000UL, HEARTBEAT_FAIL_MAX_MS);
    }
  }

  if (!rfidOk) {
    return;
  }

  // MFRC522 often needs two presence checks before a stable serial read.
  if (!mfrc522.PICC_IsNewCardPresent()) {
    return;
  }
  if (!mfrc522.PICC_ReadCardSerial()) {
    // Second presence + read attempt (common RC522 quirk).
    if (!mfrc522.PICC_IsNewCardPresent() || !mfrc522.PICC_ReadCardSerial()) {
      return;
    }
  }

  if (millis() - lastScanMs < GATE_COOLDOWN_MS) {
    mfrc522.PICC_HaltA();
    mfrc522.PCD_StopCrypto1();
    return;
  }
  lastScanMs = millis();

  String uid = uidToHex(mfrc522.uid);
  Serial.print("UID: ");
  Serial.println(uid);

  // Brief green blink = card was read locally (even before Laravel reply).
  digitalWrite(PIN_GREEN, HIGH);
  delay(40);
  digitalWrite(PIN_GREEN, LOW);

  if (!wifiOk) {
    Serial.println("WiFi down — UID seen but not sent to Laravel. Fix WiFi/API_HOST.");
    mfrc522.PICC_HaltA();
    mfrc522.PCD_StopCrypto1();
    return;
  }

  ScanResult result = postScan(uid);
  Serial.printf("Decision: granted=%d shared_boom=%d status=%s code=%s\n",
                result.granted, result.openSharedBoom, result.status.c_str(), result.code.c_str());
  handleResult(result);

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

ScanResult postScan(const String &uid) {
  ScanResult fail = {false, false, "Access Denied", "network_error"};

  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("HTTP skipped: WiFi disconnected");
    return fail;
  }

  WiFiClient client;
  // Do not call client.setTimeout() here — on ESP32 core 3.x it is milliseconds,
  // and a small value (e.g. 5) causes HTTPC_ERROR_READ_TIMEOUT (-11).

  HTTPClient http;
  http.setReuse(false);
  Serial.printf("POST http://%s:%d/api/rfid/scan\n", API_HOST, API_PORT);

  if (!http.begin(client, API_HOST, API_PORT, "/api/rfid/scan")) {
    Serial.println("HTTP error: unable to initialize connection");
    return fail;
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
    Serial.printf("HTTP failed (%d): %s\n", code, HTTPClient::errorToString(code).c_str());
    http.end();
    return fail;
  }

  String response = http.getString();
  http.end();
  Serial.printf("HTTP %d: %s\n", code, response.c_str());

  StaticJsonDocument<768> doc;
  if (deserializeJson(doc, response)) {
    Serial.println("JSON parse error");
    return fail;
  }

  ScanResult result;
  result.granted = doc["granted"] | false;
  result.openSharedBoom = doc["open_shared_boom"] | false;
  result.status = String((const char*)(doc["status"] | "Access Denied"));
  result.code = String((const char*)(doc["code"] | "access_denied"));
  return result;
}

bool pollHeartbeat() {
  WiFiClient client;
  // Avoid client.setTimeout(5) — that is 5ms on many ESP32 cores → error -11.

  HTTPClient http;
  http.setReuse(false);
  if (!http.begin(client, API_HOST, API_PORT, "/api/rfid/heartbeat")) {
    Serial.println("Heartbeat: begin failed");
    return false;
  }

  http.addHeader("Content-Type", "application/json");
  http.addHeader("Connection", "close");
  http.addHeader("X-RFID-TOKEN", RFID_API_TOKEN);
  http.setConnectTimeout(HTTP_HB_CONNECT_MS);
  http.setTimeout(HTTP_HB_READ_MS);

  StaticJsonDocument<128> body;
  body["gate_id"] = GATE_ID;
  String payload;
  serializeJson(body, payload);

  int code = http.POST(payload);
  if (code <= 0) {
    apiFailStreak++;
    Serial.printf("Heartbeat: HTTP error %d (%s) — server %s:%d\n",
                  code, HTTPClient::errorToString(code).c_str(), API_HOST, API_PORT);
    if (apiFailStreak >= 2) {
      logLanDiagnostic();
    }
    http.end();
    return false;
  }

  apiFailStreak = 0;
  String response = http.getString();
  http.end();

  if (code != 200) {
    Serial.printf("Heartbeat: status %d body=%s\n", code, response.c_str());
    return false;
  }

  // Robust parse — ArduinoJson | operator + plain-text fallback.
  bool openCmd = false;
  StaticJsonDocument<384> doc;
  DeserializationError err = deserializeJson(doc, response);
  if (!err) {
    openCmd = doc["open"] | false;
    if (!openCmd && doc.containsKey("command")) {
      const char* cmd = doc["command"] | "";
      openCmd = (strcmp(cmd, "open") == 0);
    }
  } else {
    Serial.printf("Heartbeat: JSON parse fail (%s) body=%s\n", err.c_str(), response.c_str());
  }
  if (!openCmd) {
    openCmd = response.indexOf("\"open\":true") >= 0
      || response.indexOf("\"open\": true") >= 0
      || response.indexOf("\"command\":\"open\"") >= 0;
  }

  if (!openCmd) {
    return true;
  }

  Serial.printf("Heartbeat: OPEN command — %s\n", response.c_str());
  // Drive servo directly (do not depend on ScanResult / RFID debounce).
  digitalWrite(PIN_RED, LOW);
  digitalWrite(PIN_GREEN, HIGH);
  openGateActuator();
  gateIsOpen = true;
  gateCloseAtMs = millis() + GATE_OPEN_MS;
  gateCycleEndsMs = millis() + SCAN_BLOCK_MS;
  Serial.println("Servo OPEN — Entry scan, Exit scan, or emergency (one boom on this board)");
  return true;
}

void handleResult(const ScanResult &result, bool forceOpen) {
  if (result.granted) {
    // RFID debounce only — emergency/shared-boom heartbeat must always move the servo.
    if (!forceOpen && (gateIsOpen || millis() < gateCycleEndsMs)) {
      Serial.println("Gate cycle active — ignoring duplicate RFID open");
      digitalWrite(PIN_GREEN, HIGH);
      delay(200);
      digitalWrite(PIN_GREEN, LOW);
      return;
    }
    grantAccess();
    if (result.openSharedBoom) {
      Serial.println("Laravel queued OPEN on Entry ESP32 (GATE-IN-1). Servo moves there in ~1-2s.");
    }
    return;
  }
  denyAccess(result);
}

void grantAccess() {
  digitalWrite(PIN_RED, LOW);
  digitalWrite(PIN_GREEN, HIGH);
  openGateActuator();
  gateIsOpen = true;
  gateCloseAtMs = millis() + GATE_OPEN_MS;
  gateCycleEndsMs = millis() + SCAN_BLOCK_MS;
#if ACTUATOR_MODE == ACTUATOR_NONE
  Serial.println("Access Granted (Exit) — keep Entry ESP32 + Laravel on; servo opens on Entry in ~1-2s");
#else
  Serial.println("Access Granted (Entry) — servo opening on this board");
#endif
}

void denyAccess(const ScanResult &result) {
  digitalWrite(PIN_GREEN, LOW);
  closeGateActuator();
  gateCloseAtMs = 0;

  digitalWrite(PIN_RED, HIGH);

  int pulses = 3;
  if (result.code == "already_inside" || result.code == "already_outside") {
    pulses = 2;
  } else if (result.code == "card_not_registered") {
    pulses = 5;
  }

  for (int i = 0; i < pulses; i++) {
    digitalWrite(PIN_BUZZER, HIGH);
    delay(120);
    digitalWrite(PIN_BUZZER, LOW);
    delay(80);
  }
  delay(400);
  digitalWrite(PIN_RED, LOW);
  Serial.printf("Denied (%s)\n", result.code.c_str());
  if (result.code == "already_inside") {
    Serial.println("HINT: This card is already INSIDE. Tap it on the EXIT ESP32 (Exit.ino / GATE-OUT-1), not Entry.");
  } else if (result.code == "already_outside") {
    Serial.println("HINT: Tap this card on ENTRY first, then on EXIT. Exit is denied until there is an Entry log.");
  } else if (result.code == "card_not_registered") {
    Serial.println("HINT: Admin -> RFID Assignment -> set UID to this card.");
  } else if (result.code == "network_error") {
    Serial.println("HINT: Exit board must use same Wi-Fi + API_HOST as Entry. Upload Exit.ino (not Entry.ino).");
  }
}

void openGateActuator() {
#if ACTUATOR_MODE == ACTUATOR_SERVO
  if (!gateServo.attached()) {
    Serial.println("Servo OPEN skipped — not attached (see boot log channel=0)");
    return;
  }
  Serial.printf("Servo OPEN -> %d deg\n", SERVO_OPEN_ANGLE);
  gateServo.write(SERVO_OPEN_ANGLE);
#elif ACTUATOR_MODE == ACTUATOR_RELAY
  digitalWrite(PIN_GATE, HIGH);
#else
  Serial.println("No local servo (Exit board). Laravel will open the Entry ESP32 servo.");
#endif
}

void closeGateActuator() {
#if ACTUATOR_MODE == ACTUATOR_SERVO
  if (gateServo.attached()) {
    Serial.printf("Servo CLOSE -> %d deg\n", SERVO_CLOSE_ANGLE);
    gateServo.write(SERVO_CLOSE_ANGLE);
  }
#elif ACTUATOR_MODE == ACTUATOR_RELAY
  digitalWrite(PIN_GATE, LOW);
#endif
  gateIsOpen = false;
}

void updateGateCycle() {
  if (!gateIsOpen || gateCloseAtMs == 0) {
    return;
  }

  if (millis() >= gateCloseAtMs) {
    closeGateActuator();
    digitalWrite(PIN_GREEN, LOW);
    gateCloseAtMs = 0;
    Serial.println("Gate closed");
  }
}
