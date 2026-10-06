# Live camera WebRTC

The Live Cameras page can play each enabled camera with WebRTC. YOLO, OCR, and the motion gate stay in `hardware/ai_parking/ai_parking_service.py` and keep their own RTSP connection. Laravel Reverb is still the event channel. It does not carry video.

## When this is off

Leave `MEDIAMTX_WHEP_BASE` empty. Live Cameras keeps the existing MJPEG tiles from port 8090. That address is reachable only on the campus PC.

## When this is on

```text
Camera RTSP (preview substream)
    → MediaMTX on the campus PC
    → WebRTC WHEP
    → Live Cameras <video>
```

The AI path is unchanged:

```text
Camera RTSP (main stream)
    → open_rtsp()
    → motion gate
    → YOLO
    → plate OCR
    → POST /api/ai-parking/*
```

The VPS SSH tunnel forwards port 8090 only. It does not forward camera port 554. MediaMTX must run on a machine that can reach the camera IPs (the campus PC). Do not publish port 554 on the internet.

## Setup

1. Download [MediaMTX 1.11.3](https://github.com/bluenviron/mediamtx/releases/tag/v1.11.3) and unzip `mediamtx.exe` next to the project or anywhere on `PATH`.
2. Put each camera password in `.env` (`AI_CAMERA_1_PASS`, and the same for cameras 2 and 3). Do not commit `.env`.
3. Generate the local config (this file contains passwords and is gitignored):

```powershell
python scripts/write_mediamtx_config.py
```

4. In `.env`, set a WebRTC base the **browser** can open. On this PC:

```env
MEDIAMTX_WHEP_BASE=http://127.0.0.1:8889
```

For a phone on the campus Wi-Fi, use the PC's LAN address instead, and add that address to `MEDIAMTX_WEBRTC_ADDITIONAL_HOSTS`, then regenerate the config.

5. Start MediaMTX, then reload Laravel config:

```powershell
mediamtx storage\app\mediamtx\mediamtx.yml
php artisan config:clear
```

6. Open Live Cameras (`/admin` live cameras or `/guard` live cameras). Each tile should move from `CONNECTING` to `LIVE` without opening a Python video page.

Ports used by MediaMTX in the generated file: **8889/tcp** (WHEP) and **8189/udp** (default WebRTC media). The Python AI service stays on **8090**. Reverb stays on **8080**.

## Camera paths

| Camera | MediaMTX path | Preview RTSP |
|---|---|---|
| CAM-AI-1 Dahua | `cam-ai-1` | `/cam/realmonitor?channel=1&subtype=1` |
| CAM-AI-2 Tapo | `cam-ai-2` | `/stream2` |
| CAM-AI-3 Tapo | `cam-ai-3` | `/stream2` |

WHEP URL shape: `{MEDIAMTX_WHEP_BASE}/cam-ai-1/whep`. The browser never receives the RTSP user or password.

## Public site

A browser on the internet cannot open `http://127.0.0.1:8889`. Point `MEDIAMTX_WHEP_BASE` at a host that browser can reach, and set `MEDIAMTX_WEBRTC_ADDITIONAL_HOSTS` to that host. Reaching the cameras from the VPS still needs a VPN into the campus LAN. The current SSH reverse tunnel is not that VPN.

The Guard AI monitor keeps the YOLO overlay MJPEG. Only the clean Live Cameras page switches to WebRTC.
