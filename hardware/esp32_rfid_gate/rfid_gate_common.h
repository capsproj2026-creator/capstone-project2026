/**
 * Shared RFID gate client logic — entry and exit sketches include this file.
 * Actuator control stays on ESP32; Laravel only returns grant/deny decisions.
 *
 * Shared boom: wire the servo to the Entry ESP32 only. Exit uses ACTUATOR_NONE;
 * Laravel queues an open to Entry when Exit RFID is granted.
 *
 * Network: WIFI_SSID / API_HOST from rfid_gate_config.h (WiFiManager portal off by default).
 */
#pragma once

#include <WiFi.h>
#include <WiFiClientSecure.h>
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
#define USE_WIFI_MANAGER 0
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
// GPIO 14 is driven with the ESP32 core LEDC driver (50 Hz servo pulses).
// ESP32Servo 3.x on Arduino-ESP32 3.3 marks the servo attached on LEDC channel 0
// while the pin stays silent, so the boom never moves.
#endif

#ifndef GATE_ENTRY_OPEN_MS
#define GATE_ENTRY_OPEN_MS 10000UL
#endif
#ifndef GATE_EXIT_OPEN_MS
#define GATE_EXIT_OPEN_MS 15000UL
#endif
#ifndef GATE_OPEN_MS
#define GATE_OPEN_MS GATE_ENTRY_OPEN_MS
#endif
#ifndef GATE_COOLDOWN_MS
// Short gap so a *different* card can be tapped quickly after the previous one.
#define GATE_COOLDOWN_MS 250UL
#endif
#ifndef SAME_UID_COOLDOWN_MS
// Same physical card left on the reader after a completed scan — ignore repeats.
// Measured from AFTER the HTTP round-trip (see lastScanMs refresh below), not before.
#define SAME_UID_COOLDOWN_MS 1800UL
#endif
#ifndef SCAN_BLOCK_MS
// Prevents duplicate boom opens during one grant cycle (not a general "wait to scan").
#define SCAN_BLOCK_MS 800UL
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

// Tight timeouts so a dead API fails fast; healthy LAN replies well under these.
const uint16_t HTTP_CONNECT_MS = 4000;
const uint16_t HTTP_READ_MS    = 15000;
const uint16_t HTTP_HB_CONNECT_MS = 5000;
const uint16_t HTTP_HB_READ_MS    = 15000;

MFRC522 mfrc522(SS_PIN, RST_PIN);
Preferences gatePrefs;

#if ACTUATOR_MODE == ACTUATOR_SERVO
static const uint8_t SERVO_LEDC_BITS = 14;
static const uint32_t SERVO_FRAME_US = 20000UL;
bool gateServoReady = false;

static int servoPulseUs(int angle) {
  if (angle < 0) {
    angle = 0;
  } else if (angle > 180) {
    angle = 180;
  }
  return map(angle, 0, 180, 500, 2400);
}

static void writeGateServoAngle(int angle) {
  if (!gateServoReady) {
    return;
  }
  uint32_t us = (uint32_t) servoPulseUs(angle);
  uint32_t dutyMax = (1UL << SERVO_LEDC_BITS) - 1UL;
  uint32_t duty = (us * dutyMax) / SERVO_FRAME_US;
  ledcWrite(PIN_GATE, duty);
}
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
  unsigned long holdMs;
};

unsigned long lastScanMs = 0;
String lastUidHex = "";
unsigned long lastHeartbeatMs = 0;
unsigned long lastWifiRetryMs = 0;
unsigned long wifiRetryDelayMs = 1000UL;
unsigned long heartbeatIntervalMs = HEARTBEAT_MS;
unsigned long gateCycleEndsMs = 0;
unsigned long gateCloseAtMs = 0;
String lastHandledOpenId = "";
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
void grantAccess(unsigned long holdOverride = 0);
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

bool apiUsesTls() {
  return runtimeApiPort == 443;
}

unsigned long steadyHeartbeatMs() {
  // A full HTTPS handshake is slower than a LAN post. Stay inside the 12s online window.
  if (apiUsesTls() && HEARTBEAT_MS < 5000UL) {
    return 5000UL;
  }
  return (unsigned long) HEARTBEAT_MS;
}

String runtimeApiBase() {
  const char *scheme = apiUsesTls() ? "https://" : "http://";
  return String(scheme) + runtimeApiHost + ":" + String(runtimeApiPort);
}

int apiPost(const char *path, const String &payload, String &response, uint16_t connectMs, uint16_t readMs) {
  HTTPClient http;
  http.setReuse(false);
  bool started = false;

  if (apiUsesTls()) {
    WiFiClientSecure client;
    // Identity check needs a correct clock. Campus Wi-Fi often blocks NTP, so
    // encrypt the token without failing the gate when the clock is still 1970.
    client.setInsecure();
    client.setHandshakeTimeout(12);
    started = http.begin(client, runtimeApiHost.c_str(), runtimeApiPort, path, true);
    if (!started) {
      Serial.printf("HTTPS begin failed %s\n", path);
      return -1;
    }
    http.addHeader("Content-Type", "application/json");
    http.addHeader("Connection", "close");
    http.addHeader("X-RFID-TOKEN", runtimeApiToken.c_str());
    http.setConnectTimeout(connectMs);
    http.setTimeout(readMs);
    int code = http.POST(payload);
    if (code > 0) {
      response = http.getString();
    }
    http.end();
    return code;
  }

  WiFiClient client;
  started = http.begin(client, runtimeApiHost.c_str(), runtimeApiPort, path, false);
  if (!started) {
    Serial.printf("HTTP begin failed %s\n", path);
    return -1;
  }
  http.addHeader("Content-Type", "application/json");
  http.addHeader("Connection", "close");
  http.addHeader("X-RFID-TOKEN", runtimeApiToken.c_str());
  http.setConnectTimeout(connectMs);
  http.setTimeout(readMs);
  int code = http.POST(payload);
  if (code > 0) {
    response = http.getString();
  }
  http.end();
  return code;
}

bool wifiManagerEnabled() {
#if GATE_HAS_WIFI_MANAGER && USE_WIFI_MANAGER
  return true;
#else
  return false;
#endif
}

void loadNetworkPrefs() {
  // Always start from rfid_gate_config.h.
  runtimeApiHost = API_HOST;
  runtimeApiPort = (uint16_t) API_PORT;
  runtimeApiToken = RFID_API_TOKEN;

  // When WiFiManager is off, ignore stale NVS from old portal saves
  // (that is why boards kept calling 192.168.1.74 after reflash).
  if (!wifiManagerEnabled()) {
    // Clear old portal values so a later enable does not resurrect them.
    if (gatePrefs.begin("gate", false)) {
      gatePrefs.clear();
      gatePrefs.end();
    }
    heartbeatIntervalMs = steadyHeartbeatMs();
    Serial.printf("API from config: %s\n", runtimeApiBase().c_str());
    return;
  }

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
  heartbeatIntervalMs = steadyHeartbeatMs();
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
    if (apiUsesTls()) {
      Serial.println("TCP to www.iscvms.com is OK. HTTPS heartbeat will retry.");
    } else {
      Serial.println("TCP to PC is OK. Heartbeat HTTP will retry (keep Laravel/start.ps1 open).");
    }
  } else if (apiUsesTls()) {
    Serial.println("Cannot reach www.iscvms.com:443. ESP32 needs internet, not only the campus LAN.");
    Serial.println("Wrong network? Edit WIFI_SSID / API_HOST in rfid_gate_config.h and re-flash.");
  } else {
    Serial.println("PC unreachable from ESP32. On the PC run allow-laravel-firewall.bat (Admin).");
    Serial.printf("On phone (same Wi-Fi) open: http://%s:%u\n", runtimeApiHost.c_str(), runtimeApiPort);
    Serial.println("If phone fails too: disable router AP isolation / guest Wi-Fi.");
    Serial.println("Wrong network? Edit WIFI_SSID / API_HOST in rfid_gate_config.h and re-flash.");
  }
}

void printWifiFailureHelp() {
  wl_status_t st = WiFi.status();
  Serial.print("WiFi status code: ");
  Serial.println((int)st);
  switch (st) {
    case WL_NO_SSID_AVAIL:
      Serial.println("WiFi: SSID not found — check WIFI_SSID in rfid_gate_config.h (2.4 GHz only).");
      break;
    case WL_CONNECT_FAILED:
      Serial.println("WiFi: password rejected — check WIFI_PASSWORD in rfid_gate_config.h.");
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
    Serial.println("   4) Edit WIFI_SSID / WIFI_PASSWORD in rfid_gate_config.h and re-flash.");
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

  Serial.println("Using WIFI_SSID / WIFI_PASSWORD from rfid_gate_config.h (no portal).");

  // Compile-time Wi-Fi from rfid_gate_config.h.
  if (String(WIFI_SSID).length() == 0) {
    Serial.println("ERROR: WIFI_SSID empty. Set it in rfid_gate_config.h and re-flash.");
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
  pinMode(PIN_GATE, OUTPUT);
  gateServoReady = ledcAttach(PIN_GATE, 50, SERVO_LEDC_BITS);
  Serial.printf("Actuator: SERVO on GPIO %d (ledc=%d) — shared boom for Entry+Exit\n", PIN_GATE, gateServoReady ? 1 : 0);
  if (!gateServoReady) {
    Serial.println("WARNING: Servo PWM failed on GPIO 14. Check signal wire + external 5V + common GND.");
  } else {
    writeGateServoAngle(SERVO_CLOSE_ANGLE);
    Serial.printf("Servo -> close angle %d (%d us)\n", SERVO_CLOSE_ANGLE, servoPulseUs(SERVO_CLOSE_ANGLE));
  }
  delay(300);

#if SERVO_TEST_ON_BOOT
  if (gateServoReady) {
    Serial.println("Servo boot test: open...");
    writeGateServoAngle(SERVO_OPEN_ANGLE);
    delay(1200);
    Serial.println("Servo boot test: close...");
    writeGateServoAngle(SERVO_CLOSE_ANGLE);
    delay(800);
    Serial.println("Servo boot test done. If arm did not move, power the servo from 5V (not 3.3V) and share GND with the ESP32. Signal stays on GPIO 14.");
  }
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
  Serial.println("Normal use: power only. Wi-Fi/API come from rfid_gate_config.h.");
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
      heartbeatIntervalMs = steadyHeartbeatMs();
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

  String uid = uidToHex(mfrc522.uid);

  // Same card held on reader → short ignore. Different card → ready almost immediately.
  const unsigned long gap = millis() - lastScanMs;
  const bool sameCard = (lastUidHex.length() > 0 && uid.equalsIgnoreCase(lastUidHex));
  const unsigned long needMs = sameCard ? SAME_UID_COOLDOWN_MS : GATE_COOLDOWN_MS;
  if (gap < needMs) {
    mfrc522.PICC_HaltA();
    mfrc522.PCD_StopCrypto1();
    return;
  }
  lastScanMs = millis();
  lastUidHex = uid;

  Serial.print("UID: ");
  Serial.println(uid);

  // Brief green blink = card was read locally (even before Laravel reply).
  digitalWrite(PIN_GREEN, HIGH);
  delay(20);
  digitalWrite(PIN_GREEN, LOW);

  if (!wifiOk) {
    Serial.println("WiFi down — UID seen but not sent. Check WIFI_SSID in rfid_gate_config.h.");
    mfrc522.PICC_HaltA();
    mfrc522.PCD_StopCrypto1();
    return;
  }

  ScanResult result = postScan(uid);
  // Cooldown must start AFTER the HTTP round-trip. If we only stamped lastScanMs
  // before POST, a 1–2s Laravel reply already exhausts SAME_UID_COOLDOWN_MS and
  // the still-held card immediately fires a second scan (double profile on the
  // live gate monitor: Access Granted, then Already Inside).
  lastScanMs = millis();
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

  Serial.printf("POST %s/api/rfid/scan\n", runtimeApiBase().c_str());

  StaticJsonDocument<256> body;
  body["uid"] = uid;
  body["gate_id"] = GATE_ID;
  body["direction"] = DIRECTION;

  String payload;
  serializeJson(body, payload);

  String response;
  uint16_t connectMs = apiUsesTls() ? 12000 : HTTP_CONNECT_MS;
  int code = apiPost("/api/rfid/scan", payload, response, connectMs, HTTP_READ_MS);

  if (code <= 0) {
    Serial.printf("HTTP failed (%d): %s\n", code, HTTPClient::errorToString(code).c_str());
    return fail;
  }

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
  result.holdMs = doc["hold_ms"] | 0;
  return result;
}

bool pollHeartbeat() {
  StaticJsonDocument<128> body;
  body["gate_id"] = GATE_ID;
  String payload;
  serializeJson(body, payload);

  String response;
  uint16_t connectMs = apiUsesTls() ? 12000 : HTTP_HB_CONNECT_MS;
  int code = apiPost("/api/rfid/heartbeat", payload, response, connectMs, HTTP_HB_READ_MS);
  if (code <= 0) {
    apiFailStreak++;
    Serial.printf("Heartbeat: HTTP error %d (%s) — server %s:%d\n",
                  code, HTTPClient::errorToString(code).c_str(), runtimeApiHost.c_str(), runtimeApiPort);
    if (apiFailStreak >= 2) {
      logLanDiagnostic();
    }
    return false;
  }

  apiFailStreak = 0;

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

  unsigned long hold = (unsigned long) GATE_EXIT_OPEN_MS;
  if (!err) {
    unsigned long fromServer = doc["hold_ms"] | 0;
    if (fromServer >= 1000UL && fromServer <= 60000UL) {
      hold = fromServer;
    }
  }

  const char *openId = "";
  if (!err) {
    openId = doc["open_id"] | "";
  }
  // The site repeats the same exit command on later heartbeats. After the
  // 15s hold the boom is down, and a repeat would lift it a second time.
  if (openId[0] != '\0' && lastHandledOpenId == openId) {
    Serial.println("Repeat exit open ignored");
    return true;
  }

  Serial.printf("Heartbeat: OPEN command — %s\n", response.c_str());
  if (gateIsOpen && gateCloseAtMs > millis()) {
    Serial.println("Servo already UP — keeping the current countdown");
    if (openId[0] != '\0') {
      lastHandledOpenId = openId;
    }
    return true;
  }
  if (openId[0] != '\0') {
    lastHandledOpenId = openId;
  }
  digitalWrite(PIN_RED, LOW);
  digitalWrite(PIN_GREEN, HIGH);
  openGateActuator();
  gateIsOpen = true;
  gateCloseAtMs = millis() + hold;
  gateCycleEndsMs = millis() + SCAN_BLOCK_MS;
  Serial.printf("Servo UP — remote open; auto DOWN in %lu ms\n", hold);
  return true;
}

void handleResult(const ScanResult &result, bool forceOpen) {
  if (result.granted) {
    // RFID debounce only — emergency/shared-boom heartbeat must always move the servo.
    bool emergency = result.code == "emergency_open";
    if (!forceOpen && !emergency && (gateIsOpen || millis() < gateCycleEndsMs)) {
      Serial.println("Gate cycle active — ignoring duplicate RFID open");
      digitalWrite(PIN_GREEN, HIGH);
      delay(80);
      digitalWrite(PIN_GREEN, LOW);
      return;
    }
    grantAccess(result.holdMs);
    if (result.openSharedBoom) {
      Serial.println("Laravel queued OPEN on Entry ESP32 (GATE-IN-1). Servo moves there in ~1-2s.");
    }
    return;
  }
  denyAccess(result);
}

void grantAccess(unsigned long holdOverride) {
  digitalWrite(PIN_RED, LOW);
  digitalWrite(PIN_GREEN, HIGH);
  openGateActuator();
  gateIsOpen = true;
  unsigned long hold = (unsigned long) GATE_ENTRY_OPEN_MS;
  if (holdOverride >= 1000UL && holdOverride <= 60000UL) {
    hold = holdOverride;
  }
  gateCloseAtMs = millis() + hold;
  gateCycleEndsMs = millis() + SCAN_BLOCK_MS;
#if ACTUATOR_MODE == ACTUATOR_NONE
  Serial.printf("Access Granted (Exit) — Entry boom opens via Laravel for %lu ms\n",
                (unsigned long) GATE_EXIT_OPEN_MS);
#else
  Serial.printf("Access Granted (Entry) — servo UP now, auto DOWN in %lu ms\n", hold);
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
    delay(80);
    digitalWrite(PIN_BUZZER, LOW);
    delay(50);
  }
  delay(150);
  digitalWrite(PIN_RED, LOW);
  Serial.printf("Denied (%s)\n", result.code.c_str());
  if (result.code == "already_inside") {
    Serial.println("HINT: This card is already INSIDE. Tap it on the EXIT ESP32 (Exit.ino / GATE-OUT-1), not Entry.");
  } else if (result.code == "already_outside") {
    Serial.println("HINT: Tap this card on ENTRY first, then on EXIT. Exit is denied until there is an Entry log.");
  } else if (result.code == "card_not_registered") {
    Serial.println("HINT: Admin -> RFID Assignment -> set UID to this card.");
  } else if (result.code == "network_error") {
    Serial.println("HINT: Exit board must use the same WIFI_SSID + API_HOST as Entry in rfid_gate_config.h.");
  }
}

void openGateActuator() {
#if ACTUATOR_MODE == ACTUATOR_SERVO
  if (!gateServoReady) {
    Serial.println("Servo OPEN skipped — PWM not started (see boot log ledc=0)");
    return;
  }
  Serial.printf("Servo OPEN -> %d deg (%d us)\n", SERVO_OPEN_ANGLE, servoPulseUs(SERVO_OPEN_ANGLE));
  writeGateServoAngle(SERVO_OPEN_ANGLE);
#elif ACTUATOR_MODE == ACTUATOR_RELAY
  digitalWrite(PIN_GATE, HIGH);
#else
  Serial.println("No local servo (Exit board). Laravel will open the Entry ESP32 servo.");
#endif
}

void closeGateActuator() {
#if ACTUATOR_MODE == ACTUATOR_SERVO
  if (gateServoReady) {
    Serial.printf("Servo CLOSE -> %d deg (%d us)\n", SERVO_CLOSE_ANGLE, servoPulseUs(SERVO_CLOSE_ANGLE));
    writeGateServoAngle(SERVO_CLOSE_ANGLE);
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
    Serial.println("Servo DOWN — gate closed after open window");
  }
}
