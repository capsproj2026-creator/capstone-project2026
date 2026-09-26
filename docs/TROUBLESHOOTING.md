# ISCVMS Troubleshooting

## Web app not opening

| | |
|--|--|
| **Symptom** | Browser cannot load :8000/:8001 |
| **Likely cause** | Services not started; port conflict; PHP missing |
| **Check** | `.\scripts\status-system.ps1`; `Get-Command php` |
| **Fix** | `.\scripts\start-system.ps1 -SkipAi`; free ports with `stop-system.ps1` then restart |

## Database unavailable

| | |
|--|--|
| **Symptom** | Amber/red “Database unavailable” page or API 503 `database_unavailable` |
| **Likely cause** | MongoDB/Atlas down; bad `MONGODB_URI` |
| **Check** | `php artisan capstone:db-status`; Admin System Health |
| **Fix** | Start local Mongo service or fix Atlas URI/network; `config:clear` |

## AI offline

| | |
|--|--|
| **Symptom** | No boxes/plates; System Health AI offline |
| **Likely cause** | Python service not running; wrong token; Laravel base URL |
| **Check** | `http://127.0.0.1:8090/health`; AI terminal window |
| **Fix** | `.\start.ps1 -SkipWebStack` or restart with AI; ensure `AI_PARKING_API_TOKEN` matches `.env` |

## Camera black / offline

| | |
|--|--|
| **Symptom** | Stream unavailable tile |
| **Likely cause** | RTSP credentials/IP; network; AI not ingesting |
| **Check** | `.env` `AI_CAMERA_*`; `php artisan ai-parking:check --probe-stream` |
| **Fix** | Fix IP/user/pass/path; AI reconnects with backoff (`AI_CAMERA_RECONNECT_SEC`) |

## YOLO not detecting

| | |
|--|--|
| **Symptom** | Live video but no boxes |
| **Likely cause** | Model missing; confidence too high; vehicle outside frame |
| **Check** | `hardware/ai_parking/models/*.pt`; AI logs `detect raw=` |
| **Fix** | Run setup-yolov9; lower `AI_PARKING_CONF` slightly; check lighting |

## Plate OCR failing

| | |
|--|--|
| **Symptom** | Scanning… then not_read / wrong plate |
| **Likely cause** | Blurry plate; OCR timeout; truncated plate logic |
| **Check** | AI OCR debug lines; Guard manual Add plate |
| **Fix** | Improve angle/lighting; use manual plate; tune `AI_PARKING_OCR_*` |

## Parking calibration problem

| | |
|--|--|
| **Symptom** | Wrong slots occupied |
| **Likely cause** | Zones file not for this camera; not calibrated |
| **Check** | `AI_CAMERA_n_ZONES`; JSON `calibrated` |
| **Fix** | Re-run calibrate script; restart AI to reload |

## Duplicate detections

| | |
|--|--|
| **Symptom** | Same car twice in Latest Detections |
| **Likely cause** | Track ID churn; ghost sessions |
| **Check** | Different track #s same plate |
| **Fix** | Restart AI to clear sessions; ensure grace/hold settings; avoid moving camera |

## RFID not recognized / ESP32 disconnected

| | |
|--|--|
| **Symptom** | No scan on Gate Monitor; gate offline |
| **Likely cause** | Token mismatch; wrong API host; firewall; :8000 down |
| **Check** | `php artisan capstone:gate-status`; Serial log; token in firmware vs `.env` |
| **Fix** | Match token; point `API_HOST` to PC LAN IP; run firewall bat; start LAN front |

## Barrier not opening

| | |
|--|--|
| **Symptom** | Grant on UI but servo idle |
| **Likely cause** | Heartbeat not delivering open; wrong gate_id; servo wiring |
| **Check** | Heartbeat responses; `RFID_SHARED_BOOM_GATE_ID` |
| **Fix** | Entry ESP32 must receive heartbeat; test servo angles in firmware |

## Email / OTP failure

| | |
|--|--|
| **Symptom** | Verification mail not sent |
| **Likely cause** | SMTP credentials; Brevo limits |
| **Check** | `storage/logs/laravel.log`; `php artisan mail:test` |
| **Fix** | Fix `MAIL_*`; scheduler `email:retry-verification` |

## Queue / realtime issue

| | |
|--|--|
| **Symptom** | UI stale until refresh |
| **Likely cause** | Reverb down (queue is sync — not a worker issue) |
| **Check** | Port 8080; Reverb tab |
| **Fix** | Restart Reverb; monitors also poll HTTP as fallback |

## Port conflict

| | |
|--|--|
| **Symptom** | Start fails; wrong process on 8000/8001/8090 |
| **Check** | `status-system.ps1`; `netstat -ano \| findstr :8000` |
| **Fix** | `stop-system.ps1` (safe for ISCVMS processes only) then start again |
