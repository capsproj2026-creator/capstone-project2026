# Single-camera parking calibration (ACAD 1 → Duran → Auditorium)

One camera monitors **one lot at a time**. Calibrate in this order:

1. **ACAD 1** (area_id **4**, slots `AC-1` … `AC-10`)
2. **Duran Hall** (area_id **3**, slots `DU-1` … `DU-10`)
3. **Auditorium** (area_id **8**, slots `AU-1` … `AU-12`)

## Phase 1 — ACAD 1 (start here)

### 1. Activate ACAD 1 templates

```powershell
cd hardware\ai_parking
python select_lot.py acad1
```

This copies `zones_acad1.json` → `zones.json` and prints `.env` values.

### 2. Update `.env` (project root)

```env
AI_PARKING_AREA_ID=4
AI_CAMERA_1_AREA_ID=4
AI_CAMERA_1_NAME="ACAD 1 Building (Front)"
AI_CAMERA_1_LOCATION="ACAD 1 Building (Front)"
AI_CAMERA_1_ZONES=zones_acad1.json
AI_CAMERA_1_ENABLED=true
AI_CAMERA_2_ENABLED=false
AI_CAMERA_3_ENABLED=false
```

Set `AI_CAMERA_1_IP`, `AI_CAMERA_1_USER`, `AI_CAMERA_1_PASS` for your camera at ACAD 1.

### 3. Mount camera at ACAD 1 front

Angle should show all 10 slots plus any aisle / no-parking areas you will draw.

### 4. Calibrate polygons

Live camera:

```powershell
cd hardware\ai_parking
python calibrate_zones.py --zones zones_acad1.json
```

Or from a snapshot:

```powershell
python calibrate_zones.py --zones zones_acad1.json --image snapshot_acad1.jpg
```

| Key | Action |
|-----|--------|
| Click | Add polygon point |
| C | Close current zone |
| N / P | Next / previous zone |
| S | **Save** (sets `calibrated: true`) |
| Q | Quit |

Draw every slot (`AC-1` … `AC-10`), then `no-park-1` and `aisle-1`.

Saving updates both `zones_acad1.json` and `zones.json`.

### 5. Run and verify

```powershell
# Terminal 1
php artisan serve --host=0.0.0.0 --port=8000

# Terminal 2 (repo root)
.\scripts\start-ai-parking.ps1
```

```powershell
php artisan ai-parking:check --probe-stream
```

Open `/guard/ai-parking` — slot overlays should match ACAD 1 spaces.

---

## Phase 2 — Duran Hall

1. **Move camera** to Duran Hall (Front).
2. Activate lot:
   ```powershell
   cd hardware\ai_parking
   python select_lot.py duran
   ```
3. Update `.env`: `AI_PARKING_AREA_ID=3`, `AI_CAMERA_1_AREA_ID=3`, `AI_CAMERA_1_ZONES=zones_duran.json`, update name/location.
4. Calibrate:
   ```powershell
   python calibrate_zones.py --zones zones_duran.json --image snapshot_duran.jpg
   ```
5. Restart AI service and verify slots `DU-1` … `DU-10`.

---

## Phase 3 — Auditorium

1. **Move camera** to College Auditorium (left/right wing view).
2. Activate lot:
   ```powershell
   python select_lot.py auditorium
   ```
3. Update `.env`: `AI_PARKING_AREA_ID=8`, `AI_CAMERA_1_AREA_ID=8`, `AI_CAMERA_1_ZONES=zones_auditorium.json`.
4. Calibrate:
   ```powershell
   python calibrate_zones.py --zones zones_auditorium.json --image snapshot_auditorium.jpg
   ```
5. Restart AI service and verify slots `AU-1` … `AU-12`.

---

## Switching back to a finished lot

When the camera returns to ACAD 1:

```powershell
cd hardware\ai_parking
python select_lot.py acad1
```

Set `.env` area_id back to **4**, restart `start-ai-parking.ps1`.  
Already-calibrated `zones_acad1.json` is reused — no need to redraw unless the camera moved.

## Status

```powershell
python select_lot.py --list
```

## Files

| File | Lot |
|------|-----|
| `zones_acad1.json` | ACAD 1 (area 4) |
| `zones_duran.json` | Duran Hall (area 3) |
| `zones_auditorium.json` | Auditorium (area 8) |
| `zones.json` | Active lot (copy of current) |
| `lot_profiles.json` | Area IDs and metadata |
