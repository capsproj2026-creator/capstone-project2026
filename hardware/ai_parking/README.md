# AI Parking (YOLOv9c)

Zone-level occupancy, slot polygons, violation rules, ByteTrack tracking, optional EasyOCR plates, Laravel ingest.

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
| `AI_PARKING_OCR_ENABLED` | `1` | EasyOCR on/off |
| `AI_PARKING_OCR_EVERY_SEC` | `8` | OCR interval per track |

## Event → violation mapping

| Event | Violation type |
|-------|----------------|
| `no_parking`, `aisle_blocked`, `double_park` | Wrong Parking |
| `overtime` | Overtime Parking |
| `unauthorized` | Unauthorized Parking |

Citations require a **registered** plate (same as guard flow). Unknown plates show on the AI monitor only.
