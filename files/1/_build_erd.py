"""Build one-page MongoDB ERD into files/1/table.docx"""
from __future__ import annotations

import math
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont
from docx import Document
from docx.enum.section import WD_ORIENT
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.shared import Inches, Pt

out_dir = Path(__file__).resolve().parent
img_path = out_dir / "erd.png"
docx_path = out_dir / "table.docx"

W, H = 2400, 1550
img = Image.new("RGB", (W, H), "#FFFFFF")
draw = ImageDraw.Draw(img)


def load_font(size: int, bold: bool = False) -> ImageFont.ImageFont:
    candidates = [
        "C:/Windows/Fonts/segoeuib.ttf" if bold else "C:/Windows/Fonts/segoeui.ttf",
        "C:/Windows/Fonts/arialbd.ttf" if bold else "C:/Windows/Fonts/arial.ttf",
        "C:/Windows/Fonts/calibrib.ttf" if bold else "C:/Windows/Fonts/calibri.ttf",
    ]
    for path in candidates:
        try:
            return ImageFont.truetype(path, size)
        except OSError:
            continue
    return ImageFont.load_default()


font_title = load_font(26, True)
font_sub = load_font(13, False)
font_box = load_font(12, True)
font_pk = load_font(10, False)
font_legend = load_font(11, False)
font_legend_b = load_font(14, True)

entities = {
    "user_roles": {
        "xy": (40, 80),
        "wh": (160, 68),
        "fields": ["PK id", "role_name"],
        "fill": "#E8F1FF",
        "stroke": "#2F5FA8",
    },
    "departments": {
        "xy": (40, 190),
        "wh": (160, 78),
        "fields": ["PK id", "departmentcode", "departmentname"],
        "fill": "#E8F1FF",
        "stroke": "#2F5FA8",
    },
    "vehicles": {
        "xy": (40, 320),
        "wh": (160, 68),
        "fields": ["PK id", "vehicle_type"],
        "fill": "#E8F1FF",
        "stroke": "#2F5FA8",
    },
    "role_permissions": {
        "xy": (40, 430),
        "wh": (170, 78),
        "fields": ["PK id", "role_name", "permissions[]"],
        "fill": "#F3F4F6",
        "stroke": "#6B7280",
    },
    "users": {
        "xy": (300, 100),
        "wh": (220, 145),
        "fields": [
            "PK id",
            "FK user_role_id",
            "FK department_code",
            "FK vehicle_id",
            "rfid_tag / plate_number",
            "status",
        ],
        "fill": "#DCFCE7",
        "stroke": "#15803D",
    },
    "user_vehicles": {
        "xy": (300, 300),
        "wh": (220, 95),
        "fields": ["PK id", "FK user_id", "FK vehicle_id", "plate_number"],
        "fill": "#DCFCE7",
        "stroke": "#15803D",
    },
    "user_suspensions": {
        "xy": (300, 450),
        "wh": (220, 85),
        "fields": ["PK id", "FK user_id", "reason", "ends_at"],
        "fill": "#FEE2E2",
        "stroke": "#B91C1C",
    },
    "notifications": {
        "xy": (300, 580),
        "wh": (220, 95),
        "fields": ["PK id", "FK user_id", "FK sender_id", "type / message"],
        "fill": "#FEF3C7",
        "stroke": "#B45309",
    },
    "visitors": {
        "xy": (640, 70),
        "wh": (230, 125),
        "fields": [
            "PK id",
            "FK vehicle_id",
            "FK visitor_rfid_card_id",
            "FK registered_by",
            "plate_number / status",
        ],
        "fill": "#E0E7FF",
        "stroke": "#4338CA",
    },
    "visitor_rfid_cards": {
        "xy": (640, 240),
        "wh": (230, 105),
        "fields": ["PK id", "FK visitor_id", "FK created_by", "uid_hex", "status"],
        "fill": "#E0E7FF",
        "stroke": "#4338CA",
    },
    "registered_plates": {
        "xy": (640, 395),
        "wh": (230, 130),
        "fields": [
            "PK id / plate_number",
            "owner_type + FK owner_id",
            "FK user_vehicle_id",
            "FK visitor_id",
            "FK vehicle_id",
        ],
        "fill": "#FCE7F3",
        "stroke": "#BE185D",
    },
    "gate_logs": {
        "xy": (640, 575),
        "wh": (230, 105),
        "fields": ["PK id", "FK user_id", "FK visitor_id", "gate / result", "rfid_tag"],
        "fill": "#FEF3C7",
        "stroke": "#B45309",
    },
    "parking_areas": {
        "xy": (1000, 70),
        "wh": (210, 95),
        "fields": ["PK id", "name", "allowed_roles[]", "is_visible"],
        "fill": "#CCFBF1",
        "stroke": "#0F766E",
    },
    "parking_slots": {
        "xy": (1000, 210),
        "wh": (210, 125),
        "fields": [
            "PK id",
            "FK area_id",
            "slot_number / status",
            "FK parked_user_id",
            "FK parked_visitor_id",
        ],
        "fill": "#CCFBF1",
        "stroke": "#0F766E",
    },
    "violations_log": {
        "xy": (1000, 390),
        "wh": (210, 115),
        "fields": ["PK id", "FK user_id", "type / sanction", "evidence[]", "status"],
        "fill": "#FEE2E2",
        "stroke": "#B91C1C",
    },
    "violation_types": {
        "xy": (1310, 390),
        "wh": (180, 68),
        "fields": ["PK id", "name"],
        "fill": "#FEE2E2",
        "stroke": "#B91C1C",
    },
    "violation_sanctions": {
        "xy": (1310, 490),
        "wh": (180, 78),
        "fields": ["PK id", "name", "description"],
        "fill": "#FEE2E2",
        "stroke": "#B91C1C",
    },
    "parking_rules": {
        "xy": (1310, 70),
        "wh": (180, 78),
        "fields": ["PK id", "title", "is_active"],
        "fill": "#F3F4F6",
        "stroke": "#6B7280",
    },
    "general_informations": {
        "xy": (1310, 180),
        "wh": (180, 88),
        "fields": ["PK id", "title", "content", "is_active"],
        "fill": "#F3F4F6",
        "stroke": "#6B7280",
    },
    "stalled_vehicles": {
        "xy": (1310, 295),
        "wh": (180, 78),
        "fields": ["PK id", "plate", "is_active"],
        "fill": "#F3F4F6",
        "stroke": "#6B7280",
    },
    "system_settings": {
        "xy": (1310, 610),
        "wh": (180, 78),
        "fields": ["PK id", "key", "value"],
        "fill": "#F3F4F6",
        "stroke": "#6B7280",
    },
}


def box_rect(name: str) -> tuple[float, float, float, float]:
    x, y = entities[name]["xy"]
    w, h = entities[name]["wh"]
    return x, y, x + w, y + h


def center(name: str) -> tuple[float, float]:
    x1, y1, x2, y2 = box_rect(name)
    return (x1 + x2) / 2, (y1 + y2) / 2


def edge_point(name: str, toward: str) -> tuple[float, float]:
    cx, cy = center(name)
    tx, ty = center(toward)
    x1, y1, x2, y2 = box_rect(name)
    dx, dy = tx - cx, ty - cy
    if abs(dx) < 1e-6 and abs(dy) < 1e-6:
        return cx, cy
    candidates: list[tuple[float, float, float]] = []
    if dx != 0:
        for bx in (x1, x2):
            t = (bx - cx) / dx
            if t > 0:
                iy = cy + t * dy
                if y1 - 2 <= iy <= y2 + 2:
                    candidates.append((t, bx, max(y1, min(y2, iy))))
    if dy != 0:
        for by in (y1, y2):
            t = (by - cy) / dy
            if t > 0:
                ix = cx + t * dx
                if x1 - 2 <= ix <= x2 + 2:
                    candidates.append((t, max(x1, min(x2, ix)), by))
    if not candidates:
        return cx, cy
    candidates.sort(key=lambda c: c[0])
    return candidates[0][1], candidates[0][2]


def draw_rel(a: str, b: str, label: str, color: str) -> None:
    x1, y1 = edge_point(a, b)
    x2, y2 = edge_point(b, a)
    draw.line([(x1, y1), (x2, y2)], fill=color, width=2)
    ang = math.atan2(y1 - y2, x1 - x2)
    for da in (-0.55, 0.0, 0.55):
        lx = x2 + math.cos(ang + da) * 14
        ly = y2 + math.sin(ang + da) * 14
        draw.line([(x2, y2), (lx, ly)], fill=color, width=2)
    mx = (x1 + x2) / 2
    my = (y1 + y2) / 2 - 6
    if label:
        bbox = draw.textbbox((0, 0), label, font=font_legend)
        tw, th = bbox[2] - bbox[0], bbox[3] - bbox[1]
        pad = 2
        draw.rectangle(
            [mx - tw / 2 - pad, my - th / 2 - pad, mx + tw / 2 + pad, my + th / 2 + pad],
            fill="#FFFFFF",
        )
        draw.text((mx - tw / 2, my - th / 2), label, fill=color, font=font_legend)


draw.text(
    (40, 16),
    "Smart Campus VMS — MongoDB Entity Relationship Diagram (1 page)",
    fill="#111827",
    font=font_title,
)
draw.text(
    (40, 48),
    "Database: MongoDB Atlas · Collections as entities · FK = referenced document id/code · Soft refs by name for types/sanctions/permissions",
    fill="#4B5563",
    font=font_sub,
)

rels = [
    ("user_roles", "users", "1:N", "#2F5FA8"),
    ("departments", "users", "1:N", "#2F5FA8"),
    ("vehicles", "users", "1:N", "#2F5FA8"),
    ("users", "user_vehicles", "1:N", "#15803D"),
    ("vehicles", "user_vehicles", "1:N", "#15803D"),
    ("users", "user_suspensions", "1:N", "#B91C1C"),
    ("users", "notifications", "1:N", "#B45309"),
    ("users", "visitors", "1:N", "#4338CA"),
    ("vehicles", "visitors", "1:N", "#4338CA"),
    ("visitor_rfid_cards", "visitors", "1:1", "#4338CA"),
    ("users", "visitor_rfid_cards", "1:N", "#4338CA"),
    ("users", "registered_plates", "1:N", "#BE185D"),
    ("visitors", "registered_plates", "1:N", "#BE185D"),
    ("user_vehicles", "registered_plates", "1:N", "#BE185D"),
    ("vehicles", "registered_plates", "1:N", "#BE185D"),
    ("users", "gate_logs", "1:N", "#B45309"),
    ("visitors", "gate_logs", "1:N", "#B45309"),
    ("parking_areas", "parking_slots", "1:N", "#0F766E"),
    ("users", "parking_slots", "park", "#0F766E"),
    ("visitors", "parking_slots", "park", "#0F766E"),
    ("users", "violations_log", "1:N", "#B91C1C"),
    ("violation_types", "violations_log", "ref", "#B91C1C"),
    ("violation_sanctions", "violations_log", "ref", "#B91C1C"),
]

for a, b, lab, col in rels:
    draw_rel(a, b, lab, col)

for name, e in entities.items():
    x, y = e["xy"]
    w, h = e["wh"]
    draw.rounded_rectangle([x + 3, y + 3, x + w + 3, y + h + 3], radius=6, fill="#E5E7EB")
    draw.rounded_rectangle([x, y, x + w, y + h], radius=6, fill=e["fill"], outline=e["stroke"], width=2)
    draw.rounded_rectangle([x, y, x + w, y + 20], radius=6, fill=e["stroke"])
    draw.rectangle([x, y + 10, x + w, y + 20], fill=e["stroke"])
    draw.text((x + 6, y + 2), name, fill="#FFFFFF", font=font_box)
    fy = y + 24
    for field in e["fields"]:
        draw.text((x + 6, fy), field, fill="#1F2937", font=font_pk)
        fy += 13

legend_y = 720
draw.rectangle([40, legend_y, 2360, 1510], fill="#FAFAFA", outline="#D1D5DB", width=1)
draw.text((55, legend_y + 8), "All collections (tables) & color groups", fill="#111827", font=font_legend_b)

legend_items = [
    ("Lookup / master", "#E8F1FF", "#2F5FA8", "user_roles, departments, vehicles"),
    ("Core identity", "#DCFCE7", "#15803D", "users, user_vehicles, user_suspensions"),
    ("Visitors / RFID", "#E0E7FF", "#4338CA", "visitors, visitor_rfid_cards"),
    ("Plate registry", "#FCE7F3", "#BE185D", "registered_plates"),
    ("Parking", "#CCFBF1", "#0F766E", "parking_areas → parking_slots"),
    ("Access / alerts", "#FEF3C7", "#B45309", "gate_logs, notifications"),
    ("Violations", "#FEE2E2", "#B91C1C", "violations_log, violation_types, violation_sanctions"),
    (
        "Content / config",
        "#F3F4F6",
        "#6B7280",
        "parking_rules, general_informations, stalled_vehicles, role_permissions, system_settings",
    ),
]

lx, ly = 55, legend_y + 34
for title, fill, stroke, desc in legend_items:
    draw.rounded_rectangle([lx, ly, lx + 20, ly + 14], radius=3, fill=fill, outline=stroke, width=2)
    draw.text((lx + 28, ly - 1), f"{title}: {desc}", fill="#1F2937", font=font_legend)
    ly += 22

rx = 1260
ry = legend_y + 34
draw.text((rx, legend_y + 8), "Key FK connections", fill="#111827", font=font_legend_b)
key_rels = [
    "users.user_role_id → user_roles.id",
    "users.department_code → departments.departmentcode",
    "users / user_vehicles / visitors.vehicle_id → vehicles.id",
    "user_vehicles.user_id → users.id",
    "visitors.registered_by → users.id | visitors ↔ visitor_rfid_cards (1:1)",
    "registered_plates.owner_id + owner_type → users | visitors",
    "registered_plates.user_vehicle_id / visitor_id / vehicle_id → related docs",
    "parking_slots.area_id → parking_areas.id | parked_user_id / parked_visitor_id",
    "gate_logs.user_id → users | gate_logs.visitor_id → visitors",
    "notifications.user_id / sender_id → users | violations_log.user_id → users",
    "user_suspensions.user_id → users | visitor_rfid_cards.created_by → users",
    "Standalone: parking_rules, general_informations, stalled_vehicles,",
    "system_settings, role_permissions (role_name soft-link)",
]
for line in key_rels:
    draw.text((rx, ry), "• " + line, fill="#1F2937", font=font_legend)
    ry += 20

img.save(img_path, "PNG", optimize=True)
print("saved", img_path, img.size)

doc = Document()
section = doc.sections[0]
section.orientation = WD_ORIENT.LANDSCAPE
section.page_width = Inches(11)
section.page_height = Inches(8.5)
section.left_margin = Inches(0.35)
section.right_margin = Inches(0.35)
section.top_margin = Inches(0.3)
section.bottom_margin = Inches(0.3)

p = doc.add_paragraph()
p.alignment = WD_ALIGN_PARAGRAPH.CENTER
p.paragraph_format.space_before = Pt(0)
p.paragraph_format.space_after = Pt(0)
run = p.add_run()
run.add_picture(str(img_path), width=Inches(10.25))

doc.save(docx_path)
print("saved", docx_path)
