# AI parking model weights

Large `.pt` files are **not stored in Git**. Download them once.

## 1. Vehicle detector (YOLOv9 COCO)

Detects **car, motorcycle, bus, truck** in the full camera frame.

```powershell
# From repo root
.\scripts\setup-yolov9.ps1

# Long-range / distant vehicles
.\scripts\setup-yolov9.ps1 -Model yolov9m
```

| Env value | Name | Best for |
|-----------|------|----------|
| `yolov9t` | Tiny | Weakest PC, close range |
| `yolov9s` | Small | Fast CPU, small lots |
| `yolov9m` | Medium | **Long-range / distant cars** |
| `yolov9c` | C | **Default** — balanced |
| `yolov9e` | Extra | GPU server, max accuracy |

```env
AI_PARKING_YOLO_MODEL=yolov9c
```

## 2. Plate detector (YOLOv11 fine-tuned) — optional but recommended

Finds the **license plate rectangle** inside each vehicle crop before EasyOCR runs.  
Without this file, the system falls back to OpenCV/HSV heuristics (less accurate).

```powershell
# From repo root (downloads ~18 MB to models/plate.pt)
.\scripts\setup-plate-model.ps1

# More accurate on distant plates (slower)
.\scripts\setup-plate-model.ps1 -Variant v1m
```

| Variant | Speed | Accuracy |
|---------|-------|----------|
| `v1n` | Fastest | Good |
| `v1s` | **Default** | Better |
| `v1m` | Slower | Best for small/distant plates |

```env
AI_PARKING_PLATE_MODEL_VARIANT=v1s
AI_PARKING_PLATE_MODEL=models/plate.pt
AI_PARKING_PLATE_YOLO_CONF=0.22
```

On AI parking startup you should see:

```text
Plate detector YOLO loaded: ...\models\plate.pt
```

### Fine-tune on your own cameras (advanced)

For best PH plate accuracy, collect frames from your parking cameras and train:

1. Label plate bounding boxes (Roboflow, CVAT, or LabelImg).
2. Export in YOLO format.
3. Train with Ultralytics:
   ```powershell
   yolo detect train data=your-plates.yaml model=yolov9s.pt epochs=50 imgsz=640
   ```
4. Copy `runs/detect/train/weights/best.pt` to `models/plate.pt`.

## Verify

```powershell
cd hardware\ai_parking
python download_model.py --list
python download_plate_model.py --list
python probe_plate_ocr.py
```

Check `debug_plates/` for saved crops — the plate should be tightly framed.
