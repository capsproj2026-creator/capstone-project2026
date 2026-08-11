# YOLOv9 pretrained weights

Large `.pt` files are **not stored in Git**. Download them once:

```powershell
# From repo root (recommended)
.\scripts\setup-yolov9.ps1

# Or pick a variant
.\scripts\setup-yolov9.ps1 -Model yolov9m
```

## Models (Ultralytics COCO pretrained)

| Env value | Name | Best for |
|-----------|------|----------|
| `yolov9t` | Tiny | Weakest PC, close range |
| `yolov9s` | Small | Fast CPU, small lots |
| `yolov9m` | Medium | **Long-range / distant cars** |
| `yolov9c` | C | **Default** — balanced |
| `yolov9e` | Extra | GPU server, max accuracy |

Set in `.env`:

```env
AI_PARKING_YOLO_MODEL=yolov9c
```

Then restart the AI parking service.

## Verify

```powershell
cd hardware\ai_parking
python download_model.py --list
python -c "from yolo_models import ensure_model; ensure_model()"
```

Detects COCO classes used by parking: **car, motorcycle, bus, truck** (and person if `AI_PARKING_DETECT_PERSONS=1`).
