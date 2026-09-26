# ISCVMS Hardware / Integration APIs

Base URL: `{APP_URL}` or LAN `http://<pc-ip>:8000` (preferred for ESP32).

Do not put real tokens in this document — use placeholders.

---

## POST `/api/rfid/scan`

- **Auth:** header `X-RFID-TOKEN: <RFID_API_TOKEN>`
- **Purpose:** Process RFID tap; grant/deny; log access
- **Body (JSON):** `{ "uid": "<uid>", "gate_id": "GATE-IN-1", "direction": "Entry"|"Exit" }`
- **Errors:** 401/503 missing/wrong token; throttle 30/min; DB unavailable → Access Denied shaped JSON

## POST `/api/rfid/heartbeat`

- **Auth:** `X-RFID-TOKEN`
- **Purpose:** Keep gate online; deliver pending boom-open command
- **Body:** `{ "gate_id": "GATE-IN-1" }`
- **Response (concept):** `{ ok, gate_id, open, command, message? }`
- **Throttle:** 120/min

## POST `/api/ai-parking/occupancy`

- **Auth:** `X-AI-TOKEN: <AI_PARKING_API_TOKEN>`
- **Purpose:** Update slot occupancy + detections from Python AI
- **Body:** camera_id, area_id, vehicle_count, detections[], slots[], events[]
- **Errors:** 401/503 token; validation 422

## POST `/api/ai-parking/events`

- **Auth:** `X-AI-TOKEN`
- **Purpose:** Standalone AI events (also often embedded in occupancy)

## POST `/api/ai-parking/plate-lookup`

- **Auth:** `X-AI-TOKEN`
- **Body:** `{ "plate": "ABC1234" }`
- **Purpose:** Owner/role enrichment for OCR

## POST `/api/visitor/pre-register/google`

- **Auth:** webhook token middleware (`VISITOR_PRE_REGISTER_WEBHOOK_TOKEN`)
- **Purpose:** Google Apps Script creates Waiting visitor

## Python AI local endpoints (not Laravel)

- `GET http://127.0.0.1:8090/health` — cameras online JSON
- MJPEG streams under `/stream.mjpg`, `/CAM-*/ai/stream.mjpg`
- Plate/vehicle crop URLs used by Guard UI

## Web JSON status (session auth)

- `GET /admin/system-health/status` — Admin system health snapshot
- `GET /guard/parking/status`, `/guard/gate/status` — Guard monitors
- `GET /up` — Laravel up probe
- `GET /sync/status` — Atlas sync banner (auth)
