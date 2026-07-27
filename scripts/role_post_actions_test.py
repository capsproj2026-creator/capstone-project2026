import re
import sys
import urllib.parse
import urllib.request
import http.cookiejar
import json
from pathlib import Path


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


def fetch(url: str, opener: urllib.request.OpenerDirector, data: bytes | None = None, headers: dict[str, str] | None = None):
    req = urllib.request.Request(url, data=data, headers=headers or {})
    try:
        resp = opener.open(req, timeout=25)
        return resp.getcode(), dict(resp.headers), resp.read().decode("utf-8", errors="replace")
    except urllib.error.HTTPError as e:
        body = e.read().decode("utf-8", errors="replace")
        return e.code, dict(e.headers), body


def extract_csrf(html: str) -> str:
    m = re.search(r'name="_token"\s+value="([^"]+)"', html)
    if not m:
        raise RuntimeError("CSRF token not found in HTML")
    return m.group(1)


def login(base: str, email: str, password: str):
    cj = http.cookiejar.CookieJar()
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))
    no_redirect_opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj), NoRedirect())

    s, _, html = fetch(f"{base}/login", opener)
    if s != 200:
        raise RuntimeError(f"GET /login failed with {s}")
    token = extract_csrf(html)

    form = urllib.parse.urlencode({"_token": token, "email": email, "password": password}).encode("utf-8")
    s2, h2, _ = fetch(
        f"{base}/login",
        no_redirect_opener,
        data=form,
        headers={"Content-Type": "application/x-www-form-urlencoded"},
    )
    return cj, opener, s2, h2.get("Location")


def get_csrf_from_page(base: str, opener: urllib.request.OpenerDirector, path: str) -> str:
    url = urllib.parse.urljoin(base + "/", path.lstrip("/"))
    s, _, html = fetch(url, opener)
    if s != 200:
        raise RuntimeError(f"GET {path} failed with {s}")
    return extract_csrf(html)


def post_form(base: str, opener: urllib.request.OpenerDirector, path: str, data: dict[str, str]):
    url = urllib.parse.urljoin(base + "/", path.lstrip("/"))
    body = urllib.parse.urlencode(data).encode("utf-8")
    # Use the same cookie jar but disable redirects so we can assert Location headers.
    # opener.handlers contains instantiated handlers which don't rebuild cleanly; use a new opener instead.
    cj = None
    for h in opener.handlers:
        if isinstance(h, urllib.request.HTTPCookieProcessor):
            cj = h.cookiejar
            break
    if cj is None:
        raise RuntimeError("CookieJar not found on opener")
    no_redirect_opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj), NoRedirect())
    return fetch(url, no_redirect_opener, data=body, headers={"Content-Type": "application/x-www-form-urlencoded"})


def get_path(base: str, opener: urllib.request.OpenerDirector, path: str):
    url = urllib.parse.urljoin(base + "/", path.lstrip("/"))
    return fetch(url, opener)


def main():
    base = sys.argv[1].rstrip("/") if len(sys.argv) > 1 else "http://127.0.0.1:8001"
    failures = 0
    ids_path = Path(__file__).with_name("test_user_ids.json")
    ids = json.loads(ids_path.read_text(encoding="utf-8")) if ids_path.exists() else {}
    pending_student_id = str(ids.get("pending_student", 5))
    pending_staff_id = str(ids.get("pending_staff", 6))

    # --- Admin actions ---
    _, admin_opener, s, loc = login(base, "admin@cspc.edu", "admin123")
    print(f"[admin] POST /login -> {s} Location={loc}")

    # Approve a pending student (id=5), decline a pending staff (id=6)
    for action_path in [f"/admin/registrations/{pending_student_id}/approve", f"/admin/registrations/{pending_staff_id}/decline"]:
        s3, _, _ = get_path(base, admin_opener, action_path)
        print(f"[admin] GET {action_path} -> {s3}")
        if s3 >= 500:
            failures += 1
        if s3 == 404:
            failures += 1

    # RFID grant/deny (POST)
    try:
        token = get_csrf_from_page(base, admin_opener, "/admin/rfid")
        s4, h4, _ = post_form(base, admin_opener, "/admin/rfid", {"_token": token, "user_id": pending_student_id, "action": "grant", "tab": "Pending"})
        print(f"[admin] POST /admin/rfid grant -> {s4} Location={h4.get('Location')}")
        if s4 not in (302, 303):
            failures += 1
    except Exception as e:
        print(f"[admin] POST /admin/rfid grant -> ERROR {e}")
        failures += 1

    # Settings updates (POST) - empty payloads should still succeed
    try:
        token = get_csrf_from_page(base, admin_opener, "/admin/settings")
        s5, h5, _ = post_form(base, admin_opener, "/admin/settings/general", {"_token": token})
        print(f"[admin] POST /admin/settings/general -> {s5} Location={h5.get('Location')}")
        if s5 not in (302, 303):
            failures += 1
        token2 = get_csrf_from_page(base, admin_opener, "/admin/settings")
        s6, h6, _ = post_form(base, admin_opener, "/admin/settings/violations", {"_token": token2})
        print(f"[admin] POST /admin/settings/violations -> {s6} Location={h6.get('Location')}")
        if s6 not in (302, 303):
            failures += 1
    except Exception as e:
        print(f"[admin] POST /admin/settings/* -> ERROR {e}")
        failures += 1

    # --- Guard actions ---
    _, guard_opener, s, loc = login(base, "guard@cspc.edu", "password123")
    print(f"[guard] POST /login -> {s} Location={loc}")
    try:
        token = get_csrf_from_page(base, guard_opener, "/guard/violations")
        s7, h7, _ = post_form(
            base,
            guard_opener,
            "/guard/violations",
            {"_token": token, "plate_number": "TEST-123", "violation_type": "Wrong Parking", "description": "Automated smoke test"},
        )
        print(f"[guard] POST /guard/violations -> {s7} Location={h7.get('Location')}")
        if s7 not in (302, 303):
            failures += 1
    except Exception as e:
        print(f"[guard] POST /guard/violations -> ERROR {e}")
        failures += 1

    # --- User actions ---
    _, user_opener, s, loc = login(base, "student@cspc.edu", "password123")
    print(f"[user] POST /login -> {s} Location={loc}")

    for action_path in ["/user/notifications/mark_all_read", "/user/notifications/clear_all"]:
        s8, _, _ = get_path(base, user_opener, action_path)
        print(f"[user] GET {action_path} -> {s8}")
        if s8 >= 500:
            failures += 1

    # Profile update + password change (POST)
    try:
        token = get_csrf_from_page(base, user_opener, "/profile")
        s9, h9, _ = post_form(
            base,
            user_opener,
            "/profile",
            {"_token": token, "update_profile": "1", "fullname": "Test Student", "phone_number": "09000000000", "email": "student@cspc.edu"},
        )
        print(f"[user] POST /profile update_profile -> {s9} Location={h9.get('Location')}")
        if s9 not in (302, 303):
            failures += 1

        token2 = get_csrf_from_page(base, user_opener, "/profile")
        s10, h10, _ = post_form(
            base,
            user_opener,
            "/profile",
            {
                "_token": token2,
                "change_password": "1",
                "current_password": "password123",
                "new_password": "password123",
                "new_password_confirmation": "password123",
            },
        )
        print(f"[user] POST /profile change_password -> {s10} Location={h10.get('Location')}")
        if s10 not in (302, 303):
            failures += 1
    except Exception as e:
        print(f"[user] POST /profile -> ERROR {e}")
        failures += 1

    sys.exit(1 if failures else 0)


if __name__ == "__main__":
    main()

