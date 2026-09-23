from pathlib import Path
from geometry import assign_zones_for_box, box_ground_point, load_zones, usable_zones_for_frame

z = load_zones(Path("zones_acad1.json"))
zones = usable_zones_for_frame(z, (720, 1280))
slots = [x for x in zones if x.get("type") == "slot"]
print("calibrated slots with points:", len(slots))
for s in slots:
    xs = [p[0] for p in s["points"]]
    ys = [p[1] for p in s["points"]]
    print(f"  {s['id']}: x={min(xs)}-{max(xs)} y={min(ys)}-{max(ys)}")

boxes = [
    ("moto1", (343, 517, 507, 632)),
    ("car2", (657, 575, 860, 718)),
    ("moto3", (511, 570, 864, 719)),
]
for name, b in boxes:
    g = box_ground_point(b)
    m = assign_zones_for_box(b, slots, (720, 1280), 0.08)
    print(name, "ground", g, "matched", [x["id"] for x in m] or "NONE")
