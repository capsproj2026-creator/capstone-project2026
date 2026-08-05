"""Laravel plate → owner lookup for overlay cache (non-blocking)."""

from __future__ import annotations

import json
import os
import threading
from typing import TYPE_CHECKING, Callable, Optional
from urllib import request as urlrequest
from urllib.error import HTTPError, URLError

if TYPE_CHECKING:
    from parking_rules import TrackMemory

API_BASE = os.getenv("AI_LARAVEL_API_BASE", "http://127.0.0.1:8000").rstrip("/")
AI_API_TOKEN = os.getenv("AI_PARKING_API_TOKEN", "capstone-ai-parking-dev-token-change-me")
LOOKUP_TIMEOUT_SEC = float(os.getenv("AI_PARKING_LOOKUP_TIMEOUT_SEC", "2.5"))

_logged_fail = False
_fail_lock = threading.Lock()


def lookup_plate(plate: str) -> Optional[dict]:
    """Synchronous POST /api/ai-parking/plate-lookup. Returns identity dict or None."""
    global _logged_fail
    plate = (plate or "").strip().upper()
    if not plate:
        return None

    body = json.dumps({"plate": plate}).encode("utf-8")
    req = urlrequest.Request(
        f"{API_BASE}/api/ai-parking/plate-lookup",
        data=body,
        headers={
            "Content-Type": "application/json",
            "X-AI-TOKEN": AI_API_TOKEN,
            "Accept": "application/json",
        },
        method="POST",
    )
    try:
        with urlrequest.urlopen(req, timeout=LOOKUP_TIMEOUT_SEC) as resp:
            payload = json.loads(resp.read().decode("utf-8"))
            data = payload.get("data") if isinstance(payload, dict) else None
            return data if isinstance(data, dict) else None
    except (HTTPError, URLError, TimeoutError, json.JSONDecodeError, OSError) as e:
        with _fail_lock:
            if not _logged_fail:
                print(f"Plate lookup failed (will retry quietly): {e}")
                _logged_fail = True
        return None


def lookup_plate_async(mem: "TrackMemory", on_done: Optional[Callable[[], None]] = None) -> None:
    """Fire-and-forget owner lookup once plate is locked on a track."""
    if not mem.needs_owner_lookup():
        return
    plate = mem.plate
    if not plate:
        return
    mem.lookup_pending = True

    def _run():
        try:
            data = lookup_plate(plate)
            # Track may have changed plate while we waited.
            if mem.plate == plate and mem.plate_status == "ok":
                mem.apply_owner_lookup(data)
            else:
                mem.lookup_pending = False
        except Exception as e:
            mem.lookup_pending = False
            print(f"Plate lookup worker error: {e}")
        finally:
            if on_done:
                on_done()

    threading.Thread(target=_run, daemon=True, name="plate-owner-lookup").start()
