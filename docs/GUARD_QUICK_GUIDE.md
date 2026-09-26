# ISCVMS Guard Quick Guide

## Login

Sign in with your Guard account. Open **http://127.0.0.1:8001** on the ops PC (or LAN `:8000`).

## Daily screens

| Screen | Use |
|--------|-----|
| Live Gate Monitor | See who just tapped; ESP32 online status |
| User Monitor | Look up campus users |
| Register / Active Visitors | Walk-in visitors |
| AI Parking Monitor | Live cameras + Latest Detections + plates |
| Plate Lookup | Manual plate search |
| Violations / Access Logs | Review incidents |
| Live Cameras / Parking | Feeds and lot status |

## AI Parking Monitor tips

- **Parked / Scanning…** — OCR in progress
- **Unregistered** — plate read but not in campus vehicles
- **Unknown** — waiting on owner lookup
- **Add/Edit plate** — manual correction when OCR fails
- Amber **Server offline** banner — Laravel not reachable; streams may still work if AI is up
- Camera tile offline — RTSP/AI issue; check System Health with Admin

## Gate / RFID

- Valid tap → grant + boom (Entry servo)
- Invalid / denied → show on monitor; no boom
- If ESP32 shows offline: check WiFi, token, PC firewall, LAN `:8000`

Keep it simple: when unsure, verify plate/UID in Plate Lookup or User Monitor and escalate to Admin.
