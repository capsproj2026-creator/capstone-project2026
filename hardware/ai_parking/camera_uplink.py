#!/usr/bin/env python3
"""Send the local camera pictures to iscvms.com when the SSH tunnel is down.

This only reads the pictures the camera service is already serving on port 8090.
It does not open the cameras again, it stays idle while the tunnel is up, and it
only sends the streams someone on the site is watching.
"""

from __future__ import annotations

import json
import threading
import time
from http.client import HTTPSConnection
from pathlib import Path
from urllib.error import URLError
from urllib.request import Request, urlopen

ROOT = Path(__file__).resolve().parents[2]
LOCAL = "http://127.0.0.1:8090"
SITE = "www.iscvms.com"


def load_token() -> str:
    env = ROOT / ".env"
    if not env.is_file():
        return ""
    for line in env.read_text(encoding="utf-8", errors="replace").splitlines():
        if line.startswith("AI_PARKING_API_TOKEN="):
            return line.split("=", 1)[1].strip().strip('"').strip("'")
    return ""


TOKEN = load_token()


def log(message: str) -> None:
    print(time.strftime("%H:%M:%S"), message, flush=True)


def local_health() -> dict | None:
    try:
        with urlopen(LOCAL + "/health", timeout=4) as response:
            body = json.loads(response.read().decode("utf-8"))
    except (URLError, TimeoutError, OSError, ValueError):
        return None
    return body if isinstance(body, dict) else None


def site_status() -> dict | None:
    request = Request(f"https://{SITE}/camera-uplink/status", headers={"X-AI-TOKEN": TOKEN})
    try:
        with urlopen(request, timeout=8) as response:
            body = json.loads(response.read().decode("utf-8"))
    except (URLError, TimeoutError, OSError, ValueError):
        return None
    if not isinstance(body, dict) or not body.get("ok"):
        return None
    return body


def post_health(health: dict) -> None:
    raw = json.dumps(health).encode("utf-8")
    connection = HTTPSConnection(SITE, timeout=10)
    try:
        connection.request(
            "POST",
            "/camera-uplink/health",
            body=raw,
            headers={"X-AI-TOKEN": TOKEN, "Content-Type": "application/json"},
        )
        connection.getresponse().read()
    except OSError:
        pass
    finally:
        connection.close()


def push_once(path: str, stop: threading.Event) -> None:
    local = None
    connection = HTTPSConnection(SITE, timeout=30)
    try:
        local = urlopen(LOCAL + path, timeout=10)
        connection.putrequest("POST", "/camera-uplink/push")
        connection.putheader("X-AI-TOKEN", TOKEN)
        connection.putheader("X-Stream-Path", path)
        connection.putheader("Content-Type", "multipart/x-mixed-replace; boundary=frame")
        connection.putheader("Transfer-Encoding", "chunked")
        connection.endheaders()
        while not stop.is_set():
            chunk = local.read1(65536)
            if not chunk:
                break
            connection.send(f"{len(chunk):X}\r\n".encode("ascii") + chunk + b"\r\n")
        connection.send(b"0\r\n\r\n")
    except (URLError, TimeoutError, OSError, ValueError):
        pass
    finally:
        if local is not None:
            try:
                local.close()
            except OSError:
                pass
        connection.close()


def push_worker(path: str, stop: threading.Event) -> None:
    while not stop.is_set():
        push_once(path, stop)
        stop.wait(2)


def valid_path(path: object) -> bool:
    return isinstance(path, str) and path.startswith("/") and path.endswith(".mjpg") and ".." not in path


class Pushers:
    def __init__(self) -> None:
        self._workers: dict[str, tuple[threading.Thread, threading.Event]] = {}

    def sync(self, wanted: list[str]) -> None:
        for path in list(self._workers):
            if path not in wanted:
                self._workers.pop(path)[1].set()
        for path in wanted:
            row = self._workers.get(path)
            if row and row[0].is_alive():
                continue
            stop = threading.Event()
            thread = threading.Thread(target=push_worker, args=(path, stop), daemon=True, name=f"uplink{path}")
            self._workers[path] = (thread, stop)
            thread.start()

    def stop_all(self) -> None:
        self.sync([])


def main() -> None:
    if not TOKEN:
        raise SystemExit("AI_PARKING_API_TOKEN is missing from .env")
    log("Camera uplink ready. The live feed uses the tunnel whenever the tunnel is up.")
    pushers = Pushers()
    sending = False
    while True:
        try:
            health = local_health()
            status = site_status() if health else None
            should_send = bool(health and status and status.get("tunnel") is False)
            if should_send:
                if not sending:
                    log("Tunnel is down. Sending the camera feed to iscvms.com over HTTPS.")
                post_health(health)
                wanted = [p for p in status.get("wanted") or [] if valid_path(p)]
                pushers.sync(wanted)
            else:
                if sending:
                    reason = "Tunnel is up again." if status and status.get("tunnel") else "Camera service or site unreachable."
                    log(f"{reason} Uplink paused.")
                pushers.stop_all()
            sending = should_send
            time.sleep(3 if should_send else 5)
        except KeyboardInterrupt:
            pushers.stop_all()
            return
        except Exception as error:
            log(f"Uplink error: {error}")
            time.sleep(5)


if __name__ == "__main__":
    main()
