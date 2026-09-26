# ISCVMS Modules

## User Management
- **Purpose:** Admin CRUD for campus users, roles, documents  
- **Roles:** Admin  
- **Main:** `UserManagementController`, `User` model, `/admin/users`  
- **Failures:** Mongo down → database unavailable page  

## Vehicle Registration
- **Purpose:** Student/Staff register vehicles; Admin approve/decline  
- **Roles:** Student/Staff, Admin  
- **Main:** `RegisterController`, `RegistrationController`, `UserVehicle` / `Vehicle`  
- **Config:** remedial gate hours in `.env`  

## RFID Gate Access
- **Purpose:** Tap-to-open boom via ESP32  
- **Roles:** hardware + Guard monitor  
- **Main:** `RfidGateController`, `GateLog`, firmware under `hardware/esp32_rfid_gate*`  
- **APIs:** `/api/rfid/scan`, `/api/rfid/heartbeat`  
- **Config:** `RFID_API_TOKEN`, `RFID_SHARED_BOOM_GATE_ID`  
- **Failures:** wrong token, LAN :8000 down, WiFi, servo  

## Visitor Management
- **Purpose:** Guard register visitors; optional Google Form pre-register  
- **Main:** Guard visitor controllers, webhook `VisitorGoogleFormWebhookController`  
- **Config:** `VISITOR_PRE_REGISTER_*`, ngrok for local webhooks  

## AI Parking Monitoring
- **Purpose:** Live YOLO + plates on Guard monitor  
- **Main:** `hardware/ai_parking`, `LiveCameraController`, `AiParkingOccupancyService`  
- **Config:** `AI_CAMERA_*`, `AI_PARKING_*`, zones JSON  
- **Failures:** AI :8090 down (web still works), RTSP, OCR CPU load  

## Parking Calibration
- **Purpose:** Slot polygons per camera  
- **Main:** `calibrate_zones.py`, `scripts/calibrate-cam1.ps1`, `zones_*.json`  
- **Persist:** files on disk; AI reloads on mtime  

## Plate Recognition
- **Purpose:** EasyOCR + plate YOLO; manual correct on Guard UI  
- **Main:** `plate_ocr.py`, `plate_text.py`, correct-plate endpoints  

## Violation Tracking
- **Purpose:** Wrong parking / overtime / unauthorized  
- **Main:** `AiParkingViolationService`, Admin/Guard violation controllers  

## Notifications / Reports / Access Logs
- **Purpose:** User alerts, Admin reports/PDF, gate history  
- **Main:** `Notification`, `ReportController`, access-log views  

## System Health (turnover)
- **Purpose:** Admin live probes  
- **Main:** `SystemHealthController`, `SystemHealthService`, `/admin/system-health`  
