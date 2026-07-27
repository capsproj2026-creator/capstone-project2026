import re
import sys
import urllib.parse
import urllib.request
import http.cookiejar


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


def fetch(url: str, opener: urllib.request.OpenerDirector, data: bytes | None = None, headers: dict[str, str] | None = None):
    req = urllib.request.Request(url, data=data, headers=headers or {})
    try:
        resp = opener.open(req, timeout=20)
        return resp.getcode(), dict(resp.headers), resp.read().decode("utf-8", errors="replace")
    except urllib.error.HTTPError as e:
        body = e.read().decode("utf-8", errors="replace")
        return e.code, dict(e.headers), body


def extract_csrf(html: str) -> str:
    m = re.search(r'name="_token"\s+value="([^"]+)"', html)
    if not m:
        raise RuntimeError("CSRF token not found in /login HTML")
    return m.group(1)


def login(base: str, email: str, password: str):
    cj = http.cookiejar.CookieJar()
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))
    no_redirect_opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj), NoRedirect())

    status, _, html = fetch(f"{base}/login", opener)
    if status != 200:
        raise RuntimeError(f"GET /login failed with {status}")
    token = extract_csrf(html)

    form = urllib.parse.urlencode({"_token": token, "email": email, "password": password}).encode("utf-8")
    status, headers, _ = fetch(
        f"{base}/login",
        no_redirect_opener,
        data=form,
        headers={"Content-Type": "application/x-www-form-urlencoded"},
    )
    return opener, status, headers.get("Location")


def hit(base: str, opener: urllib.request.OpenerDirector, path: str):
    url = urllib.parse.urljoin(base + "/", path.lstrip("/"))
    status, _, _ = fetch(url, opener)
    return status


def main():
    base = sys.argv[1].rstrip("/") if len(sys.argv) > 1 else "http://127.0.0.1:8001"

    roles = [
        ("admin", "admin@cspc.edu", "admin123", ["/admin", "/admin/registrations", "/admin/users", "/admin/rfid", "/admin/parking", "/admin/settings"]),
        ("guard", "guard@cspc.edu", "password123", ["/guard", "/guard/violations", "/guard/parking", "/guard/access-logs", "/guard/live-cameras", "/guard/ai-parking", "/guard/monitor", "/guard/gate"]),
        ("student", "student@cspc.edu", "password123", ["/user", "/user/notifications", "/user/entry-exit", "/user/parking"]),
        ("staff", "staff@cspc.edu", "password123", ["/user", "/user/notifications", "/user/entry-exit", "/user/parking"]),
    ]

    failures = 0

    for role, email, password, paths in roles:
        opener, post_status, loc = login(base, email, password)
        print(f"[{role}] POST /login -> {post_status}" + (f" Location={loc}" if loc else ""))
        if post_status not in (302, 303):
            failures += 1
            continue

        for p in paths:
            s = hit(base, opener, p)
            print(f"[{role}] GET {p} -> {s}")
            if s >= 500:
                failures += 1

    sys.exit(1 if failures else 0)


if __name__ == "__main__":
    main()

