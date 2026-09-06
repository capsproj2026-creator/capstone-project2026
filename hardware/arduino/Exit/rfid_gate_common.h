/**
 * Shared RFID gate client logic — entry and exit sketches include this file.
 * Actuator control stays on ESP32; Laravel only returns grant/deny decisions.
 *
 * Shared boom: wire the servo to the Entry ESP32 only. Exit uses ACTUATOR_NONE;
 * Laravel queues an open to Entry when Exit RFID is granted.
 *
 * Network: WiFiManager phone portal + NVS-saved Laravel host/port/token so you can
 * switch home Wi-Fi / hotspot / campus without reflashing. Hold BOOT 3s to reopen portal.
 */
#pragma once

#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <SPI.h>
#include <MFRC522.h>
#include <Preferences.h>

// Config first so USE_WIFI_MANAGER is known before the WiFiManager include check.
#if __has_include("rfid_gate_config.h")
#include "rfid_gate_config.h"
#else
#error "Copy rfid_gate_config.example.h to rfid_gate_config.h and configure defaults/token."
#endif

#ifndef USE_WIFI_MANAGER
#define USE_WIFI_MANAGER 1
#endif

#if USE_WIFI_MANAGER
// Include directly so Arduino's library scanner adds WiFiManager to the path.
// __has_include(<WiFiManager.h>) is false until that happens, which caused a
// false "WiFiManager required" error even when the library was installed.
#include <WiFiManager.h>
#define GATE_HAS_WIFI_MANAGER 1
#else
#define GATE_HAS_WIFI_MANAGER 0
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

// Compile-time defaults (overridden by phone portal / NVS when WiFiManager is available).
#ifndef API_HOST
#define API_HOST "127.0.0.1"
#endif
#ifndef API_PORT
#define API_PORT 8000
#endif
#ifndef WIFI_SSID
#define WIFI_SSID ""
#endif
#ifndef WIFI_PASSWORD
#define WIFI_PASSWORD ""
#endif
#ifndef RFID_API_TOKEN
#define RFID_API_TOKEN "capstone-rfid-dev-token-change-me"
#endif
#ifndef WIFI_PORTAL_AP_NAME
#define WIFI_PORTAL_AP_NAME "Gate-Setup"
#endif
#ifndef WIFI_PORTAL_AP_PASS
#define WIFI_PORTAL_AP_PASS "capstone123"
#endif
#ifndef FORCE_CONFIG_PIN
#define FORCE_CONFIG_PIN 0
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
Preferences gatePrefs;

#if ACTUATOR_MODE == ACTUATOR_SERVO
Servo gateServo;
#endif

// Runtime network target (NVS / portal). Prefer these over compile-time macros in HTTP calls.
String runtimeApiHost = API_HOST;
uint16_t runtimeApiPort = (uint16_t) API_PORT;
String runtimeApiToken = RFID_API_TOKEN;

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
unsigned long forceConfigHoldStartMs = 0;
bool gateIsOpen = false;
bool apiOnline = false;
bool rfidOk = false;
bool portalBusy = false;
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
void loadNetworkPrefs();
void saveNetworkPrefs();
String runtimeApiBase();
bool wifiManagerEnabled();
bool forceConfigRequested();
void startWifiConfigPortal(bool force = false);
void pollForceConfigButton();

String runtimeApiBase() {
  return String("http://") + runtimeApiHost + ":" + String(runtimeApiPort);
}

bool wifiManagerEnabled() {
#if GATE_HAS_WIFI_MANAGER && USE_WIFI_MANAGER
  return true;
#else
  return false;
#endif
}

void loadNetworkPrefs() {
  runtimeApiHost = API_HOST;
  runtimeApiPort = (uint16_t) API_PORT;
  runtimeApiToken = RFID_API_TOKEN;

  if (!gatePrefs.begin("gate", true)) {
    return;
  }
  String host = gatePrefs.getString("api_host", "");
  uint16_t port = (uint16_t) gatePrefs.getUShort("api_port", 0);
  String token = gatePrefs.getString("api_token", "");
  gatePrefs.end();

  if (host.length() > 0) {
    runtimeApiHost = host;
  }
  if (port > 0) {
    runtimeApiPort = port;
  }
  if (token.length() > 0) {
    runtimeApiToken = token;
  }
}

void saveNetworkPrefs() {
  if (!gatePrefs.begin("gate", false)) {
    Serial.println("NVS: failed to open gate prefs for write");
    return;
  }
  gatePrefs.putString("api_host", runtimeApiHost);
  gatePrefs.putUShort("api_port", runtimeApiPort);
  gatePrefs.putString("api_token", runtimeApiToken);
  gatePrefs.end();
  Serial.printf("NVS saved API %s:%u\n", runtimeApiHost.c_str(), runtimeApiPort);
}

bool forceConfigRequested() {
  pinMode(FORCE_CONFIG_PIN, INPUT_PULLUP);
  delay(20);
  if (digitalRead(FORCE_CONFIG_PIN) != LOW) {
    return false;
  }
  Serial.println("BOOT held — wait 2s to open Wi-Fi / API setup portal...");
  unsigned long start = millis();
  while (digitalRead(FORCE_CONFIG_PIN) == LOW) {
    if (millis() - start >= 2000UL) {
      return true;
    }
    delay(20);
  }
  return false;
}

#if GATE_HAS_WIFI_MANAGER && USE_WIFI_MANAGER
void startWifiConfigPortal(bool force) {
  portalBusy = true;
  WiFiManager wm;
  wm.setConfigPortalTimeout(180);
  wm.setConnectTimeout(25);
  wm.setTitle("Capstone Gate Setup");
  wm.setWiFiAutoReconnect(true);
  // Open portal automatically if saved/preloaded Wi-Fi fails to connect.
  wm.setEnableConfigPortal(true);
  wm.setConfigPortalBlocking(true);

  // Seed portal / first connect from rfid_gate_config.h (overridden when user saves in portal).
  if (String(WIFI_SSID).length() > 0) {
    wm.preloadWiFi(String(WIFI_SSID), String(WIFI_PASSWORD));
  }

  char hostBuf[48];
  char portBuf[8];
  char tokenBuf[80];
  snprintf(hostBuf, sizeof(hostBuf), "%s", runtimeApiHost.c_str());
  snprintf(portBuf, sizeof(portBuf), "%u", runtimeApiPort);
  snprintf(tokenBuf, sizeof(tokenBuf), "%s", runtimeApiToken.c_str());

  WiFiManagerParameter pHost("api_host", "Laravel PC IP (ipconfig)", hostBuf, 47);
  WiFiManagerParameter pPort("api_port", "Laravel port", portBuf, 7);
  WiFiManagerParameter pToken("api_token", "RFID API token (same as .env)", tokenBuf, 79);
  wm.addParameter(&pHost);
  wm.addParameter(&pPort);
  wm.addParameter(&pToken);

  wm.setSaveParamsCallback([&]() {
    String host = String(pHost.getValue());
    host.trim();
    if (host.length() > 0) {
      runtimeApiHost = host;
    }
    int port = atoi(pPort.getValue());
    if (port > 0 && port < 65536) {
      runtimeApiPort = (uint16_t) port;
    }
    String token = String(pToken.getValue());
    token.trim();
    if (token.length() > 0) {
      runtimeApiToken = token;
    }
    saveNetworkPrefs();
  });

  Serial.println();
  Serial.println("=== Gate Wi-Fi / API portal (WiFiManager) ===");
  Serial.printf("1) On phone join Wi-Fi AP: %s  password: %s\n", WIFI_PORTAL_AP_NAME, WIFI_PORTAL_AP_PASS);
  Serial.println("2) Browser opens setup (or go to http://192.168.4.1)");
  Serial.println("3) Pick this location's 2.4 GHz Wi-Fi + Laravel PC IP + token");
  Serial.printf("   Default API target: %s\n", runtimeApiBase().c_str());
  if (String(WIFI_SSID).length() > 0) {
    Serial.printf("   Preloaded SSID from config: %s\n", WIFI_SSID);
  }
  Serial.println("==============================");

  bool ok = false;
  if (force) {
    ok = wm.startConfigPortal(WIFI_PORTAL_AP_NAME, WIFI_PORTAL_AP_PASS);
  } else {
    ok = wm.autoConnect(WIFI_PORTAL_AP_NAME, WIFI_PORTAL_AP_PASS);
  }

  // Always re-read custom params after portal closes (save callback may not fire on all paths).
  String host = String(pHost.getValue());
  host.trim();
  if (host.length() > 0) {
    runtimeApiHost = host;
  }
  int port = atoi(pPort.getValue());
  if (port > 0 && port < 65536) {
    runtimeApiPort = (uint16_t) port;
  }
  String token = String(pToken.getValue());
  token.trim();
  if (token.length() > 0) {
    runtimeApiToken = token;
  }
  saveNetworkPrefs();

  portalBusy = false;
  WiFi.mode(WIFI_STA);
  WiFi.setAutoReconnect(true);
  WiFi.setSleep(false);

  if (ok || WiFi.status() == WL_CONNECTED) {
    Serial.print("WiFi OK IP: ");
    Serial.println(WiFi.localIP());
    Serial.printf("API target: %s\n", runtimeApiBase().c_str());
  } else {
    Serial.println("Portal finished without Wi-Fi — will keep retrying.");
  }
}
#else
void startWifiConfigPortal(bool force) {
  (void) force;
  Serial.println("WiFiManager disabled (USE_WIFI_MANAGER 0). Using WIFI_SSID from rfid_gate_config.h only.");
}
#endif

void pollForceConfigButton() {
  if (portalBusy || !wifiManagerEnabled()) {
    return;
  }
  if (digitalRead(FORCE_CONFIG_PIN) == LOW) {
    if (forceConfigHoldStartMs == 0) {
      forceConfigHoldStartMs = millis();
    } else if (millis() - forceConfigHoldStartMs >= 3000UL) {
      Serial.println("BOOT held 3s — opening setup portal...");
      forceConfigHoldStartMs = 0;
      startWifiConfigPortal(true);
    }
  } else {
    forceConfigHoldStartMs = 0;
  }
}

void logLanDiagnostic() {
  if (millis() - lastLanDiagMs < 15000UL) {
    return;
  }
  lastLanDiagMs = millis();

  Serial.printf("LAN check: ESP32=%s -> %s:%u\n",
                WiFi.localIP().toString().c_str(), runtimeApiHost.c_str(), runtimeApiPort);

  WiFiClient probe;
  bool tcpOk = probe.connect(runtimeApiHost.c_str(), runtimeApiPort, 5000);
  Serial.printf("TCP probe %s:%u = %s\n", runtimeApiHost.c_str(), runtimeApiPort, tcpOk ? "OK" : "FAIL");
  if (tcpOk) {
    probe.stop();
    Serial.println("TCP to PC is OK. Heartbeat HTTP will retry (keep Laravel/start.ps1 open).");
  } else {
    Serial.println("PC unreachable from ESP32. On the PC run allow-laravel-firewall.bat (Admin).");
    Serial.printf("On phone (same Wi-Fi) open: http://%s:%u\n", runtimeApiHost.c_str(), runtimeApiPort);
    Serial.println("If phone fails too: disable router AP isolation / guest Wi-Fi.");
    Serial.println("Wrong network? Hold BOOT 3s to reopen Gate-Setup portal and enter new Wi-Fi + PC IP.");
  }
}

void printWifiFailureHelp() {
  wl_status_t st = WiFi.status();
  Serial.print("WiFi status code: ");
  Serial.println((int)st);
  switch (st) {
    case WL_NO_SSID_AVAIL:
      Serial.println("WiFi: SSID not found — open Gate-Setup portal (hold BOOT 3s) and pick the network.");
      break;
    case WL_CONNECT_FAILED:
      Serial.println("WiFi: password rejected — reopen portal and re-enter password.");
      break;
    case WL_DISCONNECTED:
      Serial.println("WiFi: still disconnected — router may be off or out of range.");
      break;
    default:
      Serial.println("WiFi: not connected yet.");
      break;
  }

  // Fresh STA mode before scan (scan after a failed begin() often returns 0 otherwise).
  WiFi.persistent(false);
  WiFi.disconnect(true, true);
  delay(200);
  WiFi.mode(WIFI_STA);
  WiFi.setSleep(false);
  delay(100);

  Serial.println("Scanning nearby Wi-Fi (async, ~6s)...");
  WiFi.scanDelete();
  int found = WiFi.scanNetworks(/*async=*/false, /*show_hidden=*/true);
  if (found == WIFI_SCAN_FAILED) {
    Serial.println("  Scan failed — power-cycle ESP32 and check antenna connector.");
    return;
  }
  if (found <= 0) {
    Serial.println("  (no networks seen)");
    Serial.println("  Checklist:");
    Serial.println("   1) ESP32 is 2.4 GHz only — enable 2.4 GHz on the router (or use a mixed SSID).");
    Serial.println("   2) External antenna screwed onto the board (IPEX/u.FL) if your module needs one.");
    Serial.println("   3) Move closer to the router; avoid metal boxes.");
    Serial.println("   4) Install Arduino library 'WiFiManager' by tzapu, re-flash, join AP Gate-Setup.");
    return;
  }
  String want = String(WIFI_SSID);
  for (int i = 0; i < found && i < 12; i++) {
    String name = WiFi.SSID(i);
    Serial.printf("  [%d] %s (%d dBm)", i + 1, name.c_str(), WiFi.RSSI(i));
    if (want.length() > 0 && name == want) {
      Serial.print("  <-- matches WIFI_SSID default");
    }
    Serial.println();
  }
  WiFi.scanDelete();
}

void connectWifiStartup() {
  loadNetworkPrefs();
  pinMode(FORCE_CONFIG_PIN, INPUT_PULLUP);

  WiFi.mode(WIFI_STA);
  WiFi.setAutoReconnect(true);
  WiFi.persistent(true);
  WiFi.setSleep(false);

  bool forcePortal = forceConfigRequested();

  if (wifiManagerEnabled()) {
    if (forcePortal) {
      Serial.println("Forced setup portal (BOOT held at power-on).");
      startWifiConfigPortal(true);
    } else {
      Serial.println("WiFiManager: connecting with saved credentials (or opening Gate-Setup portal)...");
      startWifiConfigPortal(false);
    }
    return;
  }

  Serial.println("NOTE: USE_WIFI_MANAGER is 0 — using WIFI_SSID from rfid_gate_config.h only.");

  // Legacy compile-time Wi-Fi.
  if (String(WIFI_SSID).length() == 0) {
    Serial.println("ERROR: WIFI_SSID empty. Set it in rfid_gate_config.h or enable USE_WIFI_MANAGER.");
    return;
  }

  WiFi.disconnect(true, true);
  delay(300);
  WiFi.mode(WIFI_STA);
  WiFi.setSleep(false);
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
    Serial.printf("API target: %s\n", runtimeApiBase().c_str());
  } else {
    Serial.println("WiFi not ready yet — will keep retrying in loop (no BOOT button needed).");
    printWifiFailureHelp();
    // Resume STA connect after diagnostic scan.
    WiFi.mode(WIFI_STA);
    WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  }
}

bool ensureWifiConnected() {
  if (portalBusy) {
    return false;
  }

  if (WiFi.status() == WL_CONNECTED) {
    wifiRetryDelayMs = 1000UL;
    return true;
  }

  if (millis() - lastWifiRetryMs < wifiRetryDelayMs) {
    return false;
  }

  lastWifiRetryMs = millis();
  Serial.println("WiFi lost — reconnecting...");

  if (wifiManagerEnabled()) {
    WiFi.reconnect();
  } else {
    WiFi.disconnect();
    WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  }

  if (wifiRetryDelayMs < WIFI_RETRY_MAX_MS) {
    wifiRetryDelayMs = min(wifiRetryDelayMs * 2UL, WIFI_RETRY_MAX_MS);
  }

  // After several failures, open portal so user can switch to home/hotspot/campus.
  static int wifiFailStreak = 0;
  wifiFailStreak++;
  if (wifiManagerEnabled() && wifiFailStreak >= 6) {
    wifiFailStreak = 0;
    Serial.println("WiFi still down — opening Gate-Setup portal.");
    startWifiConfigPortal(true);
  }
  if (WiFi.status() == WL_CONNECTED) {
    wifiFailStreak = 0;
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
  // attach() may return 0 on success (LEDC channel 0). Use attached() — 0 is not a failure.
  int channel = gateServo.attach(PIN_GATE, 500, 2400);
  if (!gateServo.attached()) {
    Serial.println("Servo attach failed on GPIO 14 — retrying simple attach...");
    channel = gateServo.attach(PIN_GATE);
  }
  bool ok = gateServo.attached();
  Serial.printf("Actuator: SERVO on GPIO %d (channel=%d attached=%d) — shared boom for Entry+Exit\n", PIN_GATE, channel, ok ? 1 : 0);
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
  Serial.printf("API target: %s\n", runtimeApiBase().c_str());
#if defined(GATE_ID) && defined(DIRECTION)
  if (String(GATE_ID) == "GATE-IN-1") {
    Serial.println("ROLE: ENTRY — RC522 + servo GPIO 14. Opens boom for Entry scans and Exit grants.");
  } else if (String(GATE_ID) == "GATE-OUT-1") {
    Serial.println("ROLE: EXIT — RC522 only. No servo. Laravel tells Entry ESP32 to open the boom.");
  }
#endif
  Serial.println("Normal use: power only. Hold BOOT 2s at power-on or 3s while running to open Gate-Setup portal.");
}

void loopGateClient() {
  pollForceConfigButton();
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
    Serial.println("WiFi down — UID seen but not sent. Hold BOOT 3s for Gate-Setup portal.");
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
  Serial.printf("POST http://%s:%u/api/rfid/scan\n", runtimeApiHost.c_str(), runtimeApiPort);

  if (!http.begin(client, runtimeApiHost.c_str(), runtimeApiPort, "/api/rfid/scan")) {
    Serial.println("HTTP error: unable to initialize connection");
    return fail;
  }

  http.addHeader("Content-Type", "application/json");
  http.addHeader("Connection", "close");
  http.addHeader("X-RFID-TOKEN", runtimeApiToken.c_str());
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
  if (!http.begin(client, runtimeApiHost.c_str(), runtimeApiPort, "/api/rfid/heartbeat")) {
    Serial.println("Heartbeat: begin failed");
    return false;
  }

  http.addHeader("Content-Type", "application/json");
  http.addHeader("Connection", "close");
  http.addHeader("X-RFID-TOKEN", runtimeApiToken.c_str());
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
                  code, HTTPClient::errorToString(code).c_str(), runtimeApiHost.c_str(), runtimeApiPort);
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
    Serial.println("HINT: Exit board must use same Wi-Fi + API host as Entry (or same Gate-Setup values).");
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
