/**
 * Shared RFID gate client logic — entry and exit sketches include this file.
 * Actuator control stays on ESP32; Laravel only returns grant/deny decisions.
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

#if ACTUATOR_MODE == ACTUATOR_SERVO
#include <ESP32Servo.h>
#endif

#ifndef GATE_OPEN_MS
#define GATE_OPEN_MS 3000UL
#endif
#ifndef GATE_COOLDOWN_MS
#define GATE_COOLDOWN_MS 2500UL
#endif
#ifndef SCAN_BLOCK_MS
#define SCAN_BLOCK_MS 3500UL
#endif
#ifndef HEARTBEAT_MS
#define HEARTBEAT_MS 2500UL
#endif

#if ACTUATOR_MODE == ACTUATOR_SERVO
#include <ESP32Servo.h>
#endif

#ifndef GATE_OPEN_MS
#define GATE_OPEN_MS 3000UL
#endif
#ifndef GATE_COOLDOWN_MS
#define GATE_COOLDOWN_MS 2500UL
#endif
#ifndef SCAN_BLOCK_MS
#define SCAN_BLOCK_MS 3500UL
#endif
#ifndef HEARTBEAT_MS
#define HEARTBEAT_MS 2500UL
#endif
#ifndef SERVO_TEST_ON_BOOT
#define SERVO_TEST_ON_BOOT 1
#endif

#define SS_PIN    5
#define RST_PIN   22
#define PIN_GREEN 25
#define PIN_RED   26
#define PIN_BUZZER 27
#define PIN_GATE  14

const uint16_t HTTP_CONNECT_MS = 8000;
const uint16_t HTTP_READ_MS    = 20000;

MFRC522 mfrc522(SS_PIN, RST_PIN);

#if ACTUATOR_MODE == ACTUATOR_SERVO
Servo gateServo;
#endif

struct ScanResult {
  bool granted;
  String status;
  String code;
};

unsigned long lastScanMs = 0;
unsigned long lastHeartbeatMs = 0;
unsigned long gateCycleEndsMs = 0;
unsigned long gateCloseAtMs = 0;
bool gateIsOpen = false;

String uidToHex(MFRC522::Uid &uid);
ScanResult postScan(const String &uid);
void pollHeartbeat();
void handleResult(const ScanResult &result);
void grantAccess();
void denyAccess(const ScanResult &result);
void openGateActuator();
void closeGateActuator();
void updateGateCycle();
void initGateActuator();

void initGateActuator() {
#if ACTUATOR_MODE == ACTUATOR_SERVO
  // Do NOT pinMode(PIN_GATE) before attach — it breaks PWM on ESP32 Arduino core 3.x
  gateServo.setPeriodHertz(50);
  bool ok = gateServo.attach(PIN_GATE, 500, 2400);
  Serial.printf("Actuator: SERVO on GPIO %d (attach=%d)\n", PIN_GATE, ok ? 1 : 0);
  delay(200);
  gateServo.write(SERVO_CLOSE_ANGLE);
  Serial.printf("Servo -> close angle %d\n", SERVO_CLOSE_ANGLE);
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
#else
  pinMode(PIN_GATE, OUTPUT);
  digitalWrite(PIN_GATE, LOW);
  Serial.println("Actuator: RELAY");
#endif
}

void setupGateHardware() {
  pinMode(PIN_GREEN, OUTPUT);
  pinMode(PIN_RED, OUTPUT);
  pinMode(PIN_BUZZER, OUTPUT);
  digitalWrite(PIN_GREEN, LOW);
  digitalWrite(PIN_RED, LOW);
  digitalWrite(PIN_BUZZER, LOW);

  initGateActuator();

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
  Serial.printf("Gate %s (%s) ready.\n", GATE_ID, DIRECTION);
}

void loopGateClient() {
  updateGateCycle();

  if (WiFi.status() != WL_CONNECTED) {
    WiFi.reconnect();
    delay(500);
    return;
  }

  if (millis() - lastHeartbeatMs >= HEARTBEAT_MS) {
    lastHeartbeatMs = millis();
    pollHeartbeat();
  }

  if (!mfrc522.PICC_IsNewCardPresent() || !mfrc522.PICC_ReadCardSerial()) {
    return;
  }

  if (millis() - lastScanMs < GATE_COOLDOWN_MS) {
    mfrc522.PICC_HaltA();
    return;
  }
  lastScanMs = millis();

  String uid = uidToHex(mfrc522.uid);
  Serial.print("UID: ");
  Serial.println(uid);

  ScanResult result = postScan(uid);
  Serial.printf("Decision: granted=%d status=%s code=%s\n",
                result.granted, result.status.c_str(), result.code.c_str());
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
  ScanResult fail = {false, "Access Denied", "network_error"};

  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("HTTP skipped: WiFi disconnected");
    return fail;
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
  result.status = String((const char*)(doc["status"] | "Access Denied"));
  result.code = String((const char*)(doc["code"] | "access_denied"));
  return result;
}

void pollHeartbeat() {
  WiFiClient client;
  client.setTimeout(5);

  HTTPClient http;
  http.setReuse(false);
  String url = String(API_BASE) + "/api/rfid/heartbeat";
  if (!http.begin(client, url)) {
    return;
  }

  http.addHeader("Content-Type", "application/json");
  http.addHeader("Connection", "close");
  http.addHeader("X-RFID-TOKEN", RFID_API_TOKEN);
  http.setConnectTimeout(4000);
  http.setTimeout(6000);

  StaticJsonDocument<128> body;
  body["gate_id"] = GATE_ID;
  String payload;
  serializeJson(body, payload);

  int code = http.POST(payload);
  if (code <= 0) {
    http.end();
    return;
  }

  String response = http.getString();
  http.end();

  StaticJsonDocument<256> doc;
  if (deserializeJson(doc, response)) {
    return;
  }

  bool openCmd = doc["open"] | false;
  if (openCmd) {
    Serial.println("Heartbeat: emergency open");
    ScanResult granted = {true, "Access Granted", "emergency_open"};
    handleResult(granted);
  }
}

void handleResult(const ScanResult &result) {
  if (result.granted) {
    if (gateIsOpen || millis() < gateCycleEndsMs) {
      Serial.println("Gate cycle active — ignoring duplicate open");
      digitalWrite(PIN_GREEN, HIGH);
      delay(200);
      digitalWrite(PIN_GREEN, LOW);
      return;
    }
    grantAccess();
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
  Serial.println("Access Granted — gate opening");
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
}

void openGateActuator() {
#if ACTUATOR_MODE == ACTUATOR_SERVO
  Serial.printf("Servo OPEN -> %d deg\n", SERVO_OPEN_ANGLE);
  gateServo.write(SERVO_OPEN_ANGLE);
#else
  digitalWrite(PIN_GATE, HIGH);
#endif
}

void closeGateActuator() {
#if ACTUATOR_MODE == ACTUATOR_SERVO
  Serial.printf("Servo CLOSE -> %d deg\n", SERVO_CLOSE_ANGLE);
  gateServo.write(SERVO_CLOSE_ANGLE);
#else
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
