# AI Parking (YOLOv9c)

Zone-level occupancy, slot polygons, violation rules, ByteTrack tracking, optional EasyOCR plates, Laravel ingest.

## Multi-camera (CAM-AI-1 / 2 / 3)

Configure each camera in `.env` (`AI_CAMERA_1_*` … `AI_CAMERA_3_*`).

| Camera | Typical RTSP path | Parking area |
|--------|-------------------|--------------|
| CAM-AI-1 (wired Dahua) | `/cam/realmonitor?channel=1&subtype=0` | 19 AI Test Lot |
| CAM-AI-2 (Tapo C310) | `/stream1` | 20 AI Lot B |
| CAM-AI-3 (Tapo C310) | `/stream1` | 21 AI Lot C |

Python starts one worker thread pair per camera (preview + YOLO). Shared model lock; RTSP reconnect is isolated. MJPEG paths:

- `http://127.0.0.1:8090/stream.mjpg` (CAM-AI-1)
- `http://127.0.0.1:8090/CAM-AI-2/stream.mjpg`
- `http://127.0.0.1:8090/CAM-AI-3/stream.mjpg`

Seed lots:

```powershell
php artisan db:seed --class=AiTestLotSeeder
```

## Quick connect (Windows)

```powershell
# From repo root
.\scripts\setup-yolov9.ps1
copy .env.example .env
# Edit .env: AI_PARKING_API_TOKEN, AI_CAMERA_IP, AI_CAMERA_PASS

php artisan db:seed --class=AiTestLotSeeder
php artisan serve --host=0.0.0.0 --port=8000

# Second terminal
.\scripts\start-ai-parking.ps1
php artisan ai-parking:check --probe-stream
```

The guard/admin UI loads video through Laravel (`/guard/ai-parking/stream`) so browsers on other devices do not need direct access to port 8090.

## Setup

```powershell
cd hardware\ai_parking
pip install -r requirements.txt
```

Laravel:

```powershell
php artisan db:seed --class=AiTestLotSeeder
# optional: refresh violation type names including Overtime / Unauthorized
php artisan db:seed --class=CapstoneSeeder
php artisan serve --host=0.0.0.0 --port=8000
```

## Calibrate slot / zone polygons (required for per-slot + rules)

```powershell
cd hardware\ai_parking
python calibrate_zones.py
# or: python calibrate_zones.py --image snapshot.jpg
```

Controls: click points, `N`/`P` next/prev zone, `S` save, `Q` quit.  
Saving with any slot ≥3 points sets `"calibrated": true` in `zones.json`.

Until calibrated, the service falls back to vehicle-count → first-N slots.

## Run AI service

```powershell
cd hardware\ai_parking
$env:AI_LARAVEL_API_BASE="http://127.0.0.1:8000"
$env:AI_PARKING_API_TOKEN="capstone-ai-parking-dev-token-change-me"
$env:AI_PARKING_AREA_ID="19"
$env:AI_PARKING_OVERTIME_MINUTES="30"
python -u ai_parking_service.py
```

Stream: `http://127.0.0.1:8090/stream.mjpg`  
UI: `/admin/live-cameras`, `/guard/live-cameras`, `/guard/ai-parking`

## Env vars (Python)

| Var | Default | Meaning |
|-----|---------|---------|
| `AI_LARAVEL_API_BASE` | `http://127.0.0.1:8000` | Laravel base URL |
| `AI_PARKING_API_TOKEN` | (dev token) | `X-AI-TOKEN` |
| `AI_PARKING_AREA_ID` | `19` | AI Test Lot |
| `AI_PARKING_OVERTIME_MINUTES` | `30` | Slot dwell before overtime |
| `AI_PARKING_VIOLATION_DEBOUNCE_MINUTES` | `10` | Python + Laravel debounce |
| `AI_PARKING_OCR_ENABLED` | `0` in code / set `1` in `.env` | EasyOCR on/off (enable for plate → owner names) |
| `AI_PARKING_OCR_EVERY_SEC` | `8` | OCR interval per track |
| `AI_PARKING_INFER_EVERY_SEC` | `0.8` | YOLO inference cadence (not every camera frame) |

YOLO does **not** run on every frame — about every `AI_PARKING_INFER_EVERY_SEC` seconds. Plate OCR runs about every `AI_PARKING_OCR_EVERY_SEC` seconds per tracked vehicle.

## Event → violation mapping

| Event | Violation type |
|-------|----------------|
| `no_parking`, `aisle_blocked`, `double_park` | Wrong Parking |
| `overtime` | Overtime Parking |
| `unauthorized` | Unauthorized Parking |

Citations require a **registered** plate (same as guard flow). Unknown plates show on the AI monitor only.

Laravel matches OCR plates to `users.plate_number` (hyphens/spaces ignored) and attaches **owner name** on detections/events for the AI Parking Monitor. Enable OCR:

```env
AI_PARKING_OCR_ENABLED=1
```

Then restart the Python AI service (`scripts/start-ai-parking.ps1`). First run downloads EasyOCR models.
