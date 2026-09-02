"""Activate a parking lot for single-camera calibration and live AI.

Usage (from hardware/ai_parking):
  python select_lot.py acad1
  python select_lot.py duran
  python select_lot.py auditorium
  python select_lot.py --list
"""

from __future__ import annotations

import argparse
import json
import shutil
import sys
from pathlib import Path

BASE = Path(__file__).resolve().parent
PROFILES_PATH = BASE / "lot_profiles.json"
ACTIVE_ZONES = BASE / "zones.json"


def load_profiles() -> dict:
    with PROFILES_PATH.open(encoding="utf-8") as f:
        return json.load(f)


def build_zones_template(prefix: str, capacity: int, lot_name: str, snapshot: str | None = None) -> dict:
    zones = []
    for i in range(1, capacity + 1):
        slot_id = f"{prefix}-{i}"
        zones.append({"id": slot_id, "type": "slot", "label": slot_id, "points": []})
    zones.append({"id": "no-park-1", "type": "no_parking", "label": "No Parking", "points": []})
    zones.append({"id": "aisle-1", "type": "aisle", "label": "Drive Lane / Aisle", "points": []})
    data = {
        "version": 1,
        "calibrated": False,
        "image_width": 0,
        "image_height": 0,
        "notes": f"Calibrate with: python calibrate_zones.py --zones {lot_name}  (or --image snapshot.jpg)",
        "zones": zones,
    }
    if snapshot:
        data["snapshot"] = snapshot
        data["notes"] = f"Calibrate with: python calibrate_zones.py --zones {lot_name}"
    return data


def ensure_zones_file(path: Path, lot: dict) -> None:
    if path.is_file():
        return
    data = build_zones_template(lot["prefix"], lot["capacity"], path.name, lot.get("snapshot"))
    path.write_text(json.dumps(data, indent=2) + "\n", encoding="utf-8")
    print(f"Created template: {path.name}")


def main() -> int:
    parser = argparse.ArgumentParser(description="Select active parking lot for one-camera AI setup")
    parser.add_argument("lot", nargs="?", help="Lot key: acad1, duran, auditorium")
    parser.add_argument("--list", action="store_true", help="List lots and status")
    args = parser.parse_args()

    profiles = load_profiles()
    lots = profiles["lots"]
    order = profiles.get("order", list(lots.keys()))

    if args.list or not args.lot:
        print("Calibration order:", " -> ".join(order))
        print()
        for key in order:
            lot = lots[key]
            zpath = BASE / lot["zones_file"]
            ensure_zones_file(zpath, lot)
            data = json.loads(zpath.read_text(encoding="utf-8"))
            calibrated = data.get("calibrated") and any(
                z.get("type") == "slot" and len(z.get("points") or []) >= 3 for z in data.get("zones", [])
            )
            active = ACTIVE_ZONES.is_file() and zpath.samefile(ACTIVE_ZONES)
            try:
                active = zpath.resolve() == ACTIVE_ZONES.resolve() or (
                    ACTIVE_ZONES.is_file()
                    and json.loads(ACTIVE_ZONES.read_text(encoding="utf-8")).get("notes", "") == data.get("notes", "")
                )
            except OSError:
                active = False
            status = "calibrated" if calibrated else "needs calibration"
            marker = " (active)" if key == order[0] and not any(
                json.loads((BASE / lots[k]["zones_file"]).read_text(encoding="utf-8")).get("calibrated")
                for k in order
                if (BASE / lots[k]["zones_file"]).is_file()
            ) else ""
            print(f"  {key:12} area_id={lot['area_id']:2}  cam={int(lot.get('camera') or 1)}  {lot['name'][:36]:36}  [{status}]{marker}")
        if not args.lot:
            print("\nRun: python select_lot.py acad1")
        return 0

    key = args.lot.strip().lower()
    if key not in lots:
        print(f"Unknown lot '{key}'. Choose: {', '.join(order)}", file=sys.stderr)
        return 1

    lot = lots[key]
    src = BASE / lot["zones_file"]
    ensure_zones_file(src, lot)
    shutil.copy2(src, ACTIVE_ZONES)
    cam_n = int(lot.get("camera") or 1)
    print(f"Active lot: {lot['name']} (area_id={lot['area_id']})")
    print(f"Copied {src.name} -> zones.json")
    print()
    print("Update .env:")
    if cam_n == 1:
        print(f"  AI_PARKING_AREA_ID={lot['area_id']}")
    print(f"  AI_CAMERA_{cam_n}_AREA_ID={lot['area_id']}")
    print(f'  AI_CAMERA_{cam_n}_NAME="{lot["name"]}"')
    print(f'  AI_CAMERA_{cam_n}_LOCATION="{lot["env_location"]}"')
    print(f"  AI_CAMERA_{cam_n}_ZONES={lot['zones_file']}")
    print()
    print("Calibrate (camera at this lot):")
    snapshot = lot.get("snapshot")
    if snapshot:
        print(f"  python calibrate_zones.py --zones {lot['zones_file']}")
        print(f"  # uses {snapshot}; add --live to grab from CAM-2 instead")
    else:
        print(f"  python calibrate_zones.py --zones {lot['zones_file']} --image snapshot.jpg")
        print(f"  # or live: python calibrate_zones.py --zones {lot['zones_file']}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
