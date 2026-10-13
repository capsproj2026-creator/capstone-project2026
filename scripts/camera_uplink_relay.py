#!/usr/bin/env python3
"""Receive the campus camera feed and serve it on 127.0.0.1:18091.

nginx sends viewers to the SSH tunnel on port 18090 first. This relay is only
used when nothing is listening there. It passes the JPEG frames through as-is.
"""

from __future__ import annotations

import json
import socket
import threading
import time
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path

LISTEN = ("127.0.0.1", 18091)
ENV_PATH = Path("/var/www/smart-campus/.env")
TUNNEL = ("127.0.0.1", 18090)
FRAME_TTL = 8.0
HEALTH_TTL = 12.0
WANTED_TTL = 30.0


def load_token() -> str:
    if not ENV_PATH.is_file():
        return ""
    for line in ENV_PATH.read_text(encoding="utf-8", errors="replace").splitlines():
        if line.startswith("AI_PARKING_API_TOKEN="):
            return line.split("=", 1)[1].strip().strip('"').strip("'")
    return ""


TOKEN = load_token()


def stream_path_for(path: str) -> str | None:
    if path.endswith(".mjpg"):
        return path
    for name in ("/snapshot.jpg", "/frame.jpg"):
        if path.endswith(name):
            return path[: -len(name)] + "/stream.mjpg"
    return None


class Hub:
    def __init__(self) -> None:
        self._lock = threading.Lock()
        self._cond = threading.Condition(self._lock)
        self._frames: dict[str, tuple[int, bytes, float]] = {}
        self._seq = 0
        self._health: tuple[bytes, float] | None = None
        self._wanted: dict[str, float] = {}

    def store_frame(self, path: str, jpeg: bytes) -> None:
        with self._cond:
            self._seq += 1
            self._frames[path] = (self._seq, jpeg, time.monotonic())
            self._cond.notify_all()

    def store_health(self, body: bytes) -> None:
        with self._lock:
            self._health = (body, time.monotonic())

    def health_body(self) -> bytes | None:
        with self._lock:
            if self._health and (time.monotonic() - self._health[1]) <= HEALTH_TTL:
                return self._health[0]
        return None

    def want(self, path: str) -> None:
        with self._lock:
            self._wanted[path] = time.monotonic()

    def wanted(self) -> list[str]:
        now = time.monotonic()
        with self._lock:
            for path in [p for p, at in self._wanted.items() if now - at > WANTED_TTL]:
                del self._wanted[path]
            return sorted(self._wanted)

    def latest(self, path: str) -> bytes | None:
        with self._lock:
            row = self._frames.get(path)
            if row and (time.monotonic() - row[2]) <= FRAME_TTL:
                return row[1]
        return None

    def wait_frame(self, path: str, last_seq: int, timeout: float) -> tuple[int, bytes] | None:
        deadline = time.monotonic() + timeout
        with self._cond:
            while True:
                row = self._frames.get(path)
                if row and row[0] != last_seq and (time.monotonic() - row[2]) <= FRAME_TTL:
                    return row[0], row[1]
                remaining = deadline - time.monotonic()
                if remaining <= 0:
                    return None
                self._cond.wait(remaining)


HUB = Hub()


def tunnel_is_up() -> bool:
    # A listener on 18090 means the PC's SSH tunnel is connected. Its /health
    # can take seconds while video is playing, so do not judge by that.
    try:
        with socket.create_connection(TUNNEL, timeout=1.0):
            return True
    except OSError:
        return False


def authorized(handler: BaseHTTPRequestHandler) -> bool:
    if not TOKEN:
        return False
    got = (handler.headers.get("X-AI-TOKEN") or "").strip()
    if len(got) != len(TOKEN):
        return False
    diff = 0
    for a, b in zip(got, TOKEN):
        diff |= ord(a) ^ ord(b)
    return diff == 0


def read_body(handler: BaseHTTPRequestHandler):
    encoding = (handler.headers.get("Transfer-Encoding") or "").lower()
    if "chunked" in encoding:
        while True:
            line = handler.rfile.readline()
            if not line:
                return
            size = int(line.strip().split(b";", 1)[0] or b"0", 16)
            if size == 0:
                handler.rfile.readline()
                return
            chunk = handler.rfile.read(size)
            handler.rfile.read(2)
            if chunk:
                yield chunk
        return
    length = int(handler.headers.get("Content-Length") or 0)
    remaining = length
    while remaining > 0:
        chunk = handler.rfile.read(min(65536, remaining))
        if not chunk:
            return
        remaining -= len(chunk)
        yield chunk


def parse_jpegs(chunks, on_jpeg) -> None:
    buf = b""
    marker = b"\r\n\r\n"
    for chunk in chunks:
        buf += chunk
        while True:
            header_at = buf.find(b"Content-Length:")
            if header_at < 0:
                if len(buf) > 2_000_000:
                    buf = buf[-4096:]
                break
            split = buf.find(marker, header_at)
            if split < 0:
                break
            header = buf[header_at:split].decode("latin1", "replace")
            length = 0
            for line in header.split("\r\n"):
                if line.lower().startswith("content-length:"):
                    length = int(line.split(":", 1)[1].strip() or 0)
            start = split + len(marker)
            end = start + length
            if len(buf) < end:
                break
            jpeg = buf[start:end]
            buf = buf[end:]
            if jpeg.startswith(b"\xff\xd8"):
                on_jpeg(jpeg)


class Handler(BaseHTTPRequestHandler):
    protocol_version = "HTTP/1.1"

    def log_message(self, fmt: str, *args) -> None:
        return

    def _json(self, code: int, payload: dict) -> None:
        body = json.dumps(payload).encode("utf-8")
        self.send_response(code)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(body)))
        self.send_header("Cache-Control", "no-store")
        self.end_headers()
        self.wfile.write(body)

    def _bytes(self, content_type: str, body: bytes) -> None:
        self.send_response(200)
        self.send_header("Content-Type", content_type)
        self.send_header("Content-Length", str(len(body)))
        self.send_header("Cache-Control", "no-store, private")
        self.end_headers()
        self.wfile.write(body)

    def do_GET(self) -> None:
        path = self.path.split("?", 1)[0]
        if path == "/status":
            if not authorized(self):
                self._json(401, {"ok": False})
                return
            self._json(200, {"ok": True, "tunnel": tunnel_is_up(), "wanted": HUB.wanted()})
            return
        if path == "/health":
            body = HUB.health_body()
            if body is None:
                self._json(503, {"ok": False})
                return
            self._bytes("application/json", body)
            return

        stream_path = stream_path_for(path)
        if stream_path is None:
            self.send_error(404)
            return
        HUB.want(stream_path)

        if not path.endswith(".mjpg"):
            jpeg = HUB.latest(stream_path)
            if jpeg is None:
                self.send_error(503)
                return
            self._bytes("image/jpeg", jpeg)
            return

        self.send_response(200)
        self.send_header("Cache-Control", "no-cache, no-store, private")
        self.send_header("Content-Type", "multipart/x-mixed-replace; boundary=frame")
        self.send_header("Connection", "close")
        self.end_headers()
        self.close_connection = True
        last = 0
        try:
            while True:
                HUB.want(stream_path)
                row = HUB.wait_frame(stream_path, last, 3)
                if row is None:
                    # End the response so the viewer reconnects through nginx,
                    # which sends it back to the tunnel once that is up again.
                    if HUB.health_body() is None or tunnel_is_up():
                        break
                    continue
                last, jpeg = row
                self.wfile.write(b"--frame\r\n")
                self.wfile.write(b"Content-Type: image/jpeg\r\n")
                self.wfile.write(f"Content-Length: {len(jpeg)}\r\n\r\n".encode("ascii"))
                self.wfile.write(jpeg)
                self.wfile.write(b"\r\n")
                self.wfile.flush()
        except (BrokenPipeError, ConnectionResetError, OSError):
            return

    def do_POST(self) -> None:
        path = self.path.split("?", 1)[0]
        if not authorized(self):
            self._json(401, {"ok": False})
            return
        if path == "/health":
            raw = b"".join(read_body(self))
            if len(raw) > 65536:
                self._json(413, {"ok": False})
                return
            HUB.store_health(raw)
            self._json(200, {"ok": True})
            return
        if path != "/push":
            self.send_error(404)
            return
        stream_path = (self.headers.get("X-Stream-Path") or "").split("?", 1)[0]
        if not stream_path.startswith("/") or ".." in stream_path or not stream_path.endswith(".mjpg"):
            self._json(422, {"ok": False})
            return

        def on_jpeg(jpeg: bytes) -> None:
            HUB.store_frame(stream_path, jpeg)

        self.close_connection = True
        try:
            parse_jpegs(read_body(self), on_jpeg)
            self._json(200, {"ok": True})
        except (ValueError, OSError):
            pass


class Server(ThreadingHTTPServer):
    daemon_threads = True


def main() -> None:
    if not TOKEN:
        raise SystemExit("AI_PARKING_API_TOKEN is missing")
    server = Server(LISTEN, Handler)
    print("camera uplink relay on 127.0.0.1:18091", flush=True)
    server.serve_forever()


if __name__ == "__main__":
    main()
