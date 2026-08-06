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
| `AI_PARKING_CONF` | `0.28` | YOLO confidence (lower = more detections) |
| `AI_PARKING_INFER_MAX_WIDTH` | `960` | Default max width fed to YOLO (override per cam with `AI_CAMERA_N_INFER_MAX_WIDTH`) |
| `AI_PARKING_IMG_SIZE` | `640` | YOLO imgsz (lower = smoother realtime on CPU) |
| `AI_PARKING_MIN_BOX_AREA` | `0.0005` | Drop tiny boxes only |
| `AI_PARKING_USE_TRACKER` | `0` | `0` = per-cam IoU IDs (multi-cam safe) |
| `AI_PARKING_BOX_HOLD_SEC` | `0.45` | Hold boxes briefly to reduce flicker |
| `AI_PARKING_OCR_EVERY_SEC` | `3` | OCR interval per track (async, non-blocking) |
| `AI_PARKING_OCR_MIN_CONF` | `0.30` | Min OCR conf to accept a plate |
| `AI_PARKING_OCR_UPSCALE_FACTOR` | `3` | Upscale small plate crops before OCR |
| `AI_PARKING_OCR_UPSCALE_MIN_WIDTH` | `400` | Minimum crop width after upscale |
| `AI_PARKING_PLATE_VOTE_NEEDED` | `1` | Matching OCR reads before locking plate |
| `AI_PARKING_TRACK_HOLD_SEC` | `3.0` | Keep lost tracks briefly (less re-OCR flicker) |
| `AI_PARKING_INFER_EVERY_SEC` | `0.30` | YOLO cadence target |

YOLO does **not** run on every frame — about every `AI_PARKING_INFER_EVERY_SEC` seconds. Plate OCR runs about every `AI_PARKING_OCR_EVERY_SEC` seconds per tracked vehicle, and only until a plate is locked with enough votes.

## Event → violation mapping

| Event | Violation type |
|-------|----------------|
| `no_parking`, `aisle_blocked`, `double_park` | Wrong Parking |
| `overtime` | Overtime Parking |
| `unauthorized` | Unauthorized Parking |

Citations require a **registered** plate (same as guard flow). UI labels:

| Case | Display |
|------|---------|
| Matched plate | Owner full name, plate, vehicle type, Registered |
| Readable but unknown | Unknown Vehicle · Plate Not Registered |
| Low OCR confidence | Plate Unreadable |

Laravel matches OCR plates to `users.plate_number` (hyphens/spaces ignored) and enriches detections for the AI Parking Monitor, Live Cameras, and Parking pages. Enable OCR:

```env
AI_PARKING_OCR_ENABLED=1
```

Then restart the Python AI service (`scripts/start-ai-parking.ps1`). First run downloads EasyOCR models.

## Owner overlay + plate lookup

After OCR locks a plate on a track, Python calls `POST /api/ai-parking/plate-lookup` once and caches owner fields on `TrackMemory`. MJPEG overlays show:

```
#15 Car 91%
ABC1234
John Cruz
```

(or `Unknown Vehicle` / `Plate Unreadable`). Occupancy posts may include normalized `xyxy` and owner fields; Laravel still re-enriches for Monitor consistency.

## Detection tunables (fewer FPs without killing recall)

| Env | Default | Notes |
|-----|---------|--------|
| `AI_PARKING_CONF` | `0.28` | Raise toward `0.35` if ghost boxes |
| `AI_PARKING_MIN_BOX_AREA` | `0.0005` | Tiny distant cars |
| `AI_PARKING_BOX_HOLD_SEC` | `0.45` | Smooth flicker |
| `AI_PARKING_LOOKUP_TIMEOUT_SEC` | `2.5` | Owner API; never blocks preview |
| Occupancy POST timeout | `4s` | Async thread; preview continues |

Multi-cam: shared YOLO lock; per-cam preview + OCR queue. One RTSP failure does not stall another camera’s MJPEG.

**Tapo C310 plates:** set `AI_CAMERA_2_PREVIEW_MAX_WIDTH=2304` and `AI_CAMERA_2_INFER_MAX_WIDTH=2304` (native ~3MP). Keep `/stream1`. Expect heavier CPU; Dahua can stay on lower preview/infer widths.

## Violation evidence

Rule events include a size-capped JPEG (`evidence_jpeg_base64`). Laravel stores it on `ViolationLog.evidence_photo` (private disk) with `camera_id`, `area_id`, `area_name`, `vehicle_details`, `track_id`, `confidence`. Admin/guard violation pages show camera/area/vehicle and evidence thumbnails.

## Manual checklist

- [ ] Multi-cam detect (boxes + track ids)
- [ ] Overlay shows plate + owner after OCR lock
- [ ] AI Parking Monitor: class, plate, owner, track_id, department, violation status
- [ ] Live Cameras under-tile line updates without stalling video
- [ ] Registered-plate citation creates Active log with evidence image

